<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if ($role !== 'HOD') { http_response_code(403); echo 'Forbidden'; exit; }

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$from = isset($_POST['from']) ? trim($_POST['from']) : '';
$to   = isset($_POST['to']) ? trim($_POST['to']) : '';
if ($group_id <= 0) { header('Location: '.$base.'/group/Reports.php?report=attendance&err=invalid'); exit; }

// Ensure legacy table exists
$check = mysqli_query($con, "SHOW TABLES LIKE 'attendance'");
if (!$check || mysqli_num_rows($check) === 0) {
  header('Location: '.$base.'/group/Reports.php?report=attendance&group_id='.$group_id.'&err=legacy_missing');
  exit;
}

// Build date filters
$w = 'WHERE s.group_id = ?';
$params = [$group_id];
$types = 'i';
if ($from !== '') { $w .= ' AND s.session_date >= ?'; $params[] = $from; $types .= 's'; }
if ($to   !== '') { $w .= ' AND s.session_date <= ?'; $params[] = $to;   $types .= 's'; }

// Fetch attendance rows to mirror
$sql = "SELECT s.id AS session_id, s.session_date, s.created_by, g.name AS group_name,
               ga.student_id, ga.present
        FROM group_sessions s
        INNER JOIN `groups` g ON g.id = s.group_id
        INNER JOIN group_attendance ga ON ga.session_id = s.id
        $w
        ORDER BY s.session_date, s.id";
$st = mysqli_prepare($con, $sql);
if (!$st) { header('Location: '.$base.'/group/Reports.php?report=attendance&group_id='.$group_id.'&err=query'); exit; }
mysqli_stmt_bind_param($st, $types, ...$params);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);

// Prepare helpers
// Resolve staff_name for a created_by (assumed staff_id)
function staff_name_by_id($con, $sid){
  static $cache = [];
  if ($sid === null || $sid === '') return '';
  if (isset($cache[$sid])) return $cache[$sid];
  $sid_s = mysqli_real_escape_string($con, $sid);
  $q = mysqli_query($con, "SELECT staff_name FROM staff WHERE staff_id='$sid_s' LIMIT 1");
  $name = '';
  if ($q && ($r=mysqli_fetch_assoc($q))) { $name = $r['staff_name']; }
  $cache[$sid] = $name;
  return $name;
}

$inserted = 0; $updated = 0; $errors = 0; $seen = [];

while ($res && ($r = mysqli_fetch_assoc($res))) {
  $student_id = $r['student_id'];
  $date = $r['session_date'];
  $present = (int)$r['present'] === 1 ? 1 : 0;
  $module_name = 'GROUP: '.($r['group_name'] ?? '');
  $staff_name = staff_name_by_id($con, $r['created_by']);

  // Upsert one record per student/date in legacy table
  $sid = mysqli_real_escape_string($con, $student_id);
  $dt  = mysqli_real_escape_string($con, $date);
  $chk = mysqli_query($con, "SELECT attendance_id, attendance_status FROM attendance WHERE student_id='$sid' AND date='$dt' LIMIT 1");
  if ($chk && mysqli_num_rows($chk) > 0) {
    $row = mysqli_fetch_assoc($chk);
    $aid = (int)$row['attendance_id'];
    $cur = (int)$row['attendance_status'];
    $new = ($cur === 1 || $present === 1) ? 1 : 0; // once present, keep present
    $mn = mysqli_real_escape_string($con, $module_name);
    $sn = mysqli_real_escape_string($con, $staff_name);
    if (!mysqli_query($con, "UPDATE attendance SET attendance_status=$new, module_name='$mn', staff_name='$sn' WHERE attendance_id=$aid")) { $errors++; }
    else { $updated++; }
  } else {
    $mn = mysqli_real_escape_string($con, $module_name);
    $sn = mysqli_real_escape_string($con, $staff_name);
    $ins = "INSERT INTO attendance(student_id,module_name,staff_name,attendance_status,date) VALUES('$sid','$mn','$sn',$present,'$dt')";
    if (!mysqli_query($con, $ins)) { $errors++; }
    else { $inserted++; }
  }
}

mysqli_stmt_close($st);

header('Location: '.$base.'/group/Reports.php?report=attendance&group_id='.$group_id.'&ok=export&ins='.$inserted.'&upd='.$updated.'&errc='.$errors.'&from='.urlencode($from).'&to='.urlencode($to));
