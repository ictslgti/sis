<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

// Params
$year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
if ($year === '') {
  if ($rs = mysqli_query($con, "SELECT academic_year FROM academic WHERE academic_year_status='Active' ORDER BY academic_year DESC LIMIT 1")) {
    if ($r = mysqli_fetch_row($rs)) { $year = $r[0] ?? ''; }
    mysqli_free_result($rs);
  }
}

$yearCond = $year !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $year) . "'") : '';

// Get top districts overall to use as X-axis labels
// If limit <= 0, list ALL matching districts (no LIMIT clause)
$topLimitParam = isset($_GET['limit']) ? intval($_GET['limit']) : 8;
$limitClause = '';
if ($topLimitParam > 0) {
  $topLimit = max(3, $topLimitParam);
  $limitClause = " LIMIT $topLimit";
}
$sqlTopDist = "
  SELECT TRIM(s.student_district) AS district, COUNT(DISTINCT s.student_id) AS total
  FROM student s
  JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status IN ('Following','Active') $yearCond
  JOIN course c ON c.course_id = e.course_id
  JOIN department d ON d.department_id = c.department_id
  WHERE s.student_district IS NOT NULL AND TRIM(s.student_district) <> ''
        AND LOWER(TRIM(d.department_name)) NOT IN ('admin','administration')
  GROUP BY TRIM(s.student_district)
  ORDER BY total DESC, district ASC
  $limitClause";

$labels = [];
if ($rs = mysqli_query($con, $sqlTopDist)) {
  while ($row = mysqli_fetch_assoc($rs)) { $labels[] = $row['district']; }
  mysqli_free_result($rs);
}
if (!$labels) {
  echo json_encode(['status' => 'error', 'message' => 'No districts found']);
  exit;
}

// Get department totals to choose top departments
$sqlDeptTotals = "
  SELECT d.department_id, d.department_name, COUNT(DISTINCT s.student_id) AS total
  FROM student s
  JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status IN ('Following','Active') $yearCond
  JOIN course c ON c.course_id = e.course_id
  JOIN department d ON d.department_id = c.department_id
  WHERE s.student_district IS NOT NULL AND TRIM(s.student_district) <> ''
        AND LOWER(TRIM(d.department_name)) NOT IN ('admin','administration')
  GROUP BY d.department_id, d.department_name
  ORDER BY total DESC, d.department_name ASC
  LIMIT 6"; // limit number of series

$topDepartments = [];
if ($rs = mysqli_query($con, $sqlDeptTotals)) {
  while ($row = mysqli_fetch_assoc($rs)) {
    $topDepartments[] = [
      'id' => $row['department_id'],
      'name' => $row['department_name'],
    ];
  }
  mysqli_free_result($rs);
}
if (!$topDepartments) {
  echo json_encode(['status' => 'error', 'message' => 'No departments found']);
  exit;
}

// Fetch counts grouped by department and district
$deptIdsIn = implode(',', array_map(function($d) use ($con){
  return "'" . mysqli_real_escape_string($con, $d['id']) . "'";
}, $topDepartments));

// Build a district IN list to restrict to labels
$distIn = implode(',', array_map(function($d) use ($con){
  return "'" . mysqli_real_escape_string($con, $d) . "'";
}, $labels));

$sqlMatrix = "
  SELECT d.department_id, d.department_name, TRIM(s.student_district) AS district, COUNT(DISTINCT s.student_id) AS total
  FROM student s
  JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status IN ('Following','Active') $yearCond
  JOIN course c ON c.course_id = e.course_id
  JOIN department d ON d.department_id = c.department_id
  WHERE s.student_district IS NOT NULL AND TRIM(s.student_district) <> ''
        AND TRIM(s.student_district) IN ($distIn)
        AND d.department_id IN ($deptIdsIn)
  GROUP BY d.department_id, d.department_name, TRIM(s.student_district)
";

$matrix = [];
if ($rs = mysqli_query($con, $sqlMatrix)) {
  while ($row = mysqli_fetch_assoc($rs)) {
    $dep = $row['department_id'];
    $dist = $row['district'];
    $matrix[$dep][$dist] = (int)$row['total'];
  }
  mysqli_free_result($rs);
}

// Build datasets aligning with labels
$datasets = [];
foreach ($topDepartments as $i => $dep) {
  $data = [];
  foreach ($labels as $label) {
    $data[] = isset($matrix[$dep['id']][$label]) ? $matrix[$dep['id']][$label] : 0;
  }
  $datasets[] = [
    'label' => $dep['name'],
    'data' => $data,
  ];
}

echo json_encode([
  'status' => 'success',
  'data' => [
    'labels' => $labels,
    'datasets' => $datasets,
    'meta' => [
      'academic_year' => $year,
    ],
  ],
]);
