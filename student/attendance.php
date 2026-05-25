<?php
/*
 * File    : student/attendance.php
 * Role    : Student per-course attendance detail — timeline list view
 * Requires: student role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('student');

$pdo       = get_db();
$user      = current_user();
$sid       = (int)$user['id'];
$pageTitle = 'Attendance Details';
$minPct    = (int)get_setting('min_attendance', 75);

// ── Get enrolled classes for selector ────────────────────────────────────
$enrollStmt = $pdo->prepare(
  "SELECT cl.id, c.code AS course_code, c.name AS course_name, cl.section
   FROM enrollments en
   JOIN classes cl ON cl.id = en.class_id
   JOIN courses  c ON  c.id = cl.course_id
   WHERE en.student_id = :sid
   ORDER BY c.code"
);
$enrollStmt->execute([':sid' => $sid]);
$myEnrollments = $enrollStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Filter by class ───────────────────────────────────────────────────────
$filterClass = (int)(filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?? 0);

// Ensure the student is actually enrolled in that class (ownership check)
$classInfo = null;
if ($filterClass > 0) {
  foreach ($myEnrollments as $enr) {
    if ((int)$enr['id'] === $filterClass) { $classInfo = $enr; break; }
  }
  if (!$classInfo) $filterClass = 0; // Not enrolled — deny access
}

$records = [];
$stats   = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'pct' => 0];

if ($classInfo) {
  // Session-by-session attendance for this student in this class
  $recStmt = $pdo->prepare(
    "SELECT s.session_date, s.topic,
            COALESCE(a.status, 'absent') AS status,
            a.marked_at
     FROM sessions s
     LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = :sid
     WHERE s.class_id = :cid
     ORDER BY s.session_date DESC"
  );
  $recStmt->execute([':sid' => $sid, ':cid' => $filterClass]);
  $records = $recStmt->fetchAll(PDO::FETCH_ASSOC);
  $stats   = get_attendance_stats($filterClass, $sid);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="fa fa-calendar-alt me-2 text-primary"></i>Attendance Details</h1>
</div>

<!-- Course selector -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Select Course</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Choose a course --</option>
          <?php foreach ($myEnrollments as $enr): ?>
            <option value="<?= $enr['id'] ?>" <?= ($filterClass === (int)$enr['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($enr['course_code'] . ' – ' . $enr['course_name'] . ' (Sec ' . $enr['section'] . ')', ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($classInfo): ?>

<!-- Stats row -->
<?php if ($stats['pct'] < $minPct && $stats['total'] > 0): ?>
  <div class="alert alert-danger"><i class="fa fa-triangle-exclamation me-2"></i>
    Your attendance in this course is <strong><?= $stats['pct'] ?>%</strong>, which is below the required <?= $minPct ?>%.
    Please contact your teacher immediately.
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6">
    <div class="card text-center py-3 border-success">
      <div class="fs-3 fw-bold text-success"><?= (int)($stats['present'] ?? 0) ?></div>
      <div class="text-muted small">Present</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card text-center py-3 border-danger">
      <div class="fs-3 fw-bold text-danger"><?= (int)($stats['absent'] ?? 0) ?></div>
      <div class="text-muted small">Absent</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card text-center py-3 border-warning">
      <div class="fs-3 fw-bold text-warning"><?= (int)($stats['late'] ?? 0) ?></div>
      <div class="text-muted small">Late</div>
    </div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card text-center py-3 <?= $stats['pct'] >= $minPct ? 'border-success' : 'border-danger' ?>">
      <div class="fs-3 fw-bold <?= $stats['pct'] >= $minPct ? 'text-success' : 'text-danger' ?>"><?= $stats['pct'] ?>%</div>
      <div class="text-muted small">Attendance Rate</div>
    </div>
  </div>
</div>

<!-- Timeline / list -->
<?php if (empty($records)): ?>
  <div class="alert alert-info">No sessions recorded for this course yet.</div>
<?php else: ?>
  <h6 class="fw-semibold mb-3">Session Log — <?= htmlspecialchars($classInfo['course_code'] . ' ' . $classInfo['course_name'], ENT_QUOTES, 'UTF-8') ?></h6>
  <div class="table-card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Topic</th>
            <th class="text-center">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $rec): ?>
          <tr>
            <td><?= htmlspecialchars(date('D, M j Y', strtotime($rec['session_date'])), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= $rec['topic'] ? htmlspecialchars($rec['topic'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">—</em>' ?></td>
            <td class="text-center">
              <span class="badge <?= status_badge($rec['status']) ?>">
                <?= htmlspecialchars(ucfirst($rec['status']), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php else: ?>
  <div class="alert alert-info"><i class="fa fa-info-circle me-2"></i>Select a course above to see your attendance.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
