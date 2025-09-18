<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if (!in_array($role, ['HOD','IN3'], true)) { http_response_code(403); echo 'Forbidden'; exit; }

function back($params){ global $base; header('Location: '.$base.'/attendance/BulkMonthlyMark.php?'.http_build_query($params)); exit; }

// Resolve department
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}
if ($deptCode === '') { back(['err'=>'nodept']); }

$month = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
$courseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$includeWeekends = !empty($_POST['include_weekends']) ? 1 : 0;
$respectHolidays = !empty($_POST['respect_holidays']) ? 1 : 0;
$respectVacations = !empty($_POST['respect_vacations']) ? 1 : 0;

$dates = isset($_POST['dates']) && is_array($_POST['dates']) ? array_values(array_unique($_POST['dates'])) : [];
if (empty($dates)) { back(['month'=>$month,'course_id'=>$courseId,'err'=>'nodates']); }

// Only consider past or today
$today = date('Y-m-d');
$dates = array_values(array_filter($dates, function($d) use ($today){ return preg_match('/^\d{4}-\d{2}-\d{2}$/',$d) && $d <= $today; }));
if (empty($dates)) { back(['month'=>$month,'course_id'=>$courseId,'err'=>'nodates']); }

// Students scope (Active/Following)
$where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."' AND se.student_enroll_status IN ('Following','Active')";
if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
$sqlSt = "SELECT s.student_id FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where ORDER BY s.student_id";
$students = [];
$res = mysqli_query($con, $sqlSt);
if ($res) { while ($r=mysqli_fetch_assoc($res)) { $students[] = $r['student_id']; } }
if (empty($students)) { back(['month'=>$month,'course_id'=>$courseId,'err'=>'nostudents']); }

// Input present map: present[sid][] = list of dates with present
$present = isset($_POST['present']) && is_array($_POST['present']) ? $_POST['present'] : [];
$presentSet = [];
foreach ($present as $sid => $dlist) {
  if (!is_array($dlist)) continue;
  foreach ($dlist as $d) {
    $k = (string)$sid.'|'.$d;
    $presentSet[$k] = true;
  }
}

// Staff name for attribution
$staff_name = '';
if (!empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $qr = mysqli_query($con, "SELECT staff_name FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($qr && ($rr=mysqli_fetch_assoc($qr))) { $staff_name = $rr['staff_name']; }
}

// Ensure unique key for idempotent upserts
@mysqli_query($con, "ALTER TABLE `attendance` ADD UNIQUE KEY `uniq_student_date_module` (`student_id`,`date`,`module_name`)");
$module_name = 'DAILY-S1';

mysqli_begin_transaction($con);
$ok = true; $ins=0; $upd=0;
$st = mysqli_prepare($con,
  "INSERT INTO attendance (attendance_status, staff_name, student_id, date, module_name)
   VALUES (?,?,?,?,?)
   ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status), staff_name=VALUES(staff_name)"
);
if (!$st) { mysqli_rollback($con); back(['month'=>$month,'course_id'=>$courseId,'err'=>'stmt']); }

foreach ($students as $sid) {
  foreach ($dates as $d) {
    $key = (string)$sid.'|'.$d;
    $presentVal = isset($presentSet[$key]) ? 1 : 0;
    mysqli_stmt_bind_param($st, 'issss', $presentVal, $staff_name, $sid, $d, $module_name);
    if (!mysqli_stmt_execute($st)) { $ok=false; break; }
    $aff = mysqli_stmt_affected_rows($st);
    if ($aff === 1) $ins++; else if ($aff === 2) $upd++;
  }
  if (!$ok) break;
}
if ($st) { mysqli_stmt_close($st); }

if ($ok) {
  mysqli_commit($con);
  back(['month'=>$month,'course_id'=>$courseId,'ok'=>1,'ins'=>$ins,'upd'=>$upd,'load'=>1,'include_weekends'=>$includeWeekends,'respect_holidays'=>$respectHolidays,'respect_vacations'=>$respectVacations]);
} else {
  mysqli_rollback($con);
  back(['month'=>$month,'course_id'=>$courseId,'err'=>'dberror','load'=>1]);
}
