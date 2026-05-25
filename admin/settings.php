<?php
/*
 * File    : admin/settings.php
 * Role    : System settings management (school name, thresholds, year, semester)
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$pageTitle = 'System Settings';
$message   = '';
$msgType   = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $schoolName  = trim(filter_input(INPUT_POST, 'school_name',  FILTER_DEFAULT) ?? '');
    $minAtt      = (int)(filter_input(INPUT_POST, 'min_attendance', FILTER_VALIDATE_INT) ?? 75);
    $semester    = (int)(filter_input(INPUT_POST, 'semester',    FILTER_VALIDATE_INT) ?? 1);
    $acadYear    = trim(filter_input(INPUT_POST, 'academic_year', FILTER_DEFAULT) ?? '');

    if ($schoolName === '') {
      $message = 'School name is required.';
      $msgType = 'danger';
    } elseif ($minAtt < 1 || $minAtt > 100) {
      $message = 'Minimum attendance must be between 1 and 100.';
      $msgType = 'danger';
    } else {
      save_setting('school_name',    $schoolName);
      save_setting('min_attendance', (string)$minAtt);
      save_setting('semester',       (string)$semester);
      save_setting('academic_year',  $acadYear);
      $message = 'Settings saved successfully.';
    }
  }
}

$settings = get_settings();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="fa fa-cog me-2 text-primary"></i>System Settings</h1>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fa fa-sliders me-2"></i>General Settings</div>
      <div class="card-body">
        <form method="POST" novalidate id="settingsForm">
          <?php csrf_field(); ?>

          <div class="mb-4">
            <label class="form-label fw-semibold">School / Campus Name <span class="text-danger">*</span></label>
            <input type="text" name="school_name" class="form-control" required maxlength="100"
                   value="<?= htmlspecialchars($settings['school_name'] ?? 'Campus Attendance System', ENT_QUOTES, 'UTF-8') ?>">
            <div class="invalid-feedback">School name is required.</div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Minimum Attendance Threshold (%) <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" name="min_attendance" class="form-control" required
                     min="1" max="100"
                     value="<?= htmlspecialchars($settings['min_attendance'] ?? '75', ENT_QUOTES, 'UTF-8') ?>">
              <span class="input-group-text">%</span>
            </div>
            <div class="form-text">Students below this threshold will be highlighted in red.</div>
            <div class="invalid-feedback">Must be between 1 and 100.</div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Academic Year</label>
              <input type="text" name="academic_year" class="form-control" maxlength="9"
                     placeholder="e.g. 2025-2026"
                     value="<?= htmlspecialchars($settings['academic_year'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Current Semester</label>
              <select name="semester" class="form-select">
                <?php for ($s = 1; $s <= 3; $s++): ?>
                  <option value="<?= $s ?>" <?= ((int)($settings['semester'] ?? 1) === $s) ? 'selected' : '' ?>>
                    Semester <?= $s ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-save me-1"></i>Save Settings
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Info card -->
    <div class="card shadow-sm mt-4">
      <div class="card-header fw-semibold"><i class="fa fa-circle-info me-2 text-info"></i>System Information</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th width="40%">PHP Version</th><td><?= phpversion() ?></td></tr>
          <tr><th>Server Time</th><td><?= date('Y-m-d H:i:s') ?></td></tr>
          <tr>
            <th>Database</th>
            <td>
              <?php
                try {
                  $v = $pdo->query('SELECT VERSION()')->fetchColumn();
                  echo 'MySQL ' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                } catch (PDOException) {
                  echo 'Connected';
                }
              ?>
            </td>
          </tr>
          <tr><th>Academic Year</th><td><?= htmlspecialchars($settings['academic_year'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
          <tr><th>Current Semester</th><td><?= htmlspecialchars($settings['semester'] ?? '1', ENT_QUOTES, 'UTF-8') ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('settingsForm').addEventListener('submit', function(e) {
  if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
  this.classList.add('was-validated');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
