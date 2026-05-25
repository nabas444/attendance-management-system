<?php
/*
 * File    : api/get_students.php
 * Role    : AJAX endpoint — returns enrolled students for a class as JSON
 * Requires: teacher or admin role
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

$classId = (int)(filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?? 0);
if ($classId < 1) {
  echo json_encode(['success' => false, 'error' => 'Invalid class ID.']);
  exit;
}

$pdo = get_db();

// ── Verify class ownership for teachers ───────────────────────────────────
if ($user['role'] === 'teacher') {
  $check = $pdo->prepare('SELECT id FROM classes WHERE id = :id AND teacher_id = :tid');
  $check->execute([':id' => $classId, ':tid' => (int)$user['id']]);
  if ($check->fetch() === false) {
    echo json_encode(['success' => false, 'error' => 'Class not found or access denied.']);
    exit;
  }
}

// ── Fetch students ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
  "SELECT u.id, u.name, u.email
   FROM enrollments en
   JOIN users u ON u.id = en.student_id
   WHERE en.class_id = :cid
   ORDER BY u.name"
);
$stmt->execute([':cid' => $classId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $students, 'count' => count($students)]);
