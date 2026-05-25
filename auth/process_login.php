<?php
/*
 * File    : auth/process_login.php
 * Role    : Handles login form submission; validates credentials
 * Requires: POST from login.php
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: login.php');
  exit;
}

// CSRF check
if (!validate_csrf()) {
  $_SESSION['login_error'] = 'Invalid request. Please try again.';
  header('Location: login.php');
  exit;
}

$email    = filter_input(INPUT_POST, 'email',    FILTER_VALIDATE_EMAIL);
$password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);

if (!$email || !$password) {
  $_SESSION['login_error'] = 'Please enter a valid email and password.';
  $_SESSION['login_email'] = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
  header('Location: login.php');
  exit;
}

if (mb_strlen($password) < 8) {
  $_SESSION['login_error'] = 'Password must be at least 8 characters.';
  $_SESSION['login_email'] = $email;
  header('Location: login.php');
  exit;
}

$pdo  = get_db();
$stmt = $pdo->prepare(
  'SELECT id, name, email, password, role, is_active
   FROM users
   WHERE email = :email
   LIMIT 1'
);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false || !password_verify($password, $user['password'])) {
  $_SESSION['login_error'] = 'Invalid email or password.';
  $_SESSION['login_email'] = $email;
  header('Location: login.php');
  exit;
}

if (!(int)$user['is_active']) {
  $_SESSION['login_error'] = 'Your account has been deactivated. Please contact the administrator.';
  header('Location: login.php');
  exit;
}

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

$_SESSION['user'] = [
  'id'   => (int)$user['id'],
  'name' => $user['name'],
  'email'=> $user['email'],
  'role' => $user['role'],
];

// Regenerate CSRF token on fresh login
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$redirect = [
  'admin'   => '../admin/index.php',
  'teacher' => '../teacher/index.php',
  'student' => '../student/index.php',
];

header('Location: ' . ($redirect[$user['role']] ?? '../index.php'));
exit;