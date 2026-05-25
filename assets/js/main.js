/**
 * main.js — Shared JS: toast notifications, sidebar toggle, confirm modal
 */

// ── Toast notifications ────────────────────────────────────────────────────
/**
 * Shows a Bootstrap 5 toast notification.
 * @param {string} message
 * @param {'success'|'danger'|'warning'|'info'} type
 */
function showToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  if (!container) return;

  const icons = {
    success: "fa-circle-check",
    danger: "fa-circle-xmark",
    warning: "fa-triangle-exclamation",
    info: "fa-circle-info",
  };
  const icon = icons[type] || icons.info;

  const id = "toast-" + Date.now();
  const html = `
    <div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert" aria-live="assertive">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i class="fa-solid ${icon}"></i>
          <span>${message}</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>`;

  container.insertAdjacentHTML("beforeend", html);
  const el = document.getElementById(id);
  const toast = new bootstrap.Toast(el, { delay: 4000 });
  toast.show();
  el.addEventListener("hidden.bs.toast", () => el.remove());
}

// ── Spinner overlay ────────────────────────────────────────────────────────
function showSpinner() {
  let el = document.getElementById("spinnerOverlay");
  if (!el) {
    el = document.createElement("div");
    el.id = "spinnerOverlay";
    el.className = "spinner-overlay";
    el.innerHTML =
      '<div class="spinner-border text-light" style="width:3rem;height:3rem"></div>';
    document.body.appendChild(el);
  }
  el.classList.add("show");
}

function hideSpinner() {
  const el = document.getElementById("spinnerOverlay");
  if (el) el.classList.remove("show");
}

// ── Delete confirmation modal ──────────────────────────────────────────────
/**
 * Shows a Bootstrap modal asking the user to confirm deletion.
 * On confirm, submits the form identified by formId.
 * @param {string} formId
 * @param {string} itemName
 */
function confirmDelete(formId, itemName) {
  let modal = document.getElementById("deleteConfirmModal");
  if (!modal) {
    modal = document.createElement("div");
    modal.innerHTML = `
      <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header border-0">
              <h5 class="modal-title text-danger">
                <i class="fa-solid fa-trash me-2"></i>Confirm Delete
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteConfirmBody"></div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-danger" id="deleteConfirmBtn">
                <i class="fa-solid fa-trash me-1"></i>Delete
              </button>
            </div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(modal);
  }

  document.getElementById("deleteConfirmBody").textContent =
    `Are you sure you want to delete "${itemName}"? This action cannot be undone.`;

  const bsModal = new bootstrap.Modal(
    document.getElementById("deleteConfirmModal"),
  );
  bsModal.show();

  const btn = document.getElementById("deleteConfirmBtn");
  const newBtn = btn.cloneNode(true); // remove old listeners
  btn.parentNode.replaceChild(newBtn, btn);
  newBtn.addEventListener("click", () => {
    bsModal.hide();
    const form = document.getElementById(formId);
    if (form) form.submit();
  });
}

// ── Table search filter ────────────────────────────────────────────────────
/**
 * Filters visible rows in a table based on a search input.
 * @param {string} inputId     ID of the <input> element
 * @param {string} tableId     ID of the <table> element
 */
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener("input", () => {
    const term = input.value.toLowerCase();
    Array.from(table.querySelectorAll("tbody tr")).forEach((row) => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(term) ? "" : "none";
    });
  });
}

// ── Sidebar toggle ─────────────────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  const toggle = document.getElementById("sidebarToggle");
  if (!sidebar || !toggle) return;

  toggle.addEventListener("click", () => {
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle("mobile-open");
    } else {
      sidebar.classList.toggle("collapsed");
      const wrapper = document.getElementById("mainWrapper");
      if (wrapper) wrapper.classList.toggle("sidebar-collapsed");
    }
  });
});
