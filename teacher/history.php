<?php
/*
 * File    : teacher/history.php
 * Role    : Lists all past attendance sessions for the teacher with edit/delete
 * Requires: teacher role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('teacher');

$pdo       = get_db();
$user      = current_user();
$tid       = (int)$user['id'];
$pageTitle = 'Session History';
$message   = '';
$msgType   = 'success';

// ── Handle topic edit ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $action    = filter_input(INPUT_POST, 'action', FILTER_DEFAULT) ?? '';
    $sessionId = (int)(filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT) ?? 0);

    if ($action === 'edit_topic' && $sessionId > 0) {
      $topic = trim(filter_input(INPUT_POST, 'topic', FILTER_DEFAULT) ?? '');
      // Only update sessions that belong to this teacher
      $stmt = $pdo->prepare('UPDATE sessions SET topic = :t WHERE id = :id AND teacher_id = :tid');
      $stmt->execute([':t' => $topic, ':id' => $sessionId, ':tid' => $tid]);
      $message = 'Session topic updated.';
    } elseif ($action === 'delete' && $sessionId > 0) {
      $stmt = $pdo->prepare('DELETE FROM sessions WHERE id = :id AND teacher_id = :tid');
      $stmt->execute([':id' => $sessionId, ':tid' => $tid]);
      $message = 'Session deleted.';
    }
  }
}

// ── Filter by class ───────────────────────────────────────────────────────
$filterClass = (int)(filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?? 0);
$myClasses   = get_teacher_classes($tid);

// ── Load sessions ─────────────────────────────────────────────────────────
$sql    = "SELECT s.id, s.session_date, s.topic, s.created_at,
                  c.code AS course_code, c.name AS course_name, cl.section,
                  (SELECT COUNT(*) FROM attendance a WHERE a.session_id = s.id) AS marked_count,
                  (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = cl.id) AS total_enrolled
           FROM sessions s
           JOIN classes cl ON cl.id = s.class_id
           JOIN courses  c ON  c.id = cl.course_id
           WHERE s.teacher_id = :tid";
$params = [':tid' => $tid];
if ($filterClass > 0) {
  $sql    .= ' AND s.class_id = :cid';
  $params[':cid'] = $filterClass;
}
$sql .= ' ORDER BY s.session_date DESC, s.created_at DESC';
$stmt     = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-history me-2 text-primary"></i>Session History</h1>
  <a href="take-attendance.php" class="btn btn-primary">
    <i class="fa fa-plus me-1"></i>New Session
  </a>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filter -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="history.php" class="btn btn-sm <?= $filterClass === 0 ? 'btn-primary' : 'btn-outline-secondary' ?>">All Classes</a>
  <?php foreach ($myClasses as $cl): ?>
    <a href="?class_id=<?= $cl['id'] ?>"
       class="btn btn-sm <?= $filterClass === (int)$cl['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <?= htmlspecialchars($cl['course_code'] . ' Sec ' . $cl['section'], ENT_QUOTES, 'UTF-8') ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="mb-3">
  <input type="text" id="histSearch" class="form-control search-box" placeholder="Search sessions…">
</div>

<?php if (empty($sessions)): ?>
  <div class="alert alert-info">No sessions found.</div>
<?php else: ?>
<div class="table-card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="histTable">
      <thead>
        <tr>
          <th>Date</th>
          <th>Course</th>
          <th>Section</th>
          <th>Topic</th>
          <th class="text-center">Marked</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $sess): ?>
        <tr>
          <td><?= htmlspecialchars(date('M j, Y', strtotime($sess['session_date'])), ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge-dept"><?= htmlspecialchars($sess['course_code'], ENT_QUOTES, 'UTF-8') ?></span>
              <?= htmlspecialchars($sess['course_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($sess['section'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= $sess['topic'] ? htmlspecialchars($sess['topic'], ENT_QUOTES, 'UTF-8') : '<em class="text-muted">—</em>' ?></td>
          <td class="text-center">
            <span class="badge <?= (int)$sess['marked_count'] > 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
              <?= (int)$sess['marked_count'] ?>/<?= (int)$sess['total_enrolled'] ?>
            </span>
          </td>
          <td class="text-center action-btns">
            <a href="take-attendance.php?session_id=<?= $sess['id'] ?>" class="btn btn-sm btn-outline-primary" title="Re-mark">
              <i class="fa fa-edit"></i>
            </a>
            <button class="btn btn-sm btn-outline-secondary" title="Edit Topic"
                    onclick="openEditTopic(<?= $sess['id'] ?>, '<?= htmlspecialchars(addslashes($sess['topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
              <i class="fa fa-pen-to-square"></i>
            </button>
            <form id="del-sess-<?= $sess['id'] ?>" method="POST" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete('del-sess-<?= $sess['id'] ?>', '<?= htmlspecialchars(date('M j, Y', strtotime($sess['session_date'])), ENT_QUOTES, 'UTF-8') ?>')">
                <i class="fa fa-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Edit Topic Modal -->
<div class="modal fade" id="editTopicModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" id="editTopicForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="edit_topic">
        <input type="hidden" name="session_id" id="editSessionId" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Edit Session Topic</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Topic / Notes</label>
          <input type="text" name="topic" id="editTopicInput" class="form-control" maxlength="150">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
initTableSearch('histSearch', 'histTable');

function openEditTopic(sessionId, topic) {
  document.getElementById('editSessionId').value = sessionId;
  document.getElementById('editTopicInput').value = topic;
  new bootstrap.Modal(document.getElementById('editTopicModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
