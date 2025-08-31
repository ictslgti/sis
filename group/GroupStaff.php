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

// Group info
$grp = null; $stg = mysqli_prepare($con,'SELECT * FROM groups WHERE id=?'); if($stg){ mysqli_stmt_bind_param($stg,'i',$group_id); mysqli_stmt_execute($stg); $rg=mysqli_stmt_get_result($stg); $grp=$rg?mysqli_fetch_assoc($rg):null; mysqli_stmt_close($stg);} if(!$grp){ echo '<div class="container mt-4"><div class="alert alert-warning">Group not found</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

// Current staff
$cur = [];
$q = mysqli_prepare($con,'SELECT gs.id, gs.staff_id, gs.role, s.staff_name FROM group_staff gs INNER JOIN staff s ON s.staff_id=gs.staff_id WHERE gs.group_id=? AND gs.active=1 ORDER BY s.staff_name');
if ($q){ mysqli_stmt_bind_param($q,'i',$group_id); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $cur[]=$r; } mysqli_stmt_close($q);} 

// Staff candidates (working staff only)
$candidates = [];
$qc = mysqli_query($con, "SELECT staff_id, staff_name FROM staff WHERE staff_status='Working' ORDER BY staff_name");
while ($qc && ($r = mysqli_fetch_assoc($qc))) { $candidates[] = $r; }
?>
<div class="container mt-4">
  <h3>Manage Staff — <?php echo h($grp['name']); ?> (<?php echo h($grp['course_id']); ?>, <?php echo h($grp['academic_year']); ?>)</h3>

  <div class="card mb-3">
    <div class="card-header">Assign Staff</div>
    <div class="card-body">
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Select Staff</label>
            <select name="staff_id" class="form-control" required>
              <option value="">Select</option>
              <?php foreach ($candidates as $c): ?>
                <option value="<?php echo h($c['staff_id']); ?>"><?php echo h($c['staff_name']).' ('.h($c['staff_id']).')'; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Role</label>
            <select name="assign_role" class="form-control" required>
              <option value="">Select</option>
              <option value="LECTURER">LECTURER</option>
              <option value="INSTRUCTOR">INSTRUCTOR</option>
            </select>
          </div>
          <div class="form-group col-md-3 d-flex align-items-end">
            <button type="submit" name="action" value="assign" class="btn btn-primary">Assign</button>
            <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">Back</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Current Assignments</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>Staff</th><th>Role</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($cur as $r): ?>
              <tr>
                <td><?php echo h($r['staff_name']).' ('.h($r['staff_id']).')'; ?></td>
                <td><?php echo h($r['role']); ?></td>
                <td>
                  <form method="POST" action="<?php echo $base; ?>/controller/GroupStaffUpdate.php" onsubmit="return confirm('Unassign this staff from the group?');" class="d-inline">
                    <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
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
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
