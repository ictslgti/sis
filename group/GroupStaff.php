<?php
$title = "Group Staff | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if ($role !== 'HOD') { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if ($group_id<=0) { echo '<div class="container mt-4"><div class="alert alert-warning">Invalid group</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Group info (fallback to group_students presence)
$grp = null; $stg = mysqli_prepare($con,'SELECT * FROM groups WHERE id=?'); if($stg){ mysqli_stmt_bind_param($stg,'i',$group_id); mysqli_stmt_execute($stg); $rg=mysqli_stmt_get_result($stg); $grp=$rg?mysqli_fetch_assoc($rg):null; mysqli_stmt_close($stg);} 
if(!$grp){
  // If group table has no row, but group_students has entries, allow limited view
  $hasStudents = false;
  $chk = mysqli_prepare($con,'SELECT COUNT(*) c FROM group_students WHERE group_id=?');
  if ($chk){ mysqli_stmt_bind_param($chk,'i',$group_id); mysqli_stmt_execute($chk); $rs=mysqli_stmt_get_result($chk); $row=$rs?mysqli_fetch_assoc($rs):null; $hasStudents = ((int)($row['c']??0))>0; mysqli_stmt_close($chk);} 
  if ($hasStudents){
    // Try to infer course_id and academic_year from one enrolled student
    $inf = ['course_id'=>'', 'academic_year'=>''];
    $qi = mysqli_prepare($con, 'SELECT se.course_id, se.academic_year FROM student_enroll se WHERE se.student_id = (
                                 SELECT gs2.student_id FROM group_students gs2 WHERE gs2.group_id=? AND gs2.status="active" ORDER BY gs2.id ASC LIMIT 1)
                               ');
    if ($qi) { mysqli_stmt_bind_param($qi,'i',$group_id); mysqli_stmt_execute($qi); $ri = mysqli_stmt_get_result($qi); $rowi = $ri?mysqli_fetch_assoc($ri):null; mysqli_stmt_close($qi); if ($rowi){ $inf['course_id']=$rowi['course_id']??''; $inf['academic_year']=$rowi['academic_year']??''; } }
    $grp = [ 'id'=>$group_id, 'name'=>'Group #'.$group_id, 'course_id'=>$inf['course_id'], 'academic_year'=>$inf['academic_year'] ];
  } else {
    echo '<div class="container mt-4"><div class="alert alert-warning">Group not found. <a href="'.($base.'/group/AddGroup.php').'" class="alert-link">Create a group</a></div></div>'; require_once __DIR__.'/../footer.php'; exit;
  }
}

// Detect module-wise table
$hasGsm = false;
$ck = mysqli_query($con, "SHOW TABLES LIKE 'group_staff_module'");
if ($ck && mysqli_num_rows($ck) > 0) { $hasGsm = true; }
if ($ck) mysqli_free_result($ck);

// Current staff assignments (with optional module filter)
$cur = [];
$filterModule = isset($_GET['module']) ? trim((string)$_GET['module']) : '';
if ($hasGsm) {
  if ($filterModule !== '') {
    $q = mysqli_prepare($con,'SELECT gsm.id, gsm.staff_id, gsm.role, gsm.module_id, gsm.delivery_type, s.staff_name, m.module_name FROM group_staff_module gsm INNER JOIN staff s ON s.staff_id=gsm.staff_id LEFT JOIN module m ON m.module_id=gsm.module_id AND m.course_id=(SELECT course_id FROM groups WHERE id=?) WHERE gsm.group_id=? AND gsm.module_id=? AND gsm.active=1 ORDER BY m.module_id, s.staff_name');
    if ($q){ mysqli_stmt_bind_param($q,'iis',$group_id,$group_id,$filterModule); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $cur[]=$r; } mysqli_stmt_close($q);} 
  } else {
    $q = mysqli_prepare($con,'SELECT gsm.id, gsm.staff_id, gsm.role, gsm.module_id, gsm.delivery_type, s.staff_name, m.module_name FROM group_staff_module gsm INNER JOIN staff s ON s.staff_id=gsm.staff_id LEFT JOIN module m ON m.module_id=gsm.module_id AND m.course_id=(SELECT course_id FROM groups WHERE id=?) WHERE gsm.group_id=? AND gsm.active=1 ORDER BY m.module_id, s.staff_name');
    if ($q){ mysqli_stmt_bind_param($q,'ii',$group_id,$group_id); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $cur[]=$r; } mysqli_stmt_close($q);} 
  }
} else {
  $q = mysqli_prepare($con,'SELECT gs.id, gs.staff_id, gs.role, s.staff_name FROM group_staff gs INNER JOIN staff s ON s.staff_id=gs.staff_id WHERE gs.group_id=? AND gs.active=1 ORDER BY s.staff_name');
  if ($q){ mysqli_stmt_bind_param($q,'i',$group_id); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $cur[]=$r; } mysqli_stmt_close($q);} 
}

// Staff candidates (working staff only, HOD department scoped if applicable)
$candidates = [];
$hodDept = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';
$sqlCand = "SELECT staff_id, staff_name FROM staff WHERE staff_status='Working'";
if ($role === 'HOD' && $hodDept !== '') {
  $sqlCand .= " AND department_id='".mysqli_real_escape_string($con, $hodDept)."'";
}
$sqlCand .= " ORDER BY staff_name";
$qc = mysqli_query($con, $sqlCand);
while ($qc && ($r = mysqli_fetch_assoc($qc))) { $candidates[] = $r; }

// Modules of this group's course (only if course_id is known)
$modules = [];
if (!empty($grp['course_id'])) {
  $qm = mysqli_prepare($con, 'SELECT module_id, module_name FROM module WHERE course_id=? ORDER BY module_id');
  if ($qm) { mysqli_stmt_bind_param($qm,'s',$grp['course_id']); mysqli_stmt_execute($qm); $rm = mysqli_stmt_get_result($qm); while($rm && ($r=mysqli_fetch_assoc($rm))){ $modules[]=$r; } mysqli_stmt_close($qm); }
} else {
  // If no course_id, force disable module-wise UI
  $hasGsm = false;
}
?>
<div class="container mt-4">
  <h3>Manage Staff — <?php echo h($grp['name']); ?><?php if (!empty($grp['course_id'])): ?> (<?php echo h($grp['course_id']); ?>, <?php echo h($grp['academic_year']); ?>)<?php endif; ?></h3>
  <?php
    // Group size info from group_students
    $gsize = 0; $qs = mysqli_prepare($con,'SELECT COUNT(*) c FROM group_students WHERE group_id=? AND status="active"'); if ($qs){ mysqli_stmt_bind_param($qs,'i',$group_id); mysqli_stmt_execute($qs); $rs=mysqli_stmt_get_result($qs); $row=$rs?mysqli_fetch_assoc($rs):null; $gsize=(int)($row['c']??0); mysqli_stmt_close($qs);} 
  ?>
  <p class="text-muted">Students: <?php echo (int)$gsize; ?><?php if (empty($grp['course_id'])): ?> — course/year inferred from student_enroll is unavailable. You can still assign staff without module mapping.<?php endif; ?></p>
  <?php if (isset($_GET['ok'])): ?><div class="alert alert-success">Saved successfully.</div><?php endif; ?>
  <?php if (isset($_GET['err'])): ?><div class="alert alert-danger">Action failed. Code: <?php echo h($_GET['err']); ?></div><?php endif; ?>

  <div class="card mb-3 center-card-form">
    <div class="card-header">Assign Staff<?php echo $hasGsm? ' (Module-wise)':''; ?></div>
    <div class="card-body">
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <div class="form-row">
          <?php if ($hasGsm): ?>
          <div class="form-group col-md-4">
            <label>Module</label>
            <select name="module_id" class="form-control" required>
              <option value="">Select</option>
              <?php foreach ($modules as $m): ?>
                <option value="<?php echo h($m['module_id']); ?>"><?php echo h($m['module_id']).' - '.h($m['module_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Delivery</label>
            <select name="delivery_type" class="form-control" required>
              <option value="">Select</option>
              <option value="THEORY">THEORY</option>
              <option value="PRACTICAL">PRACTICAL</option>
              <option value="BOTH">BOTH</option>
            </select>
          </div>
          <?php endif; ?>
          <div class="form-group <?php echo $hasGsm?'col-md-3':'col-md-6'; ?>">
            <label>Select Staff</label>
            <select name="staff_id" class="form-control" required>
              <option value="">Select</option>
              <?php foreach ($candidates as $c): ?>
                <option value="<?php echo h($c['staff_id']); ?>"><?php echo h($c['staff_name']).' ('.h($c['staff_id']).')'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group <?php echo $hasGsm?'col-md-2':'col-md-3'; ?>">
            <label>Role</label>
            <select name="assign_role" class="form-control" required>
              <option value="">Select</option>
              <option value="LECTURER">LECTURER</option>
              <option value="INSTRUCTOR">INSTRUCTOR</option>
            </select>
          </div>
          <div class="form-group <?php echo $hasGsm?'col-md-2':'col-md-3'; ?> d-flex align-items-end">
            <button type="submit" name="action" value="assign" class="btn btn-primary">Assign</button>
            <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">Back</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Current Assignments</span>
      <?php if ($hasGsm): ?>
      <form method="get" class="form-inline m-0 p-0">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <label class="mr-2 mb-0">Module</label>
        <select name="module" class="form-control form-control-sm" onchange="this.form.submit()">
          <option value="">All</option>
          <?php foreach ($modules as $m): $sel = ($filterModule===$m['module_id'])?' selected':''; ?>
            <option value="<?php echo h($m['module_id']); ?>"<?php echo $sel; ?>><?php echo h($m['module_id']).' - '.h($m['module_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr>
            <?php if ($hasGsm): ?><th>Module</th><th>Delivery</th><?php endif; ?>
            <th>Staff</th><th>Role</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($cur as $r): ?>
              <tr>
                <?php if ($hasGsm): ?><td><?php echo h($r['module_id']).($r['module_name']?(' - '.h($r['module_name'])):''); ?></td><td><?php echo h($r['delivery_type'] ?? 'BOTH'); ?></td><?php endif; ?>
                <td><?php echo h($r['staff_name']).' ('.h($r['staff_id']).')'; ?></td>
                <td><?php echo h($r['role']); ?></td>
                <td>
                  <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" onsubmit="return confirm('Unassign this staff from the group?');" class="d-inline">
                    <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
                    <?php if ($hasGsm): ?><input type="hidden" name="module_id" value="<?php echo h($r['module_id']); ?>"><input type="hidden" name="delivery_type" value="<?php echo h($r['delivery_type'] ?? 'BOTH'); ?>"><?php endif; ?>
                    <input type="hidden" name="staff_id" value="<?php echo h($r['staff_id']); ?>">
                    <input type="hidden" name="assign_role" value="<?php echo h($r['role']); ?>">
                    <button type="submit" name="action" value="unassign" class="btn btn-sm btn-outline-danger">Unassign</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($cur)): ?>
              <tr><td colspan="3" class="text-center text-muted">No staff assigned yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header">Students in Group</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead><tr><th>Student ID</th><th>Name</th></tr></thead>
          <tbody>
            <?php
            $stu = [];
            $qs = mysqli_prepare($con,'SELECT gs.student_id, s.student_fullname FROM group_students gs LEFT JOIN student s ON s.student_id=gs.student_id WHERE gs.group_id=? AND gs.status="active" ORDER BY s.student_fullname');
            if ($qs){ mysqli_stmt_bind_param($qs,'i',$group_id); mysqli_stmt_execute($qs); $res=mysqli_stmt_get_result($qs); while($res && ($r=mysqli_fetch_assoc($res))){ $stu[]=$r; } mysqli_stmt_close($qs);} 
            if ($stu) { foreach ($stu as $r) { echo '<tr><td>'.h($r['student_id']).'</td><td>'.h($r['student_fullname']).'</td></tr>'; } } else { echo '<tr><td colspan="2" class="text-center text-muted">No students found</td></tr>'; }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
