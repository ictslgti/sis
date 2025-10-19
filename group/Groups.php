<?php
$title = "Groups | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$_isADM = ($role === 'ADM');

// Check for redirect parameter from timetable
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';

// Check for flash messages
if (isset($_SESSION['info'])) {
    echo '<div class="container mt-3"><div class="alert alert-info">' . htmlspecialchars($_SESSION['info']) . '</div></div>';
    unset($_SESSION['info']);
}

$canManage = in_array($role, ['HOD','IN1','IN2','IN3']);
$canAccess = $canManage || in_array($role, ['LE1','LE2','ADM']);
if (!$canAccess) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Admin filters: department and course
$filter_department_id = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$filter_course_id = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$filter_academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$departments = [];
$courses = [];
$academic_years = [];
if ($_isADM) {
  // Load departments
  $rs = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
  if ($rs) { while ($row = mysqli_fetch_assoc($rs)) { $departments[] = $row; } }
  // Load courses (optionally filtered by department)
  if ($filter_department_id !== '') {
    $stc = mysqli_prepare($con, "SELECT course_id, course_name, department_id FROM course WHERE department_id = ? ORDER BY course_name");
    if ($stc) { mysqli_stmt_bind_param($stc, 's', $filter_department_id); mysqli_stmt_execute($stc); $rc = mysqli_stmt_get_result($stc); while ($rc && ($r=mysqli_fetch_assoc($rc))) { $courses[]=$r; } mysqli_stmt_close($stc); }
  } else {
    $rc = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name");
    if ($rc) { while ($r = mysqli_fetch_assoc($rc)) { $courses[] = $r; } }
  }

  // Load academic years (optionally filtered by selected department/course)
  $sqlYears = "SELECT DISTINCT g.academic_year FROM `groups` g LEFT JOIN course c ON c.course_id = g.course_id WHERE 1";
  $yParams = [];
  $yTypes = '';
  if ($filter_department_id !== '') { $sqlYears .= " AND c.department_id = ?"; $yParams[] = $filter_department_id; $yTypes .= 's'; }
  if ($filter_course_id !== '') { $sqlYears .= " AND g.course_id = ?"; $yParams[] = $filter_course_id; $yTypes .= 's'; }
  $sqlYears .= " ORDER BY g.academic_year DESC";
  if ($yTypes === '') {
    $ry = mysqli_query($con, $sqlYears);
    if ($ry) { while ($row = mysqli_fetch_assoc($ry)) { $academic_years[] = $row['academic_year']; } }
  } else {
    $sty = mysqli_prepare($con, $sqlYears);
    if ($sty) { mysqli_stmt_bind_param($sty, $yTypes, ...$yParams); mysqli_stmt_execute($sty); $ry = mysqli_stmt_get_result($sty); while ($ry && ($row = mysqli_fetch_assoc($ry))) { $academic_years[] = $row['academic_year']; } mysqli_stmt_close($sty); }
  }
}

// Fetch groups: HOD/IN1/IN2/IN3 see only groups under own department; others see assigned groups
$groups = [];
if ($_isADM) {
  // Admin: view all groups, with optional filters
  $sql = "SELECT g.*, c.course_name, c.department_id FROM `groups` g LEFT JOIN course c ON c.course_id=g.course_id WHERE 1";
  $params = [];
  $types = '';
  if ($filter_department_id !== '') { $sql .= " AND c.department_id = ?"; $params[] = $filter_department_id; $types .= 's'; }
  if ($filter_course_id !== '') { $sql .= " AND g.course_id = ?"; $params[] = $filter_course_id; $types .= 's'; }
  if ($filter_academic_year !== '') { $sql .= " AND g.academic_year = ?"; $params[] = $filter_academic_year; $types .= 's'; }
  $sql .= " ORDER BY g.created_at DESC";
  if ($types === '') {
    $rs = mysqli_query($con, $sql);
    while ($rs && ($r = mysqli_fetch_assoc($rs))) { $groups[] = $r; }
  } else {
    $st = mysqli_prepare($con, $sql);
    if ($st) {
      mysqli_stmt_bind_param($st, $types, ...$params);
      mysqli_stmt_execute($st);
      $rs = mysqli_stmt_get_result($st);
      while ($rs && ($r = mysqli_fetch_assoc($rs))) { $groups[] = $r; }
      mysqli_stmt_close($st);
    }
  }
} elseif ($canManage) {
  // HOD/IN1/IN2/IN3: only groups whose course belongs to their department
  $dep = '';
  if (!empty($_SESSION['department_code'])) {
    $dep = trim((string)$_SESSION['department_code']);
  } elseif (!empty($_SESSION['department_id'])) {
    $dep = trim((string)$_SESSION['department_id']);
  }
  $sql = "SELECT g.*, c.course_name FROM `groups` g LEFT JOIN course c ON c.course_id=g.course_id WHERE c.department_id = ? ORDER BY g.created_at DESC";
  $st = mysqli_prepare($con, $sql);
  if ($st) {
    mysqli_stmt_bind_param($st, 's', $dep);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    while ($rs && ($r = mysqli_fetch_assoc($rs))) { $groups[] = $r; }
    mysqli_stmt_close($st);
  }
} else {
  $uid = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
  $st = mysqli_prepare($con, "SELECT DISTINCT g.* FROM `groups` g INNER JOIN group_staff gs ON gs.group_id=g.id WHERE gs.staff_id=? AND gs.active=1 ORDER BY g.created_at DESC");
  if ($st) { mysqli_stmt_bind_param($st, 's', $uid); mysqli_stmt_execute($st); $rs = mysqli_stmt_get_result($st); while ($rs && ($r=mysqli_fetch_assoc($rs))) { $groups[]=$r; } mysqli_stmt_close($st); }
}
?>
<div class="container mt-4<?php echo $_isADM ? '' : ' hod-desktop-offset'; ?>">
  <?php if (!empty($redirect) && $redirect === 'group_timetable'): ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle"></i> Please select a group to view its timetable.
    </div>
  <?php endif; ?>
  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Groups</h3>
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?php echo $base; ?>/group/AddGroup.php">Add Group</a>
    <?php endif; ?>
  </div>

  <?php if ($_isADM): ?>
  <form method="get" class="card mb-3">
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-4">
          <label for="department_id">Department</label>
          <select class="form-control" id="department_id" name="department_id" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?php echo h($d['department_id']); ?>" <?php echo ($filter_department_id === (string)$d['department_id']) ? 'selected' : ''; ?>><?php echo h($d['department_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-4">
          <label for="course_id">Course</label>
          <select class="form-control" id="course_id" name="course_id">
            <option value="">All Courses</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?php echo h($c['course_id']); ?>" <?php echo ($filter_course_id === (string)$c['course_id']) ? 'selected' : ''; ?>><?php echo h($c['course_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label for="academic_year">Academic Year</label>
          <select class="form-control" id="academic_year" name="academic_year">
            <option value="">All Years</option>
            <?php foreach ($academic_years as $y): ?>
              <option value="<?php echo h($y); ?>" <?php echo ($filter_academic_year === (string)$y) ? 'selected' : ''; ?>><?php echo h($y); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-1 d-flex align-items-end">
          <button type="submit" class="btn btn-secondary mr-2">Apply</button>
          <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-light">Clear</a>
        </div>
      </div>
    </div>
  </form>
  <?php endif; ?>

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
                <div class="btn-group">
                  <?php $studentsUrl = $base . '/group/GroupStudents.php?group_id=' . $g['id'] . (!empty($redirect) ? '&redirect=' . urlencode($redirect) : ''); ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo $studentsUrl; ?>">
                    <i class="fas fa-users"></i> 
                  </a>
                  <?php if ($canManage): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>/group/AddGroup.php?id=<?php echo $g['id']; ?>">
                    <i class="fas fa-edit"></i> 
                  </a>
                  
                  <form method="POST" action="<?php echo $base; ?>/controller/GroupDelete.php" class="d-inline ml-1" onsubmit="return confirm('FORCE DELETE will also remove all student assignments from this group. Are you sure?');">
                    <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                    <input type="hidden" name="force" value="1">
                    <button type="submit" class="btn btn-sm btn-danger" title="Force delete: also removes student assignments">
                    <i class="fas fa-trash"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                  <?php if (!empty($redirect) && $redirect === 'group_timetable'): ?>
                  <a class="btn btn-sm btn-success" href="<?php echo $base; ?>/timetable/GroupTimetable.php?group_id=<?php echo $g['id']; ?>">
                    <i class="fas fa-calendar-alt"></i> 
                  </a>
                  <?php endif; ?>
                </div>
                
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

