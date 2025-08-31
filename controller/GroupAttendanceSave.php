<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$allowed = ['HOD','IN1','IN2','LE1','LE2','ADM'];
if (!in_array($role, $allowed)) { http_response_code(403); echo 'Forbidden'; exit; }

$session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
if ($session_id<=0) { http_response_code(400); echo 'Invalid'; exit; }

// Load session + group
$st = mysqli_prepare($con,'SELECT s.*, g.id AS group_id FROM group_sessions s INNER JOIN groups g ON g.id=s.group_id WHERE s.id=?');
if ($st) { mysqli_stmt_bind_param($st,'i',$session_id); mysqli_stmt_execute($st); $rs=mysqli_stmt_get_result($st); $sess = $rs?mysqli_fetch_assoc($rs):null; mysqli_stmt_close($st);} if(!$sess){ http_response_code(404); echo 'Not found'; exit; }

// Non-HOD must be assigned to the group
$uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
if ($role !== 'HOD') {
  $st2 = mysqli_prepare($con,'SELECT 1 FROM group_staff WHERE group_id=? AND staff_id=? AND active=1 LIMIT 1');
  if ($st2) { mysqli_stmt_bind_param($st2,'is',$sess['group_id'],$uid); mysqli_stmt_execute($st2); $rs2=mysqli_stmt_get_result($st2); $ok = ($rs2 && mysqli_fetch_row($rs2)); mysqli_stmt_close($st2); if(!$ok){ http_response_code(403); echo 'Not assigned'; exit; } }
}

// Get students in group
$students = [];
$q = mysqli_prepare($con,'SELECT student_id FROM group_students WHERE group_id=? AND status="active"');
if ($q) { mysqli_stmt_bind_param($q,'i',$sess['group_id']); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $students[]=$r['student_id']; } mysqli_stmt_close($q);} 

if (empty($students)) { header('Location: '.$base.'/group/MarkGroupAttendance.php?session_id='.$session_id.'&err=nostudents'); exit; }

mysqli_begin_transaction($con);
$okAll = true;
foreach ($students as $sid) {
  $key = 'ATT'.$sid;
  $val = isset($_POST[$key]) ? $_POST[$key] : 'Absent';
  $present = ($val==='Present') ? 1 : 0;
  $stI = mysqli_prepare($con,'INSERT INTO group_attendance (session_id, student_id, present, marked_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE present=VALUES(present), marked_at=CURRENT_TIMESTAMP, marked_by=VALUES(marked_by)');
  if ($stI) { mysqli_stmt_bind_param($stI,'isis', $session_id, $sid, $present, $uid); $ok = mysqli_stmt_execute($stI); mysqli_stmt_close($stI); if(!$ok){ $okAll=false; break; } }
}
if ($okAll) { mysqli_commit($con); header('Location: '.$base.'/group/GroupSessions.php?group_id='.$sess['group_id'].'&ok=1'); }
else { mysqli_rollback($con); header('Location: '.$base.'/group/MarkGroupAttendance.php?session_id='.$session_id.'&err=save'); }
