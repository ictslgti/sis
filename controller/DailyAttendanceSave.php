<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Only HODs can save daily attendance
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'HOD') {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

// Resolve HOD department
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r = mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}
if ($deptCode === '') {
  header('Location: '.$base.'/attendance/DailyAttendance.php?err=nodept');
  exit;
}

// Inputs
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$slot = 1; // single-slot system
$course = isset($_POST['course']) ? trim($_POST['course']) : '';
$present = isset($_POST['present']) && is_array($_POST['present']) ? $_POST['present'] : [];

if ($date === '' || !$slot) {
  header('Location: '.$base.'/attendance/DailyAttendance.php?err=badreq');
  exit;
}

// Use per-slot module name to avoid cross-slot duplicates (single slot = 1)
$module_name = 'DAILY-S1';

// Load target students for this save (dept + optional course, active statuses)
$where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."' AND se.student_enroll_status IN ('Following','Active')";
if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
$sql = "SELECT s.student_id FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where ORDER BY s.student_id";
$students = [];
$res = mysqli_query($con, $sql);
if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[] = $r['student_id']; } }

// Build a set for present
$presentSet = [];
foreach ($present as $sid) { $presentSet[(string)$sid] = true; }

$staff_name = '';
if (!empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $qr = mysqli_query($con, "SELECT staff_name FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($qr && ($rr=mysqli_fetch_assoc($qr))) { $staff_name = $rr['staff_name']; }
}

// Best-effort: ensure unique key exists to prevent true duplicates
// Suppress error if already exists
@mysqli_query($con, "ALTER TABLE `attendance` ADD UNIQUE KEY `uniq_student_date_module` (`student_id`,`date`,`module_name`)");

mysqli_begin_transaction($con);
$ok = true;
// Use single UPSERT to guarantee idempotency per student/date/module
$stUpsert = mysqli_prepare(
  $con,
  "INSERT INTO attendance (attendance_status, staff_name, student_id, date, module_name)
   VALUES (?,?,?,?,?)
   ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status), staff_name=VALUES(staff_name)"
);
if (!$stUpsert) { $ok = false; }

if ($ok) {
  foreach ($students as $sid) {
    $presentVal = isset($presentSet[(string)$sid]) ? 1 : 0;
    $sid_s = (string)$sid;
    mysqli_stmt_bind_param($stUpsert, 'issss', $presentVal, $staff_name, $sid_s, $date, $module_name);
    if (!mysqli_stmt_execute($stUpsert)) { $ok = false; break; }
  }
}

if ($stUpsert) { mysqli_stmt_close($stUpsert); }

if ($ok) {
  mysqli_commit($con);
  // Redirect back preserving filters
  $qs = http_build_query(['date'=>$date,'course'=>$course,'ok'=>1]);
  header('Location: '.$base.'/attendance/DailyAttendance.php?'.$qs);
} else {
  mysqli_rollback($con);
  $qs = http_build_query(['date'=>$date,'course'=>$course,'err'=>'dberror']);
  header('Location: '.$base.'/attendance/DailyAttendance.php?'.$qs);
}
