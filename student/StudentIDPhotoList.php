<?php
// BLOCK#1 START DON'T CHANGE THE ORDER
$title = "Student ID Photos (Dept) | SLGTI";
include_once("../config.php");

// Access control (align with StudentIDPhoto.php)
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','MA2'])) {
  include_once("../head.php");
  include_once("../menu.php");
  http_response_code(403);
  echo '<div class="alert alert-danger m-3">Access denied. Admins only.</div>';
  include_once("../footer.php");
  exit;
}

include_once("../head.php");
include_once("../menu.php");
// END DON'T CHANGE THE ORDER

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$dept = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$ay = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$course = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$students = [];

// Load department list
$departments = [];
$res = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
if ($res) { while ($row = mysqli_fetch_assoc($res)) { $departments[] = $row; } }

// Optionally load courses for selected department
$courses = [];
if ($dept !== '') {
  $cs = mysqli_prepare($con, "SELECT course_id, course_name FROM course WHERE department_id=? ORDER BY course_name");
  mysqli_stmt_bind_param($cs, 's', $dept);
  mysqli_stmt_execute($cs);
  $cr = mysqli_stmt_get_result($cs);
  if ($cr) { while ($r = mysqli_fetch_assoc($cr)) { $courses[] = $r; } }
  mysqli_stmt_close($cs);
}

// Build listing only if department selected
if ($dept !== '') {
  // Latest active enrollment per student in the selected department (optionally by course and ay)
  $where = " WHERE c.department_id=? AND se.student_enroll_status IN ('Following')";
  $types = 's';
  $params = [$dept];
  if ($course !== '') { $where .= " AND se.course_id=?"; $types .= 's'; $params[] = $course; }
  if ($ay !== '') { $where .= " AND se.academic_year=?"; $types .= 's'; $params[] = $ay; }

  $sql = "
    SELECT s.student_id, s.student_fullname, se.course_id, c.course_name, sip.id_photo
    FROM student s
    JOIN (
      SELECT se1.student_id, se1.course_id, se1.academic_year
      FROM student_enroll se1
      JOIN course c1 ON c1.course_id=se1.course_id
      $where
      AND NOT EXISTS (
        SELECT 1 FROM student_enroll se2
        WHERE se2.student_id=se1.student_id
          AND se2.student_enroll_status IN ('Following')
          " . ($course !== '' ? " AND se2.course_id=se1.course_id" : "") . "
          " . ($ay !== '' ? " AND se2.academic_year>=se1.academic_year" : " AND se2.academic_year>se1.academic_year") . "
      )
    ) t ON t.student_id = s.student_id
    JOIN student_enroll se ON se.student_id=t.student_id AND se.course_id=t.course_id AND se.academic_year=t.academic_year
    JOIN course c ON c.course_id=se.course_id
    LEFT JOIN student_idphoto sip ON sip.student_id=s.student_id
    ORDER BY s.student_id
  ";

  // Prepare and bind dynamic params
  $stmt = mysqli_prepare($con, $sql);
  if ($stmt) {
    // Bind department (+ optional course, ay) to the subquery's WHERE
    // Note: We need to bind the same params as constructed for $where
    $bind = [$stmt, $types];
    foreach ($params as $p) { $bind[] = $p; }
    // mysqli_stmt_bind_param requires references
    foreach($bind as $k=>$v){ $bind[$k] = &$bind[$k]; }
    call_user_func_array('mysqli_stmt_bind_param', $bind);

    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($rs) { while ($row = mysqli_fetch_assoc($rs)) { $students[] = $row; } }
    mysqli_stmt_close($stmt);
  } else {
    $errors[] = 'Query failed: ' . h(mysqli_error($con));
  }
}
?>

<style>
.card-img-top { width: 100%; aspect-ratio: 3 / 4; object-fit: cover; }
.card-header { font-weight: 600; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; }
.badge-muted { background: #f0f0f0; color: #555; }
</style>

<div class="container mt-3">
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-id-card"></i> Student ID Photos - Department Wise</span>
    </div>
    <div class="card-body">
      <?php if (!empty($errors)) { echo '<div class="alert alert-danger py-2">'.h(implode(' | ', $errors)).'</div>'; } ?>
      <form method="get" class="form-inline">
        <div class="form-row w-100">
          <div class="form-group col-md-4">
            <label for="department_id">Department</label>
            <select class="form-control" id="department_id" name="department_id" required>
              <option value="">-- Select Department --</option>
              <?php foreach ($departments as $d) { $sel = $dept===$d['department_id']?'selected':''; ?>
                <option value="<?php echo h($d['department_id']); ?>" <?php echo $sel; ?>><?php echo h($d['department_name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label for="course_id">Course</label>
            <select class="form-control" id="course_id" name="course_id" <?php echo $dept===''?'disabled':''; ?>>
              <option value="">All Courses</option>
              <?php foreach ($courses as $c) { $sel = $course===$c['course_id']?'selected':''; ?>
                <option value="<?php echo h($c['course_id']); ?>" <?php echo $sel; ?>><?php echo h($c['course_name']); ?></option>
              <?php } ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label for="academic_year">Academic Year</label>
            <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?php echo h($ay); ?>" placeholder="e.g., 2024/2025">
          </div>
          <div class="form-group col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-block">Filter</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php if ($dept==='') { ?>
    <div class="alert alert-info">Select a department to view student ID photos.</div>
  <?php } else { ?>
    <div class="grid">
      <?php foreach ($students as $st) {
        $sid = $st['student_id'];
        $name = $st['student_fullname'];
        $courseName = $st['course_name'];
        $imgSrc = '';
        if (!empty($st['id_photo'])) {
          $imgSrc = 'data:image/jpeg;base64,' . base64_encode($st['id_photo']);
        } else {
          $base = defined('APP_BASE') ? APP_BASE : '';
          $imgSrc = $base . '/img/profile/user.png';
        }
      ?>
        <div class="card shadow-sm">
          <div class="card-header text-center"><?php echo h($sid); ?></div>
          <img class="card-img-top" src="<?php echo h($imgSrc); ?>" alt="ID Photo">
          <div class="card-footer text-center">
            <div class="font-weight-bold"><?php echo h($name ?: $sid); ?></div>
            <div class="text-muted small"><?php echo h($courseName); ?></div>
          </div>
        </div>
      <?php } ?>
    </div>
    <?php if (!$students) { ?>
      <div class="alert alert-warning mt-3">No students found for the selected filters.</div>
    <?php } ?>
  <?php } ?>
</div>

<script>
// Enable/disable course dropdown based on department selection
(function(){
  const dept = document.getElementById('department_id');
  const course = document.getElementById('course_id');
  if (dept && course) {
    dept.addEventListener('change', function(){
      course.disabled = !this.value;
      if (!this.value) { course.value=''; }
      this.form && this.form.submit();
    });
  }
})();
</script>

<!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
<?php include_once("../footer.php"); ?>
<!-- END DON'T CHANGE THE ORDER -->
