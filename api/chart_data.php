<?php
/*
 * File    : api/chart_data.php
 * Role    : AJAX endpoint — returns JSON data for Chart.js charts
 * Requires: Any authenticated role
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
$type = filter_input(INPUT_GET, 'type', FILTER_DEFAULT) ?? '';
$pdo  = get_db();

switch ($type) {
  // Campus-wide doughnut data (admin)
  case 'campus_overall':
    if ($user['role'] !== 'admin') { echo json_encode(['success'=>false,'error'=>'Access denied.']); exit; }
    $pct = get_campus_attendance_pct();
    echo json_encode(['success' => true, 'data' => ['pct' => $pct, 'absent' => 100 - $pct]]);
    break;

  // Department-wise bar (admin)
  case 'dept_bar':
    if ($user['role'] !== 'admin') { echo json_encode(['success'=>false,'error'=>'Access denied.']); exit; }
    $stmt = $pdo->query(
      "SELECT d.name,
              COUNT(a.id) AS total,
              SUM(a.status IN ('present','late')) AS present
       FROM departments d
       LEFT JOIN users u ON u.dept_id = d.id AND u.role = 'student'
       LEFT JOIN attendance a ON a.student_id = u.id
       GROUP BY d.id, d.name
       ORDER BY d.name"
    );
    $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = [];
    $values = [];
    foreach ($rows as $r) {
      $labels[] = $r['name'];
      $t = (int)($r['total'] ?? 0);
      $p = (int)($r['present'] ?? 0);
      $values[] = ($t > 0) ? (int)round(($p / $t) * 100) : 0;
    }
    echo json_encode(['success' => true, 'data' => ['labels' => $labels, 'values' => $values]]);
    break;

  // Teacher trend line
  case 'teacher_trend':
    if ($user['role'] !== 'teacher') { echo json_encode(['success'=>false,'error'=>'Access denied.']); exit; }
    $tid  = (int)$user['id'];
    $stmt = $pdo->prepare(
      "SELECT s.session_date,
              COUNT(a.id) AS total,
              SUM(a.status IN ('present','late')) AS present
       FROM sessions s
       JOIN classes cl ON cl.id = s.class_id
       LEFT JOIN attendance a ON a.session_id = s.id
       WHERE cl.teacher_id = :tid
       GROUP BY s.session_date
       ORDER BY s.session_date DESC LIMIT 10"
    );
    $stmt->execute([':tid' => $tid]);
    $rows   = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $labels = [];
    $values = [];
    foreach ($rows as $r) {
      $labels[] = date('M j', strtotime($r['session_date']));
      $t = (int)($r['total'] ?? 0);
      $p = (int)($r['present'] ?? 0);
      $values[] = ($t > 0) ? (int)round(($p / $t) * 100) : 0;
    }
    echo json_encode(['success' => true, 'data' => ['labels' => $labels, 'values' => $values]]);
    break;

  // Student per-course doughnut
  case 'student_course':
    if ($user['role'] !== 'student') { echo json_encode(['success'=>false,'error'=>'Access denied.']); exit; }
    $classId = (int)(filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?? 0);
    if ($classId < 1) { echo json_encode(['success'=>false,'error'=>'Invalid class.']); exit; }
    // Ownership check
    $check = $pdo->prepare('SELECT id FROM enrollments WHERE class_id=:cid AND student_id=:sid');
    $check->execute([':cid'=>$classId,':sid'=>(int)$user['id']]);
    if ($check->fetch() === false) { echo json_encode(['success'=>false,'error'=>'Not enrolled.']); exit; }
    $stats = get_attendance_stats($classId, (int)$user['id']);
    echo json_encode(['success'=>true,'data'=>['pct'=>$stats['pct'],'absent'=>100-$stats['pct']]]);
    break;

  default:
    echo json_encode(['success' => false, 'error' => 'Unknown chart type.']);
}
