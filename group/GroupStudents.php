<?php
$title = "Group Students | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
$redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';

// Allow HOD/IN1/IN2/IN3 to access; others forbidden
if (!in_array($role, ['HOD','IN1','IN2','IN3'], true)) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
    require_once __DIR__.'/../footer.php';
    exit;
}

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
// Allow alias 'group__id' (with double underscore) to be tolerant of URL typos
if ($group_id <= 0 && isset($_GET['group__id'])) { $group_id = (int)$_GET['group__id']; }
if ($group_id<=0) {
  if (!empty($redirect) && $redirect === 'group_timetable') {
    $_SESSION['info'] = 'Please select a group to view its timetable';
    header('Location: '.($base ?: '').'/group/Groups.php?redirect=group_timetable');
    exit;
  }
  echo '<div class="container mt-4"><div class="alert alert-warning">Invalid group</div><a class="btn btn-secondary mt-2" href="'.($base ?: '').'/group/Groups.php">Back to Groups</a></div>';
  require_once __DIR__.'/../footer.php';
  exit; 
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Group info
$grp = null; 
$stg = mysqli_prepare($con,'SELECT g.*, c.department_id FROM `groups` g LEFT JOIN course c ON c.course_id = g.course_id WHERE g.id=?');
if($stg){ 
  mysqli_stmt_bind_param($stg,'i',$group_id); 
  mysqli_stmt_execute($stg); 
  $rg=mysqli_stmt_get_result($stg); 
  $grp=$rg?mysqli_fetch_assoc($rg):null; 
  mysqli_stmt_close($stg);
}
if(!$grp){ 
  echo '<div class="container mt-4"><div class="alert alert-warning">Group not found</div></div>'; 
  require_once __DIR__.'/../footer.php'; 
  exit; 
}

// Build a robust printable label for the group
$grp_label = '';
if ($grp) {
  $nameCol = '';
  if (array_key_exists('group_name',$grp)) { $nameCol = trim((string)$grp['group_name']); }
  elseif (array_key_exists('name',$grp)) { $nameCol = trim((string)$grp['name']); }
  $codeCol = array_key_exists('group_code',$grp) ? trim((string)$grp['group_code']) : '';
  $grp_label = $nameCol !== '' ? $nameCol : ($codeCol !== '' ? $codeCol : ('Group #'.(int)$group_id));
}

// Enforce department ownership for HOD/IN roles: group must belong to user's department
if (in_array($role, ['HOD','IN1','IN2','IN3'], true)) {
  $deptId = '';
  if (!empty($_SESSION['department_code'])) {
    $deptId = trim((string)$_SESSION['department_code']);
  } elseif (!empty($_SESSION['department_id'])) {
    $deptId = trim((string)$_SESSION['department_id']);
  }
  $grpDept = isset($grp['department_id']) ? trim((string)$grp['department_id']) : '';
  if ($deptId === '' || $grpDept === '' || strval($grpDept) !== strval($deptId)) {
    echo '<div class="container mt-4"><div class="alert alert-danger">Access denied for this group</div></div>';
    require_once __DIR__.'/../footer.php';
    exit;
  }
}

// Current students (include legacy/null/empty statuses)
$cur = [];
$q = mysqli_prepare($con,'SELECT gs.id, gs.student_id, s.student_fullname 
                          FROM group_students gs 
                          INNER JOIN student s ON s.student_id=gs.student_id 
                          WHERE gs.group_id=? AND (gs.status="active" OR gs.status IS NULL OR gs.status="") 
                          ORDER BY s.student_fullname, s.student_id');
if ($q){ mysqli_stmt_bind_param($q,'i',$group_id); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $cur[]=$r; } mysqli_stmt_close($q);} 

// Candidates: by course + academic year from student_enroll not yet assigned to any active group for the same course & academic year
$candidates = [];
$qc = mysqli_prepare($con,'SELECT se.student_id, s.student_fullname 
                          FROM student_enroll se 
                          INNER JOIN student s ON s.student_id=se.student_id 
                          WHERE se.course_id=? AND se.academic_year=? 
                            AND NOT EXISTS (
                              SELECT 1 FROM group_students gs 
                              JOIN `groups` g2 ON g2.id = gs.group_id 
                              WHERE gs.student_id = se.student_id 
                                AND gs.status = "active"
                                AND g2.course_id = se.course_id 
                                AND g2.academic_year = se.academic_year
                            )
                          ORDER BY s.student_fullname');
if ($qc){
  mysqli_stmt_bind_param($qc,'ss',$grp['course_id'],$grp['academic_year']);
  mysqli_stmt_execute($qc);
  $resc=mysqli_stmt_get_result($qc);
  while($resc && ($r=mysqli_fetch_assoc($resc))){ $candidates[]=$r; }
  mysqli_stmt_close($qc);
}
// Fallback: if academic_year does not match, show active enrollments for this course regardless of year
if (empty($candidates)) {
  $qf = mysqli_prepare($con,'SELECT DISTINCT se.student_id, s.student_fullname 
                             FROM student_enroll se 
                             INNER JOIN student s ON s.student_id=se.student_id 
                             WHERE se.course_id=? 
                               AND COALESCE(se.student_enroll_status,\'\') IN (\'Following\',\'Active\') 
                               AND NOT EXISTS (
                                 SELECT 1 FROM group_students gs 
                                 JOIN `groups` g2 ON g2.id = gs.group_id 
                                 WHERE gs.student_id = se.student_id 
                                   AND gs.status = "active"
                                   AND g2.course_id = se.course_id 
                                   AND g2.academic_year = ?
                               )
                             ORDER BY s.student_fullname');
  if ($qf){
    mysqli_stmt_bind_param($qf,'ss',$grp['course_id'],$grp['academic_year']);
    mysqli_stmt_execute($qf);
    $resf=mysqli_stmt_get_result($qf);
    while($resf && ($r=mysqli_fetch_assoc($resf))){ $candidates[]=$r; }
    mysqli_stmt_close($qf);
  }
}
?>
<div class="container mt-4">
  <h3>Manage Students — <?php echo h($grp_label); ?> (<?php echo h($grp['course_id']); ?>, <?php echo h($grp['academic_year']); ?>)</h3>
  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Saved successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['err'])): ?>
    <div class="alert alert-danger">Action failed. Code: <?php echo h($_GET['err']); ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">Add Students</div>
    <div class="card-body">
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStudentsUpdate.php" class="mb-4">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <?php if (!empty($redirect)): ?>
          <input type="hidden" name="redirect" value="<?php echo h($redirect); ?>">
        <?php endif; ?>
        <div class="form-row">
          <div class="form-group col-md-8">
            <label for="student_ids">Select Students</label>
            <select name="student_ids[]" id="student_ids" class="form-control select2" multiple size="15">
              <?php foreach ($candidates as $s): ?>
                <option value="<?php echo h($s['student_id']); ?>"><?php echo h($s['student_fullname']); ?></option>
              <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Tip: Hold Ctrl (Cmd on Mac) to select multiple students.</small>
          </div>
          <div class="form-group col-md-4 d-flex align-items-end">
            <button type="submit" name="action" value="add" class="btn btn-primary">
              <?php echo !empty($redirect) ? 'Continue to Timetable' : 'Add to Group'; ?>
            </button>
            <?php if (!empty($redirect)): ?>
              <a href="<?php echo $base . '/timetable/GroupTimetable.php?group_id=' . $group_id; ?>" class="btn btn-success ml-2">
                Skip to Timetable
              </a>
            <?php else: ?>
              <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">Back</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Current Students</span>
      <?php if (!empty($cur)): ?>
      <!-- Bulk remove form lives separately to avoid nesting forms in the table -->
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStudentsUpdate.php" id="bulkRemoveForm" class="m-0 p-0">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <input type="hidden" name="action" value="bulk_remove">
      </form>
      <button type="submit" form="bulkRemoveForm" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove selected students from the group?');">Remove Selected</button>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped">
            <thead>
              <tr>
                <th style="width:40px"><input type="checkbox" id="selectAll"></th>
                <th>Student</th>
                <th style="width:150px">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cur as $r): ?>
                <tr>
                  <!-- Associate checkboxes with the standalone bulkRemoveForm using the form attribute -->
                  <td><input type="checkbox" name="student_ids[]" value="<?php echo h($r['student_id']); ?>" form="bulkRemoveForm"></td>
                  <td><?php echo h($r['student_fullname']).' ('.h($r['student_id']).')'; ?></td>
                  <td>
                    <form method="POST" action="<?php echo $base; ?>/controller/GroupStudentsUpdate.php" onsubmit="return confirm('Remove this student from the group?');" class="d-inline">
                      <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
                      <input type="hidden" name="student_id" value="<?php echo h($r['student_id']); ?>">
                      <button type="submit" name="action" value="remove" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($cur)): ?>
                <tr><td colspan="3" class="text-center text-muted">No students in this group yet</td></tr>
              <?php endif; ?>
            </tbody>
        </table>
        <?php if (!empty($cur)): ?>
        <button type="submit" form="bulkRemoveForm" class="btn btn-outline-danger" onclick="return confirm('Remove selected students from the group?');">Remove Selected</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>

<script>
  // Simple client-side search filter for candidate list
  (function(){
    var search = document.getElementById('candidateSearch');
    var list = document.getElementById('candidateList');
    if (!search || !list) return;
    var items = Array.prototype.slice.call(list.options).map(function(o){ return {value:o.value, text:o.text}; });
    function apply(){
      var q = search.value.toLowerCase();
      while (list.options.length) list.remove(0);
      items.forEach(function(it){
        if (!q || it.text.toLowerCase().indexOf(q) !== -1) {
          var o = document.createElement('option'); o.value = it.value; o.text = it.text; list.add(o);
        }
      });
    }
    search.addEventListener('input', apply);
  })();

  // Select All toggle for bulk remove
  (function(){
    var selAll = document.getElementById('selectAll');
    if (!selAll) return;
    selAll.addEventListener('change', function(){
      var form = document.getElementById('bulkRemoveForm');
      if (!form) return;
      var boxes = form.querySelectorAll('input[type="checkbox"][name="student_ids[]"]');
      boxes.forEach(function(b){ b.checked = selAll.checked; });
    });
  })();
</script>
