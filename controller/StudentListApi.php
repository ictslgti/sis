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
// Optional: filter students by selected hostel's gender
$hostelId = isset($_GET['hostel_id']) ? trim($_GET['hostel_id']) : '';
// Resolve hostel gender if provided (Male/Female/Mixed)
$hostelGender = '';
if ($hostelId !== '') {
  if ($stG = mysqli_prepare($con, "SELECT `gender` FROM `hostels` WHERE `id` = ? LIMIT 1")) {
    mysqli_stmt_bind_param($stG, 'i', $hostelId);
    if (mysqli_stmt_execute($stG)) {
      $rsG = mysqli_stmt_get_result($stG);
      if ($rsG) {
        $rowG = mysqli_fetch_assoc($rsG);
        if ($rowG && isset($rowG['gender'])) { $hostelGender = $rowG['gender']; }
      }
    }
    mysqli_stmt_close($stG);
  }
}

$sql = "SELECT DISTINCT s.`student_id`, s.`student_fullname`, s.`student_gender`, d.`department_id`, d.`department_name`
        FROM `student` s
        LEFT JOIN `student_enroll` se ON se.`student_id` = s.`student_id`
        LEFT JOIN `course` c ON c.`course_id` = se.`course_id`
        LEFT JOIN `department` d ON d.`department_id` = c.`department_id`";
$where = [];
$params = [];
$types  = '';

// Exclude inactive students
$where[] = '(s.student_status IS NULL OR (s.student_status != \'Inactive\' AND s.student_status != 0))';

if ($dept !== '') { $where[] = 'd.department_id = ?'; $params[] = $dept; $types .= 's'; }
if ($q !== '') { $where[] = '(s.student_id LIKE ? OR s.student_fullname LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }
// Apply gender filter based on hostel if specified and gender is strict
if ($hostelGender === 'Male' || $hostelGender === 'Female') {
  $where[] = 's.student_gender = ?';
  $params[] = $hostelGender;
  $types .= 's';
}

if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY s.`student_fullname`, s.`student_id`';

$data = [];
if ($types !== '') {
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, $types, ...$params);
    if (!mysqli_stmt_execute($st)) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'message' => 'Query failed', 'error' => mysqli_error($con)]);
      exit;
    }
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($row = mysqli_fetch_assoc($rs))) { $data[] = $row; }
    mysqli_stmt_close($st);
  } else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Prepare failed', 'error' => mysqli_error($con)]);
    exit;
  }
} else {
  $rs = mysqli_query($con, $sql);
  if ($rs === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Query failed', 'error' => mysqli_error($con)]);
    exit;
  }
  while ($rs && ($row = mysqli_fetch_assoc($rs))) { $data[] = $row; }
}

echo json_encode($data);
