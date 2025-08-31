<?php
// hostel/rooms_api.php - returns JSON rooms for a block including occupancy
require_once(__DIR__ . '/../config.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
if (!isset($_GET['block_id'])) { echo json_encode([]); exit; }
$bid = (int)$_GET['block_id'];

// Optional student gender from query
$studentGender = isset($_GET['student_gender']) ? trim($_GET['student_gender']) : null;
if ($studentGender === '') { $studentGender = null; }

// Determine warden gender if WAR
$wardenGender = null;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'WAR' && !empty($_SESSION['user_name'])) {
  if ($st = mysqli_prepare($con, "SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($st, 's', $_SESSION['user_name']);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    if ($rs) { $r = mysqli_fetch_assoc($rs); if ($r && isset($r['staff_gender'])) { $wardenGender = $r['staff_gender']; } }
    mysqli_stmt_close($st);
  }
}

// Helper: expand gender synonyms
$expand = function($g) {
  if (!$g) return [];
  $g = trim($g);
  if (strcasecmp($g,'Male')===0 || strcasecmp($g,'Boys')===0 || strcasecmp($g,'Boy')===0) return ['Male','Boys','Boy'];
  if (strcasecmp($g,'Female')===0 || strcasecmp($g,'Girls')===0 || strcasecmp($g,'Girl')===0 || strcasecmp($g,'Ladies')===0) return ['Female','Girls','Girl','Ladies'];
  if (strcasecmp($g,'Mixed')===0) return ['Mixed'];
  return [$g];
};

$params = [$bid];
$types  = 'i';

$genderConds = ["h.gender='Mixed'"]; // always allow Mixed
$want = [];
foreach ([$wardenGender, $studentGender] as $g) { foreach ($expand($g) as $v) { $want[$v] = true; } }
$wantList = array_keys($want);
if (!empty($wantList)) {
  $place = implode(',', array_fill(0, count($wantList), '?'));
  $genderConds[] = "h.gender IN ($place)";
  foreach ($wantList as $v) { $params[] = $v; $types .= 's'; }
}

$sql = "SELECT r.id, r.room_no, r.capacity,
               (SELECT COUNT(*) FROM hostel_allocations a WHERE a.room_id=r.id AND a.status='active') AS occupied
        FROM hostel_rooms r
        INNER JOIN hostel_blocks b ON b.id = r.block_id
        INNER JOIN hostels h ON h.id = b.hostel_id
        WHERE r.block_id = ? AND (".implode(' OR ', $genderConds).")
        ORDER BY r.room_no";

$stmt = mysqli_prepare($con, $sql);
if ($stmt) { mysqli_stmt_bind_param($stmt, $types, ...$params); }

if ($stmt) { mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt); }
$out = [];
while ($res && $row = mysqli_fetch_assoc($res)) {
  $out[] = [
    'id' => (int)$row['id'],
    'room_no' => $row['room_no'],
    'capacity' => (int)$row['capacity'],
    'occupied' => (int)$row['occupied']
  ];
}
echo json_encode($out);
