<?php
// menu2.php - Responsive top navbar for SAO, HOD, DIR, ACC (desktop + mobile)
// Safe session access
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$u_n  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$u_ta = isset($_SESSION['user_table']) ? $_SESSION['user_table'] : '';
$u_t  = isset($_SESSION['user_type']) ? strtoupper(trim($_SESSION['user_type'])) : '';
$d_c  = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';

// Base path helper
$base = defined('APP_BASE') ? APP_BASE : '';

// Only render for SAO, HOD, DIR, ACC and instructor roles (IN1, IN2, IN3);
// otherwise do nothing to avoid unintended menus
if (!in_array($u_t, ['SAO', 'HOD', 'DIR', 'ACC', 'IN1', 'IN2', 'IN3'], true)) {
  return;
}
// Decide content container: full-width on dashboard index, centered elsewhere
$__script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
$__is_dash_index = (strpos($__script, '/dashboard/index.php') !== false);
$__content_container = $__is_dash_index ? 'container-fluid px-2 px-md-3 px-lg-4' : 'container';
?>
<style>
  /* Minor UX tweaks for better mobile usability */
  .navbar-brand {
    font-weight: 700;
  }

  /* Reduce navbar vertical space */
  .navbar {
    padding-top: .25rem;
    padding-bottom: .25rem;
  }

  @media (min-width: 992px) {
    .navbar {
      padding-left: .75rem;
      padding-right: .75rem;
    }
  }

  @media (max-width: 575.98px) {
    .navbar-nav .nav-link {
      padding-top: .6rem;
      padding-bottom: .6rem;
    }

    .dropdown-menu {
      max-height: 60vh;
      overflow-y: auto;
    }
  }

  /* Ensure content isn't pushed too far down under sticky navbar */
  .page-wrapper .page-content {
    padding-top: .0rem;
  }

  /* Make navbar span edge-to-edge with items hugging the right on desktop */
  @media (min-width: 992px) {
    .navbar.navbar-light {
      padding-right: 0;
    }
  }

  /* Centered content with comfortable side spaces */
  .page-content>.container {
    padding-left: 0rem;
    padding-right: 0rem;
    max-width: 1320px;
  }

  @media (min-width: 768px) {
    .page-content>.container {
      padding-left: 0rem;
      padding-right: 0rem;
    }
  }

  @media (min-width: 992px) {
    .page-content>.container {
      padding-left: 0rem;
      padding-right: 0rem;
    }
  }

  @media (min-width: 1600px) {
    .page-content>.container {
      max-width: 1440px;
    }
  }
</style>
<?php if ($__is_dash_index) { ?>
  <style>
    /* Dashboard index: full width with modest side gutters */
    @media (min-width: 992px) {
      .page-wrapper .page-content {
        padding-left: 0 !important;
        padding-right: 0 !important;
      }

      .page-content>.container,
      .page-content>.container-fluid {
        max-width: 100% !important;
      }

      /* Add gentle side padding for readability */
      .page-content .container-fluid {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
      }
    }

    @media (min-width: 1200px) {
      .page-content .container-fluid {
        padding-left: 1.25rem !important;
        padding-right: 1.25rem !important;
      }
    }

    @media (min-width: 1400px) {
      .page-content .container-fluid {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
      }
    }
  </style>
<?php } ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top px-2 px-md-3 px-lg-4">
  <a class="navbar-brand" href="<?php echo $base; ?><?php echo (in_array($u_t, ['HOD','IN1','IN2','IN3'], true)) ? '/hod/Dashboard.php' : '/dashboard/index.php'; ?>">MIS@SLGTI</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#saoNavbar" aria-controls="saoNavbar" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse justify-content-end" id="saoNavbar">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?><?php echo (in_array($u_t, ['HOD','IN1','IN2','IN3'], true)) ? '/hod/Dashboard.php' : '/dashboard/index.php'; ?>">
          <i class="fa fa-home"></i> Dashboard
        </a>
      </li>

      <!-- Hostel dropdown: SAO only -->
      <?php if ($u_t === 'SAO'): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="saoHostel" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="far fa-building"></i> Hostel
          </a>
          <div class="dropdown-menu" aria-labelledby="saoHostel">
            <a class="dropdown-item" href="<?php echo $base; ?>/hostel/Hostel.php">Hostels Info</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/hostel/BulkRoomAssign.php">Bulk Assign</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/hostel/ManualAllocate.php">Manual Allocate</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/hostel/Payments.php">Hostel Payments</a>
          </div>
        </li>
      <?php endif; ?>

      <!-- Director/Accounts quick links -->
      <?php if (in_array($u_t, ['DIR', 'ACC'], true)): ?>

        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base; ?>/hostel/AllocatedRoomWise.php">
            <i class="far fa-building"></i> Hostels Info
          </a>
        </li>

      <?php endif; ?>

      <!-- Students dropdown: SAO full menu, HOD limited to Manage Students -->
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="saoStudents" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-user-graduate"></i> Students
        </a>
        <div class="dropdown-menu" aria-labelledby="saoStudents">
          <a class="dropdown-item" href="<?php echo $base; ?>/student/ManageStudents.php">Manage Students</a>
          <?php if ($u_t === 'SAO'): ?>
            <a class="dropdown-item" href="<?php echo $base; ?>/student/ImportStudentEnroll.php">Add a Student</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/student/AllowanceEligibility.php">Allowance Eligibility</a>
          <?php endif; ?>
        </div>
      </li>

      <!-- Attendance: HOD gets Daily + Monthly (scoped to own department) -->
      <?php if (in_array($u_t, ['HOD','IN1','IN2','IN3'], true)): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="hodAttendance" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-calendar-check"></i> Attendance
          </a>
          <div class="dropdown-menu" aria-labelledby="hodAttendance">
            <a class="dropdown-item" href="<?php echo $base; ?>/attendance/DailyAttendance.php">Daily Attendance</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/attendance/MonthlyAttendanceReport.php">Monthly Report</a>
          </div>
        </li>

        <!-- Groups: HOD can manage department groups -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="hodGroups" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-users"></i> Groups
          </a>
          <div class="dropdown-menu" aria-labelledby="hodGroups">
            <a class="dropdown-item" href="<?php echo $base; ?>/group/Groups.php?department_id=<?php echo urlencode($d_c); ?>">Manage Groups</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/group/AddGroup.php?department_id=<?php echo urlencode($d_c); ?>">Add Group</a>
            <a class="dropdown-item" href="<?php echo $base; ?>/group/Reports.php?department_id=<?php echo urlencode($d_c); ?>">Reports</a>
          </div>
        </li>

       

        

      
      <?php endif; ?>

      <!-- Attendance report: SAO only -->
      <?php if ($u_t === 'SAO'): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo $base; ?>/attendance/MonthlyAttendanceReport.php">
            <i class="fas fa-calendar-check"></i> Attendance Report
          </a>
        </li>
      <?php endif; ?>
    </ul>

    <ul class="navbar-nav ml-3">
      <!-- Optional: quick search placeholder (hidden by default) -->
      <!-- <form class="form-inline my-2 my-lg-0 mr-3 d-none">
        <input class="form-control mr-sm-2" type="search" placeholder="Search" aria-label="Search">
      </form> -->

      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="saoUser" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fa fa-user"></i> <?php echo htmlspecialchars($u_n ?: ''); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="saoUser">
          <h6 class="dropdown-header">
            <?php echo htmlspecialchars($u_t); ?><?php echo $d_c ? ' | ' . htmlspecialchars($d_c) : ''; ?>
          </h6>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo $base; ?>/Profile.php">Profile</a>
          <a class="dropdown-item" href="<?php echo $base; ?>/logout.php">Logout</a>
        </div>
      </li>
    </ul>
  </div>
</nav>

<!-- Open the main content wrappers expected by footer.php -->
<main class="page-content">
  <div class="<?php echo $__content_container; ?>">