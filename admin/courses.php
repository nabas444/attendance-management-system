<?php
/*
 * File    : admin/courses.php
 * Role    : CRUD for courses and classes (assign teachers, manage sections)
 * Requires: admin role
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');

$pdo       = get_db();
$message   = '';
$msgType   = 'success';
$editRow   = null;
$editClass = null;
$pageTitle = 'Courses & Classes';

// ── Handle POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf()) {
    $message = 'Invalid CSRF token.';
    $msgType = 'danger';
  } else {
    $action = filter_input(INPUT_POST, 'action', FILTER_DEFAULT) ?? '';

    if (in_array($action, ['add_course','edit_course'], true)) {
      $name    = trim(filter_input(INPUT_POST, 'name',    FILTER_DEFAULT) ?? '');
      $code    = strtoupper(trim(filter_input(INPUT_POST, 'code', FILTER_DEFAULT) ?? ''));
      $deptId  = (int)(filter_input(INPUT_POST, 'dept_id', FILTER_VALIDATE_INT) ?? 0);
      $credits = (int)(filter_input(INPUT_POST, 'credits', FILTER_VALIDATE_INT) ?? 3);
      $id      = (int)(filter_input(INPUT_POST, 'id',      FILTER_VALIDATE_INT) ?? 0);

      if ($name === '' || $code === '' || $deptId < 1) {
        $message = 'Name, code and department are required.';
        $msgType = 'danger';
      } else {
        try {
          if ($action === 'add_course') {
            $stmt = $pdo->prepare('INSERT INTO courses (code,name,dept_id,credits) VALUES (:co,:n,:d,:cr)');
            $stmt->execute([':co'=>$code,':n'=>$name,':d'=>$deptId,':cr'=>$credits]);
            $message = 'Course added.';
          } else {
            $stmt = $pdo->prepare('UPDATE courses SET code=:co,name=:n,dept_id=:d,credits=:cr WHERE id=:id');
            $stmt->execute([':co'=>$code,':n'=>$name,':d'=>$deptId,':cr'=>$credits,':id'=>$id]);
            $message = 'Course updated.';
          }
        } catch (PDOException $e) {
          $message = str_contains($e->getMessage(), 'Duplicate') ? 'Course code already exists.' : 'Database error.';
          $msgType = 'danger';
        }
      }
    } elseif ($action === 'delete_course') {
      $id = (int)(filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT) ?? 0);
      try {
        $pdo->prepare('DELETE FROM courses WHERE id = :id')->execute([':id'=>$id]);
        $message = 'Course deleted.';
      } catch (PDOException) {
        $message = 'Cannot delete: course is in use.';
        $msgType = 'danger';
      }
    } elseif (in_array($action, ['add_class','edit_class'], true)) {
      $courseId  = (int)(filter_input(INPUT_POST,'course_id', FILTER_VALIDATE_INT) ?? 0);
      $teacherId = (int)(filter_input(INPUT_POST,'teacher_id',FILTER_VALIDATE_INT) ?? 0);
      $section   = trim(filter_input(INPUT_POST,'section',  FILTER_DEFAULT) ?? '');
      $year      = trim(filter_input(INPUT_POST,'academic_year',FILTER_DEFAULT) ?? '');
      $sem       = (int)(filter_input(INPUT_POST,'semester', FILTER_VALIDATE_INT) ?? 1);
      $schedule  = trim(filter_input(INPUT_POST,'schedule', FILTER_DEFAULT) ?? '');
      $id        = (int)(filter_input(INPUT_POST,'id',       FILTER_VALIDATE_INT) ?? 0);

      if ($courseId < 1 || $teacherId < 1 || $section === '') {
        $message = 'Course, teacher and section are required.';
        $msgType = 'danger';
      } else {
        if ($action === 'add_class') {
          $stmt = $pdo->prepare(
            'INSERT INTO classes (course_id,teacher_id,section,academic_year,semester,schedule)
             VALUES (:ci,:ti,:sec,:yr,:sem,:sch)'
          );
          $stmt->execute([':ci'=>$courseId,':ti'=>$teacherId,':sec'=>$section,
                          ':yr'=>$year,':sem'=>$sem,':sch'=>$schedule]);
          $message = 'Class created.';
        } else {
          $stmt = $pdo->prepare(
            'UPDATE classes SET course_id=:ci,teacher_id=:ti,section=:sec,
             academic_year=:yr,semester=:sem,schedule=:sch WHERE id=:id'
          );
          $stmt->execute([':ci'=>$courseId,':ti'=>$teacherId,':sec'=>$section,
                          ':yr'=>$year,':sem'=>$sem,':sch'=>$schedule,':id'=>$id]);
          $message = 'Class updated.';
        }
      }
    } elseif ($action === 'delete_class') {
      $id = (int)(filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT) ?? 0);
      try {
        $pdo->prepare('DELETE FROM classes WHERE id = :id')->execute([':id'=>$id]);
        $message = 'Class deleted.';
      } catch (PDOException) {
        $message = 'Cannot delete: class has associated records.';
        $msgType = 'danger';
      }
    }
  }
}

if (isset($_GET['edit_course'])) {
  $stmt    = $pdo->prepare('SELECT * FROM courses WHERE id = :id');
  $stmt->execute([':id' => (int)$_GET['edit_course']]);
  $editRow = $stmt->fetch() ?: null;
}
if (isset($_GET['edit_class'])) {
  $stmt      = $pdo->prepare('SELECT * FROM classes WHERE id = :id');
  $stmt->execute([':id' => (int)$_GET['edit_class']]);
  $editClass = $stmt->fetch() ?: null;
}

$courses     = get_courses();
$classes     = get_all_classes();
$departments = get_departments();
$teachers    = get_users_by_role('teacher');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
  <h1><i class="fa fa-book me-2 text-primary"></i>Courses &amp; Classes</h1>
  <div class="d-flex gap-2">
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#courseModal">
      <i class="fa fa-plus me-1"></i>Add Course
    </button>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#classModal">
      <i class="fa fa-plus me-1"></i>Add Class
    </button>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= htmlspecialchars($msgType,ENT_QUOTES,'UTF-8') ?> alert-dismissible fade show">
    <?= htmlspecialchars($message,ENT_QUOTES,'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Courses table -->
<h5 class="fw-semibold mb-2">Courses</h5>
<div class="mb-3"><input type="text" id="courseSearch" class="form-control search-box" placeholder="Search courses…"></div>
<div class="table-card mb-4">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="courseTable">
      <thead>
        <tr><th>#</th><th>Code</th><th>Name</th><th>Department</th><th>Credits</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $i => $c): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><span class="badge-dept"><?= htmlspecialchars($c['code'],ENT_QUOTES,'UTF-8') ?></span></td>
          <td><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($c['dept_name'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= (int)$c['credits'] ?></td>
          <td class="action-btns">
            <a href="?edit_course=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></a>
            <form id="del-c-<?= $c['id'] ?>" method="POST" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete_course">
              <input type="hidden" name="id"     value="<?= $c['id'] ?>">
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete('del-c-<?= $c['id'] ?>','<?= htmlspecialchars(addslashes($c['name']),ENT_QUOTES,'UTF-8') ?>')">
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

<!-- Classes table -->
<h5 class="fw-semibold mb-2" id="classes">Classes</h5>
<div class="mb-3"><input type="text" id="classSearch" class="form-control search-box" placeholder="Search classes…"></div>
<div class="table-card">
  <div class="table-responsive">
    <table class="table table-striped table-hover mb-0" id="classTable">
      <thead>
        <tr><th>#</th><th>Course</th><th>Section</th><th>Teacher</th><th>Year</th><th>Sem</th><th>Students</th><th>Schedule</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($classes as $i => $cl): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><span class="badge-dept"><?= htmlspecialchars($cl['course_code'],ENT_QUOTES,'UTF-8') ?></span> <?= htmlspecialchars($cl['course_name'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($cl['section'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($cl['teacher_name'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= htmlspecialchars($cl['academic_year'],ENT_QUOTES,'UTF-8') ?></td>
          <td><?= (int)$cl['semester'] ?></td>
          <td><?= (int)$cl['student_count'] ?></td>
          <td><?= htmlspecialchars($cl['schedule'] ?? '—',ENT_QUOTES,'UTF-8') ?></td>
          <td class="action-btns">
            <a href="?edit_class=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></a>
            <form id="del-cl-<?= $cl['id'] ?>" method="POST" class="d-inline">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="delete_class">
              <input type="hidden" name="id"     value="<?= $cl['id'] ?>">
              <button type="button" class="btn btn-sm btn-outline-danger"
                      onclick="confirmDelete('del-cl-<?= $cl['id'] ?>','<?= htmlspecialchars(addslashes($cl['course_name'].' '.$cl['section']),ENT_QUOTES,'UTF-8') ?>')">
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

<!-- Course Modal -->
<div class="modal fade" id="courseModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" novalidate id="courseForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" id="courseAction" value="add_course">
        <input type="hidden" name="id"     id="courseId"     value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="courseModalTitle">Add Course</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-8">
              <label class="form-label fw-semibold">Course Name *</label>
              <input type="text" class="form-control" name="name" id="courseName" required maxlength="100">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Code *</label>
              <input type="text" class="form-control text-uppercase" name="code" id="courseCode" required maxlength="20">
            </div>
            <div class="col-8">
              <label class="form-label fw-semibold">Department *</label>
              <select class="form-select" name="dept_id" id="courseDept" required>
                <option value="">Select…</option>
                <?php foreach (get_departments() as $d): ?>
                  <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Credits</label>
              <input type="number" class="form-control" name="credits" id="courseCredits" value="3" min="1" max="6">
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

<!-- Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" novalidate id="classForm">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" id="classAction" value="add_class">
        <input type="hidden" name="id"     id="classId"     value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="classModalTitle">Add Class</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Course *</label>
              <select class="form-select" name="course_id" id="classCourse" required>
                <option value="">Select…</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code'].' – '.$c['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Teacher *</label>
              <select class="form-select" name="teacher_id" id="classTeacher" required>
                <option value="">Select…</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name'],ENT_QUOTES,'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Section *</label>
              <input type="text" class="form-control" name="section" id="classSection" required maxlength="10">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Year</label>
              <input type="text" class="form-control" name="academic_year" id="classYear" value="2025-2026" maxlength="9">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Semester</label>
              <select class="form-select" name="semester" id="classSem">
                <option value="1">1</option>
                <option value="2">2</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Schedule</label>
              <input type="text" class="form-control" name="schedule" id="classSchedule" placeholder="e.g. Mon/Wed 9:00-10:30">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save Class</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
initTableSearch('courseSearch','courseTable');
initTableSearch('classSearch','classTable');

document.getElementById('courseForm').addEventListener('submit',function(e){
  if(!this.checkValidity()){e.preventDefault();e.stopPropagation();}
  this.classList.add('was-validated');
});
document.getElementById('classForm').addEventListener('submit',function(e){
  if(!this.checkValidity()){e.preventDefault();e.stopPropagation();}
  this.classList.add('was-validated');
});

<?php if ($editRow): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('courseModalTitle').textContent = 'Edit Course';
  document.getElementById('courseAction').value  = 'edit_course';
  document.getElementById('courseId').value      = '<?= $editRow['id'] ?>';
  document.getElementById('courseName').value    = '<?= htmlspecialchars($editRow['name'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('courseCode').value    = '<?= htmlspecialchars($editRow['code'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('courseDept').value    = '<?= $editRow['dept_id'] ?>';
  document.getElementById('courseCredits').value = '<?= $editRow['credits'] ?>';
  new bootstrap.Modal(document.getElementById('courseModal')).show();
});
<?php endif; ?>

<?php if ($editClass): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('classModalTitle').textContent = 'Edit Class';
  document.getElementById('classAction').value   = 'edit_class';
  document.getElementById('classId').value       = '<?= $editClass['id'] ?>';
  document.getElementById('classCourse').value   = '<?= $editClass['course_id'] ?>';
  document.getElementById('classTeacher').value  = '<?= $editClass['teacher_id'] ?>';
  document.getElementById('classSection').value  = '<?= htmlspecialchars($editClass['section'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('classYear').value     = '<?= htmlspecialchars($editClass['academic_year'],ENT_QUOTES,'UTF-8') ?>';
  document.getElementById('classSem').value      = '<?= $editClass['semester'] ?>';
  document.getElementById('classSchedule').value = '<?= htmlspecialchars($editClass['schedule'] ?? '',ENT_QUOTES,'UTF-8') ?>';
  new bootstrap.Modal(document.getElementById('classModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>