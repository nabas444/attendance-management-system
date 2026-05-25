<?php
/*
 * File    : includes/functions.php
 * Role    : Shared helper functions used across the application
 * Requires: No session role; included by all pages
 */

require_once __DIR__ . '/../config/database.php';

// ── Settings ──────────────────────────────────────────────────────────────────

/**
 * Returns all settings as a key => value array.
 * @return array
 */
function get_settings(): array {
  $pdo  = get_db();
  $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $out  = [];
  foreach ($rows as $row) {
    $out[$row['setting_key']] = $row['setting_value'];
  }
  return $out;
}

/**
 * Returns a single setting value by key, or $default if not found.
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function get_setting(string $key, mixed $default = ''): mixed {
  $pdo  = get_db();
  $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
  $stmt->execute([':k' => $key]);
  $row  = $stmt->fetch(PDO::FETCH_ASSOC);
  return ($row !== false) ? $row['setting_value'] : $default;
}

/**
 * Upserts a setting key/value pair.
 * @param string $key
 * @param string $value
 * @return void
 */
function save_setting(string $key, string $value): void {
  $pdo  = get_db();
  $stmt = $pdo->prepare(
    'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) AS nr
     ON DUPLICATE KEY UPDATE setting_value = nr.setting_value'
  );
  $stmt->execute([':k' => $key, ':v' => $value]);
}

// ── Users ─────────────────────────────────────────────────────────────────────

/**
 * Fetches a user by ID; returns array or null.
 * @param int $id
 * @return array|null
 */
function get_user(int $id): ?array {
  $pdo  = get_db();
  $stmt = $pdo->prepare(
    'SELECT u.*, d.name AS dept_name
     FROM users u
     LEFT JOIN departments d ON d.id = u.dept_id
     WHERE u.id = :id'
  );
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return ($row !== false) ? $row : null;
}

/**
 * Fetches all users with the given role; returns array of rows.
 * @param string $role  admin|teacher|student
 * @return array
 */
function get_users_by_role(string $role): array {
  $pdo  = get_db();
  $stmt = $pdo->prepare(
    'SELECT u.*, d.name AS dept_name
     FROM users u
     LEFT JOIN departments d ON d.id = u.dept_id
     WHERE u.role = :role
     ORDER BY u.name'
  );
  $stmt->execute([':role' => $role]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Departments ───────────────────────────────────────────────────────────────

/**
 * Returns all departments ordered by name.
 * @return array
 */
function get_departments(): array {
  $pdo  = get_db();
  $stmt = $pdo->query(
    'SELECT d.*, COUNT(u.id) AS student_count
     FROM departments d
     LEFT JOIN users u ON u.dept_id = d.id AND u.role = \'student\'
     GROUP BY d.id
     ORDER BY d.name'
  );
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Courses ───────────────────────────────────────────────────────────────────

/**
 * Returns all courses joined with department name.
 * @return array
 */
function get_courses(): array {
  $pdo  = get_db();
  $stmt = $pdo->query(
    'SELECT c.*, d.name AS dept_name
     FROM courses c
     JOIN departments d ON d.id = c.dept_id
     ORDER BY c.code'
  );
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Classes ───────────────────────────────────────────────────────────────────

/**
 * Returns classes assigned to a specific teacher.
 * @param int $teacherId
 * @return array
 */
function get_teacher_classes(int $teacherId): array {
  $pdo  = get_db();
  $stmt = $pdo->prepare(
    'SELECT cl.*, c.name AS course_name, c.code AS course_code,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id) AS student_count
     FROM classes cl
     JOIN courses c ON c.id = cl.course_id
     WHERE cl.teacher_id = :tid
     ORDER BY c.code, cl.section'
  );
  $stmt->execute([':tid' => $teacherId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Returns all classes with course and teacher info (admin view).
 * @return array
 */
function get_all_classes(): array {
  $pdo  = get_db();
  $stmt = $pdo->query(
    'SELECT cl.*, c.name AS course_name, c.code AS course_code,
            u.name AS teacher_name,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id) AS student_count
     FROM classes cl
     JOIN courses c ON c.id = cl.course_id
     JOIN users   u ON u.id = cl.teacher_id
     ORDER BY c.code, cl.section'
  );
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Attendance helpers ────────────────────────────────────────────────────────

/**
 * Computes attendance stats (total, present, absent, late, excused, pct)
 * for a given class and optional student filter.
 * @param int      $classId
 * @param int|null $studentId  null = class-wide aggregate
 * @return array
 */
function get_attendance_stats(int $classId, ?int $studentId = null): array {
  $pdo  = get_db();
  $sql  = 'SELECT
             COUNT(*) AS total,
             SUM(a.status = \'present\') AS present,
             SUM(a.status = \'absent\')  AS absent,
             SUM(a.status = \'late\')    AS late,
             SUM(a.status = \'excused\') AS excused
           FROM attendance a
           JOIN sessions s ON s.id = a.session_id
           WHERE s.class_id = :cid';
  $params = [':cid' => $classId];
  if ($studentId !== null) {
    $sql    .= ' AND a.student_id = :sid';
    $params[':sid'] = $studentId;
  }
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $row  = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row === false) {
    return ['total'=>0,'present'=>0,'absent'=>0,'late'=>0,'excused'=>0,'pct'=>0];
  }
  $total   = (int)($row['total']   ?? 0);
  $present = (int)($row['present'] ?? 0);
  $late    = (int)($row['late']    ?? 0);
  if ($total > 0) {
    $pct = round((($present + $late) / $total) * 100);
  } else {
    $pct = 0;
  }
  $row['pct']  = $pct;
  $row['total'] = $total;
  return $row;
}

/**
 * Returns campus-wide overall attendance percentage.
 * @return int
 */
function get_campus_attendance_pct(): int {
  $pdo  = get_db();
  $stmt = $pdo->query(
    'SELECT COUNT(*) AS total,
            SUM(status IN (\'present\',\'late\')) AS present
     FROM attendance'
  );
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row === false) return 0;
  $total   = (int)($row['total']   ?? 0);
  $present = (int)($row['present'] ?? 0);
  if ($total > 0) {
    return (int) round(($present / $total) * 100);
  }
  return 0;
}

/**
 * Returns today's session count.
 * @return int
 */
function get_todays_session_count(): int {
  $pdo  = get_db();
  $stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM sessions WHERE session_date = CURDATE()'
  );
  $stmt->execute();
  return (int)$stmt->fetchColumn();
}

/**
 * Returns recent activity rows (last 10 sessions with teacher + course).
 * @return array
 */
function get_recent_activity(): array {
  $pdo  = get_db();
  $stmt = $pdo->query(
    'SELECT s.session_date, s.topic, s.created_at,
            u.name AS teacher_name,
            c.name AS course_name, c.code AS course_code,
            cl.section
     FROM sessions s
     JOIN classes cl ON cl.id = s.class_id
     JOIN courses  c ON  c.id = cl.course_id
     JOIN users    u ON  u.id = s.teacher_id
     ORDER BY s.created_at DESC
     LIMIT 10'
  );
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Returns students enrolled in a class, with their attendance stats.
 * @param int $classId
 * @return array
 */
function get_class_students_with_stats(int $classId): array {
  $pdo    = get_db();
  $minPct = (int) get_setting('min_attendance', 75);
  $stmt   = $pdo->prepare(
    'SELECT u.id, u.name, u.email,
            COUNT(a.id)                            AS total,
            SUM(a.status = \'present\')            AS present,
            SUM(a.status = \'absent\')             AS absent,
            SUM(a.status = \'late\')               AS late,
            SUM(a.status = \'excused\')            AS excused
     FROM enrollments en
     JOIN users u ON u.id = en.student_id
     LEFT JOIN sessions s ON s.class_id = en.class_id
     LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id
     WHERE en.class_id = :cid
     GROUP BY u.id, u.name, u.email
     ORDER BY u.name'
  );
  $stmt->execute([':cid' => $classId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as &$row) {
    $total   = (int)($row['total']   ?? 0);
    $present = (int)($row['present'] ?? 0);
    $late    = (int)($row['late']    ?? 0);
    if ($total > 0) {
      $row['pct'] = round((($present + $late) / $total) * 100);
    } else {
      $row['pct'] = 0;
    }
    $row['below_threshold'] = ($row['pct'] < $minPct);
  }
  unset($row);
  return $rows;
}

/**
 * Returns true if a session for the given class+date already exists.
 * @param int    $classId
 * @param string $date  Y-m-d
 * @return bool
 */
function session_exists(int $classId, string $date): bool {
  $pdo  = get_db();
  $stmt = $pdo->prepare(
    'SELECT id FROM sessions WHERE class_id = :cid AND session_date = :d LIMIT 1'
  );
  $stmt->execute([':cid' => $classId, ':d' => $date]);
  return ($stmt->fetch() !== false);
}

// ── Utility ───────────────────────────────────────────────────────────────────

/**
 * Outputs rows as a CSV download. Exits after sending.
 * @param array  $headers  Column header labels
 * @param array  $rows     Array of associative arrays
 * @param string $filename Suggested download filename
 * @return void
 */
function export_csv(array $headers, array $rows, string $filename): void {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $out = fopen('php://output', 'w');
  fputcsv($out, $headers);
  foreach ($rows as $row) {
    fputcsv($out, array_values($row));
  }
  fclose($out);
  exit;
}

/**
 * Safely echoes a value through htmlspecialchars.
 * @param mixed $val
 * @return void
 */
function e(mixed $val): void {
  echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns a Bootstrap badge class for an attendance status string.
 * @param string $status
 * @return string
 */
function status_badge(string $status): string {
  return match ($status) {
    'present' => 'bg-success',
    'absent'  => 'bg-danger',
    'late'    => 'bg-warning text-dark',
    'excused' => 'bg-secondary',
    default   => 'bg-light text-dark',
  };
}