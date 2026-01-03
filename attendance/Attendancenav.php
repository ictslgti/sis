<!-- Attendance navigation partial: include only navigation UI, no headers/footers or self-includes -->

<?php $cur = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''); ?>
<div class="mb-4">
  <div class="text-center mb-3">
    <h2 class="mb-2" style="color: #0f172a; font-weight: 700; letter-spacing: 0.5px;">
      <i class="fas fa-calendar-check mr-2" style="color: #2563eb;"></i>Student Attendance System
    </h2>
  </div>

  <ul class="nav nav-tabs mb-3" style="border-bottom: 2px solid #e2e8f0;">
    <?php $ut = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : ''; ?>
    <?php if (in_array($ut, ['HOD','IN3'], true)) { ?>
      <li class="nav-item">
        <a class="nav-link <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? 'active' : ''; ?>" 
           href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php"
           style="color: <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? '#2563eb' : '#475569'; ?> !important; 
                  font-weight: 600; 
                  padding: 0.75rem 1.5rem; 
                  border: none;
                  border-bottom: <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? '3px solid #2563eb' : '3px solid transparent'; ?>;
                  transition: all 0.3s ease;
                  background: <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? 'rgba(37, 99, 235, 0.05)' : 'transparent'; ?>;">
          <i class="fas fa-chart-line mr-2"></i>Monthly Report
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?php echo (strpos($cur, '/attendance/BulkMonthlyMark.php') !== false) ? 'active' : ''; ?>" 
           href="<?php echo APP_BASE; ?>/attendance/BulkMonthlyMark.php"
           style="color: <?php echo (strpos($cur, '/attendance/BulkMonthlyMark.php') !== false) ? '#2563eb' : '#475569'; ?> !important; 
                  font-weight: 600; 
                  padding: 0.75rem 1.5rem; 
                  border: none;
                  border-bottom: <?php echo (strpos($cur, '/attendance/BulkMonthlyMark.php') !== false) ? '3px solid #2563eb' : '3px solid transparent'; ?>;
                  transition: all 0.3s ease;
                  background: <?php echo (strpos($cur, '/attendance/BulkMonthlyMark.php') !== false) ? 'rgba(37, 99, 235, 0.05)' : 'transparent'; ?>;">
          <i class="fas fa-tasks mr-2"></i>Bulk Monthly Mark
        </a>
      </li>
    <?php } elseif (in_array($ut, ['SAO','ADM','FIN','ACC'], true)) { ?>
      <li class="nav-item">
        <a class="nav-link <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? 'active' : ''; ?>" 
           href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php"
           style="color: <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? '#2563eb' : '#475569'; ?> !important; 
                  font-weight: 600; 
                  padding: 0.75rem 1.5rem; 
                  border: none;
                  border-bottom: <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? '3px solid #2563eb' : '3px solid transparent'; ?>;
                  transition: all 0.3s ease;
                  background: <?php echo (strpos($cur, '/attendance/MonthlyAttendanceReport.php') !== false) ? 'rgba(37, 99, 235, 0.05)' : 'transparent'; ?>;">
          <i class="fas fa-chart-line mr-2"></i>Monthly Report
        </a>
      </li>
    <?php } ?>
  </ul>
</div>
<style>
  /* Professional Attendance Nav Tabs */
  .nav-tabs .nav-link {
    transition: all 0.3s ease;
  }
  
  .nav-tabs .nav-link:hover {
    color: #2563eb !important;
    background: rgba(37, 99, 235, 0.05) !important;
    border-bottom-color: rgba(37, 99, 235, 0.3) !important;
  }
  
  .nav-tabs .nav-link.active {
    color: #2563eb !important;
    background: rgba(37, 99, 235, 0.05) !important;
    border-bottom-color: #2563eb !important;
    font-weight: 700;
  }
</style>
