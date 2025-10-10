<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if (!in_array($role, ['HOD','IN3'], true)) { http_response_code(403); echo 'Forbidden'; exit; }

function back_to_grid($params){ global $base; header('Location: '.$base.'/attendance/BulkMonthlyMark.php?'.http_build_query($params)); exit; }

// Resolve department
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}
if ($deptCode === '') { back_to_grid(['err'=>'nodept']); }

$month   = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
$courseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$groupId  = isset($_POST['group_id']) ? trim($_POST['group_id']) : '';
$lockDate = isset($_POST['lock_date']) ? trim($_POST['lock_date']) : '';

$firstDay = $month.'-01';
$lastDay  = date('Y-m-t', strtotime($firstDay));
$today    = date('Y-m-d');

// Validate lock date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lockDate) || $lockDate < $firstDay || $lockDate > $lastDay || $lockDate > $today) {
  back_to_grid(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'nodates','load'=>1]);
}

// Collect students in scope
$students = [];
if ($groupId !== '') {
  $sqlSt = "SELECT s.student_id
            FROM group_students gs
            JOIN student s ON s.student_id = gs.student_id
            WHERE gs.group_id = ? AND (gs.status='active' OR gs.status IS NULL OR gs.status='')
            ORDER BY s.student_id";
  if ($st = mysqli_prepare($con, $sqlSt)) {
    $gid = (int)$groupId;
    mysqli_stmt_bind_param($st, 'i', $gid);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($res && ($r = mysqli_fetch_assoc($res))) { $students[] = $r['student_id']; }
    mysqli_stmt_close($st);
  }
} else {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."' AND se.student_enroll_status IN ('Following','Active')";
  if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
  $sqlSt = "SELECT s.student_id FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where ORDER BY s.student_id";
  $res = mysqli_query($con, $sqlSt);
  if ($res) { while ($r=mysqli_fetch_assoc($res)) { $students[] = $r['student_id']; } }
}
if (empty($students)) { back_to_grid(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'nostudents','load'=>1]); }

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
$ok=true; $upd=0; $ins=0;

// Insert/Update -1 for all students in scope for the lock date
$values = [];
$dt = mysqli_real_escape_string($con, $lockDate);
$stf = mysqli_real_escape_string($con, $staff_name);
$mn  = mysqli_real_escape_string($con, $module_name);
foreach ($students as $sid) {
  $sidEsc = mysqli_real_escape_string($con, $sid);
  $values[] = "(-1,'{$stf}','{$sidEsc}','{$dt}','{$mn}')";
}
if (!empty($values)) {
  $sql = "INSERT INTO attendance (attendance_status, staff_name, student_id, date, module_name) VALUES ".implode(',', $values)
       . " ON DUPLICATE KEY UPDATE attendance_status=-1, staff_name=VALUES(staff_name)";
  if (!mysqli_query($con, $sql)) { $ok=false; }
  else { $upd = mysqli_affected_rows($con); }
}

if ($ok) {
  mysqli_commit($con);
  back_to_grid([
    'month'=>$month,
    'course_id'=>$courseId,
    'group_id'=>$groupId,
    'ok'=>1,
    'upd'=>$upd,
    'load'=>1,
    'respect_holidays'=>1,
    'respect_vacations'=>1
  ]);
} else {
  mysqli_rollback($con);
  back_to_grid(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'dberror','load'=>1]);
}
