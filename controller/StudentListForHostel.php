<?php
// controller/StudentListForHostel.php
// Returns JSON array of students eligible for hostel allocation (not already actively allocated)
// Optional filters: department_id, course_id. WAR sees only same-gender students.

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'], true)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'message' => 'Forbidden']);
  exit;
}

$dept = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$course = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$hostelId = isset($_GET['hostel_id']) ? trim($_GET['hostel_id']) : '';
$gender = isset($_GET['gender']) ? trim($_GET['gender']) : '';
$userType = $_SESSION['user_type'];
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

$wardenGender = '';
if ($userType === 'WAR' && $userName !== '') {
  if ($st = mysqli_prepare($con, 'SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1')) {
    mysqli_stmt_bind_param($st, 's', $userName);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $r = $rs ? mysqli_fetch_assoc($rs) : null;
    if ($r && !empty($r['staff_gender'])) { $wardenGender = $r['staff_gender']; }
    mysqli_stmt_close($st);
  }
}

$sql = "SELECT DISTINCT s.`student_id`, s.`student_fullname`, s.`student_gender`, d.`department_id`, d.`department_name`, c.`course_id`, c.`course_name`
        FROM `student` s
        LEFT JOIN `student_enroll` se ON se.`student_id` = s.`student_id`
        LEFT JOIN `course` c ON c.`course_id` = se.`course_id`
        LEFT JOIN `department` d ON d.`department_id` = c.`department_id`
        LEFT JOIN `hostel_allocations` a ON a.`student_id` = s.`student_id` AND a.`status`='active'
        WHERE a.`id` IS NULL";
$params = [];
$types  = '';
if ($dept !== '') { $sql .= ' AND d.`department_id` = ?'; $params[] = $dept; $types .= 's'; }
if ($course !== '') { $sql .= ' AND c.`course_id` = ?'; $params[] = $course; $types .= 's'; }
if ($wardenGender !== '') { $sql .= ' AND s.`student_gender` = ?'; $params[] = $wardenGender; $types .= 's'; }
// Explicit gender filter from UI (overrides none; still combines with hostel gender if provided)
if ($gender !== '') { $sql .= ' AND s.`student_gender` = ?'; $params[] = $gender; $types .= 's'; }
// Optional: restrict by hostel gender
if ($hostelId !== '') {
  // Get hostel gender
  if ($stH = mysqli_prepare($con, 'SELECT `gender` FROM `hostels` WHERE `id`=? LIMIT 1')) {
    mysqli_stmt_bind_param($stH, 'i', $hostelId);
    if (mysqli_stmt_execute($stH)) {
      $rsH = mysqli_stmt_get_result($stH); $rH = $rsH ? mysqli_fetch_assoc($rsH) : null;
      $hg = $rH ? ($rH['gender'] ?? '') : '';
      if ($hg === 'Male' || $hg === 'Female') { $sql .= ' AND s.`student_gender` = ?'; $params[] = $hg; $types .= 's'; }
    }
    mysqli_stmt_close($stH);
  }
}
$sql .= ' ORDER BY s.`student_fullname`, s.`student_id`';

$data = [];
if ($st = mysqli_prepare($con, $sql)) {
  if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
  if (!mysqli_stmt_execute($st)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Query failed','error'=>mysqli_error($con)]);
    exit;
  }
  $rs = mysqli_stmt_get_result($st);
  while ($rs && ($row = mysqli_fetch_assoc($rs))) { $data[] = $row; }
  mysqli_stmt_close($st);
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'message'=>'Prepare failed','error'=>mysqli_error($con)]);
  exit;
}

echo json_encode(['ok'=>true,'students'=>$data]);
