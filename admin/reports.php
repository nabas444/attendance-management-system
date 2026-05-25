<?php
/*
 * File    : admin/reports.php
 * Role    : Admin view of all attendance reports with CSV export
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$pageTitle = 'Attendance Reports';
$message   = '';
$msgType   = 'success';

// ── Filters ───────────────────────────────────────────────────────────────
$filterClass = (int)(filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?? 0);
$filterDept  = (int)(filter_input(INPUT_GET, 'dept_id',  FILTER_VALIDATE_INT) ?? 0);
$filterFrom  = filter_input(INPUT_GET, 'date_from', FILTER_DEFAULT) ?? '';
$filterTo    = filter_input(INPUT_GET, 'date_to',   FILTER_DEFAULT) ?? '';

// Validate dates
$dateFrom = '';
$dateTo   = '';
if ($filterFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterFrom)) $dateFrom = $filterFrom;
if ($filterTo   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTo))   $dateTo   = $filterTo;

// ── Load filter dropdowns ─────────────────────────────────────────────────
$allClasses = get_all_classes();
$departments = get_departments();

// ── Build report query ────────────────────────────────────────────────────
$reportRows = [];
if ($filterClass > 0) {
  $sql = "SELECT u.name AS student_name, u.email,
                 SUM(a.status = 'present') AS present,
                 SUM(a.status = 'absent')  AS absent,
                 SUM(a.status = 'late')    AS late,
                 SUM(a.status = 'excused') AS excused,
                 COUNT(a.id)               AS total
          FROM enrollments en
          JOIN users u ON u.id = en.student_id
          LEFT JOIN sessions s ON s.class_id = en.class_id
          LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id";
  $params = [':cid' => $filterClass];
  $where  = ["en.class_id = :cid"];
  if ($dateFrom) { $where[] = "s.session_date >= :df"; $params[':df'] = $dateFrom; }
  if ($dateTo)   { $where[] = "s.session_date <= :dt"; $params[':dt'] = $dateTo; }
  $sql .= ' WHERE ' . implode(' AND ', $where);
  $sql .= ' GROUP BY u.id, u.name, u.email ORDER BY u.name';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $reportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── CSV Export ────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $filterClass > 0) {
  if (!empty($reportRows)) {
    $minPct = (int)get_setting('min_attendance', 75);
    $csvRows = [];
    foreach ($reportRows as $r) {
      $total = (int)($r['total'] ?? 0);
      $pres  = (int)($r['present'] ?? 0);
      $late  = (int)($r['late']    ?? 0);
      $pct   = ($total > 0) ? round((($pres + $late) / $total) * 100) : 0;
      $csvRows[] = [
        $r['student_name'],
        $r['email'],
        $pres,
        $r['absent'],
        $late,
        $r['excused'],
        $total,
        $pct . '%',
        ($pct < $minPct) ? 'Below Threshold' : 'OK',
      ];
    }
    export_csv(
      ['Student Name','Email','Present','Absent','Late','Excused','Total','Percentage','Status'],
      $csvRows,
      'attendance_report_class_' . $filterClass . '_' . date('Ymd') . '.csv'
    );
  }
}

$minPct = (int)get_setting('min_attendance', 75);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-chart-bar me-2 text-primary"></i>Attendance Reports</h1>
</div>

<!-- ── Filter Form ───────────────────────────────────────────────────────── -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="fa fa-filter me-2"></i>Filter Report</div>
  <div class="card-body">
    <form method="GET" class="row g-3">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Class</label>
        <select name="class_id" class="form-select">
          <option value="">-- Select Class --</option>
          <?php foreach ($allClasses as $cl): ?>
            <option value="<?= $cl['id'] ?>" <?= ($filterClass === (int)$cl['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cl['course_code'] . ' – ' . $cl['course_name'] . ' (Sec ' . $cl['section'] . ')', ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search me-1"></i>Filter</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Results ───────────────────────────────────────────────────────────── -->
<?php if ($filterClass > 0): ?>

  <?php
    // Class info
    $clStmt = $pdo->prepare(
      "SELECT cl.*, c.name AS course_name, c.code AS course_code, u.name AS teacher_name
       FROM classes cl
       JOIN courses c ON c.id = cl.course_id
       JOIN users u ON u.id = cl.teacher_id
       WHERE cl.id = :id"
    );
    $clStmt->execute([':id' => $filterClass]);
    $classInfo = $clStmt->fetch(PDO::FETCH_ASSOC);
  ?>

  <?php if ($classInfo): ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-0"><?= htmlspecialchars($classInfo['course_code'] . ' – ' . $classInfo['course_name'], ENT_QUOTES, 'UTF-8') ?></h5>
      <small class="text-muted">Section <?= htmlspecialchars($classInfo['section'], ENT_QUOTES, 'UTF-8') ?> &nbsp;|&nbsp; Teacher: <?= htmlspecialchars($classInfo['teacher_name'], ENT_QUOTES, 'UTF-8') ?></small>
    </div>
    <a href="?class_id=<?= $filterClass ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&export=1"
       class="btn btn-success">
      <i class="fa fa-download me-1"></i>Export CSV
    </a>
  </div>
  <?php endif; ?>

  <?php if (empty($reportRows)): ?>
    <div class="alert alert-info">No attendance data found for the selected filters.</div>
  <?php else: ?>
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
              <th>Email</th>
              <th class="text-center">Present</th>
              <th class="text-center">Absent</th>
              <th class="text-center">Late</th>
              <th class="text-center">Excused</th>
              <th class="text-center">Total</th>
              <th>Attendance %</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reportRows as $i => $r): ?>
              <?php
                $total = (int)($r['total']   ?? 0);
                $pres  = (int)($r['present'] ?? 0);
                $late  = (int)($r['late']    ?? 0);
                $pct   = ($total > 0) ? (int)round((($pres + $late) / $total) * 100) : 0;
                $rowClass = ($pct < $minPct && $total > 0) ? 'table-danger' : '';
              ?>
              <tr class="<?= $rowClass ?>">
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($r['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($r['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-center"><span class="badge bg-success"><?= $pres ?></span></td>
                <td class="text-center"><span class="badge bg-danger"><?= (int)($r['absent'] ?? 0) ?></span></td>
                <td class="text-center"><span class="badge bg-warning text-dark"><?= $late ?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?= (int)($r['excused'] ?? 0) ?></span></td>
                <td class="text-center"><?= $total ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:8px">
                      <div class="progress-bar <?= $pct >= $minPct ? 'bg-success' : 'bg-danger' ?>"
                           style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="small fw-semibold"><?= $pct ?>%</span>
                  </div>
                </td>
                <td class="text-center">
                  <?php if ($total === 0): ?>
                    <span class="badge bg-secondary">No Data</span>
                  <?php elseif ($pct >= $minPct): ?>
                    <span class="badge bg-success">OK</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Low</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>initTableSearch('reportSearch','reportTable');</script>
  <?php endif; ?>

<?php else: ?>
  <div class="alert alert-info"><i class="fa fa-info-circle me-2"></i>Select a class above to generate a report.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
