<?php
// controller/HostelAllocate.php - allocate a student to a room if capacity allows
require_once(__DIR__ . '/../config.php');

function redirect_back_alloc($params = []){
  $base = defined('APP_BASE') ? APP_BASE : '';
  $dest = isset($_GET['back']) ? $_GET['back'] : '/hostel/Requests.php';
  $qs = http_build_query($params);
  header('Location: ' . $base . $dest . ($qs ? ('?' . $qs) : ''));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; exit; }
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'])) { http_response_code(403); echo 'Forbidden'; exit; }

$student_id   = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$room_id      = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$allocated_at = isset($_POST['allocated_at']) ? $_POST['allocated_at'] : date('Y-m-d');
$leaving_at   = isset($_POST['leaving_at']) && $_POST['leaving_at'] !== '' ? $_POST['leaving_at'] : null;

if ($student_id === '' || $room_id <= 0) { redirect_back_alloc(['err'=>'invalid']); }

// Ensure request is paid
$st = mysqli_prepare($con, "SELECT status FROM hostel_requests WHERE student_id=?");
mysqli_stmt_bind_param($st, 's', $student_id);
mysqli_stmt_execute($st);
mysqli_stmt_bind_result($st, $status);
$found = mysqli_stmt_fetch($st);
mysqli_stmt_close($st);
if (!$found || $status !== 'paid') { redirect_back_alloc(['err'=>'status']); }

// Capacity check: get capacity and current active count
$q = mysqli_query($con, "SELECT capacity FROM hostel_rooms WHERE id=".(int)$room_id);
$room = $q ? mysqli_fetch_assoc($q) : null;
if (!$room) { redirect_back_alloc(['err'=>'room']); }
$cap = (int)$room['capacity'];

$q2 = mysqli_query($con, "SELECT COUNT(*) AS occupied FROM hostel_allocations WHERE room_id=".(int)$room_id." AND status='active'");
$occRow = $q2 ? mysqli_fetch_assoc($q2) : ['occupied'=>0];
$occ = (int)$occRow['occupied'];
if ($occ >= $cap) { redirect_back_alloc(['err'=>'full']); }

// Fetch student's gender
$stuGender = null;
if ($stS = mysqli_prepare($con, "SELECT student_gender FROM student WHERE student_id=? LIMIT 1")) {
  mysqli_stmt_bind_param($stS, 's', $student_id);
  mysqli_stmt_execute($stS);
  $rsS = mysqli_stmt_get_result($stS);
  if ($rsS) { $rowS = mysqli_fetch_assoc($rsS); if ($rowS && isset($rowS['student_gender'])) { $stuGender = $rowS['student_gender']; } }
  mysqli_stmt_close($stS);
}

// Find hostel gender for the selected room
$sqlHg = "SELECT h.gender FROM hostels h INNER JOIN hostel_blocks b ON b.hostel_id=h.id INNER JOIN hostel_rooms r ON r.block_id=b.id WHERE r.id=? LIMIT 1";
$hostelGender = null;
if ($stH = mysqli_prepare($con, $sqlHg)) {
  mysqli_stmt_bind_param($stH, 'i', $room_id);
  mysqli_stmt_execute($stH);
  $rsH = mysqli_stmt_get_result($stH);
  $hostel = $rsH ? mysqli_fetch_assoc($rsH) : null;
  mysqli_stmt_close($stH);
  if (!$hostel) { redirect_back_alloc(['err'=>'room']); }
  $hostelGender = $hostel['gender'];
}

// Enforce student-hostel gender compatibility: Mixed or matches student's gender
if ($hostelGender && $stuGender && !($hostelGender === 'Mixed' || $hostelGender === $stuGender)) {
  redirect_back_alloc(['err'=>'stu_gender']);
}

// If WAR, enforce gender-based hostel restriction (hostel gender must be Mixed or match warden's gender)
if ($_SESSION['user_type'] === 'WAR') {
  // Get warden gender
  $wardenGender = null;
  if (!empty($_SESSION['user_name'])) {
    if ($stG = mysqli_prepare($con, "SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1")) {
      mysqli_stmt_bind_param($stG, 's', $_SESSION['user_name']);
      mysqli_stmt_execute($stG);
      $rsG = mysqli_stmt_get_result($stG);
      if ($rsG) { $rg = mysqli_fetch_assoc($rsG); if ($rg && isset($rg['staff_gender'])) { $wardenGender = $rg['staff_gender']; } }
      mysqli_stmt_close($stG);
    }
  }
  if ($wardenGender) {
    if (!($hostelGender === 'Mixed' || $hostelGender === $wardenGender)) {
      redirect_back_alloc(['err'=>'gender']);
    }
  }
}

// Deactivate any existing active allocation for this student
mysqli_query($con, "UPDATE hostel_allocations SET status='left' WHERE student_id='".mysqli_real_escape_string($con, $student_id)."' AND status='active'");

// Insert allocation
$ins = mysqli_prepare($con, "INSERT INTO hostel_allocations (student_id, room_id, allocated_at, leaving_at, status) VALUES (?, ?, ?, ?, 'active')");
if (!$ins) { redirect_back_alloc(['err'=>'db']); }
mysqli_stmt_bind_param($ins, 'siss', $student_id, $room_id, $allocated_at, $leaving_at);
$ok = mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

if ($ok) {
  // Mark request allocated
  $up = mysqli_prepare($con, "UPDATE hostel_requests SET status='allocated' WHERE student_id=?");
  if ($up) { mysqli_stmt_bind_param($up, 's', $student_id); mysqli_stmt_execute($up); mysqli_stmt_close($up); }
  redirect_back_alloc(['ok'=>1]);
} else {
  redirect_back_alloc(['err'=>'save']);
}
