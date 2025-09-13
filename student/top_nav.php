<?php
// student/top_nav.php
// Compact top navbar for student pages
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$studentId = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
$studentName = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : '';
?>
<nav id="studentTopBar" class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm sticky-top py-0" style="min-height:44px;">
  <a class="navbar-brand d-flex align-items-center" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/student/StudentDashboard.php" style="font-size: 0.95rem;">
    <span class="font-weight-semibold">Sri Lanka German Training Institute</span>
   
  </a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#studentTopNav" aria-controls="studentTopNav" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="studentTopNav">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item">
        <a class="nav-link" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/onpeak/RequestOnPeak.php">OnPeak Request</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/student/request_hostel.php">Hostel</a>
      </li>
      
      
    </ul>

    <ul class="navbar-nav ml-auto align-items-center">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="studentTopMenu" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <?php echo htmlspecialchars($studentId ?: 'Account'); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="studentTopMenu">
          <a class="dropdown-item" href="/student/Student_profile.php">Profile</a>
          
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="/logout.php">Logout</a>
        </div>
      </li>
    </ul>
  </div>
</nav>
<script>
  (function(){
    function applyTheme(theme){
      try{
        var body = document.body;
        var nav = document.getElementById('studentTopBar');
        if(!body || !nav) return;
        if(theme === 'dark'){
          body.classList.add('theme-dark');
          nav.classList.remove('navbar-light','bg-light');
          nav.classList.add('navbar-dark','bg-dark');
        } else {
          body.classList.remove('theme-dark');
          nav.classList.remove('navbar-dark','bg-dark');
          nav.classList.add('navbar-light','bg-light');
        }
      }catch(e){}
    }
    document.addEventListener('DOMContentLoaded', function(){
      var current = localStorage.getItem('slgti_theme') || 'light';
      applyTheme(current);
    });
  })();
</script>
