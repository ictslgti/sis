<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

require_roles(['ADM','SAO','DIR']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$is_sao   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO';
$is_dir   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'DIR';
$can_mutate = ($is_admin || $is_sao); // DIR is view-only

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Load student ID
$sid = isset($_GET['Sid']) ? trim($_GET['Sid']) : '';
if ($sid === '') {
  header('Location: ' . $base . '/student/ManageStudents.php');
  exit;
}

// Fetch base data
$student = null;
$enroll  = null;
$departments = [];
$courses = [];

if ($r = mysqli_query($con, "SELECT * FROM `student` WHERE `student_id`='".mysqli_real_escape_string($con,$sid)."' LIMIT 1")) {
  $student = mysqli_fetch_assoc($r) ?: null;
  mysqli_free_result($r);
}

// Latest enrollment (if any)
$enrollSql = "SELECT e.* , c.course_name, c.department_id FROM student_enroll e 
              LEFT JOIN course c ON c.course_id=e.course_id
              WHERE e.student_id='".mysqli_real_escape_string($con,$sid)."' 
              ORDER BY e.student_enroll_date DESC LIMIT 1";
if ($r = mysqli_query($con, $enrollSql)) {
  $enroll = mysqli_fetch_assoc($r) ?: null;
  mysqli_free_result($r);
}

// Dropdown data
if ($r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $departments[] = $row; }
  mysqli_free_result($r);
}
if ($r = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $courses[] = $row; }
  mysqli_free_result($r);
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$can_mutate) {
    http_response_code(403);
    echo 'Forbidden: View-only access';
    exit;
  }

  $form = isset($_POST['form']) ? $_POST['form'] : '';
  if ($form === 'profile') {
    // Update student table core fields
    $fields = [
      'student_title','student_fullname','student_ininame','student_gender','student_civil','student_email',
      'student_nic','student_dob','student_phone','student_whatsapp','student_address','student_zip',
      'student_district','student_divisions','student_provice','student_blood','student_nationality',
      'student_em_name','student_em_address','student_em_phone','student_em_relation'
    ];
    $set = [];
    foreach ($fields as $f) {
      $val = isset($_POST[$f]) && $_POST[$f] !== '' ? "'".mysqli_real_escape_string($con, $_POST[$f])."'" : 'NULL';
      $set[] = "`$f` = $val";
    }
    $sql = "UPDATE `student` SET ".implode(',', $set)." WHERE `student_id`='".mysqli_real_escape_string($con,$sid)."'";
    if (!mysqli_query($con, $sql)) {
      $errors[] = 'Failed to update profile: '.mysqli_error($con);
    } else {
      $messages[] = 'Profile updated successfully';
    }
  }

  if ($form === 'enroll') {
    // Update or insert latest enrollment
    $course_id = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
    $course_mode = isset($_POST['course_mode']) ? trim($_POST['course_mode']) : '';
    $academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
    $student_enroll_date = isset($_POST['student_enroll_date']) ? trim($_POST['student_enroll_date']) : '';
    $student_enroll_exit_date = isset($_POST['student_enroll_exit_date']) ? trim($_POST['student_enroll_exit_date']) : '';
    $student_enroll_status = isset($_POST['student_enroll_status']) ? trim($_POST['student_enroll_status']) : '';

    // Check if latest exists
    $has = false;
    if ($r = mysqli_query($con, "SELECT id FROM student_enroll WHERE student_id='".mysqli_real_escape_string($con,$sid)."' ORDER BY student_enroll_date DESC LIMIT 1")) {
      $row = mysqli_fetch_assoc($r);
      $has = !!$row;
      mysqli_free_result($r);
    }
    if ($has) {
      $sql = "UPDATE student_enroll SET 
                course_id='".mysqli_real_escape_string($con,$course_id)."',
                course_mode='".mysqli_real_escape_string($con,$course_mode)."',
                academic_year='".mysqli_real_escape_string($con,$academic_year)."',
                student_enroll_date='".mysqli_real_escape_string($con,$student_enroll_date)."',
                student_enroll_exit_date=".($student_enroll_exit_date!==''?"'".mysqli_real_escape_string($con,$student_enroll_exit_date)."'":"NULL").",
                student_enroll_status='".mysqli_real_escape_string($con,$student_enroll_status)."'
              WHERE student_id='".mysqli_real_escape_string($con,$sid)."' 
              ORDER BY student_enroll_date DESC LIMIT 1";
      // MySQL does not support ORDER BY in UPDATE directly without subquery; do a subquery id fetch
      $rs = mysqli_query($con, "SELECT id FROM student_enroll WHERE student_id='".mysqli_real_escape_string($con,$sid)."' ORDER BY student_enroll_date DESC, id DESC LIMIT 1");
      $lastId = ($rs && ($tmp=mysqli_fetch_assoc($rs))) ? (int)$tmp['id'] : 0;
      if ($rs) mysqli_free_result($rs);
      if ($lastId) {
        $sql = "UPDATE student_enroll SET 
                  course_id='".mysqli_real_escape_string($con,$course_id)."',
                  course_mode='".mysqli_real_escape_string($con,$course_mode)."',
                  academic_year='".mysqli_real_escape_string($con,$academic_year)."',
                  student_enroll_date='".mysqli_real_escape_string($con,$student_enroll_date)."',
                  student_enroll_exit_date=".($student_enroll_exit_date!==''?"'".mysqli_real_escape_string($con,$student_enroll_exit_date)."'":"NULL").",
                  student_enroll_status='".mysqli_real_escape_string($con,$student_enroll_status)."'
                WHERE id=$lastId";
        if (!mysqli_query($con, $sql)) { $errors[] = 'Failed to update enrollment: '.mysqli_error($con); }
        else { $messages[] = 'Enrollment updated successfully'; }
      } else {
        $errors[] = 'Could not locate latest enrollment row.';
      }
    } else {
      $sql = "INSERT INTO student_enroll(student_id, course_id, course_mode, academic_year, student_enroll_date, student_enroll_exit_date, student_enroll_status)
              VALUES (
                '".mysqli_real_escape_string($con,$sid)."',
                '".mysqli_real_escape_string($con,$course_id)."',
                '".mysqli_real_escape_string($con,$course_mode)."',
                '".mysqli_real_escape_string($con,$academic_year)."',
                '".mysqli_real_escape_string($con,$student_enroll_date)."',
                ".($student_enroll_exit_date!==''?"'".mysqli_real_escape_string($con,$student_enroll_exit_date)."'":"NULL").",
                '".mysqli_real_escape_string($con,$student_enroll_status)."'
              )";
      if (!mysqli_query($con, $sql)) { $errors[] = 'Failed to create enrollment: '.mysqli_error($con); }
      else { $messages[] = 'Enrollment created successfully'; }
    }
  }

  // Redirect PRG
  if ($messages || $errors) {
    $_SESSION['flash_messages'] = $messages;
    $_SESSION['flash_errors'] = $errors;
    header('Location: ' . $base . '/student/StudentUnifiedEdit.php?Sid='.urlencode($sid));
    exit;
  }
}

// Flash
if (!empty($_SESSION['flash_messages'])) { $messages = $_SESSION['flash_messages']; unset($_SESSION['flash_messages']); }
if (!empty($_SESSION['flash_errors'])) { $errors = $_SESSION['flash_errors']; unset($_SESSION['flash_errors']); }

$title = 'Unified Student Edit | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid px-0 px-sm-2 px-md-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-white shadow-sm mb-2">
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/student/ManageStudents.php">Students</a></li>
      <li class="breadcrumb-item active" aria-current="page">Unified Edit</li>
    </ol>
  </nav>
  <h4 class="d-flex align-items-center mb-3"><i class="fas fa-user-cog text-primary mr-2"></i> Unified Edit: <?php echo h($sid); ?></h4>

  <?php foreach ($messages as $m): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo h($e); ?></div><?php endforeach; ?>

  <ul class="nav nav-tabs" id="ueTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Profile</a></li>
    <li class="nav-item"><a class="nav-link" id="enroll-tab" data-toggle="tab" href="#enroll" role="tab">Enrollment</a></li>
    <li class="nav-item"><a class="nav-link" id="hostel-tab" data-toggle="tab" href="#hostel" role="tab">Hostel</a></li>
    <li class="nav-item"><a class="nav-link" id="transport-tab" data-toggle="tab" href="#transport" role="tab">Transport</a></li>
  </ul>
  <div class="tab-content border-left border-right border-bottom p-3 bg-white" id="ueContent">
    <!-- Profile Tab -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
      <form method="post">
        <input type="hidden" name="form" value="profile">
        <div class="form-row">
          <div class="form-group col-md-2">
            <label>Title</label>
            <input type="text" class="form-control" name="student_title" value="<?php echo h($student['student_title'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-10">
            <label>Full Name</label>
            <input type="text" class="form-control" name="student_fullname" value="<?php echo h($student['student_fullname'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Name with Initials</label>
            <input type="text" class="form-control" name="student_ininame" value="<?php echo h($student['student_ininame'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-2">
            <label>Gender</label>
            <select class="form-control" name="student_gender" <?php echo $can_mutate?'':'disabled'; ?>>
              <?php foreach (["Male","Female","Other"] as $g): ?>
                <option value="<?php echo h($g); ?>" <?php echo (($student['student_gender'] ?? '')===$g?'selected':''); ?>><?php echo h($g); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Civil Status</label>
            <select class="form-control" name="student_civil" <?php echo $can_mutate?'':'disabled'; ?>>
              <?php foreach (["Single","Married"] as $c): ?>
                <option value="<?php echo h($c); ?>" <?php echo (($student['student_civil'] ?? '')===$c?'selected':''); ?>><?php echo h($c); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>NIC</label>
            <input type="text" class="form-control" name="student_nic" value="<?php echo h($student['student_nic'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Email</label>
            <input type="email" class="form-control" name="student_email" value="<?php echo h($student['student_email'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-4">
            <label>Phone</label>
            <input type="text" class="form-control" name="student_phone" value="<?php echo h($student['student_phone'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-4">
            <label>WhatsApp</label>
            <input type="text" class="form-control" name="student_whatsapp" value="<?php echo h($student['student_whatsapp'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Date of Birth</label>
            <input type="date" class="form-control" name="student_dob" value="<?php echo h($student['student_dob'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Nationality</label>
            <input type="text" class="form-control" name="student_nationality" value="<?php echo h($student['student_nationality'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>ZIP</label>
            <input type="text" class="form-control" name="student_zip" value="<?php echo h($student['student_zip'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Blood</label>
            <input type="text" class="form-control" name="student_blood" value="<?php echo h($student['student_blood'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Address</label>
            <input type="text" class="form-control" name="student_address" value="<?php echo h($student['student_address'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>District</label>
            <input type="text" class="form-control" name="student_district" value="<?php echo h($student['student_district'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Divisional Secretariat</label>
            <input type="text" class="form-control" name="student_divisions" value="<?php echo h($student['student_divisions'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Province</label>
            <input type="text" class="form-control" name="student_provice" value="<?php echo h($student['student_provice'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Emergency Name</label>
            <input type="text" class="form-control" name="student_em_name" value="<?php echo h($student['student_em_name'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Emergency Address</label>
            <input type="text" class="form-control" name="student_em_address" value="<?php echo h($student['student_em_address'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Emergency Phone</label>
            <input type="text" class="form-control" name="student_em_phone" value="<?php echo h($student['student_em_phone'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Emergency Relation</label>
            <input type="text" class="form-control" name="student_em_relation" value="<?php echo h($student['student_em_relation'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <?php if ($can_mutate): ?>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Profile</button>
        <?php endif; ?>
      </form>
    </div>

    <!-- Enrollment Tab -->
    <div class="tab-pane fade" id="enroll" role="tabpanel">
      <form method="post">
        <input type="hidden" name="form" value="enroll">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Course</label>
            <select class="form-control" name="course_id" <?php echo $can_mutate?'':'disabled'; ?>>
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo h($c['course_id']); ?>" <?php echo (($enroll['course_id'] ?? '')===$c['course_id']?'selected':''); ?>><?php echo h($c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Mode</label>
            <select class="form-control" name="course_mode" <?php echo $can_mutate?'':'disabled'; ?>>
              <?php foreach (["Full","Part"] as $m): ?>
                <option value="<?php echo h($m); ?>" <?php echo (($enroll['course_mode'] ?? '')===$m?'selected':''); ?>><?php echo h($m==='Full'?'Full Time':'Part Time'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Academic Year</label>
            <input type="text" class="form-control" name="academic_year" value="<?php echo h($enroll['academic_year'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-2">
            <label>Status</label>
            <select class="form-control" name="student_enroll_status" <?php echo $can_mutate?'':'disabled'; ?>>
              <?php foreach (["Following","Completed","Dropout","Long Absent"] as $st): ?>
                <option value="<?php echo h($st); ?>" <?php echo (($enroll['student_enroll_status'] ?? '')===$st?'selected':''); ?>><?php echo h($st); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Enroll Date</label>
            <input type="date" class="form-control" name="student_enroll_date" value="<?php echo h($enroll['student_enroll_date'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Exit Date</label>
            <input type="date" class="form-control" name="student_enroll_exit_date" value="<?php echo h($enroll['student_enroll_exit_date'] ?? ''); ?>" <?php echo $can_mutate?'':'disabled'; ?>>
          </div>
        </div>
        <?php if ($can_mutate): ?>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Enrollment</button>
        <?php endif; ?>
      </form>
    </div>

    <!-- Hostel Tab -->
    <div class="tab-pane fade" id="hostel" role="tabpanel">
      <div class="alert alert-info">Manage active hostel allocation for this student. Use move or leave actions below. For advanced operations, visit Hostel > Manage.</div>
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Current Allocation</label>
          <?php
            $alloc = null;
            $q = "SELECT a.id, a.room_id, a.allocated_at, a.leaving_at, a.status, r.room_no, b.name AS block_name, h.name AS hostel_name
                  FROM hostel_allocations a
                  LEFT JOIN hostel_rooms r ON r.id=a.room_id
                  LEFT JOIN hostel_blocks b ON b.id=r.block_id
                  LEFT JOIN hostels h ON h.id=b.hostel_id
                  WHERE a.student_id='".mysqli_real_escape_string($con,$sid)."' AND a.status='active' LIMIT 1";
            if ($rr = mysqli_query($con, $q)) { $alloc = mysqli_fetch_assoc($rr) ?: null; mysqli_free_result($rr);}  
          ?>
          <div class="form-control" readonly>
            <?php if ($alloc): ?>
              <?php echo h(($alloc['hostel_name']??''). ' / '.($alloc['block_name']??''). ' / '.($alloc['room_no']??'')); ?>
              <div class="small text-muted">Since <?php echo h($alloc['allocated_at'] ?? ''); ?></div>
            <?php else: ?>
              <span class="text-muted">No active allocation</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($can_mutate): ?>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label>Move to Room ID</label>
          <input type="number" id="to_room_id" class="form-control" placeholder="Room ID">
        </div>
        <div class="form-group col-md-3 align-self-end">
          <button class="btn btn-secondary" id="btnMove"><i class="fas fa-exchange-alt mr-1"></i>Move</button>
          <?php if ($alloc): ?>
            <button class="btn btn-warning" id="btnLeave"><i class="fas fa-door-open mr-1"></i>Mark as Left</button>
          <?php endif; ?>
        </div>
      </div>
      <div id="hostelMsg"></div>
      <script>
        (function(){
          var btnMove = document.getElementById('btnMove');
          if (btnMove) {
            btnMove.addEventListener('click', function(ev){
              ev.preventDefault();
              var toRoom = document.getElementById('to_room_id').value.trim();
              if (!toRoom) { alert('Enter target Room ID'); return; }
              fetch('<?php echo $base; ?>/controller/HostelAllocationActions.php', {
                method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action:'move', student_id:'<?php echo h($sid); ?>', from_room_id:'<?php echo h((string)($alloc['room_id'] ?? 0)); ?>', to_room_id: toRoom })
              }).then(r=>r.json()).then(j=>{
                var d=document.getElementById('hostelMsg');
                if (j.ok) { d.innerHTML='<div class="alert alert-success">'+(j.message||'Moved')+'</div>'; location.reload(); }
                else { d.innerHTML='<div class="alert alert-danger">'+(j.message||'Failed')+'</div>'; }
              }).catch(()=>{ document.getElementById('hostelMsg').innerHTML='<div class="alert alert-danger">Request failed</div>'; });
            });
          }
          var btnLeave = document.getElementById('btnLeave');
          if (btnLeave) {
            btnLeave.addEventListener('click', function(ev){
              ev.preventDefault();
              fetch('<?php echo $base; ?>/controller/HostelAllocationActions.php', {
                method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action:'leave', student_id:'<?php echo h($sid); ?>', room_id:'<?php echo h((string)($alloc['room_id'] ?? 0)); ?>' })
              }).then(r=>r.json()).then(j=>{
                var d=document.getElementById('hostelMsg');
                if (j.ok) { d.innerHTML='<div class="alert alert-success">'+(j.message||'Left')+'</div>'; location.reload(); }
                else { d.innerHTML='<div class="alert alert-danger">'+(j.message||'Failed')+'</div>'; }
              }).catch(()=>{ document.getElementById('hostelMsg').innerHTML='<div class="alert alert-danger">Request failed</div>'; });
            });
          }
        })();
      </script>
      <?php endif; ?>
    </div>

    <!-- Transport Tab -->
    <div class="tab-pane fade" id="transport" role="tabpanel">
      <div class="alert alert-warning mb-3"><strong>Transport (Bus/Season):</strong> No transport tables were found in the codebase. This tab is a placeholder. Tell me the exact fields (e.g., route, stop, season start/end, fee) or the DB table names and I will wire them here.</div>
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Route (placeholder)</label>
          <input type="text" class="form-control" value="" disabled>
        </div>
        <div class="form-group col-md-4">
          <label>Stop (placeholder)</label>
          <input type="text" class="form-control" value="" disabled>
        </div>
        <div class="form-group col-md-4">
          <label>Season Validity (placeholder)</label>
          <input type="text" class="form-control" value="" disabled>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
