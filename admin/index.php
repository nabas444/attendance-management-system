<?php
/*
 * File    : admin/index.php
 * Role    : Admin dashboard with stats, charts, and recent activity
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$pageTitle = 'Dashboard';

// ── Summary counts ────────────────────────────────────────────────────────
$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$classCount   = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$todaySessions = get_todays_session_count();
$campusPct    = get_campus_attendance_pct();
$recentActivity = get_recent_activity();

// ── Department-wise attendance for bar chart ──────────────────────────────
$deptStmt = $pdo->query(
  "SELECT d.name AS dept_name,
          COUNT(a.id) AS total,
          SUM(a.status IN ('present','late')) AS present
   FROM departments d
   LEFT JOIN users u ON u.dept_id = d.id AND u.role = 'student'
   LEFT JOIN attendance a ON a.student_id = u.id
   GROUP BY d.id, d.name
   ORDER BY d.name"
);
$deptRows = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

$deptLabels = [];
$deptValues = [];
foreach ($deptRows as $dr) {
  $deptLabels[] = htmlspecialchars($dr['dept_name'], ENT_QUOTES, 'UTF-8');
  $total   = (int)($dr['total']   ?? 0);
  $present = (int)($dr['present'] ?? 0);
  $deptValues[] = ($total > 0) ? (int)round(($present / $total) * 100) : 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-gauge me-2 text-primary"></i>Dashboard</h1>
  <span class="text-muted small"><?= date('l, F j, Y') ?></span>
</div>

<!-- ── Summary Cards ────────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--blue">
      <div class="stat-icon"><i class="fa fa-chalkboard-teacher"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $teacherCount ?></div>
        <div class="stat-label">Teachers</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--green">
      <div class="stat-icon"><i class="fa fa-user-graduate"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $studentCount ?></div>
        <div class="stat-label">Students</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--purple">
      <div class="stat-icon"><i class="fa fa-calendar-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $classCount ?></div>
        <div class="stat-label">Classes</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--orange">
      <div class="stat-icon"><i class="fa fa-clock"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $todaySessions ?></div>
        <div class="stat-label">Today's Sessions</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Charts Row ────────────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">
  <div class="col-lg-4">
    <div class="card h-100 shadow-sm">
      <div class="card-header fw-semibold"><i class="fa fa-circle-half-stroke me-2 text-primary"></i>Campus Attendance</div>
      <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
        <div style="width:200px;height:200px">
          <canvas id="campusDoughnut"></canvas>
        </div>
        <p class="mt-3 mb-0 text-muted small text-center">Overall campus-wide attendance rate</p>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card h-100 shadow-sm">
      <div class="card-header fw-semibold"><i class="fa fa-chart-bar me-2 text-primary"></i>Attendance by Department</div>
      <div class="card-body">
        <canvas id="deptBar" height="120"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent Activity ───────────────────────────────────────────────────── -->
<div class="card shadow-sm">
  <div class="card-header fw-semibold"><i class="fa fa-clock-rotate-left me-2 text-primary"></i>Recent Activity</div>
  <div class="card-body p-0">
    <?php if (empty($recentActivity)): ?>
      <p class="text-muted text-center py-4">No activity yet.</p>
    <?php else: ?>
      <ul class="activity-feed">
        <?php foreach ($recentActivity as $act): ?>
        <li class="activity-item">
          <span class="activity-dot"></span>
          <div class="activity-content">
            <strong><?= htmlspecialchars($act['teacher_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            took attendance for
            <strong><?= htmlspecialchars($act['course_code'] . ' – ' . $act['course_name'], ENT_QUOTES, 'UTF-8') ?></strong>
            (Section <?= htmlspecialchars($act['section'], ENT_QUOTES, 'UTF-8') ?>)
            <?php if ($act['topic']): ?>
              <em class="text-muted">– <?= htmlspecialchars($act['topic'], ENT_QUOTES, 'UTF-8') ?></em>
            <?php endif; ?>
            <span class="text-muted small d-block"><?= htmlspecialchars(date('M j, Y', strtotime($act['session_date'])), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  createDoughnutChart('campusDoughnut', <?= $campusPct ?>, 'Attendance');
  createBarChart(
    'deptBar',
    <?= json_encode($deptLabels) ?>,
    <?= json_encode($deptValues) ?>
  );
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
