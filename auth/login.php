<?php
/*
 * File    : auth/login.php
 * Role    : Single login page for all roles
 * Requires: No session (redirects if already logged in)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
  $role = current_user()['role'];
  $redirect = [
    'admin'   => '../admin/index.php',
    'teacher' => '../teacher/index.php',
    'student' => '../student/index.php',
  ];
  header('Location: ' . ($redirect[$role] ?? '../index.php'));
  exit;
}

$error      = $_SESSION['login_error'] ?? '';
$oldEmail   = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_email']);

$schoolName = get_setting('school_name', 'Campus Attendance System');
$pageTitle  = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999"></div>

<div class="login-wrapper">
  <div class="login-card card p-4 p-md-5">
    <div class="text-center mb-4">
      <i class="fa fa-graduation-cap fa-3x text-primary mb-3"></i>
      <h1 class="login-brand"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="text-muted small">Sign in to continue</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa fa-circle-xmark me-2"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form action="process_login.php" method="POST" novalidate id="loginForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="mb-3">
        <label for="email" class="form-label fw-semibold">Email Address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa fa-envelope text-muted"></i></span>
          <input type="email" class="form-control" id="email" name="email"
                 value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="you@campus.edu" required autocomplete="email">
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>
      </div>

      <div class="mb-4">
        <label for="password" class="form-label fw-semibold">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fa fa-lock text-muted"></i></span>
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="••••••••" required minlength="8" autocomplete="current-password">
          <button type="button" class="btn btn-outline-secondary" id="togglePwd" title="Show/hide password">
            <i class="fa fa-eye" id="eyeIcon"></i>
          </button>
          <div class="invalid-feedback">Password must be at least 8 characters.</div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
        <i class="fa fa-sign-in-alt me-2"></i>Sign In
      </button>
    </form>

    <div class="text-center mt-4 text-muted small">
      <strong>Demo credentials</strong><br>
      Admin: admin@campus.edu / password<br>
      Teacher: nati@campus.edu / password<br>
      Student: abeni@campus.edu / password
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
// Client-side form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
  if (!this.checkValidity()) {
    e.preventDefault();
    e.stopPropagation();
  }
  this.classList.add('was-validated');
});

// Password toggle
document.getElementById('togglePwd').addEventListener('click', function() {
  const pwd  = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (pwd.type === 'password') {
    pwd.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    pwd.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
});
</script>
</body>
</html>