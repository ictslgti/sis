<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if ($role !== 'HOD') { http_response_code(403); echo 'Forbidden'; exit; }

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$staff_id = isset($_POST['staff_id']) ? trim($_POST['staff_id']) : '';
$assign_role = isset($_POST['assign_role']) ? trim($_POST['assign_role']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($group_id<=0 || $staff_id==='') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=invalid'); exit; }

if ($action === 'assign') {
  if ($assign_role !== 'LECTURER' && $assign_role !== 'INSTRUCTOR') {
    header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=role'); exit;
  }
  $st = mysqli_prepare($con,'INSERT INTO group_staff (group_id, staff_id, role, active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE active=1, assigned_at=CURRENT_TIMESTAMP');
  mysqli_stmt_bind_param($st,'iss',$group_id,$staff_id,$assign_role);
  mysqli_stmt_execute($st);
  mysqli_stmt_close($st);
} elseif ($action === 'unassign') {
  $st = mysqli_prepare($con,'UPDATE group_staff SET active=0 WHERE group_id=? AND staff_id=? AND role=?');
  mysqli_stmt_bind_param($st,'iss',$group_id,$staff_id,$assign_role);
  mysqli_stmt_execute($st);
  mysqli_stmt_close($st);
}
header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&ok=1');
