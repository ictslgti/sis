<?php
// BulkMonthlyMark.php - HOD/IN3 bulk monthly attendance marking UI
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Allow only HOD and IN3
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$allowed = ['HOD','IN3'];
if (!in_array($role, $allowed, true)) { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }
$base = defined('APP_BASE') ? APP_BASE : '';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu2.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Resolve department for HOD/IN roles
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}

// Load courses of department
$courses = [];
if ($deptCode !== '') {
  $qr = mysqli_prepare($con, 'SELECT course_id, course_name FROM course WHERE department_id=? ORDER BY course_id');
  if ($qr) { mysqli_stmt_bind_param($qr,'s',$deptCode); mysqli_stmt_execute($qr); $res = mysqli_stmt_get_result($qr); while($res && ($row=mysqli_fetch_assoc($res))){ $courses[]=$row; } mysqli_stmt_close($qr);} 
}

$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$courseId = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$groupId = isset($_GET['group_id']) ? trim($_GET['group_id']) : '';
$includeWeekends = isset($_GET['include_weekends']) ? (int)$_GET['include_weekends'] : 0;
$respectHolidays = isset($_GET['respect_holidays']) ? (int)$_GET['respect_holidays'] : 1;
$respectVacations = isset($_GET['respect_vacations']) ? (int)$_GET['respect_vacations'] : 1;
$overrideExisting = isset($_GET['override_existing']) ? (int)$_GET['override_existing'] : 0;
$markAs = isset($_GET['mark_as']) && in_array($_GET['mark_as'], ['Present','Absent'], true) ? $_GET['mark_as'] : 'Present';

?>
<style>
  /* Grid scroll with sticky header and first two columns */
  .grid-scroll {
    position: relative;
    max-height: 70vh; /* restore vertical scroll inside grid */
    overflow: auto; /* both axes with bottom scrollbar */
  }
  .grid-scroll table {
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
    width: max-content; /* allow wide grid */
  }
  .grid-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa;
  }
  /* Sticky left columns */
  .grid-scroll th.sticky-col,
  .grid-scroll td.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #fff;
  }
  .grid-scroll th.sticky-col-2,
  .grid-scroll td.sticky-col-2 {
    position: sticky;
    left: 160px; /* must equal width of first sticky column */
    z-index: 2;
    background: #fff;
  }
  /* Keep sticky headers above sticky body cells */
  .grid-scroll thead th.sticky-col,
  .grid-scroll thead th.sticky-col-2 { z-index: 5; }

  /* Column widths for fixed offsets */
  .grid-scroll .col-id { min-width: 160px; max-width: 180px; }
  .grid-scroll .col-name { min-width: 220px; max-width: 280px; }
  .grid-scroll th.date-col, .grid-scroll td.date-col { min-width: 40px; max-width: 56px; text-align: center; }

  /* Improve checkbox alignment */
  .grid-scroll td.date-col input[type="checkbox"] { transform: translateY(1px); }

  /* Locked (-1) cells: muted and non-interactive cue */
  .grid-scroll td.date-col.locked { background: #f5f6f7; opacity: 0.7; }
  .grid-scroll td.date-col.locked input[type="checkbox"] { cursor: not-allowed; }
</style>
<div class="container mt-3">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div><strong>Bulk Monthly Attendance</strong> <small class="text-muted">(HOD / IN3)</small></div>
    </div>
    <div class="card-body">
      <?php
        // Feedback alerts
        $ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
        $err = isset($_GET['err']) ? trim((string)($_GET['err'])) : '';
        if ($ok === 1) {
          $ins = isset($_GET['ins']) ? (int)$_GET['ins'] : 0;
          $upd = isset($_GET['upd']) ? (int)$_GET['upd'] : 0;
          $skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
          echo '<div class="alert alert-success">Saved successfully. Inserts: '.(int)$ins.', Updates: '.(int)$upd.(isset($_GET['skip']) ? ', Skipped: '.(int)$skip : '').'</div>';
        } elseif ($err !== '') {
          $msg = 'Action failed.';
          if ($err === 'nodept') { $msg = 'No department resolved for your account.'; }
          elseif ($err === 'nodates') { $msg = 'No eligible dates in the selected month (filters may exclude all days).'; }
          elseif ($err === 'nostudents') { $msg = 'No eligible students in the selected scope.'; }
          elseif ($err === 'stmt') { $msg = 'Database statement could not be prepared.'; }
          elseif ($err === 'dberror') { $msg = 'Database error occurred while saving.'; }
          echo '<div class="alert alert-danger">'.htmlspecialchars($msg).'</div>';
        }
      ?>
      
      <form method="get" action="" class="mb-3">
        <input type="hidden" name="load" value="1">
        <div class="form-row">
          <div class="form-group col-md-3">
            <label for="g_month">Month</label>
            <input type="month" class="form-control" id="g_month" name="month" value="<?php echo h($month); ?>" required>
          </div>
          <div class="form-group col-md-4">
            <label for="g_course">Course (optional)</label>
            <select class="form-control" id="g_course" name="course_id">
              <option value="">All courses in department</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo h($c['course_id']); ?>" <?php echo ($courseId===$c['course_id'])?'selected':''; ?>><?php echo h($c['course_id'].' - '.$c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label for="g_group">Group (optional)</label>
            <select class="form-control" id="g_group" name="group_id">
              <option value="">All groups<?php echo $courseId? ' in course' : '' ; ?></option>
              <?php foreach (($groups ?? []) as $g): ?>
                <option value="<?php echo h($g['id']); ?>" <?php echo ($groupId!=='' && (string)$groupId===(string)$g['id'])?'selected':''; ?>><?php echo h($g['label']); ?> (ID <?php echo h($g['id']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-5">
            <div class="custom-control custom-checkbox mt-4">
              <input type="checkbox" class="custom-control-input" id="g_weekends" name="include_weekends" value="1" <?php echo $includeWeekends? 'checked' : ''; ?>>
              <label class="custom-control-label" for="g_weekends">Include weekends</label>
            </div>
            <div class="custom-control custom-checkbox mt-2">
              <input type="checkbox" class="custom-control-input" id="g_holidays" name="respect_holidays" value="1" <?php echo $respectHolidays? 'checked' : ''; ?>>
              <label class="custom-control-label" for="g_holidays">Skip public holidays</label>
            </div>
            <div class="custom-control custom-checkbox mt-2">
              <input type="checkbox" class="custom-control-input" id="g_vacations" name="respect_vacations" value="1" <?php echo $respectVacations? 'checked' : ''; ?>>
              <label class="custom-control-label" for="g_vacations">Skip institute vacations</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-secondary">Load Grid</button>
      </form>

      <?php
        $shouldLoad = isset($_GET['load']) && $_GET['load'] == '1';
        if ($shouldLoad && $deptCode !== '') {
          // Build month date list
          $firstDay = $month.'-01';
          $lastDay = date('Y-m-t', strtotime($firstDay));
          $today = date('Y-m-d');
          $daysInMonth = (int)date('t', strtotime($firstDay));

          // Helpers to load holidays/vacations
          function load_holidays_set($con, $firstDay, $lastDay) {
            $set = [];
            $cands = [ ['table'=>'holidays_lk','col'=>'date'], ['table'=>'public_holidays','col'=>'holiday_date'], ['table'=>'holidays','col'=>'holiday_date'] ];
            foreach ($cands as $c) {
              $t = mysqli_real_escape_string($con, $c['table']);
              $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
              if ($rs && mysqli_fetch_row($rs)) {
                $col = $c['col'];
                $q = mysqli_query($con, "SELECT `${col}` AS d FROM `${t}` WHERE `${col}` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
                if ($q) { while($r=mysqli_fetch_assoc($q)){ if (!empty($r['d'])) { $set[$r['d']] = true; } } }
                break;
              }
            }
            return $set;
          }
          function load_vacations_set($con, $firstDay, $lastDay) {
            $set = [];
            $single = [ ['table' => 'vacation_days', 'col' => 'vacation_date'], ['table' => 'vacations_days', 'col' => 'date'] ];
            foreach ($single as $c) {
              $t = mysqli_real_escape_string($con, $c['table']);
              $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
              if ($rs && mysqli_fetch_row($rs)) {
                $col = $c['col'];
                $q = mysqli_query($con, "SELECT `${col}` AS d FROM `${t}` WHERE `${col}` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
                if ($q) { while($r=mysqli_fetch_assoc($q)){ if (!empty($r['d'])) { $set[$r['d']] = true; } } }
                return $set;
              }
            }
            $ranges = [ ['table' => 'vacations', 'start' => 'start_date', 'end' => 'end_date'], ['table' => 'academic_vacations', 'start' => 'start_date', 'end' => 'end_date'], ['table' => 'institution_vacations', 'start' => 'from_date', 'end' => 'to_date'] ];
            foreach ($ranges as $c) {
              $t = mysqli_real_escape_string($con, $c['table']);
              $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
              if ($rs && mysqli_fetch_row($rs)) {
                $colS = $c['start']; $colE = $c['end'];
                $q = mysqli_query($con, "SELECT `${colS}` AS s, `${colE}` AS e FROM `${t}` WHERE NOT(`${colE}` < '".mysqli_real_escape_string($con,$firstDay)."' OR `${colS}` > '".mysqli_real_escape_string($con,$lastDay)."')");
                if ($q) {
                  while ($r = mysqli_fetch_assoc($q)) {
                    $s = !empty($r['s']) ? max($firstDay, $r['s']) : $firstDay;
                    $e = !empty($r['e']) ? min($lastDay, $r['e']) : $lastDay;
                    $ds = strtotime($s); $de = strtotime($e);
                    if ($ds && $de && $ds <= $de) {
                      for ($tday = $ds; $tday <= $de; $tday = strtotime('+1 day', $tday)) {
                        $set[date('Y-m-d', $tday)] = true;
                      }
                    }
                  }
                }
                break;
              }
            }
            return $set;
          }

          $holidaySet = $respectHolidays ? load_holidays_set($con, $firstDay, $lastDay) : [];
          $vacationSet = $respectVacations ? load_vacations_set($con, $firstDay, $lastDay) : [];

          // Build visible dates applying filters
          $visibleDates = [];
          for ($d=1; $d<=$daysInMonth; $d++) {
            $dstr = date('Y-m-d', strtotime($month.'-'.str_pad($d,2,'0',STR_PAD_LEFT)));
            if ($dstr > $today) continue; // ignore future
            $w = (int)date('w', strtotime($dstr));
            if (!$includeWeekends && ($w===0 || $w===6)) continue;
            if ($respectHolidays && isset($holidaySet[$dstr])) continue;
            if ($respectVacations && isset($vacationSet[$dstr])) continue;
            $visibleDates[] = $dstr;
          }

          // Load students in scope (course or group)
          $students = [];
          if ($groupId !== '') {
            // By group membership
            $sql = "SELECT s.student_id, s.student_fullname
                    FROM group_students gs
                    JOIN student s ON s.student_id = gs.student_id
                    WHERE gs.group_id = ? AND (gs.status='active' OR gs.status IS NULL OR gs.status='')
                    ORDER BY s.student_id";
            if ($st = mysqli_prepare($con, $sql)) {
              mysqli_stmt_bind_param($st, 'i', $groupId);
              mysqli_stmt_execute($st);
              $res = mysqli_stmt_get_result($st);
              while ($res && ($r = mysqli_fetch_assoc($res))) { $students[] = $r; }
              mysqli_stmt_close($st);
            }
          } else {
            // Original: by department (and optional course)
            $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
            if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
            $where .= " AND se.student_enroll_status IN ('Following','Active')";
            $sql = "SELECT s.student_id, s.student_fullname FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where ORDER BY s.student_id ASC";
            $res = mysqli_query($con, $sql);
            if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[]=$r; } }
          }

          // Do NOT auto-include dates that are fully/partially locked (-1). They should be excluded from bulk operations.

          // Preload existing attendance for quick pre-checks (module DAILY-S1)
          $presentMap = [];
          $lockedMap = []; // attendance_status < 0 (e.g., -1) => disable editing
          if (!empty($students) && !empty($visibleDates)) {
            $ids = array_map(function($r){ return "'".mysqli_real_escape_string($GLOBALS['con'],$r['student_id'])."'"; }, $students);
            $idList = implode(',', $ids);
            $dateList = implode(',', array_map(function($d){ return "'".addslashes($d)."'"; }, $visibleDates));
            $mn = 'DAILY-S1';
            // Use MIN instead of MAX to cope with legacy duplicates (0 and 1 both present);
            // this ensures explicit absences (0) are reflected after Save Grid even if old 'present' rows exist.
            $q = mysqli_query($con, "SELECT student_id, date, MIN(attendance_status) AS st FROM attendance WHERE module_name='".mysqli_real_escape_string($con,$mn)."' AND date IN ($dateList) AND student_id IN ($idList) GROUP BY student_id, date");
            if ($q) {
              while($row=mysqli_fetch_assoc($q)){
                $stmin = (int)$row['st'];
                if ($stmin === 1) { $presentMap[$row['student_id']][$row['date']] = true; }
                if ($stmin < 0)  { $lockedMap[$row['student_id']][$row['date']]  = true; }
              }
            }
          }
      ?>
        <div class="alert alert-info">Showing grid for <strong><?php echo h($month); ?></strong> <?php echo $courseId? ('| Course: '.h($courseId)) : ''; ?> — Dates: <?php echo count($visibleDates); ?>, Students: <?php echo count($students); ?></div>
        <!-- Quick action: mark a day as Holiday/Vacation (-1 for all visible students) -->
        <form method="post" action="<?php echo $base; ?>/controller/MarkHolidayDate.php" class="form-inline mb-2">
          <label class="mr-2">Mark date as Holiday/Vacation:</label>
          <input type="date" class="form-control mr-2" name="lock_date" min="<?php echo h($firstDay); ?>" max="<?php echo h($lastDay); ?>" required>
          <input type="hidden" name="month" value="<?php echo h($month); ?>">
          <input type="hidden" name="course_id" value="<?php echo h($courseId); ?>">
          <input type="hidden" name="group_id" value="<?php echo h($groupId); ?>">
          <button type="submit" class="btn btn-outline-danger">Lock Date (-1)</button>
        </form>

        <form method="post" action="<?php echo $base; ?>/controller/BulkMonthlySaveDetailed.php">
          <input type="hidden" name="month" value="<?php echo h($month); ?>">
          <input type="hidden" name="course_id" value="<?php echo h($courseId); ?>">
          <input type="hidden" name="group_id" value="<?php echo h($groupId); ?>">
          <input type="hidden" name="include_weekends" value="<?php echo (int)$includeWeekends; ?>">
          <input type="hidden" name="respect_holidays" value="<?php echo (int)$respectHolidays; ?>">
          <input type="hidden" name="respect_vacations" value="<?php echo (int)$respectVacations; ?>">
          <?php foreach ($visibleDates as $d): ?>
            <input type="hidden" name="dates[]" value="<?php echo h($d); ?>">
          <?php endforeach; ?>
          <div class="d-flex align-items-center mb-2">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="select_all_grid">
              <label class="custom-control-label" for="select_all_grid">Select all</label>
            </div>
          </div>
          <div class="table-responsive grid-scroll">
            <table class="table table-sm table-bordered">
              <thead class="thead-light">
                <tr>
                  <th class="sticky-col col-id">Student ID</th>
                  <th class="sticky-col-2 col-name">Student Name</th>
                  <?php foreach ($visibleDates as $d): $di=(int)substr($d,8,2); ?>
                    <th class="text-center date-col" title="<?php echo h($d); ?>"><?php echo $di; ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($students)): ?>
                  <?php foreach ($students as $st): $sid=$st['student_id']; ?>
                    <tr>
                      <td class="sticky-col col-id"><?php echo h($sid); ?></td>
                      <td class="sticky-col-2 col-name"><?php echo h($st['student_fullname']); ?></td>
                      <?php foreach ($visibleDates as $d): $checked = !empty($presentMap[$sid][$d]); $locked = !empty($lockedMap[$sid][$d]); ?>
                        <td class="text-center date-col<?php echo $locked ? ' locked' : '' ; ?>" title="<?php echo $locked ? 'Locked (status -1)' : h($d); ?>">
                          <input type="checkbox" name="present[<?php echo h($sid); ?>][]" value="<?php echo h($d); ?>" <?php echo $checked?'checked':''; ?> <?php echo $locked?'disabled':''; ?>>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="<?php echo 2 + count($visibleDates); ?>" class="text-center text-muted">No students</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-2">
            <button type="submit" class="btn btn-success">Save Grid</button>
          </div>
        </form>
      <?php } ?>
      <div class="text-muted small mt-3">Tip: Use Monthly Report to review results afterwards.</div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
  (function(){
    var base = <?php echo json_encode($base); ?>;
    // Only the grid loader form remains
    var courseSelects = [document.getElementById('g_course')].filter(Boolean);
    var groupSelects  = [document.getElementById('g_group')].filter(Boolean);
    function loadGroupsFor(selectEl, targetEl){
      if (!selectEl || !targetEl) return;
      var cid = selectEl.value || '';
      var xhr = new XMLHttpRequest();
      xhr.open('POST', base + '/controller/ajax/get_course_groups.php');
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function(){
        if (xhr.status === 200) {
          targetEl.innerHTML = xhr.responseText;
        } else {
          // Fallback: keep a minimal option to avoid empty select
          targetEl.innerHTML = '<option value="">All groups'+(cid?' in course':'')+'</option>';
        }
      };
      xhr.send('course_id=' + encodeURIComponent(cid));
    }
    // Link grid form selects
    if (courseSelects[0] && groupSelects[0]){
      courseSelects[0].addEventListener('change', function(){ loadGroupsFor(courseSelects[0], groupSelects[0]); });
      // Initial populate
      loadGroupsFor(courseSelects[0], groupSelects[0]);
    }

    // Select-all behavior for grid
    var selectAll = document.getElementById('select_all_grid');
    if (selectAll){
      selectAll.addEventListener('change', function(){
        var table = document.querySelector('form[action$="/controller/BulkMonthlySaveDetailed.php"] table');
        if (!table) return;
        var inputs = table.querySelectorAll('tbody input[type="checkbox"]');
        inputs.forEach(function(cb){ if (!cb.disabled) { cb.checked = selectAll.checked; } });
      });
    }

    // On submit, serialize checked boxes into a compact JSON payload to bypass max_input_vars limits
    var gridForm = document.querySelector('form[action$="/controller/BulkMonthlySaveDetailed.php"]');
    if (gridForm){
      gridForm.addEventListener('submit', function(){
        try {
          var checked = gridForm.querySelectorAll('tbody input[type="checkbox"]:checked');
          var pairs = [];
          checked.forEach(function(cb){
            if (cb.disabled) { return; }
            var m = cb.name && cb.name.match(/^present\[(.+?)\]/);
            if (m) { pairs.push([m[1], cb.value]); }
          });
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'present_pairs';
          hidden.value = JSON.stringify(pairs);
          gridForm.appendChild(hidden);
          // Disable individual checkboxes so they are not submitted as thousands of inputs
          var allCbs = gridForm.querySelectorAll('tbody input[type="checkbox"]');
          allCbs.forEach(function(cb){ cb.disabled = true; });
        } catch (e) { /* no-op; server will still handle legacy inputs if present */ }
      });
    }
  })();
  </script>
