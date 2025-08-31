<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Access control: Admin or Director (DIR). DIR will be view-only.
require_roles(['ADM','DIR']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$is_dir   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'DIR';

// Helpers
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Handle actions
$messages = [];
$errors = [];

// Block mutations for non-admins
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$is_admin) {
    http_response_code(403);
    echo 'Forbidden: View-only access';
    exit;
  }
  // Single delete
  if (isset($_POST['delete_sid'])) {
    $sid = $_POST['delete_sid'];
    $stmt = mysqli_prepare($con, "UPDATE student SET student_status='Inactive' WHERE student_id=?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $sid);
      mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);
      if ($affected > 0) { $messages[] = "Student $sid set to Inactive"; }
      else { $errors[] = "No changes for $sid"; }
    } else {
      $errors[] = 'DB error (single)';
    }
  }
  // Bulk delete
  if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_inactivate') {
    $sids = isset($_POST['sids']) && is_array($_POST['sids']) ? array_values(array_filter($_POST['sids'])) : [];
    if (!$sids) {
      $errors[] = 'No students selected for bulk inactivate.';
    } else {
      // Build a single UPDATE ... IN (...) with proper escaping
      $inParts = [];
      foreach ($sids as $sid) {
        $inParts[] = "'" . mysqli_real_escape_string($con, $sid) . "'";
      }
      $inList = implode(',', $inParts);
      $q = "UPDATE student SET student_status='Inactive' WHERE student_id IN (".$inList.")";
      if (mysqli_query($con, $q)) {
        $affected = mysqli_affected_rows($con);
        $messages[] = ($affected > 0) ? 'Selected students set to Inactive' : 'No rows updated';
      } else {
        $errors[] = 'Bulk update failed';
      }
    }
  }
  // Redirect to avoid resubmission
  if ($messages || $errors) {
    $_SESSION['flash_messages'] = $messages;
    $_SESSION['flash_errors'] = $errors;
    header('Location: ' . $base . '/student/ManageStudents.php');
    exit;
  }
}

// Flash
if (!empty($_SESSION['flash_messages'])) { $messages = $_SESSION['flash_messages']; unset($_SESSION['flash_messages']); }
if (!empty($_SESSION['flash_errors'])) { $errors = $_SESSION['flash_errors']; unset($_SESSION['flash_errors']); }

// Filters
$fid = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$fstatus = isset($_GET['status']) ? $_GET['status'] : '';

// New filters: department, course, gender
$fdept   = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$fcourse = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$fgender = isset($_GET['gender']) ? trim($_GET['gender']) : '';

// For DIR (view-only), restrict to Active students regardless of requested filter
if ($is_dir) {
  $fstatus = 'Active';
}

$where = [];
$params = [];
// Join with enrollment/course/department to support department/course filtering
$sql = "SELECT s.student_id, s.student_fullname, s.student_email, s.student_phone, s.student_status, s.student_gender,
               e.course_id, c.course_name, d.department_id, d.department_name
        FROM student s
        LEFT JOIN student_enroll e ON e.student_id = s.student_id AND e.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = e.course_id
        LEFT JOIN department d ON d.department_id = c.department_id";
if ($fid !== '') {
  $where[] = "s.student_id = '" . mysqli_real_escape_string($con, $fid) . "'";
}
if ($fstatus !== '') {
  $where[] = "s.student_status = '" . mysqli_real_escape_string($con, $fstatus) . "'";
}
if ($fdept !== '') {
  $where[] = "d.department_id = '" . mysqli_real_escape_string($con, $fdept) . "'";
}
if ($fcourse !== '') {
  $where[] = "c.course_id = '" . mysqli_real_escape_string($con, $fcourse) . "'";
}
if ($fgender !== '') {
  $where[] = "s.student_gender = '" . mysqli_real_escape_string($con, $fgender) . "'";
}
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY s.student_id ASC LIMIT 500';
$res = mysqli_query($con, $sql);

// Load dropdown data: departments and courses (for filters)
$departments = [];
if ($r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $departments[] = $row; }
  mysqli_free_result($r);
}
$courses = [];
if ($r = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $courses[] = $row; }
  mysqli_free_result($r);
}

// Include standard head and menu to load CSS/JS
$title = 'Manage Students | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h3>Manage Students <?php echo $is_admin ? '(Admin)' : '(View Only)'; ?></h3>

      <?php foreach ($messages as $m): ?>
        <div class="alert alert-success"><?php echo h($m); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo h($e); ?></div>
      <?php endforeach; ?>

      <form class="form-inline mb-3" method="get" action="">
        <div class="form-group mr-2">
          <label for="fid" class="mr-2">Student ID</label>
          <input type="text" id="fid" name="student_id" class="form-control" value="<?php echo h($fid); ?>" placeholder="2025/AUT/...">
        </div>
        <div class="form-group mr-2">
          <label for="fdept" class="mr-2">Department</label>
          <select id="fdept" name="department_id" class="form-control">
            <option value="">-- Any --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?php echo h($d['department_id']); ?>" <?php echo ($fdept===$d['department_id']?'selected':''); ?>><?php echo h($d['department_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mr-2">
          <label for="fcourse" class="mr-2">Course</label>
          <select id="fcourse" name="course_id" class="form-control">
            <option value="">-- Any --</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?php echo h($c['course_id']); ?>" data-dept="<?php echo h($c['department_id']); ?>" <?php echo ($fcourse===$c['course_id']?'selected':''); ?>><?php echo h($c['course_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mr-2">
          <label for="fgender" class="mr-2">Gender</label>
          <select id="fgender" name="gender" class="form-control">
            <option value="">-- Any --</option>
            <?php foreach (["Male","Female","Other"] as $g): ?>
              <option value="<?php echo h($g); ?>" <?php echo ($fgender===$g?'selected':''); ?>><?php echo h($g); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mr-2">
          <label for="fstatus" class="mr-2">Status</label>
          <select id="fstatus" name="status" class="form-control" <?php echo $is_dir ? 'disabled' : ''; ?>>
            <option value="">-- Any --</option>
            <?php foreach (["Active","Inactive","Following","Completed","Suspended"] as $st): ?>
              <?php if (!$is_dir || $st === 'Active'): ?>
                <option value="<?php echo h($st); ?>" <?php echo ($fstatus===$st?'selected':''); ?>><?php echo h($st); ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <?php if ($is_dir): ?>
            <input type="hidden" name="status" value="Active">
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
      </form>
      <script>
        // Client-side filter: limit course options by selected department
        (function(){
          var dept = document.getElementById('fdept');
          var course = document.getElementById('fcourse');
          if (!dept || !course) return;
          var all = Array.prototype.slice.call(course.options).map(function(o){ return {value:o.value, text:o.text, dept:o.getAttribute('data-dept')}; });
          function apply(){
            var d = dept.value;
            var keepSelected = course.value;
            // Rebuild
            while (course.options.length) course.remove(0);
            var opt = document.createElement('option'); opt.value=''; opt.text='-- Any --'; course.add(opt);
            all.forEach(function(it){
              if (!it.value) return; // skip placeholder
              if (!d || it.dept === d){ var o = document.createElement('option'); o.value=it.value; o.text=it.text; course.add(o); }
            });
            // Try to restore selection if still valid
            if (keepSelected) {
              for (var i=0;i<course.options.length;i++){ if (course.options[i].value===keepSelected){ course.selectedIndex=i; break; } }
            }
          }
          dept.addEventListener('change', apply);
          // Initialize on load
          apply();
        })();
      </script>

      <form method="post" <?php echo $is_admin ? "onsubmit=\"return confirm('Inactivate selected students?');\"" : 'onsubmit="return false;"'; ?>>
        <?php if ($is_admin): ?>
        <div class="mb-2">
          <button type="submit" name="bulk_action" value="bulk_inactivate" class="btn btn-danger btn-sm">Bulk Inactivate</button>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table table-striped table-bordered">
            <thead>
              <tr>
                <?php if ($is_admin): ?>
                  <th><input type="checkbox" onclick="var c=this.checked; document.querySelectorAll('.sel').forEach(function(cb){cb.checked=c;});"></th>
                <?php endif; ?>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($res && mysqli_num_rows($res) > 0): $i=0; while ($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                  <?php if ($is_admin): ?>
                    <td><input type="checkbox" class="sel" name="sids[]" value="<?php echo h($row['student_id']); ?>"></td>
                  <?php endif; ?>
                  <td><?php echo h($row['student_id']); ?></td>
                  <td><?php echo h($row['student_fullname']); ?></td>
                  <td><?php echo h($row['student_email']); ?></td>
                  <td><?php echo h($row['student_phone']); ?></td>
                  <td><?php echo h($row['student_status']); ?></td>
                  <td>
                    <?php 
                      $viewUrl = $base.'/student/Student_profile.php?Sid='.urlencode($row['student_id']);
                      $editUrl = $base.'/student/StudentEditAdmin.php?Sid='.urlencode($row['student_id']);
                    ?>
                    <?php if ($is_admin): ?>
                      <a class="btn btn-sm btn-success" title="Edit" href="<?php echo $editUrl; ?>"><i class="far fa-edit"></i></a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-info" title="View" href="<?php echo $viewUrl; ?>"><i class="fas fa-angle-double-right"></i></a>
                    <?php if ($is_admin): ?>
                      <button type="submit" name="delete_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Inactivate <?php echo h($row['student_id']); ?>?');"><i class="far fa-trash-alt"></i></button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="<?php echo $is_admin ? 7 : 6; ?>" class="text-center">No students found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
