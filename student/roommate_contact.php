<?php
// student/roommate_contact.php - returns JSON emergency + contact details of a roommate
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

// Must be logged-in student
if (!isset($_SESSION['user_table']) || $_SESSION['user_table'] !== 'student') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'Forbidden']);
  exit;
}
$me = $_SESSION['user_name'] ?? '';
$target = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
if ($me === '' || $target === '') { http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Invalid request']); exit; }

// Verify both are in the same active room
$sql = "SELECT a1.room_id FROM hostel_allocations a1
        JOIN hostel_allocations a2 ON a2.room_id = a1.room_id AND a2.status='active'
        WHERE a1.student_id = ? AND a1.status = 'active' AND a2.student_id = ? LIMIT 1";
$sameRoom = false; $roomId = null;
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st, 'ss', $me, $target);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  if ($rs && ($row = mysqli_fetch_assoc($rs))) { $sameRoom = true; $roomId = $row['room_id'] ?? null; }
  mysqli_stmt_close($st);
}
if (!$sameRoom) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Not authorized to view this contact']); exit; }

// Fetch roommate contact and emergency details
$info = null;
$q = "SELECT s.student_id, s.student_fullname, s.student_ininame, s.student_phone, s.student_email, s.student_whatsapp,
             s.student_em_name, s.student_em_phone, s.student_em_address, s.student_em_relation
      FROM student s WHERE s.student_id = ? LIMIT 1";
if ($st = mysqli_prepare($con, $q)) {
  mysqli_stmt_bind_param($st, 's', $target);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  if ($rs) { $info = mysqli_fetch_assoc($rs) ?: null; }
  mysqli_stmt_close($st);
}
if (!$info) { http_response_code(404); echo json_encode(['ok'=>false,'message'=>'Not found']); exit; }

echo json_encode(['ok'=>true,'room_id'=>$roomId,'contact'=>$info]);
