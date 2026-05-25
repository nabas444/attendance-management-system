<?php
/*
 * File    : teacher/index.php
 * Role    : Teacher dashboard — today's classes, attendance stats, low-attendance alerts
 * Requires: teacher role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('teacher');

$pdo       = get_db();
$user      = current_user();
$tid       = (int)$user['id'];
$pageTitle = 'Teacher Dashboard';
$minPct    = (int)get_setting('min_attendance', 75);

// ── Today's classes ───────────────────────────────────────────────────────
$todayStmt = $pdo->prepare(
  "SELECT cl.*, c.name AS course_name, c.code AS course_code, cl.section,
          (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id) AS student_count,
          (SELECT id FROM sessions s WHERE s.class_id = cl.id AND s.session_date = CURDATE() LIMIT 1) AS today_session_id
   FROM classes cl
   JOIN courses c ON c.id = cl.course_id
   WHERE cl.teacher_id = :tid
   ORDER BY c.code"
);
$todayStmt->execute([':tid' => $tid]);
$myClasses = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Weekly attendance rate ────────────────────────────────────────────────
$weekStmt = $pdo->prepare(
  "SELECT COUNT(*) AS total,
          SUM(a.status IN ('present','late')) AS present
   FROM attendance a
   JOIN sessions s ON s.id = a.session_id
   JOIN classes cl ON cl.id = s.class_id
   WHERE cl.teacher_id = :tid
     AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
);
$weekStmt->execute([':tid' => $tid]);
$weekRow  = $weekStmt->fetch(PDO::FETCH_ASSOC);
$weekTotal   = (int)($weekRow['total']   ?? 0);
$weekPresent = (int)($weekRow['present'] ?? 0);
$weekPct     = ($weekTotal > 0) ? (int)round(($weekPresent / $weekTotal) * 100) : 0;

// ── Low-attendance students across all my classes ─────────────────────────
$lowStmt = $pdo->prepare(
  "SELECT u.name AS student_name, u.email,
          c.code AS course_code, c.name AS course_name, cl.section,
          COUNT(a.id) AS total,
          SUM(a.status IN ('present','late')) AS present
   FROM enrollments en
   JOIN users u ON u.id = en.student_id
   JOIN classes cl ON cl.id = en.class_id
   JOIN courses c ON c.id = cl.course_id
   LEFT JOIN sessions s ON s.class_id = cl.id
   LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id
   WHERE cl.teacher_id = :tid
   GROUP BY u.id, cl.id
   HAVING total > 0 AND ROUND((present/total)*100) < :minpct
   ORDER BY ROUND((present/total)*100) ASC
   LIMIT 10"
);
$lowStmt->execute([':tid' => $tid, ':minpct' => $minPct]);
$lowStudents = $lowStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Trend data (last 8 sessions) ──────────────────────────────────────────
$trendStmt = $pdo->prepare(
  "SELECT s.session_date,
          COUNT(a.id) AS total,
          SUM(a.status IN ('present','late')) AS present
   FROM sessions s
   JOIN classes cl ON cl.id = s.class_id
   LEFT JOIN attendance a ON a.session_id = s.id
   WHERE cl.teacher_id = :tid
   GROUP BY s.session_date
   ORDER BY s.session_date DESC
   LIMIT 8"
);
$trendStmt->execute([':tid' => $tid]);
$trendRaw = array_reverse($trendStmt->fetchAll(PDO::FETCH_ASSOC));

$trendLabels = [];
$trendValues = [];
foreach ($trendRaw as $tr) {
  $trendLabels[] = date('M j', strtotime($tr['session_date']));
  $tot = (int)($tr['total'] ?? 0);
  $pre = (int)($tr['present'] ?? 0);
  $trendValues[] = ($tot > 0) ? (int)round(($pre / $tot) * 100) : 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-gauge me-2 text-primary"></i>My Dashboard</h1>
  <a href="take-attendance.php" class="btn btn-primary">
    <i class="fa fa-clipboard-list me-1"></i>Take Attendance
  </a>
</div>

<!-- ── Stats Row ─────────────────────────────────────────────────────────── -->
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--blue">
      <div class="stat-icon"><i class="fa fa-calendar-alt"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= count($myClasses) ?></div>
        <div class="stat-label">My Classes</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--green">
      <div class="stat-icon"><i class="fa fa-percent"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= $weekPct ?>%</div>
        <div class="stat-label">Weekly Rate</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--orange">
      <div class="stat-icon"><i class="fa fa-triangle-exclamation"></i></div>
      <div class="stat-body">
        <div class="stat-value"><?= count($lowStudents) ?></div>
        <div class="stat-label">Low Attendance</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card stat-card--purple">
      <div class="stat-icon"><i class="fa fa-clock"></i></div>
      <div class="stat-body">
        <div class="stat-value">
          <?php
            $todayDone = array_filter($myClasses, fn($c) => $c['today_session_id']);
            echo count($todayDone) . '/' . count($myClasses);
          ?>
        </div>
        <div class="stat-label">Today Marked</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Trend Chart -->
  <div class="col-lg-8">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold"><i class="fa fa-chart-line me-2 text-primary"></i>Attendance Trend (Last 8 Sessions)</div>
      <div class="card-body">
        <?php if (empty($trendLabels)): ?>
          <p class="text-muted text-center py-4">No session data yet.</p>
        <?php else: ?>
          <canvas id="trendLine" height="120"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Today's Classes -->
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header fw-semibold"><i class="fa fa-calendar-day me-2 text-primary"></i>Today's Classes</div>
      <div class="card-body p-0">
        <?php if (empty($myClasses)): ?>
          <p class="text-muted text-center py-4">No classes assigned.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($myClasses as $cl): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <span class="fw-semibold"><?= htmlspecialchars($cl['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
                <small class="text-muted d-block">Sec <?= htmlspecialchars($cl['section'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= (int)$cl['student_count'] ?> students</small>
              </div>
              <?php if ($cl['today_session_id']): ?>
                <span class="badge bg-success">Marked</span>
              <?php else: ?>
                <a href="take-attendance.php?class_id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-primary">Mark</a>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Low Attendance Students ───────────────────────────────────────────── -->
<?php if (!empty($lowStudents)): ?>
<div class="card shadow-sm border-danger">
  <div class="card-header fw-semibold text-danger"><i class="fa fa-triangle-exclamation me-2"></i>Students Below <?= $minPct ?>% Threshold</div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead><tr><th>Student</th><th>Course</th><th class="text-center">Attendance %</th></tr></thead>
      <tbody>
        <?php foreach ($lowStudents as $ls): ?>
          <?php
            $t = (int)($ls['total'] ?? 0);
            $p = (int)($ls['present'] ?? 0);
            $pct = ($t > 0) ? (int)round(($p / $t) * 100) : 0;
          ?>
          <tr class="table-danger">
            <td><?= htmlspecialchars($ls['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($ls['course_code'] . ' Sec ' . $ls['section'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-center"><span class="badge bg-danger"><?= $pct ?>%</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  <?php if (!empty($trendLabels)): ?>
  createLineChart('trendLine', <?= json_encode($trendLabels) ?>, <?= json_encode($trendValues) ?>);
  <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
