<?php
// Delete Group controller with safety checks
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// Allow only HOD/IN1/IN2/IN3
if (!in_array($role, ['HOD','IN1','IN2','IN3'], true)) {
  $_SESSION['info'] = 'Forbidden: you do not have permission to delete groups.';
  header('Location: ' . ($base ?: '') . '/group/Groups.php');
  exit;
}

// Validate input
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$force = isset($_POST['force']) ? (int)$_POST['force'] : 0; // 1 to cascade delete students
if ($id <= 0) {
  $_SESSION['info'] = 'Invalid group selected.';
  header('Location: ' . ($base ?: '') . '/group/Groups.php');
  exit;
}

// Check if group exists and belongs to the same department (for HOD/IN roles)
$grp = null;
$st = mysqli_prepare($con, 'SELECT g.*, c.department_id FROM `groups` g LEFT JOIN course c ON c.course_id=g.course_id WHERE g.id=?');
if ($st) {
  mysqli_stmt_bind_param($st, 'i', $id);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $grp = $rs ? mysqli_fetch_assoc($rs) : null;
  mysqli_stmt_close($st);
}
if (!$grp) {
  $_SESSION['info'] = 'Group not found.';
  header('Location: ' . ($base ?: '') . '/group/Groups.php');
  exit;
}

// Department scope enforcement
$deptId = '';
if (!empty($_SESSION['department_code'])) { $deptId = trim((string)$_SESSION['department_code']); }
elseif (!empty($_SESSION['department_id'])) { $deptId = trim((string)$_SESSION['department_id']); }
if (in_array($role, ['HOD','IN1','IN2','IN3'], true)) {
  $grpDept = isset($grp['department_id']) ? trim((string)$grp['department_id']) : '';
  if ($deptId !== '' && $grpDept !== '' && strval($grpDept) !== strval($deptId)) {
    $_SESSION['info'] = 'Access denied: group is outside your department.';
    header('Location: ' . ($base ?: '') . '/group/Groups.php');
    exit;
  }
}

// Count students assigned (any status)
$cnt = 0;
$cs = mysqli_prepare($con, 'SELECT COUNT(*) AS c FROM group_students WHERE group_id=?');
if ($cs) {
  mysqli_stmt_bind_param($cs, 'i', $id);
  mysqli_stmt_execute($cs);
  $rs = mysqli_stmt_get_result($cs);
  $row = $rs ? mysqli_fetch_assoc($rs) : null;
  $cnt = $row ? (int)$row['c'] : 0;
  mysqli_stmt_close($cs);
}
// If not forcing and students exist, block deletion
if ($cnt > 0 && $force !== 1) {
  $_SESSION["info"] = 'Cannot delete: students are assigned to this group.';
  header('Location: ' . ($base ?: '') . '/group/Groups.php');
  exit;
}

// Proceed to delete: remove related group_staff entries first (if any), then the group
mysqli_begin_transaction($con);
try {
  // If force, remove student links first
  if ($force === 1) {
    $dgst = mysqli_prepare($con, 'DELETE FROM group_students WHERE group_id=?');
    if ($dgst) { mysqli_stmt_bind_param($dgst, 'i', $id); mysqli_stmt_execute($dgst); mysqli_stmt_close($dgst); }
  }

  // Delete staff links
  $ds = mysqli_prepare($con, 'DELETE FROM group_staff WHERE group_id=?');
  if ($ds) { mysqli_stmt_bind_param($ds, 'i', $id); mysqli_stmt_execute($ds); mysqli_stmt_close($ds); }

  // Optionally clean up sessions without students (safe regardless)
  $dss = mysqli_prepare($con, 'DELETE FROM group_sessions WHERE group_id=?');
  if ($dss) { mysqli_stmt_bind_param($dss, 'i', $id); mysqli_stmt_execute($dss); mysqli_stmt_close($dss); }

  // Delete the group
  $dg = mysqli_prepare($con, 'DELETE FROM `groups` WHERE id=?');
  if (!$dg) { throw new Exception('prepare'); }
  mysqli_stmt_bind_param($dg, 'i', $id);
  mysqli_stmt_execute($dg);
  if (mysqli_stmt_affected_rows($dg) < 1) { throw new Exception('notfound'); }
  mysqli_stmt_close($dg);

  mysqli_commit($con);
  $_SESSION['info'] = ($force === 1 ? 'Group and its student assignments deleted successfully.' : 'Group deleted successfully.');
} catch (Throwable $e) {
  mysqli_rollback($con);
  $_SESSION['info'] = 'Delete failed.';
}

header('Location: ' . ($base ?: '') . '/group/Groups.php');
exit;
