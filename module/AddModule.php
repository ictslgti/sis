<?php
// BLOCK#1 START DON'T CHANGE THE ORDER
$title = "Add/Edit Module | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// END DON'T CHANGE THE ORDER

$isADM = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
$deptCode = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';
$base = defined('APP_BASE') ? APP_BASE : '';

// Initial values
$module = [
  'module_id' => '',
  'course_id' => '',
  'module_name' => '',
  'semester_id' => '',
  'module_lecture_hours' => '',
  'module_practical_hours' => '',
  'module_self_study_hours' => '',
  'module_learning_hours' => '',
  'module_relative_unit' => ''
];
$editing = false;

// If arriving from Module list with a course_id filter, prefill it when creating
if (isset($_GET['course_id']) && $_GET['course_id'] !== '') {
  $module['course_id'] = (string)$_GET['course_id'];
}

// Load for edit
if (isset($_GET['edits'], $_GET['editc']) && $_GET['edits'] !== '' && $_GET['editc'] !== '') {
  $editing = true;
  $eid = $_GET['edits'];
  $ecid = $_GET['editc'];
  if ($isHOD && $deptCode !== '') {
    $sql = "SELECT m.* FROM module m INNER JOIN course c ON c.course_id=m.course_id 
            WHERE m.module_id=? AND m.course_id=? AND c.department_id=? LIMIT 1";
    if ($st = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($st, 'sss', $eid, $ecid, $deptCode);
      mysqli_stmt_execute($st);
      $rs = mysqli_stmt_get_result($st);
      if ($rs && ($row = mysqli_fetch_assoc($rs))) { $module = $row; }
      mysqli_stmt_close($st);
    }
  } else {
    $sql = "SELECT * FROM module WHERE module_id=? AND course_id=? LIMIT 1";
    if ($st = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($st, 'ss', $eid, $ecid);
      mysqli_stmt_execute($st);
      $rs = mysqli_stmt_get_result($st);
      if ($rs && ($row = mysqli_fetch_assoc($rs))) { $module = $row; }
      mysqli_stmt_close($st);
    }
  }
}

$msg = '';

// Handle POST add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
  $mid = trim((string)($_POST['module_id'] ?? ''));
  $cid = trim((string)($_POST['course_id'] ?? ''));
  $name = trim((string)($_POST['module_name'] ?? ''));
  $sem = trim((string)($_POST['semester_id'] ?? ''));
  $lec = (string)($_POST['module_lecture_hours'] ?? '');
  $prac = (string)($_POST['module_practical_hours'] ?? '');
  $self = (string)($_POST['module_self_study_hours'] ?? '');
  $learn = (string)($_POST['module_learning_hours'] ?? '');
  $rel = trim((string)($_POST['module_relative_unit'] ?? ''));

  // Enforce HOD scoping to their department's courses
  if ($isHOD && $deptCode !== '') {
    $chk = mysqli_prepare($con, "SELECT 1 FROM course WHERE course_id=? AND department_id=? LIMIT 1");
    if ($chk) {
      mysqli_stmt_bind_param($chk, 'ss', $cid, $deptCode);
      mysqli_stmt_execute($chk);
      $rs = mysqli_stmt_get_result($chk);
      $okCourse = $rs && mysqli_num_rows($rs) === 1;
      mysqli_stmt_close($chk);
      if (!$okCourse) {
        $msg = '<div class="alert alert-danger">You can only manage modules for courses in your department.</div>';
        $act = '';
      }
    }
  }

  // Compute learning hours if not provided
  $l = is_numeric($lec) ? (int)$lec : 0;
  $p = is_numeric($prac) ? (int)$prac : 0;
  $s = is_numeric($self) ? (int)$self : 0;
  $total = $l + $p + $s;
  if ($learn === '' || !is_numeric($learn)) { $learn = (string)$total; }

  if ($act === 'create') {
    if ($mid !== '' && $cid !== '' && $name !== '') {
      $sql = "INSERT INTO module (module_id, course_id, module_name, semester_id, module_lecture_hours, module_practical_hours, module_self_study_hours, module_learning_hours, module_relative_unit)
              VALUES (?,?,?,?,?,?,?,?,?)";
      if ($st = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($st, 'sssssssss', $mid, $cid, $name, $sem, $lec, $prac, $self, $learn, $rel);
        if (@mysqli_stmt_execute($st)) {
          $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">Module created successfully<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
          echo '<script>setTimeout(function(){ location.href = "'.($base).'/module/Module.php?course_id='.rawurlencode($cid).'"; }, 500);</script>';
        } else {
          $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to create module. It may already exist.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
        mysqli_stmt_close($st);
      }
    } else {
      $msg = '<div class="alert alert-warning">Module ID, Course, and Name are required.</div>';
    }
  } elseif ($act === 'update') {
    if ($mid !== '' && $cid !== '' && $name !== '') {
      // HOD can only update within own department (enforced above for course_id)
      $sql = "UPDATE module SET module_name=?, semester_id=?, module_lecture_hours=?, module_practical_hours=?, module_self_study_hours=?, module_learning_hours=?, module_relative_unit=? WHERE module_id=? AND course_id=?";
      if ($st = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($st, 'sssssssss', $name, $sem, $lec, $prac, $self, $learn, $rel, $mid, $cid);
        if (@mysqli_stmt_execute($st)) {
          $msg = '<div class="alert alert-success alert-dismissible fade show" role="alert">Module updated successfully<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
          echo '<script>setTimeout(function(){ location.href = "'.($base).'/module/Module.php?course_id='.rawurlencode($cid).'"; }, 500);</script>';
        } else {
          $msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to update module.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        }
        mysqli_stmt_close($st);
      }
    } else {
      $msg = '<div class="alert alert-warning">Module ID, Course, and Name are required.</div>';
    }
  }

  // Preserve form values on error
  $module = [
    'module_id' => $mid,
    'course_id' => $cid,
    'module_name' => $name,
    'semester_id' => $sem,
    'module_lecture_hours' => $lec,
    'module_practical_hours' => $prac,
    'module_self_study_hours' => $self,
    'module_learning_hours' => $learn,
    'module_relative_unit' => $rel,
  ];
  $editing = ($act === 'update') || $editing;
}

?>

<div class="container mt-3">
  <?php echo $msg; ?>
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0"><?php echo $editing ? 'Edit Module' : 'Add Module'; ?></h5>
        <small class="text-muted">Fill the form to <?php echo $editing ? 'update' : 'create'; ?> a module</small>
      </div>
      <div>
        <a href="<?php echo $base; ?>/module/Module.php<?php echo $module['course_id']?('?course_id='.urlencode($module['course_id'])):''; ?>" class="btn btn-outline-secondary btn-sm">Back</a>
      </div>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label for="module_id">Module ID</label>
            <input type="text" class="form-control" id="module_id" name="module_id" value="<?php echo htmlspecialchars($module['module_id']); ?>" <?php echo $editing?'readonly':''; ?> required>
          </div>
          <div class="form-group col-md-8">
            <label for="module_name">Module Name</label>
            <input type="text" class="form-control" id="module_name" name="module_name" value="<?php echo htmlspecialchars($module['module_name']); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="course_id">Course</label>
            <select id="course_id" name="course_id" class="custom-select" <?php echo $editing? 'disabled' : ''; ?> required>
              <option value="">-- Select --</option>
              <?php
                $q = "SELECT course_id, course_name FROM course";
                $where = [];
                if ($isHOD && $deptCode !== '') { $where[] = "department_id='".mysqli_real_escape_string($con,$deptCode)."'"; }
                if (!empty($where)) { $q .= ' WHERE '.implode(' AND ',$where); }
                $q .= ' ORDER BY course_name';
                if ($rs = mysqli_query($con, $q)) {
                  while ($r = mysqli_fetch_assoc($rs)) {
                    $sel = ($r['course_id'] === $module['course_id']) ? 'selected' : '';
                    echo '<option value="'.htmlspecialchars($r['course_id']).'" '.$sel.'>'.htmlspecialchars($r['course_name']).' ('.htmlspecialchars($r['course_id']).')</option>';
                  }
                  mysqli_free_result($rs);
                }
              ?>
            </select>
            <?php if ($editing) { echo '<input type="hidden" name="course_id" value="'.htmlspecialchars($module['course_id']).'">'; } ?>
          </div>
          <div class="form-group col-md-6">
            <label for="semester_id">Semester ID</label>
            <input type="text" class="form-control" id="semester_id" name="semester_id" value="<?php echo htmlspecialchars($module['semester_id']); ?>" placeholder="e.g., S1, S2" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label for="module_lecture_hours">Lecture Hours</label>
            <input type="number" min="0" class="form-control" id="module_lecture_hours" name="module_lecture_hours" value="<?php echo htmlspecialchars($module['module_lecture_hours']); ?>" required>
          </div>
          <div class="form-group col-md-4">
            <label for="module_practical_hours">Practical Hours</label>
            <input type="number" min="0" class="form-control" id="module_practical_hours" name="module_practical_hours" value="<?php echo htmlspecialchars($module['module_practical_hours']); ?>" required>
          </div>
          <div class="form-group col-md-4">
            <label for="module_self_study_hours">Self Study Hours</label>
            <input type="number" min="0" class="form-control" id="module_self_study_hours" name="module_self_study_hours" value="<?php echo htmlspecialchars($module['module_self_study_hours']); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="module_learning_hours">Learning Hours (total)</label>
            <input type="number" min="0" class="form-control" id="module_learning_hours" name="module_learning_hours" value="<?php echo htmlspecialchars($module['module_learning_hours']); ?>" placeholder="Auto = L+P+S (leave blank to auto)">
          </div>
          <div class="form-group col-md-6">
            <label for="module_relative_unit">Relative Unit</label>
            <input type="text" class="form-control" id="module_relative_unit" name="module_relative_unit" value="<?php echo htmlspecialchars($module['module_relative_unit']); ?>" placeholder="Optional">
          </div>
        </div>

        <div class="d-flex">
          <?php if ($editing): ?>
            <button type="submit" class="btn btn-primary mr-2">Save Changes</button>
            <a href="<?php echo $base; ?>/module/Module.php?course_id=<?php echo urlencode($module['course_id']); ?>" class="btn btn-secondary">Cancel</a>
          <?php else: ?>
            <button type="submit" class="btn btn-success mr-2">Create Module</button>
            <a href="<?php echo $base; ?>/module/Module.php<?php echo $module['course_id']?('?course_id='.urlencode($module['course_id'])):''; ?>" class="btn btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  .card-header h5 { font-weight: 600; }
  label { font-weight: 600; }
</style>

<?php include_once __DIR__ . '/../footer.php'; ?>
