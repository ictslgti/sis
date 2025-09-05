<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if (!isset($_GET['get_geo_data'])) {
  echo json_encode(['status' => 'error', 'message' => 'Missing get_geo_data']);
  exit;
}

$status = isset($_GET['status']) ? trim($_GET['status']) : 'Following';
$conduct = isset($_GET['conduct']) ? strtolower(trim($_GET['conduct'])) : 'accepted';
$allowed = ['Following','Completed','Dropout','Active','All'];
if (!in_array($status, $allowed, true)) { $status = 'Following'; }

$year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
if ($year === '') {
  if ($rs = mysqli_query($con, "SELECT academic_year FROM academic WHERE academic_year_status='Active' ORDER BY academic_year DESC LIMIT 1")) {
    if ($r = mysqli_fetch_row($rs)) { $year = $r[0] ?? ''; }
    mysqli_free_result($rs);
  }
}

$yearCond = $year !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $year) . "'") : '';
$statusCond = '';
if ($status !== 'All') {
  $statusCond = " AND e.student_enroll_status='" . mysqli_real_escape_string($con, $status) . "'";
}
$accCase = ($conduct === 'accepted') ? " AND s.student_conduct_accepted_at IS NOT NULL" : '';

// Province-wise counts
$sqlProv = "
  SELECT 
    COALESCE(NULLIF(TRIM(s.student_provice), ''), 'Unknown') AS province,
    SUM(CASE WHEN s.student_gender='Male'   " . $accCase . " THEN 1 ELSE 0 END) AS male,
    SUM(CASE WHEN s.student_gender='Female' " . $accCase . " THEN 1 ELSE 0 END) AS female,
    SUM(CASE WHEN 1=1 " . $accCase . " THEN 1 ELSE 0 END) AS total
  FROM student s
  JOIN student_enroll e ON e.student_id = s.student_id " . $statusCond . $yearCond . "
  GROUP BY province
  ORDER BY province ASC";

$resProv = mysqli_query($con, $sqlProv);
if (!$resProv) {
  echo json_encode(['status' => 'error', 'message' => 'Province query failed', 'error' => mysqli_error($con)]);
  exit;
}
$province = [];
while ($row = mysqli_fetch_assoc($resProv)) {
  $province[] = [
    'province' => $row['province'],
    'male' => (int)($row['male'] ?? 0),
    'female' => (int)($row['female'] ?? 0),
    'total' => (int)($row['total'] ?? 0),
  ];
}

// District-wise counts
$sqlDist = "
  SELECT 
    COALESCE(NULLIF(TRIM(s.student_district), ''), 'Unknown') AS district,
    SUM(CASE WHEN s.student_gender='Male'   " . $accCase . " THEN 1 ELSE 0 END) AS male,
    SUM(CASE WHEN s.student_gender='Female' " . $accCase . " THEN 1 ELSE 0 END) AS female,
    SUM(CASE WHEN 1=1 " . $accCase . " THEN 1 ELSE 0 END) AS total
  FROM student s
  JOIN student_enroll e ON e.student_id = s.student_id " . $statusCond . $yearCond . "
  GROUP BY district
  ORDER BY total DESC, district ASC
  LIMIT 20"; // Top 20 for readability

$resDist = mysqli_query($con, $sqlDist);
if (!$resDist) {
  echo json_encode(['status' => 'error', 'message' => 'District query failed', 'error' => mysqli_error($con)]);
  exit;
}
$district = [];
while ($row = mysqli_fetch_assoc($resDist)) {
  $district[] = [
    'district' => $row['district'],
    'male' => (int)($row['male'] ?? 0),
    'female' => (int)($row['female'] ?? 0),
    'total' => (int)($row['total'] ?? 0),
  ];
}

// Optional: normalize province numeric codes to names
$provMap = [
  '1' => 'Northern',
  '2' => 'Eastern',
  '3' => 'Western',
  '4' => 'Southern',
  '5' => 'Central',
  '6' => 'North Western',
  '7' => 'Uva',
  '8' => 'North Central',
  '9' => 'Sabaragamuwa',
];
foreach ($province as &$p) {
  $key = $p['province'];
  if (isset($provMap[$key])) { $p['province'] = $provMap[$key]; }
}
unset($p);

echo json_encode([
  'status' => 'success',
  'data' => [
    'province' => $province,
    'district' => $district,
  ]
]);
