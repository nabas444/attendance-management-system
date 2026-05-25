<?php
/*
 * File    : admin/index.php
 * Role    : Admin dashboard with stats, charts, and activity feed
 * Requires: admin role
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('admin');

$pageTitle = 'Dashboard';

$pdo = get_db();

// Count stats
$teacherCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$classCount   = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$todayCount   = get_todays_session_count();
$campusPct    = get_campus_attendance_pct();
$activity     = get_recent_activity();

// Department-wise attendance for bar chart
$deptStmt = $pdo->query(
  'SELECT d.name AS dept,
          COUNT(a.id) AS total,
          SUM(a.status IN (\'present\',\'late\')) AS attended
   FROM departments d
   LEFT JOIN users u        ON u.dept_id = d.id AND u.role = \'student\'
   LEFT JOIN enrollments en ON en.student_id = u.id
   LEFT JOIN sessions s     ON s.class_id = en.class_id
   LEFT JOIN attendance a   ON a.session_id = s.id AND a.student_id = u.id
   GROUP BY d.id, d.name
   ORDER BY d.name'
);
$deptRows   = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
$deptLabels = [];
$deptPcts   = [];
foreach ($deptRows as $dr) {
  $deptLabels[] = $dr['dept'];
  $total        = (int)($dr['total']    ?? 0);
  $attended     = (int)($dr['attended'] ?? 0);
  $deptPcts[]   = ($total > 0) ? (int)round(($attended / $total) * 100) : 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-gauge me-2 text-primary"></i>Dashboard</h1>
  <span class="text-muted small"><?= date('l, F j, Y') ?></span>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
  <?php
  $stats = [
    ['icon'=>'fa-chalkboard-teacher','color'=>'primary','label'=>'Teachers',      'value'=>$teacherCount],
    ['icon'=>'fa-user-graduate',     'color'=>'success','label'=>'Students',       'value'=>$studentCount],
    ['icon'=>'fa-calendar-alt',      'color'=>'info',   'label'=>'Classes',        'value'=>$classCount],
    ['icon'=>'fa-clock',             'color'=>'warning','label'=>"Today's Sessions",'value'=>$todayCount],
  ];
  foreach ($stats as $s): ?>
  <div class="col-6 col-lg-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3 p-3">
        <div class="card-icon bg-<?= $s['color'] ?> bg-opacity-10 text-<?= $s['color'] ?>">
          <i class="fa <?= $s['icon'] ?>"></i>
        </div>
        <div>
          <div class="card-number text-<?= $s['color'] ?>"><?= $s['value'] ?></div>
          <div class="text-muted small"><?= htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="chart-card h-100">
      <h6 class="fw-semibold mb-3">Campus Attendance</h6>
      <div style="max-width:220px;margin:auto;">
        <canvas id="campusDoughnut"></canvas>
      </div>
      <div class="text-center mt-2 text-muted small">Overall: <strong><?= $campusPct ?>%</strong></div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="chart-card h-100">
      <h6 class="fw-semibold mb-3">Department-wise Attendance</h6>
      <canvas id="deptBar" height="160"></canvas>
    </div>
  </div>
</div>

<!-- Recent activity -->
<div class="table-card">
  <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
    <h6 class="fw-semibold mb-0"><i class="fa fa-history me-2 text-primary"></i>Recent Activity</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Date</th><th>Course</th><th>Section</th><th>Teacher</th><th>Topic</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($activity)): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No sessions yet.</td></tr>
        <?php else: ?>
          <?php foreach ($activity as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['session_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <span class="badge-dept"><?= htmlspecialchars($row['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
              <?= htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td><?= htmlspecialchars($row['section'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['teacher_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['topic'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  createDoughnutChart('campusDoughnut', <?= $campusPct ?>, 'Attendance');
  createBarChart('deptBar',
    <?= json_encode($deptLabels) ?>,
    <?= json_encode($deptPcts) ?>
  );
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>