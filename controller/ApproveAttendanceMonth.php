<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Only HOD can approve
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if ($role !== 'HOD') { http_response_code(403); echo 'Forbidden'; exit; }

// Resolve department from session
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}
if ($deptCode === '') { header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?err=nodept'); exit; }

$month = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
$courseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';

$firstDay = $month.'-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

// Ensure column exists
@mysqli_query($con, "ALTER TABLE `attendance` ADD COLUMN `approved_status` VARCHAR(64) NULL");

// Collect students in scope
$students = [];
$where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."' AND se.student_enroll_status IN ('Following','Active')";
if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
$sql = "SELECT s.student_id FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where";
$res = mysqli_query($con, $sql);
if ($res) { while ($r=mysqli_fetch_assoc($res)) { $students[] = $r['student_id']; } }

if (empty($students)) { header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'err'=>'nostudents'])); exit; }

$idList = implode(',', array_map(function($sid){ return "'".mysqli_real_escape_string($GLOBALS['con'],$sid)."'"; }, $students));

// Set approved_status for all attendance rows for this scope and month
// Only update rows within date range for these students
$sqlUpd = "UPDATE attendance SET approved_status='HOD is Approved' WHERE student_id IN ($idList) AND `date` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'";
if (!mysqli_query($con, $sqlUpd)) {
  header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'err'=>'dberror','errm'=>mysqli_error($con)]));
  exit;
}

header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'ok'=>1,'approved'=>1]));
