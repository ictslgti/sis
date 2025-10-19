<?php
// Returns a single default End Exam assessment type for a given module from assessments_type.
// If not present, it will be created (assessment_name='End Exam', assessment_type='T', assessment_percentage=100).
include_once("../config.php");
header('Content-Type: text/html; charset=utf-8');

$module = $_POST['module_id'] ?? $_POST['assessmentType'] ?? $_POST['module'] ?? '';
if (!$module) { echo '<option value="">Choose End Exam type...</option>'; exit; }

// 1) Try to fetch an existing 'End Exam' type for this module
$sql = "SELECT assessment_type_id FROM assessments_type WHERE module_id = ? AND LOWER(assessment_name) = 'end exam' LIMIT 1";
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st, 's', $module);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $row = $rs ? mysqli_fetch_assoc($rs) : null;
  mysqli_stmt_close($st);

  if ($row && isset($row['assessment_type_id'])) {
    $id = (int)$row['assessment_type_id'];
    echo '<option value="'.$id.'">End Exam</option>';
    exit;
  }
}

// 2) Not found: create it. We need a course_id to insert; reuse any course_id mapped to this module in assessments_type
$course_id = null;
$qm = "SELECT course_id FROM assessments_type WHERE module_id = ? LIMIT 1";
if ($stm = mysqli_prepare($con, $qm)) {
  mysqli_stmt_bind_param($stm, 's', $module);
  mysqli_stmt_execute($stm);
  $rm = mysqli_stmt_get_result($stm);
  $r = $rm ? mysqli_fetch_assoc($rm) : null;
  $course_id = $r ? $r['course_id'] : null;
  mysqli_stmt_close($stm);
}

if ($course_id) {
  $ins = "INSERT INTO assessments_type (course_id, module_id, assessment_name, assessment_type, assessment_percentage) VALUES (?,?,?,?,?)";
  if ($si = mysqli_prepare($con, $ins)) {
    $name = 'End Exam';
    $type = 'T';
    $perc = 100;
    mysqli_stmt_bind_param($si, 'ssssi', $course_id, $module, $name, $type, $perc);
    if (mysqli_stmt_execute($si)) {
      $new_id = mysqli_insert_id($con);
      echo '<option value="'.(int)$new_id.'">End Exam</option>';
      mysqli_stmt_close($si);
      exit;
    }
    mysqli_stmt_close($si);
  }
}

// 3) Fallback if we cannot determine course_id or insert fails
echo '<option value="">End Exam (define in Assessment Type)</option>';
