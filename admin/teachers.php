<?php
/*
 * File    : admin/teachers.php
 * Role    : CRUD for teacher accounts
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$message   = '';
$msgType   = 'success';
$editRow   = null;
$pageTitle = 'Manage Teachers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $action = filter_input(INPUT_POST, 'action', FILTER_DEFAULT) ?? '';

    if (in_array($action, ['add','edit'], true)) {
      $name   = trim(filter_input(INPUT_POST, 'name',   FILTER_DEFAULT) ?? '');
      $email  = filter_input(INPUT_POST, 'email',  FILTER_VALIDATE_EMAIL);
      $phone  = trim(filter_input(INPUT_POST, 'phone',  FILTER_DEFAULT) ?? '');
      $deptId = (int)(filter_input(INPUT_POST, 'dept_id',FILTER_VALIDATE_INT) ?? 0);
      $active = (int)(filter_input(INPUT_POST, 'is_active',FILTER_VALIDATE_INT) ?? 1);
      $pwd    = filter_input(INPUT_POST, 'password', FILTER_DEFAULT) ?? '';
      $id     = (int)(filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?? 0);

      if ($name === '' || !$email) {
        $message = 'Name and valid email are required.';
        $msgType = 'danger';
      } elseif ($action === 'add' && mb_strlen($pwd) < 8) {
        $message = 'Password must be at least 8 characters.';
        $msgType = 'danger';
      } else {
        try {
          if ($action === 'add') {
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
              'INSERT INTO users (name,email,phone,password,role,dept_id,is_active)
               VALUES (:n,:e,:ph,:pw,\'teacher\',:d,:a)'
            );
            $stmt->execute([':n'=>$name,':e'=>$email,':ph'=>$phone,':pw'=>$hash,':d'=>($deptId>0?$deptId:null),':a'=>$active]);
            $message = 'Teacher added.';
          } else {
            if (mb_strlen($pwd) >= 8) {
              $hash = password_hash($pwd, PASSWORD_BCRYPT);
              $stmt = $pdo->prepare(
                'UPDATE users SET name=:n,email=:e,phone=:ph,password=:pw,dept_id=:d,is_active=:a WHERE id=:id AND role=\'teacher\''
              );
              $stmt->execute([':n'=>$name,':e'=>$email,':ph'=>$phone,':pw'=>$hash,':d'=>($deptId>0?$deptId:null),':a'=>$active,':id'=>$id]);
            } else {
              $stmt = $pdo->prepare(
                'UPDATE users SET name=:n,email=:e,phone=:ph,dept_id=:d,is_active=:a WHERE id=:id AND role=\'teacher\''
              );
              $stmt->execute([':n'=>$name,':e'=>$email,':ph'=>$phone,':d'=>($deptId>0?$deptId:null),':a'=>$active,':id'=>$id]);
            }
            $message = 'Teacher updated.';
          }
        } catch (PDOException $e) {
          $message = str_contains($e->getMessage(),'Duplicate') ? 'Email already in use.' : 'Database error.';
          $msgType = 'danger';
        }
      }
    } elseif ($action === 'delete') {
      $id = (int)(filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT) ?? 0);
      try {
        $pdo->prepare('DELETE FROM users WHERE id=:id AND role=\'teacher\'')->execute([':id'=>$id]);
        $message = 'Teacher deleted.';
      } catch (PDOException) {
        $message = 'Cannot delete: teacher has assigned classes.';
        $msgType = 'danger';
      }
    } elseif ($action === 'toggle') {
      $id  = (int)(filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT) ?? 0);
      $val = (int)(filter_input(INPUT_POST,'val',FILTER_VALIDATE_INT) ?? 0);
      $pdo->prepare('UPDATE users SET is_active=:v WHERE id=:id AND role=\'teacher\'')
          ->execute([':v'=>$val,':id'=>$id]);
      $message = $val ? 'Teacher activated.' : 'Teacher deactivated.';
    }
  }
}

if (isset($_GET['edit'])) {
  $stmt    = $pdo->prepare("SELECT * FROM users WHERE id=:id AND role='teacher'");
  $stmt->execute([':id'=>(int)$_GET['edit']]);
  $editRow = $stmt->fetch() ?: null;
}

$teachers    = get_users_by_role('teacher');
$departments = get_departments();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-chalkboard-teacher me-2 text-primary"></i>Teachers</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#teacherModal">
    <i class="fa fa-plus me-1"></i>Add Teacher
  </button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType,ENT_QUOTES,'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message,ENT_QUOTES,'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<div class="mb-3"><input type="text" id="teacherSearch" class="form-control search-box" placeholder="Search teachers…"></div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="teacherTable">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($teachers as $i => $t): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($t['name'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($t['email'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($t['phone'] ?? '—',ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($t['dept_name'] ?? '—',ENT_QUOTES,'UTF-8') ?></td>
          <td>
            <form method="POST" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id"  value="<?= $t['id'] ?>">
              <input type="hidden" name="val" value="<?= $t['is_active'] ? 0 : 1 ?>">
              <button type="submit" class="btn btn-sm <?= $t['is_active'] ? 'btn-success' : 'btn-secondary' ?>">
                <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
              </button>
            </form>
          </td>
          <td class="action-btns">
            <a href="?edit=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></a>
            <form id="del-t-<?= $t['id'] ?>" method="POST" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id"     value="<?= $t['id'] ?>">
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete('del-t-<?= $t['id'] ?>','<?= htmlspecialchars(addslashes($t['name']),ENT_QUOTES,'UTF-8') ?>')">
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

<!-- Modal -->
<div class="modal fade" id="teacherModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate id="teacherForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" id="tAction" value="add">
        <input type="hidden" name="id"     id="tId"     value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="teacherModalTitle">Add Teacher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Full Name *</label>
              <input type="text" class="form-control" name="name" id="tName" required maxlength="100">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email *</label>
              <input type="email" class="form-control" name="email" id="tEmail" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" class="form-control" name="phone" id="tPhone" maxlength="20">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Department</label>
              <select class="form-select" name="dept_id" id="tDept">
                <option value="">None</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password <span id="pwdNote">(min 8 chars) *</span></label>
              <input type="password" class="form-control" name="password" id="tPwd" minlength="8">
              <div class="form-text" id="pwdHint"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <select class="form-select" name="is_active" id="tActive">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
          </div>
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
initTableSearch('teacherSearch','teacherTable');
document.getElementById('teacherForm').addEventListener('submit',function(e){
  if(!this.checkValidity()){e.preventDefault();e.stopPropagation();}
  this.classList.add('was-validated');
});

<?php if ($editRow): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('teacherModalTitle').textContent = 'Edit Teacher';
  document.getElementById('tAction').value = 'edit';
  document.getElementById('tId').value     = '<?= $editRow['id'] ?>';
  document.getElementById('tName').value   = '<?= htmlspecialchars($editRow['name'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('tEmail').value  = '<?= htmlspecialchars($editRow['email'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('tPhone').value  = '<?= htmlspecialchars($editRow['phone'] ?? '',ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('tDept').value   = '<?= (int)($editRow['dept_id'] ?? 0) ?>';
  document.getElementById('tActive').value = '<?= (int)$editRow['is_active'] ?>';
  document.getElementById('tPwd').required = false;
  document.getElementById('pwdNote').textContent = '(leave blank to keep)';
  document.getElementById('pwdHint').textContent = 'Leave blank to keep current password.';
  new bootstrap.Modal(document.getElementById('teacherModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>