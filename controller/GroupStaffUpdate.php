<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if ($role !== 'HOD') { http_response_code(403); echo 'Forbidden'; exit; }

$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$staff_id = isset($_POST['staff_id']) ? trim($_POST['staff_id']) : '';
$assign_role = isset($_POST['assign_role']) ? trim($_POST['assign_role']) : '';
$module_id = isset($_POST['module_id']) ? trim($_POST['module_id']) : '';
$delivery_type = isset($_POST['delivery_type']) ? trim($_POST['delivery_type']) : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($group_id<=0 || $staff_id==='') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=invalid'); exit; }

// Detect module-wise table
$hasGsm = false;
$ck = mysqli_query($con, "SHOW TABLES LIKE 'group_staff_module'");
if ($ck && mysqli_num_rows($ck) > 0) { $hasGsm = true; }
if ($ck) mysqli_free_result($ck);

if ($action === 'assign') {
  if ($assign_role !== 'LECTURER' && $assign_role !== 'INSTRUCTOR') {
    header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=role'); exit;
  }
  if ($hasGsm) {
    if ($module_id === '') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module'); exit; }
    if ($delivery_type === '' || !in_array($delivery_type, ['THEORY','PRACTICAL','BOTH'], true)) { $delivery_type = 'BOTH'; }
    $st = mysqli_prepare($con,'INSERT INTO group_staff_module (group_id, module_id, staff_id, role, delivery_type, active) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE delivery_type=VALUES(delivery_type), active=1, assigned_at=CURRENT_TIMESTAMP');
    if ($st) {
      mysqli_stmt_bind_param($st,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type);
      mysqli_stmt_execute($st);
      mysqli_stmt_close($st);
    }
  } else {
    $st = mysqli_prepare($con,'INSERT INTO group_staff (group_id, staff_id, role, active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE active=1, assigned_at=CURRENT_TIMESTAMP');
    if ($st) {
      mysqli_stmt_bind_param($st,'iss',$group_id,$staff_id,$assign_role);
      mysqli_stmt_execute($st);
      mysqli_stmt_close($st);
    }
  }
} elseif ($action === 'unassign') {
  if ($hasGsm && $module_id !== '') {
    if ($delivery_type === '' || !in_array($delivery_type, ['THEORY','PRACTICAL','BOTH'], true)) { $delivery_type = 'BOTH'; }
    $st = mysqli_prepare($con,'UPDATE group_staff_module SET active=0 WHERE group_id=? AND module_id=? AND staff_id=? AND role=? AND delivery_type=?');
    if ($st) { mysqli_stmt_bind_param($st,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
  } else {
    $st = mysqli_prepare($con,'UPDATE group_staff SET active=0 WHERE group_id=? AND staff_id=? AND role=?');
    if ($st) { mysqli_stmt_bind_param($st,'iss',$group_id,$staff_id,$assign_role); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
  }
}
header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&ok=1');
