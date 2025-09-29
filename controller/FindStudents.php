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
$sql = "SELECT s.student_id, s.student_fullname
        FROM student s
        WHERE s.student_id LIKE '$qs%'
           OR s.student_fullname LIKE '%$qs%'
        ORDER BY s.student_id ASC
        LIMIT $limit";

$rs = mysqli_query($con, $sql);
if ($rs && mysqli_num_rows($rs) > 0) {
  echo '<option value="">-- Select student --</option>';
  while ($r = mysqli_fetch_assoc($rs)) {
    $id = htmlspecialchars($r['student_id'], ENT_QUOTES, 'UTF-8');
    $nm = htmlspecialchars($r['student_fullname'] ?? '', ENT_QUOTES, 'UTF-8');
    echo '<option value="'.$id.'">'.$id.' — '.$nm.'</option>';
  }
  mysqli_free_result($rs);
} else {
  echo '<option value="">No matches</option>';
}
