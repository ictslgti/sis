<?php
// Returns <option> list of modules for a given course using assessments_type
include_once("../config.php");
header('Content-Type: text/html; charset=utf-8');

$course = $_POST['course_id'] ?? $_POST['getmodule'] ?? $_POST['course'] ?? '';
if (!$course) { echo '<option value="">Choose...</option>'; exit; }

$sql = "SELECT DISTINCT module_id FROM assessments_type WHERE course_id = ? ORDER BY module_id";
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st, 's', $course);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  if ($rs && mysqli_num_rows($rs) > 0) {
    echo '<option value="">Choose...</option>';
    while ($row = mysqli_fetch_assoc($rs)) {
      $m = htmlspecialchars($row['module_id']);
      echo '<option value="'.$m.'">'.$m.'</option>';
    }
  } else {
    echo '<option value="">No modules</option>';
  }
  mysqli_stmt_close($st);
} else {
  echo '<option value="">Error</option>';
}
