<?php
// Read-only JSON endpoint: returns student personal and emergency info
// Access: ADM & SAO (all students), WAR (restricted to same-gender students)

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;

function respond($ok, $dataOrMsg = null, $http = 200) {
  if (!headers_sent()) { http_response_code($http); }
  if ($ok) {
    echo json_encode(['ok' => true, 'data' => $dataOrMsg]);
  } else {
    echo json_encode(['ok' => false, 'message' => $dataOrMsg]);
  }
  exit;
}

if (!$userType || !$userName) {
  respond(false, 'Unauthorized', 401);
}

if (!in_array($userType, ['ADM','SAO','WAR'], true)) {
  respond(false, 'Forbidden', 403);
}

$studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
if ($studentId === '') {
  respond(false, 'student_id is required', 400);
}

// Fetch warden gender if needed
$wardenGender = null;
if ($userType === 'WAR') {
  if ($st = mysqli_prepare($con, 'SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1')) {
    mysqli_stmt_bind_param($st, 's', $userName);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    if ($rs) {
      $r = mysqli_fetch_assoc($rs);
      if ($r && isset($r['staff_gender'])) { $wardenGender = $r['staff_gender']; }
    }
    mysqli_stmt_close($st);
  }
}

$sql = 'SELECT student_id, student_fullname, student_gender, student_email, student_phone, student_address, student_em_name, student_em_relation, student_em_phone FROM student WHERE student_id=?';
if ($userType === 'WAR' && $wardenGender) {
  $sql .= ' AND student_gender=?';
}

$result = null;
if ($stmt = mysqli_prepare($con, $sql)) {
  if ($userType === 'WAR' && $wardenGender) {
    mysqli_stmt_bind_param($stmt, 'ss', $studentId, $wardenGender);
  } else {
    mysqli_stmt_bind_param($stmt, 's', $studentId);
  }
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
} else {
  respond(false, 'Database error (prepare failed)', 500);
}

if (!$result) {
  respond(false, 'Database error', 500);
}

if (mysqli_num_rows($result) !== 1) {
  if ($userType === 'WAR') {
    // Either not found or filtered by gender
    respond(false, 'Not found or access denied', 404);
  }
  respond(false, 'Not found', 404);
}

$row = mysqli_fetch_assoc($result);

respond(true, [
  'student_id' => $row['student_id'],
  'student_fullname' => $row['student_fullname'],
  'student_gender' => $row['student_gender'],
  'student_email' => $row['student_email'],
  'student_phone' => $row['student_phone'],
  'student_address' => $row['student_address'],
  'student_em_name' => $row['student_em_name'],
  'student_em_relation' => $row['student_em_relation'],
  'student_em_phone' => $row['student_em_phone'],
]);
