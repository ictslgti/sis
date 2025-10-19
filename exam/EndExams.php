<?php
$title = "End Exams | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

$success = $error = null;
$new_assessment_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $course = $_POST['course'] ?? '';
  $module = $_POST['module'] ?? '';
  $assessment_type_id = $_POST['assessment_type_id'] ?? '';
  $academic_year = $_POST['academic_year'] ?? '';
  $assessment_date = $_POST['assessment_date'] ?? '';

  if ($course && $module && $assessment_type_id && $academic_year && $assessment_date) {
    $sql = "INSERT INTO `assessments` (`course_id`,`module_id`,`assessment_type_id`,`academic_year`,`assessment_date`) VALUES (?,?,?,?,?)";
    if ($stmt = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($stmt, 'ssiss', $course, $module, $assessment_type_id, $academic_year, $assessment_date);
      if (mysqli_stmt_execute($stmt)) {
        $new_assessment_id = mysqli_insert_id($con);
        $success = "End Exam created. You can now enter marks.";
      } else {
        $error = 'DB error creating exam: ' . htmlspecialchars(mysqli_error($con));
      }
      mysqli_stmt_close($stmt);
    }
  } else {
    $error = 'Please fill all fields.';
  }
}
?>
<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h3 class="mb-0">Create End Exam</h3>
      <?php if ($new_assessment_id) { ?>
        <a class="btn btn-success" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/assessment/AddAssessmentResults.php?StudentMarks=<?php echo urlencode($new_assessment_id); ?>">
          <i class="fas fa-plus"></i> Enter Marks
        </a>
      <?php } ?>
    </div>
    <div class="card-body">
      <?php if ($success) { ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
      <?php } ?>
      <?php if ($error) { ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
      <?php } ?>

      <form method="post">
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Course</label>
            <select class="custom-select" name="course" id="course" onchange="loadModules(this.value)" required>
              <option value="">Choose Course...</option>
              <?php
              $q = "SELECT DISTINCT course_id FROM assessments_type ORDER BY course_id";
              if ($rs = mysqli_query($con, $q)) {
                while ($r = mysqli_fetch_assoc($rs)) {
                  echo '<option value="'.htmlspecialchars($r['course_id']).'">'.htmlspecialchars($r['course_id']).'</option>';
                }
                mysqli_free_result($rs);
              }
              ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Module</label>
            <select class="custom-select" name="module" id="module" onchange="loadEndExamTypes(this.value)" required>
              <option value="">Choose...</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Assessment Type</label>
            <select class="custom-select" name="assessment_type_id" id="assessments" required>
              <option value="">Choose End Exam type...</option>
            </select>
            <small class="text-muted">Filtered to types like End/Final. If empty, define one in Assessments Type.</small>
          </div>
          <div class="form-group col-md-3">
            <label>Academic Year</label>
            <select class="custom-select" name="academic_year" required>
              <option value="">Choose...</option>
              <?php
              $qy = "SELECT academic_year FROM academic ORDER BY academic_year DESC";
              if ($ry = mysqli_query($con, $qy)) {
                while ($ay = mysqli_fetch_assoc($ry)) {
                  echo '<option value="'.htmlspecialchars($ay['academic_year']).'">'.htmlspecialchars($ay['academic_year']).'</option>';
                }
                mysqli_free_result($ry);
              }
              ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Exam Date</label>
            <input type="date" class="form-control" name="assessment_date" required>
          </div>
        </div>
        <div class="text-right">
          <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save End Exam</button>
        </div>
      </form>

      <hr>
      <h5 class="mb-3" id="recent">Recent End Exams</h5>
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead class="thead-light">
            <tr>
              <th>ID</th>
              <th>Course</th>
              <th>Module</th>
              <th>Type</th>
              <th>Academic Year</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $list = "SELECT a.assessment_id, a.course_id, a.module_id, a.academic_year, a.assessment_date, t.assessment_name
                     FROM assessments a JOIN assessments_type t ON t.assessment_type_id = a.assessment_type_id
                     WHERE t.assessment_name LIKE '%End%' OR t.assessment_name LIKE '%Final%'
                     ORDER BY a.assessment_id DESC LIMIT 25";
            if ($rl = mysqli_query($con, $list)) {
              if (mysqli_num_rows($rl) === 0) {
                echo '<tr><td colspan="7" class="text-center text-muted">No End Exams yet.</td></tr>';
              }
              while ($rr = mysqli_fetch_assoc($rl)) {
                echo '<tr>';
                echo '<td>'.(int)$rr['assessment_id'].'</td>';
                echo '<td>'.htmlspecialchars($rr['course_id']).'</td>';
                echo '<td>'.htmlspecialchars($rr['module_id']).'</td>';
                echo '<td>'.htmlspecialchars($rr['assessment_name']).'</td>';
                echo '<td>'.htmlspecialchars($rr['academic_year']).'</td>';
                echo '<td>'.htmlspecialchars($rr['assessment_date']).'</td>';
                echo '<td>';
                echo '<a class="btn btn-sm btn-success" href="'.(defined('APP_BASE') ? APP_BASE : '').'/assessment/AddAssessmentResults.php?StudentMarks='.(int)$rr['assessment_id'].'"><i class="fas fa-plus"></i> Enter Marks</a> ';
                echo '<a class="btn btn-sm btn-info" href="'.(defined('APP_BASE') ? APP_BASE : '').'/exam/EndExamResults.php?assessment_id='.(int)$rr['assessment_id'].'"><i class="fas fa-list"></i> View Results</a>';
                echo '</td>';
                echo '</tr>';
              }
              mysqli_free_result($rl);
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function loadModules(courseId){
  const xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function(){
    if(this.readyState===4 && this.status===200){
      document.getElementById('module').innerHTML = this.responseText;
      document.getElementById('assessments').innerHTML = '<option value="">Choose End Exam type...</option>';
    }
  };
  xhr.open('POST','<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/exam/api_get_modules.php',true);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.send('getmodule='+encodeURIComponent(courseId));
}
function loadEndExamTypes(moduleId){
  const xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function(){
    if(this.readyState===4 && this.status===200){
      // Filter client-side as fallback; server may already filter by module
      const div = document.createElement('div');
      div.innerHTML = this.responseText;
      const sel = document.getElementById('assessments');
      sel.innerHTML='';
      div.querySelectorAll('option').forEach(function(opt){
        const txt = (opt.textContent||'').toLowerCase();
        if(txt.includes('end') || txt.includes('final')){
          sel.appendChild(opt);
        }
      });
      if(!sel.options.length){
        sel.innerHTML = '<option value="">No End Exam type found for this module</option>';
      }
    }
  };
  xhr.open('POST','<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/exam/api_get_end_types.php',true);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.send('assessmentType='+encodeURIComponent(moduleId));
}
</script>

<?php include_once("../footer.php"); ?>
