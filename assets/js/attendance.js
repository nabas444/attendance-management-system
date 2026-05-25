/**
 * attendance.js — AJAX attendance marking logic for take-attendance page
 */

// Global state for current session
const AttendanceApp = {
  studentStatuses: {}, // { studentId: 'present'|'absent'|'late'|'excused' }
  sessionId: null,
  csrfToken: "",
};

/**
 * Initializes the attendance marking UI.
 * @param {number} sessionId
 * @param {string} csrfToken
 */
function initAttendance(sessionId, csrfToken) {
  AttendanceApp.sessionId = sessionId;
  AttendanceApp.csrfToken = csrfToken;

  // Initialize all cards as absent
  document.querySelectorAll(".student-card").forEach((card) => {
    const sid = card.dataset.studentId;
    const existingStatus = card.dataset.currentStatus || "absent";
    AttendanceApp.studentStatuses[sid] = existingStatus;
    applyCardStyle(card, existingStatus);
  });

  updateSummary();
}

/**
 * Cycles a student card through: absent → present → late → excused → absent.
 * @param {HTMLElement} card
 */
function cycleStatus(card) {
  const sid = card.dataset.studentId;
  const cycle = ["absent", "present", "late", "excused"];
  const curr = AttendanceApp.studentStatuses[sid] || "absent";
  const nextIdx = (cycle.indexOf(curr) + 1) % cycle.length;
  const next = cycle[nextIdx];

  AttendanceApp.studentStatuses[sid] = next;
  applyCardStyle(card, next);
  updateSummary();
}

/**
 * Sets a specific status on a card (used by quick-set buttons).
 * @param {HTMLElement} card
 * @param {string} status
 */
function setStatus(card, status) {
  const sid = card.dataset.studentId;
  AttendanceApp.studentStatuses[sid] = status;
  applyCardStyle(card, status);
  updateSummary();
}

/**
 * Applies visual styling to a card based on status.
 * @param {HTMLElement} card
 * @param {string} status
 */
function applyCardStyle(card, status) {
  card.classList.remove(
    "status-present",
    "status-absent",
    "status-late",
    "status-excused",
  );
  card.classList.add("status-" + status);

  const badge = card.querySelector(".status-badge");
  if (badge) {
    const colors = {
      present: "bg-success text-white",
      absent: "bg-danger text-white",
      late: "bg-warning text-dark",
      excused: "bg-secondary text-white",
    };
    badge.className =
      "status-badge " + (colors[status] || "bg-secondary text-white");
    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
  }
}

/**
 * Updates the summary counts shown above the grid.
 */
function updateSummary() {
  const counts = { present: 0, absent: 0, late: 0, excused: 0 };
  Object.values(AttendanceApp.studentStatuses).forEach((s) => {
    if (counts[s] !== undefined) counts[s]++;
  });
  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };
  set("countPresent", counts.present);
  set("countAbsent", counts.absent);
  set("countLate", counts.late);
  set("countExcused", counts.excused);
}

/**
 * Sets all students to the same status.
 * @param {string} status
 */
function markAll(status) {
  document.querySelectorAll(".student-card").forEach((card) => {
    setStatus(card, status);
  });
}

/**
 * Submits attendance data via AJAX.
 * @param {string} apiUrl
 */
function submitAttendance(apiUrl) {
  if (!AttendanceApp.sessionId) {
    showToast("No session selected.", "danger");
    return;
  }

  const records = Object.entries(AttendanceApp.studentStatuses).map(
    ([sid, status]) => ({
      student_id: parseInt(sid, 10),
      status,
    }),
  );

  if (records.length === 0) {
    showToast("No students to mark.", "warning");
    return;
  }

  showSpinner();

  fetch(apiUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      session_id: AttendanceApp.sessionId,
      records,
      csrf_token: AttendanceApp.csrfToken,
    }),
  })
    .then((res) => res.json())
    .then((data) => {
      hideSpinner();
      if (data.success) {
        showToast("Attendance saved successfully!", "success");
        const btn = document.getElementById("submitAttendanceBtn");
        if (btn) {
          btn.textContent = "Saved ✓";
          btn.disabled = true;
        }
      } else {
        showToast(data.error || "Save failed.", "danger");
      }
    })
    .catch(() => {
      hideSpinner();
      showToast("Network error. Please try again.", "danger");
    });
}
