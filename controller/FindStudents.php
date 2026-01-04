<?php
// controller/FindStudents.php - returns <option> list for student select based on query
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Allow FIN/ACC/MA4/ADM to query
require_roles(['FIN','ACC','MA4','ADM']);

header('Content-Type: text/html; charset=utf-8');

$q = isset($_POST['q']) ? trim($_POST['q']) : '';
$limit = 30;

if ($q === '') {
  echo '<option value="">-- Type to search --</option>';
  exit;
}

$qs = mysqli_real_escape_string($con, $q);
// Search by ID prefix or name contains (case-insensitive)
// Exclude inactive students
$sql = "SELECT s.student_id, s.student_fullname
        FROM student s
        WHERE (LOWER(s.student_id) LIKE LOWER('$qs%')
           OR LOWER(s.student_fullname) LIKE LOWER('%$qs%'))
        AND (s.student_status IS NULL OR s.student_status = '' OR (s.student_status != 'Inactive' AND s.student_status != '0'))
        ORDER BY s.student_id ASC
        LIMIT $limit";

$rs = mysqli_query($con, $sql);
if (!$rs) {
  // Log error for debugging
  error_log('FindStudents query error: ' . mysqli_error($con));
  echo '<option value="">Error: Database query failed</option>';
  exit;
}

if (mysqli_num_rows($rs) > 0) {
  echo '<option value="">-- Select student --</option>';
  while ($r = mysqli_fetch_assoc($rs)) {
    $id = htmlspecialchars($r['student_id'] ?? '', ENT_QUOTES, 'UTF-8');
    $nm = htmlspecialchars($r['student_fullname'] ?? '', ENT_QUOTES, 'UTF-8');
    if ($id !== '') {
      echo '<option value="'.$id.'">'.$id.' — '.$nm.'</option>';
    }
  }
  mysqli_free_result($rs);
} else {
  echo '<option value="">No matches found</option>';
}
