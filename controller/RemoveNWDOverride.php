<?php
// RemoveNWDOverride.php
// Removes NWD override (attendance_status = -1) for students in scope for the given date

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

$dt = mysqli_real_escape_string($con, $date);

// Check if module_name column exists
$hasModuleName = false;
if ($chk = mysqli_query($con, "SHOW COLUMNS FROM `attendance` LIKE 'module_name'")) {
  $hasModuleName = (mysqli_num_rows($chk) === 1);
  mysqli_free_result($chk);
}

// Build DELETE with joins to scope by department/course
$where = "c.department_id='".mysqli_real_escape_string($con,$dept)."'";
if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
$where .= " AND se.student_enroll_status IN ('Following','Active')";

$del = "DELETE a FROM attendance a\n        JOIN student s ON s.student_id=a.student_id\n        JOIN student_enroll se ON se.student_id=s.student_id\n        JOIN course c ON c.course_id=se.course_id\n        WHERE a.date='$dt' AND a.attendance_status=-1 AND $where";
if ($hasModuleName) { $del .= " AND a.module_name='".mysqli_real_escape_string($con,'NWD')."'"; }

if (!mysqli_query($con, $del)) {
  $err = mysqli_error($con);
  redirect_back(['err'=>'db','month'=>$month,'department_id'=>$dept,'course_id'=>$course,'focus_date'=>$date]);
}

redirect_back(['ok'=>'1','month'=>$month,'department_id'=>$dept,'course_id'=>$course,'focus_date'=>$date]);
