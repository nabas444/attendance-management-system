<?php
/*
 * File    : admin/departments.php
 * Role    : CRUD management for departments
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$message   = '';
$msgType   = 'success';
$editRow   = null;
$pageTitle = 'Departments';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $action = filter_input(INPUT_POST, 'action', FILTER_DEFAULT) ?? '';

    if ($action === 'add' || $action === 'edit') {
      $name = trim(filter_input(INPUT_POST, 'name', FILTER_DEFAULT) ?? '');
      $code = strtoupper(trim(filter_input(INPUT_POST, 'code', FILTER_DEFAULT) ?? ''));
      $id   = (int)(filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?? 0);

      if ($name === '' || $code === '') {
        $message = 'Name and code are required.';
        $msgType = 'danger';
      } else {
        try {
          if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO departments (name, code) VALUES (:n, :c)');
            $stmt->execute([':n' => $name, ':c' => $code]);
            $message = 'Department added successfully.';
          } else {
            $stmt = $pdo->prepare('UPDATE departments SET name = :n, code = :c WHERE id = :id');
            $stmt->execute([':n' => $name, ':c' => $code, ':id' => $id]);
            $message = 'Department updated successfully.';
          }
        } catch (PDOException $e) {
          $message = 'Error: ' . (str_contains($e->getMessage(), 'Duplicate') ? 'Department code already exists.' : 'Database error.');
          $msgType = 'danger';
        }
      }
    } elseif ($action === 'delete') {
      $id = (int)(filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?? 0);
      if ($id > 0) {
        try {
          $stmt = $pdo->prepare('DELETE FROM departments WHERE id = :id');
          $stmt->execute([':id' => $id]);
          $message = 'Department deleted.';
        } catch (PDOException $e) {
          $message = 'Cannot delete: department is in use.';
          $msgType = 'danger';
        }
      }
    }
  }
}

// Load edit row
if (isset($_GET['edit'])) {
  $eid  = (int)$_GET['edit'];
  $stmt = $pdo->prepare('SELECT * FROM departments WHERE id = :id');
  $stmt->execute([':id' => $eid]);
  $editRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$departments = get_departments();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-building me-2 text-primary"></i>Departments</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal">
    <i class="fa fa-plus me-1"></i>Add Department
  </button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Search -->
<div class="mb-3">
  <input type="text" id="deptSearch" class="form-control search-box" placeholder="Search departments…">
</div>

<!-- Table -->
<div class="table-card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="deptTable">
      <thead>
        <tr>
          <th>#</th><th>Code</th><th>Name</th><th>Students</th><th>Created</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($departments)): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">No departments found.</td></tr>
        <?php else: ?>
          <?php foreach ($departments as $i => $d): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><span class="badge-dept"><?= htmlspecialchars($d['code'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= htmlspecialchars($d['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)$d['student_count'] ?></td>
            <td><?= htmlspecialchars(date('M j, Y', strtotime($d['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="action-btns">
              <a href="?edit=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-edit"></i>
              </a>
              <form id="del-dept-<?= $d['id'] ?>" method="POST" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="confirmDelete('del-dept-<?= $d['id'] ?>', '<?= htmlspecialchars(addslashes($d['name']), ENT_QUOTES, 'UTF-8') ?>')">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" novalidate id="deptForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" id="deptAction" value="add">
        <input type="hidden" name="id"     id="deptId"     value="0">

        <div class="modal-header">
          <h5 class="modal-title" id="deptModalTitle">Add Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Department Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="deptName"
                   required maxlength="100" placeholder="e.g. Computer Science">
            <div class="invalid-feedback">Name is required.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-uppercase" name="code" id="deptCode"
                   required maxlength="20" placeholder="e.g. CS">
            <div class="form-text">Short unique code (e.g. CS, MATH).</div>
            <div class="invalid-feedback">Code is required.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="deptSubmitBtn">Save Department</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
initTableSearch('deptSearch', 'deptTable');

// Open edit modal if ?edit=N
<?php if ($editRow): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('deptModalTitle').textContent = 'Edit Department';
  document.getElementById('deptAction').value = 'edit';
  document.getElementById('deptId').value     = '<?= $editRow['id'] ?>';
  document.getElementById('deptName').value   = '<?= htmlspecialchars($editRow['name'], ENT_QUOTES, 'UTF-8') ?>';
  document.getElementById('deptCode').value   = '<?= htmlspecialchars($editRow['code'], ENT_QUOTES, 'UTF-8') ?>';
  new bootstrap.Modal(document.getElementById('deptModal')).show();
});
<?php endif; ?>

document.getElementById('deptForm').addEventListener('submit', function(e) {
  if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
  this.classList.add('was-validated');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>