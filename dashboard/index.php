<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "Home | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!--END DON'T CHANGE THE ORDER-->



<?php
// Legacy student survey notification block removed to prevent syntax and path errors.
?>

<!--BLOCK#2 START YOUR CODE HERE -->
<?php
// Determine if current user is a student
$isStudent = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU');
// Academic year filter (default: latest Active) - used by both student and admin dashboards
$selectedYear = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
if ($selectedYear === '') {
  if ($rs = mysqli_query($con, "SELECT academic_year FROM academic WHERE academic_year_status='Active' ORDER BY academic_year DESC LIMIT 1")) {
    if ($r = mysqli_fetch_row($rs)) { $selectedYear = $r[0] ?? ''; }
    mysqli_free_result($rs);
  }
}
?>

<?php if ($isStudent): ?>
<?php
    // Load the logged-in student's core profile data for personalized dashboard
    $username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
    $p_title = $p_fname = $p_ininame = $p_nic = $p_depth = $p_course = $p_level = $p_batch = $p_exit = null;
    if ($username) {
        $sql = "SELECT u.user_name, e.course_id, s.student_title, s.student_fullname, s.student_ininame, s.student_nic,
                       d.department_name, c.course_name, c.course_nvq_level, e.academic_year, e.student_enroll_exit_date
                  FROM student s
                  JOIN student_enroll e ON s.student_id = e.student_id
                  JOIN user u ON u.user_name = s.student_id
                  JOIN course c ON c.course_id = e.course_id
                  JOIN department d ON d.department_id = c.department_id
                 WHERE e.student_enroll_status = 'Following' AND u.user_name = '" . mysqli_real_escape_string($con, $username) . "'";
        $result = mysqli_query($con, $sql);
        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            $p_title  = $row['student_title'];
            $p_fname  = $row['student_fullname'];
            $p_ininame= $row['student_ininame'];
            $p_nic    = $row['student_nic'];
            $p_depth  = $row['department_name'];
            $p_course = $row['course_name'];
            $p_level  = $row['course_nvq_level'];
            $p_batch  = $row['academic_year'];
            $p_exit   = $row['student_enroll_exit_date'];
        }
    }
?>


<!-- Academic Year filter -->
<div class="row mt-3">
  <div class="col-12">
    <form method="get" action="" class="form-inline mb-2">
      <label class="mr-2 small text-muted">Academic Year</label>
      <select name="academic_year" class="form-control form-control-sm mr-2" style="min-width:200px;">
        <option value="">-- Latest Active --</option>
        <?php
        $years = [];
        if ($rs = mysqli_query($con, "SELECT academic_year FROM academic ORDER BY academic_year DESC")) {
          while ($r = mysqli_fetch_assoc($rs)) { $years[] = $r['academic_year']; }
          mysqli_free_result($rs);
        }
        foreach ($years as $y) {
          $sel = ($selectedYear === $y) ? 'selected' : '';
          echo '<option value="'.htmlspecialchars($y).'" '.$sel.'>'.htmlspecialchars($y).'</option>';
        }
        ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Apply</button>
      <?php if (!empty($_GET['academic_year'])): ?>
        <a href="<?php echo (defined('APP_BASE')? APP_BASE : ''); ?>/dashboard/index.php" class="btn btn-link btn-sm ml-2">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="row mt-0">
  <div class="col-md-4 col-sm-12">
    <div class="card mb-3 text-center">
      <div class="card-body">
        <img src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/get_student_image.php?Sid=<?php echo urlencode($username); ?>&t=<?php echo time(); ?>" alt="user image" class="img-thumbnail mb-3" style="width:160px;height:160px;object-fit:cover;">
        <h5 class="card-title mb-1"><?php echo htmlspecialchars(($p_title ? $p_title.'. ' : '').$p_fname); ?></h5>
        <div class="text-muted">ID: <?php echo htmlspecialchars($username); ?></div>
        <?php if ($p_nic): ?><div class="text-muted">NIC: <?php echo htmlspecialchars($p_nic); ?></div><?php endif; ?>
        <div class="mt-3">
          <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/Student_profile.php" class="btn btn-primary btn-sm">View Full Profile</a>
          <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/student/Student_profile.php#nav-modules" class="btn btn-outline-secondary btn-sm">My Modules</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8 col-sm-12">
    <div class="card mb-3">
      <div class="card-body">
        <h6 class="card-header font-weight-lighter mb-3 bg-white px-0">My Academic Summary</h6>
        <div class="row">
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">Department</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_depth ?: '—'); ?></div>
          </div>
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">Course</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_course ?: '—'); ?></div>
          </div>
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">NVQ Level</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_level !== null ? ('Level - '.$p_level) : '—'); ?></div>
          </div>
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">Batch</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_batch ?: '—'); ?><?php echo $p_exit ? ' ('.$p_exit.')' : ''; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="alert alert-info">
      This dashboard is personalized for students. Use the sidebar to access Attendance, Assessments, Notices, and more.
    </div>
  </div>
</div>

<?php else: ?>

<?php
// Centralized counts for top stats
$deptCount = 0; $courseCount = 0; $acadCount = 0; $studentCount = 0;
// Departments (exclude admin/administration)
if ($rs = mysqli_query($con, "SELECT COUNT(department_id) AS cnt FROM department WHERE LOWER(TRIM(department_name)) NOT IN ('admin','administration')")) {
  if ($r = mysqli_fetch_assoc($rs)) { $deptCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}
// Courses
if ($rs = mysqli_query($con, "SELECT COUNT(course_id) AS cnt FROM course")) {
  if ($r = mysqli_fetch_assoc($rs)) { $courseCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}
// Academic years
if ($rs = mysqli_query($con, "SELECT COUNT(academic_year) AS cnt FROM academic")) {
  if ($r = mysqli_fetch_assoc($rs)) { $acadCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}
// Students (current Following) in the selected academic year
// Definition: enrollment in selected year with status = 'Following', and student status not 'Inactive'
$yearCond = $selectedYear !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $selectedYear) . "'") : '';
$sqlStu = "SELECT COUNT(DISTINCT s.student_id) AS cnt
           FROM student s
           JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status = 'Following'" . $yearCond . "
           WHERE COALESCE(s.student_status,'') <> 'Inactive'";
if ($rs = mysqli_query($con, $sqlStu)) {
  if ($r = mysqli_fetch_assoc($rs)) { $studentCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}

// Active students in the selected academic year (status = 'Active')
$activeCount = 0;
$sqlActive = "SELECT COUNT(DISTINCT s.student_id) AS cnt
              FROM student s
              JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status = 'Active'" . $yearCond . "
              WHERE COALESCE(s.student_status,'') <> 'Inactive'";
if ($rs = mysqli_query($con, $sqlActive)) {
  if ($r = mysqli_fetch_assoc($rs)) { $activeCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}

// Internship count in selected academic year (students with OJT record linked to enrollments of the year)
$internCount = 0;
$sqlIntern = "SELECT COUNT(DISTINCT o.student_id) AS cnt
              FROM ojt o
              JOIN student_enroll e ON e.student_id = o.student_id AND e.student_enroll_status IN ('Following','Active')" . $yearCond . "
              JOIN student s ON s.student_id = e.student_id
              WHERE COALESCE(s.student_status,'') <> 'Inactive'";
if ($rs = mysqli_query($con, $sqlIntern)) {
  if ($r = mysqli_fetch_assoc($rs)) { $internCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}

// NVQ Level 4 & 5 student totals (Following)
$nvq4Count = 0; $nvq5Count = 0;
$sqlNvq4 = "SELECT COUNT(DISTINCT s.student_id) AS cnt
            FROM student s
            JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status = 'Following'" . $yearCond . "
            JOIN course c ON c.course_id = e.course_id
            WHERE COALESCE(s.student_status,'') <> 'Inactive' AND CAST(c.course_nvq_level AS CHAR) = '4'";
if ($rs = mysqli_query($con, $sqlNvq4)) { if ($r = mysqli_fetch_assoc($rs)) { $nvq4Count = (int)$r['cnt']; } mysqli_free_result($rs); }

$sqlNvq5 = "SELECT COUNT(DISTINCT s.student_id) AS cnt
            FROM student s
            JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status = 'Following'" . $yearCond . "
            JOIN course c ON c.course_id = e.course_id
            WHERE COALESCE(s.student_status,'') <> 'Inactive' AND CAST(c.course_nvq_level AS CHAR) = '5'";
if ($rs = mysqli_query($con, $sqlNvq5)) { if ($r = mysqli_fetch_assoc($rs)) { $nvq5Count = (int)$r['cnt']; } mysqli_free_result($rs); }
?>

<style>
  /* Cool Color Theme Variables - Matching Index.php */
  :root {
    --theme-primary: #6366f1;
    --theme-primary-dark: #4f46e5;
    --theme-primary-light: #818cf8;
    --theme-secondary: #06b6d4;
    --theme-accent: #f472b6;
    --theme-success: #22d3ee;
    --theme-indigo: #6366f1;
    --theme-cyan: #06b6d4;
    --theme-pink: #f472b6;
  }
  
  /* Admin Dashboard Container - Full Width */
  .page-content .container-fluid {
    max-width: 100% !important;
    width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    padding-left: 15px;
    padding-right: 15px;
    box-sizing: border-box;
  }

  /* Responsive adjustments */
  @media (min-width: 992px) {
    .page-content .container-fluid {
      padding-left: 20px;
      padding-right: 20px;
    }
  }

  @media (max-width: 991.98px) {
    .page-content .container-fluid {
      padding-left: 15px;
      padding-right: 15px;
    }
  }

  @media (max-width: 575.98px) {
    .page-content .container-fluid {
      padding-left: 10px;
      padding-right: 10px;
    }
  }
  
  /* Enhanced Form Controls - Cool Colors */
  .form-control {
    border: 1.5px solid #c7d2fe;
    border-radius: 8px;
    transition: all 0.3s ease;
  }
  .form-control:focus {
    border-color: var(--theme-primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    outline: none;
  }
  
  /* Enhanced Buttons - Cool Gradient */
  .btn-primary {
    background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-primary-dark) 50%, var(--theme-secondary) 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    transition: all 0.3s ease;
  }
  .btn-primary:hover {
    background: linear-gradient(135deg, var(--theme-primary-light) 0%, var(--theme-primary) 50%, var(--theme-success) 100%);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
    transform: translateY(-2px);
  }

  /* Enhanced stat cards with modern design */
  .stat-card { 
    border: 0; 
    color: #fff; 
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
  }
  .stat-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 12px 30px rgba(0,0,0,0.25) !important;
  }
  /* Cool Color Palette - Matching Index.php */
  .bg-red    { background: linear-gradient(135deg, #f472b6 0%, #ec4899 100%); }
  .bg-black  { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); }
  .bg-yellow { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; }
  .bg-blue   { background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-primary-dark) 50%, var(--theme-secondary) 100%); }
  .bg-green  { background: linear-gradient(135deg, var(--theme-secondary) 0%, #0891b2 100%); }
  .bg-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
  .stat-card .icon { 
    width: 56px; 
    height: 56px; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    border-radius: 12px; 
    background: rgba(255,255,255,0.25);
    font-size: 1.5rem;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
  }
  .stat-card:hover .icon {
    transform: scale(1.1);
    background: rgba(255,255,255,0.35);
  }
  .bg-yellow .icon { background: rgba(255,255,255,0.25); }
  .stat-label { 
    opacity: .95; 
    font-size: .75rem; 
    text-transform: uppercase; 
    letter-spacing: 1px; 
    font-weight: 600;
    margin-bottom: 0.25rem;
  }
  .stat-value { 
    font-size: 2.25rem; 
    font-weight: 800; 
    line-height: 1; 
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  /* Mobile-only: remove outer side space on dashboard */
  @media (max-width: 575.98px) {
    .page-wrapper .page-content > .container-fluid { padding-left: .25rem !important; padding-right: .25rem !important; }
    .mobile-tight > [class^="col-"],
    .mobile-tight > [class*=" col-"] { padding-left: 0 !important; padding-right: 0 !important; }
    .mobile-tight { margin-left: 0 !important; margin-right: 0 !important; }
    /* Increase height a bit for the line chart on small screens */
    .dept-line-body { height: clamp(300px, 50vh, 520px) !important; }
  }
  /* Chips list under line chart to show district names currently plotted */
  .chip-list-wrap { margin-top: .5rem; }
  .chip-list-label { font-size: 12px; color: #6c757d; margin-bottom: .25rem; }
  .chip-list { 
    padding-top: .5rem; 
    border-top: 1px solid #f1f3f5; 
    display: flex; 
    flex-wrap: wrap; 
    gap: 6px 8px;
    width: 100%;
  }
  .chip { 
    display: inline-flex; 
    align-items: center; 
    background: #f8f9fa; 
    border: 1px solid #e9ecef; 
    color: #495057; 
    border-radius: 999px; 
    padding: 2px 10px; 
    font-size: 12px; 
    line-height: 1.6; 
    white-space: nowrap; 
    box-shadow: 0 1px 0 rgba(0,0,0,0.02);
  }
  .chip .count { 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    min-width: 18px; height: 18px; 
    margin-left: 6px; 
    border-radius: 999px; 
    background: #e9ecef; 
    color: #495057; 
    font-size: 11px; 
    padding: 0 6px; 
  }
  /* Ensure department titles in accordion headers are left-aligned and can wrap - Bootstrap 5 */
  .accordion .accordion-header .accordion-button { text-align: left; white-space: normal; }
  @media (max-width: 575.98px) {
    .accordion .accordion-header h6 { justify-content: space-between; }
    .accordion .accordion-header .accordion-button { text-align: left; }
  }
  /* Footer-specific tweaks: remove divider inside card-footer */
  .card-footer .chip-list { border-top: 0; padding-top: 0; }
  .card-footer .chip-list-label { margin-bottom: .25rem; }
  @media (max-width: 575.98px) {
    .chip { font-size: 11px; padding: 2px 8px; }
    .chip .count { min-width: 16px; height: 16px; font-size: 10px; }
  }
  
  /* Disable table hover effects */
  .table tbody tr:hover {
    background-color: transparent !important;
  }
  .table-striped tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.05) !important;
  }
  .table tbody tr:hover td {
    background-color: transparent !important;
  }
</style>
<!-- Admin Dashboard Header -->
<div class="row mt-2 mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0" style="background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-primary-dark) 50%, var(--theme-secondary) 100%); border-radius: 12px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4) !important;">
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
          <div class="mb-3 mb-md-0 text-white">
            <h3 class="mb-1 font-weight-bold">
              <i class="fas fa-tachometer-alt mr-2"></i>Sri Lanka German Training Institute - MIS
            </h3>
            <div class="text-white-50 small">
              <i class="fas fa-chart-line mr-1"></i>Administrative Dashboard Overview
            </div>
          </div>
          <div class="ml-md-auto">
            <form method="get" action="" class="form-inline">
              <div class="form-group mb-0 mr-2">
                <label class="mr-2 text-white small font-weight-bold">
                  <i class="fas fa-calendar-alt mr-1"></i>Academic Year
                </label>
                <select name="academic_year" class="form-control form-control-sm" style="min-width:200px; border-radius: 6px;">
                  <option value="">-- Latest Active --</option>
                  <?php
                  $years = [];
                  if ($rs = mysqli_query($con, "SELECT academic_year FROM academic ORDER BY academic_year DESC")) {
                    while ($r = mysqli_fetch_assoc($rs)) { $years[] = $r['academic_year']; }
                    mysqli_free_result($rs);
                  }
                  foreach ($years as $y) {
                    $sel = ($selectedYear === $y) ? 'selected' : '';
                    echo '<option value="'.htmlspecialchars($y).'" '.$sel.'>'.htmlspecialchars($y).'</option>';
                  }
                  ?>
                </select>
              </div>
              <button type="submit" class="btn btn-light btn-sm mr-2" style="border-radius: 6px;">
                <i class="fas fa-filter mr-1"></i>Apply
              </button>
              <?php if (!empty($_GET['academic_year'])): ?>
                <a href="<?php echo (defined('APP_BASE')? APP_BASE : ''); ?>/dashboard/index.php" class="btn btn-outline-light btn-sm" style="border-radius: 6px;">
                  <i class="fas fa-times mr-1"></i>Clear
                </a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mt-2 mobile-tight">
  <div class="col-md-4 col-sm-6 col-12 mb-2">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border-radius: 12px; transition: all 0.3s ease; border: none; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: #818cf8; font-size: 2rem;"><i class="fas fa-building fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.9); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Departments</div>
          <div class="stat-value" style="color: #ffffff; font-size: 2rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $deptCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--theme-pink) 0%, #ec4899 100%); border-radius: 12px; transition: all 0.3s ease; border: none; box-shadow: 0 4px 12px rgba(244, 114, 182, 0.3);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: #fbcfe8; font-size: 2rem;"><i class="fas fa-book-open fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.9); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Courses</div>
          <div class="stat-value" style="color: #ffffff; font-size: 2rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $courseCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-primary-dark) 50%, var(--theme-secondary) 100%); border-radius: 12px; transition: all 0.3s ease; border: none; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: #c7d2fe; font-size: 2rem;"><i class="fas fa-users fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.9); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Following Students</div>
          <div class="stat-value" style="color: #ffffff; font-size: 2rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $studentCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; transition: all 0.3s ease; border: none; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: #fde68a; font-size: 2rem;"><i class="fas fa-briefcase fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.9); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Internships</div>
          <div class="stat-value" style="color: #ffffff; font-size: 2rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $internCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--theme-secondary) 0%, #0891b2 100%); border-radius: 12px; transition: all 0.3s ease; border: none; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: #a5f3fc; font-size: 2rem;"><i class="fas fa-level-up-alt fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.9); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">NVQ Level 4</div>
          <div class="stat-value" style="color: #ffffff; font-size: 2rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $nvq4Count; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 12px; transition: all 0.3s ease; border: none; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: #ddd6fe; font-size: 2rem;"><i class="fas fa-level-up-alt fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.9); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">NVQ Level 5</div>
          <div class="stat-value" style="color: #ffffff; font-size: 2rem; font-weight: 800; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?php echo $nvq5Count; ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<hr>

<!-- Course-wise Students Table (excludes courses with zero students) -->
<?php
  // Build course-wise list for the selected academic year
  $yearCondCourse = $selectedYear !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $selectedYear) . "'") : '';
  $courseRows = [];
  $sqlCourses = "
    SELECT 
      d.department_name,
      c.course_id,
      c.course_name,
      COUNT(DISTINCT s.student_id) AS total,
      COUNT(DISTINCT CASE WHEN s.student_gender='Male' THEN s.student_id END) AS male,
      COUNT(DISTINCT CASE WHEN s.student_gender='Female' THEN s.student_id END) AS female
    FROM course c
    LEFT JOIN department d ON d.department_id = c.department_id
    LEFT JOIN student_enroll e ON e.course_id = c.course_id 
      AND e.student_enroll_status = 'Following'" . $yearCondCourse . "
    LEFT JOIN student s ON s.student_id = e.student_id AND COALESCE(s.student_status,'') <> 'Inactive'
    WHERE LOWER(TRIM(d.department_name)) NOT IN ('admin','administration')
    GROUP BY d.department_name, c.course_id, c.course_name
    HAVING total > 0
    ORDER BY d.department_name ASC, c.course_name ASC";
  if ($rs = mysqli_query($con, $sqlCourses)) {
    while ($r = mysqli_fetch_assoc($rs)) { $courseRows[] = $r; }
    mysqli_free_result($rs);
  }
?>

<?php
  // Group courses under their departments
  $byDept = [];
  $deptTotals = [];
  foreach ($courseRows as $row) {
    $dn = $row['department_name'] ?? 'Unknown';
    if (!isset($byDept[$dn])) { $byDept[$dn] = []; $deptTotals[$dn] = 0; }
    $byDept[$dn][] = $row;
    $deptTotals[$dn] += (int)($row['total'] ?? 0);
  }
  // Compute gender counts per department (Male/Female)
  $genderByDept = [];
  $yearCondG = $selectedYear !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $selectedYear) . "'") : '';
  $sqlG = "
    SELECT d.department_name AS dep,
           COUNT(DISTINCT CASE WHEN s.student_gender='Male' AND COALESCE(s.student_status,'') <> 'Inactive' THEN s.student_id END) AS male,
           COUNT(DISTINCT CASE WHEN s.student_gender='Female' AND COALESCE(s.student_status,'') <> 'Inactive' THEN s.student_id END) AS female
    FROM department d
    LEFT JOIN course c ON c.department_id = d.department_id
    LEFT JOIN student_enroll e ON e.course_id = c.course_id AND e.student_enroll_status = 'Following' $yearCondG
    LEFT JOIN student s ON s.student_id = e.student_id
    GROUP BY d.department_name
  ";
  if ($rsG = mysqli_query($con, $sqlG)) {
    while ($rg = mysqli_fetch_assoc($rsG)) {
      $genderByDept[$rg['dep']] = [
        'male' => (int)($rg['male'] ?? 0),
        'female' => (int)($rg['female'] ?? 0)
      ];
    }
    mysqli_free_result($rsG);
  }
?>
<div class="row mt-1 mobile-tight">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important; border-bottom: 2px solid #e2e8f0;">
        <div class="font-weight-bold" style="color: var(--theme-primary); font-size: 1.1rem;">
          <i class="fas fa-chart-bar mr-2"></i> Department-wise Course Counts
        </div>
        <?php if (!empty($selectedYear)) : ?>
          <span class="badge" style="background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-secondary) 100%); color: #ffffff; padding: 0.5rem 1rem; font-weight: 600;">
            <i class="fas fa-calendar-alt mr-1"></i>Year: <?php echo htmlspecialchars($selectedYear); ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="card-body p-4">
        <?php if (empty($byDept)): ?>
          <div class="text-center text-muted py-5">
            <i class="fas fa-inbox fa-3x mb-3" style="color: #cbd5e1;"></i>
            <p class="mb-0">No data available<?php echo $selectedYear ? ' for the selected year' : ''; ?>.</p>
          </div>
        <?php else: ?>
          <!-- Department Accordion - Full Width -->
          <div class="accordion" id="deptCourseAcc" style="width: 100%;">
            <?php $i=0; foreach ($byDept as $deptName => $rows): $i++; $collapseId = 'dcoll'.$i; 
              $g = $genderByDept[$deptName] ?? ['male'=>0,'female'=>0];
              $courseCount = count($rows);
              $totalStudents = $deptTotals[$deptName] ?? 0;
            ?>
              <div class="card mb-2 border-0 shadow-sm" style="background: #ffffff; border-radius: 8px;">
                <div class="card-header py-3" id="h<?php echo $i; ?>" style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-radius: 8px; cursor: pointer;">
                  <h6 class="mb-0 d-flex justify-content-between align-items-center">
                    <button class="btn btn-link p-0 text-left" type="button" data-toggle="collapse" data-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>" style="color: #1e293b; font-weight: 600; text-decoration: none; width: 100%;">
                      <span style="font-size: 1rem;">
                        <i class="fas fa-building mr-2" style="color: var(--theme-primary);"></i>
                        <?php echo htmlspecialchars($deptName); ?>
                      </span>
                      <small class="ml-2 text-muted">(<?php echo $courseCount; ?> <?php echo $courseCount == 1 ? 'Course' : 'Courses'; ?>)</small>
                    </button>
                    <span class="ml-2 d-flex align-items-center">
                      <span class="badge badge-primary mr-2" title="Total Students" style="background: linear-gradient(135deg, var(--theme-secondary) 0%, #0891b2 100%); padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                        <i class="fas fa-users mr-1"></i><?php echo number_format($totalStudents); ?>
                      </span>
                      <span class="badge badge-primary mr-1" title="Male" style="background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-primary-dark) 100%);">
                        <i class="fas fa-male"></i> <?php echo number_format($g['male']); ?>
                      </span>
                      <span class="badge" style="background: linear-gradient(135deg, var(--theme-pink) 0%, #ec4899 100%); color: #fff; padding: 0.4rem 0.8rem;" title="Female">
                        <i class="fas fa-female"></i> <?php echo number_format($g['female']); ?>
                      </span>
                    </span>
                  </h6>
                </div>
                <div id="<?php echo $collapseId; ?>" class="collapse" data-parent="#deptCourseAcc">
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm table-striped mb-0" style="font-size: 0.9rem;">
                        <thead class="thead-light">
                          <tr>
                            <th style="width:50%; font-weight: 600; color: #475569; padding: 12px 15px;">Course</th>
                            <th class="text-right" style="width:15%; font-weight: 600; color: #475569; padding: 12px 15px;">Male</th>
                            <th class="text-right" style="width:15%; font-weight: 600; color: #475569; padding: 12px 15px;">Female</th>
                            <th class="text-right" style="width:20%; font-weight: 600; color: #475569; padding: 12px 15px;">Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($rows as $r): ?>
                            <tr>
                              <td style="color: #1e293b; padding: 12px 15px;"><?php echo htmlspecialchars($r['course_name'] ?? ''); ?></td>
                              <td class="text-right font-weight-bold" style="color: var(--theme-primary); padding: 12px 15px;"><?php echo (int)($r['male'] ?? 0); ?></td>
                              <td class="text-right font-weight-bold" style="color: var(--theme-pink); padding: 12px 15px;"><?php echo (int)($r['female'] ?? 0); ?></td>
                              <td class="text-right font-weight-bold" style="color: #1e293b; padding: 12px 15px;"><?php echo (int)($r['total'] ?? 0); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row mt-3 mobile-tight align-items-center rel-filter">
  <div class="col-md-6 col-sm-12">
    <h5 class="mb-0"><i class="fas fa-praying-hands mr-2 text-primary"></i>Religion-wise Students</h5>
    <small class="text-muted">Live count by student religion</small>
  </div>
  <div class="col-md-6 col-sm-12 text-md-right mt-2 mt-md-0">
    <form class="form-inline justify-content-md-end" id="relFilterForm" style="display: none !important;">
      <label class="mr-2 small text-muted">Department</label>
      <select class="form-control form-control-sm" id="relDept">
        <option value="">All</option>
        <?php
          $dres = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
          if ($dres) { while($dr = mysqli_fetch_assoc($dres)) { echo '<option value="'.htmlspecialchars($dr['department_id']).'">'.htmlspecialchars($dr['department_name'])."</option>"; } }
        ?>
      </select>
    </form>
  </div>
  <div class="col-12"><hr class="mt-2 mb-3"></div>
</div>

<div class="row" id="religionCards"></div>

<script>
  (function(){
    function fetchReligion(dept){
      var base = "<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>";
      var url = base + '/dashboard/religion_distribution_api.php' + (dept ? ('?department_id=' + encodeURIComponent(dept)) : '');
      return fetch(url).then(function(r){ return r.json(); });
    }
    function renderReligion(json){
      var wrap = document.getElementById('religionCards');
      if (!wrap) return;
      wrap.innerHTML = '';
      if (!json || json.ok!==true || !json.data || !json.data.length){
        wrap.innerHTML = '<div class="col-12"><div class="card rel-empty"><div class="card-body text-center">No religion data available.</div></div></div>';
        return;
      }
      var total = Number(json.total||0) || 0;
      json.data.forEach(function(r, idx){
        var name = String(r.religion||'Unknown');
        var cnt = Number(r.cnt||0) || 0;
        var pct = total>0 ? Math.round((cnt/total)*1000)/10 : 0; // 0.1 precision
        var grad = 'rel-grad-' + (idx % 8);
        var icon = '<i class="fas fa-praying-hands"></i>';
        var col = document.createElement('div');
        col.className = 'col-lg-3 col-md-4 col-sm-6 col-12 mb-3';
        col.innerHTML = '<div class="card rel-card '+grad+' shadow-sm">\
            <div class="card-body rel-body">\
              <div>\
                <div class="rel-name">'+name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</div>\
                <div class="rel-percent">'+pct+'% of students</div>\
              </div>\
              <div class="text-right">\
                <div class="rel-icon mb-2">'+icon+'</div>\
                <div class="rel-count">'+cnt+'</div>\
              </div>\
            </div>\
          </div>';
        wrap.appendChild(col);
      });
    }
    function load(){
      var dept = document.getElementById('relDept').value;
      fetchReligion(dept).then(renderReligion).catch(function(){ renderReligion(null); });
    }
    document.addEventListener('DOMContentLoaded', function(){
      var sel = document.getElementById('relDept');
      if (sel) sel.addEventListener('change', load);
      load();
    });
  })();
  </script>


<div class="row mt-4 mobile-tight">
    <div class="col-12">
        <?php
        // Pass selected year into widget
        $gw_academic_year = $selectedYear;
        // Embed gender charts widget directly on dashboard
        $genderWidget = __DIR__ . '/partials/gender_widget.php';
        if (file_exists($genderWidget)) {
            include $genderWidget;
        } else {
            echo '<div class="alert alert-warning">Gender widget not found.</div>';
        }
        ?>
    </div>
</div>

<div class="row mt-4 mobile-tight">
    <div class="col-12">
        <?php
        // Province & District-wise gender widget
        $ggw_academic_year = $selectedYear;
        $geoWidget = __DIR__ . '/partials/geo_gender_widget.php';
        if (file_exists($geoWidget)) {
            include $geoWidget;
        } else {
            echo '<div class="alert alert-warning">Geo gender widget not found.</div>';
        }
        ?>
    </div>
    
</div>

<!-- Department-wise District Count (Line Chart) -->
<div class="row mt-4 mobile-tight">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
        <div class="font-weight-semibold"><i class="fas fa-chart-line mr-1 text-primary"></i> Department-wise District Counts</div>
        <?php if (!empty($selectedYear)) : ?>
          <span class="badge badge-light">Year: <?php echo htmlspecialchars($selectedYear); ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body dept-line-body" style="height: clamp(260px, 42vh, 420px);">
        <canvas id="deptDistrictLine"></canvas>
      </div>
      <div class="card-footer bg-white py-2">
        <div class="chip-list-wrap mb-1">
          <div class="chip-list-label">Districts shown</div>
          <div id="deptDistrictList" class="chip-list" aria-live="polite" aria-label="Districts currently plotted"></div>
        </div>
      </div>
    </div>
  </div>
  <script>
    window.addEventListener('load', function(){
      var ctx = document.getElementById('deptDistrictLine');
      if (!ctx || !window.Chart) return;
      var isMobile = window.matchMedia('(max-width: 575.98px)').matches;
      var limit = isMobile ? 5 : 0; // 0 => all districts (API omits LIMIT)
      var url = "<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/dashboard/department_district_api.php?academic_year=<?php echo urlencode($selectedYear); ?>&limit=" + encodeURIComponent(limit);
      fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(json){
          if (!json || json.status !== 'success') { throw new Error('API error'); }
          var labels = json.data.labels || [];
          var series = json.data.datasets || [];
          // If no data, render a small notice in the card and abort
          if (!labels.length || !series.length) {
            var container = ctx.parentNode;
            if (container) {
              var div = document.createElement('div');
              div.className = 'text-center text-muted small';
              div.textContent = 'No department-wise district data available for the selected academic year.';
              container.appendChild(div);
              ctx.style.display = 'none';
              // Clear district list if exists
              var listEl0 = document.getElementById('deptDistrictList');
              if (listEl0) listEl0.innerHTML = '';
            }
            return;
          }
          // Populate district names shown in the line chart (chips) with per-district totals
          try {
            var listEl = document.getElementById('deptDistrictList');
            if (listEl) {
              // compute totals per district across all departments (series)
              var totalsByLabel = labels.map(function(_, idx){
                var sum = 0; 
                for (var k=0; k<series.length; k++) { sum += Number(series[k].data[idx] || 0); }
                return sum;
              });
              // Determine top 3 ranks by total (ties keep earlier rank order)
              var idxs = labels.map(function(_,i){return i;});
              idxs.sort(function(a,b){ return (totalsByLabel[b]-totalsByLabel[a]) || (a-b); });
              var rankByIdx = {};
              for (var r=0; r<idxs.length; r++) { rankByIdx[idxs[r]] = r+1; }
              listEl.innerHTML = labels.map(function(name, idx){
                var safe = String(name).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                var count = totalsByLabel[idx] || 0;
                var rank = rankByIdx[idx] || 999;
                var rankCls = rank===1? ' top1' : (rank===2? ' top2' : (rank===3? ' top3' : ''));
                var medal = rank<=3 ? '<span class="medal" aria-hidden="true"></span>' : '';
                var title = 'title="' + safe + ': ' + count + ' students' + (rank<=3? (' (Rank '+rank+')') : '') + '"';
                return '<span class="chip'+ rankCls +'" '+ title +'>' + medal + safe + '<span class="count">'+ count +'</span></span>';
              }).join('');
            }
          } catch(e) { /* no-op */ }
          var palette = [
            '#4e73df','#e74a3b','#1cc88a','#f6c23e','#36b9cc','#6f42c1','#fd7e14','#20c997'
          ];
          var datasets = series.map(function(s, i){
            var color = palette[i % palette.length];
            return {
              label: s.label,
              data: s.data,
              fill: false,
              borderColor: color,
              backgroundColor: color,
              borderWidth: isMobile ? 1.5 : 2,
              pointRadius: isMobile ? 0 : 3,
              pointHoverRadius: isMobile ? 6 : 5,
              pointHitRadius: 10,
              lineTension: 0.25,
              cubicInterpolationMode: 'monotone',
              spanGaps: true
            };
          });
          var chart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: { labels: labels, datasets: datasets },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              legend: { display: !isMobile, position: isMobile ? 'bottom' : 'top', labels: { boxWidth: 10, fontSize: isMobile ? 10 : 12 } },
              tooltips: { mode: 'index', intersect: false, bodyFontSize: isMobile ? 11 : 12 },
              layout: { padding: { left: 6, right: 6, top: 6, bottom: isMobile ? 12 : 8 } },
              hover: { mode: 'nearest', intersect: true },
              scales: {
                xAxes: [{
                  ticks: {
                    autoSkip: isMobile ? true : false,
                    maxRotation: isMobile ? 35 : 60,
                    minRotation: 0,
                    fontSize: isMobile ? 10 : 12,
                    callback: function(value, index){
                      if (!isMobile) return value;
                      // On mobile, show every 2nd label only
                      if (index % 2 !== 0) return '';
                      var v = String(value || '');
                      return v.length > 10 ? (v.substr(0, 10) + '…') : v;
                    }
                  },
                  gridLines: { display: false }
                }],
                yAxes: [{
                  ticks: { beginAtZero: true, precision: 0, fontSize: isMobile ? 10 : 12 },
                  gridLines: { color: 'rgba(0,0,0,0.05)' }
                }]
              }
            }
          });
        })
        .catch(function(e){
          console && console.warn && console.warn('deptDistrictLine error', e);
          var container = ctx && ctx.parentNode;
          if (container) {
            var div = document.createElement('div');
            div.className = 'text-center text-muted small';
            div.textContent = 'Unable to load chart at this time.';
            container.appendChild(div);
            if (ctx) ctx.style.display = 'none';
          }
        });
    });
  </script>
</div>

<!-- Removed progress bar cards row (Completion & Dropout) as requested -->




<!-- 
<div class="row m-2">
    <div class="col-md-12  ">
        <canvas id="myChart"></canvas>
    </div>
</div> -->


<!-- 
<script>
function showCouese(val) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Course").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getCourse", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("department=" + val);
}

function showModule(val) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Module").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getModule", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("course=" + val);
}

function showTeacher() {
    var did = document.getElementById("Departmentx").value;
    var cid = document.getElementById("Course").value;
    var mid = document.getElementById("Module").value;
    var aid = null;
    var tid = null;

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Teacher").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getTeacher", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("StaffModuleEnrollment=1&staff_id=" + tid + "&course_id=" + cid + "&module_id=" + mid +
        "&academic_year=" + aid);
}
</script>

 -->


<!-- Chart and script removed -->
 
 
<!-- Admin Dashboard Alignment Script -->
<script>
  (function() {
    'use strict';
    
    var resizeTimer = null;
    var sidebarToggleTimer = null;
    
    // Ensure proper alignment for admin dashboard - Full Width
    function ensureDashboardAlignment() {
      try {
        var containerFluid = document.querySelector('.page-content .container-fluid');
        if (!containerFluid) return;
        
        // Full width - no centering
        containerFluid.style.maxWidth = '100%';
        containerFluid.style.width = '100%';
        containerFluid.style.marginLeft = '0';
        containerFluid.style.marginRight = '0';
        
        // Ensure proper padding based on screen size
        var windowWidth = window.innerWidth || document.documentElement.clientWidth;
        if (windowWidth >= 992) {
          containerFluid.style.paddingLeft = '20px';
          containerFluid.style.paddingRight = '20px';
        } else if (windowWidth >= 576) {
          containerFluid.style.paddingLeft = '15px';
          containerFluid.style.paddingRight = '15px';
        } else {
          containerFluid.style.paddingLeft = '10px';
          containerFluid.style.paddingRight = '10px';
        }
        
        // Ensure box-sizing
        containerFluid.style.boxSizing = 'border-box';
      } catch(e) {
        console.warn('Error ensuring dashboard alignment:', e);
      }
    }
    
    // Handle window resize with debouncing
    function handleResize() {
      if (resizeTimer) {
        clearTimeout(resizeTimer);
      }
      resizeTimer = setTimeout(function() {
        ensureDashboardAlignment();
      }, 150);
    }
    
    // Handle sidebar toggle
    function handleSidebarToggle() {
      if (sidebarToggleTimer) {
        clearTimeout(sidebarToggleTimer);
      }
      sidebarToggleTimer = setTimeout(function() {
        ensureDashboardAlignment();
      }, 300);
    }
    
    // Initialize on DOM ready
    function initDashboardAlignment() {
      ensureDashboardAlignment();
      
      // Setup resize handler
      window.removeEventListener('resize', handleResize);
      window.addEventListener('resize', handleResize, { passive: true });
      
      // Setup sidebar toggle handlers (if jQuery is available)
      if (window.jQuery) {
        jQuery(document).off('click', '#show-sidebar, #close-sidebar').on('click', '#show-sidebar, #close-sidebar', handleSidebarToggle);
        
        // Also listen for sidebar state changes
        var observer = new MutationObserver(function(mutations) {
          mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
              handleSidebarToggle();
            }
          });
        });
        
        var pageWrapper = document.querySelector('.page-wrapper');
        if (pageWrapper) {
          observer.observe(pageWrapper, {
            attributes: true,
            attributeFilter: ['class']
          });
        }
      }
    }
    
    // Run initialization
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initDashboardAlignment);
    } else {
      initDashboardAlignment();
    }
    
    // Also run after a short delay to ensure all styles are applied
    setTimeout(ensureDashboardAlignment, 100);
  })();
</script>

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->

<?php endif; ?>