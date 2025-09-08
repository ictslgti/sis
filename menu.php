<?php
// Ensure session and safely read session variables
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$u_n  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$u_ta = isset($_SESSION['user_table']) ? $_SESSION['user_table'] : '';
$u_t  = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$d_c  = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';

// Normalize user_type for consistent comparisons
if (isset($_SESSION['user_type'])) {
  $_SESSION['user_type'] = strtoupper(trim($_SESSION['user_type']));
  $u_t = $_SESSION['user_type'];
}

$username = null;
if($u_ta=='staff'){
  $sql = "SELECT * FROM `staff` WHERE `staff_id` = '$u_n'";
  $result = mysqli_query($con, $sql);
  if (mysqli_num_rows($result) == 1) {
  $row = mysqli_fetch_assoc($result);
  $username =  $row['staff_name'];
  }

}if($u_ta=='student'){
  $sql = "SELECT * FROM `student` WHERE `student_id` = '$u_n'";
  $result = mysqli_query($con, $sql);
  if (mysqli_num_rows($result) == 1) {
  $row = mysqli_fetch_assoc($result);
  $username =  $row['student_fullname'];
  }
}

// For student users, do not render the sidebar at all
if ($u_t === 'STU') {
  return; // stop including this file silently for students
}

// For IN3, use a top navbar (no sidebar)
if ($u_t === 'IN3') {
  $base = defined('APP_BASE') ? APP_BASE : '';
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
  <?php
  return; // prevent sidebar from rendering
}

?>
<style>
  /* Mobile spacing and alignment improvements */
  @media (max-width: 575.98px) {
    .page-content .container-fluid { padding: 0.75rem 0.75rem; }
    #sidebar .sidebar-header { padding: 0.75rem; }
    #sidebar .sidebar-menu ul li a { padding-top: 0.6rem; padding-bottom: 0.6rem; }
    #sidebar .sidebar-submenu ul li a { padding-left: 2.25rem; }
    #show-sidebar.btn { top: 0.5rem; left: 0.5rem; position: sticky; z-index: 1030; }
  }
  @media (min-width: 576px) and (max-width: 991.98px) {
    .page-content .container-fluid { padding: 1rem 1rem; }
  }
  /* Ensure content has breathing room when sidebar is closed */
  .page-wrapper .page-content { padding-top: 0.5rem; }
</style>
<nav id="sidebar" class="sidebar-wrapper">
    <div class="sidebar-content">
      <div class="sidebar-brand">
        <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?><?php echo ($_SESSION['user_type']=='STU') ? '/home/home.php' : '/dashboard/index.php'; ?>">MIS@SLGTI</a>
        <div id="close-sidebar">
          <i class="fas fa-times"></i>
        </div>
      </div>
      <div class="sidebar-header">
        <div class="user-pic">
          <img class="img-responsive img-rounded" src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/user.jpg" alt="<?php echo $u_n;?> picture">
        </div>
        <div class="user-info">
          <span class="user-name">
            <strong><?php echo $u_n;?></strong>
          </span>
          <span class="user-role"><?php echo htmlspecialchars($u_t ?: ''); ?> | <?php echo htmlspecialchars($d_c ?: ''); ?> </span>
          <span class="user-status">
            <i class="fa fa-user"></i>
            <span>
              <a href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); echo ($_SESSION['user_type']=='STU') ? '/student/Student_profile.php' : '/Profile.php'; ?>">Profile</a>
              &nbsp;|&nbsp;
              <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/logout.php">Logout</a>
            </span>
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
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AllocatedRoomWise.php">Allocated Students (Room-wise)</a>
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
        <?php } elseif ($u_t === 'DIR') { ?>
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
          
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/StudentBankDetails.php">
              <i class="fas fa-university"></i>
              <span>Student Bank Details</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/HostelFeeReports.php">
              <i class="fa fa-print"></i>
              <span>Hostel Fee Reports</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/RegistrationPaymentReport.php">
              <i class="fa fa-file-invoice-dollar"></i>
              <span>Registration Payment Report</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/RegistrationPaymentApproval.php">
              <i class="fa fa-check-circle"></i>
              <span>Registration Payment Approval</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/NormalizeRegistrationReason.php">
              <i class="fa fa-tools"></i>
              <span>Normalize Registration Reason</span>
            </a>
          </li>
        </ul>
        <?php } elseif ($u_t === 'HOD') { ?>
        <ul>
          <li class="header-menu"><span>Head of Department</span></li>
          <!-- Dashboard -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
              <i class="fa fa-tachometer-alt"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <!-- Attendance -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/DailyAttendance.php">
              <i class="fas fa-calendar-check"></i>
              <span>Daily Attendance</span>
            </a>
          </li>

          <!-- My Department: Department, Courses, Modules -->
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-university"></i>
              <span>My Department</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php">Department Info</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php">Courses</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php">Add a Course</a></li>
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
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/Reports.php">Reports</a></li>
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
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/DepartmentStudents.php">
              <i class="fas fa-user-graduate"></i>
              <span>My Dept Students</span>
            </a>
          </li>

          <!-- Timetable -->
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-calendar-alt"></i>
              <span>Timetable</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/Timetable.php">Timetable</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/AddTimetable.php">Add a Timetable</a></li>
              </ul>
            </div>
          </li>

          <!-- Examinations -->
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-award"></i>
              <span>Examinations</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/Assessment.php">Assessment Info</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessment.php">Add Assessment</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessmentType.php">Add Assessment Type</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessmentResults.php">Add Assessment Results</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AssessmentReport.php">Assessment Report</a></li>
              </ul>
            </div>
          </li>

          <!-- Attendances (hidden) -->
          <!-- Removed per requirement: no attendance in menu -->

          <!-- On-the-job Training -->
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-briefcase"></i>
              <span>On-the-job Training</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/OJT.php">OJT Info</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/addojt.php">Add a Training Place</a></li>
                <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/OJTReport.php">OJT Report</a></li>
              </ul>
            </div>
          </li>

          <!-- Hostels section removed as per requirements -->

          <!-- Payments -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/payment/Payments.php">
              <i class="fa fa-file-invoice-dollar"></i>
              <span>Payments</span>
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
          <li class="header-menu">
            <span>General</span>
          </li>
          <?php if($_SESSION['user_type'] != 'STU') { ?>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/index.php">
              <i class="fa fa-home"></i>
              <span>Dashboard</span>
              <!-- <span class="badge badge-pill badge-primary">Beta</span> -->
            </a>
          </li>
          <?php } ?>
          <?php if($_SESSION['user_type'] === 'ADM') { ?>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/gender_distribution.php">
              <i class="fas fa-chart-bar"></i>
              <span>Gender Distribution Report</span>
            </a>
          </li>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/dashboard/gender_pie.php">
              <i class="fas fa-chart-pie"></i>
              <span>Gender Pie by Department</span>
            </a>
          </li>
          <?php } ?>

          <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO') { ?>
          <!-- SAO: Hostel with submenu -->
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
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php">Add a Student</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AllowanceEligibility.php">Allowance Eligibility</a>
                </li>
              </ul>
            </div>
          </li>
          <!-- SAO: Attendance Report -->
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/MonthlyAttendanceReport.php">
              <i class="fas fa-calendar-check"></i>
              <span>Attendance Report</span>
            </a>
          </li>
          <?php } ?>

          <?php if(isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['HOD','IN1','IN2','LE1','LE2','ADM'])) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-users"></i>
              <span>Groups</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <?php if($_SESSION['user_type']==='HOD') { ?>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/Groups.php">Groups</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/AddGroup.php">Add Group</a></li>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/Reports.php">Reports</a></li>
                <?php } else { ?>
                  <li><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/group/Groups.php">My Groups</a></li>
                <?php } ?>
              </ul>
            </div>
          </li>
          <?php } ?>
          <?php if(isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['HOD','IN1','IN2','LE1','LE2','ADM'])) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-university"></i>
              <span>Departments</span>
              <!-- <span class="badge badge-pill badge-warning">New</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                <a  href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/Department.php">Departments Info</a>
                </li>
                <li>
                <?php if(($_SESSION['user_type'] =='ADM')) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/department/AddDepartment.php">Add a Department</a>
                <?php }?>
                </li>
                <li>
                <a  href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AcademicYear.php">Academic Years Info</a>
                </li>
                <li>
                <?php if(($_SESSION['user_type'] =='ADM')) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/academic/AddAcademicYear.php">Add a Academic Year</a>
                <?php }?>
                </li>
                <li>
                <a  href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/Course.php">Courses Info</a>
                </li>
                <li>
                <?php if(($_SESSION['user_type'] =='ADM') || ($_SESSION['user_type'] =='HOD')) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php">Add a Course</a>
                <?php }?>
                </li>
                <li>
                <?php if(($_SESSION['user_type'] =='ADM') || ($_SESSION['user_type'] =='HOD')) { ?><a  href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/module/Module.php">Modules Info</a><?php }?>
                </li>
                <!-- <li>
                <?php if(($_SESSION['user_type'] =='ADM') || ($_SESSION['user_type'] =='HOD')) { ?><a href="../module/ModuleEnrollement.php">Add a Module<?php }?>
                </a>
                </li> -->

              </ul>
            </div>
          </li>
          <?php } ?>
          <?php if($_SESSION['user_type'] == 'STU') { ?>
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
          <?php if($_SESSION['user_type']!='STU' && $_SESSION['user_type']!=='SAO' && !is_role('IN2')){ ?> <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-user-tie"></i>
              <span>Staffs</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffManage.php">Manage Staff</a>
                  <hr>
                </li>              
                <li>
                  <?php if($_SESSION['user_type'] == 'ADM') { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/staff/StaffPositionType.php">Staff Position Types</a>
                  <?php } ?>
                </li>
                <?php if($_SESSION['user_type'] !== 'HOD') { ?>
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
          <?php if($_SESSION['user_type'] =='ADM'){ ?> <li class="sidebar-dropdown">
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
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ManageStudents.php">Manage Students</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/BulkUpdateReligion.php">Bulk Update Student Religion</a>
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
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/ConductReport.php">Conduct Acceptance Report</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentIDPhoto.php">Student ID Photo</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentIDPhotoList.php">Student ID Photo List (By Department)</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/UploadDocumentation.php">Upload Student Documentation (PDF)</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/ImportStudentEnroll.php">Import Student Enrollment</a>
                </li>
              </ul>
            </div>
          </li>  <?php } ?>
          <?php if(can_view(['HOD'])){ ?>
          <li>
            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/DepartmentStudents.php">
              <i class="fas fa-user-graduate"></i>
              <span>My Dept Students</span>
            </a>
          </li>
          <?php } ?>
          <?php if($_SESSION['user_type']!=='SAO') { ?>
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
                <?php if(isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR','HOD','ADM'])){ ?>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/timetable/AddTimetable.php">Add a Timetable</a>
                </li>
                <?php } ?>
                <hr>
                <?php if(isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR','HOD','ADM'])){ ?>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/Notice.php">Notice Info</a>
                </li>
                <?php } ?>
                <?php if($_SESSION['user_type']=='ADM'){ ?> 
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/notices/AddNotice.php">Add a Notice</a>
                </li>
                <?php } ?>
              </ul>
            </div>
          </li>
          <?php } ?>
          <?php if($_SESSION['user_type'] != 'STU' && $_SESSION['user_type']!=='SAO') { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-award"></i>
              <span>Examinations</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AssessmentResults.php">Assessment Results</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessment.php">Add Assessment</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessmentType.php">Add Assessment Type</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessmentResults.php">Add Assessment Results</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AssessmentReport.php">Assessment Report</a>
                </li>
                <hr>
                <!-- <li>
                  <a href="TVECExamination">TVEC Examinations Info</a>
                </li>
                <li>
                  <a href="AddTVECExamination">Add TVEC Examination</a>
                </li> -->
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

          <?php if($_SESSION['user_type'] != 'STU' && $_SESSION['user_type']!=='SAO' && !is_role('IN2')) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-briefcase"></i>
              <span>On-the-job Training</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->  
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><?php if($_SESSION['user_type']=='ADM'){ ?> 
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/OJT.php">On-the-job Training Info</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/addojt.php">Add a Training Place</a>
                  <hr>
                </li> <?php } ?>             
                <li><?php if($_SESSION['user_type']=='ADM'){ ?>
                  <a href="#">Placement Change</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/ojt/OJTReport.php">OJT Report</a>
                </li><?php } ?>
              </ul>
            </div>
          </li>
          <?php } ?>

          <?php if($_SESSION['user_type'] != 'STU' && $_SESSION['user_type']!=='SAO' && !is_role('IN2')) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="far fa-building"></i>
              <span>Hostels</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <?php if($u_t==='WAR'){ ?>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Requests.php">Hostel Requests</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Payments.php">Hostel Payments</a>
                  </li>
                <?php } elseif($u_t==='ADM'){ ?>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Requests.php">Hostel Requests</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AssignHostel.php">Assign Hostel</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManageHostel.php">Manage Hostels &amp; Blocks</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManageRooms.php">Manage Rooms</a>
                    <hr>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Hostel.php">Hostels Info</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/Payments.php">Hostel Payments</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/AllocatedRoomWise.php">Allocated Students (Room-wise)</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/ManualAllocate.php">Manual Allocate</a>
                  </li>
                  <li>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/hostel/SwapRooms.php">Swap Rooms</a>
                  </li>
                <?php } ?>
              </ul>
            </div>
          </li>
          <?php } ?>


          <?php if($_SESSION['user_type']=='ADM' && !is_role('IN2')){ ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="far fa-grin"></i>
              <span>Feedbacks</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/StudentFeedbackinfo.php">Students Feedback Info</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/AddStudentFeedback.php">Create a Student Feedback</a>
                  <hr>
                </li>              
                <li>
                  <a href="#">Teacher Feedback Info</a>
                </li>
                <li>
                  <a href="#">Create a Teacher Feedback</a>
                </li>
                <li>
                  <a href="#">Industry Feedback Info</a>
                </li>
                <li>
                  <a href="#">Create a Industry Feedback</a>
                </li>
              </ul>
            </div>
          </li>
          <?php } ?>


          <?php if($_SESSION['user_type']!='STU' && $_SESSION['user_type']!=='SAO' && !is_role('IN2')){ ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-file-alt"></i>
              <span>Inventory</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/inventory/InventoryInfo.php">Inventory Info</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/inventory/AddInventory.php">Add a Inventory</a>
                  <hr>
                </li>              
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/item/AddItem.php">Add a Item</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/supplier/AddSupplier.php">Add a Supplier</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/inventory/InventoryReport.php">Inventory Report</a>
                </li>
              </ul>
            </div>
          </li>
          <?php } ?>   


          <?php if($_SESSION['user_type']=='ADM' && !is_role('IN2')){ ?>
          <li class="sidebar-dropdown"> 
            <a href="#">
              <i class="fas fa-book-open"></i>
              <span>Library</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/LibraryHome.php">Library Home</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/AddBook.php">Add a Book</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/IssueBook.php">Issue a Book</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/ViewBooks.php">All Book</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/book/IssuedBook.php">Issued Books Info</a>
                </li>
              </ul>
            </div>
          </li>
          <?php } ?>  

          <?php if(!is_role('IN2') && $_SESSION['user_type']!=='SAO'){ ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-hamburger"></i>
              <span>Canteen</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li><?php if($_SESSION['user_type']!='STU'){ ?>
                  <a href="#">Food Items</a>
                </li> <?php } ?>
                <li><?php if($_SESSION['user_type']!='STU'){ ?>
                  <a href="#">Add a Food Item</a>
                  <hr>
                </li>  <?php } ?>             
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/food/FoodOrders.php">Food Orders</a>
                  <hr>
                </li>
                <?php if($_SESSION['user_type']!='STU'){ ?>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/canteen/CanteenReport.php">Daily Report</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/order/dailyorder.php">Daily Orders</a>
                </li>
                <?php } ?>
              </ul>
            </div>
          </li>
          <?php } ?>

          <?php if($_SESSION['user_type'] != 'STU' && !is_role('IN2')) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fab fa-amazon-pay"></i>
              <span>Payments</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul> 
                <?php if($_SESSION['user_type']=='FIN') { ?>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/RegistrationPaymentApproval.php">Registration Payment Approval</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/finance/RegistrationPaymentReport.php">Registration Payment Report</a>
                </li>
                <?php } ?> 
              </ul>
            </div>
          </li>
          <?php } ?>

          <?php if($_SESSION['user_type']!=='SAO') { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-door-open"></i>
              <span>On-Peak & Off-Peak</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul> <?php if($_SESSION['user_type']!='STU'){ ?>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/OnPeak.php">On-Peak Info </a>
                </li> <?php } ?>
                <li> <?php if($_SESSION['user_type']=='STU' ){ ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/RequestOnPeak.php">Request a On-Peak</a>
                  <hr>
                </li> <?php } ?>             
                <li><?php if($_SESSION['user_type']=='WAR' ){ ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/onpeak&offpeak/OffPeak.php">Off-Peak Info</a>
                </li><?php } ?>
                <li><?php if($_SESSION['user_type']=='STU' ){ ?>
                  <a href="#">Request a Off-Peak</a>
                </li><?php } ?> 
              </ul>
            </div>
          </li>

          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-tint"></i>
              <span>Blood Donations</span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>  <?php if((($_SESSION['user_type'] =='WAR') || ($_SESSION['user_type'] =='HOD') || ($_SESSION['user_type'] =='STU'))) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/blood/BloodDonations.php">Blood Donations Info</a>
                </li> <?php } ?> 
                <li><?php if((($_SESSION['user_type'] =='WAR') || ($_SESSION['user_type'] =='HOD') || ($_SESSION['user_type'] =='STU'))) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/blood/BloodDonors.php">Blood Donors</a>
                  <hr>
                </li>  <?php } ?>             
                <li><?php if($_SESSION['user_type'] =='ADM') { ?>
                  <a href="#">Donate Blood</a>
                </li>  <?php } ?>         
              </ul>
            </div>
          </li>
          <?php if(isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR','HOD','ADM'])){ ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-tint"></i>
              <span>Attendance </span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>  <?php if((($_SESSION['user_type'] =='WAR') || ($_SESSION['user_type'] =='HOD'))) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Attendance.php">Attendance Info</a>
                </li> <?php } ?> 
                <li><?php if((($_SESSION['user_type'] =='WAR') || ($_SESSION['user_type'] =='HOD'))) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/DailyAttendance.php">Attendance </a>
                  <hr>
                </li>  <?php } ?>             
                <li><?php if($_SESSION['user_type'] =='ADM') { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/ManageAttendance.php">Attendance (Admin)</a>
                </li>  <?php } ?>         
              </ul>
            </div>
          </li>
          <?php } ?>
          <?php if(isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['WAR','HOD','ADM'])){ ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-tint"></i>
              <span>Payroll System </span>
              <!-- <span class="badge badge-pill badge-danger">3</span> -->
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>  <?php if((($_SESSION['user_type'] =='WAR') || ($_SESSION['user_type'] =='HOD'))) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Payroll.php">Payroll Info</a>
                </li> <?php } ?> 
                <li><?php if((($_SESSION['user_type'] =='WAR') || ($_SESSION['user_type'] =='HOD'))) { ?>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/Payroll.php">Payroll </a>
                  <hr>
                </li>  <?php } ?>             
                <li><?php if($_SESSION['user_type'] =='ADM') { ?> 
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/attendance/ManagePayroll.php">Payroll (Admin)</a>
                </li>  <?php } ?>         
              </ul>
            </div>
          </li>
          <?php } ?>


          <?php } ?>
          <?php if($_SESSION['user_type'] != 'STU' && $_SESSION['user_type']!=='SAO') { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-network-wired"></i>
              <span>Network Management</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <!-- Device Discovery removed -->
                </li>
                <?php if($_SESSION['user_type'] == 'ADM') { ?>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/devices/NetworkSettings.php">Network Settings</a>
                </li>
                <?php } ?>
              </ul>
            </div>
          </li>
          <?php } ?>

          <?php if($_SESSION['user_type'] == 'ADM' && !is_role('IN2')) { ?>
          <li class="sidebar-dropdown">
            <a href="#">
              <i class="fas fa-cogs"></i>
              <span>Administration</span>
            </a>
            <div class="sidebar-submenu">
              <ul>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/Administration.php">Admin Dashboard</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/ConductReport.php">Conduct Report</a>
                </li>
                <li>
                  <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/administration/DatabaseExport.php?download=1&simple=1">Database Export</a>
                </li>
              </ul>
            </div>
          </li>
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
          <?php if($_SESSION['user_type']!=='SAO') { ?>
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

  <main class="page-content">
    <div class="container-fluid">
      <!-- NOTE: The <div class="container-fluid"> and enclosing <main> are intentionally left open here.
           They are closed in footer.php to wrap each page's main content. -->

