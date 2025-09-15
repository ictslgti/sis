<?php
$title = "Group Sessions | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

$allowedRoles = ['HOD','IN1','IN2','LE1','LE2','ADM'];
if (!in_array($role, $allowedRoles)) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

// Access: HOD can view any; others must be assigned
if (!in_array($role, ['HOD'])) {
  $st = mysqli_prepare($con, 'SELECT 1 FROM group_staff WHERE group_id=? AND staff_id=? AND active=1 LIMIT 1');
  if ($st) { mysqli_stmt_bind_param($st,'is',$group_id,$uid); mysqli_stmt_execute($st); $rs = mysqli_stmt_get_result($st); $ok = ($rs && mysqli_fetch_row($rs)); mysqli_stmt_close($st); if(!$ok){ echo '<div class="container mt-4"><div class="alert alert-danger">Not assigned to this group</div></div>'; require_once __DIR__.'/../footer.php'; exit; } }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Group info
$grp = null; $stg = mysqli_prepare($con,'SELECT * FROM `groups` WHERE id=?'); if($stg){ mysqli_stmt_bind_param($stg,'i',$group_id); mysqli_stmt_execute($stg); $rg=mysqli_stmt_get_result($stg); $grp=$rg?mysqli_fetch_assoc($rg):null; mysqli_stmt_close($stg);} if(!$grp){ echo '<div class="container mt-4"><div class="alert alert-warning">Group not found</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

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

// Create session form (all allowed roles)
?>
<div class="container mt-4">
  <h3>Sessions — <?php echo h($grp['name']); ?> (<?php echo h($grp['course_id']); ?>, <?php echo h($grp['academic_year']); ?>)</h3>
  <div class="card mb-3">
    <div class="card-header">Create Session</div>
    <div class="card-body">
      <form method="POST" action="<?php echo $base; ?>/controller/GroupSessionCreate.php">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Date</label>
            <input type="date" name="session_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group col-md-2">
            <label>Start</label>
            <input type="time" name="start_time" class="form-control">
          </div>
          <div class="form-group col-md-2">
            <label>End</label>
            <input type="time" name="end_time" class="form-control">
          </div>
          <div class="form-group col-md-5">
            <label>Coverage Title</label>
            <input type="text" name="coverage_title" class="form-control" placeholder="e.g., Arrays and Loops" required>
          </div>
        </div>
        <div class="form-group">
          <label>Coverage Notes</label>
          <textarea name="coverage_notes" class="form-control" rows="2" placeholder="Short notes"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Create</button>
        <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">Back</a>
      </form>
    </div>
  </div>

  <?php
  // List sessions
  $rows = [];
  $qs = mysqli_prepare($con,'SELECT * FROM group_sessions WHERE group_id=? ORDER BY session_date DESC, id DESC');
  if ($qs) { mysqli_stmt_bind_param($qs,'i',$group_id); mysqli_stmt_execute($qs); $res = mysqli_stmt_get_result($qs); while($res && ($r=mysqli_fetch_assoc($res))){ $rows[]=$r; } mysqli_stmt_close($qs);} 
  ?>
  <div class="card">
    <div class="card-header">Sessions</div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>Date</th><th>Time</th><th>Coverage</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo h($r['session_date']); ?></td>
                <td><?php echo h(($r['start_time']?:'') . (($r['end_time'])?(' - '.$r['end_time']):'')); ?></td>
                <td><b><?php echo h($r['coverage_title']); ?></b><br><small class="text-muted"><?php echo h($r['coverage_notes']); ?></small></td>
                <td>
                  <a class="btn btn-sm btn-info" href="<?php echo $base; ?>/group/MarkGroupAttendance.php?session_id=<?php echo (int)$r['id']; ?>">Mark Attendance</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
              <tr><td colspan="4" class="text-center text-muted">No sessions yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
