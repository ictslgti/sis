<?php
// controller/HostelSwapRooms.php - perform room swap between two active allocations
require_once(__DIR__ . '/../config.php');

function redirect_back_swap($params = []){
  $base = defined('APP_BASE') ? APP_BASE : '';
  $dest = '/hostel/SwapRooms.php';
  $qs = http_build_query($params);
  header('Location: ' . $base . $dest . ($qs ? ('?' . $qs) : ''));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method Not Allowed'; exit; }
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','WAR'])) { http_response_code(403); echo 'Forbidden'; exit; }

$allocA = isset($_POST['alloc_a']) ? (int)$_POST['alloc_a'] : 0;
$allocB = isset($_POST['alloc_b']) ? (int)$_POST['alloc_b'] : 0;
$eff    = isset($_POST['effective_date']) && $_POST['effective_date'] !== '' ? $_POST['effective_date'] : date('Y-m-d');

if ($allocA <= 0 || $allocB <= 0 || $allocA === $allocB) { redirect_back_swap(['err'=>'invalid']); }

// Load both allocations
$st = mysqli_prepare($con, "SELECT a.id, a.student_id, a.room_id, a.status, s.student_gender,
                                  r.id AS room_id2, r.room_no, b.id AS block_id, h.id AS hostel_id, h.gender AS hostel_gender
                             FROM hostel_allocations a
                       INNER JOIN student s ON s.student_id=a.student_id
                       INNER JOIN hostel_rooms r ON r.id=a.room_id
                       INNER JOIN hostel_blocks b ON b.id=r.block_id
                       INNER JOIN hostels h ON h.id=b.hostel_id
                            WHERE a.id IN (?, ?) FOR UPDATE");
if (!$st) { redirect_back_swap(['err'=>'db']); }
mysqli_begin_transaction($con);
mysqli_stmt_bind_param($st, 'ii', $allocA, $allocB);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$rows = [];
while ($res && ($row = mysqli_fetch_assoc($res))) { $rows[] = $row; }
mysqli_stmt_close($st);
if (count($rows) !== 2) { mysqli_rollback($con); redirect_back_swap(['err'=>'notfound']); }

// Map A/B
$A = ($rows[0]['id'] == $allocA) ? $rows[0] : $rows[1];
$B = ($rows[0]['id'] == $allocB) ? $rows[0] : $rows[1];

// Validate active
if ($A['status'] !== 'active' || $B['status'] !== 'active') { mysqli_rollback($con); redirect_back_swap(['err'=>'status']); }

// Gender compatibility: student vs destination hostel
$destHostelGenderForA = $B['hostel_gender'];
$destHostelGenderForB = $A['hostel_gender'];
$stuAG = $A['student_gender'];
$stuBG = $B['student_gender'];
$okA = ($destHostelGenderForA === 'Mixed' || strcasecmp($destHostelGenderForA,$stuAG)===0);
$okB = ($destHostelGenderForB === 'Mixed' || strcasecmp($destHostelGenderForB,$stuBG)===0);
if (!$okA || !$okB) { mysqli_rollback($con); redirect_back_swap(['err'=>'stu_gender']); }

// If WAR, enforce warden gender restriction for both destination hostels
if ($_SESSION['user_type'] === 'WAR') {
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
    $gW = $wardenGender;
    $okW1 = ($A['hostel_gender'] === 'Mixed' || strcasecmp($A['hostel_gender'],$gW)===0);
    $okW2 = ($B['hostel_gender'] === 'Mixed' || strcasecmp($B['hostel_gender'],$gW)===0);
    if (!$okW1 || !$okW2) { mysqli_rollback($con); redirect_back_swap(['err'=>'gender']); }
  }
}

// Perform swap
$roomA = (int)$A['room_id'];
$roomB = (int)$B['room_id'];
$upd = mysqli_prepare($con, 'UPDATE hostel_allocations SET room_id=?, allocated_at=? WHERE id=?');
if (!$upd) { mysqli_rollback($con); redirect_back_swap(['err'=>'db2']); }

// Move A to roomB
mysqli_stmt_bind_param($upd, 'isi', $roomB, $eff, $allocA);
if (!mysqli_stmt_execute($upd)) { mysqli_stmt_close($upd); mysqli_rollback($con); redirect_back_swap(['err'=>'saveA']); }
// Move B to roomA
mysqli_stmt_bind_param($upd, 'isi', $roomA, $eff, $allocB);
if (!mysqli_stmt_execute($upd)) { mysqli_stmt_close($upd); mysqli_rollback($con); redirect_back_swap(['err'=>'saveB']); }

mysqli_stmt_close($upd);
mysqli_commit($con);
redirect_back_swap(['ok'=>1]);
