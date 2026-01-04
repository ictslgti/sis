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
      <div class="form-group">
        <label class="small text-muted">Academic Year</label>
        <select name="academic_year" class="form-control form-control-sm">
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
      <button type="submit" class="btn btn-primary btn-sm">Apply</button>
      <?php if (!empty($_GET['academic_year'])): ?>
        <a href="<?php echo (defined('APP_BASE')? APP_BASE : ''); ?>/dashboard/index.php" class="btn btn-outline-secondary btn-sm">Clear</a>
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
        <h6 class="mb-3 px-0" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%); color: #ffffff; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600;">
          <i class="fas fa-graduation-cap mr-2"></i>My Academic Summary
        </h6>
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

    <div class="alert alert-info" style="background-color: var(--color-light-blue); border-color: var(--color-medium-blue); color: var(--color-dark-navy);">
      <i class="fas fa-info-circle mr-2"></i>This dashboard is personalized for students. Use the sidebar to access Attendance, Assessments, Notices, and more.
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
$yearCond = $selectedYear !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $selectedYear) . "'") : '';
$sqlStu = "SELECT COUNT(DISTINCT s.student_id) AS cnt
           FROM student s
           JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status = 'Following'" . $yearCond . "
           WHERE COALESCE(s.student_status,'') <> 'Inactive'";
if ($rs = mysqli_query($con, $sqlStu)) {
  if ($r = mysqli_fetch_assoc($rs)) { $studentCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}

// Active students in the selected academic year
$activeCount = 0;
$sqlActive = "SELECT COUNT(DISTINCT s.student_id) AS cnt
              FROM student s
              JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status = 'Active'" . $yearCond . "
              WHERE COALESCE(s.student_status,'') <> 'Inactive'";
if ($rs = mysqli_query($con, $sqlActive)) {
  if ($r = mysqli_fetch_assoc($rs)) { $activeCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}

// Internship count in selected academic year
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

// NVQ Level 4 & 5 student totals
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
  /* ============================================
     DASHBOARD - CUSTOM COLOR THEME
     Colors: #E7F0FA, #7BA4D0, #2E5E00, #0D2440
     ============================================ */
  
  /* Custom Color Theme Variables */
  :root {
    --color-light-blue: #E7F0FA;
    --color-medium-blue: #7BA4D0;
    --color-dark-green: #2E5E00;
    --color-dark-navy: #0D2440;
    --white: #ffffff;
    --gray-light: #f8fafc;
    --gray-border: #d1d5db;
    --text-dark: #1e293b;
    --text-muted: #64748b;
  }
  
  /* Dashboard Container */
  .page-content {
    width: 100%;
    margin: 0;
    padding: 0;
  }
  
  .page-content .container-fluid {
    max-width: 100%;
    width: 100%;
    margin-left: auto;
    margin-right: auto;
    padding-left: 15px;
    padding-right: 15px;
    box-sizing: border-box;
  }

  @media (min-width: 1400px) {
    .page-content .container-fluid {
      padding-left: 30px;
      padding-right: 30px;
    }
  }

  @media (min-width: 992px) and (max-width: 1399px) {
    .page-content .container-fluid {
      padding-left: 20px;
      padding-right: 20px;
    }
  }

  @media (min-width: 768px) and (max-width: 991.98px) {
    .page-content .container-fluid {
      padding-left: 15px;
      padding-right: 15px;
    }
  }

  @media (max-width: 767.98px) {
    .page-content .container-fluid {
      padding-left: 12px;
      padding-right: 12px;
    }
  }

  @media (max-width: 575.98px) {
    .page-content .container-fluid {
      padding-left: 10px;
      padding-right: 10px;
    }
  }
  
  /* Row Alignment */
  .row {
    margin-left: -15px;
    margin-right: -15px;
  }
  
  .row > [class*="col-"] {
    padding-left: 15px;
    padding-right: 15px;
  }
  
  @media (max-width: 575.98px) {
    .row {
      margin-left: -10px;
      margin-right: -10px;
    }
    
    .row > [class*="col-"] {
      padding-left: 10px;
      padding-right: 10px;
    }
  }
  
  /* Form Controls - Custom Color Theme */
  .form-control {
    border: 1.5px solid var(--color-medium-blue);
    border-radius: 8px;
    background: var(--white);
    color: var(--text-dark);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
    height: auto;
  }
  
  .form-control:focus {
    border-color: var(--color-dark-navy);
    box-shadow: 0 0 0 3px rgba(13, 36, 64, 0.1);
    outline: none;
    background: var(--white);
    color: var(--text-dark);
  }
  
  /* Select Box - Proper Size & Alignment */
  select.form-control,
  select.form-control-sm {
    min-width: 200px;
    max-width: 100%;
    width: auto;
    padding: 0.5rem 2rem 0.5rem 0.75rem;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%230D2440' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 12px;
    cursor: pointer;
    vertical-align: middle;
  }
  
  select.form-control-sm {
    padding: 0.375rem 1.75rem 0.375rem 0.625rem;
    font-size: 0.875rem;
    min-width: 180px;
    height: auto;
  }
  
  /* Form Inline Alignment */
  .form-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
  }
  
  .form-inline .form-group {
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: nowrap;
  }
  
  .form-inline label {
    margin-bottom: 0;
    white-space: nowrap;
    flex-shrink: 0;
  }
  
  .form-inline select {
    flex-shrink: 0;
  }
  
  .form-inline .btn {
    flex-shrink: 0;
    white-space: nowrap;
  }
  
  /* Responsive Select Box */
  @media (max-width: 767.98px) {
    .form-inline {
      flex-direction: column;
      align-items: stretch;
      gap: 0.5rem;
    }
    
    .form-inline .form-group {
      flex-direction: column;
      align-items: stretch;
      gap: 0.25rem;
    }
    
    .form-inline label {
      width: 100%;
    }
    
    select.form-control,
    select.form-control-sm {
      width: 100% !important;
      min-width: 100% !important;
      max-width: 100% !important;
    }
    
    .form-inline .btn {
      width: 100%;
    }
  }
  
  /* Dashboard Header Form Alignment */
  .dashboard-header-card .form-inline {
    align-items: center;
  }
  
  .dashboard-header-card .form-group {
    margin-right: 0.5rem;
  }
  
  @media (max-width: 991.98px) {
    .dashboard-header-card .form-inline {
      flex-direction: column;
      align-items: stretch;
    }
    
    .dashboard-header-card .form-group {
      margin-right: 0;
      margin-bottom: 0.5rem;
    }
    
    .dashboard-header-card select {
      width: 100% !important;
    }
  }
  
  /* Buttons - Custom Color Theme */
  .btn-primary {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
    border: none;
    color: var(--white);
    box-shadow: 0 2px 4px rgba(13, 36, 64, 0.2);
    border-radius: 8px;
    font-weight: 600;
  }
  
  .btn-primary:hover {
    background: linear-gradient(135deg, var(--color-medium-blue) 0%, var(--color-dark-navy) 100%);
    box-shadow: 0 4px 8px rgba(13, 36, 64, 0.3);
  }
  
  .btn-outline-primary {
    border: 2px solid var(--color-dark-navy);
    color: var(--color-dark-navy);
    background: transparent;
    border-radius: 8px;
  }
  
  .btn-outline-primary:hover {
    background: var(--color-dark-navy);
    color: var(--white);
  }
  
  .btn-outline-secondary {
    border: 2px solid var(--color-medium-blue);
    color: var(--color-medium-blue);
    background: transparent;
    border-radius: 8px;
  }
  
  .btn-outline-secondary:hover {
    background: var(--color-medium-blue);
    color: var(--white);
  }
  
  .btn-light {
    background: var(--color-light-blue);
    border: 1px solid var(--color-medium-blue);
    color: var(--color-dark-navy);
    border-radius: 8px;
    font-weight: 600;
  }
  
  .btn-light:hover {
    background: var(--color-medium-blue);
    color: var(--white);
  }
  
  .btn-outline-light {
    border: 2px solid var(--white);
    color: var(--white);
    background: transparent;
    border-radius: 8px;
  }
  
  .btn-outline-light:hover {
    background: var(--white);
    color: var(--color-dark-navy);
  }

  /* Stat Cards - Custom Color Theme */
  .stat-card { 
    border: 0; 
    color: var(--white); 
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(13, 36, 64, 0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  
  .stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(13, 36, 64, 0.2);
  }
  
  .stat-card .icon { 
    width: 56px; 
    height: 56px; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    border-radius: 12px; 
    background: rgba(255, 255, 255, 0.25);
    font-size: 1.5rem;
  }
  
  .stat-label { 
    opacity: 0.95; 
    font-size: 0.75rem; 
    text-transform: uppercase; 
    letter-spacing: 1px; 
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: rgba(255, 255, 255, 0.95);
  }
  
  .stat-value { 
    font-size: 2.25rem; 
    font-weight: 800; 
    line-height: 1; 
    color: var(--white);
  }

  /* Mobile Spacing */
  .mobile-tight {
    margin-left: -15px;
    margin-right: -15px;
  }
  
  .mobile-tight > [class^="col-"],
  .mobile-tight > [class*=" col-"] {
    padding-left: 15px;
    padding-right: 15px;
  }
  
  @media (max-width: 575.98px) {
    .mobile-tight {
      margin-left: -10px;
      margin-right: -10px;
    }
    
    .mobile-tight > [class^="col-"],
    .mobile-tight > [class*=" col-"] {
      padding-left: 10px;
      padding-right: 10px;
    }
    
    .dept-line-body { 
      height: clamp(300px, 50vh, 520px) !important; 
    }
  }
  
  /* Chip List Styles */
  .chip-list-wrap { 
    margin-top: 0.5rem; 
  }
  
  .chip-list-label { 
    font-size: 12px; 
    color: var(--text-muted); 
    margin-bottom: 0.25rem; 
  }
  
  .chip-list { 
    padding-top: 0.5rem; 
    border-top: 1px solid var(--gray-border); 
    display: flex; 
    flex-wrap: wrap; 
    gap: 6px 8px;
    width: 100%;
  }
  
  .chip { 
    display: inline-flex; 
    align-items: center; 
    background: var(--gray-light); 
    border: 1px solid var(--gray-border); 
    color: var(--text-dark); 
    border-radius: 999px; 
    padding: 2px 10px; 
    font-size: 12px; 
    line-height: 1.6; 
    white-space: nowrap; 
  }
  
  .chip .count { 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    min-width: 18px; 
    height: 18px; 
    margin-left: 6px; 
    border-radius: 999px; 
    background: var(--gray-border); 
    color: var(--text-dark); 
    font-size: 11px; 
    padding: 0 6px; 
  }
  
  .card-footer .chip-list { 
    border-top: 0; 
    padding-top: 0; 
  }
  
  .card-footer .chip-list-label { 
    margin-bottom: 0.25rem; 
  }
  
  @media (max-width: 575.98px) {
    .chip { 
      font-size: 11px; 
      padding: 2px 8px; 
    }
    
    .chip .count { 
      min-width: 16px; 
      height: 16px; 
      font-size: 10px; 
    }
  }
  
  /* Accordion Styles */
  .accordion .accordion-header .accordion-button { 
    text-align: left; 
    white-space: normal; 
  }
  
  @media (max-width: 575.98px) {
    .accordion .accordion-header h6 { 
      justify-content: space-between; 
    }
    
    .accordion .accordion-header .accordion-button { 
      text-align: left; 
    }
  }
  
  /* Table Styles - Custom Color Theme */
  .table {
    color: var(--text-dark);
    background: var(--white);
  }
  
  .table thead th {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
    color: var(--white);
    font-weight: 600;
    border: none;
    padding: 1rem 0.75rem;
  }
  
  .table tbody {
    background: var(--white);
    color: var(--text-dark);
  }
  
  .table tbody td {
    color: var(--text-dark);
    border-color: var(--color-light-blue);
    padding: 0.875rem 0.75rem;
  }
  
  .table-striped tbody tr:nth-of-type(odd) {
    background-color: var(--color-light-blue);
  }
  
  .table tbody tr:hover {
    background-color: rgba(123, 164, 208, 0.1);
  }
  
  /* Cards - Custom Color Theme */
  .card {
    background: var(--white);
    border: 1px solid var(--color-light-blue);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(13, 36, 64, 0.08);
    transition: box-shadow 0.2s ease;
  }
  
  .card:hover {
    box-shadow: 0 4px 12px rgba(13, 36, 64, 0.12);
  }
  
  .card-header {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
    color: var(--white);
    border: none;
    border-radius: 12px 12px 0 0;
    padding: 1rem 1.25rem;
    font-weight: 600;
  }
  
  .card-header * {
    color: var(--white) !important;
  }
  
  .card-header h1,
  .card-header h2,
  .card-header h3,
  .card-header h4,
  .card-header h5,
  .card-header h6 {
    color: var(--white) !important;
  }
  
  .card-body {
    color: var(--text-dark);
    background: var(--white);
    padding: 1.5rem;
  }
  
  /* Alert Styles */
  .alert-info {
    background-color: var(--color-light-blue);
    border-color: var(--color-medium-blue);
    color: var(--color-dark-navy);
  }
  
  /* Badge Styles */
  .badge {
    background: var(--color-medium-blue);
    color: var(--white);
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-weight: 600;
  }
  
  .badge-primary {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
  }
  
  .badge-light {
    background: var(--color-light-blue);
    color: var(--color-dark-navy);
  }
  
  /* Text Colors */
  .text-primary {
    color: var(--color-dark-navy) !important;
  }
  
  .text-muted {
    color: var(--text-muted) !important;
  }
  
  /* Chip Styles */
  .chip {
    background: var(--color-light-blue);
    border: 1px solid var(--color-medium-blue);
    color: var(--color-dark-navy);
  }
  
  .chip .count {
    background: var(--color-medium-blue);
    color: var(--white);
  }
  
  /* Accordion Header */
  .accordion .card-header {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
    color: var(--white);
  }
  
  .accordion .card-header * {
    color: var(--white) !important;
  }
  
  .accordion .card-header button {
    color: var(--white) !important;
  }
  
  .accordion .card-header button i {
    color: var(--white) !important;
  }
  
  .accordion .card-header .badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: var(--white) !important;
    border: 1px solid rgba(255, 255, 255, 0.3);
  }
  
  /* Dashboard Header Card */
  .dashboard-header-card {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(13, 36, 64, 0.2);
  }
  
  /* Religion Cards */
  .rel-card {
    background: linear-gradient(135deg, var(--color-light-blue) 0%, var(--color-medium-blue) 100%);
    border: 1px solid var(--color-medium-blue);
    color: var(--color-dark-navy);
  }
  
  .rel-card .rel-name {
    color: var(--color-dark-navy);
    font-weight: 700;
  }
  
  .rel-card .rel-count {
    color: var(--color-dark-navy);
    font-weight: 800;
    font-size: 1.5rem;
  }
  
  /* Chart Container */
  .dept-line-body {
    background: var(--color-light-blue);
  }
  
  /* Alert Styling */
  .alert-info {
    background-color: var(--color-light-blue) !important;
    border-color: var(--color-medium-blue) !important;
    color: var(--color-dark-navy) !important;
  }
  
  .alert-warning {
    background-color: #fff3cd;
    border-color: #ffc107;
    color: #856404;
  }
  
  /* Card Footer */
  .card-footer {
    background-color: var(--white) !important;
    border-top: 1px solid var(--color-light-blue);
  }
  
  .card-footer .chip-list-label {
    color: var(--color-dark-navy);
    font-weight: 600;
  }
  
  /* Chip Styling */
  .chip {
    background: var(--color-light-blue) !important;
    border: 1px solid var(--color-medium-blue) !important;
    color: var(--color-dark-navy) !important;
  }
  
  .chip .count {
    background: var(--color-medium-blue) !important;
    color: var(--white) !important;
  }
  
  .chip.top1 .count,
  .chip.top2 .count,
  .chip.top3 .count {
    background: var(--color-dark-navy) !important;
    color: var(--white) !important;
  }
  
  /* Student Dashboard Cards */
  .card-title {
    color: var(--color-dark-navy);
    font-weight: 600;
  }
  
  /* Academic Year Selector in Header */
  .dashboard-header-card select.form-control {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: var(--white) !important;
  }
  
  .dashboard-header-card select.form-control option {
    background-color: var(--color-dark-navy);
    color: var(--white);
  }
  
  .dashboard-header-card select.form-control:focus {
    background-color: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    color: var(--white) !important;
  }
  
  /* Table Striped Rows */
  .table-striped tbody tr:nth-of-type(odd) {
    background-color: var(--color-light-blue) !important;
  }
  
  .table-striped tbody tr:nth-of-type(even) {
    background-color: var(--white) !important;
  }
  
  /* Text Colors */
  .text-muted {
    color: var(--text-muted) !important;
  }
  
  /* Links */
  a {
    color: var(--color-dark-navy);
  }
  
  a:hover {
    color: var(--color-medium-blue);
  }
  
  /* Empty State */
  .text-center.text-muted i {
    color: var(--color-medium-blue) !important;
  }
</style>

<!-- Admin Dashboard Header -->
<div class="row mt-2 mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0 dashboard-header-card">
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
              <div class="form-group">
                <label class="text-white small font-weight-bold">
                  <i class="fas fa-calendar-alt mr-1"></i>Academic Year
                </label>
                <select name="academic_year" class="form-control form-control-sm">
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
              <button type="submit" class="btn btn-light btn-sm">
                <i class="fas fa-filter mr-1"></i>Apply
              </button>
              <?php if (!empty($_GET['academic_year'])): ?>
                <a href="<?php echo (defined('APP_BASE')? APP_BASE : ''); ?>/dashboard/index.php" class="btn btn-outline-light btn-sm">
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

<style>
  /* ============================================
     DASHBOARD BLUE THEME - COMPREHENSIVE
     ============================================ */
  
  /* Page Background */
  body {
    background-color: var(--color-light-blue);
  }
  
  .page-content {
    background-color: var(--color-light-blue);
    min-height: 100vh;
  }
  
  .container-fluid {
    background-color: transparent;
  }
  
  /* Dashboard Header Card */
  .dashboard-header-card {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(13, 36, 64, 0.2);
  }
  
  .dashboard-header-card * {
    color: var(--white) !important;
  }
  
  .dashboard-header-card h3 {
    color: var(--white) !important;
  }
  
  .dashboard-header-card .text-white-50 {
    color: rgba(255, 255, 255, 0.8) !important;
  }
  
  /* Stat Card Backgrounds */
  .stat-card[style*="background"] {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%) !important;
  }
  
  /* All Card Headers - Blue Theme */
  .card-header {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%) !important;
    color: var(--white) !important;
  }
  
  .card-header * {
    color: var(--white) !important;
  }
  
  .card-header h1,
  .card-header h2,
  .card-header h3,
  .card-header h4,
  .card-header h5,
  .card-header h6,
  .card-header .font-weight-bold,
  .card-header .font-weight-semibold,
  .card-header div,
  .card-header span,
  .card-header label {
    color: var(--white) !important;
  }
  
  .card-header i,
  .card-header .fa,
  .card-header .fas,
  .card-header .far {
    color: var(--white) !important;
  }
  
  .card-header .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    color: var(--white) !important;
  }
  
  /* Card Bodies - White Background */
  .card-body {
    background-color: var(--white) !important;
    color: var(--text-dark);
  }
  
  /* Accordion Headers - Blue Theme */
  .accordion .card-header {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%) !important;
    color: var(--white) !important;
  }
  
  .accordion .card-header * {
    color: var(--white) !important;
  }
  
  .accordion .card-header button {
    color: var(--white) !important;
  }
  
  .accordion .card-header button i {
    color: var(--white) !important;
  }
  
  .accordion .card-header .badge {
    background: rgba(255, 255, 255, 0.25) !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    color: var(--white) !important;
  }
  
  /* Headings */
  h3, h5, h6 {
    color: var(--color-dark-navy);
  }
  
  /* Horizontal Rules */
  hr {
    border-color: var(--color-medium-blue);
    border-width: 1px;
    opacity: 0.3;
  }
  
  /* Form Elements */
  label {
    color: var(--color-dark-navy);
    font-weight: 600;
  }
  
  .form-control {
    background-color: var(--white);
    border-color: var(--color-medium-blue);
  }
  
  .form-control:focus {
    border-color: var(--color-dark-navy);
    background-color: var(--white);
  }
  
  /* Buttons in Header */
  .dashboard-header-card .btn-light {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: var(--white) !important;
  }
  
  .dashboard-header-card .btn-light:hover {
    background: rgba(255, 255, 255, 0.3) !important;
    color: var(--white) !important;
  }
  
  .dashboard-header-card .btn-outline-light {
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    color: var(--white) !important;
  }
  
  .dashboard-header-card .btn-outline-light:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    color: var(--white) !important;
  }
  
  /* Icon Colors */
  .text-primary i,
  i.text-primary {
    color: var(--color-dark-navy) !important;
  }
  
  /* Success/Info Colors using Green */
  .text-success {
    color: var(--color-dark-green) !important;
  }
  
  .bg-success {
    background-color: var(--color-dark-green) !important;
  }
  
  /* Table Headers in Accordion */
  .accordion .table thead th {
    background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%) !important;
    color: var(--white) !important;
  }
  
  /* Empty State */
  .text-center.text-muted {
    color: var(--text-muted) !important;
  }
  
  /* Department List Items */
  .accordion .card {
    background: var(--white);
    border: 1px solid var(--color-light-blue);
  }
  
  .accordion .card-body {
    background: var(--white);
  }
  
  /* Badge in Card Header */
  .card-header .badge-primary {
    background: rgba(255, 255, 255, 0.25) !important;
    color: var(--white) !important;
  }
</style>

<!-- Stat Cards Row -->
<div class="row mt-2 mobile-tight">
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: rgba(255,255,255,0.9);"><i class="fas fa-building fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.95);">Departments</div>
          <div class="stat-value" style="color: #ffffff;"><?php echo $deptCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--color-medium-blue) 0%, var(--color-light-blue) 100%); color: var(--color-dark-navy);">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3" style="color: var(--color-dark-navy); background: rgba(13, 36, 64, 0.1);"><i class="fas fa-book-open fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: var(--color-dark-navy);">Courses</div>
          <div class="stat-value" style="color: var(--color-dark-navy);"><?php echo $courseCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: rgba(255,255,255,0.9);"><i class="fas fa-users fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.95);">Following Students</div>
          <div class="stat-value" style="color: #ffffff;"><?php echo $studentCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--color-dark-green) 0%, #3d7a00 100%);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: rgba(255,255,255,0.9);"><i class="fas fa-briefcase fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.95);">Internships</div>
          <div class="stat-value" style="color: #ffffff;"><?php echo $internCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--color-medium-blue) 0%, var(--color-dark-navy) 100%);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: rgba(255,255,255,0.9);"><i class="fas fa-level-up-alt fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.95);">NVQ Level 4</div>
          <div class="stat-value" style="color: #ffffff;"><?php echo $nvq4Count; ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card shadow-sm" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%);">
      <div class="card-body d-flex align-items-center" style="color: #ffffff;">
        <div class="icon mr-3" style="color: rgba(255,255,255,0.9);"><i class="fas fa-level-up-alt fa-lg"></i></div>
        <div>
          <div class="stat-label" style="color: rgba(255,255,255,0.95);">NVQ Level 5</div>
          <div class="stat-value" style="color: #ffffff;"><?php echo $nvq5Count; ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<hr class="my-4">

<!-- Course-wise Students Table -->
<?php
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

  // Group courses under their departments
  $byDept = [];
  $deptTotals = [];
  foreach ($courseRows as $row) {
    $dn = $row['department_name'] ?? 'Unknown';
    if (!isset($byDept[$dn])) { $byDept[$dn] = []; $deptTotals[$dn] = 0; }
    $byDept[$dn][] = $row;
    $deptTotals[$dn] += (int)($row['total'] ?? 0);
  }
  
  // Compute gender counts per department
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
      <div class="card-header d-flex justify-content-between align-items-center py-3" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%) !important; border-bottom: 2px solid rgba(255,255,255,0.2);">
        <div class="font-weight-bold text-white" style="color: #ffffff !important; font-size: 1.1rem;">
          <i class="fas fa-chart-bar mr-2 text-white"></i> Department-wise Course Counts
        </div>
        <?php if (!empty($selectedYear)) : ?>
          <span class="badge" style="background: rgba(255, 255, 255, 0.25) !important; border: 1px solid rgba(255, 255, 255, 0.4) !important; color: #ffffff !important; padding: 0.5rem 1rem; font-weight: 600;">
            <i class="fas fa-calendar-alt mr-1 text-white"></i>Year: <?php echo htmlspecialchars($selectedYear); ?>
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
          <div class="accordion" id="deptCourseAcc" style="width: 100%;">
            <?php $i=0; foreach ($byDept as $deptName => $rows): $i++; $collapseId = 'dcoll'.$i; 
              $g = $genderByDept[$deptName] ?? ['male'=>0,'female'=>0];
              $courseCount = count($rows);
              $totalStudents = $deptTotals[$deptName] ?? 0;
            ?>
              <div class="card mb-2 border-0 shadow-sm" style="background: #ffffff; border-radius: 8px;">
                <div class="card-header py-3" id="h<?php echo $i; ?>" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%); border-radius: 8px; cursor: pointer;">
                  <h6 class="mb-0 d-flex justify-content-between align-items-center">
                    <button class="btn btn-link p-0 text-left" type="button" data-toggle="collapse" data-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>" style="color: #ffffff !important; font-weight: 600; text-decoration: none; width: 100%;">
                      <span style="font-size: 1rem; color: #ffffff;">
                        <i class="fas fa-building mr-2 text-white"></i>
                        <?php echo htmlspecialchars($deptName); ?>
                      </span>
                      <small class="ml-2 text-white" style="color: rgba(255,255,255,0.9) !important;">(<?php echo $courseCount; ?> <?php echo $courseCount == 1 ? 'Course' : 'Courses'; ?>)</small>
                    </button>
                    <span class="ml-2 d-flex align-items-center">
                      <span class="badge badge-primary mr-2" title="Total Students" style="background: rgba(255, 255, 255, 0.25) !important; border: 1px solid rgba(255, 255, 255, 0.4) !important; color: #ffffff !important; padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                        <i class="fas fa-users mr-1 text-white"></i><?php echo number_format($totalStudents); ?>
                      </span>
                      <span class="badge badge-primary mr-1" title="Male" style="background: rgba(255, 255, 255, 0.25) !important; border: 1px solid rgba(255, 255, 255, 0.4) !important; color: #ffffff !important; padding: 0.4rem 0.8rem;">
                        <i class="fas fa-male text-white"></i> <?php echo number_format($g['male']); ?>
                      </span>
                      <span class="badge" style="background: rgba(255, 255, 255, 0.25) !important; border: 1px solid rgba(255, 255, 255, 0.4) !important; color: #ffffff !important; padding: 0.4rem 0.8rem;" title="Female">
                        <i class="fas fa-female text-white"></i> <?php echo number_format($g['female']); ?>
                      </span>
                    </span>
                  </h6>
                </div>
                <div id="<?php echo $collapseId; ?>" class="collapse" data-parent="#deptCourseAcc">
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm table-striped mb-0" style="font-size: 0.9rem;">
                        <thead>
                          <tr>
                            <th style="width:50%; font-weight: 600; color: #ffffff; background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%); padding: 12px 15px;">Course</th>
                            <th class="text-right" style="width:15%; font-weight: 600; color: #ffffff; background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%); padding: 12px 15px;">Male</th>
                            <th class="text-right" style="width:15%; font-weight: 600; color: #ffffff; background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%); padding: 12px 15px;">Female</th>
                            <th class="text-right" style="width:20%; font-weight: 600; color: #ffffff; background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%); padding: 12px 15px;">Total</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($rows as $r): ?>
                            <tr>
                              <td style="color: var(--color-dark-navy); padding: 12px 15px;"><?php echo htmlspecialchars($r['course_name'] ?? ''); ?></td>
                              <td class="text-right font-weight-bold" style="color: var(--color-dark-navy); padding: 12px 15px;"><?php echo (int)($r['male'] ?? 0); ?></td>
                              <td class="text-right font-weight-bold" style="color: var(--color-dark-navy); padding: 12px 15px;"><?php echo (int)($r['female'] ?? 0); ?></td>
                              <td class="text-right font-weight-bold" style="color: var(--color-dark-navy); padding: 12px 15px;"><?php echo (int)($r['total'] ?? 0); ?></td>
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

<!-- Religion Distribution -->
<div class="row mt-3 mobile-tight align-items-center rel-filter">
  <div class="col-md-6 col-sm-12">
    <h5 class="mb-0" style="color: var(--color-dark-navy);"><i class="fas fa-praying-hands mr-2" style="color: var(--color-dark-navy);"></i>Religion-wise Students</h5>
    <small class="text-muted" style="color: var(--text-muted);">Live count by student religion</small>
  </div>
  <div class="col-md-6 col-sm-12 text-md-right mt-2 mt-md-0">
    <form class="form-inline justify-content-md-end" id="relFilterForm" style="display: none !important;">
      <label class="mr-2 small text-muted">Department</label>
      <select class="form-control form-control-sm" id="relDept" style="min-width: 200px;">
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

<!-- Gender Widgets -->
<div class="row mt-4 mobile-tight">
  <div class="col-12">
    <?php
    $gw_academic_year = $selectedYear;
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

<!-- Department-wise District Count Chart -->
<div class="row mt-4 mobile-tight">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header d-flex align-items-center justify-content-between py-2" style="background: linear-gradient(135deg, var(--color-dark-navy) 0%, var(--color-medium-blue) 100%) !important;">
        <div class="font-weight-semibold text-white" style="color: #ffffff !important;"><i class="fas fa-chart-line mr-1 text-white"></i> Department-wise District Counts</div>
        <?php if (!empty($selectedYear)) : ?>
          <span class="badge" style="background: rgba(255, 255, 255, 0.25) !important; border: 1px solid rgba(255, 255, 255, 0.4) !important; color: #ffffff !important; padding: 0.4rem 0.8rem; font-weight: 600;">Year: <?php echo htmlspecialchars($selectedYear); ?></span>
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
</div>

<!-- JavaScript Section -->
<script>
  (function() {
    'use strict';
    
    // ============================================
    // DASHBOARD ALIGNMENT SCRIPT
    // ============================================
    var resizeTimer = null;
    var sidebarToggleTimer = null;
    
    function ensureDashboardAlignment() {
      try {
        var containerFluid = document.querySelector('.page-content .container-fluid');
        if (!containerFluid) return;
        
        var windowWidth = window.innerWidth || document.documentElement.clientWidth;
        
        containerFluid.style.maxWidth = '100%';
        containerFluid.style.width = '100%';
        containerFluid.style.marginLeft = 'auto';
        containerFluid.style.marginRight = 'auto';
        containerFluid.style.boxSizing = 'border-box';
        
        // Responsive padding
        if (windowWidth >= 1400) {
          containerFluid.style.paddingLeft = '30px';
          containerFluid.style.paddingRight = '30px';
        } else if (windowWidth >= 992) {
          containerFluid.style.paddingLeft = '20px';
          containerFluid.style.paddingRight = '20px';
        } else if (windowWidth >= 768) {
          containerFluid.style.paddingLeft = '15px';
          containerFluid.style.paddingRight = '15px';
        } else if (windowWidth >= 576) {
          containerFluid.style.paddingLeft = '12px';
          containerFluid.style.paddingRight = '12px';
        } else {
          containerFluid.style.paddingLeft = '10px';
          containerFluid.style.paddingRight = '10px';
        }
        
        // Row margins
        var rows = containerFluid.querySelectorAll('.row');
        rows.forEach(function(row) {
          if (windowWidth >= 576) {
            row.style.marginLeft = '-15px';
            row.style.marginRight = '-15px';
          } else {
            row.style.marginLeft = '-10px';
            row.style.marginRight = '-10px';
          }
        });
        
      } catch(e) {
        if (console && console.warn) {
          console.warn('Error ensuring dashboard alignment:', e);
        }
      }
    }
    
    function handleResize() {
      if (resizeTimer) clearTimeout(resizeTimer);
      resizeTimer = setTimeout(ensureDashboardAlignment, 150);
    }
    
    function handleSidebarToggle() {
      if (sidebarToggleTimer) clearTimeout(sidebarToggleTimer);
      sidebarToggleTimer = setTimeout(ensureDashboardAlignment, 300);
    }
    
    function initDashboardAlignment() {
      ensureDashboardAlignment();
      window.removeEventListener('resize', handleResize);
      window.addEventListener('resize', handleResize, { passive: true });
      
      if (window.jQuery) {
        jQuery(document).off('click', '#show-sidebar, #close-sidebar, .sidebar-toggle')
          .on('click', '#show-sidebar, #close-sidebar, .sidebar-toggle', handleSidebarToggle);
        
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
    
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initDashboardAlignment);
    } else {
      initDashboardAlignment();
    }
    
    setTimeout(ensureDashboardAlignment, 100);
    setTimeout(ensureDashboardAlignment, 500);
    
    // ============================================
    // RELIGION DISTRIBUTION SCRIPT
    // ============================================
    function fetchReligion(dept) {
      var base = "<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>";
      var url = base + '/dashboard/religion_distribution_api.php' + (dept ? ('?department_id=' + encodeURIComponent(dept)) : '');
      return fetch(url).then(function(r) { return r.json(); });
    }
    
    function renderReligion(json) {
      var wrap = document.getElementById('religionCards');
      if (!wrap) return;
      wrap.innerHTML = '';
      
      if (!json || json.ok !== true || !json.data || !json.data.length) {
        wrap.innerHTML = '<div class="col-12"><div class="card rel-empty"><div class="card-body text-center">No religion data available.</div></div></div>';
        return;
      }
      
      var total = Number(json.total || 0) || 0;
      json.data.forEach(function(r, idx) {
        var name = String(r.religion || 'Unknown');
        var cnt = Number(r.cnt || 0) || 0;
        var pct = total > 0 ? Math.round((cnt / total) * 1000) / 10 : 0;
        var grad = 'rel-grad-' + (idx % 8);
        var icon = '<i class="fas fa-praying-hands"></i>';
        var col = document.createElement('div');
        col.className = 'col-lg-3 col-md-4 col-sm-6 col-12 mb-3';
        col.innerHTML = '<div class="card rel-card ' + grad + ' shadow-sm">' +
          '<div class="card-body rel-body">' +
          '<div><div class="rel-name">' + name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>' +
          '<div class="rel-percent">' + pct + '% of students</div></div>' +
          '<div class="text-right"><div class="rel-icon mb-2">' + icon + '</div>' +
          '<div class="rel-count">' + cnt + '</div></div></div></div>';
        wrap.appendChild(col);
      });
    }
    
    function loadReligion() {
      var dept = document.getElementById('relDept');
      var deptValue = dept ? dept.value : '';
      fetchReligion(deptValue).then(renderReligion).catch(function() { renderReligion(null); });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      var sel = document.getElementById('relDept');
      if (sel) sel.addEventListener('change', loadReligion);
      loadReligion();
    });
    
    // ============================================
    // DEPARTMENT DISTRICT LINE CHART
    // ============================================
    window.addEventListener('load', function() {
      var ctx = document.getElementById('deptDistrictLine');
      if (!ctx || !window.Chart) return;
      
      var isMobile = window.matchMedia('(max-width: 575.98px)').matches;
      var limit = isMobile ? 5 : 0;
      var url = "<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/dashboard/department_district_api.php?academic_year=<?php echo urlencode($selectedYear); ?>&limit=" + encodeURIComponent(limit);
      
      fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(json) {
          if (!json || json.status !== 'success') { throw new Error('API error'); }
          
          var labels = json.data.labels || [];
          var series = json.data.datasets || [];
          
          if (!labels.length || !series.length) {
            var container = ctx.parentNode;
            if (container) {
              var div = document.createElement('div');
              div.className = 'text-center text-muted small';
              div.textContent = 'No department-wise district data available for the selected academic year.';
              container.appendChild(div);
              ctx.style.display = 'none';
              var listEl0 = document.getElementById('deptDistrictList');
              if (listEl0) listEl0.innerHTML = '';
            }
            return;
          }
          
          // Populate district chips
          try {
            var listEl = document.getElementById('deptDistrictList');
            if (listEl) {
              var totalsByLabel = labels.map(function(_, idx) {
                var sum = 0;
                for (var k = 0; k < series.length; k++) {
                  sum += Number(series[k].data[idx] || 0);
                }
                return sum;
              });
              
              var idxs = labels.map(function(_, i) { return i; });
              idxs.sort(function(a, b) { return (totalsByLabel[b] - totalsByLabel[a]) || (a - b); });
              
              var rankByIdx = {};
              for (var r = 0; r < idxs.length; r++) {
                rankByIdx[idxs[r]] = r + 1;
              }
              
              listEl.innerHTML = labels.map(function(name, idx) {
                var safe = String(name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                var count = totalsByLabel[idx] || 0;
                var rank = rankByIdx[idx] || 999;
                var rankCls = rank === 1 ? ' top1' : (rank === 2 ? ' top2' : (rank === 3 ? ' top3' : ''));
                var medal = rank <= 3 ? '<span class="medal" aria-hidden="true"></span>' : '';
                var title = 'title="' + safe + ': ' + count + ' students' + (rank <= 3 ? (' (Rank ' + rank + ')') : '') + '"';
                return '<span class="chip' + rankCls + '" ' + title + '>' + medal + safe + '<span class="count">' + count + '</span></span>';
              }).join('');
            }
          } catch(e) { /* no-op */ }
          
          var palette = ['#4e73df', '#e74a3b', '#1cc88a', '#f6c23e', '#36b9cc', '#6f42c1', '#fd7e14', '#20c997'];
          var datasets = series.map(function(s, i) {
            var color = palette[i % palette.length];
            return {
              label: s.label,
              data: s.data,
              fill: false,
              borderColor: color,
              backgroundColor: color,
              borderWidth: isMobile ? 1.5 : 2,
              pointRadius: isMobile ? 0 : 3,
              pointRadius: isMobile ? 4 : 3,
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
              scales: {
                xAxes: [{
                  ticks: {
                    autoSkip: isMobile ? true : false,
                    maxRotation: isMobile ? 35 : 60,
                    minRotation: 0,
                    fontSize: isMobile ? 10 : 12,
                    callback: function(value, index) {
                      if (!isMobile) return value;
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
        .catch(function(e) {
          if (console && console.warn) console.warn('deptDistrictLine error', e);
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
  })();
</script>

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->

<?php endif; ?>
