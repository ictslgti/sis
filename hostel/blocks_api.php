<?php
// hostel/blocks_api.php - returns JSON blocks for a hostel
require_once(__DIR__ . '/../config.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
if (!isset($_GET['hostel_id'])) { echo json_encode([]); exit; }
$hid = (int)$_GET['hostel_id'];

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

$params = [$hid];
$types  = 'i';
$condParts = [];
$condParts[] = "b.hostel_id=?";

// Build gender conditions only if we have any gender context
$want = [];
foreach ([$wardenGender, $studentGender] as $g) { foreach ($expand($g) as $v) { $want[$v] = true; } }
$wantList = array_keys($want);

if (!empty($wantList)) {
  // If we have a desired gender context, allow those and also Mixed
  $place = implode(',', array_fill(0, count($wantList), '?'));
  $genderConds = ["h.gender='Mixed'", "h.gender IN ($place)"];
  $sql = "SELECT b.id, b.name FROM hostel_blocks b INNER JOIN hostels h ON h.id=b.hostel_id WHERE ".implode(' AND ', $condParts)." AND (".implode(' OR ', $genderConds).") ORDER BY b.name";
  $stmt = mysqli_prepare($con, $sql);
  if ($stmt) {
    foreach ($wantList as $v) { $params[] = $v; $types .= 's'; }
    mysqli_stmt_bind_param($stmt, $types, ...$params);
  }
} else {
  // No gender filtering -> return all blocks for the hostel
  $sql = "SELECT b.id, b.name FROM hostel_blocks b INNER JOIN hostels h ON h.id=b.hostel_id WHERE ".implode(' AND ', $condParts)." ORDER BY b.name";
  $stmt = mysqli_prepare($con, $sql);
  if ($stmt) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
}

$out = [];
if ($stmt) {
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($res && $row = mysqli_fetch_assoc($res)) { $out[] = ['id'=>(int)$row['id'], 'name'=>$row['name']]; }
  mysqli_stmt_close($stmt);
}
echo json_encode($out);
