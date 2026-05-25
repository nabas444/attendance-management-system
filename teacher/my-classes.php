<?php
/*
 * File    : teacher/my-classes.php
 * Role    : Lists all classes assigned to the logged-in teacher
 * Requires: teacher role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('teacher');

$pdo       = get_db();
$user      = current_user();
$tid       = (int)$user['id'];
$pageTitle = 'My Classes';
$minPct    = (int)get_setting('min_attendance', 75);

$myClasses = get_teacher_classes($tid);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-calendar-check me-2 text-primary"></i>My Classes</h1>
  <a href="take-attendance.php" class="btn btn-primary">
    <i class="fa fa-clipboard-list me-1"></i>Take Attendance
  </a>
</div>

<?php if (empty($myClasses)): ?>
  <div class="alert alert-info"><i class="fa fa-info-circle me-2"></i>No classes are assigned to you yet. Contact your administrator.</div>
<?php else: ?>

<div class="mb-3">
  <input type="text" id="classSearch" class="form-control search-box" placeholder="Search classes…">
</div>

<div class="row g-4" id="classGrid">
  <?php foreach ($myClasses as $cl): ?>
    <?php
      $stats = get_attendance_stats((int)$cl['id']);
      $sessStmt = $pdo->prepare('SELECT COUNT(*) FROM sessions WHERE class_id = :cid');
      $sessStmt->execute([':cid' => $cl['id']]);
      $sessCount = (int)$sessStmt->fetchColumn();
    ?>
    <div class="col-md-6 col-xl-4 class-card-col">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span class="badge bg-primary fs-6"><?= htmlspecialchars($cl['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
          <span class="badge bg-secondary">Sec <?= htmlspecialchars($cl['section'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="card-body">
          <h5 class="card-title mb-1"><?= htmlspecialchars($cl['course_name'], ENT_QUOTES, 'UTF-8') ?></h5>
          <?php if ($cl['schedule']): ?>
            <p class="text-muted small mb-2"><i class="fa fa-clock me-1"></i><?= htmlspecialchars($cl['schedule'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>

          <div class="row g-2 text-center mb-3">
            <div class="col-4">
              <div class="fw-bold"><?= (int)$cl['student_count'] ?></div>
              <div class="text-muted small">Students</div>
            </div>
            <div class="col-4">
              <div class="fw-bold"><?= $sessCount ?></div>
              <div class="text-muted small">Sessions</div>
            </div>
            <div class="col-4">
              <div class="fw-bold <?= $stats['pct'] >= $minPct ? 'text-success' : 'text-danger' ?>">
                <?= $stats['pct'] ?>%
              </div>
              <div class="text-muted small">Attendance</div>
            </div>
          </div>

          <div class="progress mb-3" style="height:8px">
            <div class="progress-bar <?= $stats['pct'] >= $minPct ? 'bg-success' : 'bg-danger' ?>"
                 style="width:<?= $stats['pct'] ?>%"></div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <a href="take-attendance.php?class_id=<?= $cl['id'] ?>" class="btn btn-sm btn-primary flex-fill">
            <i class="fa fa-clipboard-list me-1"></i>Mark
          </a>
          <a href="reports.php?class_id=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-secondary flex-fill">
            <i class="fa fa-chart-bar me-1"></i>Report
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<script>
// Simple search that hides cards
document.getElementById('classSearch').addEventListener('input', function() {
  const term = this.value.toLowerCase();
  document.querySelectorAll('.class-card-col').forEach(col => {
    col.style.display = col.textContent.toLowerCase().includes(term) ? '' : 'none';
  });
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
