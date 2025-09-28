<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if (!in_array($role, ['HOD','IN3'], true)) { http_response_code(403); echo 'Forbidden'; exit; }

function hredir($qs){ global $base; header('Location: '.$base.'/attendance/BulkMonthlyMark.php?'.$qs); exit; }

// Resolve department
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}
if ($deptCode === '') { hredir(http_build_query(['err'=>'nodept'])); }

$month = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
$courseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$groupId = isset($_POST['group_id']) ? trim($_POST['group_id']) : '';
$includeWeekends = !empty($_POST['include_weekends']) ? 1 : 0;
$respectHolidays = !empty($_POST['respect_holidays']) ? 1 : 0;
$respectVacations = !empty($_POST['respect_vacations']) ? 1 : 0;
$overrideExisting = !empty($_POST['override_existing']) ? 1 : 0;
$markAs = (isset($_POST['mark_as']) && in_array($_POST['mark_as'], ['Present','Absent'], true)) ? $_POST['mark_as'] : 'Present';

$firstDay = $month.'-01';
$lastDay = date('Y-m-t', strtotime($firstDay));
$today = date('Y-m-d');

// Load holidays set (optional tables)
function load_holidays_set($con, $firstDay, $lastDay) {
  $set = [];
  $cands = [
    ['table'=>'holidays_lk','col'=>'date'],
    ['table'=>'public_holidays','col'=>'holiday_date'],
    ['table'=>'holidays','col'=>'holiday_date'],
  ];
  foreach ($cands as $c) {
    $t = mysqli_real_escape_string($con, $c['table']);
    $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
    if ($rs && mysqli_fetch_row($rs)) {
      $col = $c['col'];
      $q = mysqli_query($con, "SELECT `${col}` AS d FROM `${t}` WHERE `${col}` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
      if ($q) { while($r=mysqli_fetch_assoc($q)){ if (!empty($r['d'])) { $set[$r['d']] = true; } } }
      break;
    }
  }
  return $set;
}

function load_vacations_set($con, $firstDay, $lastDay) {
  $set = [];
  $candsSingle = [
    ['table' => 'vacation_days', 'col' => 'vacation_date'],
    ['table' => 'vacations_days', 'col' => 'date'],
  ];
  foreach ($candsSingle as $c) {
    $t = mysqli_real_escape_string($con, $c['table']);
    $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
    if ($rs && mysqli_fetch_row($rs)) {
      $col = $c['col'];
      $q = mysqli_query($con, "SELECT `${col}` AS d FROM `${t}` WHERE `${col}` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
      if ($q) { while($r=mysqli_fetch_assoc($q)){ if (!empty($r['d'])) { $set[$r['d']] = true; } } }
      return $set;
    }
  }
  $candsRange = [
    ['table' => 'vacations', 'start' => 'start_date', 'end' => 'end_date'],
    ['table' => 'academic_vacations', 'start' => 'start_date', 'end' => 'end_date'],
    ['table' => 'institution_vacations', 'start' => 'from_date', 'end' => 'to_date'],
  ];
  foreach ($candsRange as $c) {
    $t = mysqli_real_escape_string($con, $c['table']);
    $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
    if ($rs && mysqli_fetch_row($rs)) {
      $colS = $c['start']; $colE = $c['end'];
      $q = mysqli_query($con, "SELECT `${colS}` AS s, `${colE}` AS e FROM `${t}` WHERE NOT(`${colE}` < '".mysqli_real_escape_string($con,$firstDay)."' OR `${colS}` > '".mysqli_real_escape_string($con,$lastDay)."')");
      if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
          $s = !empty($r['s']) ? max($firstDay, $r['s']) : $firstDay;
          $e = !empty($r['e']) ? min($lastDay, $r['e']) : $lastDay;
          $ds = strtotime($s); $de = strtotime($e);
          if ($ds && $de && $ds <= $de) {
            for ($tday = $ds; $tday <= $de; $tday = strtotime('+1 day', $tday)) {
              $set[date('Y-m-d', $tday)] = true;
            }
          }
        }
      }
      break;
    }
  }
  return $set;
}

$holidaySet = $respectHolidays ? load_holidays_set($con, $firstDay, $lastDay) : [];
$vacationSet = $respectVacations ? load_vacations_set($con, $firstDay, $lastDay) : [];

// Build list of dates to mark
$dates = [];
$daysInMonth = (int)date('t', strtotime($firstDay));
for ($d=1; $d<=$daysInMonth; $d++) {
  $dstr = date('Y-m-d', strtotime($month.'-'.str_pad($d,2,'0',STR_PAD_LEFT)));
  if ($dstr > $today) { continue; }
  $w = (int)date('w', strtotime($dstr)); // 0=Sun,6=Sat
  if (!$includeWeekends && ($w===0 || $w===6)) { continue; }
  if ($respectHolidays && isset($holidaySet[$dstr])) { continue; }
  if ($respectVacations && isset($vacationSet[$dstr])) { continue; }
  $dates[] = $dstr;
}

if (empty($dates)) { hredir(http_build_query(['month'=>$month,'course_id'=>$courseId,'err'=>'nodates'])); }

// Load students in scope (Active/Following) by group or by course/department
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
if (empty($students)) { hredir(http_build_query(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'nostudents'])); }

// Staff name for attribution
$staff_name = '';
if (!empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $qr = mysqli_query($con, "SELECT staff_name FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($qr && ($rr=mysqli_fetch_assoc($qr))) { $staff_name = $rr['staff_name']; }
}

// Ensure unique key for idempotency
@mysqli_query($con, "ALTER TABLE `attendance` ADD UNIQUE KEY `uniq_student_date_module` (`student_id`,`date`,`module_name`)");

$module_name = 'DAILY-S1';
$presentVal = ($markAs === 'Present') ? 1 : 0;

mysqli_begin_transaction($con);
$ok = true;
$inserted = 0; $updated = 0; $skipped = 0;

// Prepare upsert statement
if ($overrideExisting) {
  $sqlIns = "INSERT INTO attendance (attendance_status, staff_name, student_id, date, module_name) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status), staff_name=VALUES(staff_name)";
} else {
  // No override: keep existing values (no-op on duplicate)
  $sqlIns = "INSERT INTO attendance (attendance_status, staff_name, student_id, date, module_name) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE attendance_status=attendance_status";
}
$st = mysqli_prepare($con, $sqlIns);
if (!$st) { mysqli_rollback($con); hredir(http_build_query(['month'=>$month,'course_id'=>$courseId,'err'=>'stmt'])); }

foreach ($students as $sid) {
  foreach ($dates as $dstr) {
    mysqli_stmt_bind_param($st, 'issss', $presentVal, $staff_name, $sid, $dstr, $module_name);
    if (!mysqli_stmt_execute($st)) { $ok = false; break; }
    // Affected rows heuristic: 1 insert, 2 update (for ON DUP KEY), 0 no-op
    $aff = mysqli_stmt_affected_rows($st);
    if ($aff === 1) { $inserted++; }
    elseif ($aff === 2) { $updated++; }
    else { $skipped++; }
  }
  if (!$ok) { break; }
}

if ($st) { mysqli_stmt_close($st); }

if ($ok) {
  mysqli_commit($con);
  $qs = http_build_query([
    'month'=>$month,
    'course_id'=>$courseId,
    'group_id'=>$groupId,
    'ok'=>1,
    'ins'=>$inserted,
    'upd'=>$updated,
    'skip'=>$skipped
  ]);
  hredir($qs);
} else {
  mysqli_rollback($con);
  $qs = http_build_query(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'dberror']);
  hredir($qs);
}
