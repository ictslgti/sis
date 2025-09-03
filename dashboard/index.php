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

<div class="row mt-3">
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
// Students who accepted Code of Conduct
if ($rs = mysqli_query($con, "SELECT COUNT(student_id) AS cnt FROM student WHERE student_conduct_accepted_at IS NOT NULL")) {
  if ($r = mysqli_fetch_assoc($rs)) { $studentCount = (int)$r['cnt']; }
  mysqli_free_result($rs);
}
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
</style>

<div class="row mt-3">
  <div class="col-md-4 col-sm-6 col-12 mb-3">
    <div class="card stat-card bg-red shadow-sm">
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
    <div class="card stat-card bg-black shadow-sm">
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
          <div class="stat-label">Students</div>
          <div class="stat-value"><?php echo $studentCount; ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<hr>




<!-- Removed academic year dropdown and bar chart as requested -->






<div class="row mt-4">
    <div class="col-12">
        <?php
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