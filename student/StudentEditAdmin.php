<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'ADM') {
  http_response_code(403);
  echo 'Forbidden: Admins only';
  exit;
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Get student id
$sid = isset($_GET['Sid']) ? trim($_GET['Sid']) : (isset($_POST['Sid']) ? trim($_POST['Sid']) : '');
if ($sid === '') {
  $_SESSION['flash_errors'] = ['Missing Student ID'];
  header('Location: '.$base.'/student/ManageStudents.php');
  exit;
}

// Handle update
$messages = [];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  // Collect fields
  $fields = [
    'student_title','student_fullname','student_ininame','student_gender','student_email','student_nic','student_dob','student_phone','student_address',
    'student_zip','student_district','student_divisions','student_provice','student_blood','student_civil',
    'student_em_name','student_em_address','student_em_phone','student_em_relation','student_status'
  ];
  $data = [];
  foreach ($fields as $f) { $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : null; }

  // New Department/Course from form
  $new_dept = isset($_POST['new_dept']) ? trim($_POST['new_dept']) : '';
  $new_coid = isset($_POST['new_coid']) ? trim($_POST['new_coid']) : '';

  $sql = "UPDATE student SET student_title=?, student_fullname=?, student_ininame=?, student_gender=?, student_email=?, student_nic=?, student_dob=?, student_phone=?, student_address=?, student_zip=?, student_district=?, student_divisions=?, student_provice=?, student_blood=?, student_civil=?, student_em_name=?, student_em_address=?, student_em_phone=?, student_em_relation=?, student_status=? WHERE student_id=?";
  $stmt = mysqli_prepare($con, $sql);
  if ($stmt) {
    // 20 fields to update + 1 for WHERE student_id => 21 's'
    mysqli_stmt_bind_param($stmt, 'sssssssssssssssssssss',
      $data['student_title'],$data['student_fullname'],$data['student_ininame'],$data['student_gender'],$data['student_email'],$data['student_nic'],$data['student_dob'],$data['student_phone'],$data['student_address'],$data['student_zip'],$data['student_district'],$data['student_divisions'],$data['student_provice'],$data['student_blood'],$data['student_civil'],$data['student_em_name'],$data['student_em_address'],$data['student_em_phone'],$data['student_em_relation'],$data['student_status'],$sid
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // If course selected, validate and update student's current enrollment
    if ($new_coid !== '') {
      // Validate course belongs to department if provided; also fetch its department
      $courseDept = null;
      if ($cs = mysqli_prepare($con, 'SELECT department_id FROM course WHERE course_id=?')) {
        mysqli_stmt_bind_param($cs, 's', $new_coid);
        mysqli_stmt_execute($cs);
        $cr = mysqli_stmt_get_result($cs);
        $courseDept = ($cr && ($rowc = mysqli_fetch_assoc($cr))) ? $rowc['department_id'] : null;
        mysqli_stmt_close($cs);
      }
      if (!$courseDept) {
        $errors[] = 'Selected course not found.';
      } elseif ($new_dept !== '' && $new_dept !== $courseDept) {
        $errors[] = 'Selected course does not belong to the chosen department.';
      } else {
        // Determine current enrollment row to update: prefer Following/Active, else latest by academic year
        $enq = mysqli_prepare($con, "SELECT student_id, course_id, academic_year FROM student_enroll WHERE student_id=? ORDER BY (student_enroll_status IN ('Following','Active')) DESC, academic_year DESC LIMIT 1");
        if ($enq) {
          mysqli_stmt_bind_param($enq, 's', $sid);
          mysqli_stmt_execute($enq);
          $enr = mysqli_stmt_get_result($enq);
          $cur = $enr ? mysqli_fetch_assoc($enr) : null;
          mysqli_stmt_close($enq);
          if ($cur) {
            // Update the course_id in that enrollment
            $up = mysqli_prepare($con, 'UPDATE student_enroll SET course_id=? WHERE student_id=? AND course_id=? AND academic_year=?');
            if ($up) {
              mysqli_stmt_bind_param($up, 'ssss', $new_coid, $cur['student_id'], $cur['course_id'], $cur['academic_year']);
              mysqli_stmt_execute($up);
              mysqli_stmt_close($up);
            } else {
              $errors[] = 'Failed to prepare enrollment update: '.mysqli_error($con);
            }
          } else {
            $errors[] = 'No enrollment found to update course.';
          }
        } else {
          $errors[] = 'Failed to query current enrollment: '.mysqli_error($con);
        }
      }
    }

    if (!$errors) {
    $_SESSION['flash_messages'] = ['Student updated successfully'];
    header('Location: '.$base.'/student/ManageStudents.php');
    exit;
    } else {
      // Persist errors and fall through to render
      $_SESSION['flash_errors'] = $errors;
    }
  } else {
    $errors[] = 'Database error while preparing student update: '.mysqli_error($con);
  }
}

// Fetch student
$st = mysqli_prepare($con, "SELECT * FROM student WHERE student_id=? LIMIT 1");
if (!$st) {
  die('DB error');
}
mysqli_stmt_bind_param($st,'s',$sid);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$student = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($st);
if (!$student) {
  $_SESSION['flash_errors'] = ['Student not found'];
  header('Location: '.$base.'/student/ManageStudents.php');
  exit;
}

$title = 'Edit Student (Admin) | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h3>Edit Student (Admin)</h3>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo h($e); ?></div>
      <?php endforeach; ?>
      <form method="post">
        <input type="hidden" name="Sid" value="<?php echo h($sid); ?>">
        <div class="card mb-3">
          <div class="card-header">Basic Info</div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-2">
                <label>Title</label>
                <input type="text" name="student_title" class="form-control" value="<?php echo h($student['student_title'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-5">
                <label>Full Name</label>
                <input type="text" name="student_fullname" class="form-control" value="<?php echo h($student['student_fullname'] ?? ''); ?>" required>
              </div>
              <div class="form-group col-md-5">
                <label>Name with Initials</label>
                <input type="text" name="student_ininame" class="form-control" value="<?php echo h($student['student_ininame'] ?? ''); ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Gender</label>
                <select name="student_gender" class="form-control">
                  <?php foreach (['Male','Female'] as $g): ?>
                    <option value="<?php echo h($g); ?>" <?php echo ((($student['student_gender'] ?? '')===$g)?'selected':''); ?>><?php echo h($g); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-5">
                <label>Email</label>
                <input type="email" name="student_email" class="form-control" value="<?php echo h($student['student_email'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-4">
                <label>NIC</label>
                <input type="text" name="student_nic" class="form-control" value="<?php echo h($student['student_nic'] ?? ''); ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Date of Birth</label>
                <input type="date" name="student_dob" class="form-control" value="<?php echo h($student['student_dob'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Phone</label>
                <input type="text" name="student_phone" class="form-control" value="<?php echo h($student['student_phone'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-6">
                <label>Address</label>
                <input type="text" name="student_address" class="form-control" value="<?php echo h($student['student_address'] ?? ''); ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-2">
                <label>Zip</label>
                <input type="text" name="student_zip" class="form-control" value="<?php echo h($student['student_zip'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-3">
                <label>District</label>
                <input type="text" name="student_district" class="form-control" value="<?php echo h($student['student_district'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Division</label>
                <input type="text" name="student_divisions" class="form-control" value="<?php echo h($student['student_divisions'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-2">
                <label>Province</label>
                <input type="text" name="student_provice" class="form-control" value="<?php echo h($student['student_provice'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-2">
                <label>Blood Group</label>
                <input type="text" name="student_blood" class="form-control" value="<?php echo h($student['student_blood'] ?? ''); ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Civil Status</label>
                <input type="text" name="student_civil" class="form-control" value="<?php echo h($student['student_civil'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Status</label>
                <select name="student_status" class="form-control">
                  <?php $statuses=['Active','Following','Completed','Suspended','Inactive']; foreach($statuses as $st): ?>
                    <option value="<?php echo h($st); ?>" <?php echo ((($student['student_status'] ?? '')===$st)?'selected':''); ?>><?php echo h($st); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <?php
        // Determine current enrollment to preselect department/course
        $curEnroll = null; $curDept = null;
        $qe = mysqli_prepare($con, "SELECT se.course_id, se.academic_year, se.student_enroll_status, c.department_id FROM student_enroll se LEFT JOIN course c ON c.course_id=se.course_id WHERE se.student_id=? ORDER BY (se.student_enroll_status IN ('Following','Active')) DESC, se.academic_year DESC LIMIT 1");
        if ($qe) {
          mysqli_stmt_bind_param($qe, 's', $sid);
          mysqli_stmt_execute($qe);
          $qr = mysqli_stmt_get_result($qe);
          if ($qr) { $curEnroll = mysqli_fetch_assoc($qr); $curDept = $curEnroll['department_id'] ?? null; }
          mysqli_stmt_close($qe);
        }
        ?>
        <div class="card mb-3">
          <div class="card-header">Enrollment</div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Department</label>
                <select name="new_dept" id="new_dept" class="form-control">
                  <option value="">Select department</option>
                  <?php
                  $rd = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name');
                  while ($d = mysqli_fetch_assoc($rd)) {
                    $sel = ($d['department_id'] === ($curDept ?? '')) ? 'selected' : '';
                    echo '<option value="'.h($d['department_id']).'" '.$sel.'>'.h($d['department_name']).' ('.h($d['department_id']).')</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-8">
                <label>Course</label>
                <select name="new_coid" id="new_coid" class="form-control">
                  <option value="">Select course</option>
                  <?php
                  $rq = mysqli_query($con, 'SELECT course_id, course_name, department_id FROM course ORDER BY course_name');
                  while ($r = mysqli_fetch_assoc($rq)) {
                    $sel = ($r['course_id'] === ($curEnroll['course_id'] ?? '')) ? 'selected' : '';
                    echo '<option value="'.h($r['course_id']).'" data-dept="'.h($r['department_id']).'" '.$sel.'>'.h($r['course_name']).' ('.h($r['course_id']).')</option>';
                  }
                  ?>
                </select>
                <small class="form-text text-muted">Changing course will update the student's current enrollment.</small>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">Emergency Contact</div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Name</label>
                <input type="text" name="student_em_name" class="form-control" value="<?php echo h($student['student_em_name'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-4">
                <label>Address</label>
                <input type="text" name="student_em_address" class="form-control" value="<?php echo h($student['student_em_address'] ?? ''); ?>">
              </div>
              <div class="form-group col-md-4">
                <label>Relation</label>
                <input type="text" name="student_em_relation" class="form-control" value="<?php echo h($student['student_em_relation'] ?? ''); ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Phone</label>
                <input type="text" name="student_em_phone" class="form-control" value="<?php echo h($student['student_em_phone'] ?? ''); ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <button type="submit" name="save" class="btn btn-primary">Save Changes</button>
          <a class="btn btn-secondary" href="<?php echo $base; ?>/student/ManageStudents.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>

<script>
  // Filter courses by selected department
  (function(){
    var dept = document.getElementById('new_dept');
    var course = document.getElementById('new_coid');
    if (!dept || !course) return;
    var all = Array.prototype.slice.call(course.options).map(function(o){ return {value:o.value, text:o.text, dept:o.getAttribute('data-dept')}; });
    function apply(){
      var d = dept.value;
      var keep = course.value;
      while (course.options.length) course.remove(0);
      var opt = document.createElement('option'); opt.value=''; opt.text='Select course'; course.add(opt);
      all.forEach(function(it){
        if (!it.value) return;
        if (!d || it.dept === d){ var o=document.createElement('option'); o.value=it.value; o.text=it.text; o.setAttribute('data-dept', it.dept); course.add(o); }
      });
      if (keep){ for (var i=0;i<course.options.length;i++){ if (course.options[i].value===keep){ course.selectedIndex=i; break; } } }
    }
    dept.addEventListener('change', apply);
    apply();
  })();
</script>
