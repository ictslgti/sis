<?php
// controller/HostelsList.php
// Returns JSON list of active hostels, optionally filtered by gender (Male/Female/Mixed)
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_type'])) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Unauthorized']); exit; }

$gender = isset($_GET['gender']) ? trim($_GET['gender']) : '';
$params = [];
$types = '';
$sql = 'SELECT id, name, gender FROM hostels WHERE active=1';
if ($gender !== '') {
  // Expand synonyms as stored in DB
  $list = [];
  if (strcasecmp($gender, 'Male') === 0) {
    $list = ['Male','Boys','Boy'];
  } elseif (strcasecmp($gender, 'Female') === 0) {
    $list = ['Female','Girls','Girl','Ladies'];
  } elseif (strcasecmp($gender, 'Mixed') === 0) {
    $list = ['Mixed'];
  }
  if (!empty($list)) {
    $place = implode(',', array_fill(0, count($list), '?'));
    $sql .= " AND gender IN ($place)";
    $params = array_merge($params, $list);
    $types .= str_repeat('s', count($list));
  }
}
$sql .= ' ORDER BY name';

$list = [];
if ($st = mysqli_prepare($con, $sql)) {
  if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
  if (!mysqli_stmt_execute($st)) { http_response_code(500); echo json_encode(['ok'=>false,'message'=>'Query failed','error'=>mysqli_error($con)]); exit; }
  $rs = mysqli_stmt_get_result($st);
  while ($rs && ($row = mysqli_fetch_assoc($rs))) { $list[] = $row; }
  mysqli_stmt_close($st);
  echo json_encode(['ok'=>true,'hostels'=>$list]);
  exit;
}
http_response_code(500);
echo json_encode(['ok'=>false,'message'=>'Prepare failed','error'=>mysqli_error($con)]);
