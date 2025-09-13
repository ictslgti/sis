<?php
// SetNWDOverride.php
// Marks a given date as Non-Working Day (attendance_status = -1) for students in scope

require_once __DIR__ . '/../config.php';
require_roles(['HOD','IN3','SAO','ADM']);

function redirect_back($params = []){
  $base = APP_BASE . '/attendance/MonthlyAttendanceReport.php';
  $qs = [];
  foreach ($params as $k=>$v) { $qs[] = urlencode($k).'='.urlencode($v); }
  header('Location: '.$base.(empty($qs)?'':'?'.implode('&',$qs)));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_back(['err' => 'method']);
}

$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$dept = isset($_POST['department_id']) ? trim($_POST['department_id']) : '';
$course = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$month = isset($_POST['month']) ? trim($_POST['month']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { redirect_back(['err'=>'date']); }
if ($dept==='') { redirect_back(['err'=>'dept']); }

// Build student scope
$where = "WHERE c.department_id='".mysqli_real_escape_string($con,$dept)."'";
if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
$where .= " AND se.student_enroll_status IN ('Following','Active')";
$sql = "SELECT s.student_id\n        FROM student_enroll se\n        JOIN course c ON c.course_id = se.course_id\n        JOIN student s ON s.student_id = se.student_id\n        $where";
$rs = mysqli_query($con, $sql);
$ids = [];
if ($rs) { while($r=mysqli_fetch_assoc($rs)){ $ids[] = $r['student_id']; } }
if (empty($ids)) { redirect_back(['ok'=>'1','note'=>'no_students','month'=>$month,'department_id'=>$dept,'course_id'=>$course,'focus_date'=>$date]); }

$dt = mysqli_real_escape_string($con, $date);
$now = date('Y-m-d H:i:s');

// Detect optional columns in attendance table
$hasModuleName = false; $hasCreatedAt = false;
if ($chk = mysqli_query($con, "SHOW COLUMNS FROM `attendance` LIKE 'module_name'")) { $hasModuleName = (mysqli_num_rows($chk) === 1); mysqli_free_result($chk); }
if ($chk2 = mysqli_query($con, "SHOW COLUMNS FROM `attendance` LIKE 'created_at'")) { $hasCreatedAt = (mysqli_num_rows($chk2) === 1); mysqli_free_result($chk2); }

mysqli_begin_transaction($con);
try {
  // 1) UPDATE existing attendance rows for this date and scope to -1 (NWD) without deleting
  $where = "c.department_id='".mysqli_real_escape_string($con,$dept)."'";
  if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
  $where .= " AND se.student_enroll_status IN ('Following','Active')";
  $upd = "UPDATE attendance a\n          JOIN student s ON s.student_id=a.student_id\n          JOIN student_enroll se ON se.student_id=s.student_id\n          JOIN course c ON c.course_id=se.course_id\n          SET a.attendance_status=-1";
  if ($hasModuleName) { $upd .= ", a.module_name='".mysqli_real_escape_string($con,'NWD')."'"; }
  if ($hasCreatedAt) { $upd .= ", a.created_at='".mysqli_real_escape_string($con,$now)."'"; }
  $upd .= " WHERE a.`date`='$dt' AND $where";
  mysqli_query($con, $upd);

  // Build insert columns dynamically (escape reserved names like `date`)
  $cols = ['student_id','date','attendance_status'];
  if ($hasModuleName) $cols[] = 'module_name';
  if ($hasCreatedAt) $cols[] = 'created_at';
  $colsSql = array_map(function($c){ return "`".$c."`"; }, $cols);
  $colList = '('.implode(', ', $colsSql).')';

  // Determine students who still do not have any attendance row for this date
  $presentSet = [];
  $inAll = implode(',', array_map(function($x) use ($con){ return "'".mysqli_real_escape_string($con,$x)."'"; }, $ids));
  $qexist = mysqli_query($con, "SELECT DISTINCT student_id FROM attendance WHERE `date`='$dt' AND student_id IN ($inAll)");
  if ($qexist) { while($r=mysqli_fetch_assoc($qexist)){ $presentSet[$r['student_id']] = true; } }
  $idsToInsert = array_values(array_filter($ids, function($sid) use ($presentSet){ return !isset($presentSet[$sid]); }));

  // Prepare values in chunks to avoid packet size limits
  $chunkSize = 500;
  for ($i=0; $i<count($idsToInsert); $i+=$chunkSize) {
    $values = [];
    $slice = array_slice($idsToInsert, $i, $chunkSize);
    foreach ($slice as $sid) {
      $sidEsc = mysqli_real_escape_string($con, $sid);
      $row = ["'$sidEsc'", "'$dt'", "-1"];
      if ($hasModuleName) { $row[] = "'".mysqli_real_escape_string($con,'NWD')."'"; }
      if ($hasCreatedAt) { $row[] = "'".mysqli_real_escape_string($con,$now)."'"; }
      $values[] = '('.implode(',', $row).')';
    }
    $ins = "INSERT INTO attendance $colList VALUES ".implode(',', $values);
    if (!mysqli_query($con, $ins)) { throw new Exception('insert'); }
  }

  mysqli_commit($con);
  redirect_back(['ok'=>'1','month'=>$month,'department_id'=>$dept,'course_id'=>$course,'focus_date'=>$date]);
} catch (Exception $e) {
  mysqli_rollback($con);
  $err = mysqli_error($con);
  $code = mysqli_errno($con);
  $msg = substr($err ?: $e->getMessage(), 0, 180);
  redirect_back(['err'=>'db','errm'=>($code?($code.': '):'').$msg,'month'=>$month,'department_id'=>$dept,'course_id'=>$course,'focus_date'=>$date]);
}
