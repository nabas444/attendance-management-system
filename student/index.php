<?php
/*
 * File    : student/index.php
 * Role    : Student dashboard — per-course attendance summary and overall score
 * Requires: student role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('student');

$pdo       = get_db();
$user      = current_user();
$sid       = (int)$user['id'];
$pageTitle = 'My Attendance';
$minPct    = (int)get_setting('min_attendance', 75);

// ── Per-course stats ───────────────────────────────────────────────────────
$courseStmt = $pdo->prepare(
  "SELECT c.name AS course_name, c.code AS course_code, cl.section, cl.id AS class_id,
          u.name AS teacher_name,
          COUNT(a.id) AS total,
          SUM(a.status = 'present') AS present,
          SUM(a.status = 'absent')  AS absent,
          SUM(a.status = 'late')    AS late,
          SUM(a.status = 'excused') AS excused
   FROM enrollments en
   JOIN classes cl ON cl.id = en.class_id
   JOIN courses  c ON  c.id = cl.course_id
   JOIN users    u ON  u.id = cl.teacher_id
   LEFT JOIN sessions s ON s.class_id = cl.id
   LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = :sid
   WHERE en.student_id = :sid2
   GROUP BY cl.id, c.name, c.code, cl.section, u.name
   ORDER BY c.code"
);
$courseStmt->execute([':sid' => $sid, ':sid2' => $sid]);
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

// Compute pct per course
$hasLow        = false;
$overallTotal   = 0;
$overallPresent = 0;

foreach ($courses as &$co) {
  $total   = (int)($co['total']   ?? 0);
  $present = (int)($co['present'] ?? 0);
  $late    = (int)($co['late']    ?? 0);
  $co['pct'] = ($total > 0) ? (int)round((($present + $late) / $total) * 100) : 0;
  $co['below'] = ($co['pct'] < $minPct && $total > 0);
  if ($co['below']) $hasLow = true;
  $overallTotal   += $total;
  $overallPresent += ($present + $late);
}
unset($co);

$overallPct = ($overallTotal > 0) ? (int)round(($overallPresent / $overallTotal) * 100) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="fa fa-gauge me-2 text-primary"></i>My Attendance Dashboard</h1>
</div>

<!-- Low attendance warning -->
<?php if ($hasLow): ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="fa fa-triangle-exclamation fa-lg"></i>
    <div>
      <strong>Warning:</strong> One or more of your courses are below the minimum attendance threshold of <?= $minPct ?>%.
      Please contact your teacher or advisor.
    </div>
  </div>
<?php endif; ?>

<!-- Overall score -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card shadow-sm text-center py-4">
      <div style="width:160px;height:160px;margin:0 auto">
        <canvas id="overallDoughnut"></canvas>
      </div>
      <h5 class="mt-3 mb-0">Overall Score</h5>
      <p class="text-muted small">Across all enrolled courses</p>
    </div>
  </div>
  <div class="col-md-8">
    <div class="row g-3 h-100 align-content-start">
      <div class="col-6">
        <div class="stat-card stat-card--green">
          <div class="stat-icon"><i class="fa fa-check"></i></div>
          <div class="stat-body">
            <div class="stat-value"><?= $overallPresent ?></div>
            <div class="stat-label">Present + Late</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card stat-card--orange">
          <div class="stat-icon"><i class="fa fa-times"></i></div>
          <div class="stat-body">
            <div class="stat-value"><?= $overallTotal - $overallPresent ?></div>
            <div class="stat-label">Absent</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card stat-card--blue">
          <div class="stat-icon"><i class="fa fa-book"></i></div>
          <div class="stat-body">
            <div class="stat-value"><?= count($courses) ?></div>
            <div class="stat-label">Courses Enrolled</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="stat-card stat-card--purple">
          <div class="stat-icon"><i class="fa fa-calendar"></i></div>
          <div class="stat-body">
            <div class="stat-value"><?= $overallTotal ?></div>
            <div class="stat-label">Total Sessions</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Per-course cards -->
<h5 class="fw-semibold mb-3">Course Breakdown</h5>
<?php if (empty($courses)): ?>
  <div class="alert alert-info">You are not enrolled in any courses.</div>
<?php else: ?>
<div class="row g-4">
  <?php foreach ($courses as $idx => $co): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card shadow-sm h-100 <?= $co['below'] ? 'border-danger' : '' ?>">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="badge bg-primary"><?= htmlspecialchars($co['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($co['below']): ?>
          <span class="badge bg-danger"><i class="fa fa-triangle-exclamation me-1"></i>Low</span>
        <?php else: ?>
          <span class="badge bg-success">Good</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <h6 class="fw-semibold mb-1"><?= htmlspecialchars($co['course_name'], ENT_QUOTES, 'UTF-8') ?></h6>
        <small class="text-muted">Sec <?= htmlspecialchars($co['section'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars($co['teacher_name'], ENT_QUOTES, 'UTF-8') ?></small>

        <div class="row g-2 text-center mt-2">
          <div class="col-3">
            <div class="fw-bold text-success"><?= (int)($co['present'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.7rem">Present</div>
          </div>
          <div class="col-3">
            <div class="fw-bold text-danger"><?= (int)($co['absent'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.7rem">Absent</div>
          </div>
          <div class="col-3">
            <div class="fw-bold text-warning"><?= (int)($co['late'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.7rem">Late</div>
          </div>
          <div class="col-3">
            <div class="fw-bold text-secondary"><?= (int)($co['excused'] ?? 0) ?></div>
            <div class="text-muted" style="font-size:.7rem">Excused</div>
          </div>
        </div>

        <div class="mt-3" style="width:100px;height:100px;margin:0 auto">
          <canvas id="course_chart_<?= $idx ?>"></canvas>
        </div>
      </div>
      <div class="card-footer">
        <a href="attendance.php?class_id=<?= $co['class_id'] ?>" class="btn btn-sm btn-outline-primary w-100">
          <i class="fa fa-list me-1"></i>View Details
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  createDoughnutChart('overallDoughnut', <?= $overallPct ?>, 'Overall');
  <?php foreach ($courses as $idx => $co): ?>
  createDoughnutChart('course_chart_<?= $idx ?>', <?= $co['pct'] ?>, '<?= htmlspecialchars($co['course_code'], ENT_QUOTES, 'UTF-8') ?>');
  <?php endforeach; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
