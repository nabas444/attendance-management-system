<?php
/*
 * File    : admin/students.php
 * Role    : CRUD for student accounts + enrollment management
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$message   = '';
$msgType   = 'success';
$editRow   = null;
$pageTitle = 'Manage Students';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $action = filter_input(INPUT_POST,'action',FILTER_DEFAULT) ?? '';

    if (in_array($action,['add','edit'],true)) {
      $name   = trim(filter_input(INPUT_POST,'name',  FILTER_DEFAULT) ?? '');
      $email  = filter_input(INPUT_POST,'email', FILTER_VALIDATE_EMAIL);
      $phone  = trim(filter_input(INPUT_POST,'phone', FILTER_DEFAULT) ?? '');
      $deptId = (int)(filter_input(INPUT_POST,'dept_id',FILTER_VALIDATE_INT) ?? 0);
      $active = (int)(filter_input(INPUT_POST,'is_active',FILTER_VALIDATE_INT) ?? 1);
      $pwd    = filter_input(INPUT_POST,'password',FILTER_DEFAULT) ?? '';
      $id     = (int)(filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT) ?? 0);

      if ($name === '' || !$email) {
        $message = 'Name and valid email are required.'; $msgType = 'danger';
      } elseif ($action === 'add' && mb_strlen($pwd) < 8) {
        $message = 'Password must be at least 8 characters.'; $msgType = 'danger';
      } else {
        try {
          if ($action === 'add') {
            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $pdo->prepare(
              'INSERT INTO users (name,email,phone,password,role,dept_id,is_active)
               VALUES (:n,:e,:ph,:pw,\'student\',:d,:a)'
            )->execute([':n'=>$name,':e'=>$email,':ph'=>$phone,':pw'=>$hash,':d'=>($deptId>0?$deptId:null),':a'=>$active]);
            $message = 'Student added.';
          } else {
            if (mb_strlen($pwd) >= 8) {
              $hash = password_hash($pwd, PASSWORD_BCRYPT);
              $pdo->prepare(
                'UPDATE users SET name=:n,email=:e,phone=:ph,password=:pw,dept_id=:d,is_active=:a WHERE id=:id AND role=\'student\''
              )->execute([':n'=>$name,':e'=>$email,':ph'=>$phone,':pw'=>$hash,':d'=>($deptId>0?$deptId:null),':a'=>$active,':id'=>$id]);
            } else {
              $pdo->prepare(
                'UPDATE users SET name=:n,email=:e,phone=:ph,dept_id=:d,is_active=:a WHERE id=:id AND role=\'student\''
              )->execute([':n'=>$name,':e'=>$email,':ph'=>$phone,':d'=>($deptId>0?$deptId:null),':a'=>$active,':id'=>$id]);
            }
            $message = 'Student updated.';
          }
        } catch (PDOException $e) {
          $message = str_contains($e->getMessage(),'Duplicate') ? 'Email already in use.' : 'Database error.';
          $msgType = 'danger';
        }
      }
    } elseif ($action === 'delete') {
      $id = (int)(filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT) ?? 0);
      try {
        $pdo->prepare("DELETE FROM users WHERE id=:id AND role='student'")->execute([':id'=>$id]);
        $message = 'Student deleted.';
      } catch (PDOException) {
        $message = 'Cannot delete: student has attendance records.'; $msgType = 'danger';
      }
    } elseif ($action === 'enroll') {
      $classId   = (int)(filter_input(INPUT_POST,'class_id',  FILTER_VALIDATE_INT) ?? 0);
      $studentId = (int)(filter_input(INPUT_POST,'student_id',FILTER_VALIDATE_INT) ?? 0);
      if ($classId > 0 && $studentId > 0) {
        try {
          $pdo->prepare('INSERT INTO enrollments (class_id,student_id) VALUES (:c,:s)')
              ->execute([':c'=>$classId,':s'=>$studentId]);
          $message = 'Student enrolled.';
        } catch (PDOException) {
          $message = 'Student already enrolled in that class.'; $msgType = 'warning';
        }
      }
    } elseif ($action === 'unenroll') {
      $enId = (int)(filter_input(INPUT_POST,'enroll_id',FILTER_VALIDATE_INT) ?? 0);
      $pdo->prepare('DELETE FROM enrollments WHERE id=:id')->execute([':id'=>$enId]);
      $message = 'Student unenrolled.';
    }
  }
}

if (isset($_GET['edit'])) {
  $stmt    = $pdo->prepare("SELECT * FROM users WHERE id=:id AND role='student'");
  $stmt->execute([':id'=>(int)$_GET['edit']]);
  $editRow = $stmt->fetch() ?: null;
}

// If ?view_enrollments=N, show that student's enrollments
$viewEnrollId  = (int)(filter_input(INPUT_GET,'view_enrollments',FILTER_VALIDATE_INT) ?? 0);
$enrollStudent = null;
$enrollments   = [];
$allClasses    = [];
if ($viewEnrollId > 0) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id=:id AND role='student'");
  $stmt->execute([':id'=>$viewEnrollId]);
  $enrollStudent = $stmt->fetch() ?: null;
  if ($enrollStudent) {
    $stmt = $pdo->prepare(
      'SELECT en.id AS enroll_id, cl.section, c.code AS course_code, c.name AS course_name,
              u.name AS teacher_name, cl.academic_year
       FROM enrollments en
       JOIN classes cl ON cl.id = en.class_id
       JOIN courses  c ON  c.id = cl.course_id
       JOIN users    u ON  u.id = cl.teacher_id
       WHERE en.student_id = :sid
       ORDER BY c.code'
    );
    $stmt->execute([':sid'=>$viewEnrollId]);
    $enrollments = $stmt->fetchAll();
    $allClasses  = get_all_classes();
  }
}

$students    = get_users_by_role('student');
$departments = get_departments();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-user-graduate me-2 text-primary"></i>Students</h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal">
    <i class="fa fa-plus me-1"></i>Add Student
  </button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType,ENT_QUOTES,'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message,ENT_QUOTES,'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<?php if ($enrollStudent): ?>
<!-- Enrollment panel -->
<div class="card mb-4 border-primary">
  <div class="card-header bg-primary text-white d-flex justify-content-between">
    <strong>Enrollments for <?= htmlspecialchars($enrollStudent['name'],ENT_QUOTES,'UTF-8') ?></strong>
    <a href="students.php" class="btn btn-sm btn-light">Close</a>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-8">
        <form method="POST" class="d-flex gap-2">
          <?php csrf_field(); ?>
          <input type="hidden" name="action"     value="enroll">
          <input type="hidden" name="student_id" value="<?= $viewEnrollId ?>">
          <select class="form-select" name="class_id" required>
            <option value="">Select class to enroll…</option>
            <?php foreach ($allClasses as $cl): ?>
              <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['course_code'].' – '.$cl['course_name'].' (Sec '.$cl['section'].')',ENT_QUOTES,'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-success btn-sm text-nowrap">Enroll</button>
        </form>
      </div>
    </div>
    <table class="table table-sm">
      <thead><tr><th>Course</th><th>Section</th><th>Teacher</th><th>Year</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($enrollments)): ?>
          <tr><td colspan="5" class="text-muted">No enrollments.</td></tr>
        <?php else: ?>
          <?php foreach ($enrollments as $en): ?>
          <tr>
            <td><span class="badge-dept"><?= htmlspecialchars($en['course_code'],ENT_QUOTES,'UTF-8') ?></span> <?= htmlspecialchars($en['course_name'],ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($en['section'],ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($en['teacher_name'],ENT_QUOTES,'UTF-8') ?></td>
            <td><?= htmlspecialchars($en['academic_year'],ENT_QUOTES,'UTF-8') ?></td>
            <td>
              <form method="POST" class="d-inline">
                <?php csrf_field(); ?>
                <input type="hidden" name="action"    value="unenroll">
                <input type="hidden" name="enroll_id" value="<?= $en['enroll_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="mb-3"><input type="text" id="studentSearch" class="form-control search-box" placeholder="Search students…"></div>

<div class="table-card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="studentTable">
      <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($s['name'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($s['email'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($s['dept_name'] ?? '—',ENT_QUOTES,'UTF-8') ?></td>
          <td><span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td class="action-btns">
            <a href="?view_enrollments=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info" title="Manage Enrollments"><i class="fa fa-list"></i></a>
            <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></a>
            <form id="del-s-<?= $s['id'] ?>" method="POST" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id"     value="<?= $s['id'] ?>">
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete('del-s-<?= $s['id'] ?>','<?= htmlspecialchars(addslashes($s['name']),ENT_QUOTES,'UTF-8') ?>')">
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

<!-- Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate id="studentForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" id="sAction" value="add">
        <input type="hidden" name="id"     id="sId"     value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="studentModalTitle">Add Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label>
              <input type="text" class="form-control" name="name" id="sName" required maxlength="100"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Email *</label>
              <input type="email" class="form-control" name="email" id="sEmail" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Phone</label>
              <input type="text" class="form-control" name="phone" id="sPhone" maxlength="20"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Department</label>
              <select class="form-select" name="dept_id" id="sDept">
                <option value="">None</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label fw-semibold">Password <span id="sPwdNote">(min 8 chars) *</span></label>
              <input type="password" class="form-control" name="password" id="sPwd" minlength="8"></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Status</label>
              <select class="form-select" name="is_active" id="sActive">
                <option value="1">Active</option><option value="0">Inactive</option>
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
initTableSearch('studentSearch','studentTable');
document.getElementById('studentForm').addEventListener('submit',function(e){
  if(!this.checkValidity()){e.preventDefault();e.stopPropagation();}
  this.classList.add('was-validated');
});
<?php if ($editRow): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('studentModalTitle').textContent = 'Edit Student';
  document.getElementById('sAction').value  = 'edit';
  document.getElementById('sId').value      = '<?= $editRow['id'] ?>';
  document.getElementById('sName').value    = '<?= htmlspecialchars($editRow['name'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('sEmail').value   = '<?= htmlspecialchars($editRow['email'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('sPhone').value   = '<?= htmlspecialchars($editRow['phone'] ?? '',ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('sDept').value    = '<?= (int)($editRow['dept_id'] ?? 0) ?>';
  document.getElementById('sActive').value  = '<?= (int)$editRow['is_active'] ?>';
  document.getElementById('sPwd').required  = false;
  document.getElementById('sPwdNote').textContent = '(leave blank to keep)';
  new bootstrap.Modal(document.getElementById('studentModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>