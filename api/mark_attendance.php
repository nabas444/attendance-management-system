<?php
/*
 * File    : api/mark_attendance.php
 * Role    : AJAX endpoint — saves attendance records for a session
 * Requires: teacher or admin role (JSON POST)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Auth check ─────────────────────────────────────────────────────────────
if (!is_logged_in()) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
  exit;
}

$user = current_user();
if (!in_array($user['role'] ?? '', ['teacher', 'admin'], true)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Access denied.']);
  exit;
}

// ── Read JSON body ─────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
  exit;
}

// ── CSRF validation ────────────────────────────────────────────────────────
$submittedToken = $data['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
  echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
  exit;
}

// ── Validate inputs ────────────────────────────────────────────────────────
$sessionId = (int)($data['session_id'] ?? 0);
$records   = $data['records'] ?? [];

if ($sessionId < 1) {
  echo json_encode(['success' => false, 'error' => 'Invalid session ID.']);
  exit;
}

if (!is_array($records) || empty($records)) {
  echo json_encode(['success' => false, 'error' => 'No attendance records provided.']);
  exit;
}

$validStatuses = ['present', 'absent', 'late', 'excused'];

$pdo = get_db();

// ── Verify session ownership (teacher must own the session) ────────────────
if ($user['role'] === 'teacher') {
  $verify = $pdo->prepare('SELECT id FROM sessions WHERE id = :sid AND teacher_id = :tid');
  $verify->execute([':sid' => $sessionId, ':tid' => (int)$user['id']]);
  if ($verify->fetch() === false) {
    echo json_encode(['success' => false, 'error' => 'Session not found or access denied.']);
    exit;
  }
}

// ── Upsert attendance records ──────────────────────────────────────────────
try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare(
    'INSERT INTO attendance (session_id, student_id, status)
     VALUES (:sid, :stid, :status) AS nr
     ON DUPLICATE KEY UPDATE status = nr.status, marked_at = CURRENT_TIMESTAMP'
  );

  foreach ($records as $rec) {
    $studentId = (int)($rec['student_id'] ?? 0);
    $status    = $rec['status'] ?? 'absent';

    if ($studentId < 1 || !in_array($status, $validStatuses, true)) {
      continue; // Skip invalid records
    }

    $stmt->execute([':sid' => $sessionId, ':stid' => $studentId, ':status' => $status]);
  }

  $pdo->commit();
  echo json_encode(['success' => true, 'message' => 'Attendance saved.']);

} catch (PDOException $e) {
  $pdo->rollBack();
  error_log('mark_attendance error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
}
