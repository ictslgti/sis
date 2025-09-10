<?php
// course_wise_export.php - CSV export for course-wise student counts
// Access: Only SAO and DIR

// Bootstrap
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Authorize
$role = isset($_SESSION['user_type']) ? strtoupper(trim($_SESSION['user_type'])) : '';
if (!in_array($role, ['SAO','DIR'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    exit;
}

// Input: academic_year (optional)
$selectedYear = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$yearCond = $selectedYear !== '' ? (" AND e.academic_year='" . mysqli_real_escape_string($con, $selectedYear) . "'") : '';

// Query (align with dashboard/index.php listing)
$sql = "
  SELECT 
    d.department_name,
    c.course_id,
    c.course_name,
    COUNT(DISTINCT s.student_id) AS total
  FROM course c
  LEFT JOIN department d ON d.department_id = c.department_id
  LEFT JOIN student_enroll e ON e.course_id = c.course_id 
    AND e.student_enroll_status IN ('Following','Active')" . $yearCond . "
  LEFT JOIN student s ON s.student_id = e.student_id AND COALESCE(s.student_status,'') <> 'Inactive'
  WHERE LOWER(TRIM(d.department_name)) NOT IN ('admin','administration')
  GROUP BY d.department_name, c.course_id, c.course_name
  HAVING total > 0
  ORDER BY d.department_name ASC, c.course_name ASC";

$res = mysqli_query($con, $sql);

// Output headers for CSV
$fnameParts = ['course_wise_students'];
if ($selectedYear !== '') { $fnameParts[] = preg_replace('/[^0-9A-Za-z-]/','_', $selectedYear); }
$fnameParts[] = date('Ymd_His');
$filename = implode('_', $fnameParts) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fwrite($out, "\xEF\xBB\xBF");
// Headers row (no course code)
fputcsv($out, ['Department','Course Name','Students']);

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $row['department_name'] ?? '',
            $row['course_name'] ?? '',
            (int)($row['total'] ?? 0),
        ]);
    }
    mysqli_free_result($res);
}

fclose($out);
exit;
