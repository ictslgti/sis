<?php
// student/top_nav.php
// Compact top navbar for student pages
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$studentId = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
$studentName = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : '';
?>
<nav id="studentTopBar" class="navbar navbar-expand-md navbar-dark sticky-top shadow-lg" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); min-height: 56px; border-bottom: 2px solid rgba(37, 99, 235, 0.3);">
  <a class="navbar-brand d-flex align-items-center" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/student/StudentDashboard.php" style="font-size: 1rem; font-weight: 700; letter-spacing: 0.5px; color: #ffffff !important;">
    <i class="fas fa-graduation-cap mr-2" style="color: #2563eb;"></i>
    <span class="font-weight-bold">SLGTI</span>
    <span class="ml-2" style="font-size: 0.85rem; font-weight: 400; opacity: 0.9;">Student Portal</span>
  </a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#studentTopNav" aria-controls="studentTopNav" aria-expanded="false" aria-label="Toggle navigation" style="border-color: rgba(255, 255, 255, 0.3);">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="studentTopNav">
    <ul class="navbar-nav mr-auto">
    </ul>

    <ul class="navbar-nav ml-auto align-items-center">
      <li class="nav-item">
        <a class="nav-link" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/season/RequestSeason.php" style="color: #cbd5e1 !important; font-weight: 500; padding: 0.5rem 1rem; border-radius: 6px; transition: all 0.3s ease;">
          <i class="fas fa-bus mr-1"></i> Season Request
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/season/SeasonRequests.php" style="color: #cbd5e1 !important; font-weight: 500; padding: 0.5rem 1rem; border-radius: 6px; transition: all 0.3s ease;">
          <i class="fas fa-list mr-1"></i> My Requests
        </a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="studentTopMenu" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="color: #ffffff !important; font-weight: 600; padding: 0.5rem 1rem; border-radius: 6px; background: rgba(37, 99, 235, 0.2); transition: all 0.3s ease;">
          <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($studentId ?: 'Account'); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right shadow-lg" aria-labelledby="studentTopMenu" style="border: none; border-radius: 8px; margin-top: 0.5rem; background: #ffffff; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);">
          <a class="dropdown-item" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/student/Student_profile.php" style="color: #1e293b !important; padding: 0.75rem 1.25rem; font-weight: 500; transition: all 0.2s ease;">
            <i class="fas fa-user mr-2" style="color: #2563eb;"></i> Profile
          </a>
          <div class="dropdown-divider" style="margin: 0.5rem 0; border-color: #e2e8f0;"></div>
          <a class="dropdown-item text-danger" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/logout.php" style="color: #dc2626 !important; padding: 0.75rem 1.25rem; font-weight: 500; transition: all 0.2s ease;">
            <i class="fas fa-sign-out-alt mr-2"></i> Logout
          </a>
        </div>
      </li>
    </ul>
  </div>
</nav>
<style>
  /* Professional Student Top Navbar Styling */
  #studentTopBar .nav-link {
    transition: all 0.3s ease;
  }
  
  #studentTopBar .nav-link:hover {
    background: rgba(37, 99, 235, 0.15) !important;
    color: #ffffff !important;
    transform: translateY(-1px);
  }
  
  #studentTopBar .nav-link:active {
    background: rgba(37, 99, 235, 0.25) !important;
  }
  
  #studentTopBar .dropdown-item:hover {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
    color: #2563eb !important;
    padding-left: 1.5rem;
  }
  
  #studentTopBar .dropdown-item.text-danger:hover {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
    color: #dc2626 !important;
  }
  
  #studentTopBar .navbar-brand:hover {
    opacity: 0.9;
    transform: scale(1.02);
  }
</style>
<script>
  (function(){
    function applyTheme(theme){
      try{
        var body = document.body;
        var nav = document.getElementById('studentTopBar');
        if(!body || !nav) return;
        // Always use dark navbar with professional gradient
        nav.classList.remove('navbar-light','bg-light');
        nav.classList.add('navbar-dark');
        nav.style.background = 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)';
        nav.style.borderBottom = '2px solid rgba(37, 99, 235, 0.3)';
        
        if(theme === 'dark'){
          body.classList.add('theme-dark');
        } else {
          body.classList.remove('theme-dark');
        }
      }catch(e){}
    }
    document.addEventListener('DOMContentLoaded', function(){
      var current = localStorage.getItem('slgti_theme') || 'light';
      applyTheme(current);
    });
  })();
</script>
