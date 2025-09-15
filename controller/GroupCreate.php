<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if (!in_array($role, ['HOD'])) { http_response_code(403); echo 'Forbidden'; exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$course_id = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'active';

if ($name === '' || $course_id === '' || $academic_year === '') {
  header('Location: '.$base.'/group/AddGroup.php?err=invalid'.($id?'&id='.$id:'')); exit;
}

// Ensure HOD can only create/edit groups for courses in their own department
$deptId = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : 0;
if ($deptId <= 0) { http_response_code(403); echo 'Forbidden'; exit; }
$chk = mysqli_prepare($con, 'SELECT 1 FROM course WHERE course_id = ? AND department_id = ?');
if ($chk) {
  mysqli_stmt_bind_param($chk, 'si', $course_id, $deptId);
  mysqli_stmt_execute($chk);
  $rs = mysqli_stmt_get_result($chk);
  $okCourse = ($rs && $rs->num_rows > 0);
  mysqli_stmt_close($chk);
  if (!$okCourse) {
    header('Location: '.$base.'/group/AddGroup.php?err=dept_mismatch'.($id?'&id='.$id:''));
    exit;
  }
} else {
  header('Location: '.$base.'/group/AddGroup.php?err=dbprep'.($id?'&id='.$id:''));
  exit;
}

if ($id > 0) {
  $st = mysqli_prepare($con, 'UPDATE `groups` SET name=?, course_id=?, academic_year=?, status=? WHERE id=?');
  if (!$st) { header('Location: '.$base.'/group/AddGroup.php?err=dbprep&id='.(int)$id); exit; }
  mysqli_stmt_bind_param($st, 'ssssi', $name, $course_id, $academic_year, $status, $id);
  if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); header('Location: '.$base.'/group/AddGroup.php?err=dbexec&id='.(int)$id); exit; }
  mysqli_stmt_close($st);
} else {
  $st = mysqli_prepare($con, 'INSERT INTO `groups` (name, course_id, academic_year, status, created_by) VALUES (?, ?, ?, "active", ?)');
  if (!$st) { header('Location: '.$base.'/group/AddGroup.php?err=dbprep'); exit; }
  $uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
  mysqli_stmt_bind_param($st, 'ssss', $name, $course_id, $academic_year, $uid);
  if (!mysqli_stmt_execute($st)) { mysqli_stmt_close($st); header('Location: '.$base.'/group/AddGroup.php?err=dbexec'); exit; }
  mysqli_stmt_close($st);
}
header('Location: '.$base.'/group/Groups.php?ok=1');
