<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$allowed = ['HOD','IN1','IN2','LE1','LE2','ADM'];
if (!in_array($role, $allowed)) { http_response_code(403); echo 'Forbidden'; exit; }

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$session_date = isset($_POST['session_date']) ? $_POST['session_date'] : date('Y-m-d');
$start_time = isset($_POST['start_time']) && $_POST['start_time']!=='' ? $_POST['start_time'] : null;
$end_time = isset($_POST['end_time']) && $_POST['end_time']!=='' ? $_POST['end_time'] : null;
$coverage_title = isset($_POST['coverage_title']) ? trim($_POST['coverage_title']) : '';
$coverage_notes = isset($_POST['coverage_notes']) ? trim($_POST['coverage_notes']) : null;
$uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;

if ($group_id<=0 || $coverage_title==='') { header('Location: '.$base.'/group/GroupSessions.php?group_id='.$group_id.'&err=invalid'); exit; }

// Non-HOD must be assigned to the group
if (!in_array($role, ['HOD'])) {
  $st = mysqli_prepare($con,'SELECT 1 FROM group_staff WHERE group_id=? AND staff_id=? AND active=1 LIMIT 1');
  if ($st) { mysqli_stmt_bind_param($st,'is',$group_id,$uid); mysqli_stmt_execute($st); $rs=mysqli_stmt_get_result($st); $ok = ($rs && mysqli_fetch_row($rs)); mysqli_stmt_close($st); if(!$ok){ http_response_code(403); echo 'Not assigned'; exit; } }
}

$st = mysqli_prepare($con,'INSERT INTO group_sessions (group_id, session_date, start_time, end_time, coverage_title, coverage_notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($st,'issssss', $group_id, $session_date, $start_time, $end_time, $coverage_title, $coverage_notes, $uid);
mysqli_stmt_execute($st);
mysqli_stmt_close($st);
header('Location: '.$base.'/group/GroupSessions.php?group_id='.$group_id.'&ok=1');
