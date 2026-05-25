<?php
/*
 * File    : includes/auth.php
 * Role    : Session management and role-based access control helpers
 * Requires: Included by every protected page
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/**
 * Returns the current logged-in user array or null.
 * @return array|null
 */
function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

/**
 * Returns true if a user is logged in.
 * @return bool
 */
function is_logged_in(): bool {
  return isset($_SESSION['user']['id']);
}

/**
 * Redirects to login if not authenticated.
 * @return void
 */
function require_login(): void {
  if (!is_logged_in()) {
    header('Location: ' . login_url());
    exit;
  }
}

/**
 * Requires the logged-in user to have one of the given roles.
 * Redirects to login (or 403 page) if not.
 * @param string|array $roles
 * @return void
 */
function require_role(string|array $roles): void {
  require_login();
  $roles = (array) $roles;
  $user  = current_user();
  if (!in_array($user['role'] ?? '', $roles, true)) {
    header('Location: ' . login_url());
    exit;
  }
}

/**
 * Builds the path to the login page regardless of calling depth.
 * @return string
 */
function login_url(): string {
  // Works from any subdirectory depth (admin/, teacher/, api/, etc.)
  $depth = substr_count(
    str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']),
    '/'
  ) - substr_count(
    str_replace('\\', '/', realpath(__DIR__ . '/../')),
    '/'
  ) - 1;
  $up    = str_repeat('../', max(0, $depth));
  return $up . 'auth/login.php';
}

/**
 * Generates a CSRF token and stores it in the session (once per session).
 * @return string
 */
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

/**
 * Validates the submitted CSRF token against the session token.
 * @return bool
 */
function validate_csrf(): bool {
  $submitted = $_POST['csrf_token'] ?? '';
  return hash_equals($_SESSION['csrf_token'] ?? '', $submitted);
}

/**
 * Outputs a hidden CSRF input field.
 * @return void
 */
function csrf_field(): void {
  echo '<input type="hidden" name="csrf_token" value="'
    . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}