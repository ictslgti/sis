<?php
$title = "End Exam Results | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

$assessment_id = isset($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : 0;
if ($assessment_id <= 0) {
  echo '<div class="container my-4"><div class="alert alert-warning">Missing assessment_id.</div></div>';
  include_once("../footer.php");
  exit;
}

$meta = null;
$qm = "SELECT a.assessment_id, a.course_id, a.module_id, a.academic_year, a.assessment_date, t.assessment_name, t.assessment_percentage
       FROM assessments a JOIN assessments_type t ON t.assessment_type_id=a.assessment_type_id
       WHERE a.assessment_id=?";
if ($stm = mysqli_prepare($con, $qm)) {
  mysqli_stmt_bind_param($stm, 'i', $assessment_id);
  mysqli_stmt_execute($stm);
  $res = mysqli_stmt_get_result($stm);
  $meta = mysqli_fetch_assoc($res) ?: null;
  mysqli_stmt_close($stm);
}
?>
<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0">End Exam Results</h3>
        <?php if ($meta) { ?>
          <small><?php echo htmlspecialchars($meta['course_id']).' • '.htmlspecialchars($meta['module_id']).' • '.htmlspecialchars($meta['academic_year']); ?></small>
        <?php } ?>
      </div>
      <div>
        <a class="btn btn-secondary btn-sm" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/exam/EndExams.php">Back</a>
        <a class="btn btn-success btn-sm" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessmentResults.php?StudentMarks=<?php echo $assessment_id; ?>"><i class="fas fa-plus"></i> Add/Edit Marks</a>
      </div>
    </div>
    <div class="card-body">
      <?php
      function grade_from_marks($m){
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
      ?>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="thead-light">
            <tr>
              <th>#</th>
              <th>Student ID</th>
              <th>Attempt</th>
              <th>Marks</th>
              <th>Grade</th>
              <th>GP</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Get latest attempt per student for this assessment
            $sql = "SELECT m.student_id, m.assessment_attempt, m.assessment_marks
                    FROM assessments_marks m
                    WHERE m.assessment_id = ? AND (m.student_id, m.assessment_attempt) IN (
                      SELECT student_id, MAX(assessment_attempt) FROM assessments_marks WHERE assessment_id = ? GROUP BY student_id
                    )
                    ORDER BY m.student_id";
            if ($st = mysqli_prepare($con, $sql)) {
              mysqli_stmt_bind_param($st, 'ii', $assessment_id, $assessment_id);
              mysqli_stmt_execute($st);
              $rs = mysqli_stmt_get_result($st);
              $i = 1;
              $sum_gp = 0.0; $cnt = 0;
              if ($rs && mysqli_num_rows($rs) > 0) {
                while ($row = mysqli_fetch_assoc($rs)) {
                  $marks = (float)$row['assessment_marks'];
                  $status = ($marks >= 40.0) ? 'Pass' : 'Fail';
                  [$gr, $gp] = grade_from_marks($marks);
                  $sum_gp += floatval($gp); $cnt++;
                  echo '<tr>';
                  echo '<td>'.($i++).'</td>';
                  echo '<td>'.htmlspecialchars($row['student_id']).'</td>';
                  echo '<td>'.htmlspecialchars($row['assessment_attempt']).'</td>';
                  echo '<td>'.htmlspecialchars($row['assessment_marks']).'</td>';
                  echo '<td>'.htmlspecialchars($gr).'</td>';
                  echo '<td>'.htmlspecialchars($gp).'</td>';
                  echo '<td>'.htmlspecialchars($status).'</td>';
                  echo '</tr>';
                }
              } else {
                echo '<tr><td colspan="5" class="text-center text-muted">No marks recorded.</td></tr>';
              }
              mysqli_stmt_close($st);
            }
            ?>
          </tbody>
        </table>
      </div>
      <?php if(isset($sum_gp) && $cnt>0) { $gpa = number_format($sum_gp / $cnt, 2); ?>
      <div class="mt-2 text-right"><span class="badge badge-info">Simple GPA: <?php echo $gpa; ?></span></div>
      <?php } ?>
    </div>
  </div>
</div>

<?php include_once("../footer.php"); ?>
