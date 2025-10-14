<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Only Admin can unlock
if (!is_role('ADM')) { http_response_code(403); echo 'Forbidden'; exit; }

// Inputs
$month = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
$departmentId = isset($_POST['department_id']) ? trim($_POST['department_id']) : '';
$courseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';

if ($departmentId === '') {
  // Fallback to session department if not provided
  $departmentId = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
}
if ($departmentId === '') { header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'err'=>'nodept'])); exit; }

$firstDay = $month.'-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

// Ensure column exists
@mysqli_query($con, "ALTER TABLE `attendance` ADD COLUMN `approved_status` VARCHAR(64) NULL");

// Collect students in scope
$students = [];
$where = "WHERE c.department_id='".mysqli_real_escape_string($con,$departmentId)."' AND se.student_enroll_status IN ('Following','Active')";
if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
$sql = "SELECT s.student_id FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where";
$res = mysqli_query($con, $sql);
if ($res) { while ($r=mysqli_fetch_assoc($res)) { $students[] = $r['student_id']; } }

if (empty($students)) { header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'department_id'=>$departmentId,'err'=>'nostudents'])); exit; }

$idList = implode(',', array_map(function($sid){ return "'".mysqli_real_escape_string($GLOBALS['con'],$sid)."'"; }, $students));

// Clear approved_status for all attendance rows in range
$sqlUpd = "UPDATE attendance SET approved_status=NULL WHERE student_id IN ($idList) AND `date` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'";
if (!mysqli_query($con, $sqlUpd)) {
  header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'department_id'=>$departmentId,'err'=>'dberror','errm'=>mysqli_error($con)]));
  exit;
}

header('Location: '.$base.'/attendance/MonthlyAttendanceReport.php?'.http_build_query(['month'=>$month,'course_id'=>$courseId,'department_id'=>$departmentId,'ok'=>1,'unlocked'=>1]));
