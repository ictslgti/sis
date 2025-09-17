<?php
// SAO export: Full student details as CSV
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if ($role !== 'SAO' && $role !== 'ADM') {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

// Optional filters
$onlyActive   = isset($_GET['active']) ? (trim($_GET['active']) === '1') : false;
$deptFilter   = isset($_GET['department_id']) ? trim((string)$_GET['department_id']) : '';
$courseFilter = isset($_GET['course_id']) ? trim((string)$_GET['course_id']) : '';
$genderFilter = isset($_GET['gender']) ? trim((string)$_GET['gender']) : '';
$provFilter   = isset($_GET['province']) ? trim((string)$_GET['province']) : '';
$distFilter   = isset($_GET['district']) ? trim((string)$_GET['district']) : '';

// Build query: base student table, left join latest enrollment to get department/course
$sql = "SELECT 
  s.student_id,
  s.student_title,
  s.student_fullname,
  s.student_ininame,
  s.student_gender,
  s.student_civil,
  s.student_email,
  s.student_nic,
  s.student_dob,
  s.student_phone,
  s.student_address,
  s.student_zip,
  s.student_district,
  s.student_divisions,
  s.student_provice,
  s.student_blood,
  s.student_em_name,
  s.student_em_address,
  s.student_em_phone,
  s.student_em_relation,
  s.student_status,
  s.bank_name,
  s.bank_account_no,
  s.bank_branch,
  s.bank_frontsheet_path,
  s.student_conduct_accepted_at,
  s.student_profile_doc,
  s.student_nationality,
  s.student_whatsapp,
  s.student_religion,
  se.course_id AS latest_course_id,
  c.course_name AS latest_course_name,
  d.department_id AS latest_department_id,
  d.department_name AS latest_department_name,
  se.academic_year AS latest_academic_year,
  se.student_enroll_status AS latest_enroll_status,
  se.student_enroll_date AS latest_enroll_date,
  se.student_enroll_exit_date AS latest_enroll_exit_date
FROM student s
LEFT JOIN (
  SELECT t1.* FROM student_enroll t1
  INNER JOIN (
    SELECT student_id, MAX(COALESCE(student_enroll_date, '0000-00-00')) AS max_dt
    FROM student_enroll
    GROUP BY student_id
  ) t2
  ON t1.student_id=t2.student_id AND COALESCE(t1.student_enroll_date,'0000-00-00')=t2.max_dt
) se ON se.student_id = s.student_id
LEFT JOIN course c ON c.course_id = se.course_id
LEFT JOIN department d ON d.department_id = c.department_id";

$where = [];
$params = [];
$types  = '';
if ($onlyActive) {
  $where[] = "(s.student_status IS NULL OR s.student_status NOT IN ('Inactive','Dropout'))";
}
if ($deptFilter !== '') {
  $where[] = "(d.department_id = ?)";
  $params[] = $deptFilter; $types .= 's';
}
if ($courseFilter !== '') {
  $where[] = "(c.course_id = ?)";
  $params[] = $courseFilter; $types .= 's';
}
if ($genderFilter !== '') {
  $where[] = "(COALESCE(s.student_gender,'') = ?)";
  $params[] = $genderFilter; $types .= 's';
}
if ($provFilter !== '') {
  $where[] = "(COALESCE(s.student_provice,'') = ?)";
  $params[] = $provFilter; $types .= 's';
}
if ($distFilter !== '') {
  $where[] = "(COALESCE(s.student_district,'') = ?)";
  $params[] = $distFilter; $types .= 's';
}
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY s.student_fullname';

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
  http_response_code(500);
  echo 'Database error';
  exit;
}
if ($types !== '') {
  mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Output CSV headers
$filename = 'students_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
$headers = [
  'student_id','student_title','student_fullname','student_ininame','student_gender','student_civil','student_email','student_nic','student_dob','student_phone','student_address','student_zip','student_district','student_divisions','student_provice','student_blood','student_em_name','student_em_address','student_em_phone','student_em_relation','student_status','bank_name','bank_account_no','bank_branch','bank_frontsheet_path','student_conduct_accepted_at','student_profile_doc','student_nationality','student_whatsapp','student_religion','latest_course_id','latest_course_name','latest_department_id','latest_department_name','latest_academic_year','latest_enroll_status','latest_enroll_date','latest_enroll_exit_date'
];
fputcsv($out, $headers);

while ($res && ($row = mysqli_fetch_assoc($res))) {
  $line = [];
  foreach ($headers as $h) {
    $v = isset($row[$h]) ? $row[$h] : '';
    // Normalize newlines
    if (is_string($v)) { $v = str_replace(["\r\n","\r"], "\n", $v); }
    $line[] = $v;
  }
  fputcsv($out, $line);
}

if ($res) { mysqli_free_result($res); }
mysqli_stmt_close($stmt);
fclose($out);
exit;
