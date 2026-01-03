<?php
// Ensure session and safely read session variables
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$u_n  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$u_ta = isset($_SESSION['user_table']) ? $_SESSION['user_table'] : '';
$u_t  = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$d_c  = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';

// Ensure DB connection is available for lookups in this file
if (!isset($con) || !($con instanceof mysqli)) {
  $cfg = __DIR__ . '/config.php';
  if (file_exists($cfg)) {
    include_once $cfg;
  }
}

// Normalize user_type for consistent comparisons
if (isset($_SESSION['user_type'])) {
  $_SESSION['user_type'] = strtoupper(trim($_SESSION['user_type']));
  $u_t = $_SESSION['user_type'];
}

$username = null;
if ($u_ta == 'staff' && isset($con) && ($con instanceof mysqli)) {
  $sql = "SELECT staff_name FROM `staff` WHERE `staff_id` = '" . mysqli_real_escape_string($con, $u_n) . "'";
  if ($result = mysqli_query($con, $sql)) {
    if (mysqli_num_rows($result) == 1) {
      $row = mysqli_fetch_assoc($result);
      $username = $row['staff_name'] ?? null;
    }
    mysqli_free_result($result);
  }
}
if ($u_ta == 'student' && isset($con) && ($con instanceof mysqli)) {
  $sql = "SELECT student_fullname FROM `student` WHERE `student_id` = '" . mysqli_real_escape_string($con, $u_n) . "'";
  if ($result = mysqli_query($con, $sql)) {
    if (mysqli_num_rows($result) == 1) {
      $row = mysqli_fetch_assoc($result);
      $username = $row['student_fullname'] ?? null;
    }
    mysqli_free_result($result);
  }
}

// For student users, render top navbar only (no sidebar)
if ($u_t === 'STU') {
  $student_top_nav = __DIR__ . '/student/top_nav.php';
  if (file_exists($student_top_nav)) {
    include $student_top_nav;
  }
  return; // stop sidebar rendering for students
}

// All non-student roles will use the sidebar navbar (like admin)
// Permissions remain unchanged - sidebar menu items are role-based

?>
<style>
  /* ============================================
     BLUE THEME SIDEBAR - PROFESSIONAL MENU
     NO HOVER EFFECTS - CLEAN & SIMPLE
     ============================================ */
  
  /* Sidebar Base - Blue Theme */
  .chiller-theme .sidebar-wrapper {
    background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
    box-shadow: 2px 0 15px rgba(0, 0, 0, 0.2);
    border-right: 1px solid #1e3a8a;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-brand {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    padding: 1.25rem 1.5rem;
    border-bottom: 2px solid rgba(37, 99, 235, 0.2);
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-brand > a {
    color: #ffffff !important;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
    text-decoration: none;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-brand > a i {
    color: #c7d2fe;
    font-size: 1.5rem;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-brand #close-sidebar {
    color: #ffffff;
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 6px;
  }
  
  /* Sidebar Header - Blue Theme */
  .chiller-theme .sidebar-wrapper .sidebar-header {
    background: rgba(30, 58, 138, 0.3) !important;
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-header .user-pic {
    border: 3px solid #ffffff;
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2);
    background: #ffffff;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-header .user-info .user-name {
    color: #ffffff !important;
    font-weight: 700;
    font-size: 0.95rem;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-header .user-info .user-role {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.75rem;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-header .user-info .user-status a {
    color: rgba(255, 255, 255, 0.9) !important;
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    text-decoration: none;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-header .user-info .user-status a[href*="logout"] {
    color: #fca5a5 !important;
    border-color: rgba(252, 165, 165, 0.3);
  }
  
  /* Menu Items - Professional Alignment - No Hover */
  .chiller-theme .sidebar-wrapper .sidebar-menu {
    background: transparent;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu ul {
    list-style: none !important;
    padding: 0;
    margin: 0;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li {
    list-style: none !important;
    list-style-type: none !important;
    margin: 0;
    padding: 0;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li a {
    color: rgba(255, 255, 255, 0.9) !important;
    background: transparent !important;
    padding: 0.875rem 1.5rem;
    border: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 0.75rem;
    text-decoration: none;
    position: relative;
    width: 100%;
    box-sizing: border-box;
    line-height: 1.5;
    min-height: 44px;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li a i {
    color: rgba(255, 255, 255, 0.8);
    width: 20px !important;
    min-width: 20px !important;
    text-align: center !important;
    font-size: 1rem !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0;
    line-height: 1;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li a span {
    flex: 1;
    font-weight: 500;
    display: inline-block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.5;
  }
  
  /* Active Menu Item - No Background, No Border */
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li.active > a {
    background: transparent !important;
    color: #ffffff !important;
    font-weight: 600;
    border: none !important;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li.active > a i {
    color: #ffffff;
  }
  
  /* Dropdown Menu - Blue Theme */
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-dropdown > a:after {
    position: absolute !important;
    right: 1.5rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: rgba(255, 255, 255, 0.7);
    margin-left: auto;
    flex-shrink: 0;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-dropdown.active > a {
    background: transparent !important;
    color: #ffffff !important;
    font-weight: 600;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-dropdown.active > a:after {
    color: #ffffff;
    transform: translateY(-50%) rotate(90deg) !important;
  }
  
  /* Submenu - Blue Theme - Professional Alignment - No Hover */
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu {
    background: rgba(30, 58, 138, 0.2);
    border-left: 2px solid rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0;
    margin: 0;
    display: none !important; /* Hidden by default - only show on click */
  }
  
  /* Show submenu only when parent dropdown is active */
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-dropdown.active > .sidebar-submenu {
    display: block !important;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu ul {
    list-style: none !important;
    padding: 0;
    margin: 0;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu ul li {
    list-style: none !important;
    list-style-type: none !important;
    margin: 0;
    padding: 0;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu ul li a {
    color: rgba(255, 255, 255, 0.8) !important;
    background: transparent !important;
    padding: 0.75rem 1.5rem 0.75rem 2.5rem !important;
    font-size: 0.9rem;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 0.625rem;
    text-decoration: none;
    position: relative;
    width: 100%;
    box-sizing: border-box;
    line-height: 1.5;
    min-height: 40px;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu ul li a i {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem !important;
    width: 18px !important;
    min-width: 18px !important;
    text-align: center !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0;
    line-height: 1;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu ul li a span {
    flex: 1;
    display: inline-block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.5;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .sidebar-submenu ul li.active > a {
    background: transparent !important;
    color: #ffffff !important;
    font-weight: 600;
  }
  
  /* Header Menu - Blue Theme */
  .chiller-theme .sidebar-wrapper .sidebar-menu .header-menu {
    padding: 1rem 1.5rem 0.5rem;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: block;
    margin: 0;
    line-height: 1.5;
  }
  
  .chiller-theme .sidebar-wrapper .sidebar-menu .header-menu span {
    display: block;
    line-height: 1.5;
  }
  
  /* Global: Remove all bullets from sidebar menu lists */
  .chiller-theme .sidebar-wrapper ul,
  .chiller-theme .sidebar-wrapper ul li,
  .chiller-theme .sidebar-wrapper .sidebar-menu ul,
  .chiller-theme .sidebar-wrapper .sidebar-menu ul li,
  .chiller-theme .sidebar-wrapper .sidebar-submenu ul,
  .chiller-theme .sidebar-wrapper .sidebar-submenu ul li {
    list-style: none !important;
    list-style-type: none !important;
  }
  
  /* Show Sidebar Button */
  #show-sidebar {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    color: #ffffff;
    box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
    border: none;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
  }
  
  /* Scrollbar */
  .chiller-theme .sidebar-content::-webkit-scrollbar {
    width: 6px;
  }
  
  .chiller-theme .sidebar-content::-webkit-scrollbar-track {
    background: rgba(30, 58, 138, 0.3);
    border-radius: 10px;
  }
  
  .chiller-theme .sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 10px;
  }
  
  /* Mobile Responsive */
  @media (max-width: 575.98px) {
    .page-content .container-fluid {
      padding: 0.75rem;
    }
    
    #sidebar .sidebar-header {
      padding: 1rem;
    }
    
    #sidebar .sidebar-menu ul li a {
      padding-top: 0.75rem;
      padding-bottom: 0.75rem;
    }
    
    #sidebar .sidebar-submenu ul li a {
      padding-left: 2rem;
    }
    
    #show-sidebar.btn {
      top: 0.5rem;
      left: 0.5rem;
      position: sticky;
      z-index: 1030;
    }
  }
  
  @media (min-width: 576px) and (max-width: 991.98px) {
    .page-content .container-fluid {
      padding: 1rem;
    }
  }
  
  /* Page Content Alignment */
  .page-wrapper .page-content {
    padding-top: 0.5rem;
  }
  
  .page-content .container-fluid {
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
    padding-left: 15px;
    padding-right: 15px;
  }
  
  @media (min-width: 992px) {
    .page-content .container-fluid {
      padding-left: 20px;
      padding-right: 20px;
    }
  }
</style>

<nav id="sidebar" class="sidebar-wrapper">
  <div class="sidebar-content">
    <div class="sidebar-brand">
      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?><?php echo ($_SESSION['user_type'] == 'STU') ? '/home/home.php' : '/dashboard/index.php'; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
        <i class="fas fa-university"></i>
        <span>MIS@SLGTI</span>
      </a>
      <div id="close-sidebar" style="cursor: pointer;">
        <i class="fas fa-times"></i>
      </div>
    </div>
    
    <div class="sidebar-header">
      <div class="user-pic">
        <img class="img-responsive img-rounded" src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/user.jpg" alt="<?php echo htmlspecialchars($u_n); ?> picture">
      </div>
      <div class="user-info">
        <span class="user-name">
          <?php echo htmlspecialchars($u_n); ?>
        </span>
        <span class="user-role">
          <?php echo htmlspecialchars($u_t ?: ''); ?> | <?php echo htmlspecialchars($d_c ?: ''); ?>
        </span>
        <span class="user-status">
          <a href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); echo ($_SESSION['user_type'] == 'STU') ? '/student/Student_profile.php' : '/Profile.php'; ?>">
            <i class="fas fa-user-circle"></i> Profile
          </a>
          <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </span>
      </div>
    </div>
    
    <div class="sidebar-menu">
      <?php if ($u_t === 'WAR') { ?>
        <ul>
          <li class="header-menu"><span>General</span></li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
              <i class="fa fa-home"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="far fa-building"></i>
              <span>Hostels</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Requests.php">Hostel Requests</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Payments.php">Hostel Payments</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AllocatedRoomWise.php">Hostel Info</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManualAllocate.php">Manual Allocate</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/SwapRooms.php">Swap Rooms</a></li>
              </ul>
            </div>
          </li>
        </ul>
      <?php } elseif ($u_t === 'MA2') { ?>
        <ul>
          <li class="header-menu"><span>General</span></li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
              <i class="fa fa-home"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-user-graduate"></i>
              <span>Students</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">Students Info</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AddStudent.php">Add a Student</a></li>
                <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentReEnroll.php">Student Re Enroll</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ChangeEnrollment.php">Change Course/Reg No</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentEnrollmentReport.php">Student Enrollment Report</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentIDPhoto.php">Student ID Photo</a></li>
                <li><a href="#" onclick="(function(){var sid=prompt('Enter Student ID to download Application Form:'); if(sid){ window.open('<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/library/pdf/student_application.php?Sid='+encodeURIComponent(sid), '_blank'); }})(); return false;">Download Student Application</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/UploadDocumentation.php">Upload Student Documentation (PDF)</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php">Import Student Enrollment</a></li>
              </ul>
            </div>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/RegistrationPaymentsApproved.php">
              <i class="fa fa-check"></i>
              <span>Approved Registration Payments</span>
            </a>
          </li>
        </ul>
      <?php } elseif (in_array($u_t, ['DIR', 'ACC'], true)) { ?>
        <ul>
          <li class="header-menu"><span>Director</span></li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
              <i class="fa fa-home"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php">
              <i class="fas fa-university"></i>
              <span>Departments Info</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">
              <i class="fas fa-user-graduate"></i>
              <span>Students Info</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/gender_pie.php">
              <i class="fas fa-chart-pie"></i>
              <span>Gender Pie by Department</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AllocatedRoomWise.php">
              <i class="far fa-building"></i>
              <span>Hostels Info</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/ConductReport.php">
              <i class="fas fa-check-circle"></i>
              <span>Department Wise Conduct Acceptance</span>
            </a>
          </li>
        </ul>
      <?php } elseif ($u_t === 'FIN') { ?>
        <ul>
          <li class="header-menu"><span>Finance</span></li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
              <i class="fa fa-home"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fab fa-amazon-pay"></i>
              <span>Payments</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/CollectPayment.php">Collect Payment</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/PaymentsSummary.php">Payments Summary</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/ManagePaymentTypes.php">Manage Payment Types</a></li>
              </ul>
            </div>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AllocatedRoomWise.php">
              <i class="far fa-building"></i>
              <span>Hostel Info</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">
              <i class="fas fa-users"></i>
              <span>Manage Students</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance Report</span>
            </a>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-bus"></i>
              <span>Season Reports</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport1.php">Report 1 - Student Details</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport2.php">Report 2 - Payment Details</a></li>
              </ul>
            </div>
          </li>
        </ul>
      <?php } elseif ($u_t === 'HOD') { ?>
        <ul>
          <li class="header-menu"><span>Head of Department</span></li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hod/Dashboard.php">
              <i class="fa fa-tachometer-alt"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php"><i class="fas fa-calendar-alt mr-1"></i> Monthly Report</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/BulkMonthlyMark.php"><i class="fas fa-tasks mr-1"></i> Bulk Monthly Mark</a></li>
              </ul>
            </div>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-university"></i>
              <span>My Department</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php">Courses</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php">Modules</a></li>
              </ul>
            </div>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-users"></i>
              <span>Groups</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/Groups.php">Groups</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/AddGroup.php">Add Group</a></li>
              </ul>
            </div>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-user-tie"></i>
              <span>Staff</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffManage.php">Manage Staff</a></li>
              </ul>
            </div>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">
              <i class="fas fa-user-graduate"></i>
              <span>My Dept Students</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php">
              <i class="fas fa-bus"></i>
              <span>Season Approval</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak/OnPeak.php">
              <i class="far fa-calendar-check"></i>
              <span>OnPeak Calendar</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/password/change_password.php">
              <i class="fa fa-key"></i>
              <span>Change Password</span>
            </a>
          </li>
        </ul>
      <?php } elseif (in_array($u_t, ['IN1', 'IN2', 'IN3'], true)) { ?>
        <ul>
          <li class="header-menu"><span>Instructor</span></li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hod/Dashboard.php">
              <i class="fa fa-tachometer-alt"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php"><i class="fas fa-calendar-alt mr-1"></i> Monthly Report</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/BulkMonthlyMark.php"><i class="fas fa-tasks mr-1"></i> Bulk Monthly Mark</a></li>
              </ul>
            </div>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-university"></i>
              <span>My Department</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php">Courses</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php">Modules</a></li>
              </ul>
            </div>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-users"></i>
              <span>Groups</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/Groups.php">Groups</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/AddGroup.php">Add Group</a></li>
              </ul>
            </div>
          </li>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-user-tie"></i>
              <span>Staff</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffManage.php">Manage Staff</a></li>
              </ul>
            </div>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">
              <i class="fas fa-user-graduate"></i>
              <span>My Dept Students</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak/OnPeak.php">
              <i class="far fa-calendar-check"></i>
              <span>OnPeak Calendar</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/password/change_password.php">
              <i class="fa fa-key"></i>
              <span>Change Password</span>
            </a>
          </li>
        </ul>
      <?php } else { ?>
        <ul>
          <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM') { ?>
            <li class="header-menu">
              <span><i class="fas fa-user-shield mr-2"></i>Administrator</span>
            </li>
            
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
              </a>
            </li>
            
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/index.php">
                <i class="fas fa-home"></i>
                <span>Index</span>
              </a>
            </li>
            
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-university"></i>
                <span>Departments & Academic</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php"><i class="fas fa-list mr-1"></i>Departments Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/AddDepartment.php"><i class="fas fa-plus-circle mr-1"></i>Add Department</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AcademicYear.php"><i class="fas fa-calendar-alt mr-1"></i>Academic Years</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AddAcademicYear.php"><i class="fas fa-plus-circle mr-1"></i>Add Academic Year</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php"><i class="fas fa-book mr-1"></i>Courses</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php"><i class="fas fa-plus-circle mr-1"></i>Add Course</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php"><i class="fas fa-cubes mr-1"></i>Modules</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php"><i class="fas fa-users mr-1"></i>Manage Students</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php"><i class="fas fa-file-upload mr-1"></i>Import Student Enrollment</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ExportStudents.php"><i class="fas fa-file-download mr-1"></i>Export Students (CSV)</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentReEnroll.php"><i class="fas fa-redo mr-1"></i>Student Re Enroll</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ChangeEnrollment.php"><i class="fas fa-exchange-alt mr-1"></i>Change Course</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/UploadDocumentation.php"><i class="fas fa-file-pdf mr-1"></i>Upload Student Documentation (PDF)</a></li>
                  <li><a href="#" onclick="(function(){var sid=prompt('Enter Student ID for ID Card:'); if(sid){ window.open('<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentIDCard.php?id='+encodeURIComponent(sid), '_blank'); }})(); return false;"><i class="fas fa-id-card mr-1"></i>Student ID Card</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-user-tie"></i>
                <span>Staff</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffManage.php"><i class="fas fa-users-cog mr-1"></i>Manage Staff</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffPositionType.php"><i class="fas fa-briefcase mr-1"></i>Staff Position Types</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-award"></i>
                <span>Examinations</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/exam/EndExams.php"><i class="fas fa-clipboard-check mr-1"></i>End Exams</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/exam/Transcript.php"><i class="fas fa-file-alt mr-1"></i>Transcript</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php"><i class="fas fa-chart-bar mr-1"></i>Monthly Attendance Report</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-bus"></i>
                <span>Season Requests</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php"><i class="fas fa-list mr-1"></i>All Requests</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php"><i class="fas fa-check-circle mr-1"></i>Approve Requests</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php"><i class="fas fa-money-bill-wave mr-1"></i>Collect Payment</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/IssueSeason.php"><i class="fas fa-ticket-alt mr-1"></i>Issue Season</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport1.php"><i class="fas fa-file-alt mr-1"></i>Report 1 - Student Details</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport2.php"><i class="fas fa-file-invoice mr-1"></i>Report 2 - Payment Details</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="far fa-building"></i>
                <span>Hostels</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManageHostel.php"><i class="fas fa-building mr-1"></i>Manage Hostels & Blocks</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManageRooms.php"><i class="fas fa-door-open mr-1"></i>Manage Rooms</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php"><i class="fas fa-info-circle mr-1"></i>Hostels Info</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-briefcase"></i>
                <span>On-the-job Training</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/OJT.php"><i class="fas fa-list mr-1"></i>On-the-job Training Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/addojt.php"><i class="fas fa-plus-circle mr-1"></i>Add a Training Place</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-calendar-alt"></i>
                <span>Timetable & Notices</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/Timetable.php"><i class="fas fa-calendar mr-1"></i>Timetable</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/Notice.php"><i class="fas fa-bullhorn mr-1"></i>Notice Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/AddNotice.php"><i class="fas fa-plus-circle mr-1"></i>Add Notice</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-book-open"></i>
                <span>Library</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/LibraryHome.php"><i class="fas fa-home mr-1"></i>Library Home</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/AddBook.php"><i class="fas fa-plus-circle mr-1"></i>Add a Book</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/IssueBook.php"><i class="fas fa-hand-holding mr-1"></i>Issue a Book</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/ViewBooks.php"><i class="fas fa-list mr-1"></i>All Books</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/IssuedBook.php"><i class="fas fa-book-reader mr-1"></i>Issued Books Info</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="far fa-grin"></i>
                <span>Feedbacks</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentFeedbackinfo.php"><i class="fas fa-comments mr-1"></i>Students Feedback Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AddStudentFeedback.php"><i class="fas fa-plus-circle mr-1"></i>Create a Student Feedback</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-money-check-alt"></i>
                <span>Payroll System</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/ManagePayroll.php"><i class="fas fa-calculator mr-1"></i>Payroll (Admin)</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-network-wired"></i>
                <span>Network Management</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/devices/NetworkSettings.php"><i class="fas fa-cog mr-1"></i>Network Settings</a></li>
                </ul>
              </div>
            </li>

            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-cogs"></i>
                <span>Administration</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/Administration.php">Admin Dashboard</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/LoginActivity.php">Login Activity</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/ConductReport.php">Conduct Report</a></li>
                  <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/DatabaseExport.php?download=1&simple=1">Database Export</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/StudentImageBackup.php">Student Image Backup</a></li>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO') { ?>
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/index.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
              </a>
            </li>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="far fa-building"></i>
                <span>Hostel</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/BulkRoomAssign.php">Bulk Assign</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManualAllocate.php">Manual Allocate</a></li>
                </ul>
              </div>
            </li>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">Manage Students</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ExportStudents.php">Export Students (CSV)</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php">Add a Student</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AllowanceEligibility.php">Allowance Eligibility</a></li>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['SAO'])) { ?>
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance Report</span>
              </a>
            </li>
          <?php } ?>

          <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['HOD', 'IN1', 'IN2', 'LE1', 'LE2'])) { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-university"></i>
                <span>Departments</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php">Departments Info</a></li>
                  <?php if ($_SESSION['user_type'] == 'ADM') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/AddDepartment.php">Add a Department</a></li>
                  <?php } ?>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AcademicYear.php">Academic Years</a></li>
                  <?php if ($_SESSION['user_type'] == 'ADM') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AddAcademicYear.php">Add Academic Year</a></li>
                  <?php } ?>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php">Courses</a></li>
                  <?php if (($_SESSION['user_type'] == 'ADM') || ($_SESSION['user_type'] == 'HOD')) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php">Add Course</a></li>
                  <?php } ?>
                  <?php if (($_SESSION['user_type'] == 'ADM') || ($_SESSION['user_type'] == 'HOD')) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php">Modules</a></li>
                  <?php } ?>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if ($_SESSION['user_type'] != 'STU' && $_SESSION['user_type'] !== 'SAO' && !is_role('IN2')) { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-user-tie"></i>
                <span>Staffs</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffManage.php">Manage Staff</a></li>
                  <?php if ($_SESSION['user_type'] == 'HOD') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffModuleEnrollment.php">Module Enrollment</a></li>
                  <?php } ?>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if (can_view(['HOD'])) { ?>
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">
                <i class="fas fa-user-graduate"></i>
                <span>My Dept Students</span>
              </a>
            </li>
          <?php } ?>

          <?php if ($_SESSION['user_type'] !== 'SAO') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-calendar-alt"></i>
                <span>Timetable & Notice</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/Timetable.php">Timetable</a></li>
                  <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD'])) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/AddTimetable.php">Add a Timetable</a></li>
                  <?php } ?>
                  <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD'])) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/Notice.php">Notice Info</a></li>
                  <?php } ?>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if ($_SESSION['user_type'] != 'STU' && $_SESSION['user_type'] !== 'SAO' && !is_role('IN2') && $_SESSION['user_type'] !== 'ADM') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="far fa-building"></i>
                <span>Hostels</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <?php if ($u_t === 'WAR') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Requests.php">Hostel Requests</a></li>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a></li>
                  <?php } ?>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if (in_array($_SESSION['user_type'], ['HOD', 'WAR', 'SAO'], true)) { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-bus"></i>
                <span>Season Requests</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php">All Requests</a></li>
                  <?php if (in_array($_SESSION['user_type'], ['HOD'], true)) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php">Approve Requests</a></li>
                  <?php } ?>
                  <?php if (in_array($_SESSION['user_type'], ['HOD', 'SAO'], true)) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php">Collect Payment</a></li>
                  <?php } ?>
                  <?php if (in_array($_SESSION['user_type'], ['HOD', 'SAO'], true)) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/IssueSeason.php">Issue Season</a></li>
                  <?php } ?>
                  <?php if (in_array($_SESSION['user_type'], ['HOD', 'SAO'], true)) { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport1.php">Report 1 - Student Details</a></li>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport2.php">Report 2 - Payment Details</a></li>
                  <?php } ?>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if ($_SESSION['user_type'] != 'STU' && $_SESSION['user_type'] !== 'SAO' && !is_role('IN2') && $_SESSION['user_type'] != 'FIN') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fab fa-amazon-pay"></i>
                <span>Payments</span>
              </a>
              <div class="sidebar-submenu">
                <ul></ul>
              </div>
            </li>
          <?php } ?>

          <?php if ($_SESSION['user_type'] !== 'SAO') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-door-open"></i>
                <span>On-Peak & Off-Peak</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <?php if ($_SESSION['user_type'] != 'STU') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/OnPeak.php">On-Peak Info</a></li>
                  <?php } ?>
                  <?php if ($_SESSION['user_type'] == 'STU') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/RequestOnPeak.php">Request a On-Peak</a></li>
                    <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                  <?php } ?>
                  <?php if ($_SESSION['user_type'] == 'WAR') { ?>
                    <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/OffPeak.php">Off-Peak Info</a></li>
                  <?php } ?>
                  <?php if ($_SESSION['user_type'] == 'STU') { ?>
                    <li><a href="#">Request a Off-Peak</a></li>
                  <?php } ?>
                </ul>
              </div>
            </li>

            <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD', 'ADM'])) { ?>
              <li class="sidebar-dropdown">
                <a href="#">
                  <i class="fas fa-calendar-check"></i>
                  <span>Attendance</span>
                </a>
                <div class="sidebar-submenu">
                  <ul>
                    <?php if ($_SESSION['user_type'] == 'WAR') { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Attendance.php">Attendance Info</a></li>
                    <?php } ?>
                    <?php if ($_SESSION['user_type'] == 'WAR') { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/DailyAttendance.php">Daily Attendance</a></li>
                    <?php } ?>
                    <?php if ($_SESSION['user_type'] == 'HOD') { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">Monthly Report</a></li>
                    <?php } ?>
                    <?php if ($_SESSION['user_type'] == 'HOD') { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/BulkMonthlyMark.php">Bulk Monthly Mark</a></li>
                    <?php } ?>
                    <?php if ($_SESSION['user_type'] == 'ADM') { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">Monthly Attendance Report</a></li>
                    <?php } ?>
                  </ul>
                </div>
              </li>
            <?php } ?>

            <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD', 'ADM'])) { ?>
              <li class="sidebar-dropdown">
                <a href="#">
                  <i class="fas fa-tint"></i>
                  <span>Payroll System</span>
                </a>
                <div class="sidebar-submenu">
                  <ul>
                    <?php if (($_SESSION['user_type'] == 'WAR') || ($_SESSION['user_type'] == 'HOD')) { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Payroll.php">Payroll Info</a></li>
                    <?php } ?>
                    <?php if (($_SESSION['user_type'] == 'WAR') || ($_SESSION['user_type'] == 'HOD')) { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Payroll.php">Payroll</a></li>
                      <li><hr style="margin: 0.5rem 0; border-color: #e2e8f0;"></li>
                    <?php } ?>
                    <?php if ($_SESSION['user_type'] == 'ADM') { ?>
                      <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/ManagePayroll.php">Payroll (Admin)</a></li>
                    <?php } ?>
                  </ul>
                </div>
              </li>
            <?php } ?>
          <?php } ?>

          <li class="header-menu">
            <span>Extra</span>
          </li>
          
          <li>
            <a href="#">
              <i class="fa fa-book"></i>
              <span>Documentation</span>
            </a>
          </li>
          
          <?php if ($_SESSION['user_type'] !== 'SAO') { ?>
            <li>
              <a href="#">
                <i class="fa fa-calendar"></i>
                <span>Calendar</span>
              </a>
            </li>
          <?php } ?>
          
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/password/change_password.php">
              <i class="fa fa-key"></i>
              <span>Change Password</span>
            </a>
          </li>
        </ul>
      <?php } ?>
    </div>
    <!-- sidebar-menu  -->
  </div>
  <!-- sidebar-content  -->
  <div class="sidebar-footer" style="display:none;"></div>
</nav>

<script>
  // ============================================
  // SIDEBAR - CLICK TO EXPAND SUBMENUS
  // SUBMENUS CLOSED BY DEFAULT ON RELOAD
  // ============================================
  (function() {
    'use strict';
    
    function initSidebar() {
      try {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        
        // Close all submenus by default on page load
        closeAllSubmenus();
        
        // Highlight active menu item (but don't expand submenu)
        highlightActiveMenuItem();
        
        // Setup click handlers for dropdown menus
        setupDropdownClickHandlers();
        
        var sidebarContent = sidebar.querySelector('.sidebar-content');
        if (sidebarContent) {
          sidebarContent.style.scrollBehavior = 'smooth';
        }
        
        ensureAdminAlignment();
      } catch(e) {
        // Silent fail
      }
    }
    
    // Close all submenus on page load
    function closeAllSubmenus() {
      try {
        var allSubmenus = document.querySelectorAll('#sidebar .sidebar-submenu');
        allSubmenus.forEach(function(submenu) {
          submenu.style.display = 'none';
        });
        
        var allDropdowns = document.querySelectorAll('#sidebar .sidebar-dropdown');
        allDropdowns.forEach(function(dropdown) {
          dropdown.classList.remove('active');
        });
      } catch(e) {
        // Silent fail
      }
    }
    
    // Setup click handlers for dropdown menus
    function setupDropdownClickHandlers() {
      try {
        var dropdownLinks = document.querySelectorAll('#sidebar .sidebar-dropdown > a');
        dropdownLinks.forEach(function(dropdownLink) {
          dropdownLink.addEventListener('click', function(e) {
            var href = this.getAttribute('href');
            // Only handle if it's a dropdown toggle (href is # or javascript:void(0))
            if (href === '#' || href === 'javascript:void(0)' || !href) {
              e.preventDefault();
              e.stopPropagation();
              
              var dropdown = this.closest('.sidebar-dropdown');
              if (dropdown) {
                var submenu = dropdown.querySelector('.sidebar-submenu');
                if (submenu) {
                  var isActive = dropdown.classList.contains('active');
                  
                  // Close all other dropdowns
                  var allDropdowns = document.querySelectorAll('#sidebar .sidebar-dropdown');
                  allDropdowns.forEach(function(otherDropdown) {
                    if (otherDropdown !== dropdown) {
                      otherDropdown.classList.remove('active');
                      var otherSubmenu = otherDropdown.querySelector('.sidebar-submenu');
                      if (otherSubmenu) {
                        otherSubmenu.style.display = 'none';
                      }
                    }
                  });
                  
                  // Toggle current dropdown
                  if (isActive) {
                    dropdown.classList.remove('active');
                    submenu.style.display = 'none';
                  } else {
                    dropdown.classList.add('active');
                    submenu.style.display = 'block';
                  }
                }
              }
            }
          });
        });
      } catch(e) {
        // Silent fail
      }
    }
    
    function highlightActiveMenuItem() {
      try {
        var currentPath = window.location.pathname;
        var currentFile = currentPath.split('/').pop() || '';
        var basePath = (document.querySelector('base') || {}).href || '';
        
        var menuLinks = document.querySelectorAll('#sidebar .sidebar-menu a[href]');
        menuLinks.forEach(function(link) {
          try {
            var href = link.getAttribute('href') || '';
            if (!href || href === '#' || href === 'javascript:void(0)') return;
            
            var normalizedHref = href.replace(basePath, '').replace(/^\//, '');
            var normalizedPath = currentPath.replace(/^\//, '');
            
            if (normalizedPath.includes(normalizedHref) || 
                normalizedHref.includes(currentFile) ||
                currentFile === normalizedHref.split('/').pop()) {
              
              var listItem = link.closest('li');
              if (listItem) {
                listItem.classList.add('active');
                
                // Don't auto-expand submenus on page load
                // Submenus will only open when clicked
              }
            }
          } catch(e) {
            // Silent fail
          }
        });
      } catch(e) {
        // Silent fail
      }
    }
    
    function ensureAdminAlignment() {
      try {
        var containerFluid = document.querySelector('.page-content .container-fluid');
        if (!containerFluid) return;
        
        var computedStyle = window.getComputedStyle(containerFluid);
        var maxWidth = computedStyle.maxWidth;
        
        if (maxWidth && maxWidth !== 'none' && maxWidth !== '100%') {
          containerFluid.style.marginLeft = 'auto';
          containerFluid.style.marginRight = 'auto';
        }
        
        var windowWidth = window.innerWidth || document.documentElement.clientWidth;
        if (windowWidth >= 992) {
          containerFluid.style.paddingLeft = '20px';
          containerFluid.style.paddingRight = '20px';
        } else if (windowWidth >= 576) {
          containerFluid.style.paddingLeft = '15px';
          containerFluid.style.paddingRight = '15px';
        }
      } catch(e) {
        // Silent fail
      }
    }
    
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
      initSidebar();
    }
  })();
</script>

<main class="page-content">
  <div class="container-fluid">
    <!-- NOTE: The <div class="container-fluid"> and enclosing <main> are intentionally left open here.
           They are closed in footer.php to wrap each page's main content. -->
