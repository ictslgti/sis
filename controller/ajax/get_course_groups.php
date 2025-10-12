<?php
// Returns <option> list of groups for a given course_id, scoped to department when available
require_once __DIR__ . '/../../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: text/html; charset=UTF-8');

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
// Allow HOD, IN3, ADM to query
if (!in_array($role, ['HOD','IN3','ADM'], true)) {
  http_response_code(403);
  echo '<option value="">Not authorized</option>';
  exit;
}

$deptCode = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';
$courseId = isset($_POST['course_id']) ? trim((string)$_POST['course_id']) : (isset($_GET['course_id']) ? trim((string)$_GET['course_id']) : '');

$options = '';
$options .= '<option value="">All groups'.($courseId!==''?' in course':'').'</option>';

if ($courseId === '') {
  echo $options;
  exit;
}

// Detect available name/code columns in groups table to avoid schema mismatches
$nameCol = $codeCol = '';
// Alias COLUMN_NAME to ensure consistent array key case across DBs
$colSql = "SELECT COLUMN_NAME AS column_name FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'groups' AND COLUMN_NAME IN ('group_name','group_code','name')";
$colRs = mysqli_query($con, $colSql);
if ($colRs) {
  while ($cr = mysqli_fetch_assoc($colRs)) {
    if (!isset($cr['column_name'])) { continue; }
    $cn = strtolower($cr['column_name']);
    if ($cn === 'group_name' || $cn === 'name') { $nameCol = $cr['column_name']; }
    if ($cn === 'group_code') { $codeCol = $cr['column_name']; }
  }
  mysqli_free_result($colRs);
}
// Build base SQL selecting only existing columns
$selNm = $nameCol ? ("g.`$nameCol`") : "NULL";
$selCd = $codeCol ? ("g.`$codeCol`") : "NULL";
$sqlBase = "SELECT g.id, $selNm AS nm, $selCd AS cd
            FROM `groups` g
            JOIN course c ON c.course_id = g.course_id
            WHERE g.course_id = ?";
$rows = 0;
if ($deptCode !== '') {
  $sql = $sqlBase . " AND c.department_id = ? ORDER BY (nm IS NULL), nm, cd";
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, 'ss', $courseId, $deptCode);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($row = mysqli_fetch_assoc($rs))) {
      $rows++;
      $id = (int)$row['id'];
      $nm = isset($row['nm']) ? trim((string)$row['nm']) : '';
      $cd = isset($row['cd']) ? trim((string)$row['cd']) : '';
      $labelRaw = $nm !== '' ? $nm : ($cd !== '' ? $cd : ('Group #'.$id));
      $label = htmlspecialchars($labelRaw, ENT_QUOTES, 'UTF-8');
      $options .= '<option value="'.$id.'">'.$label.' (ID '.$id.')</option>';
    }
    mysqli_stmt_close($st);
  }
}
// Fallback without department scope if none found
if ($rows === 0) {
  $sql = $sqlBase . " ORDER BY (nm IS NULL), nm, cd";
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, 's', $courseId);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($row = mysqli_fetch_assoc($rs))) {
      $rows++;
      $id = (int)$row['id'];
      $nm = isset($row['nm']) ? trim((string)$row['nm']) : '';
      $cd = isset($row['cd']) ? trim((string)$row['cd']) : '';
      $labelRaw = $nm !== '' ? $nm : ($cd !== '' ? $cd : ('Group #'.$id));
      $label = htmlspecialchars($labelRaw, ENT_QUOTES, 'UTF-8');
      $options .= '<option value="'.$id.'">'.$label.' (ID '.$id.')</option>';
    }
    mysqli_stmt_close($st);
  }
}
if ($rows === 0) {
  $options .= '<option value="" disabled>(No groups found)</option>';
}

echo $options;
