<?php
$title = "Add/Edit Group | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if (!in_array($role, ['HOD'])) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$name = $course_id = $academic_year = $status = '';
if ($id > 0) {
  $st = mysqli_prepare($con, 'SELECT * FROM `groups` WHERE id=?');
  if ($st) { mysqli_stmt_bind_param($st,'i',$id); mysqli_stmt_execute($st); $rs = mysqli_stmt_get_result($st); $row = $rs?mysqli_fetch_assoc($rs):null; mysqli_stmt_close($st); if ($row){ $name=$row['name']; $course_id=$row['course_id']; $academic_year=$row['academic_year']; $status=$row['status']; }}
}
?>
<div class="container mt-4">
  <h3><?php echo $id? 'Edit Group':'Add Group'; ?></h3>
  <form method="POST" action="<?php echo $base; ?>/controller/GroupCreate.php">
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <div class="form-row">
      <div class="form-group col-md-5">
        <label>Name</label>
        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
      </div>
      <div class="form-group col-md-3">
        <label>Course</label>
        <select name="course_id" class="form-control" required>
          <option value="">Select</option>
          <?php
            $deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
            $isHOD = ($role === 'HOD' && $deptCode !== '');
            $sql = "SELECT course_id, course_name FROM course";
            if ($isHOD) {
              $sql .= " WHERE department_id='".mysqli_real_escape_string($con, $deptCode)."'";
            }
            $sql .= " ORDER BY course_name";
            $q = mysqli_query($con, $sql);
            while ($q && ($r = mysqli_fetch_assoc($q))) {
              $sel = ($course_id === $r['course_id']) ? ' selected' : '';
              echo '<option value="'.htmlspecialchars($r['course_id']).'"'.$sel.'>'.htmlspecialchars($r['course_name']).' ('.htmlspecialchars($r['course_id']).')'.'</option>';
            }
          ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Academic Year</label>
        <select name="academic_year" class="form-control" required>
          <option value="">Select</option>
          <?php $q=mysqli_query($con,'SELECT DISTINCT academic_year FROM academic ORDER BY academic_year DESC'); while($q && ($r=mysqli_fetch_assoc($q))){ $sel = ($academic_year===$r['academic_year'])?' selected':''; echo '<option value="'.htmlspecialchars($r['academic_year']).'"'.$sel.'>'.htmlspecialchars($r['academic_year']).'</option>'; } ?>
        </select>
      </div>
    </div>
    <?php if ($id): ?>
    <div class="form-group col-md-3 pl-0">
      <label>Status</label>
      <select name="status" class="form-control">
        <option value="active" <?php echo ($status==='active')?'selected':''; ?>>Active</option>
        <option value="inactive" <?php echo ($status==='inactive')?'selected':''; ?>>Inactive</option>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">Back</a>
  </form>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
