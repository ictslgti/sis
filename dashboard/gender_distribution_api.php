<?php
// Returns department-wise gender counts as JSON
// Query: ?get_gender_data=1 [&status=Following|Completed|Dropout|All] [&academic_year=YYYY/YYYY] [&conduct=accepted|all]

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

// simple guard
if (!isset($_GET['get_gender_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing get_gender_data']);
    exit;
}

$status = isset($_GET['status']) ? trim($_GET['status']) : 'Following';
$allowed = ['Following','Completed','Dropout','All'];
if (!in_array($status, $allowed, true)) { $status = 'Following'; }

// conduct filter: 'accepted' (default) counts only students who accepted Code of Conduct; 'all' counts everyone
$conduct = isset($_GET['conduct']) ? strtolower(trim($_GET['conduct'])) : 'accepted';
if (!in_array($conduct, ['accepted','all'], true)) { $conduct = 'accepted'; }

// Academic year filter: default to latest Active if not provided
$year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
if ($year === '') {
    if ($rs = mysqli_query($con, "SELECT academic_year FROM academic WHERE academic_year_status='Active' ORDER BY academic_year DESC LIMIT 1")) {
        if ($r = mysqli_fetch_row($rs)) { $year = $r[0] ?? ''; }
        mysqli_free_result($rs);
    }
}

// Build SQL with LEFT JOIN to include all departments
// Tables inferred: department(department_id, department_name), course(department_id), student_enroll(student_id, course_id, student_enroll_status, academic_year), student(student_id, student_gender)
$whereEnroll = '';
if ($status !== 'All') {
    $whereEnroll .= " AND e.student_enroll_status = '" . mysqli_real_escape_string($con, $status) . "'";
}
if ($year !== '') {
    $whereEnroll .= " AND e.academic_year = '" . mysqli_real_escape_string($con, $year) . "'";
}

// Apply acceptance condition inside aggregates to preserve LEFT JOIN behavior
$accCase = ($conduct === 'accepted') ? " AND s.student_conduct_accepted_at IS NOT NULL" : "";

$sql = "
SELECT 
  d.department_name AS department,
  COUNT(DISTINCT CASE WHEN s.student_gender = 'Male'$accCase AND COALESCE(s.student_status,'') <> 'Inactive' THEN s.student_id END) AS male,
  COUNT(DISTINCT CASE WHEN s.student_gender = 'Female'$accCase AND COALESCE(s.student_status,'') <> 'Inactive' THEN s.student_id END) AS female
FROM department d
LEFT JOIN course c ON c.department_id = d.department_id
LEFT JOIN student_enroll e ON e.course_id = c.course_id$whereEnroll
LEFT JOIN student s ON s.student_id = e.student_id
GROUP BY d.department_id, d.department_name
ORDER BY d.department_name ASC
";

$res = mysqli_query($con, $sql);
if (!$res) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed', 'error' => mysqli_error($con)]);
    exit;
}

$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $data[] = [
        'department' => $row['department'],
        'male' => (int)($row['male'] ?? 0),
        'female' => (int)($row['female'] ?? 0)
    ];
}

echo json_encode(['status' => 'success', 'data' => $data]);
