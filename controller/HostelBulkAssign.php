<?php
// controller/HostelBulkAssign.php - process bulk hostel allocations
require_once __DIR__ . '/../config.php';

function go_back($params = []){
  $base = defined('APP_BASE') ? APP_BASE : '';
  $dest = '/hostel/BulkRoomAssign.php';
  $qs = http_build_query($params);
  header('Location: ' . $base . $dest . ($qs ? ('?' . $qs) : ''));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; exit; }
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'])) { http_response_code(403); echo 'Forbidden'; exit; }

$hostel_id    = isset($_POST['hostel_id']) ? (int)$_POST['hostel_id'] : 0;
$block_id     = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
$room_id      = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$allocated_at = isset($_POST['allocated_at']) ? $_POST['allocated_at'] : date('Y-m-d');
$leaving_at   = isset($_POST['leaving_at']) && $_POST['leaving_at'] !== '' ? $_POST['leaving_at'] : null;
$student_ids  = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];

if ($hostel_id<=0 || $block_id<=0 || $room_id<=0 || empty($student_ids)) { go_back(['msg'=>'Invalid form submission.']); }

// Fetch capacity and current occupied for the selected room
$room = null;
if ($st = mysqli_prepare($con, 'SELECT r.capacity, (SELECT COUNT(*) FROM hostel_allocations a WHERE a.room_id=r.id AND a.status=\'active\') AS occupied FROM hostel_rooms r WHERE r.id=?')){
  mysqli_stmt_bind_param($st, 'i', $room_id);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $room = $rs ? mysqli_fetch_assoc($rs) : null;
  mysqli_stmt_close($st);
}
if (!$room) { go_back(['msg'=>'Room not found']); }
$capacity = (int)$room['capacity'];
$occupied = (int)$room['occupied'];
$available = max(0, $capacity - $occupied);

if ($available <= 0) { go_back(['msg'=>'Room is already full.']); }

// Determine hostel gender
$hostelGender = null;
if ($stH = mysqli_prepare($con, 'SELECT h.gender FROM hostels h INNER JOIN hostel_blocks b ON b.hostel_id=h.id INNER JOIN hostel_rooms r ON r.block_id=b.id WHERE r.id=? LIMIT 1')){
  mysqli_stmt_bind_param($stH, 'i', $room_id);
  mysqli_stmt_execute($stH);
  $rsH = mysqli_stmt_get_result($stH);
  $hrow = $rsH ? mysqli_fetch_assoc($rsH) : null;
  mysqli_stmt_close($stH);
  if ($hrow) { $hostelGender = $hrow['gender']; }
}

// If WAR, enforce warden gender restriction
if ($_SESSION['user_type'] === 'WAR' && $hostelGender && $hostelGender !== 'Mixed') {
  $wardenGender = null;
  if (!empty($_SESSION['user_name'])) {
    if ($stG = mysqli_prepare($con, 'SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1')){
      mysqli_stmt_bind_param($stG, 's', $_SESSION['user_name']);
      mysqli_stmt_execute($stG);
      $rsG = mysqli_stmt_get_result($stG);
      $rg = $rsG ? mysqli_fetch_assoc($rsG) : null;
      mysqli_stmt_close($stG);
      if ($rg && isset($rg['staff_gender'])) { $wardenGender = $rg['staff_gender']; }
    }
  }
  if ($wardenGender && strcasecmp($wardenGender, $hostelGender) !== 0) {
    go_back(['msg'=>'Selected room is not allowed for your ward.']);
  }
}

$ok = 0; $fail = 0; $messages = [];

foreach ($student_ids as $sidRaw) {
  $sid = trim($sidRaw);
  if ($sid === '') { $fail++; $messages[] = "Skipped an empty student id"; continue; }

  // Stop if no more slots
  if ($occupied >= $capacity) { $messages[] = 'Room reached capacity; remaining students skipped.'; break; }

  // Student gender
  $stuGender = null;
  if ($stS = mysqli_prepare($con, 'SELECT student_gender FROM student WHERE student_id=? LIMIT 1')){
    mysqli_stmt_bind_param($stS, 's', $sid);
    mysqli_stmt_execute($stS);
    $rsS = mysqli_stmt_get_result($stS);
    $sr = $rsS ? mysqli_fetch_assoc($rsS) : null;
    mysqli_stmt_close($stS);
    if ($sr && isset($sr['student_gender'])) { $stuGender = $sr['student_gender']; }
  }

  // Gender compatibility
  if ($hostelGender && $stuGender && !($hostelGender === 'Mixed' || strcasecmp($hostelGender,$stuGender)===0)) {
    $fail++; $messages[] = "$sid: gender incompatible"; continue; }

  // End any existing active allocation for this student
  mysqli_query($con, "UPDATE hostel_allocations SET status='left' WHERE student_id='".mysqli_real_escape_string($con, $sid)."' AND status='active'");

  // Insert
  if ($ins = mysqli_prepare($con, "INSERT INTO hostel_allocations (student_id, room_id, allocated_at, leaving_at, status) VALUES (?, ?, ?, ?, 'active')")){
    mysqli_stmt_bind_param($ins, 'siss', $sid, $room_id, $allocated_at, $leaving_at);
    $saved = mysqli_stmt_execute($ins);
    mysqli_stmt_close($ins);
    if ($saved) { $ok++; $occupied++; }
    else { $fail++; $messages[] = "$sid: save failed"; }
  } else { $fail++; $messages[] = "$sid: db error"; }
}

$summary = implode('; ', $messages);

go_back(['ok'=>$ok, 'fail'=>$fail, 'msg'=>$summary]);
