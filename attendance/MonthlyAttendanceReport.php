<!--Block#1 start dont change the order-->
<?php 
$title="Monthly Attendance Report | SLGTI";    
include_once ("../config.php");
include_once ("../head.php");
include_once ("../menu.php");
include_once ("Attendancenav.php");
// Only HODs can use this page
require_roles(['HOD']);
?>
<!-- end dont change the order-->
<?php
$isExport = isset($_GET['export']) && $_GET['export']==='csv';

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

// Filters
$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$course = isset($_GET['course']) ? trim($_GET['course']) : '';

// Compute month range
$firstDay = $month.'-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

// Load department courses
$courses = [];
if ($deptCode !== '') {
  $dq = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con,$deptCode)."' ORDER BY course_name");
  if ($dq) { while($row=mysqli_fetch_assoc($dq)){ $courses[]=$row; } }
}

// Load students in scope
$students = [];
if ($deptCode !== '') {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
  if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
  $sql = "SELECT s.student_id, s.student_fullname, se.course_id, c.course_name
          FROM student_enroll se
          JOIN course c ON c.course_id = se.course_id
          JOIN student s ON s.student_id = se.student_id
          $where
          ORDER BY s.student_id ASC";
  $res = mysqli_query($con, $sql);
  if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[$r['student_id']]=$r; } }
}

$results = [];
$totalDays = 0;
if (!empty($students)) {
  // Build IN list
  $ids = [];
  foreach ($students as $sid => $info) {
    $ids[] = "'".mysqli_real_escape_string($con, $sid)."'";
  }
  $idList = implode(',', $ids);

  // Determine total distinct marked days in month for dept (support legacy and new module names)
  $qDays = mysqli_query($con, "SELECT COUNT(DISTINCT date) AS dcnt FROM attendance WHERE date BETWEEN '".$firstDay."' AND '".$lastDay."' AND (module_name='DAILY' OR module_name LIKE 'DAILY SLOT %' OR module_name LIKE 'DAILY-S%') AND student_id IN ($idList)");
  if ($qDays && ($rowd=mysqli_fetch_assoc($qDays))) { $totalDays = (int)$rowd['dcnt']; }

  // Per-student present days using max per date (support legacy and new module names)
  $q = mysqli_query($con, "SELECT a.student_id, COUNT(*) AS present_days FROM (SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE date BETWEEN '".$firstDay."' AND '".$lastDay."' AND (module_name='DAILY' OR module_name LIKE 'DAILY SLOT %' OR module_name LIKE 'DAILY-S%') AND student_id IN ($idList) GROUP BY student_id, date) a WHERE a.st=1 GROUP BY a.student_id");
  $presentMap = [];
  if ($q) { while($r=mysqli_fetch_assoc($q)){ $presentMap[$r['student_id']] = (int)$r['present_days']; } }

  foreach ($students as $sid=>$info) {
    $pd = isset($presentMap[$sid]) ? $presentMap[$sid] : 0;
    $pct = $totalDays>0 ? round(($pd/$totalDays)*100, 2) : 0.0;
    $results[] = [
      'student_id' => $sid,
      'student_fullname' => $info['student_fullname'],
      'course_id' => $info['course_id'],
      'course_name' => $info['course_name'],
      'present_days' => $pd,
      'total_days' => $totalDays,
      'percentage' => $pct,
    ];
  }
}

if ($isExport) {
  // CSV export
  if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="monthly_attendance_'.preg_replace('/[^0-9\-]/','',$month).'.csv"');
  $out = fopen('php://output','w');
  fputcsv($out, ['Student ID','Name','Course','Present Days','Total Days','Percentage']);
  foreach ($results as $r) {
    fputcsv($out, [
      $r['student_id'],
      $r['student_fullname'],
      $r['course_id'].' - '.$r['course_name'],
      $r['present_days'],
      $r['total_days'],
      $r['percentage'],
    ]);
  }
  fclose($out);
  exit;
}
?>
<div class="container" style="margin-top:30px">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Monthly Attendance Report (HOD)</strong>
      <form class="form-inline" method="get" action="">
        <label class="mr-2">Month</label>
        <input type="month" name="month" class="form-control mr-2" value="<?php echo htmlspecialchars($month); ?>" required>
        <label class="mr-2">Course</label>
        <select name="course" class="form-control mr-2">
          <option value="">-- All --</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo $course===$c['course_id']?'selected':''; ?>><?php echo htmlspecialchars($c['course_name']); ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary" type="submit">Load</button>
        <a class="btn btn-outline-success ml-2" href="?<?php echo http_build_query(['month'=>$month,'course'=>$course,'export'=>'csv']); ?>">Export CSV</a>
      </form>
    </div>
    <div class="card-body">
      <?php if ($deptCode===''): ?>
        <div class="alert alert-warning">Department not configured for your account. Please contact admin.</div>
      <?php else: ?>
        <div class="mb-2"><strong>Department:</strong> <?php echo htmlspecialchars($deptCode); ?> | <strong>Month:</strong> <?php echo htmlspecialchars($month); ?> | <strong>Total Marked Days:</strong> <?php echo (int)$totalDays; ?></div>
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead class="thead-light">
              <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Course</th>
                <th class="text-right">Present Days</th>
                <th class="text-right">Total Days</th>
                <th class="text-right">%</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($results)): ?>
                <?php foreach ($results as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                    <td><?php echo htmlspecialchars($r['student_fullname']); ?></td>
                    <td><?php echo htmlspecialchars($r['course_id'].' - '.$r['course_name']); ?></td>
                    <td class="text-right"><?php echo (int)$r['present_days']; ?></td>
                    <td class="text-right"><?php echo (int)$r['total_days']; ?></td>
                    <td class="text-right"><?php echo number_format($r['percentage'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
