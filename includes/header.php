<?php
/*
 * File    : includes/header.php
 * Role    : Shared HTML <head>, dark sidebar and top navbar (role-aware)
 * Requires: Called after auth check; $pageTitle must be set by caller
 */

// $pageTitle should be set in the calling page before including this file
$pageTitle = $pageTitle ?? 'Campus Attendance System';

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user       = current_user();
$schoolName = get_setting('school_name', 'Campus Attendance System');

// Build base path prefix based on file depth
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$basePath   = str_replace('\\', '/', realpath(__DIR__ . '/..') . '/');
$depth      = substr_count(str_replace($basePath, '', $scriptPath), '/');
$base       = str_repeat('../', $depth);

// Role-specific nav items
$navItems = [];
if ($user) {
  switch ($user['role']) {
    case 'admin':
      $navItems = [
        ['icon'=>'fa-gauge',        'label'=>'Dashboard',    'href'=>$base.'admin/index.php'],
        ['icon'=>'fa-building',     'label'=>'Departments',  'href'=>$base.'admin/departments.php'],
        ['icon'=>'fa-book',         'label'=>'Courses',      'href'=>$base.'admin/courses.php'],
        ['icon'=>'fa-chalkboard-teacher','label'=>'Teachers','href'=>$base.'admin/teachers.php'],
        ['icon'=>'fa-user-graduate','label'=>'Students',     'href'=>$base.'admin/students.php'],
        ['icon'=>'fa-calendar-alt', 'label'=>'Classes',      'href'=>$base.'admin/courses.php#classes'],
        ['icon'=>'fa-chart-bar',    'label'=>'Reports',      'href'=>$base.'admin/reports.php'],
        ['icon'=>'fa-cog',          'label'=>'Settings',     'href'=>$base.'admin/settings.php'],
      ];
      break;
    case 'teacher':
      $navItems = [
        ['icon'=>'fa-gauge',        'label'=>'Dashboard',      'href'=>$base.'teacher/index.php'],
        ['icon'=>'fa-calendar-check','label'=>'My Classes',    'href'=>$base.'teacher/my-classes.php'],
        ['icon'=>'fa-clipboard-list','label'=>'Take Attendance','href'=>$base.'teacher/take-attendance.php'],
        ['icon'=>'fa-history',      'label'=>'History',        'href'=>$base.'teacher/history.php'],
        ['icon'=>'fa-chart-bar',    'label'=>'Reports',        'href'=>$base.'teacher/reports.php'],
      ];
      break;
    case 'student':
      $navItems = [
        ['icon'=>'fa-gauge',        'label'=>'Dashboard',   'href'=>$base.'student/index.php'],
        ['icon'=>'fa-calendar-alt', 'label'=>'Attendance',  'href'=>$base.'student/attendance.php'],
      ];
      break;
  }
  // Common items
  $navItems[] = ['icon'=>'fa-key', 'label'=>'Change Password', 'href'=>$base.'auth/change_password.php'];
  $navItems[] = ['icon'=>'fa-sign-out-alt', 'label'=>'Logout',  'href'=>$base.'auth/logout.php', 'class'=>'text-danger'];
}

$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Custom styles -->
  <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>

<?php if ($user): ?>
<!-- ── SIDEBAR ──────────────────────────────────────────────────────────── -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fa fa-graduation-cap me-2"></i>
    <span class="brand-text"><?= htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navItems as $item): ?>
      <?php
        $isActive  = (basename($item['href']) === $currentScript) ? 'active' : '';
        $extraClass = $item['class'] ?? '';
      ?>
      <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
         class="sidebar-link <?= $isActive ?> <?= $extraClass ?>">
        <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> sidebar-icon"></i>
        <span class="sidebar-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</div>

<!-- ── TOP BAR ──────────────────────────────────────────────────────────── -->
<div class="main-wrapper" id="mainWrapper">
  <header class="top-bar">
    <button class="btn btn-sm btn-outline-secondary me-3" id="sidebarToggle" title="Toggle Sidebar">
      <i class="fa fa-bars"></i>
    </button>
    <h6 class="top-bar-title mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h6>

    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="badge bg-primary text-capitalize"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></span>
      <span class="d-none d-sm-inline text-muted small"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
      <a href="<?= $base ?>auth/logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
        <i class="fa fa-sign-out-alt"></i>
      </a>
    </div>
  </header>

  <!-- ── Toast container ── -->
  <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index:9999"></div>

  <!-- ── Page content ──────────────────────────────────────────────────── -->
  <main class="content-area">
<?php else: ?>
<!-- No sidebar for non-logged-in pages (login page) -->
<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999"></div>
<?php endif; ?>