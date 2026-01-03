<?php
// top_nav_in3.php - Bootstrap 4 top navbar for IN3 role
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';
$u_n  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark shadow-lg mb-3" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid rgba(37, 99, 235, 0.3); min-height: 60px;">
  <a class="navbar-brand font-weight-bold d-flex align-items-center" href="<?php echo $base; ?>/dashboard/index.php" style="font-size: 1.1rem; letter-spacing: 0.5px; color: #ffffff !important;">
    <i class="fas fa-university mr-2" style="color: #2563eb;"></i>
    <span>MIS@SLGTI</span>
  </a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#in3Topbar" aria-controls="in3Topbar" aria-expanded="false" aria-label="Toggle navigation" style="border-color: rgba(255, 255, 255, 0.3);">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="in3Topbar">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/student/ManageStudents.php" style="color: #cbd5e1 !important; font-weight: 500; padding: 0.6rem 1rem; border-radius: 6px; transition: all 0.3s ease;">
          <i class="fas fa-user-graduate mr-1"></i> Manage Students
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/group/Groups.php" style="color: #cbd5e1 !important; font-weight: 500; padding: 0.6rem 1rem; border-radius: 6px; transition: all 0.3s ease;">
          <i class="fas fa-users mr-1"></i> Manage Groups
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/attendance/Attendancenav.php" style="color: #cbd5e1 !important; font-weight: 500; padding: 0.6rem 1rem; border-radius: 6px; transition: all 0.3s ease;">
          <i class="fas fa-calendar-check mr-1"></i> Attendance (Dept-wise)
        </a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto align-items-center">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="in3User" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="color: #ffffff !important; font-weight: 600; padding: 0.6rem 1rem; border-radius: 6px; background: rgba(37, 99, 235, 0.2); transition: all 0.3s ease;">
          <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($u_n ?: 'Account'); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right shadow-lg" aria-labelledby="in3User" style="border: none; border-radius: 8px; margin-top: 0.5rem; background: #ffffff; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);">
          <a class="dropdown-item" href="<?php echo $base; ?>/Profile.php" style="color: #1e293b !important; padding: 0.75rem 1.25rem; font-weight: 500; transition: all 0.2s ease;">
            <i class="fas fa-user mr-2" style="color: #2563eb;"></i> Profile
          </a>
          <div class="dropdown-divider" style="margin: 0.5rem 0; border-color: #e2e8f0;"></div>
          <a class="dropdown-item" href="<?php echo $base; ?>/logout.php" style="color: #dc2626 !important; padding: 0.75rem 1.25rem; font-weight: 500; transition: all 0.2s ease;">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>
    </ul>
  </div>
</nav>
<style>
  /* Professional IN3 Top Navbar Styling */
  .navbar.navbar-dark .nav-link {
    transition: all 0.3s ease;
  }
  
  .navbar.navbar-dark .nav-link:hover {
    background: rgba(37, 99, 235, 0.15) !important;
    color: #ffffff !important;
    transform: translateY(-1px);
  }
  
  .navbar.navbar-dark .nav-link:active {
    background: rgba(37, 99, 235, 0.25) !important;
  }
  
  .navbar.navbar-dark .dropdown-item:hover {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
    color: #2563eb !important;
    padding-left: 1.5rem;
  }
  
  .navbar.navbar-dark .dropdown-item:has(.text-danger):hover,
  .navbar.navbar-dark .dropdown-item.text-danger:hover {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
    color: #dc2626 !important;
  }
  
  .navbar.navbar-dark .navbar-brand:hover {
    opacity: 0.9;
    transform: scale(1.02);
  }
</style>
