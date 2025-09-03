<!-- Attendance navigation partial: include only navigation UI, no headers/footers or self-includes -->
 
 <div class="jumbotron-small text-center" style="margin-bottom:0">
   <h1>Student Attendance System</h1>
 </div>
 
 <ul class="nav nav-tabs">
  
   <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD') { ?>
   <li class="nav-item">
     <a class="nav-link" href="<?php echo APP_BASE; ?>/attendance/DailyAttendance.php">Daily Attendance (HOD)</a>
   </li>
   <li class="nav-item">
     <a class="nav-link" href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php">Monthly Report (HOD)</a>
   </li>
   <?php } ?>
 </ul>
