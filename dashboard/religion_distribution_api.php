<?php
// dashboard/religion_distribution_api.php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Optional: restrict to logged-in non-student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] === 'STU') {
  http_response_code(403);
  echo json_encode(['ok'=>false,'message'=>'Forbidden']);
  exit;
}

$departmentId = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';

$list = [];
try {
  if ($departmentId !== '') {
    // Count by religion within a department (based on current/active enrollment records)
    $sql = "SELECT TRIM(s.student_religion) AS religion,
                   COUNT(DISTINCT s.student_id) AS cnt
            FROM student s
            JOIN student_enroll se ON se.student_id = s.student_id
            JOIN course c ON c.course_id = se.course_id
            WHERE c.department_id = ?
              AND s.student_religion IS NOT NULL
              AND TRIM(s.student_religion) <> ''
              AND LOWER(TRIM(s.student_religion)) <> 'unknown'
            GROUP BY religion
            ORDER BY cnt DESC, religion ASC";
    if ($st = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($st, 's', $departmentId);
      mysqli_stmt_execute($st);
      $rs = mysqli_stmt_get_result($st);
      while ($rs && ($row = mysqli_fetch_assoc($rs))) { $list[] = $row; }
      mysqli_stmt_close($st);
    } else { throw new Exception(mysqli_error($con)); }
  } else {
    // Global count by religion (exclude empty/unknown)
    $sql = "SELECT TRIM(student_religion) AS religion,
                   COUNT(*) AS cnt
            FROM student
            WHERE student_religion IS NOT NULL
              AND TRIM(student_religion) <> ''
              AND LOWER(TRIM(student_religion)) <> 'unknown'
            GROUP BY religion
            ORDER BY cnt DESC, religion ASC";
    $rs = mysqli_query($con, $sql);
    if (!$rs) { throw new Exception(mysqli_error($con)); }
    while ($row = mysqli_fetch_assoc($rs)) { $list[] = $row; }
  }
  $total = 0; foreach ($list as $r) { $total += (int)$r['cnt']; }
  echo json_encode(['ok'=>true, 'total'=>$total, 'data'=>$list]);
} catch (Exception $ex) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'message'=>'Query failed', 'error'=>$ex->getMessage()]);
}
