<?php
$title = "Group Students | SLGTI";
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
$grp = null; $stg = mysqli_prepare($con,'SELECT * FROM `groups` WHERE id=?'); if($stg){ mysqli_stmt_bind_param($stg,'i',$group_id); mysqli_stmt_execute($stg); $rg=mysqli_stmt_get_result($stg); $grp=$rg?mysqli_fetch_assoc($rg):null; mysqli_stmt_close($stg);} if(!$grp){ echo '<div class="container mt-4"><div class="alert alert-warning">Group not found</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

// Current students
$cur = [];
$q = mysqli_prepare($con,'SELECT gs.id, gs.student_id, s.student_fullname FROM group_students gs INNER JOIN student s ON s.student_id=gs.student_id WHERE gs.group_id=? AND gs.status="active" ORDER BY s.student_fullname');
if ($q){ mysqli_stmt_bind_param($q,'i',$group_id); mysqli_stmt_execute($q); $res=mysqli_stmt_get_result($q); while($res && ($r=mysqli_fetch_assoc($res))){ $cur[]=$r; } mysqli_stmt_close($q);} 

// Candidates: by course + academic year from student_enroll not yet in group
$candidates = [];
$qc = mysqli_prepare($con,'SELECT se.student_id, s.student_fullname FROM student_enroll se INNER JOIN student s ON s.student_id=se.student_id WHERE se.course_id=? AND se.academic_year=? AND se.student_id NOT IN (SELECT student_id FROM group_students WHERE group_id=?) ORDER BY s.student_fullname');
if ($qc){ mysqli_stmt_bind_param($qc,'ssi',$grp['course_id'],$grp['academic_year'],$group_id); mysqli_stmt_execute($qc); $resc=mysqli_stmt_get_result($qc); while($resc && ($r=mysqli_fetch_assoc($resc))){ $candidates[]=$r; } mysqli_stmt_close($qc);} 
?>
<div class="container mt-4">
  <h3>Manage Students — <?php echo h($grp['name']); ?> (<?php echo h($grp['course_id']); ?>, <?php echo h($grp['academic_year']); ?>)</h3>
  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Saved successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['err'])): ?>
    <div class="alert alert-danger">Action failed. Code: <?php echo h($_GET['err']); ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header">Add Students</div>
    <div class="card-body">
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStudentsUpdate.php">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <div class="form-row">
          <div class="form-group col-md-8">
            <label>Select Students</label>
            <input type="text" id="candidateSearch" class="form-control mb-2" placeholder="Search by name or ID...">
            <select name="student_ids[]" id="candidateList" class="form-control" multiple size="10" required>
              <?php foreach ($candidates as $c): ?>
                <option value="<?php echo h($c['student_id']); ?>"><?php echo h($c['student_fullname']).' ('.h($c['student_id']).')'; ?></option>
              <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Tip: Hold Ctrl (Cmd on Mac) to select multiple students.</small>
          </div>
          <div class="form-group col-md-4 d-flex align-items-end">
            <button type="submit" name="action" value="add" class="btn btn-primary">Add to Group</button>
            <a href="<?php echo $base; ?>/group/Groups.php" class="btn btn-secondary ml-2">Back</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Current Students</span>
      <?php if (!empty($cur)): ?>
      <form method="POST" action="<?php echo $base; ?>/controller/GroupStudentsUpdate.php" class="m-0 p-0">
        <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
        <button type="submit" name="action" value="bulk_remove" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove selected students from the group?');">Remove Selected</button>
      </form>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <form method="POST" action="<?php echo $base; ?>/controller/GroupStudentsUpdate.php" id="bulkRemoveForm">
          <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
          <input type="hidden" name="action" value="bulk_remove">
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
                  <td><input type="checkbox" name="student_ids[]" value="<?php echo h($r['student_id']); ?>"></td>
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
        </form>
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
