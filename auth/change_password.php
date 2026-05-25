<?php
/*
 * File    : auth/change_password.php
 * Role    : Allows any logged-in user to change their password
 * Requires: Any authenticated role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user      = current_user();
$message   = '';
$msgType   = 'success';
$pageTitle = 'Change Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid request token.';
    $msgType = 'danger';
  } else {
    $oldPwd  = filter_input(INPUT_POST, 'old_password',  FILTER_DEFAULT) ?? '';
    $newPwd  = filter_input(INPUT_POST, 'new_password',  FILTER_DEFAULT) ?? '';
    $confPwd = filter_input(INPUT_POST, 'confirm_password', FILTER_DEFAULT) ?? '';

    if (mb_strlen($oldPwd) < 1 || mb_strlen($newPwd) < 8) {
      $message = 'New password must be at least 8 characters.';
      $msgType = 'danger';
    } elseif ($newPwd !== $confPwd) {
      $message = 'New passwords do not match.';
      $msgType = 'danger';
    } else {
      $pdo  = get_db();
      $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
      $stmt->execute([':id' => $user['id']]);
      $row  = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row === false || !password_verify($oldPwd, $row['password'])) {
        $message = 'Current password is incorrect.';
        $msgType = 'danger';
      } else {
        $hash   = password_hash($newPwd, PASSWORD_BCRYPT);
        $update = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id');
        $update->execute([':pw' => $hash, ':id' => $user['id']]);
        $message = 'Password changed successfully!';
        $msgType = 'success';
      }
    }
  }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1><i class="fa fa-key me-2 text-primary"></i>Change Password</h1>
</div>

<div class="row justify-content-center">
  <div class="col-md-6">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <?php if ($message): ?>
          <div class="alert alert-<?= htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <form method="POST" novalidate id="changePwdForm">
          <?php csrf_field(); ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Current Password</label>
            <input type="password" class="form-control" name="old_password" required minlength="1">
            <div class="invalid-feedback">Please enter your current password.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" class="form-control" name="new_password"
                   id="newPwd" required minlength="8">
            <div class="form-text">Minimum 8 characters.</div>
            <div class="invalid-feedback">New password must be at least 8 characters.</div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Confirm New Password</label>
            <input type="password" class="form-control" name="confirm_password"
                   id="confPwd" required minlength="8">
            <div class="invalid-feedback">Passwords do not match.</div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="fa fa-save me-2"></i>Update Password
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('changePwdForm').addEventListener('submit', function(e) {
  const np = document.getElementById('newPwd').value;
  const cp = document.getElementById('confPwd').value;
  if (np !== cp) {
    document.getElementById('confPwd').setCustomValidity('Passwords do not match.');
  } else {
    document.getElementById('confPwd').setCustomValidity('');
  }
  if (!this.checkValidity()) {
    e.preventDefault();
    e.stopPropagation();
  }
  this.classList.add('was-validated');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>