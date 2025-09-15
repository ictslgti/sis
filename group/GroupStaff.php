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

// Enforce department ownership for HODs
if ($role === 'HOD') {
  $deptId = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : 0;
  if ($deptId > 0) {
    $chk = mysqli_prepare($con, 'SELECT c.department_id FROM groups g LEFT JOIN course c ON c.course_id=g.course_id WHERE g.id=?');
    if ($chk) {
      mysqli_stmt_bind_param($chk, 'i', $group_id);
      mysqli_stmt_execute($chk);
      $rs = mysqli_stmt_get_result($chk);
      $row = $rs ? mysqli_fetch_assoc($rs) : null;
      mysqli_stmt_close($chk);
      if (!$row || (int)($row['department_id'] ?? 0) !== $deptId) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Access denied for this group</div></div>';
        require_once __DIR__.'/../footer.php';
        exit;
      }
    }
  }
}

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

// Staff candidates (working staff across ALL departments; include department name for UI)
$candidates = [];
$sqlCand = "SELECT s.staff_id, s.staff_name, d.department_name FROM staff s LEFT JOIN department d ON d.department_id=s.department_id WHERE s.staff_status='Working' ORDER BY d.department_name, s.staff_name";
$qc = mysqli_query($con, $sqlCand);
while ($qc && ($r = mysqli_fetch_assoc($qc))) { $candidates[] = $r; }

// Modules of this group's course (only if course_id is known)
$modules = [];
$showModuleUI = !empty($grp['course_id']);
if ($showModuleUI) {
  // Load modules for the group's course only
  $qm = mysqli_prepare($con, 'SELECT module_id, module_name, semester_id FROM module WHERE course_id=? ORDER BY module_id');
  if ($qm) { mysqli_stmt_bind_param($qm,'s',$grp['course_id']); mysqli_stmt_execute($qm); $rm = mysqli_stmt_get_result($qm); while($rm && ($r=mysqli_fetch_assoc($rm))){ $modules[]=$r; } mysqli_stmt_close($qm); }
}
?>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div>
      <h4 class="mb-0">Manage Staff — <?php echo h($grp['name']); ?></h4>
      <small class="text-muted">
        <?php if (!empty($grp['course_id'])): ?>Course: <strong><?php echo h($grp['course_id']); ?></strong> · Year: <strong><?php echo h($grp['academic_year']); ?></strong><?php else: ?>Course/year not available<?php endif; ?>
      </small>
    </div>
<script>
  function slgtiValidateAssign(form){
    try{
      var hasModuleUI = <?php echo $showModuleUI? 'true':'false'; ?>;
      if(!hasModuleUI){ return false; }
      var mod = form.querySelector('[name="module_id"]');
      var del = form.querySelector('[name="delivery_type"]');
      if(!mod || !del){ return true; }
      var mv = (mod.tagName === 'SELECT') ? mod.value : (mod.getAttribute('value')||'');
      var dv = (del.tagName === 'SELECT') ? del.value : (del.getAttribute('value')||'');
      if(!mv || !dv){
        alert('Please select a module and delivery type.');
        return false;
      }
    }catch(e){}
    return true;
  }
</script>
    <div>
      <?php
        // Group size info from group_students
        $gsize = 0; $qs = mysqli_prepare($con,'SELECT COUNT(*) c FROM group_students WHERE group_id=? AND status="active"'); if ($qs){ mysqli_stmt_bind_param($qs,'i',$group_id); mysqli_stmt_execute($qs); $rs=mysqli_stmt_get_result($qs); $row=$rs?mysqli_fetch_assoc($rs):null; $gsize=(int)($row['c']??0); mysqli_stmt_close($qs);} 
      ?>
      <span class="badge badge-secondary">Students: <?php echo (int)$gsize; ?></span>
    </div>
  </div>
  <?php if (isset($_GET['ok'])): ?><div class="alert alert-success py-2 mb-3">Saved successfully.</div><?php endif; ?>
  <?php if (isset($_GET['err'])): ?>
    <?php if ($_GET['err']==='module_table'): ?>
      <div class="alert alert-warning py-2 mb-3">Module-wise saving is not enabled yet. Please create the <code>group_staff_module</code> table to save module assignments.</div>
    <?php elseif ($_GET['err']==='module'): ?>
      <div class="alert alert-danger py-2 mb-3">Please select a module and delivery type.</div>
    <?php elseif ($_GET['err']==='module_course'): ?>
      <div class="alert alert-danger py-2 mb-3">Selected module does not belong to this group's course. Load is limited to this course only.</div>
    <?php else: ?>
      <div class="alert alert-danger py-2 mb-3">Action failed. Code: <?php echo h($_GET['err']); ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card mb-3 center-card-form">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <strong>Assign Staff</strong> <small class="text-muted"><?php echo $hasGsm? '(Module-wise)':''; ?></small>
      </div>
      <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-sm btn-outline-secondary">Back to Groups</a>
    </div>
    <div class="card-body">
      <?php if (!$showModuleUI): ?>
        <div class="alert alert-warning mb-3">
          This group does not have a course assigned yet. Please set the group's course (or enroll a student with a course) to enable module-wise assignment.
        </div>
      <?php endif; ?>
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" onsubmit="return slgtiValidateAssign(this);">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <div class="form-row">
          <?php if ($showModuleUI): ?>
          <div class="form-group col-md-4">
            <label class="mb-1">Module</label>
            <select name="module_id" class="selectpicker" data-live-search="true" data-width="100%" data-size="6" title="Select module…" required>
              <?php foreach ($modules as $m): ?>
                <option value="<?php echo h($m['module_id']); ?>" data-subtext="<?php echo h($m['semester_id']?:''); ?>">
                  <?php echo h($m['module_id']).' — '.h($m['module_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Search by code or name; subtext shows semester</small>
            <?php if (!$hasGsm): ?>
              <small class="form-text text-warning">Note: Saving requires the <code>group_staff_module</code> table.</small>
            <?php endif; ?>
          </div>
          <div class="form-group col-md-3">
            <label class="mb-1">Delivery</label>
            <select name="delivery_type" class="custom-select" required>
              <option value="">Select delivery…</option>
              <option value="THEORY">THEORY</option>
              <option value="PRACTICAL">PRACTICAL</option>
              <option value="BOTH">BOTH</option>
            </select>
            <small class="form-text text-muted">Choose teaching type</small>
          </div>
          <?php endif; ?>
          <div class="form-group <?php echo $showModuleUI?'col-md-3':'col-md-6'; ?>">
            <label class="mb-1">Select Staff</label>
            <select name="staff_id" class="selectpicker" data-live-search="true" data-width="100%" data-size="6" title="Select staff…" required>
              <?php foreach ($candidates as $c): ?>
                <option value="<?php echo h($c['staff_id']); ?>" data-subtext="<?php echo h($c['department_name'] ?: ''); ?>">
                  <?php echo h($c['staff_name']).' ('.h($c['staff_id']).')'; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group <?php echo $showModuleUI?'col-md-2':'col-md-3'; ?>">
            <label class="mb-1">Role</label>
            <select name="assign_role" class="custom-select" required>
              <option value="">Select role…</option>
              <option value="LECTURER">LECTURER</option>
              <option value="INSTRUCTOR">INSTRUCTOR</option>
            </select>
          </div>
          <div class="form-group <?php echo $showModuleUI?'col-md-2':'col-md-3'; ?> d-flex align-items-end">
            <button type="submit" name="action" value="assign" class="btn btn-primary" <?php echo $showModuleUI? '':'disabled'; ?> title="<?php echo $showModuleUI? '':'Set group course to enable module-wise assignment'; ?>"><i class="fas fa-user-plus mr-1"></i>Assign</button>
            <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-outline-secondary ml-2">Back</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span>Current Assignments</span>
      <?php if ($showModuleUI): ?>
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
      <?php if ($hasGsm): ?>
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small">Manage assignments</div>
        <div>
          <form method="post" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" class="form-inline d-inline" onsubmit="return confirm('Unassign ALL staff from this group?');">
            <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
            <button type="submit" name="action" value="bulk_unassign_all" class="btn btn-sm btn-outline-danger">Unassign All</button>
          </form>
          <form method="post" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" class="form-inline d-inline ml-2" onsubmit="return confirm('Unassign all staff for the selected module?');">
            <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
            <select name="module_id" class="selectpicker" data-live-search="true" data-width="220px" data-size="6" title="Select module" required>
              <?php foreach ($modules as $m): ?>
                <option value="<?php echo h($m['module_id']); ?>" data-subtext="<?php echo h($m['semester_id']?:''); ?>"><?php echo h($m['module_id']).' — '.h($m['module_name']); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="action" value="bulk_unassign_module" class="btn btn-sm btn-outline-warning ml-1">Unassign Module</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead><tr>
            <?php if ($showModuleUI): ?><th style="width:32%">Module</th><th style="width:12%">Delivery</th><?php endif; ?>
            <th style="width:36%">Staff</th><th style="width:12%">Role</th><th style="width:8%" class="text-right">Action</th></tr></thead>
          <tbody>
            <?php foreach ($cur as $r): ?>
              <tr>
                <?php if ($showModuleUI): ?>
                  <?php if ($hasGsm): ?>
                    <td><?php echo h($r['module_id']).($r['module_name']?(' - '.h($r['module_name'])):''); ?></td>
                    <td><?php echo h($r['delivery_type'] ?? 'BOTH'); ?></td>
                  <?php else: ?>
                    <td><span class="text-muted">—</span></td>
                    <td><span class="text-muted">—</span></td>
                  <?php endif; ?>
                <?php endif; ?>
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
                  <?php if ($hasGsm): ?>
                  <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" onsubmit="return confirm('Hard delete this assignment permanently?');" class="d-inline ml-1">
                    <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
                    <input type="hidden" name="module_id" value="<?php echo h($r['module_id']); ?>">
                    <input type="hidden" name="delivery_type" value="<?php echo h($r['delivery_type'] ?? 'BOTH'); ?>">
                    <input type="hidden" name="staff_id" value="<?php echo h($r['staff_id']); ?>">
                    <input type="hidden" name="assign_role" value="<?php echo h($r['role']); ?>">
                    <button type="submit" name="action" value="hard_delete" class="btn btn-sm btn-outline-secondary">Hard Delete</button>
                  </form>
                  <?php endif; ?>
                  <?php if ($hasGsm && $showModuleUI): ?>
                    <?php $rowKey = h($r['staff_id'].'_'.$r['module_id'].'_'.$r['delivery_type'].'_'.$r['role']); ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary ml-1" data-toggle="collapse" data-target="#edit-row-<?php echo $rowKey; ?>">Edit</button>
                    <div id="edit-row-<?php echo $rowKey; ?>" class="collapse mt-2">
                      <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" class="form-inline">
                        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
                        <input type="hidden" name="staff_id" value="<?php echo h($r['staff_id']); ?>">
                        <input type="hidden" name="old_module_id" value="<?php echo h($r['module_id']); ?>">
                        <input type="hidden" name="old_delivery_type" value="<?php echo h($r['delivery_type'] ?? 'BOTH'); ?>">
                        <input type="hidden" name="old_role" value="<?php echo h($r['role']); ?>">
                        <select name="module_id" class="selectpicker" data-live-search="true" data-width="220px" data-size="6" title="Module" required>
                          <?php foreach ($modules as $m): $sel = ($m['module_id']===$r['module_id'])? ' selected' : ''; ?>
                            <option value="<?php echo h($m['module_id']); ?>" data-subtext="<?php echo h($m['semester_id']?:''); ?>"<?php echo $sel; ?>><?php echo h($m['module_id']).' — '.h($m['module_name']); ?></option>
                          <?php endforeach; ?>
                        </select>
                        <select name="delivery_type" class="custom-select custom-select-sm ml-1" style="width:auto; display:inline-block;" required>
                          <option value="THEORY" <?php echo ($r['delivery_type']??'')==='THEORY'?'selected':''; ?>>THEORY</option>
                          <option value="PRACTICAL" <?php echo ($r['delivery_type']??'')==='PRACTICAL'?'selected':''; ?>>PRACTICAL</option>
                          <option value="BOTH" <?php echo ($r['delivery_type']??'')==='BOTH'?'selected':''; ?>>BOTH</option>
                        </select>
                        <select name="assign_role" class="custom-select custom-select-sm ml-1" style="width:auto; display:inline-block;" required>
                          <option value="LECTURER" <?php echo ($r['role']==='LECTURER')?'selected':''; ?>>LECTURER</option>
                          <option value="INSTRUCTOR" <?php echo ($r['role']==='INSTRUCTOR')?'selected':''; ?>>INSTRUCTOR</option>
                        </select>
                        <button type="submit" name="action" value="update" class="btn btn-sm btn-primary ml-1">Save</button>
                      </form>
                    </div>
                  <?php endif; ?>
                  <?php if (!$hasGsm && $showModuleUI): ?>
                    <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" class="d-inline ml-2" title="Map this staff to a module for this group">
                      <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
                      <input type="hidden" name="staff_id" value="<?php echo h($r['staff_id']); ?>">
                      <input type="hidden" name="assign_role" value="<?php echo h($r['role']); ?>">
                      <select name="module_id" class="selectpicker" data-live-search="true" data-width="220px" data-size="6" title="Module" required>
                        <?php foreach ($modules as $m): ?>
                          <option value="<?php echo h($m['module_id']); ?>" data-subtext="<?php echo h($m['semester_id']?:''); ?>"><?php echo h($m['module_id']).' — '.h($m['module_name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <select name="delivery_type" class="custom-select custom-select-sm" style="width:auto; display:inline-block;" required>
                        <option value="THEORY">THEORY</option>
                        <option value="PRACTICAL">PRACTICAL</option>
                        <option value="BOTH" selected>BOTH</option>
                      </select>
                      <button type="submit" name="action" value="map_legacy" class="btn btn-sm btn-primary">Set Module</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($cur)): ?>
              <tr><td colspan="<?php echo $showModuleUI? '5':'4'; ?>" class="text-center text-muted">No staff assigned yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header bg-white"><strong>Students in Group</strong></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover">
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

  <?php if ($showModuleUI): ?>
  <?php
    // Build module-wise teaching map for this group
    $teachMap = [];
    // Initialize with modules list
    foreach ($modules as $m) {
      $teachMap[$m['module_id']] = [
        'meta' => $m,
        'THEORY' => [],
        'PRACTICAL' => [],
        'BOTH' => [],
      ];
    }
    // Load assignments if table exists
    if ($hasGsm) {
      $qmap = mysqli_prepare($con, 'SELECT gsm.module_id, gsm.delivery_type, gsm.role, s.staff_name, s.staff_id FROM group_staff_module gsm INNER JOIN staff s ON s.staff_id=gsm.staff_id WHERE gsm.group_id=? AND gsm.active=1');
      if ($qmap) {
        mysqli_stmt_bind_param($qmap, 'i', $group_id);
        mysqli_stmt_execute($qmap);
        $rsmap = mysqli_stmt_get_result($qmap);
        while ($rsmap && ($r = mysqli_fetch_assoc($rsmap))) {
          $mid = $r['module_id'];
          $dt = in_array($r['delivery_type'], ['THEORY','PRACTICAL','BOTH'], true) ? $r['delivery_type'] : 'BOTH';
          if (isset($teachMap[$mid])) {
            $teachMap[$mid][$dt][] = $r['staff_name'].' ('.$r['staff_id'].')';
          }
        }
        mysqli_stmt_close($qmap);
      }
    }
  ?>
  <div class="card mt-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <strong>Module-wise Teaching Map</strong>
      <small class="text-muted">Batch: <?php echo h($grp['name']); ?><?php if (!empty($grp['course_id'])): ?> · Course: <?php echo h($grp['course_id']); ?><?php endif; ?></small>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover">
          <thead>
            <tr>
              <th>Module</th>
              <th>Semester</th>
              <th>Theory</th>
              <th>Practical</th>
              <th>Both</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teachMap as $mid => $info): $m=$info['meta']; ?>
              <tr>
                <td><?php echo h($mid).' — '.h($m['module_name']); ?></td>
                <td><?php echo h($m['semester_id'] ?? ''); ?></td>
                <td>
                  <?php if (!empty($info['THEORY'])) { echo h(implode(', ', $info['THEORY'])); }
                  else { echo '<span class="badge badge-light">Unassigned</span>'; } ?>
                </td>
                <td>
                  <?php if (!empty($info['PRACTICAL'])) { echo h(implode(', ', $info['PRACTICAL'])); }
                  else { echo '<span class="badge badge-light">Unassigned</span>'; } ?>
                </td>
                <td>
                  <?php if (!empty($info['BOTH'])) { echo h(implode(', ', $info['BOTH'])); }
                  else { echo '<span class="badge badge-light">Unassigned</span>'; } ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$hasGsm): ?>
        <div class="alert alert-warning mt-2 mb-0">Module-wise assignments table (<code>group_staff_module</code>) not found. Display shows modules but cannot reflect assignments until the table is created.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<style>
  .center-card-form .custom-select { cursor: pointer; }
  .center-card-form label { font-weight: 600; }
  .badge-secondary { font-weight: 600; }
  @media (max-width: 575.98px) {
    .center-card-form .form-row > [class^="col-"] { margin-bottom: .75rem; }
  }
</style>
<?php require_once __DIR__ . '/../footer.php'; ?>
