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
        <img src="/MIS/student/get_student_image.php?Sid=<?php echo urlencode($username); ?>&t=<?php echo time(); ?>" alt="user image" class="img-thumbnail mb-3" style="width:160px;height:160px;object-fit:cover;">
        <h5 class="card-title mb-1"><?php echo htmlspecialchars(($p_title ? $p_title.'. ' : '').$p_fname); ?></h5>
        <div class="text-muted">ID: <?php echo htmlspecialchars($username); ?></div>
        <?php if ($p_nic): ?><div class="text-muted">NIC: <?php echo htmlspecialchars($p_nic); ?></div><?php endif; ?>
        <div class="mt-3">
          <a href="/MIS/student/Student_profile.php" class="btn btn-primary btn-sm">View Full Profile</a>
          <a href="/MIS/student/Student_profile.php#nav-modules" class="btn btn-outline-secondary btn-sm">My Modules</a>
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
  /* Lightweight gradients for stat cards */
  .stat-card { border: 0; color: #fff; }
  /* Requested palette: red, black, yellow, blue */
  .bg-red    { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); }
  .bg-black  { background: linear-gradient(135deg, #343a40 0%, #000000 100%); }
  .bg-yellow { background: linear-gradient(135deg, #f6c23e 0%, #e0a800 100%); color: #212529; }
  .bg-blue   { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
  .stat-card .icon { width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: rgba(255,255,255,0.2); }
  .bg-yellow .icon { background: rgba(0,0,0,0.15); }
  .stat-label { opacity: .9; font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; }
  .stat-value { font-size: 2rem; font-weight: 700; line-height: 1; }

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
  /* Ensure department titles in accordion headers are left-aligned and can wrap */
  .accordion .card-header .btn-link { text-align: left; white-space: normal; }
  @media (max-width: 575.98px) {
    .accordion .card-header h6 { justify-content: space-between; }
    .accordion .card-header .btn-link { text-align: left; }
  }
  /* Footer-specific tweaks: remove divider inside card-footer */
  .card-footer .chip-list { border-top: 0; padding-top: 0; }
  .card-footer .chip-list-label { margin-bottom: .25rem; }
  @media (max-width: 575.98px) {
    .chip { font-size: 11px; padding: 2px 8px; }
    .chip .count { min-width: 16px; height: 16px; font-size: 10px; }
  }
</style>
<div class="row mt-1">
  <div class="col-12">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
      <div class="mb-2 mb-md-0">
        <h4 class="mb-0 font-weight-bold text-dark text-left text-md-left">Sri Lanka German Training Institute - MIS</h4>
        <div class="text-muted small text-left text-md-left">Dashboard Overview</div>
      </div>
      <div class="ml-md-auto">
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
    <hr class="mt-1 mb-2">
  </div>
</div>

<div class="row mt-2 mobile-tight">
  <div class="col-md-4 col-sm-6 col-12 mb-2">
    <div class="card stat-card bg-black shadow-sm">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-building fa-lg"></i></div>
        <div>
          <div class="stat-label">Departments</div>
          <div class="stat-value"><?php echo $deptCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-red shadow-sm">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-book-open fa-lg"></i></div>
        <div>
          <div class="stat-label">Courses</div>
          <div class="stat-value"><?php echo $courseCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-yellow shadow-sm">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-calendar-alt fa-lg"></i></div>
        <div>
          <div class="stat-label">Academic Years</div>
          <div class="stat-value"><?php echo $acadCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-blue shadow-sm">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-users fa-lg"></i></div>
        <div>
          <div class="stat-label">Following Students</div>
          <div class="stat-value"><?php echo $studentCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-teal shadow-sm" style="background: linear-gradient(135deg,#20c997 0%, #0f766e 100%);">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-user-check fa-lg"></i></div>
        <div>
          <div class="stat-label">Active Students</div>
          <div class="stat-value"><?php echo $activeCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-orange shadow-sm" style="background: linear-gradient(135deg,#fd7e14 0%, #c05621 100%);">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-briefcase fa-lg"></i></div>
        <div>
          <div class="stat-label">Internships</div>
          <div class="stat-value"><?php echo $internCount; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-green shadow-sm" style="background: linear-gradient(135deg,#1cc88a 0%, #13855c 100%);">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-level-up-alt fa-lg"></i></div>
        <div>
          <div class="stat-label">NVQ Level 4</div>
          <div class="stat-value"><?php echo $nvq4Count; ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-purple shadow-sm" style="background: linear-gradient(135deg,#6f42c1 0%, #4b2f88 100%);">
      <div class="card-body d-flex align-items-center">
        <div class="icon mr-3"><i class="fas fa-level-up-alt fa-lg"></i></div>
        <div>
          <div class="stat-label">NVQ Level 5</div>
          <div class="stat-value"><?php echo $nvq5Count; ?></div>
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
      <div class="card-header bg-white d-flex justify-content-between py-2">
        <div class="font-weight-semibold"><i class="fas fa-sitemap mr-1 text-primary"></i> Department-wise Course Counts</div>
        <?php if (!empty($selectedYear)) : ?>
          <span class="badge badge-light">Year: <?php echo htmlspecialchars($selectedYear); ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (empty($byDept)): ?>
          <div class="text-center text-muted small">No data available<?php echo $selectedYear ? ' for the selected year' : ''; ?>.</div>
        <?php else: ?>
          <div class="accordion" id="deptCourseAcc">
            <?php $i=0; foreach ($byDept as $deptName => $rows): $i++; $collapseId = 'dcoll'.$i; ?>
              <div class="card mb-2 border-0">
                <div class="card-header bg-light py-2" id="h<?php echo $i; ?>">
                  <h6 class="mb-0 d-flex justify-content-between align-items-center">
                    <button class="btn btn-link p-0" type="button" data-toggle="collapse" data-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $i===1?'true':'false'; ?>" aria-controls="<?php echo $collapseId; ?>">
                      <?php echo htmlspecialchars($deptName); ?>
                    </button>
                    <span>
                      <?php $g = $genderByDept[$deptName] ?? ['male'=>0,'female'=>0]; ?>
                      <span class="badge badge-primary mr-1" title="Male"><i class="fas fa-male"></i> <?php echo number_format($g['male']); ?></span>
                      <span class="badge" style="background:#e83e8c;color:#fff" title="Female"><i class="fas fa-female"></i> <?php echo number_format($g['female']); ?></span>
                    </span>
                  </h6>
                </div>
                <div id="<?php echo $collapseId; ?>" class="collapse <?php echo $i===1?'show':''; ?>" data-parent="#deptCourseAcc">
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm table-striped mb-0">
                        <thead class="thead-light">
                          <tr>
                            <th style="width:70%;">Course</th>
                            <th class="text-right" style="width:15%;">Male</th>
                            <th class="text-right" style="width:15%;">Female</th>
                            <th class="text-right" style="width:30%;">Students</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($rows as $r): ?>
                            <tr>
                              <td><?php echo htmlspecialchars($r['course_name'] ?? ''); ?></td>
                              <td class="text-right font-weight-bold"><?php echo (int)($r['male'] ?? 0); ?></td>
                              <td class="text-right font-weight-bold"><?php echo (int)($r['female'] ?? 0); ?></td>
                              <td class="text-right font-weight-bold"><?php echo (int)($r['total'] ?? 0); ?></td>
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
    <form class="form-inline justify-content-md-end" id="relFilterForm">
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
 
 
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->

<?php endif; ?>