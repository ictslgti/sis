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

// Department ownership enforcement for HODs
$deptId = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : 0;
if ($deptId <= 0) { http_response_code(403); echo 'Forbidden'; exit; }
$chkDept = mysqli_prepare($con, 'SELECT c.department_id FROM groups g LEFT JOIN course c ON c.course_id=g.course_id WHERE g.id=?');
if ($chkDept) {
  mysqli_stmt_bind_param($chkDept, 'i', $group_id);
  mysqli_stmt_execute($chkDept);
  $rsDept = mysqli_stmt_get_result($chkDept);
  $rowDept = $rsDept ? mysqli_fetch_assoc($rsDept) : null;
  mysqli_stmt_close($chkDept);
  if (!$rowDept || (int)($rowDept['department_id'] ?? 0) !== $deptId) {
    header('Location: '.$base.'/group/Groups.php?err=dept');
    exit;
  }
} else {
  header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=dbprep');
  exit;
}

// Detect module-wise table
$hasGsm = false;
$ck = mysqli_query($con, "SHOW TABLES LIKE 'group_staff_module'");
if ($ck && mysqli_num_rows($ck) > 0) { $hasGsm = true; }
if ($ck) mysqli_free_result($ck);

if ($action === 'assign') {
  if ($assign_role !== 'LECTURER' && $assign_role !== 'INSTRUCTOR') {
    header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=role'); exit;
  }
  // Module-wise only: group_staff_module must exist
  if (!$hasGsm) { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit; }
  if ($module_id === '') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module'); exit; }
  if ($delivery_type === '' || !in_array($delivery_type, ['THEORY','PRACTICAL','BOTH'], true)) { $delivery_type = 'BOTH'; }
  // Validate module belongs to this group's course
  $chk = mysqli_prepare($con, 'SELECT 1 FROM module WHERE module_id=? AND course_id=(SELECT course_id FROM groups WHERE id=?)');
  if ($chk) {
    mysqli_stmt_bind_param($chk, 'si', $module_id, $group_id);
    mysqli_stmt_execute($chk);
    $rs = mysqli_stmt_get_result($chk);
    $ok = ($rs && mysqli_num_rows($rs) > 0);
    mysqli_stmt_close($chk);
    if (!$ok) { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_course'); exit; }
  }
  $st = mysqli_prepare($con,'INSERT INTO group_staff_module (group_id, module_id, staff_id, role, delivery_type, active) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE delivery_type=VALUES(delivery_type), active=1, assigned_at=CURRENT_TIMESTAMP');
  if ($st) {
    mysqli_stmt_bind_param($st,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
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
} elseif ($action === 'map_legacy') {
  // Map a legacy group_staff assignment to module-wise entry and deactivate legacy row
  // Requires module_id and delivery_type
  if ($module_id === '' || !in_array($delivery_type, ['THEORY','PRACTICAL','BOTH'], true)) {
    header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit;
  }
  // Ensure module table exists
  $ck = mysqli_query($con, "SHOW TABLES LIKE 'group_staff_module'");
  if (!($ck && mysqli_num_rows($ck) > 0)) { if ($ck) mysqli_free_result($ck); header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit; }
  if ($ck) mysqli_free_result($ck);
  // Insert or update module-wise
  $ins = mysqli_prepare($con,'INSERT INTO group_staff_module (group_id, module_id, staff_id, role, delivery_type, active) VALUES (?, ?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE delivery_type=VALUES(delivery_type), active=1, assigned_at=CURRENT_TIMESTAMP');
  if ($ins) { mysqli_stmt_bind_param($ins,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type); mysqli_stmt_execute($ins); mysqli_stmt_close($ins); }
  // Deactivate legacy row
  $upd = mysqli_prepare($con,'UPDATE group_staff SET active=0 WHERE group_id=? AND staff_id=? AND role=?');
  if ($upd) { mysqli_stmt_bind_param($upd,'iss',$group_id,$staff_id,$assign_role); mysqli_stmt_execute($upd); mysqli_stmt_close($upd); }
} elseif ($action === 'update') {
  // Update an existing module-wise assignment to new module/delivery/role.
  // Requires old identifiers to locate the row exactly
  if (!$hasGsm) { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit; }
  $old_module = isset($_POST['old_module_id']) ? trim($_POST['old_module_id']) : '';
  $old_delivery = isset($_POST['old_delivery_type']) ? trim($_POST['old_delivery_type']) : '';
  $old_role = isset($_POST['old_role']) ? trim($_POST['old_role']) : '';
  if ($module_id === '' || $old_module === '' || $old_delivery === '' || $old_role === '') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module'); exit; }
  if ($delivery_type === '' || !in_array($delivery_type, ['THEORY','PRACTICAL','BOTH'], true)) { $delivery_type = 'BOTH'; }
  if ($assign_role !== 'LECTURER' && $assign_role !== 'INSTRUCTOR') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=role'); exit; }
  // Validate new module belongs to this group's course
  $chk = mysqli_prepare($con, 'SELECT 1 FROM module WHERE module_id=? AND course_id=(SELECT course_id FROM groups WHERE id=?)');
  if ($chk) { mysqli_stmt_bind_param($chk,'si',$module_id,$group_id); mysqli_stmt_execute($chk); $rs=mysqli_stmt_get_result($chk); $ok = ($rs && mysqli_num_rows($rs)>0); mysqli_stmt_close($chk); if(!$ok){ header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_course'); exit; } }
  // Soft-close the old row, then upsert the new definition
  $st1 = mysqli_prepare($con,'UPDATE group_staff_module SET active=0 WHERE group_id=? AND module_id=? AND staff_id=? AND role=? AND delivery_type=?');
  if ($st1) { mysqli_stmt_bind_param($st1,'issss',$group_id,$old_module,$staff_id,$old_role,$old_delivery); mysqli_stmt_execute($st1); mysqli_stmt_close($st1); }
  $st2 = mysqli_prepare($con,'INSERT INTO group_staff_module (group_id, module_id, staff_id, role, delivery_type, active) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE delivery_type=VALUES(delivery_type), role=VALUES(role), active=1, assigned_at=CURRENT_TIMESTAMP');
  if ($st2) { mysqli_stmt_bind_param($st2,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type); mysqli_stmt_execute($st2); mysqli_stmt_close($st2); }
} elseif ($action === 'delete') {
  // Soft delete an assignment (module-wise only)
  if (!$hasGsm) { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit; }
  if ($module_id === '' || $delivery_type === '' || $assign_role === '') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module'); exit; }
  $st = mysqli_prepare($con,'UPDATE group_staff_module SET active=0 WHERE group_id=? AND module_id=? AND staff_id=? AND role=? AND delivery_type=?');
  if ($st) { mysqli_stmt_bind_param($st,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
} elseif ($action === 'bulk_unassign_all') {
  if (!$hasGsm) { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit; }
  $st = mysqli_prepare($con,'UPDATE group_staff_module SET active=0 WHERE group_id=?');
  if ($st) { mysqli_stmt_bind_param($st,'i',$group_id); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
} elseif ($action === 'bulk_unassign_module') {
  if (!$hasGsm || $module_id==='') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module'); exit; }
  $st = mysqli_prepare($con,'UPDATE group_staff_module SET active=0 WHERE group_id=? AND module_id=?');
  if ($st) { mysqli_stmt_bind_param($st,'is',$group_id,$module_id); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
} elseif ($action === 'bulk_unassign_staff') {
  if (!$hasGsm || $staff_id==='') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=invalid'); exit; }
  $st = mysqli_prepare($con,'UPDATE group_staff_module SET active=0 WHERE group_id=? AND staff_id=?');
  if ($st) { mysqli_stmt_bind_param($st,'is',$group_id,$staff_id); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
} elseif ($action === 'hard_delete') {
  // Hard delete a single assignment row
  if (!$hasGsm) { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module_table'); exit; }
  if ($module_id === '' || $delivery_type === '' || $assign_role === '' || $staff_id==='') { header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&err=module'); exit; }
  $st = mysqli_prepare($con,'DELETE FROM group_staff_module WHERE group_id=? AND module_id=? AND staff_id=? AND role=? AND delivery_type=?');
  if ($st) { mysqli_stmt_bind_param($st,'issss',$group_id,$module_id,$staff_id,$assign_role,$delivery_type); mysqli_stmt_execute($st); mysqli_stmt_close($st); }
}
header('Location: '.$base.'/group/GroupStaff.php?group_id='.$group_id.'&ok=1');
