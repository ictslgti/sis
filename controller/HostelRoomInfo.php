<?php
// controller/HostelRoomInfo.php - returns JSON with room meta and allocated students
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Access: ADM, SAO, WAR
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'], true)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'message' => 'Forbidden']);
  exit;
}

$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
if ($roomId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'message' => 'Invalid room_id']);
  exit;
}

$room = null;
$students = [];

// Fetch room meta and current occupied count
$sqlRoom = "SELECT r.`id`, r.`room_no`, r.`capacity`, b.`name` AS block_name, h.`name` AS hostel_name,
              (SELECT COUNT(*) FROM `hostel_allocations` a WHERE a.`room_id`=r.`id` AND a.`status`='active') AS occupied
            FROM `hostel_rooms` r
            LEFT JOIN `hostel_blocks` b ON b.`id` = r.`block_id`
            LEFT JOIN `hostels` h ON h.`id` = b.`hostel_id`
            WHERE r.`id` = ? LIMIT 1";
if ($st = mysqli_prepare($con, $sqlRoom)) {
  mysqli_stmt_bind_param($st, 'i', $roomId);
  if (!mysqli_stmt_execute($st)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Query failed (room)', 'error' => mysqli_error($con)]);
    exit;
  }
  $rs = mysqli_stmt_get_result($st);
  $room = mysqli_fetch_assoc($rs) ?: null;
  mysqli_stmt_close($st);
}
if (!$room) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'message' => 'Room not found']);
  exit;
}

// Fetch allocated students in this room (active)
$sqlStud = "SELECT s.`student_id`, s.`student_ininame`, s.`student_fullname`, s.`student_gender`,
                  d.`department_name`, c.`course_name`
            FROM `hostel_allocations` a
            LEFT JOIN `student` s ON s.`student_id` = a.`student_id`
            LEFT JOIN (
              SELECT se.`student_id`, MAX(se.`student_enroll_date`) AS max_enroll_date
              FROM `student_enroll` se
              GROUP BY se.`student_id`
            ) le ON le.`student_id` = s.`student_id`
            LEFT JOIN `student_enroll` se2 ON se2.`student_id` = le.`student_id` AND se2.`student_enroll_date` = le.`max_enroll_date`
            LEFT JOIN `course` c ON c.`course_id` = se2.`course_id`
            LEFT JOIN `department` d ON d.`department_id` = c.`department_id`
            WHERE a.`room_id` = ? AND a.`status` = 'active'
            ORDER BY s.`student_ininame`, s.`student_fullname`, s.`student_id`";
if ($st = mysqli_prepare($con, $sqlStud)) {
  mysqli_stmt_bind_param($st, 'i', $roomId);
  if (!mysqli_stmt_execute($st)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Query failed (students)', 'error' => mysqli_error($con)]);
    exit;
  }
  $rs = mysqli_stmt_get_result($st);
  while ($rs && ($row = mysqli_fetch_assoc($rs))) { $students[] = $row; }
  mysqli_stmt_close($st);
}

echo json_encode(['ok' => true, 'room' => $room, 'students' => $students]);
