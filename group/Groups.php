<?php
$title = "Groups | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$_isADM = ($role === 'ADM');

$canManage = in_array($role, ['HOD']);
$canAccess = $canManage || in_array($role, ['IN1','IN2','LE1','LE2','ADM']);
if (!$canAccess) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch groups: HOD sees all; others see assigned groups
$groups = [];
if ($canManage) {
  // HOD: only groups whose course belongs to HOD's department
  $dep = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
  $sql = "SELECT g.*, c.course_name FROM `groups` g LEFT JOIN course c ON c.course_id=g.course_id WHERE (? = '' OR c.department_code = ?) ORDER BY g.created_at DESC";
  $st = mysqli_prepare($con, $sql);
  if ($st) {
    mysqli_stmt_bind_param($st, 'ss', $dep, $dep);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($r = mysqli_fetch_assoc($rs))) { $groups[] = $r; }
    mysqli_stmt_close($st);
    // Fallback: if no groups found under department filter, try unfiltered to ensure visibility
    if (empty($groups) && $dep !== '') {
      $sql2 = "SELECT g.*, c.course_name FROM `groups` g LEFT JOIN course c ON c.course_id=g.course_id ORDER BY g.created_at DESC";
      $q2 = mysqli_query($con, $sql2);
      while ($q2 && ($r2 = mysqli_fetch_assoc($q2))) { $groups[] = $r2; }
    }
  } else {
    // Prepare failed: show unfiltered as a safe fallback
    $sql2 = "SELECT g.*, c.course_name FROM `groups` g LEFT JOIN course c ON c.course_id=g.course_id ORDER BY g.created_at DESC";
    $q2 = mysqli_query($con, $sql2);
    while ($q2 && ($r2 = mysqli_fetch_assoc($q2))) { $groups[] = $r2; }
  }
} else {
  $uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
  $st = mysqli_prepare($con, "SELECT DISTINCT g.* FROM `groups` g INNER JOIN group_staff gs ON gs.group_id=g.id WHERE gs.staff_id=? AND gs.active=1 ORDER BY g.created_at DESC");
  if ($st) { mysqli_stmt_bind_param($st, 's', $uid); mysqli_stmt_execute($st); $rs = mysqli_stmt_get_result($st); while ($rs && ($r=mysqli_fetch_assoc($rs))) { $groups[]=$r; } mysqli_stmt_close($st); }
}
?>
<div class="container mt-4<?php echo $_isADM ? '' : ' hod-desktop-offset'; ?>">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Groups</h3>
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?php echo $base; ?>/group/AddGroup.php">Add Group</a>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Course</th>
              <th>Academic Year</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td><?php echo (int)$g['id']; ?></td>
              <td><?php echo h($g['name']); ?></td>
              <td><?php echo h(($g['course_name'] ?? '')); ?> (<?php echo h($g['course_id']); ?>)</td>
              <td><?php echo h($g['academic_year']); ?></td>
              <td><?php echo h($g['status']); ?></td>
              <td>
                <?php if ($canManage): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>/group/AddGroup.php?id=<?php echo (int)$g['id']; ?>">Edit</a>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>/group/GroupStudents.php?group_id=<?php echo (int)$g['id']; ?>">Students</a>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>/group/GroupStaff.php?group_id=<?php echo (int)$g['id']; ?>">Staff</a>
                <?php endif; ?>
                <a class="btn btn-sm btn-info" href="<?php echo $base; ?>/group/GroupSessions.php?group_id=<?php echo (int)$g['id']; ?>">Sessions</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($groups)): ?>
            <tr><td colspan="6" class="text-center text-muted">No groups found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
