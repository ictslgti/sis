<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
  die('DB connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($con, 'utf8');

$title = 'Change Enrollment | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$messages = [];
$errors = [];

// Load existing by GET params
$old_stid = isset($_GET['stid']) ? trim($_GET['stid']) : '';
$old_coid = isset($_GET['coid']) ? trim($_GET['coid']) : '';
$old_ayear = isset($_GET['ayear']) ? trim($_GET['ayear']) : '';

$enroll = [
  'student_id' => $old_stid,
  'course_id' => $old_coid,
  'course_mode' => '',
  'academic_year' => $old_ayear,
  'student_enroll_date' => '',
  'student_enroll_exit_date' => '',
  'student_enroll_status' => ''
];

if ($old_stid !== '' && $old_coid !== '' && $old_ayear !== '') {
  $st = mysqli_prepare($con, 'SELECT student_id, course_id, course_mode, academic_year, student_enroll_date, student_enroll_exit_date, student_enroll_status FROM student_enroll WHERE student_id=? AND course_id=? AND academic_year=? LIMIT 1');
  if ($st) {
    mysqli_stmt_bind_param($st, 'sss', $old_stid, $old_coid, $old_ayear);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    if ($rs && ($row = mysqli_fetch_assoc($rs))) {
      $enroll = $row;
    } else {
      $errors[] = 'Enrollment record not found for provided keys.';
    }
    mysqli_stmt_close($st);
  } else {
    $errors[] = 'Database error while preparing fetch.';
  }
}

// Handle POST for update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  $old_stid = trim($_POST['old_stid'] ?? '');
  $old_coid = trim($_POST['old_coid'] ?? '');
  $old_ayear = trim($_POST['old_ayear'] ?? '');

  $new_stid = trim($_POST['new_stid'] ?? '');
  $new_dept = trim($_POST['new_dept'] ?? '');
$new_coid = trim($_POST['new_coid'] ?? '');
  $new_ayear = trim($_POST['new_ayear'] ?? '');
  $course_mode = trim($_POST['course_mode'] ?? 'Full');
  $edate = trim($_POST['student_enroll_date'] ?? '');
  $exdate = trim($_POST['student_enroll_exit_date'] ?? '');
  $status = trim($_POST['student_enroll_status'] ?? 'Following');

  // Basic validation
  if ($old_stid === '' || $old_coid === '' || $old_ayear === '') { $errors[] = 'Missing original enrollment keys.'; }
  if ($new_stid === '' || $new_coid === '' || $new_ayear === '') { $errors[] = 'New Student, Course and Academic Year are required.'; }
  if ($edate === '') { $errors[] = 'Enroll date is required.'; }
  if ($exdate === '') { $exdate = $edate; }

  // Validate course belongs to selected/new department (if provided)
  if ($new_coid !== '') {
    if ($cs = mysqli_prepare($con, 'SELECT department_id FROM course WHERE course_id=?')) {
      mysqli_stmt_bind_param($cs, 's', $new_coid);
      mysqli_stmt_execute($cs);
      $cr = mysqli_stmt_get_result($cs);
      $courseDept = ($cr && ($rowc = mysqli_fetch_assoc($cr))) ? $rowc['department_id'] : null;
      mysqli_stmt_close($cs);
      if (!$courseDept) {
        $errors[] = 'Selected course not found.';
      } elseif ($new_dept !== '' && $courseDept !== $new_dept) {
        $errors[] = 'Selected course does not belong to the chosen department.';
      } else {
        // Align new_dept with course's actual department
        $new_dept = $courseDept;
      }
    } else {
      $errors[] = 'Database error while validating course.';
    }
  }

  // Check duplicates
  $will_replace = (isset($_POST['replace_if_exists']) && $_POST['replace_if_exists'] === '1');
  if (!$errors) {
    $chk = mysqli_prepare($con, 'SELECT 1 FROM student_enroll WHERE student_id=? AND course_id=? AND academic_year=?');
    mysqli_stmt_bind_param($chk, 'sss', $new_stid, $new_coid, $new_ayear);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    $exists_target = (mysqli_stmt_num_rows($chk) > 0);
    mysqli_stmt_close($chk);
    if ($exists_target && ($new_stid !== $old_stid || $new_coid !== $old_coid || $new_ayear !== $old_ayear)) {
      if (!$will_replace) {
        $errors[] = 'Target enrollment already exists for this Student/Course/Year.';
      }
    }
  }

  if (!$errors) {
    mysqli_begin_transaction($con);
    $ok = true;

    // If student_id changes, update student table first
    if ($new_stid !== $old_stid) {
      // Ensure new student_id not taken
      $chk2 = mysqli_prepare($con, 'SELECT 1 FROM student WHERE student_id=?');
      mysqli_stmt_bind_param($chk2, 's', $new_stid);
      mysqli_stmt_execute($chk2);
      mysqli_stmt_store_result($chk2);
      $student_taken = (mysqli_stmt_num_rows($chk2) > 0);
      mysqli_stmt_close($chk2);

      if ($student_taken) {
        $ok = false; $errors[] = 'New registration number (student_id) already exists in student table.';
      } else {
        // 1) Update student table (triggers will sync user.user_name)
        $u = mysqli_prepare($con, 'UPDATE student SET student_id=? WHERE student_id=?');
        if ($u) {
          mysqli_stmt_bind_param($u, 'ss', $new_stid, $old_stid);
          if (!mysqli_stmt_execute($u)) { $ok = false; $errors[] = 'Failed to update student_id in student table.'; }
          mysqli_stmt_close($u);
        } else { $ok = false; $errors[] = 'DB error preparing student update.'; }

        // 2) Cascade student_id change across all referencing tables
        if ($ok) {
          // Discover referencing tables/columns dynamically to avoid missing any
          $targets = [];
          $iq = "SELECT TABLE_NAME, COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND COLUMN_NAME IN ('student_id','member_id','qualification_student_id')";
          if ($ir = mysqli_query($con, $iq)) {
            while ($row = mysqli_fetch_assoc($ir)) {
              $t = $row['TABLE_NAME'];
              $c = $row['COLUMN_NAME'];
              // Exclude core tables handled separately or irrelevant
              if (in_array($t, ['student','student_enroll'], true)) { continue; }
              $targets[$t] = $c; // last one wins if duplicates, acceptable here
            }
            mysqli_free_result($ir);
          }

          // Add known aliases not always present in INFORMATION_SCHEMA (older installs) as fallback
          $fallbacks = [
            'attendance' => 'student_id',
            'pays' => 'student_id',
            'hostel_student_details' => 'student_id',
            'onpeak_request' => 'student_id',
            'onpeak_delete_details' => 'student_id',
            'off_peak' => 'student_id',
            'hik_user_map' => 'student_id',
            'manage_final_place' => 'student_id',
            'ojt' => 'student_id',
            'assessments_marks' => 'student_id',
            'feedback_done' => 'student_id',
            'issued_books' => 'member_id',
            'issued_books_deleted' => 'member_id',
            'student_qualification' => 'qualification_student_id',
          ];
          foreach ($fallbacks as $ft => $fc) { if (!isset($targets[$ft])) { $targets[$ft] = $fc; } }

          foreach ($targets as $table => $col) {
            if (!$ok) break;
            $sql = "UPDATE `".$table."` SET `".$col."`=? WHERE `".$col."`=?";
            $stU = mysqli_prepare($con, $sql);
            if ($stU) {
              mysqli_stmt_bind_param($stU, 'ss', $new_stid, $old_stid);
              if (!mysqli_stmt_execute($stU)) {
                $ok = false; $errors[] = 'Failed to update ' . $table . ' (' . $col . ') for new student_id.';
              }
              mysqli_stmt_close($stU);
            }
          }
        }
      }
    }

    // Update or replace enrollment row keys and fields
    if ($ok) {
      if ($will_replace && ($new_stid !== $old_stid || $new_coid !== $old_coid || $new_ayear !== $old_ayear)) {
        // Replace behavior: update the TARGET row's non-key fields, then delete the original row
        $updT = mysqli_prepare($con, 'UPDATE student_enroll SET course_mode=?, student_enroll_date=?, student_enroll_exit_date=?, student_enroll_status=? WHERE student_id=? AND course_id=? AND academic_year=?');
        if ($updT) {
          mysqli_stmt_bind_param($updT, 'sssssss', $course_mode, $edate, $exdate, $status, $new_stid, $new_coid, $new_ayear);
          if (!mysqli_stmt_execute($updT)) { $ok = false; $errors[] = 'Failed to update target enrollment record.'; }
          mysqli_stmt_close($updT);
        } else { $ok = false; $errors[] = 'DB error preparing target update.'; }

        if ($ok) {
          // Delete the original row
          $del = mysqli_prepare($con, 'DELETE FROM student_enroll WHERE student_id=? AND course_id=? AND academic_year=?');
          if ($del) {
            mysqli_stmt_bind_param($del, 'sss', $old_stid, $old_coid, $old_ayear);
            if (!mysqli_stmt_execute($del)) { $ok = false; $errors[] = 'Failed to delete original enrollment row.'; }
            mysqli_stmt_close($del);
          } else { $ok = false; $errors[] = 'DB error preparing delete of original row.'; }
        }
      } else {
        // Normal behavior: update old row and change keys to new keys
        $upd = mysqli_prepare($con, 'UPDATE student_enroll SET student_id=?, course_id=?, course_mode=?, academic_year=?, student_enroll_date=?, student_enroll_exit_date=?, student_enroll_status=? WHERE student_id=? AND course_id=? AND academic_year=?');
        if ($upd) {
          mysqli_stmt_bind_param($upd, 'ssssssssss', $new_stid, $new_coid, $course_mode, $new_ayear, $edate, $exdate, $status, $old_stid, $old_coid, $old_ayear);
          if (!mysqli_stmt_execute($upd) || mysqli_stmt_affected_rows($upd) < 1) {
            $ok = false; $errors[] = 'Failed to update enrollment record.';
          }
          mysqli_stmt_close($upd);
        } else { $ok = false; $errors[] = 'DB error preparing enrollment update.'; }
      }
    }

    if ($ok) {
      mysqli_commit($con);
      $messages[] = 'Enrollment changed successfully.';
      // Refresh current values
      $enroll['student_id'] = $new_stid;
      $enroll['course_id'] = $new_coid;
      $enroll['course_mode'] = $course_mode;
      $enroll['academic_year'] = $new_ayear;
      $enroll['student_enroll_date'] = $edate;
      $enroll['student_enroll_exit_date'] = $exdate;
      $enroll['student_enroll_status'] = $status;
      $old_stid = $new_stid; $old_coid = $new_coid; $old_ayear = $new_ayear;
    } else {
      mysqli_rollback($con);
    }
  }
}
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h3>Change Student Enrollment</h3>
      <?php foreach ($messages as $m): ?>
        <div class="alert alert-success"><?php echo h($m); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo h($e); ?></div>
      <?php endforeach; ?>
      <div class="card mb-3">
        <div class="card-header">Find Enrollment (Current)</div>
        <div class="card-body">
          <form method="get" class="mb-0">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Student</label>
                <select name="stid" class="form-control">
                  <option value="">Select student</option>
                  <?php
                  $stq = mysqli_query($con, 'SELECT student_id, student_fullname FROM student ORDER BY student_fullname, student_id');
                  while ($sr = mysqli_fetch_assoc($stq)) {
                    $sel = ($sr['student_id'] === $old_stid) ? 'selected' : '';
                    echo '<option value="'.h($sr['student_id']).'" '.$sel.'>'.h($sr['student_fullname']).' ('.h($sr['student_id']).')</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>Course</label>
                <select name="coid" class="form-control">
                  <option value="">Select course</option>
                  <?php
                  $rqf = mysqli_query($con, 'SELECT course_id, course_name FROM course ORDER BY course_name');
                  while ($rf = mysqli_fetch_assoc($rqf)) {
                    $sel = ($rf['course_id'] === $old_coid) ? 'selected' : '';
                    echo '<option value="'.h($rf['course_id']).'" '.$sel.'>'.h($rf['course_name']).' ('.h($rf['course_id']).')</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Academic Year</label>
                <select name="ayear" class="form-control">
                  <option value="">Select year</option>
                  <?php
                  $rq2f = mysqli_query($con, 'SELECT academic_year, academic_year_status FROM academic ORDER BY academic_year DESC');
                  while ($r2f = mysqli_fetch_assoc($rq2f)) {
                    $sel = ($r2f['academic_year'] === $old_ayear) ? 'selected' : '';
                    echo '<option value="'.h($r2f['academic_year']).'" '.$sel.'>'.h($r2f['academic_year']).' - '.h($r2f['academic_year_status']).'</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary btn-block">Load</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="alert alert-warning">
        <strong>Note:</strong> Changing the Registration Number (student_id) updates it in <code>student</code>, this enrollment, and cascades to key modules (attendance, payments, hostel, on/off-peak, OJT, etc.) within a single transaction. If any part fails, no changes are saved.
      </div>
      <form method="post">
        <input type="hidden" name="old_stid" value="<?php echo h($old_stid); ?>">
        <input type="hidden" name="old_coid" value="<?php echo h($old_coid); ?>">
        <input type="hidden" name="old_ayear" value="<?php echo h($old_ayear); ?>">

        <div class="card mb-3">
          <div class="card-header">Current Enrollment</div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Student ID (current)</label>
                <input type="text" class="form-control" value="<?php echo h($old_stid); ?>" disabled>
              </div>
              <div class="form-group col-md-4">
                <label>Course (current)</label>
                <input type="text" class="form-control" value="<?php echo h($old_coid); ?>" disabled>
              </div>
              <div class="form-group col-md-4">
                <label>Academic Year (current)</label>
                <input type="text" class="form-control" value="<?php echo h($old_ayear); ?>" disabled>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">New Enrollment</div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>New Registration Number (student_id)</label>
                <input type="text" name="new_stid" class="form-control" value="<?php echo h($enroll['student_id']); ?>" required>
              </div>
              <div class="form-group col-md-4">
                <label>New Department</label>
                <select name="new_dept" id="new_dept" class="form-control">
                  <option value="">Select department</option>
                  <?php
                  $current_dept = null;
                  if (!empty($enroll['course_id'])) {
                    if ($cd = mysqli_prepare($con, 'SELECT department_id FROM course WHERE course_id=?')) {
                      mysqli_stmt_bind_param($cd, 's', $enroll['course_id']);
                      mysqli_stmt_execute($cd);
                      $cdr = mysqli_stmt_get_result($cd);
                      if ($cdr && ($cdrx = mysqli_fetch_assoc($cdr))) { $current_dept = $cdrx['department_id']; }
                      mysqli_stmt_close($cd);
                    }
                  }
                  $rd = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name');
                  while ($d = mysqli_fetch_assoc($rd)) {
                    $sel = ($d['department_id'] === ($current_dept ?? '')) ? 'selected' : '';
                    echo '<option value="'.h($d['department_id']).'" '.$sel.'>'.h($d['department_name']).' ('.h($d['department_id']).')</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>New Course</label>
                <select name="new_coid" id="new_coid" class="form-control" required>
                  <option value="">Select course</option>
                  <?php
                  $rq = mysqli_query($con, 'SELECT course_id, course_name, department_id FROM course ORDER BY course_name');
                  while ($r = mysqli_fetch_assoc($rq)) {
                    $sel = ($r['course_id'] === ($enroll['course_id'] ?? '')) ? 'selected' : '';
                    echo '<option value="'.h($r['course_id']).'" data-dept="'.h($r['department_id']).'" '.$sel.'>'.h($r['course_name']).' ('.h($r['course_id']).')</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>New Academic Year</label>
                <select name="new_ayear" class="form-control" required>
                  <option value="">Select year</option>
                  <?php
                  $rq2 = mysqli_query($con, 'SELECT academic_year, academic_year_status FROM academic ORDER BY academic_year DESC');
                  while ($r2 = mysqli_fetch_assoc($rq2)) {
                    $sel = ($r2['academic_year'] === ($enroll['academic_year'] ?? '')) ? 'selected' : '';
                    echo '<option value="'.h($r2['academic_year']).'" '.$sel.'>'.h($r2['academic_year']).' - '.h($r2['academic_year_status']).'</option>';
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Course Mode</label>
                <select name="course_mode" class="form-control">
                  <?php $m = $enroll['course_mode'] ?? 'Full'; ?>
                  <option value="Full" <?php echo ($m==='Full')?'selected':''; ?>>Full Time</option>
                  <option value="Part" <?php echo ($m==='Part')?'selected':''; ?>>Part Time</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Enroll Date</label>
                <input type="date" name="student_enroll_date" class="form-control" value="<?php echo h($enroll['student_enroll_date']); ?>" required>
              </div>
              <div class="form-group col-md-3">
                <label>Exit Date</label>
                <input type="date" name="student_enroll_exit_date" class="form-control" value="<?php echo h($enroll['student_enroll_exit_date']); ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Status</label>
                <select name="student_enroll_status" class="form-control">
                  <?php $st = $enroll['student_enroll_status'] ?? 'Following'; ?>
                  <?php foreach (['Following','Completed','Dropout','Long Absent'] as $s): ?>
                    <option value="<?php echo h($s); ?>" <?php echo ($st===$s)?'selected':''; ?>><?php echo h($s); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-3">
        <div class="form-check mb-2">
          <input type="checkbox" class="form-check-input" id="replace_if_exists" name="replace_if_exists" value="1">
          <label class="form-check-label" for="replace_if_exists">Replace if target enrollment exists</label>
        </div>
        <button type="submit" name="save" class="btn btn-primary">Save Changes</button>
        <?php if ($old_stid && $old_coid): ?>
        <a class="btn btn-secondary" href="<?php echo h($base ?? ''); ?>/student/StudentReEnroll.php?stid=<?php echo h($old_stid); ?>&coid=<?php echo h($old_coid); ?>&ayear=<?php echo h($old_ayear); ?>">Back</a>
        <?php endif; ?>
      </div>
      </form>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>

<script>
  // Client-side: Filter courses by chosen department in New Enrollment
  (function(){
    var dept = document.getElementById('new_dept');
    var course = document.getElementById('new_coid');
    if (!dept || !course) return;
    var all = Array.prototype.slice.call(course.options).map(function(o){ return {value:o.value, text:o.text, dept:o.getAttribute('data-dept')}; });
    function apply(){
      var d = dept.value;
      var keepSelected = course.value;
      while (course.options.length) course.remove(0);
      var opt = document.createElement('option'); opt.value=''; opt.text='Select course'; course.add(opt);
      all.forEach(function(it){
        if (!it.value) return;
        if (!d || it.dept === d){ var o = document.createElement('option'); o.value=it.value; o.text=it.text; o.setAttribute('data-dept', it.dept); course.add(o); }
      });
      if (keepSelected) {
        for (var i=0;i<course.options.length;i++){ if (course.options[i].value===keepSelected){ course.selectedIndex=i; break; } }
      }
    }
    dept.addEventListener('change', apply);
    // Initialize on load
    apply();
  })();
</script>
