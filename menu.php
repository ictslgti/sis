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
  /* Professional Menu Styling - Only Background Change on Hover */
  
  /* Base menu item styling - no borders, no transforms */
  .sidebar-wrapper .sidebar-menu ul li,
  .sidebar-wrapper .sidebar-menu ul li a,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown > a,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown .sidebar-submenu li,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown .sidebar-submenu li a {
    border-left: none !important;
    border: none !important;
    transform: none !important;
    transition: background-color 0.2s ease !important;
  }
  
  /* Prevent text movement - fixed padding */
  .sidebar-wrapper .sidebar-menu ul li a {
    padding-left: 1.5rem !important;
    padding-right: 1.25rem !important;
  }
  
  .sidebar-wrapper .sidebar-menu ul li:hover > a,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown:hover > a,
  .sidebar-wrapper .sidebar-menu ul li.active > a,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown.active > a {
    padding-left: 1.5rem !important;
    padding-right: 1.25rem !important;
  }
  
  /* Only background color change on hover - no other changes */
  .sidebar-wrapper .sidebar-menu ul li:hover > a {
    background-color: rgba(99, 102, 241, 0.1) !important;
    color: inherit !important;
    border-left: none !important;
    transform: none !important;
    padding-left: 1.5rem !important;
    text-shadow: none !important;
    box-shadow: none !important;
  }
  
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown:hover > a {
    background-color: rgba(99, 102, 241, 0.1) !important;
    color: inherit !important;
    border-left: none !important;
    transform: none !important;
    padding-left: 1.5rem !important;
  }
  
  /* Submenu hover - only background */
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown .sidebar-submenu li:hover > a {
    background-color: rgba(99, 102, 241, 0.08) !important;
    color: inherit !important;
    padding-left: inherit !important;
    transform: none !important;
    border-left: none !important;
  }
  
  /* Active state - subtle background, no border */
  .sidebar-wrapper .sidebar-menu ul li.active > a,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown.active > a {
    background-color: rgba(99, 102, 241, 0.15) !important;
    border-left: none !important;
    color: inherit !important;
    font-weight: 500 !important;
  }
  
  /* Icons - no changes on hover, only background follows parent */
  .sidebar-wrapper .sidebar-menu ul li a i,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown > a i {
    transition: none !important;
    transform: none !important;
    color: inherit !important;
    background: transparent !important;
    box-shadow: none !important;
    text-shadow: none !important;
  }
  
  .sidebar-wrapper .sidebar-menu ul li:hover a i,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown:hover a i {
    color: inherit !important;
    transform: none !important;
    background: transparent !important;
    box-shadow: none !important;
    text-shadow: none !important;
  }
  
  /* Submenu indicators - no changes */
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown .sidebar-submenu li a:hover:before {
    background: inherit !important;
    width: inherit !important;
    height: inherit !important;
    box-shadow: none !important;
    transform: none !important;
  }
  
  /* Remove any border colors from active states */
  .sidebar-wrapper .sidebar-menu ul li.active,
  .sidebar-wrapper .sidebar-menu .sidebar-dropdown.active {
    border-left: none !important;
  }
  
  /* Mobile spacing and alignment improvements */
  @media (max-width: 575.98px) {
    .page-content .container-fluid {
      padding: 0.75rem 0.75rem;
    }

    #sidebar .sidebar-header {
      padding: 0.75rem;
    }

    #sidebar .sidebar-menu ul li a {
      padding-top: 0.6rem;
      padding-bottom: 0.6rem;
    }

    #sidebar .sidebar-submenu ul li a {
      padding-left: 2.25rem;
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
      padding: 1rem 1rem;
    }
  }

  /* Ensure content has breathing room when sidebar is closed */
  .page-wrapper .page-content {
    padding-top: 0.5rem;
  }

  /* Admin Dashboard and general page content alignment */
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
        <i class="fas fa-university" style="font-size: 1.5rem; color: #2563eb; text-shadow: 0 0 10px rgba(37, 99, 235, 0.5);"></i>
        <span style="font-weight: 700; letter-spacing: 1px;">MIS@SLGTI</span>
      </a>
      <div id="close-sidebar" style="cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: all 0.3s ease;">
        <i class="fas fa-times"></i>
      </div>
    </div>
    <div class="sidebar-header" style="background: transparent !important;">
      <div class="user-pic" style="border: 2px solid rgba(37, 99, 235, 0.3); box-shadow: 0 0 15px rgba(37, 99, 235, 0.2);">
        <img class="img-responsive img-rounded" src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/user.jpg" alt="<?php echo $u_n; ?> picture">
      </div>
      <div class="user-info">
        <span class="user-name" style="display: block; font-size: 0.95rem; font-weight: 700; color: #ffffff; margin-bottom: 0.25rem;">
          <?php echo htmlspecialchars($u_n); ?>
        </span>
        <span class="user-role" style="display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; letter-spacing: 0.5px;">
         <?php echo htmlspecialchars($u_t ?: ''); ?> | <?php echo htmlspecialchars($d_c ?: ''); ?>
        </span>
        <span class="user-status" style="display: flex; gap: 0.75rem; font-size: 0.8rem;">
          <a href="<?php echo (defined('APP_BASE') ? APP_BASE : '');
                    echo ($_SESSION['user_type'] == 'STU') ? '/student/Student_profile.php' : '/Profile.php'; ?>" 
             style="color: #cbd5e1; text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; background: transparent; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.4rem;">
            <i class="fas fa-user-circle"></i> Profile
          </a>
          <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/logout.php" 
             style="color: #fca5a5; text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 6px; background: transparent; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.4rem;">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </span>
      </div>
    </div>
    <!-- sidebar-header  -->
    <!-- <div class="sidebar-search">
        <div>
          <div class="input-group">
            <input type="text" class="form-control search-menu" placeholder="Search...">
            <div class="input-group-append">
              <span class="input-group-text">
                <i class="fa fa-search" aria-hidden="true"></i>
              </span>
            </div>
          </div>
        </div>
      </div> -->
    <!-- sidebar-search  -->
    <div class="sidebar-menu">
      <?php if ($u_t === 'WAR') { ?>
        <ul>
          <li class="header-menu"><span>General</span></li>
          <!-- Dashboard - First Menu Item -->
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
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Requests.php">Hostel Requests</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Payments.php">Hostel Payments</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AllocatedRoomWise.php">Hostel Info</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManualAllocate.php">Manual Allocate</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/SwapRooms.php">Swap Rooms</a>
                </li>
              </ul>
            </div>
          </li>
        </ul>
      <?php } elseif ($u_t === 'MA2') { ?>
        <ul>
          <li class="header-menu"><span>General</span></li>
          <!-- Dashboard - First Menu Item -->
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
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">Students Info</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AddStudent.php">Add a Student</a>
                </li>
                <hr>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentReEnroll.php">Student Re Enroll</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ChangeEnrollment.php">Change Course/Reg No</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentEnrollmentReport.php">Student Enrollment Report</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentIDPhoto.php">Student ID Photo</a>
                </li>
                <li>
                  <a href="#" onclick="(function(){var sid=prompt('Enter Student ID to download Application Form:'); if(sid){ window.open('<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/library/pdf/student_application.php?Sid='+encodeURIComponent(sid), '_blank'); }})(); return false;">Download Student Application</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/UploadDocumentation.php">Upload Student Documentation (PDF)</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php">Import Student Enrollment</a>
                </li>
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
          <!-- Dashboard - First Menu Item -->
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
          <!-- Dashboard - First Menu Item -->
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
              <span> Attendance Report</span>
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
          <!-- Dashboard -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hod/Dashboard.php">
              <i class="fa fa-tachometer-alt"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <!-- Attendance -->
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">
                    <i class="fas fa-calendar-alt mr-1"></i> Monthly Report
                  </a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/BulkMonthlyMark.php">
                    <i class="fas fa-tasks mr-1"></i> Bulk Monthly Mark
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <!-- My Department: Department, Courses, Modules -->
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

          <!-- Groups (own department) -->
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

          <!-- Staff (view all details) -->
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

          <!-- My Department Students -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">
              <i class="fas fa-user-graduate"></i>
              <span>My Dept Students</span>
            </a>
          </li>

          <!-- Season Approval -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php">
              <i class="fas fa-bus"></i>
              <span>Season Approval</span>
            </a>
          </li>

          <!-- OnPeak Calendar -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak/OnPeak.php">
              <i class="far fa-calendar-check"></i>
              <span>OnPeak Calendar</span>
            </a>
          </li>

          <!-- Change Password -->
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
            <!-- Admin Header -->
            <li class="header-menu">
              <span><i class="fas fa-user-shield mr-2"></i>Administrator</span>
            </li>
            
            <!-- Admin Dashboard -->
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
              </a>
            </li>
            
            
            <!-- Admin Index/Home -->
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/index.php">
                <i class="fas fa-home"></i>
                <span>Index</span>
              </a>
            </li>
            
            <!-- Admin: Departments & Academic -->
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-university"></i>
                <span>Departments & Academic</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php"><i class="fas fa-list mr-1"></i>Departments Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/AddDepartment.php"><i class="fas fa-plus-circle mr-1"></i>Add Department</a></li>
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AcademicYear.php"><i class="fas fa-calendar-alt mr-1"></i>Academic Years</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AddAcademicYear.php"><i class="fas fa-plus-circle mr-1"></i>Add Academic Year</a></li>
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php"><i class="fas fa-book mr-1"></i>Courses</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php"><i class="fas fa-plus-circle mr-1"></i>Add Course</a></li>
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php"><i class="fas fa-cubes mr-1"></i>Modules</a></li>
                </ul>
              </div>
            </li>

            <!-- Admin: Students Management -->
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
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentReEnroll.php"><i class="fas fa-redo mr-1"></i>Student Re Enroll</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ChangeEnrollment.php"><i class="fas fa-exchange-alt mr-1"></i>Change Course</a></li>
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/UploadDocumentation.php"><i class="fas fa-file-pdf mr-1"></i>Upload Student Documentation (PDF)</a></li>
                  <li><a href="#" onclick="(function(){var sid=prompt('Enter Student ID for ID Card:'); if(sid){ window.open('<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentIDCard.php?id='+encodeURIComponent(sid), '_blank'); }})(); return false;"><i class="fas fa-id-card mr-1"></i>Student ID Card</a></li>
                </ul>
              </div>
            </li>

            <!-- Admin: Staff Management -->
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

            <!-- Admin: Examinations -->
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

            <!-- Admin: Attendance -->
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

            <!-- Admin: Season Requests -->
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
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport1.php"><i class="fas fa-file-alt mr-1"></i>Report 1 - Student Details</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport2.php"><i class="fas fa-file-invoice mr-1"></i>Report 2 - Payment Details</a></li>
                </ul>
              </div>
            </li>

            <!-- Admin: Hostels -->
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

            <!-- Admin: On-the-job Training -->
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

            <!-- Admin: Timetable & Notices -->
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-calendar-alt"></i>
                <span>Timetable & Notices</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/Timetable.php"><i class="fas fa-calendar mr-1"></i>Timetable</a></li>
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/Notice.php"><i class="fas fa-bullhorn mr-1"></i>Notice Info</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/AddNotice.php"><i class="fas fa-plus-circle mr-1"></i>Add Notice</a></li>
                </ul>
              </div>
            </li>

            <!-- Admin: Library -->
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

            <!-- Admin: Feedbacks -->
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

            <!-- Admin: Payroll System -->
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

            <!-- Admin: Network Management -->
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

            <!-- Admin: Administration -->
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
                  <li><hr style="margin: 0.5rem 0;"></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/DatabaseExport.php?download=1&simple=1">Database Export</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/StudentImageBackup.php">Student Image Backup</a></li>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO') { ?>
            <!-- SAO: Hostel with submenu -->
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
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Payments.php">Hostel Payments</a></li>
                </ul>
              </div>
            </li>
            <!-- SAO: Students submenu -->
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">Manage Students</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ExportStudents.php">Export Students (CSV)</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php">Add a Student</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AllowanceEligibility.php">Allowance Eligibility</a>
                  </li>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['SAO'])) { ?>
            <!-- SAO/ADM: Attendance Report -->
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
                <!-- <span class="badge badge-pill badge-warning">New</span> -->
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php">Departments Info</a>
                  </li>
                  <li>
                    <?php if (($_SESSION['user_type'] == 'ADM')) { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/AddDepartment.php">Add a Department</a>
                    <?php } ?>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AcademicYear.php">Academic Years</a>
                  </li>
                  <li>
                    <?php if (($_SESSION['user_type'] == 'ADM')) { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AddAcademicYear.php">Add Academic Year</a>
                    <?php } ?>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php">Courses</a>
                  </li>
                  <li>
                    <?php if (($_SESSION['user_type'] == 'ADM') || ($_SESSION['user_type'] == 'HOD')) { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php">Add Course</a>
                    <?php } ?>
                  </li>
                  <li>
                    <?php if (($_SESSION['user_type'] == 'ADM') || ($_SESSION['user_type'] == 'HOD')) { ?><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php">Modules</a><?php } ?>
                  </li>
                  <!-- <li>
                <?php if (($_SESSION['user_type'] == 'ADM') || ($_SESSION['user_type'] == 'HOD')) { ?><a href="../module/ModuleEnrollement.php">Add a Module<?php } ?>
                </a>
                </li> -->

                </ul>
              </div>
            </li>
          <?php } ?>
          <?php if ($_SESSION['user_type'] == 'STU') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-file-pdf"></i>
                <span>Downloads</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <li>
                    <a target="_blank" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/library/pdf/student_application.php?Sid=<?php echo urlencode($u_n); ?>">Application Form</a>
                  </li>
                  <li>
                    <a target="_blank" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/library/pdf/hostel_request.php?Sid=<?php echo urlencode($u_n); ?>">Hostel Request</a>
                  </li>
                  <li>
                    <a target="_blank" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/library/pdf/student_id_card.php?Sid=<?php echo urlencode($u_n); ?>">Student ID Card Request (A4)</a>
                  </li>
                </ul>
              </div>
            </li>
            <!-- Student Hostel unified link -->
            <li>
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/request_hostel.php">
                <i class="far fa-building"></i>
                <span>Hostel</span>
              </a>
            </li>
          <?php } ?>
          <?php if ($_SESSION['user_type'] != 'STU' && $_SESSION['user_type'] !== 'SAO' && !is_role('IN2')) { ?> 
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-user-tie"></i>
                <span>Staffs</span>
                <!-- <span class="badge badge-pill badge-danger">3</span> -->
              </a>
              <div class="sidebar-submenu">
                <ul>

                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffManage.php">Manage Staff</a>

                  </li>
                  <!-- Staff Position Types moved under Admin menu for ADM -->

                  <?php if ($_SESSION['user_type'] == 'HOD') { ?>
                    <li>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffModuleEnrollment.php">Module Enrollment</a>
                    </li>
                  <?php } ?>
                  <!-- <li>
                  <a href="../staff/StaffExit">Staff Exit</a>
                </li> -->
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
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/Timetable.php">Timetable</a>
                  </li>
                  <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD'])) { ?>
                    <li>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/AddTimetable.php">Add a Timetable</a>
                    </li>
                  <?php } ?>
                 
                  <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD'])) { ?>
                    <li>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/Notice.php">Notice Info</a>
                    </li>
                  <?php } ?>
                  <!-- ADM notice links are available under Admin menu -->
                </ul>
              </div>
            </li>
          <?php } ?>
          

          <!-- Attendance menu hidden per requirement -->
          <?php /* if($_SESSION['user_type'] != 'STU' && !is_role('IN2')) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-chalkboard-teacher"></i>
              <span>Attendances</span>
            </a>
          </li>
          <?php } */ ?>


          <?php if ($_SESSION['user_type'] != 'STU' && $_SESSION['user_type'] !== 'SAO' && !is_role('IN2') && $_SESSION['user_type'] !== 'ADM') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="far fa-building"></i>
                <span>Hostels</span>
              </a>
              <div class="sidebar-submenu">
                <ul>
                  <?php if ($u_t === 'WAR') { ?>
                    <li>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Requests.php">Hostel Requests</a>
                    </li>
                    <li>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a>
                    </li>
                  <?php } ?>
                </ul>
              </div>
            </li>
          <?php } ?>

          <!-- Season Requests (Non-Admin) -->
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
        
          <?php if ($_SESSION['user_type'] != 'STU' && !is_role('IN2') && $_SESSION['user_type'] != 'FIN') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fab fa-amazon-pay"></i>
                <span>Payments</span>
                <!-- <span class="badge badge-pill badge-danger">3</span> -->
              </a>
              <div class="sidebar-submenu">
                <ul>
                </ul>
              </div>
            </li>
          <?php } ?>

          <?php if ($_SESSION['user_type'] !== 'SAO') { ?>
            <li class="sidebar-dropdown">
              <a href="#">
                <i class="fas fa-door-open"></i>
                <span>On-Peak & Off-Peak</span>
                <!-- <span class="badge badge-pill badge-danger">3</span> -->
              </a>
              <div class="sidebar-submenu">
                <ul> <?php if ($_SESSION['user_type'] != 'STU') { ?>
                    <li>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/OnPeak.php">On-Peak Info </a>
                    </li> <?php } ?>
                  <li> <?php if ($_SESSION['user_type'] == 'STU') { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/RequestOnPeak.php">Request a On-Peak</a>
                      <hr>
                  </li> <?php } ?>
                <li><?php if ($_SESSION['user_type'] == 'WAR') { ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/OffPeak.php">Off-Peak Info</a>
                </li><?php } ?>
              <li><?php if ($_SESSION['user_type'] == 'STU') { ?>
                  <a href="#">Request a Off-Peak</a>
              </li><?php } ?>
                </ul>
              </div>
            </li>

            
            <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD', 'ADM'])) { ?>
              <li class="sidebar-dropdown">
                <a href="#">
                  <i class="fas fa-calendar-check"></i>
                  <span>Attendance</span>
                  <!-- <span class="badge badge-pill badge-danger">3</span> -->
                </a>
                <div class="sidebar-submenu">
                  <ul>
                    <li> <?php if ($_SESSION['user_type'] == 'WAR') { ?>
                        <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Attendance.php">Attendance Info</a>
                    </li> <?php } ?>
                  <li><?php if ($_SESSION['user_type'] == 'WAR') { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/DailyAttendance.php">Daily Attendance</a>
                  </li> <?php } ?>
                  <li><?php if ($_SESSION['user_type'] == 'HOD') { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">Monthly Report</a>
                  </li> <?php } ?>
                  <li><?php if ($_SESSION['user_type'] == 'HOD') { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/BulkMonthlyMark.php">Bulk Monthly Mark</a>
                  </li> <?php } ?>
                <li><?php if ($_SESSION['user_type'] == 'ADM') { ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">Monthly Attendance Report</a>
                </li> <?php } ?>
                  </ul>
                </div>
              </li>
            <?php } ?>

            
            <?php if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR', 'HOD', 'ADM'])) { ?>
              <li class="sidebar-dropdown">
                <a href="#">
                  <i class="fas fa-tint"></i>
                  <span>Payroll System </span>
                  <!-- <span class="badge badge-pill badge-danger">3</span> -->
                </a>
                <div class="sidebar-submenu">
                  <ul>
                    <li> <?php if ((($_SESSION['user_type'] == 'WAR') || ($_SESSION['user_type'] == 'HOD'))) { ?>
                        <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Payroll.php">Payroll Info</a>
                    </li> <?php } ?>
                  <li><?php if ((($_SESSION['user_type'] == 'WAR') || ($_SESSION['user_type'] == 'HOD'))) { ?>
                      <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Payroll.php">Payroll </a>
                      <hr>
                  </li> <?php } ?>
                <li><?php if ($_SESSION['user_type'] == 'ADM') { ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/ManagePayroll.php">Payroll (Admin)</a>
                </li> <?php } ?>
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
              <!-- <span class="badge badge-pill badge-primary">Beta</span> -->
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
  // Professional Sidebar Enhancements - Accordion Behavior (Bootstrap 4)
  (function() {
    'use strict';
    
    // Wait for DOM to be ready
    function initSidebar() {
      try {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        
        // ACCORDION: Only expand the dropdown that matches current page
        // This will be handled by restoreActiveSubmenus in footer.php
        
        // Highlight active menu item based on current URL
        highlightActiveMenuItem();
        
        // Smooth scroll for sidebar
        var sidebarContent = sidebar.querySelector('.sidebar-content');
        if (sidebarContent) {
          sidebarContent.style.scrollBehavior = 'smooth';
        }
        
        // Ensure proper container alignment for admin pages
        ensureAdminAlignment();
        
        // Setup resize handler
        setupResizeHandler();
      } catch(e) {
        console.error('Error initializing sidebar:', e);
      }
    }
    
    // Highlight active menu item based on current URL
    function highlightActiveMenuItem() {
      try {
        var currentPath = window.location.pathname;
        var currentFile = currentPath.split('/').pop() || '';
        var basePath = (document.querySelector('base') || {}).href || '';
        
        // Find and highlight active menu items
        var menuLinks = document.querySelectorAll('#sidebar .sidebar-menu a[href]');
        menuLinks.forEach(function(link) {
          try {
            var href = link.getAttribute('href') || '';
            if (!href || href === '#' || href === 'javascript:void(0)') return;
            
            // Normalize href (remove base path if present)
            var normalizedHref = href.replace(basePath, '').replace(/^\//, '');
            var normalizedPath = currentPath.replace(/^\//, '');
            
            // Check if current path matches
            if (normalizedPath.includes(normalizedHref) || 
                normalizedHref.includes(currentFile) ||
                currentFile === normalizedHref.split('/').pop()) {
              
              var listItem = link.closest('li');
              if (listItem) {
                listItem.classList.add('active');
                link.style.background = 'transparent';
                link.style.color = '';
                link.style.fontWeight = '';
                link.style.borderLeft = 'none';
                
                // Ensure parent dropdown is expanded (only if not on dashboard)
                // Note: currentPath is already defined above in the function
                var isDashboard = currentPath.includes('dashboard/index') || 
                                  currentPath.includes('dashboard/index.php') ||
                                  currentPath === '/dashboard' ||
                                  currentPath.endsWith('/dashboard/');
                
                if (!isDashboard) {
                  var dropdown = link.closest('.sidebar-dropdown');
                  if (dropdown) {
                    dropdown.classList.add('active');
                    var submenu = dropdown.querySelector('.sidebar-submenu');
                    if (submenu) {
                      submenu.style.display = 'block';
                    }
                  }
                }
              }
            }
          } catch(e) {
            console.warn('Error highlighting menu item:', e);
          }
        });
      } catch(e) {
        console.error('Error in highlightActiveMenuItem:', e);
      }
    }
    
    // Ensure proper container alignment for admin pages
    function ensureAdminAlignment() {
      try {
        var containerFluid = document.querySelector('.page-content .container-fluid');
        if (!containerFluid) return;
        
        var computedStyle = window.getComputedStyle(containerFluid);
        var maxWidth = computedStyle.maxWidth;
        
        // Ensure proper centering if max-width is set
        if (maxWidth && maxWidth !== 'none' && maxWidth !== '100%') {
          containerFluid.style.marginLeft = 'auto';
          containerFluid.style.marginRight = 'auto';
        }
        
        // Ensure proper padding based on screen size
        var windowWidth = window.innerWidth || document.documentElement.clientWidth;
        if (windowWidth >= 992) {
          containerFluid.style.paddingLeft = '20px';
          containerFluid.style.paddingRight = '20px';
        } else if (windowWidth >= 576) {
          containerFluid.style.paddingLeft = '15px';
          containerFluid.style.paddingRight = '15px';
        }
      } catch(e) {
        console.warn('Error ensuring admin alignment:', e);
      }
    }
    
    // Setup resize handler with debouncing
    var resizeTimer = null;
    function setupResizeHandler() {
      window.removeEventListener('resize', handleResize);
      window.addEventListener('resize', handleResize, { passive: true });
    }
    
    function handleResize() {
      if (resizeTimer) {
        clearTimeout(resizeTimer);
      }
      resizeTimer = setTimeout(function() {
        ensureAdminAlignment();
      }, 150);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
      // DOM already loaded
      initSidebar();
    }
  })();
</script>

<main class="page-content">
  <div class="container-fluid">
    <!-- NOTE: The <div class="container-fluid"> and enclosing <main> are intentionally left open here.
           They are closed in footer.php to wrap each page's main content. -->