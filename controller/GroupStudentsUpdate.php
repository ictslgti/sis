<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if ($role !== 'HOD') { http_response_code(403); echo 'Forbidden'; exit; }

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($group_id<=0) { header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=invalid'); exit; }

if ($action === 'add') {
  // Bulk add: accept student_ids[] array
  $ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];
  $ids = array_values(array_filter(array_map('trim', $ids), function($v){ return $v !== ''; }));
  if (empty($ids)) { header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=invalid'); exit; }

  mysqli_begin_transaction($con);
  $ok = true;
  $st = mysqli_prepare($con,'INSERT INTO group_students (group_id, student_id, status) VALUES (?, ?, "active") ON DUPLICATE KEY UPDATE status="active", enrolled_at=CURRENT_TIMESTAMP');
  if (!$st) { mysqli_rollback($con); header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=dbprep'); exit; }
  foreach ($ids as $sid) {
    $sid = substr($sid, 0, 50); // basic safeguard
    mysqli_stmt_bind_param($st,'is',$group_id,$sid);
    if (!mysqli_stmt_execute($st)) { $ok = false; break; }
  }
  mysqli_stmt_close($st);
  if ($ok) { 
    mysqli_commit($con); 
    $redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : '';
    if ($redirect === 'group_timetable') {
      header('Location: '.$base.'/timetable/GroupTimetable.php?group_id='.$group_id);
    } else {
      header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&ok=1');
    }
  } else { 
    mysqli_rollback($con); 
    header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=dbexec'); 
  }
  exit;
} elseif ($action === 'remove') {
  // Soft remove: set status left
  $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
  if ($student_id==='') { header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=invalid'); exit; }
  $st = mysqli_prepare($con,'UPDATE group_students SET status="left" WHERE group_id=? AND student_id=?');
  if (!$st) { header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=dbprep'); exit; }
  mysqli_stmt_bind_param($st,'is',$group_id,$student_id);
  if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=dbexec'); exit; }
  mysqli_stmt_close($st);
  header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&ok=1');
  exit;
} elseif ($action === 'bulk_remove') {
  // Bulk soft remove
  $ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];
  $ids = array_values(array_filter(array_map('trim', $ids), function($v){ return $v !== ''; }));
  if (empty($ids)) { header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=invalid'); exit; }

  mysqli_begin_transaction($con);
  $ok = true;
  $st = mysqli_prepare($con,'UPDATE group_students SET status="left" WHERE group_id=? AND student_id=?');
  if (!$st) { mysqli_rollback($con); header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=dbprep'); exit; }
  foreach ($ids as $sid) {
    $sid = substr($sid, 0, 50);
    mysqli_stmt_bind_param($st,'is',$group_id,$sid);
    if (!mysqli_stmt_execute($st)) { $ok = false; break; }
  }
  mysqli_stmt_close($st);
  if ($ok) { 
    mysqli_commit($con); 
    $redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : '';
    if ($redirect === 'group_timetable') {
      header('Location: '.$base.'/timetable/GroupTimetable.php?group_id='.$group_id);
    } else {
      header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&ok=1');
    }
  } else { 
    mysqli_rollback($con); 
    header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=dbexec'); 
  }
  exit;
} else {
  header('Location: '.$base.'/group/GroupStudents.php?group_id='.$group_id.'&err=invalid_action');
  exit;
}
