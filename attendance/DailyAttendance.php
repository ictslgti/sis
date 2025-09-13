<!--Block#1 start dont change the order-->
<?php 
$title="Daily Attendance | SLGTI";    
include_once ("../config.php");
include_once ("../head.php");
include_once ("../menu.php");

// HOD and IN3 can use this page
require_roles(['HOD','IN3']);
$_isADM = (isset($_SESSION['user_type']) && $_SESSION['user_type']==='ADM');
?>
<!-- end dont change the order-->
<style>
  /* Mobile layout improvements specific to Daily Attendance */
  @media (max-width: 576px) {
    .card-header .d-flex { flex-direction: column !important; align-items: stretch !important; }
    .card-header .d-flex > div:first-child { margin-bottom: .5rem; }
    .card-header .form-inline { display: block !important; }
    .card-header .form-inline label { display: block; width: 100%; margin: .25rem 0; font-weight: 600; }
    .card-header .form-inline input.form-control,
    .card-header .form-inline select.form-control,
    .card-header .form-inline button { width: 100% !important; margin: 0 0 .5rem 0 !important; }
    .badge { display: inline-block; margin-bottom: .5rem; }
    .table-responsive { overflow-x: auto; }
    table.table thead th, table.table tbody td { white-space: nowrap; }
    /* Reasonable minimum widths */
    table.table thead th:nth-child(1),
    table.table tbody td:nth-child(1) { min-width: 84px; text-align: center; }
    table.table thead th:nth-child(2),
    table.table tbody td:nth-child(2) { min-width: 160px; }
    table.table thead th:nth-child(3),
    table.table tbody td:nth-child(3) { min-width: 200px; }
    table.table thead th:nth-child(4),
    table.table tbody td:nth-child(4) { min-width: 120px; }
  }
</style>

<?php
// Resolve department for HOD
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '') {
  // Try to resolve from staff table
  if (!empty($_SESSION['user_name'])) {
    $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
    $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
    if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
  }
}

// Filters (single-slot system)
$date = isset($_GET['date']) && $_GET['date']!=='' ? $_GET['date'] : date('Y-m-d');
// Force single slot
$slot = 1;
$course = isset($_GET['course']) ? trim($_GET['course']) : '';

// Build holiday and vacation sets for current month (to disable in picker)
$firstDay = date('Y-m-01', strtotime($date));
$lastDay  = date('Y-m-t', strtotime($date));
function load_holidays_set_month($con, $firstDay, $lastDay){
  $set = [];
  $cands = [
    ['table'=>'holidays_lk','col'=>'date'],
    ['table'=>'public_holidays','col'=>'holiday_date'],
    ['table'=>'holidays','col'=>'holiday_date'],
  ];
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
$vacationSet = (function($con, $firstDay, $lastDay){
  $set = [];
  // Try single-date tables first
  $single = [
    ['table' => 'vacation_days', 'col' => 'vacation_date'],
    ['table' => 'vacations_days', 'col' => 'date'],
  ];
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
  // Range-based definitions
  $ranges = [
    ['table' => 'vacations', 'start' => 'start_date', 'end' => 'end_date'],
    ['table' => 'academic_vacations', 'start' => 'start_date', 'end' => 'end_date'],
    ['table' => 'institution_vacations', 'start' => 'from_date', 'end' => 'to_date'],
  ];
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
})($con, $firstDay, $lastDay);
$holidaySet = load_holidays_set_month($con, $firstDay, $lastDay);

// Load department courses
$courses = [];
if ($deptCode !== '') {
  $dq = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con,$deptCode)."' ORDER BY course_name");
  if ($dq) { while($row=mysqli_fetch_assoc($dq)){ $courses[]=$row; } }
}

// Detect if optional conduct acceptance column exists
$hasConduct = false;
if ($chk = mysqli_query($con, "SHOW COLUMNS FROM `student` LIKE 'student_conduct_accepted_at'")) {
  $hasConduct = (mysqli_num_rows($chk) === 1);
  mysqli_free_result($chk);
}

// Load students (scoped to department and optional course)
$students = [];
// Track how many students were excluded due to not accepting conduct
$excludedCount = 0;
if ($deptCode !== '') {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
  if ($course !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
  // Only active/following students
  $where .= " AND se.student_enroll_status IN ('Following','Active')";
  // Exclude students who have NOT accepted conduct (when column exists)
  if ($hasConduct) { $where .= " AND s.student_conduct_accepted_at IS NOT NULL"; }

  // Build SELECT list conditionally based on conduct column availability
  $selectCols = "s.student_id, s.student_fullname, se.course_id, c.course_name";
  if ($hasConduct) { $selectCols .= ", s.student_conduct_accepted_at"; }
  $sql = "SELECT $selectCols".
          "\n          FROM student_enroll se\n          JOIN course c ON c.course_id = se.course_id\n          JOIN student s ON s.student_id = se.student_id\n          $where\n          ORDER BY s.student_id ASC";
  $res = mysqli_query($con, $sql);
  if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[]=$r; } }

  // Compute excluded count for info badge
  if ($hasConduct) {
    $whereBase = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
    if ($course !== '') { $whereBase .= " AND se.course_id='".mysqli_real_escape_string($con,$course)."'"; }
    $whereBase .= " AND se.student_enroll_status IN ('Following','Active') AND s.student_conduct_accepted_at IS NULL";
    $cntSql = "SELECT COUNT(*) AS cnt\n              FROM student_enroll se\n              JOIN course c ON c.course_id = se.course_id\n              JOIN student s ON s.student_id = se.student_id\n              $whereBase";
    if ($cres = mysqli_query($con, $cntSql)) {
      if ($crow = mysqli_fetch_assoc($cres)) { $excludedCount = (int)$crow['cnt']; }
      mysqli_free_result($cres);
    }
  }
}

// Load already marked attendance for this date+slot for quick pre-check
$presentMap = [];
if (!empty($students)) {
  $ids = [];
  foreach ($students as $r) {
    $ids[] = "'" . mysqli_real_escape_string($con, $r['student_id']) . "'";
  }
  $idList = implode(',', $ids);
  if ($idList !== '') {
    $dt = mysqli_real_escape_string($con, $date);
    // Match specific slot using module_name = 'DAILY-S<slot>' to avoid cross-slot duplicates
    $mn = 'DAILY-S' . (int)$slot;
    $q = mysqli_query($con, "SELECT student_id, attendance_status FROM attendance WHERE date='$dt' AND module_name='" . mysqli_real_escape_string($con, $mn) . "' AND student_id IN ($idList)");
    if ($q) { while($row=mysqli_fetch_assoc($q)){ $presentMap[$row['student_id']] = (int)$row['attendance_status']===1; } }
  }
}
?>

<div class="container<?php echo $_isADM ? '' : ' hod-desktop-offset'; ?>" style="margin-top:30px">
  <?php include_once ("Attendancenav.php"); ?>
  <div class="card">
    <div class="card-header">
      <div class="d-flex justify-content-between align-items-center">
        <div><strong><?php echo htmlspecialchars($deptCode); ?></strong></div>
        <div>
          <form class="form-inline" method="get" action="">
            <label class="mr-2">Date</label>
            <input type="date" name="date" id="att-date" class="form-control mr-2" value="<?php echo htmlspecialchars($date); ?>" required>
            <!-- Single slot only; slot selector removed -->
            <label class="mr-2">Course</label>
            <select name="course" class="form-control mr-2">
              <option value="">-- All --</option>
              <?php foreach($courses as $c): ?>
                <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo $course===$c['course_id']?'selected':''; ?>><?php echo htmlspecialchars($c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary" type="submit">Load</button>
          </form>
        </div>
      </div>
    </div>
    <div class="card-body">
      <?php if (isset($_GET['ok'])): ?>
        <div class="alert alert-success">Attendance saved successfully.</div>
      <?php endif; ?>
      <?php if (isset($_GET['err'])): ?>
        <div class="alert alert-danger">Operation failed. Code: <?php echo htmlspecialchars($_GET['err']); ?></div>
      <?php endif; ?>
      <?php if ($deptCode===''): ?>
        <div class="alert alert-warning">Department not configured for your account. Please contact admin.</div>
      <?php else: ?>
        <?php
          $isWeekend = in_array((int)date('w', strtotime($date)), [0,6], true);
          $isHoliday = isset($holidaySet[$date]);
          $isVacation = isset($vacationSet[$date]);
          $isNonWorking = $isWeekend || $isHoliday || $isVacation;
        ?>
        <div class="mb-2">
          <?php if ($isNonWorking): ?>
            <div class="alert alert-warning py-2 mb-2">
              <strong>Note:</strong> <?php echo htmlspecialchars($date); ?> is a
              <?php
                $types=[]; if($isWeekend) $types[]='Weekend'; if($isHoliday) $types[]='Holiday'; if($isVacation) $types[]='Vacation';
                echo htmlspecialchars(implode(', ', $types));
              ?>.
              If you save attendance for this date, it will be counted in the Monthly Report as an <em>exception</em> and included in <strong>Considered Days</strong>.
            </div>
          <?php else: ?>
            <span class="badge badge-success">Working day</span>
          <?php endif; ?>
        </div>
        <form method="post" action="<?php echo APP_BASE; ?>/controller/DailyAttendanceSave.php">
          <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
          <!-- Single slot only; no slot field needed -->
          <input type="hidden" name="course" value="<?php echo htmlspecialchars($course); ?>">
          <?php if ($hasConduct): ?>
            <div class="mb-2">
              <span class="badge badge-info">Excluded <?php echo (int)$excludedCount; ?> not accepted Code of Conduct</span>
            </div>
          <?php endif; ?>
          <div class="mb-2">
            <div class="custom-control custom-checkbox d-inline-block mr-3">
              <input type="checkbox" class="custom-control-input" id="allow-nwd">
              <label class="custom-control-label" for="allow-nwd">Allow marking on non-working day (weekend/holiday/vacation)</label>
            </div>
            <span id="nwd-warning" class="badge badge-warning" style="display:none;">Selected date is a non-working day. Tick the override to proceed.</span>
            <div id="nwd-info" class="text-muted small mt-1" style="display:none;"></div>
          </div>
          <div class="mb-2">
            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAll(true)">Mark All Present</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAll(false)">Unmark All</button>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Present</th>
                  <th>Student ID</th>
                  <th>Student Name</th>
                
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($students)): ?>
                  <?php foreach($students as $s): $sid=$s['student_id']; $isP = isset($presentMap[$sid]) ? $presentMap[$sid] : false; ?>
                    <tr>
                      <td>
                        <input type="checkbox" name="present[]" value="<?php echo htmlspecialchars($sid); ?>" <?php echo $isP?'checked':''; ?> >
                      </td>
                      <td><?php echo htmlspecialchars($sid); ?></td>
                      <td><?php echo htmlspecialchars($s['student_fullname']); ?></td>
                     
                      <td>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo APP_BASE; ?>/student/Student_profile.php?Sid=<?php echo urlencode($sid); ?>" target="_blank" rel="noopener">
                          <i class="fas fa-user"></i> View Profile
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted">No students found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-2">
            <button id="save-btn" type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Attendance</button>
          </div>
        </form>
        <script>
          (function(){
            var dInput = document.getElementById('att-date');
            if (!dInput) return;
            var holidays = <?php echo json_encode(array_keys($holidaySet)); ?>;
            var vacations = <?php echo json_encode(array_keys($vacationSet)); ?>;
            var hset = {}, vset = {};
            holidays.forEach(function(d){ hset[d] = true; });
            vacations.forEach(function(d){ vset[d] = true; });
            function isWorkingDay(iso){
              var dt = new Date(iso);
              if (isNaN(dt)) return true;
              var w = dt.getDay(); // 0=Sun,6=Sat
              if (w===0 || w===6) return false;
              return !(hset[iso] || vset[iso]);
            }
            var allow = document.getElementById('allow-nwd');
            var warn = document.getElementById('nwd-warning');
            var info = document.getElementById('nwd-info');
            var saveBtn = document.getElementById('save-btn');
            function refreshNWD(){
              var v = dInput.value;
              var nwd = v && !isWorkingDay(v);
              if (nwd) {
                warn.style.display = 'inline-block';
                saveBtn.disabled = !allow.checked;
                // Build info describing type and effect
                var dt = new Date(v);
                var w = dt.getDay();
                var types = [];
                if (w===0 || w===6) types.push('Weekend');
                if (hset[v]) types.push('Holiday');
                if (vset[v]) types.push('Vacation');
                var typeStr = types.join(', ') || 'Non-working day';
                info.textContent = typeStr + ': If you save attendance for this date, it will be included in the Monthly Report as an exception and counted in Considered Days.';
                info.style.display = 'block';
              } else {
                warn.style.display = 'none';
                saveBtn.disabled = false;
                info.style.display = 'none';
              }
            }
            try { refreshNWD(); } catch(_){ }
            dInput.addEventListener('change', refreshNWD);
            if (allow) allow.addEventListener('change', refreshNWD);
          })();
        </script>
        <script>
          function toggleAll(state){
            document.querySelectorAll('input[type="checkbox"][name="present[]"]').forEach(cb=>cb.checked=state);
          }
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>

<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
