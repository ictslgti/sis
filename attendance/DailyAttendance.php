<!--Block#1 start dont change the order-->
<?php 
$title="Daily Attendance | SLGTI";    
include_once ("../config.php");
include_once ("../head.php");
include_once ("../menu.php");
include_once ("Attendancenav.php");
// Only HODs can use this page
require_roles(['HOD']);
?>
<!-- end dont change the order-->

<?php
// Resolve department for HOD
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '') {
  // Try to resolve from staff table
  if (!empty($_SESSION['user_name'])) {
    $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
    $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
    if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
  }
}

// Filters (single-slot system)
$date = isset($_GET['date']) && $_GET['date']!=='' ? $_GET['date'] : date('Y-m-d');
// Force single slot
$slot = 1;
$course = isset($_GET['course']) ? trim($_GET['course']) : '';

// Load department courses
$courses = [];
if ($deptCode !== '') {
  $dq = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con,$deptCode)."' ORDER BY course_name");
  if ($dq) { while($row=mysqli_fetch_assoc($dq)){ $courses[]=$row; } }
}

// Detect if optional conduct acceptance column exists
$hasConduct = false;
if ($chk = mysqli_query($con, "SHOW COLUMNS FROM `student` LIKE 'student_conduct_accepted_at'")) {
  $hasConduct = (mysqli_num_rows($chk) === 1);
  mysqli_free_result($chk);
}

// Load students (scoped to department and optional course)
$students = [];
if ($deptCode !== '') {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
  if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
  // Only active/following students
  $where .= " AND se.student_enroll_status IN ('Following','Active')";
  // Exclude students who have NOT accepted conduct (when column exists)
  if ($hasConduct) { $where .= " AND s.student_conduct_accepted_at IS NOT NULL"; }

  $sql = "SELECT s.student_id, s.student_fullname, se.course_id, c.course_name".
         "\n          FROM student_enroll se\n          JOIN course c ON c.course_id = se.course_id\n          JOIN student s ON s.student_id = se.student_id\n          $where\n          ORDER BY s.student_id ASC";
  $res = mysqli_query($con, $sql);
  if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[]=$r; } }
}

// Load already marked attendance for this date+slot for quick pre-check
$presentMap = [];
if (!empty($students)) {
  $ids = [];
  foreach ($students as $r) {
    $ids[] = "'" . mysqli_real_escape_string($con, $r['student_id']) . "'";
  }
  $idList = implode(',', $ids);
  if ($idList !== '') {
    $dt = mysqli_real_escape_string($con, $date);
    // Match specific slot using module_name = 'DAILY-S<slot>' to avoid cross-slot duplicates
    $mn = 'DAILY-S' . (int)$slot;
    $q = mysqli_query($con, "SELECT student_id, attendance_status FROM attendance WHERE date='$dt' AND module_name='" . mysqli_real_escape_string($con, $mn) . "' AND student_id IN ($idList)");
    if ($q) { while($row=mysqli_fetch_assoc($q)){ $presentMap[$row['student_id']] = (int)$row['attendance_status']===1; } }
  }
}
?>

<div class="container" style="margin-top:30px">
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <div><strong><?php echo htmlspecialchars($deptCode); ?></strong></div>
        <div>
          <form class="form-inline" method="get" action="">
            <label class="mr-2">Date</label>
            <input type="date" name="date" class="form-control mr-2" value="<?php echo htmlspecialchars($date); ?>" required>
            <!-- Single slot only; slot selector removed -->
            <label class="mr-2">Course</label>
            <select name="course" class="form-control mr-2">
              <option value="">-- All --</option>
              <?php foreach($courses as $c): ?>
                <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo $course===$c['course_id']?'selected':''; ?>><?php echo htmlspecialchars($c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary" type="submit">Load</button>
          </form>
        </div>
      </div>
    </div>
    <div class="card-body">
      <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success">Attendance saved successfully.</div>
      <?php endif; ?>
      <?php if (isset($_GET['err'])): ?>
        <div class="alert alert-danger">Operation failed. Code: <?php echo htmlspecialchars($_GET['err']); ?></div>
      <?php endif; ?>
      <?php if ($deptCode===''): ?>
        <div class="alert alert-warning">Department not configured for your account. Please contact admin.</div>
      <?php else: ?>
        <form method="post" action="<?php echo APP_BASE; ?>/controller/DailyAttendanceSave.php">
          <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
          <!-- Single slot only; no slot field needed -->
          <input type="hidden" name="course" value="<?php echo htmlspecialchars($course); ?>">
          <div class="mb-2">
            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAll(true)">Mark All Present</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Unmark All</button>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Present</th>
                  <th>Student ID</th>
                  <th>Student Name</th>
                  <th>Course</th>
                  <?php if ($hasConduct): ?>
                  <th>Conduct</th>
                  <?php endif; ?>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($students)): ?>
                  <?php foreach($students as $s): $sid=$s['student_id']; $isP = isset($presentMap[$sid]) ? $presentMap[$sid] : false; $accepted = ($hasConduct && !empty($s['student_conduct_accepted_at'])); ?>
                    <tr>
                      <td>
                        <input type="checkbox" name="present[]" value="<?php echo htmlspecialchars($sid); ?>" <?php echo $isP?'checked':''; ?> <?php echo ($hasConduct && !$accepted)?'disabled title="Not accepted"':''; ?>>
                      </td>
                      <td><?php echo htmlspecialchars($sid); ?></td>
                      <td><?php echo htmlspecialchars($s['student_fullname']); ?></td>
                      <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                      <?php if ($hasConduct): ?>
                      <td>
                        <?php if ($accepted): ?>
                          <span class="badge badge-success">Accepted</span>
                        <?php else: ?>
                          <span class="badge badge-warning">Not accepted</span>
                        <?php endif; ?>
                      </td>
                      <?php endif; ?>
                      <td>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo APP_BASE; ?>/student/Student_profile.php?Sid=<?php echo urlencode($sid); ?>" target="_blank" rel="noopener">
                          <i class="fas fa-user"></i> View Profile
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted">No students found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-2">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Attendance</button>
          </div>
        </form>
        <script>
          function toggleAll(state){
            document.querySelectorAll('input[type="checkbox"][name="present[]"]').forEach(cb=>cb.checked=state);
          }
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>

<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
