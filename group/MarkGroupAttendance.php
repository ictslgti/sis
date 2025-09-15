<?php
$title = "Mark Group Attendance | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
switch ($role) {
  case 'HOD': case 'IN1': case 'IN2': case 'LE1': case 'LE2': case 'ADM': break;
  default: echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit;
}
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

// Load session + group
$st = mysqli_prepare($con,'SELECT s.*, g.name AS group_name, g.course_id, g.academic_year FROM group_sessions s INNER JOIN groups g ON g.id=s.group_id WHERE s.id=?');
if ($st) { mysqli_stmt_bind_param($st,'i',$session_id); mysqli_stmt_execute($st); $rs=mysqli_stmt_get_result($st); $sess = $rs?mysqli_fetch_assoc($rs):null; mysqli_stmt_close($st);} if(!$sess){ echo '<div class="container mt-4"><div class="alert alert-warning">Session not found</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

// Access control: HOD any; others must be assigned to the group
if ($role !== 'HOD') {
  $st2 = mysqli_prepare($con,'SELECT 1 FROM group_staff WHERE group_id=? AND staff_id=? AND active=1 LIMIT 1');
  if ($st2) { mysqli_stmt_bind_param($st2,'is',$sess['group_id'],$uid); mysqli_stmt_execute($st2); $rs2=mysqli_stmt_get_result($st2); $ok = ($rs2 && mysqli_fetch_row($rs2)); mysqli_stmt_close($st2); if(!$ok){ echo '<div class="container mt-4"><div class="alert alert-danger">Not assigned to this group</div></div>'; require_once __DIR__.'/../footer.php'; exit; } }
}

// HODs: verify department ownership for the group's course
if ($role === 'HOD') {
  $deptId = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : 0;
  if ($deptId > 0) {
    $chk = mysqli_prepare($con, 'SELECT c.department_id FROM groups g LEFT JOIN course c ON c.course_id=g.course_id WHERE g.id=?');
    if ($chk) {
      mysqli_stmt_bind_param($chk, 'i', $sess['group_id']);
      mysqli_stmt_execute($chk);
      $rs2 = mysqli_stmt_get_result($chk);
      $row2 = $rs2 ? mysqli_fetch_assoc($rs2) : null;
      mysqli_stmt_close($chk);
      if (!$row2 || (int)($row2['department_id'] ?? 0) !== $deptId) {
        echo '<div class="container mt-4"><div class="alert alert-danger">Access denied for this group</div></div>';
        require_once __DIR__.'/../footer.php';
        exit;
      }
    }
  }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch group students
$students = [];
$q = mysqli_prepare($con,'SELECT gs.student_id, s.student_fullname FROM group_students gs INNER JOIN student s ON s.student_id=gs.student_id WHERE gs.group_id=? AND gs.status="active" ORDER BY s.student_fullname');
if ($q) { mysqli_stmt_bind_param($q,'i',$sess['group_id']); mysqli_stmt_execute($q); $res = mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $students[]=$r; } mysqli_stmt_close($q);} 
?>
<div class="container mt-4">
  <h3>Mark Attendance — <?php echo h($sess['group_name']); ?> (<?php echo h($sess['course_id']); ?>, <?php echo h($sess['academic_year']); ?>) — <?php echo h($sess['session_date']); ?></h3>
  <div class="alert alert-info">Coverage: <b><?php echo h($sess['coverage_title']); ?></b><br><small class="text-muted"><?php echo nl2br(h($sess['coverage_notes'])); ?></small></div>
  <form method="POST" action="<?php echo $base; ?>/controller/GroupAttendanceSave.php">
    <input type="hidden" name="session_id" value="<?php echo (int)$session_id; ?>">
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Student</th><th>Present</th><th>Absent</th></tr></thead>
        <tbody>
          <?php foreach ($students as $st): $sid = $st['student_id']; ?>
            <tr>
              <td><?php echo h($st['student_fullname']).' ('.h($sid).')'; ?></td>
              <td class="text-center"><input type="radio" name="ATT<?php echo h($sid); ?>" value="Present"></td>
              <td class="text-center"><input type="radio" name="ATT<?php echo h($sid); ?>" value="Absent"></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($students)): ?>
            <tr><td colspan="3" class="text-center text-muted">No students enrolled in this group</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <button type="submit" class="btn btn-primary">Save Attendance</button>
    <a href="<?php echo $base; ?>/group/GroupSessions.php?group_id=<?php echo (int)$sess['group_id']; ?>" class="btn btn-secondary ml-2">Back</a>
  </form>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
