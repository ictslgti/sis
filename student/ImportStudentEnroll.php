<!------START DON'T CHANGE ORDER HEAD,MANU,FOOTER----->
<!---BLOCK 01--->
<?php 
include_once("../config.php");
include_once("../auth.php");
// Access control: only Admin and SAO may access this page
require_roles(['ADM','SAO']);
$title ="IMPORT STUDENT ENROLLMENT | SLGTI";
include_once("../head.php");
include_once("../menu.php");
?>
<!----END DON'T CHANGE THE ORDER---->

<div class="ROW">
  <div class="col text-center shadow p-4 mb-4 bg-white rounded ">
    <h2>Add New Student</h2>
  </div>
</div>

<?php
// Flash messages via query params
$inserted = isset($_GET['inserted']) ? (int)$_GET['inserted'] : 0;
$updated  = isset($_GET['updated']) ? (int)$_GET['updated'] : 0;
$skipped  = isset($_GET['skipped']) ? (int)$_GET['skipped'] : 0;
$errors   = isset($_GET['errors']) ? (int)$_GET['errors'] : 0;
$msg      = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
if ($inserted || $updated || $skipped || $errors || $msg) {
  echo '<div class="alert '.($errors? 'alert-danger':'alert-success').'" role="alert">'
     . htmlspecialchars("Inserted: $inserted | Updated: $updated | Skipped: $skipped | Errors: $errors")
     . ($msg? '<br>' . htmlspecialchars($msg): '')
     . '</div>';
}

// Removed auto-refresh to keep messages visible

// Session-based detailed flash from controller
if (isset($_SESSION['import_flash'])) {
  $flash = $_SESSION['import_flash'];
  unset($_SESSION['import_flash']);
  if (!empty($flash['messages'])) {
    echo '<div class="alert alert-warning" role="alert">';
    echo '<strong>Details:</strong><br>';
    echo '<ul style="margin:8px 0 0 16px;">';
    foreach ($flash['messages'] as $m) {
      echo '<li>' . htmlspecialchars($m) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
  }
  if (!empty($flash['hint'])) {
    echo '<div class="alert alert-info" role="alert">' . htmlspecialchars($flash['hint']) . '</div>';
  }
}
?>

<?php
  // Shared data sources for both sections
  // load courses
  $courses = [];
  $rs = mysqli_query($con, "SELECT course_id, course_name FROM course ORDER BY course_id");
  if ($rs) { while ($r = mysqli_fetch_assoc($rs)) { $courses[] = $r; } }
  // load departments
  $departments = [];
  $rsd = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_id");
  if ($rsd) { while ($r = mysqli_fetch_assoc($rsd)) { $departments[] = $r; } }
  // load academic years (distinct)
  $years = [];
  $rs2 = mysqli_query($con, "SELECT DISTINCT academic_year FROM academic ORDER BY academic_year DESC");
  if ($rs2 && mysqli_num_rows($rs2) > 0) { while ($r = mysqli_fetch_assoc($rs2)) { if ($r['academic_year'] !== '') $years[] = $r['academic_year']; } }
  if (empty($years)) {
    $y = (int)date('Y');
    $years = [($y-1)."/".$y, $y."/".($y+1)];
  }
?>

<!-- Manual Add Section (moved first) -->
<div class="card mt-4">
  <div class="card-body">
    <h4 class="mb-3">Manual Add (Single Student Enrollment)</h4>
    <form method="post" action="../controller/ImportStudentEnroll.php">
      <input type="hidden" name="manual" value="1">
      <div class="form-row">
        <div class="col-md-4 mb-3">
          <label for="m_department_id">Department</label>
          <select class="custom-select" id="m_department_id" name="department_id" required>
            <option value="" disabled selected>-- Select Department --</option>
            <?php foreach ($departments as $d) { echo '<option value="'.htmlspecialchars($d['department_id']).'">'.htmlspecialchars($d['department_id'].' - '.$d['department_name']).'</option>'; } ?>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="m_course_id">Course</label>
          <select class="custom-select" id="m_course_id" name="course_id" required disabled>
            <option value="" disabled selected>-- Select Course --</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="m_academic_year">Academic Year</label>
          <select class="custom-select" id="m_academic_year" name="academic_year" required disabled>
            <option value="" disabled selected>-- Select Academic Year --</option>
            <?php foreach ($years as $y) { echo '<option value="'.htmlspecialchars($y).'">'.htmlspecialchars($y).'</option>'; } ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="col-md-4 mb-3">
          <label for="m_student_id">Student ID</label>
          <input type="text" class="form-control" id="m_student_id" name="student_id" placeholder="Auto-generated" readonly required>
          <small class="form-text text-muted">Auto-generated from Course + Academic Year. Change not allowed.</small>
        </div>
        <div class="col-md-4 mb-3">
          <label for="m_student_fullname">Student Full Name</label>
          <input type="text" class="form-control" id="m_student_fullname" name="student_fullname" placeholder="Optional">
        </div>
        <div class="col-md-4 mb-3">
          <label for="m_student_nic">Student NIC</label>
          <input type="text" class="form-control" id="m_student_nic" name="student_nic" placeholder="Optional">
        </div>
      </div>
      <div class="form-row">
        <div class="col-md-4 mb-3">
          <label for="m_enroll_date">Enroll Date</label>
          <input type="date" class="form-control" id="m_enroll_date" name="enroll_date" required>
        </div>
        <div class="col-md-4 mb-3">
          <label for="m_course_mode">Course Mode</label>
          <select class="custom-select" id="m_course_mode" name="course_mode">
            <option value="Full" selected>Full</option>
            <option value="Part">Part</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="m_status">Enrollment Status</label>
          <select class="custom-select" id="m_status" name="status">
            <option value="Following" selected>Following</option>
            <option value="Completed">Completed</option>
            <option value="Dropout">Dropout</option>
            <option value="Long Absent">Long Absent</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="col-md-3 mb-3">
          <button type="submit" class="btn btn-success btn-block"><i class="fas fa-user-plus"></i> Add Enrollment</button>
        </div>
        <div class="col-md-3 mb-3 d-flex align-items-center">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="m_debug" name="manual_debug" value="1">
            <label class="form-check-label" for="m_debug">Debug</label>
          </div>
        </div>
      </div>
    </form>
  </div>
  </div>


<div class="card">
  <div class="card-body">
    <form method="post" action="../controller/ImportStudentEnroll.php" enctype="multipart/form-data">
      <div class="form-row">
        <div class="col-md-4 mb-3">
          <label for="course_id">Course</label>
          <select class="custom-select" id="course_id" name="course_id" required>
            <option value="" disabled selected>-- Select Course --</option>
            <?php foreach ($courses as $c) { echo '<option value="'.htmlspecialchars($c['course_id']).'">'.htmlspecialchars($c['course_id'].' - '.$c['course_name']).'</option>'; } ?>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="academic_year">Academic Year</label>
          <select class="custom-select" id="academic_year" name="academic_year" required>
            <option value="" disabled selected>-- Select Academic Year --</option>
            <?php foreach ($years as $y) { echo '<option value="'.htmlspecialchars($y).'">'.htmlspecialchars($y).'</option>'; } ?>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="enroll_date">Enroll Date</label>
          <input type="date" class="form-control" id="enroll_date" name="enroll_date" required>
        </div>
      </div>
      <div class="form-row">
        <div class="col-md-4 mb-3">
          <label for="course_mode">Course Mode</label>
          <select class="custom-select" id="course_mode" name="course_mode">
            <option value="Full" selected>Full</option>
            <option value="Part">Part</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="status">Enrollment Status</label>
          <select class="custom-select" id="status" name="status">
            <option value="Following" selected>Following</option>
            <option value="Completed">Completed</option>
            <option value="Dropout">Dropout</option>
            <option value="Long Absent">Long Absent</option>
          </select>
        </div>
        <div class="col-md-4 mb-3">
          <label for="csv_file">Upload CSV file</label>
          <input type="file" class="form-control" name="csv_file" id="csv_file" accept=".csv" required>
          <small class="form-text text-muted">CSV must have headers: student_id, student_fullname, student_nic. Max 5MB. Missing students are auto-created.</small>
        </div>
      </div>
      <div class="form-row align-items-end">
        <div class="col-md-3 mb-3">
          <label for="dry_run">Dry run</label>
          <select class="custom-select" id="dry_run" name="dry_run">
            <option value="0" selected>Import</option>
            <option value="1">Validate only</option>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-file-upload"></i> Import</button>
        </div>
      </div>
    </form>

    <hr>
    <a class="btn btn-outline-secondary" href="../controller/ImportStudentEnroll.php?action=template"><i class="fas fa-download"></i> Download CSV Template</a>
  </div>
</div>

<script>
  (function(){
    const deptSel = document.getElementById('m_department_id');
    const courseSel = document.getElementById('m_course_id');
    const yearSel = document.getElementById('m_academic_year');
    const sidInput = document.getElementById('m_student_id');

    function clearSelect(sel, placeholder){
      if(!sel) return;
      sel.innerHTML = '';
      const opt = document.createElement('option');
      opt.value = '';
      opt.disabled = true; opt.selected = true;
      opt.textContent = placeholder;
      sel.appendChild(opt);
    }

    function fetchCourses(dept){
      if(!courseSel) return;
      clearSelect(courseSel, '-- Select Course --');
      courseSel.disabled = true;
      fetch('../controller/ImportStudentEnroll.php?action=courses_by_dept&department_id=' + encodeURIComponent(dept))
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(j => {
          if(j && j.ok && Array.isArray(j.courses)){
            j.courses.forEach(c => {
              const o = document.createElement('option');
              o.value = c.course_id; o.textContent = c.course_id + ' - ' + c.course_name;
              courseSel.appendChild(o);
            });
            courseSel.disabled = false;
            if (yearSel) yearSel.disabled = false;
          }
        })
        .catch(() => {
          // leave disabled on error
        });
    }

    function fetchNextId(){
      if(!courseSel || !yearSel || !sidInput) return;
      const c = courseSel.value; const y = yearSel.value;
      if(!c || !y){ sidInput.value = ''; return; }
      sidInput.value = '...';
      fetch('../controller/ImportStudentEnroll.php?action=next_student_id&course_id=' + encodeURIComponent(c) + '&academic_year=' + encodeURIComponent(y))
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(j => { if(j && j.ok){ sidInput.value = j.next_student_id || ''; } else { sidInput.value=''; } })
        .catch(() => { sidInput.value=''; });
    }

    if (deptSel) {
      deptSel.addEventListener('change', function(){
        if(this.value){ fetchCourses(this.value); }
        if (sidInput) sidInput.value = '';
      });
    }
    if (courseSel) { courseSel.addEventListener('change', fetchNextId); }
    if (yearSel) { yearSel.addEventListener('change', fetchNextId); }
  })();
</script>
