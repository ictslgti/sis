<?php
// controller/StudentListApi.php - returns JSON list of students with department and gender
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'])) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'message' => 'Forbidden']);
  exit;
}

// Optional filters (not strictly used by current UI)
$dept = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$q    = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT s.student_id, s.student_fullname, s.student_gender, d.department_id, d.department_name
        FROM student s
        LEFT JOIN student_enroll se ON se.student_id = s.student_id
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id";
$where = [];
$params = [];
$types  = '';

if ($dept !== '') { $where[] = 'd.department_id = ?'; $params[] = $dept; $types .= 's'; }
if ($q !== '') { $where[] = '(s.student_id LIKE ? OR s.student_fullname LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }

if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' GROUP BY s.student_id ORDER BY s.student_fullname, s.student_id';

$data = [];
if ($types !== '') {
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($row = mysqli_fetch_assoc($rs))) { $data[] = $row; }
    mysqli_stmt_close($st);
  }
} else {
  $rs = mysqli_query($con, $sql);
  while ($rs && ($row = mysqli_fetch_assoc($rs))) { $data[] = $row; }
}

echo json_encode($data);
