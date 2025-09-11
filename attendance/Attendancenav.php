<!-- Attendance navigation partial: include only navigation UI, no headers/footers or self-includes -->

<?php $cur = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''); ?>
<div class="container">
  <div class="jumbotron-small text-center" style="margin-bottom:.5rem">
    <h1 class="mb-2">Student Attendance System</h1>
  </div>

  <ul class="nav nav-tabs mb-3">
    <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['HOD','IN3'], true)) { ?>
      <li class="nav-item">
        <a class="nav-link <?php echo (strpos($cur, '/attendance/DailyAttendance.php') !== false) ? 'active' : ''; ?>" href="<?php echo APP_BASE; ?>/attendance/DailyAttendance.php">Daily Attendance</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? 'active' : ''; ?>" href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php">Monthly Report</a>
      </li>
    <?php } ?>
  </ul>
</div>
