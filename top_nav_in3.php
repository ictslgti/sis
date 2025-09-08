<?php
// top_nav_in3.php - Bootstrap 4 top navbar for IN3 role
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';
$u_n  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-3">
  <a class="navbar-brand font-weight-bold" href="<?php echo $base; ?>/dashboard/index.php">MIS@SLGTI</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#in3Topbar" aria-controls="in3Topbar" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="in3Topbar">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/student/ManageStudents.php">
          <i class="fas fa-user-graduate"></i> Manage Students
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/group/Groups.php">
          <i class="fas fa-users"></i> Manage Groups
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/attendance/Attendancenav.php">
          <i class="fas fa-calendar-check"></i> Attendance (Dept-wise)
        </a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="in3User" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fa fa-user"></i> <?php echo htmlspecialchars($u_n ?: ''); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="in3User">
          <a class="dropdown-item" href="<?php echo $base; ?>/Profile.php">Profile</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo $base; ?>/logout.php">Logout</a>
        </div>
      </li>
    </ul>
  </div>
</nav>
