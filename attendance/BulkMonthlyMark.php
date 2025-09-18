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
$includeWeekends = isset($_GET['include_weekends']) ? (int)$_GET['include_weekends'] : 0;
$respectHolidays = isset($_GET['respect_holidays']) ? (int)$_GET['respect_holidays'] : 1;
$respectVacations = isset($_GET['respect_vacations']) ? (int)$_GET['respect_vacations'] : 1;
$overrideExisting = isset($_GET['override_existing']) ? (int)$_GET['override_existing'] : 0;
$markAs = isset($_GET['mark_as']) && in_array($_GET['mark_as'], ['Present','Absent'], true) ? $_GET['mark_as'] : 'Present';

?>
<div class="container mt-3">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div><strong>Bulk Monthly Attendance</strong> <small class="text-muted">(HOD / IN3)</small></div>
    </div>
    <div class="card-body">
      <form method="POST" action="<?php echo $base; ?>/controller/BulkMonthlySave.php" class="mb-3">
        <div class="form-row">
          <div class="form-group col-md-3">
            <label for="month">Month</label>
            <input type="month" class="form-control" id="month" name="month" value="<?php echo h($month); ?>" required>
          </div>
          <div class="form-group col-md-4">
            <label for="course_id">Course (optional)</label>
            <select class="form-control" id="course_id" name="course_id">
              <option value="">All courses in department</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo h($c['course_id']); ?>" <?php echo ($courseId===$c['course_id'])?'selected':''; ?>><?php echo h($c['course_id'].' - '.$c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label for="mark_as">Mark as</label>
            <select class="form-control" id="mark_as" name="mark_as">
              <option value="Present" <?php echo $markAs==='Present'?'selected':''; ?>>Present</option>
              <option value="Absent" <?php echo $markAs==='Absent'?'selected':''; ?>>Absent</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label for="override_existing">Existing Marks</label>
            <select class="form-control" id="override_existing" name="override_existing">
              <option value="0" <?php echo $overrideExisting? '' : 'selected'; ?>>Keep existing (no overwrite)</option>
              <option value="1" <?php echo $overrideExisting? 'selected' : ''; ?>>Override existing</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-2">
            <div class="custom-control custom-checkbox mt-4">
              <input type="checkbox" class="custom-control-input" id="include_weekends" name="include_weekends" value="1" <?php echo $includeWeekends? 'checked' : ''; ?>>
              <label class="custom-control-label" for="include_weekends">Include weekends</label>
            </div>
          </div>
          <div class="form-group col-md-3">
            <div class="custom-control custom-checkbox mt-4">
              <input type="checkbox" class="custom-control-input" id="respect_holidays" name="respect_holidays" value="1" <?php echo $respectHolidays? 'checked' : ''; ?>>
              <label class="custom-control-label" for="respect_holidays">Skip public holidays</label>
            </div>
          </div>
          <div class="form-group col-md-3">
            <div class="custom-control custom-checkbox mt-4">
              <input type="checkbox" class="custom-control-input" id="respect_vacations" name="respect_vacations" value="1" <?php echo $respectVacations? 'checked' : ''; ?>>
              <label class="custom-control-label" for="respect_vacations">Skip institute vacations</label>
            </div>
          </div>
        </div>
        <div class="alert alert-warning small">
          This will mark attendance for all eligible students in the selected scope for every selected day in the chosen month using module name <code>DAILY-S1</code>. Future dates are ignored.
        </div>
        <button type="submit" class="btn btn-primary">Proceed to Mark Month</button>
      </form>
      <hr>
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

          // Load students in scope
          $students = [];
          $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
          if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
          $where .= " AND se.student_enroll_status IN ('Following','Active')";
          $sql = "SELECT s.student_id, s.student_fullname FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where ORDER BY s.student_id ASC";
          $res = mysqli_query($con, $sql);
          if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[]=$r; } }

          // Preload existing attendance for quick pre-checks (module DAILY-S1)
          $presentMap = [];
          if (!empty($students) && !empty($visibleDates)) {
            $ids = array_map(function($r){ return "'".mysqli_real_escape_string($GLOBALS['con'],$r['student_id'])."'"; }, $students);
            $idList = implode(',', $ids);
            $dateList = implode(',', array_map(function($d){ return "'".addslashes($d)."'"; }, $visibleDates));
            $mn = 'DAILY-S1';
            $q = mysqli_query($con, "SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE module_name='".mysqli_real_escape_string($con,$mn)."' AND date IN ($dateList) AND student_id IN ($idList) GROUP BY student_id, date");
            if ($q) { while($row=mysqli_fetch_assoc($q)){ if ((int)$row['st']===1) { $presentMap[$row['student_id']][$row['date']] = true; } } }
          }
      ?>
        <div class="alert alert-info">Showing grid for <strong><?php echo h($month); ?></strong> <?php echo $courseId? ('| Course: '.h($courseId)) : ''; ?> — Dates: <?php echo count($visibleDates); ?>, Students: <?php echo count($students); ?></div>
        <form method="post" action="<?php echo $base; ?>/controller/BulkMonthlySaveDetailed.php">
          <input type="hidden" name="month" value="<?php echo h($month); ?>">
          <input type="hidden" name="course_id" value="<?php echo h($courseId); ?>">
          <input type="hidden" name="include_weekends" value="<?php echo (int)$includeWeekends; ?>">
          <input type="hidden" name="respect_holidays" value="<?php echo (int)$respectHolidays; ?>">
          <input type="hidden" name="respect_vacations" value="<?php echo (int)$respectVacations; ?>">
          <?php foreach ($visibleDates as $d): ?>
            <input type="hidden" name="dates[]" value="<?php echo h($d); ?>">
          <?php endforeach; ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="thead-light">
                <tr>
                  <th>Student ID</th>
                  <th>Student Name</th>
                  <?php foreach ($visibleDates as $d): $di=(int)substr($d,8,2); ?>
                    <th class="text-center" title="<?php echo h($d); ?>"><?php echo $di; ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($students)): ?>
                  <?php foreach ($students as $st): $sid=$st['student_id']; ?>
                    <tr>
                      <td><?php echo h($sid); ?></td>
                      <td><?php echo h($st['student_fullname']); ?></td>
                      <?php foreach ($visibleDates as $d): $checked = !empty($presentMap[$sid][$d]); ?>
                        <td class="text-center">
                          <input type="checkbox" name="present[<?php echo h($sid); ?>][]" value="<?php echo h($d); ?>" <?php echo $checked?'checked':''; ?>>
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
