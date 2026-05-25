<?php
/*
 * File    : teacher/take-attendance.php
 * Role    : Teacher interface to mark student attendance for a session
 * Requires: teacher role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('teacher');

$pdo       = get_db();
$user      = current_user();
$tid       = (int)$user['id'];
$pageTitle = 'Take Attendance';

$message   = '';
$msgType   = 'success';

// ── Handle session creation (POST to create session first) ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $classId     = (int)(filter_input(INPUT_POST, 'class_id',     FILTER_VALIDATE_INT) ?? 0);
    $sessionDate = filter_input(INPUT_POST, 'session_date', FILTER_DEFAULT) ?? '';
    $topic       = trim(filter_input(INPUT_POST, 'topic', FILTER_DEFAULT) ?? '');

    // Validate date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
      $message = 'Invalid date format.';
      $msgType = 'danger';
    } elseif ($classId < 1) {
      $message = 'Please select a class.';
      $msgType = 'danger';
    } else {
      // Verify class belongs to this teacher
      $verifyStmt = $pdo->prepare('SELECT id FROM classes WHERE id = :id AND teacher_id = :tid');
      $verifyStmt->execute([':id' => $classId, ':tid' => $tid]);
      if ($verifyStmt->fetch() === false) {
        $message = 'Class not found or access denied.';
        $msgType = 'danger';
      } elseif (session_exists($classId, $sessionDate)) {
        $message = 'A session already exists for this class on ' . htmlspecialchars($sessionDate, ENT_QUOTES, 'UTF-8') . '. You can edit it in History.';
        $msgType = 'warning';
      } else {
        $stmt = $pdo->prepare(
          'INSERT INTO sessions (class_id, teacher_id, session_date, topic)
           VALUES (:cid, :tid, :date, :topic)'
        );
        $stmt->execute([':cid' => $classId, ':tid' => $tid, ':date' => $sessionDate, ':topic' => $topic]);
        $newSessionId = (int)$pdo->lastInsertId();
        header('Location: take-attendance.php?session_id=' . $newSessionId);
        exit;
      }
    }
  }
}

// ── Load existing session if session_id is given ──────────────────────────
$sessionId   = (int)(filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT) ?? 0);
$selectedClassId = (int)(filter_input(INPUT_GET, 'class_id',  FILTER_VALIDATE_INT) ?? 0);
$sessionRow  = null;
$students    = [];

if ($sessionId > 0) {
  // Verify session belongs to this teacher
  $sStmt = $pdo->prepare(
    "SELECT s.*, c.name AS course_name, c.code AS course_code,
            cl.section, cl.id AS class_id
     FROM sessions s
     JOIN classes cl ON cl.id = s.class_id
     JOIN courses c ON c.id = cl.course_id
     WHERE s.id = :sid AND s.teacher_id = :tid"
  );
  $sStmt->execute([':sid' => $sessionId, ':tid' => $tid]);
  $sessionRow = $sStmt->fetch(PDO::FETCH_ASSOC);

  if ($sessionRow) {
    // Fetch enrolled students with existing attendance status
    $stuStmt = $pdo->prepare(
      "SELECT u.id, u.name, u.email,
              COALESCE(a.status, 'absent') AS current_status
       FROM enrollments en
       JOIN users u ON u.id = en.student_id
       LEFT JOIN attendance a ON a.session_id = :sid AND a.student_id = u.id
       WHERE en.class_id = :cid
       ORDER BY u.name"
    );
    $stuStmt->execute([':sid' => $sessionId, ':cid' => $sessionRow['class_id']]);
    $students = $stuStmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

// ── My classes for the form dropdown ─────────────────────────────────────
$myClasses = get_teacher_classes($tid);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-clipboard-list me-2 text-primary"></i>Take Attendance</h1>
  <a href="history.php" class="btn btn-outline-secondary">
    <i class="fa fa-history me-1"></i>View History
  </a>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- ── Step 1: Select class / create session ─────────────────────────────── -->
<?php if (!$sessionRow): ?>
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="fa fa-circle-1 me-2 text-primary"></i>Step 1 — Select Class &amp; Date</div>
  <div class="card-body">
    <form method="POST" novalidate id="sessionForm">
      <?php csrf_field(); ?>
      <input type="hidden" name="create_session" value="1">

      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
          <select name="class_id" class="form-select" required id="classSelect">
            <option value="">-- Choose a class --</option>
            <?php foreach ($myClasses as $cl): ?>
              <option value="<?= $cl['id'] ?>" <?= ($selectedClassId === (int)$cl['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cl['course_code'] . ' – ' . $cl['course_name'] . ' (Sec ' . $cl['section'] . ')', ENT_QUOTES, 'UTF-8') ?>
                &nbsp;[<?= (int)$cl['student_count'] ?> students]
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Please select a class.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Session Date <span class="text-danger">*</span></label>
          <input type="date" name="session_date" class="form-control" required
                 value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
          <div class="invalid-feedback">Date is required.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Topic / Notes</label>
          <input type="text" name="topic" class="form-control" maxlength="150" placeholder="Optional…">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-arrow-right me-1"></i>Continue to Marking
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── Step 2: Mark attendance ────────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
  <div class="card-body py-3">
    <div class="row g-2 align-items-center">
      <div class="col-auto"><span class="badge bg-primary fs-6"><?= htmlspecialchars($sessionRow['course_code'], ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="col">
        <strong><?= htmlspecialchars($sessionRow['course_name'], ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="text-muted"> &mdash; Section <?= htmlspecialchars($sessionRow['section'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($sessionRow['topic']): ?>
          <em class="text-muted d-block small"><?= htmlspecialchars($sessionRow['topic'], ENT_QUOTES, 'UTF-8') ?></em>
        <?php endif; ?>
      </div>
      <div class="col-auto text-muted"><?= htmlspecialchars(date('D, M j Y', strtotime($sessionRow['session_date'])), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>
</div>

<!-- Summary counters -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="text-center p-3 rounded bg-success bg-opacity-10 border border-success">
      <div class="fs-4 fw-bold text-success" id="countPresent">0</div>
      <small class="text-success">Present</small>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="text-center p-3 rounded bg-danger bg-opacity-10 border border-danger">
      <div class="fs-4 fw-bold text-danger" id="countAbsent">0</div>
      <small class="text-danger">Absent</small>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="text-center p-3 rounded bg-warning bg-opacity-10 border border-warning">
      <div class="fs-4 fw-bold text-warning" id="countLate">0</div>
      <small class="text-warning">Late</small>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="text-center p-3 rounded bg-secondary bg-opacity-10 border border-secondary">
      <div class="fs-4 fw-bold text-secondary" id="countExcused">0</div>
      <small class="text-secondary">Excused</small>
    </div>
  </div>
</div>

<!-- Quick-mark buttons -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <button class="btn btn-sm btn-success" onclick="markAll('present')"><i class="fa fa-check me-1"></i>All Present</button>
  <button class="btn btn-sm btn-danger"  onclick="markAll('absent')"><i class="fa fa-times me-1"></i>All Absent</button>
  <button class="btn btn-sm btn-warning" onclick="markAll('late')"><i class="fa fa-clock me-1"></i>All Late</button>
  <span class="text-muted small d-flex align-items-center ms-2">Click a card to cycle: absent → present → late → excused</span>
</div>

<?php if (empty($students)): ?>
  <div class="alert alert-warning">No students enrolled in this class yet.</div>
<?php else: ?>
  <!-- Student card grid -->
  <div class="row g-3" id="studentGrid">
    <?php foreach ($students as $st): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="student-card" data-student-id="<?= $st['id'] ?>" data-current-status="<?= htmlspecialchars($st['current_status'], ENT_QUOTES, 'UTF-8') ?>"
           onclick="cycleStatus(this)" style="cursor:pointer">
        <div class="student-card-avatar">
          <i class="fa fa-user"></i>
        </div>
        <div class="student-card-name"><?= htmlspecialchars($st['name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="student-card-email text-truncate"><?= htmlspecialchars($st['email'], ENT_QUOTES, 'UTF-8') ?></div>
        <div class="mt-2">
          <span class="status-badge badge"><?= htmlspecialchars(ucfirst($st['current_status']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4 d-flex gap-3">
    <button id="submitAttendanceBtn" class="btn btn-primary btn-lg"
            onclick="submitAttendance('../api/mark_attendance.php')">
      <i class="fa fa-save me-2"></i>Save Attendance
    </button>
    <a href="take-attendance.php" class="btn btn-outline-secondary btn-lg">
      <i class="fa fa-arrow-left me-1"></i>Back
    </a>
  </div>
<?php endif; ?>

<script src="../assets/js/attendance.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  initAttendance(<?= $sessionRow['id'] ?>, '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>');
});
</script>
<?php endif; ?>

<script>
document.getElementById('sessionForm') && document.getElementById('sessionForm').addEventListener('submit', function(e) {
  if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
  this.classList.add('was-validated');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
