<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Access control: Admin and SAO can edit
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO'], true)) {
  http_response_code(403);
  echo 'Forbidden: Admin/SAO only';
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
    'student_zip','student_district','student_divisions','student_provice','student_blood','student_religion','student_civil',
    'student_em_name','student_em_address','student_em_phone','student_em_relation','student_status'
  ];
  $data = [];
  foreach ($fields as $f) { $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : null; }

  // Normalize optional fields: convert empty strings to NULL to avoid DB errors (e.g., invalid date formats)
  $nullable = [
    'student_title','student_ininame','student_email','student_nic','student_dob','student_phone','student_address',
    'student_zip','student_district','student_divisions','student_provice','student_blood','student_religion','student_civil',
    'student_em_name','student_em_address','student_em_phone','student_em_relation'
  ];
  foreach ($nullable as $nf) {
    if (array_key_exists($nf, $data) && $data[$nf] === '') { $data[$nf] = null; }
  }

  // New Department/Course from form
  $new_dept = isset($_POST['new_dept']) ? trim($_POST['new_dept']) : '';
  $new_coid = isset($_POST['new_coid']) ? trim($_POST['new_coid']) : '';
  // New Student ID (optional)
  $new_sid = isset($_POST['new_student_id']) ? trim($_POST['new_student_id']) : '';
  // New Academic Year (optional)
  $new_ayear = isset($_POST['new_ayear']) ? trim($_POST['new_ayear']) : '';

  $sql = "UPDATE student SET student_title=?, student_fullname=?, student_ininame=?, student_gender=?, student_email=?, student_nic=?, student_dob=?, student_phone=?, student_address=?, student_zip=?, student_district=?, student_divisions=?, student_provice=?, student_blood=?, student_religion=?, student_civil=?, student_em_name=?, student_em_address=?, student_em_phone=?, student_em_relation=?, student_status=? WHERE student_id=?";
  // Begin transaction to ensure atomicity when changing student_id
  mysqli_begin_transaction($con);
  $ok = true;

  $stmt = mysqli_prepare($con, $sql);
  if ($stmt) {
    // 21 fields to update + 1 for WHERE student_id => 22 's'
    mysqli_stmt_bind_param($stmt, 'ssssssssssssssssssssss',
      $data['student_title'],$data['student_fullname'],$data['student_ininame'],$data['student_gender'],$data['student_email'],$data['student_nic'],$data['student_dob'],$data['student_phone'],$data['student_address'],$data['student_zip'],$data['student_district'],$data['student_divisions'],$data['student_provice'],$data['student_blood'],$data['student_religion'],$data['student_civil'],$data['student_em_name'],$data['student_em_address'],$data['student_em_phone'],$data['student_em_relation'],$data['student_status'],$sid
    );
    if (!mysqli_stmt_execute($stmt)) { $ok = false; $errors[] = 'Failed to update student core fields: '.mysqli_error($con); }
    mysqli_stmt_close($stmt);

    // Sync account activation with student status
    if ($ok) {
      $userActive = (isset($data['student_status']) && strcasecmp($data['student_status'], 'Inactive') === 0) ? 0 : 1;
      if ($us = mysqli_prepare($con, 'UPDATE `user` SET `user_active`=? WHERE `user_name`=?')) {
        mysqli_stmt_bind_param($us, 'is', $userActive, $sid);
        if (!mysqli_stmt_execute($us)) { $ok = false; $errors[] = 'Failed to update user activation status: ' . mysqli_error($con); }
        mysqli_stmt_close($us);
      } else {
        $ok = false; $errors[] = 'DB error preparing user activation update: ' . mysqli_error($con);
      }
    }

    // If changing Student ID, validate uniqueness then cascade
    if ($ok && $new_sid !== '' && $new_sid !== $sid) {
      // Check duplicate
      $chk = mysqli_prepare($con, 'SELECT 1 FROM student WHERE student_id=? LIMIT 1');
      if ($chk) {
        mysqli_stmt_bind_param($chk, 's', $new_sid);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $exists = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);
        if ($exists) { $ok = false; $errors[] = 'New Student ID already exists.'; }
      } else { $ok = false; $errors[] = 'DB error while checking Student ID: '.mysqli_error($con); }

      // Update primary key in student
      if ($ok) {
        $us = mysqli_prepare($con, 'UPDATE student SET student_id=? WHERE student_id=?');
        if ($us) {
          mysqli_stmt_bind_param($us, 'ss', $new_sid, $sid);
          if (!mysqli_stmt_execute($us) || mysqli_stmt_affected_rows($us) < 1) { $ok = false; $errors[] = 'Failed to update Student ID in student table.'; }
          mysqli_stmt_close($us);
        } else { $ok = false; $errors[] = 'DB error preparing Student ID update: '.mysqli_error($con); }
      }

      // Cascade update across referencing tables
      if ($ok) {
        $iq = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN ('student_id','member_id','qualification_student_id')";
        $targets = [];
        if ($ir = mysqli_query($con, $iq)) {
          while ($row = mysqli_fetch_assoc($ir)) {
            $t = $row['TABLE_NAME'];
            $c = $row['COLUMN_NAME'];
            if ($t === 'student') continue; // already handled
            $targets[] = [$t,$c];
          }
          mysqli_free_result($ir);
        } else {
          $ok = false; $errors[] = 'Failed to discover referencing tables: '.mysqli_error($con);
        }
        foreach ($targets as $tc) {
          if (!$ok) break;
          list($t,$c) = $tc;
          $sqlu = "UPDATE `".$t."` SET `".$c."`=? WHERE `".$c."`=?";
          if ($ps = mysqli_prepare($con, $sqlu)) {
            mysqli_stmt_bind_param($ps, 'ss', $new_sid, $sid);
            if (!mysqli_stmt_execute($ps)) { $ok = false; $errors[] = 'Failed to update ' . $t . '.' . $c . ': ' . mysqli_error($con); }
            mysqli_stmt_close($ps);
          } else {
            $ok = false; $errors[] = 'Prepare failed for ' . $t . '.' . $c . ': ' . mysqli_error($con);
          }
        }
        // After cascade, use new ID henceforth
        if ($ok) { $sid = $new_sid; }
      }
    }

    // If course or academic year provided, validate and update student's current enrollment
    if ($ok && ($new_coid !== '' || $new_ayear !== '')) {
      // Validate course belongs to department if provided; also fetch its department
      $courseDept = null;
      if ($new_coid !== '') {
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
        }
      }
      // Validate academic year if provided
      if ($ok && $new_ayear !== '') {
        $ayok = false;
        if ($ay = mysqli_prepare($con, 'SELECT 1 FROM academic WHERE academic_year=?')) {
          mysqli_stmt_bind_param($ay, 's', $new_ayear);
          mysqli_stmt_execute($ay);
          mysqli_stmt_store_result($ay);
          $ayok = mysqli_stmt_num_rows($ay) > 0;
          mysqli_stmt_close($ay);
        }
        if (!$ayok) { $errors[] = 'Selected academic year not found.'; }
      }
      if ($ok && !$errors) {
        // Determine current enrollment row to update: prefer Following/Active, else latest by academic year
        $enq = mysqli_prepare($con, "SELECT student_id, course_id, academic_year FROM student_enroll WHERE student_id=? ORDER BY (student_enroll_status IN ('Following','Active')) DESC, academic_year DESC LIMIT 1");
        if ($enq) {
          mysqli_stmt_bind_param($enq, 's', $sid);
          mysqli_stmt_execute($enq);
          $enr = mysqli_stmt_get_result($enq);
          $cur = $enr ? mysqli_fetch_assoc($enr) : null;
          mysqli_stmt_close($enq);
          if ($cur) {
            // Determine new values
            $target_course = ($new_coid !== '') ? $new_coid : $cur['course_id'];
            $target_ayear = ($new_ayear !== '') ? $new_ayear : $cur['academic_year'];
            // Duplicate check for composite PK
            $chk = mysqli_prepare($con, 'SELECT 1 FROM student_enroll WHERE student_id=? AND course_id=? AND academic_year=?');
            if ($chk) {
              mysqli_stmt_bind_param($chk, 'sss', $sid, $target_course, $target_ayear);
              mysqli_stmt_execute($chk);
              mysqli_stmt_store_result($chk);
              $exists_target = mysqli_stmt_num_rows($chk) > 0;
              mysqli_stmt_close($chk);
              if ($exists_target && ($target_course !== $cur['course_id'] || $target_ayear !== $cur['academic_year'])) {
                $ok = false; $errors[] = 'An enrollment already exists for the selected course and academic year.';
              }
            }
            if ($ok) {
              // Update both fields as needed
              $up = mysqli_prepare($con, 'UPDATE student_enroll SET course_id=?, academic_year=? WHERE student_id=? AND course_id=? AND academic_year=?');
              if ($up) {
                mysqli_stmt_bind_param($up, 'sssss', $target_course, $target_ayear, $cur['student_id'], $cur['course_id'], $cur['academic_year']);
                if (!mysqli_stmt_execute($up)) { $ok = false; $errors[] = 'Failed to update enrollment: '.mysqli_error($con); }
                mysqli_stmt_close($up);
              } else {
                $errors[] = 'Failed to prepare enrollment update: '.mysqli_error($con);
              }
            }
          } else {
            $errors[] = 'No enrollment found to update course.';
          }
        } else {
          $errors[] = 'Failed to query current enrollment: '.mysqli_error($con);
        }
      }
    }

    if ($ok && !$errors) {
      mysqli_commit($con);
      $_SESSION['flash_messages'] = ['Student updated successfully'];
      header('Location: '.$base.'/student/ManageStudents.php');
      exit;
    } else {
      mysqli_rollback($con);
      // Persist errors and fall through to render
      $_SESSION['flash_errors'] = $errors;
    }
  } else {
    $errors[] = 'Database error while preparing student update: '.mysqli_error($con);
    mysqli_rollback($con);
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

$title = 'Edit Student | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h3>Edit Student</h3>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo h($e); ?></div>
      <?php endforeach; ?>
      <form method="post">
        <input type="hidden" name="Sid" value="<?php echo h($sid); ?>">
        <div class="card mb-3">
          <div class="card-header">Basic Info</div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>New Registration Number (Student ID)</label>
                <input type="text" name="new_student_id" class="form-control" value="<?php echo h($sid); ?>">
                <small class="form-text text-muted">Leave unchanged to keep current ID.</small>
              </div>
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
                <label>Religion</label>
                <?php
                  $religionOptions = ['Buddhism','Hinduism','Islam','Christianity','Roman Catholic','Other'];
                  $currentReligion = $student['student_religion'] ?? '';
                  $relInList = in_array($currentReligion, $religionOptions, true);
                ?>
                <select name="student_religion" class="form-control">
                  <option value="">Select religion</option>
                  <?php foreach ($religionOptions as $rel): ?>
                    <option value="<?php echo h($rel); ?>" <?php echo (($currentReligion === $rel) ? 'selected' : ''); ?>><?php echo h($rel); ?></option>
                  <?php endforeach; ?>
                  <?php if ($currentReligion !== '' && !$relInList): ?>
                    <option value="<?php echo h($currentReligion); ?>" selected><?php echo h($currentReligion); ?> (custom)</option>
                  <?php endif; ?>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Status</label>
                <select name="student_status" class="form-control">
                  <?php $statuses=['Active','Following','Completed','Suspended','Dropout','Inactive']; foreach($statuses as $st): ?>
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
              <div class="form-group col-md-4 mt-3">
                <label>Academic Year</label>
                <select name="new_ayear" id="new_ayear" class="form-control">
                  <option value="">Keep current (<?php echo h($curEnroll['academic_year'] ?? ''); ?>)</option>
                  <?php
                  $rq2 = mysqli_query($con, 'SELECT academic_year, academic_year_status FROM academic ORDER BY academic_year DESC');
                  while ($r2 = mysqli_fetch_assoc($rq2)) {
                    $sel = ($r2['academic_year'] === ($curEnroll['academic_year'] ?? '')) ? 'selected' : '';
                    echo '<option value="'.h($r2['academic_year']).'" '.$sel.'>'.h($r2['academic_year']).' - '.h($r2['academic_year_status'])."</option>";
                  }
                  ?>
                </select>
                <small class="form-text text-muted">Changing year will move the current enrollment to the selected academic year (if no duplicate exists).</small>
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
                <?php
                  $relationOptions = ['Father','Mother','Guardian','Brother','Sister','Spouse','Relative','Friend','Neighbor','Other'];
                  $currentRelation = $student['student_em_relation'] ?? '';
                  $inList = in_array($currentRelation, $relationOptions, true);
                ?>
                <select name="student_em_relation" class="form-control">
                  <option value="">Select relation</option>
                  <?php foreach ($relationOptions as $rel): ?>
                    <option value="<?php echo h($rel); ?>" <?php echo (($currentRelation === $rel) ? 'selected' : ''); ?>><?php echo h($rel); ?></option>
                  <?php endforeach; ?>
                  <?php if ($currentRelation !== '' && !$inList): ?>
                    <option value="<?php echo h($currentRelation); ?>" selected><?php echo h($currentRelation); ?> (custom)</option>
                  <?php endif; ?>
                </select>
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
