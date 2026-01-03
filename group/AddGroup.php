<?php
$title = "Add/Edit Group | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
// Permit HOD, IN1, IN2, and IN3 to add/edit groups
if (!in_array($role, ['HOD','IN1','IN2','IN3'])) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }
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
  // HOD/IN1/IN2/IN3: ensure the group's course belongs to their department
  // Prefer department_code (could be string like 'ICT'), else department_id
  $deptId = '';
  if (!empty($_SESSION['department_code'])) {
    $deptId = trim((string)$_SESSION['department_code']);
  } elseif (!empty($_SESSION['department_id'])) {
    $deptId = trim((string)$_SESSION['department_id']);
  }
  if (in_array($role, ['HOD','IN1','IN2','IN3'], true) && $deptId !== '' && $course_id !== '') {
    $chk = mysqli_prepare($con, 'SELECT 1 FROM course WHERE course_id=? AND department_id=?');
    if ($chk) { mysqli_stmt_bind_param($chk,'ss',$course_id,$deptId); mysqli_stmt_execute($chk); $rs2 = mysqli_stmt_get_result($chk); $ok = ($rs2 && mysqli_num_rows($rs2)>0); mysqli_stmt_close($chk); if(!$ok){ echo '<div class="container mt-4"><div class="alert alert-danger">Access denied for this group</div></div>'; require_once __DIR__.'/../footer.php'; exit; } }
  }
  }
?>
<style>
  /* Add Group Page - Proper Container Alignment */
  .add-group-container {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    padding-left: 15px;
    padding-right: 15px;
    width: 100%;
  }

  .hod-desktop-offset {
    margin-left: auto !important;
    margin-right: auto !important;
  }

  @media (min-width: 992px) {
    .add-group-container {
      padding-left: 20px;
      padding-right: 20px;
    }
  }

  @media (max-width: 991.98px) {
    .add-group-container {
      padding-left: 15px;
      padding-right: 15px;
    }
  }

  @media (max-width: 575.98px) {
    .add-group-container {
      padding-left: 10px;
      padding-right: 10px;
    }
  }

  /* Form styling */
  .add-group-container .form-group {
    margin-bottom: 1.5rem;
  }

  .add-group-container .form-control {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 0.625rem 1rem;
    transition: all 0.3s ease;
  }

  .add-group-container .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  .add-group-container label {
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
  }

  /* Button styling */
  .add-group-container .btn {
    border-radius: 8px;
    padding: 0.625rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .add-group-container .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
  }

  .add-group-container .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
  }

  .add-group-container .btn-secondary {
    background: #6c757d;
    border: none;
  }

  .add-group-container .btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
  }

  /* Card styling */
  .add-group-container .card {
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: none;
  }

  .add-group-container .card-header {
    border-radius: 12px 12px 0 0 !important;
    padding: 1.25rem 1.5rem;
  }
</style>
<div class="container mt-4 add-group-container<?php echo $_isADM ? '' : ' hod-desktop-offset'; ?>">
  <div class="card shadow-sm border-0">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px 12px 0 0;">
      <h3 class="mb-0" style="color: white; font-weight: 700;">
        <i class="fas fa-users mr-2"></i><?php echo $id? 'Edit Group':'Add Group'; ?>
      </h3>
    </div>
    <div class="card-body p-4">
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
            // Prefer department_code if available (can be string like 'ICT'), otherwise department_id
            $deptId = '';
            if (!empty($_SESSION['department_code'])) {
              $deptId = trim((string)$_SESSION['department_code']);
            } elseif (!empty($_SESSION['department_id'])) {
              $deptId = trim((string)$_SESSION['department_id']);
            }
            $isScoped = (in_array($role, ['HOD','IN1','IN2','IN3'], true) && $deptId !== '');
            if ($isScoped) {
              $stc = mysqli_prepare($con, 'SELECT course_id, course_name FROM course WHERE department_id = ? ORDER BY course_name');
              if ($stc) {
                mysqli_stmt_bind_param($stc, 's', $deptId);
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
        <div class="form-group mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-2"></i>Save
          </button>
          <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">
            <i class="fas fa-arrow-left mr-2"></i>Back
          </a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
