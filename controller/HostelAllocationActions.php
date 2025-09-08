<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
// controller/HostelAllocationActions.php
// JSON endpoint to move a student between rooms or mark as left
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'], true)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'message' => 'Forbidden']);
  exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

function json_fail($msg, $code = 400){ http_response_code($code); echo json_encode(['ok'=>false,'message'=>$msg]); exit; }
function db_err(){ global $con; json_fail('DB error: '.mysqli_error($con), 500); }

if ($action === 'leave') {
  $studentId = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
  $roomId    = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
  $date      = isset($_POST['leaving_at']) && $_POST['leaving_at'] !== '' ? $_POST['leaving_at'] : date('Y-m-d');
  if ($studentId === '' || $roomId <= 0) json_fail('Missing parameters');

  if (!$st = mysqli_prepare($con, "UPDATE `hostel_allocations` SET `status`='left', `leaving_at`=? WHERE `student_id`=? AND `room_id`=? AND `status`='active'")) db_err();
  mysqli_stmt_bind_param($st, 'ssi', $date, $studentId, $roomId);
  if (!mysqli_stmt_execute($st)) db_err();
  $affected = mysqli_stmt_affected_rows($st);
  mysqli_stmt_close($st);
  echo json_encode(['ok'=>true,'updated'=>$affected, 'message' => 'Student marked as left successfully.']);
  exit;
}

if ($action === 'move') {
  $studentId = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
  $fromRoom  = isset($_POST['from_room_id']) ? (int)$_POST['from_room_id'] : 0;
  $toRoom    = isset($_POST['to_room_id']) ? (int)$_POST['to_room_id'] : 0;
  $today     = date('Y-m-d');
  if ($studentId === '' || $fromRoom <= 0 || $toRoom <= 0) json_fail('Missing parameters');

  mysqli_begin_transaction($con);
  try {
    // Ensure an active allocation exists in fromRoom
    if (!$st = mysqli_prepare($con, "SELECT `id` FROM `hostel_allocations` WHERE `student_id`=? AND `room_id`=? AND `status`='active' LIMIT 1")) throw new Exception(mysqli_error($con));
    mysqli_stmt_bind_param($st, 'si', $studentId, $fromRoom);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $alloc = mysqli_fetch_assoc($rs);
    mysqli_stmt_close($st);
    if (!$alloc) throw new Exception('Active allocation not found in source room');

    // Capacity and gender checks for target room
    $sqlTarget = "SELECT r.`id`, r.`capacity`, 
                         (SELECT COUNT(*) FROM `hostel_allocations` a WHERE a.`room_id`=r.`id` AND a.`status`='active') AS occupied,
                         h.`gender` AS hostel_gender
                  FROM `hostel_rooms` r
                  LEFT JOIN `hostel_blocks` b ON b.`id`=r.`block_id`
                  LEFT JOIN `hostels` h ON h.`id`=b.`hostel_id`
                  WHERE r.`id`=? LIMIT 1";
    if (!$st = mysqli_prepare($con, $sqlTarget)) throw new Exception(mysqli_error($con));
    mysqli_stmt_bind_param($st, 'i', $toRoom);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $target = mysqli_fetch_assoc($rs) ?: null;
    mysqli_stmt_close($st);
    if (!$target) throw new Exception('Target room not found');
    if ((int)$target['occupied'] >= (int)$target['capacity']) throw new Exception('Target room is full');

    // Student gender
    if (!$st = mysqli_prepare($con, "SELECT `student_gender` FROM `student` WHERE `student_id`=? LIMIT 1")) throw new Exception(mysqli_error($con));
    mysqli_stmt_bind_param($st, 's', $studentId);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $sg = mysqli_fetch_assoc($rs);
    mysqli_stmt_close($st);
    $studentGender = $sg ? ($sg['student_gender'] ?? '') : '';
    $hostelGender = $target['hostel_gender'] ?? 'Mixed';
    if (($hostelGender === 'Male' && $studentGender !== 'Male') || ($hostelGender === 'Female' && $studentGender !== 'Female')) {
      throw new Exception('Student gender not allowed in target hostel');
    }

    // End current allocation
    if (!$st = mysqli_prepare($con, "UPDATE `hostel_allocations` SET `status`='left', `leaving_at`=? WHERE `id`=?")) throw new Exception(mysqli_error($con));
    mysqli_stmt_bind_param($st, 'si', $today, $alloc['id']);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_error($con));
    mysqli_stmt_close($st);

    // Create new allocation in target room
    if (!$st = mysqli_prepare($con, "INSERT INTO `hostel_allocations`(`student_id`,`room_id`,`allocated_at`,`status`) VALUES (?,?,?,'active')")) throw new Exception(mysqli_error($con));
    mysqli_stmt_bind_param($st, 'sis', $studentId, $toRoom, $today);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_error($con));
    mysqli_stmt_close($st);

    mysqli_commit($con);
    echo json_encode(['ok'=>true, 'message' => 'Student moved successfully.']);
  } catch (Exception $ex) {
    mysqli_rollback($con);
    http_response_code(400);
    echo json_encode(['ok'=>false, 'message'=>$ex->getMessage()]);
  }
  exit;
}

json_fail('Invalid action');
