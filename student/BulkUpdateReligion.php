<?php
// student/BulkUpdateReligion.php - ADM bulk update of student religion
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

// Role check: only ADM
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'ADM') {
  echo '<div class="container mt-4"><div class="alert alert-danger">Access denied.</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

$success = '';
$error = '';

// Handle bulk update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='bulk_update') {
  $religion = isset($_POST['religion']) ? trim($_POST['religion']) : '';
  $ids = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? $_POST['student_ids'] : [];
  $ids = array_values(array_filter(array_map('trim', $ids)));

  if ($religion === '' || empty($ids)) {
    $error = 'Please select at least one student and a religion to apply.';
  } else {
    // Update using prepared statement in batches
    $ok = true;
    mysqli_begin_transaction($con);
    try {
      $st = mysqli_prepare($con, "UPDATE student SET student_religion=? WHERE student_id=?");
      if (!$st) { throw new Exception(mysqli_error($con)); }
      foreach ($ids as $sid) {
        $sid = (string)$sid;
        mysqli_stmt_bind_param($st, 'ss', $religion, $sid);
        if (!mysqli_stmt_execute($st)) { throw new Exception(mysqli_stmt_error($st)); }
      }
      mysqli_stmt_close($st);
      mysqli_commit($con);
      $success = 'Updated religion for '.count($ids).' students.';
    } catch (Exception $ex) {
      mysqli_rollback($con);
      $error = 'Update failed: '.htmlspecialchars($ex->getMessage());
    }
  }
}

// Filters
$selDept = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$selCourse = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Load departments
$departments = [];
$r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
while ($r && ($row=mysqli_fetch_assoc($r))) { $departments[] = $row; }

// Load courses for selected department
$courses = [];
if ($selDept !== '') {
  $cdq = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con,$selDept)."' ORDER BY course_name");
  while ($cdq && ($row=mysqli_fetch_assoc($cdq))) { $courses[] = $row; }
}

// Load students
$students = [];
$where = ' WHERE 1=1 ';
if ($selDept !== '') { $where .= " AND c.department_id='".mysqli_real_escape_string($con,$selDept)."'"; }
if ($selCourse !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$selCourse)."'"; }
if ($search !== '') {
  $q = mysqli_real_escape_string($con, $search);
  $where .= " AND (s.student_id LIKE '%$q%' OR s.student_fullname LIKE '%$q%')";
}
$sql = "SELECT s.student_id, s.student_fullname, s.student_religion, c.course_name
        FROM student_enroll se
        JOIN course c ON c.course_id=se.course_id
        JOIN student s ON s.student_id=se.student_id
        $where
        GROUP BY s.student_id, s.student_fullname, s.student_religion, c.course_name
        ORDER BY s.student_id";
$res = mysqli_query($con, $sql);
if ($res) { while($row=mysqli_fetch_assoc($res)) { $students[] = $row; } }

// Religions list (editable)
$religions = [
  'Buddhism', 'Hinduism', 'Islam', 'Christianity', 'Roman Catholic', 'Other'
];
// Also add distinct religions from DB
$rl = mysqli_query($con, "SELECT DISTINCT student_religion AS r FROM student WHERE student_religion IS NOT NULL AND student_religion<>'' ORDER BY r");
if ($rl) { while($rw=mysqli_fetch_assoc($rl)) { if ($rw['r'] && !in_array($rw['r'], $religions, true)) { $religions[] = $rw['r']; } } }

?>
<div class="container mt-3">
  <h3>Bulk Update Student Religion</h3>
  <?php if ($success): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($success); ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($error); ?><button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div><?php endif; ?>

  <form method="get" class="mb-3">
    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Department</label>
        <select name="department_id" class="form-control" onchange="this.form.submit()">
          <option value="">-- All --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?php echo htmlspecialchars($d['department_id']); ?>" <?php echo $selDept===$d['department_id']?'selected':''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label>Course</label>
        <select name="course_id" class="form-control" onchange="this.form.submit()" <?php echo $selDept?'':'disabled'; ?>>
          <option value="">-- All --</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo $selCourse===$c['course_id']?'selected':''; ?>><?php echo htmlspecialchars($c['course_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label>Search (ID/Name)</label>
        <div class="input-group">
          <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="e.g., 2025/ICT/.. or John">
          <div class="input-group-append">
            <button class="btn btn-outline-secondary">Filter</button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <form method="post" onsubmit="return confirm('Apply the selected religion to the selected students?');">
    <input type="hidden" name="action" value="bulk_update">
    <div class="form-row align-items-end">
      <div class="form-group col-md-4">
        <label>Religion to apply</label>
        <select name="religion" class="form-control" required>
          <option value="">-- Select Religion --</option>
          <?php foreach ($religions as $rel): ?>
            <option value="<?php echo htmlspecialchars($rel); ?>"><?php echo htmlspecialchars($rel); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <button type="submit" class="btn btn-primary btn-block">Apply to Selected</button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-bordered">
        <thead>
          <tr>
            <th><input type="checkbox" id="chk-all"></th>
            <th>Student ID</th>
            <th>Name</th>
            <th>Course</th>
            <th>Current Religion</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($students)): ?>
            <?php foreach ($students as $s): ?>
              <tr>
                <td><input type="checkbox" name="student_ids[]" value="<?php echo htmlspecialchars($s['student_id']); ?>"></td>
                <td><?php echo htmlspecialchars($s['student_id']); ?></td>
                <td><?php echo htmlspecialchars($s['student_fullname']); ?></td>
                <td><?php echo htmlspecialchars($s['course_name']); ?></td>
                <td><?php echo htmlspecialchars($s['student_religion']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted">No students found for the selected filters.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function(){
    var all = document.getElementById('chk-all');
    if (all) {
      all.addEventListener('change', function(){
        document.querySelectorAll('input[type="checkbox"][name="student_ids[]"]').forEach(function(cb){ cb.checked = all.checked; });
      });
    }
  });
</script>
<?php require_once __DIR__ . '/../footer.php'; ?>
