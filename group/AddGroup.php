<?php
$title = "Add/Edit Group | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if (!in_array($role, ['HOD'])) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }
$_isADM = ($role === 'ADM');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$name = $course_id = $academic_year = $status = '';
// If adding a new group, allow preselect course from query
if ($id === 0 && isset($_GET['course_id'])) {
  $course_id = trim((string)$_GET['course_id']);
}
if ($id > 0) {
  $st = mysqli_prepare($con, 'SELECT * FROM `groups` WHERE id=?');
  if ($st) { mysqli_stmt_bind_param($st,'i',$id); mysqli_stmt_execute($st); $rs = mysqli_stmt_get_result($st); $row = $rs?mysqli_fetch_assoc($rs):null; mysqli_stmt_close($st); if ($row){ $name=$row['name']; $course_id=$row['course_id']; $academic_year=$row['academic_year']; $status=$row['status']; }}
  // HODs: ensure the group's course belongs to their department
  $deptId = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : 0;
  if ($role === 'HOD' && $deptId > 0 && $course_id !== '') {
    $chk = mysqli_prepare($con, 'SELECT 1 FROM course WHERE course_id=? AND department_id=?');
    if ($chk) { mysqli_stmt_bind_param($chk,'si',$course_id,$deptId); mysqli_stmt_execute($chk); $rs2 = mysqli_stmt_get_result($chk); $ok = ($rs2 && mysqli_num_rows($rs2)>0); mysqli_stmt_close($chk); if(!$ok){ echo '<div class="container mt-4"><div class="alert alert-danger">Access denied for this group</div></div>'; require_once __DIR__.'/../footer.php'; exit; } }
  }
}
?>
<div class="container mt-4<?php echo $_isADM ? '' : ' hod-desktop-offset'; ?>">
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
            $deptId = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : 0;
            $isHOD = ($role === 'HOD' && $deptId > 0);
            if ($isHOD) {
              $stc = mysqli_prepare($con, 'SELECT course_id, course_name FROM course WHERE department_id = ? ORDER BY course_name');
              if ($stc) {
                mysqli_stmt_bind_param($stc, 'i', $deptId);
                mysqli_stmt_execute($stc);
                $rc = mysqli_stmt_get_result($stc);
                while ($rc && ($r = mysqli_fetch_assoc($rc))) {
                  $sel = ($course_id === $r['course_id']) ? ' selected' : '';
                  echo '<option value="'.htmlspecialchars($r['course_id']).'"'.$sel.'>'.htmlspecialchars($r['course_name']).' ('.htmlspecialchars($r['course_id']).')'.'</option>';
                }
                mysqli_stmt_close($stc);
              }
            } else {
              $q = mysqli_query($con, 'SELECT course_id, course_name FROM course ORDER BY course_name');
              while ($q && ($r = mysqli_fetch_assoc($q))) {
                $sel = ($course_id === $r['course_id']) ? ' selected' : '';
                echo '<option value="'.htmlspecialchars($r['course_id']).'"'.$sel.'>'.htmlspecialchars($r['course_name']).' ('.htmlspecialchars($r['course_id']).')'.'</option>';
              }
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
