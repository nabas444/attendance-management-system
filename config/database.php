<?php
/*
 * File    : config/database.php
 * Role    : DB credentials and PDO connection factory
 * Requires: No session role (included by all pages)
 *
 * SECURITY WARNING: In production, move this file ABOVE the web root
 * and require it via an absolute path. Never expose credentials publicly.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_system');
define('DB_USER', 'root');       // Change for production
define('DB_PASS', '');           // Change for production
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection with strict error handling.
 * @return PDO
 */
function get_db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $dsn = sprintf(
      'mysql:host=%s;dbname=%s;charset=%s',
      DB_HOST, DB_NAME, DB_CHARSET
    );
    $options = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
      // Never expose raw error in production — log it instead
      error_log('DB connection failed: ' . $e->getMessage());
      die(json_encode(['success' => false, 'error' => 'Database connection failed.']));
    }
  }
  return $pdo;
}