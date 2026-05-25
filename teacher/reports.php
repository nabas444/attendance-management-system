<?php
/*
 * File    : teacher/reports.php
 * Role    : Per-class attendance report for teacher with CSV export
 * Requires: teacher role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('teacher');

$pdo       = get_db();
$user      = current_user();
$tid       = (int)$user['id'];
$pageTitle = 'Class Reports';
$minPct    = (int)get_setting('min_attendance', 75);

$filterClass = (int)(filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?? 0);
$myClasses   = get_teacher_classes($tid);

// Verify the selected class belongs to this teacher
$classInfo = null;
$reportRows = [];
if ($filterClass > 0) {
  foreach ($myClasses as $cl) {
    if ((int)$cl['id'] === $filterClass) { $classInfo = $cl; break; }
  }
  if (!$classInfo) {
    $filterClass = 0; // Prevent unauthorised access
  } else {
    $reportRows = get_class_students_with_stats($filterClass);
  }
}

// ── CSV Export ─────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $classInfo && !empty($reportRows)) {
  $csvRows = [];
  foreach ($reportRows as $r) {
    $csvRows[] = [
      $r['name'],
      $r['email'],
      $r['present'],
      $r['absent'],
      $r['late'],
      $r['excused'],
      $r['total'] ?? 0,
      $r['pct'] . '%',
      $r['below_threshold'] ? 'Below Threshold' : 'OK',
    ];
  }
  export_csv(
    ['Student','Email','Present','Absent','Late','Excused','Total','Percentage','Status'],
    $csvRows,
    'report_' . ($classInfo['course_code'] ?? 'class') . '_sec' . ($classInfo['section'] ?? '') . '_' . date('Ymd') . '.csv'
  );
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="fa fa-chart-bar me-2 text-primary"></i>Class Reports</h1>
</div>

<!-- Class selector -->
<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Select Class</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Choose a class --</option>
          <?php foreach ($myClasses as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= ($filterClass === (int)$cl['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cl['course_code'] . ' – ' . $cl['course_name'] . ' (Sec ' . $cl['section'] . ')', ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($classInfo && !empty($reportRows)): ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h5 class="mb-0"><?= htmlspecialchars($classInfo['course_code'] . ' – ' . $classInfo['course_name'], ENT_QUOTES, 'UTF-8') ?></h5>
    <small class="text-muted">Section <?= htmlspecialchars($classInfo['section'], ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp; <?= count($reportRows) ?> students</small>
  </div>
  <a href="?class_id=<?= $filterClass ?>&export=1" class="btn btn-success">
    <i class="fa fa-download me-1"></i>Export CSV
  </a>
</div>

<div class="mb-3">
  <input type="text" id="reportSearch" class="form-control search-box" placeholder="Search students…">
</div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="reportTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th class="text-center">Present</th>
          <th class="text-center">Absent</th>
          <th class="text-center">Late</th>
          <th class="text-center">Excused</th>
          <th>Attendance %</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reportRows as $i => $r): ?>
          <tr class="<?= $r['below_threshold'] ? 'table-danger' : '' ?>">
            <td><?= $i + 1 ?></td>
            <td>
              <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
              <?php if ($r['below_threshold']): ?>
                <i class="fa fa-triangle-exclamation text-danger ms-1" title="Below threshold"></i>
              <?php endif; ?>
            </td>
            <td class="text-center"><span class="badge bg-success"><?= (int)($r['present'] ?? 0) ?></span></td>
            <td class="text-center"><span class="badge bg-danger"><?= (int)($r['absent'] ?? 0) ?></span></td>
            <td class="text-center"><span class="badge bg-warning text-dark"><?= (int)($r['late'] ?? 0) ?></span></td>
            <td class="text-center"><span class="badge bg-secondary"><?= (int)($r['excused'] ?? 0) ?></span></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-grow-1" style="height:8px">
                  <div class="progress-bar <?= $r['pct'] >= $minPct ? 'bg-success' : 'bg-danger' ?>"
                       style="width:<?= $r['pct'] ?>%"></div>
                </div>
                <span class="small fw-semibold <?= $r['below_threshold'] ? 'text-danger' : 'text-success' ?>"><?= $r['pct'] ?>%</span>
              </div>
            </td>
            <td class="text-center">
              <?php if ((int)($r['total'] ?? 0) === 0): ?>
                <span class="badge bg-secondary">No Data</span>
              <?php elseif ($r['below_threshold']): ?>
                <span class="badge bg-danger">Low</span>
              <?php else: ?>
                <span class="badge bg-success">OK</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>initTableSearch('reportSearch','reportTable');</script>

<?php elseif ($filterClass > 0 && empty($reportRows)): ?>
  <div class="alert alert-info">No students or attendance data for this class.</div>
<?php else: ?>
  <div class="alert alert-info"><i class="fa fa-info-circle me-2"></i>Select a class above to view the report.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
