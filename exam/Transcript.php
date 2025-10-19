<?php
// ADM Transcript viewer
$title = "Student Transcript | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'ADM') {
  echo '<div class="container my-4"><div class="alert alert-warning">Access restricted to Admin.</div></div>';
  include_once("../footer.php");
  exit;
}

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$transcript = [];
$agg = ['sum_gp'=>0.0,'count'=>0];

function grade_from_marks_t($m){
  if(!is_numeric($m)) return ['-','0.00'];
  $m = floatval($m);
  if($m >= 85) return ['A', '4.00'];
  if($m >= 75) return ['B+', '3.50'];
  if($m >= 65) return ['B', '3.00'];
  if($m >= 55) return ['C+', '2.50'];
  if($m >= 45) return ['C', '2.00'];
  if($m >= 40) return ['D', '1.00'];
  return ['F', '0.00'];
}

if ($student_id !== '') {
  // Pull all assessments and latest attempt marks for the student
  $sql = "SELECT a.assessment_id, a.course_id, a.module_id, a.academic_year, a.assessment_date,
                 t.assessment_name, t.assessment_type, t.assessment_percentage,
                 m.assessment_attempt, m.assessment_marks
          FROM assessments a
          JOIN assessments_type t ON t.assessment_type_id = a.assessment_type_id
          JOIN (
            SELECT mm.assessment_id, mm.module_id, mm.student_id, MAX(mm.assessment_attempt) AS last_attempt
            FROM assessments_marks mm
            WHERE mm.student_id = ?
            GROUP BY mm.assessment_id, mm.module_id, mm.student_id
          ) last ON last.assessment_id = a.assessment_id AND last.module_id = a.module_id
          JOIN assessments_marks m ON m.assessment_id = last.assessment_id AND m.module_id = last.module_id AND m.student_id = last.student_id AND m.assessment_attempt = last.last_attempt
          WHERE m.student_id = ?
          ORDER BY a.academic_year DESC, a.assessment_date DESC";
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, 'ss', $student_id, $student_id);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($row = mysqli_fetch_assoc($rs)) {
      [$gr, $gp] = grade_from_marks_t($row['assessment_marks']);
      $row['grade'] = $gr; $row['gp'] = $gp;
      $agg['sum_gp'] += floatval($gp); $agg['count']++;
      $transcript[] = $row;
    }
    mysqli_stmt_close($st);
  }
}
?>
<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h3 class="mb-0">Student Transcript</h3>
      <form class="form-inline" method="get">
        <input type="text" class="form-control form-control-sm mr-2" name="student_id" placeholder="Student ID" value="<?php echo htmlspecialchars($student_id); ?>" required>
        <button class="btn btn-sm btn-light" type="submit"><i class="fas fa-search"></i> View</button>
      </form>
    </div>
    <div class="card-body">
      <?php if ($student_id === '') { ?>
        <div class="alert alert-info">Enter a Student ID to view transcript.</div>
      <?php } else if (!$transcript) { ?>
        <div class="alert alert-warning">No assessment records found for student <strong><?php echo htmlspecialchars($student_id); ?></strong>.</div>
      <?php } else { ?>
        <div class="mb-3">
          <span class="badge badge-info">Entries: <?php echo count($transcript); ?></span>
          <?php if($agg['count']>0) { $gpa = number_format($agg['sum_gp'] / $agg['count'], 2); ?>
            <span class="badge badge-success">Simple GPA: <?php echo $gpa; ?></span>
          <?php } ?>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="thead-light">
              <tr>
                <th>Academic Year</th>
                <th>Date</th>
                <th>Course</th>
                <th>Module</th>
                <th>Assessment</th>
                <th>Type</th>
                <th>%</th>
                <th>Attempt</th>
                <th>Marks</th>
                <th>Grade</th>
                <th>GP</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transcript as $r) { ?>
              <tr>
                <td><?php echo htmlspecialchars($r['academic_year']); ?></td>
                <td><?php echo htmlspecialchars($r['assessment_date']); ?></td>
                <td><?php echo htmlspecialchars($r['course_id']); ?></td>
                <td><?php echo htmlspecialchars($r['module_id']); ?></td>
                <td><?php echo htmlspecialchars($r['assessment_name']); ?></td>
                <td><?php echo htmlspecialchars($r['assessment_type']); ?></td>
                <td><?php echo htmlspecialchars($r['assessment_percentage']); ?></td>
                <td><?php echo htmlspecialchars($r['assessment_attempt']); ?></td>
                <td><?php echo htmlspecialchars($r['assessment_marks']); ?></td>
                <td><?php echo htmlspecialchars($r['grade']); ?></td>
                <td><?php echo htmlspecialchars($r['gp']); ?></td>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<?php include_once("../footer.php"); ?>
