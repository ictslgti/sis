<?php
// student/AllowanceEligibility.php - SAO view to list students with filters and checkboxes
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Access: only SAO users can access this page
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'SAO') {
  require_once __DIR__ . '/../head.php';
  require_once __DIR__ . '/../menu.php';
  echo '<div class="container mt-4"><div class="alert alert-danger">Access denied.</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

// Helper
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Filters
$fdept   = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$fcourse = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
// Only current allowance-eligible students
$fonlyAE = isset($_GET['only_allowance']) && $_GET['only_allowance'] === '1' ? '1' : '';
// Attendance filters: month (YYYY-MM) and minimum percent
$fmonth  = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) ? $_GET['month'] : date('Y-m');
$fminpct = isset($_GET['min_percent']) && $_GET['min_percent'] !== '' ? max(0, min(100, (int)$_GET['min_percent'])) : '';

// Handle bulk actions (SAO only)
$messages = [];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF is not implemented globally; follow existing pattern used in ManageStudents
  $action = isset($_POST['bulk_action']) ? $_POST['bulk_action'] : '';
  $sids = isset($_POST['sids']) && is_array($_POST['sids']) ? array_values(array_filter($_POST['sids'])) : [];
  if (!$sids) {
    $errors[] = 'No students selected.';
  } else {
    $inParts = [];
    foreach ($sids as $sid) { $inParts[] = "'" . mysqli_real_escape_string($con, $sid) . "'"; }
    $in = implode(',', $inParts);
    if ($action === 'bulk_mark_allowance') {
      $q = "UPDATE student SET allowance_eligible=1 WHERE student_id IN ($in)";
      if (mysqli_query($con, $q)) {
        $affected = mysqli_affected_rows($con);
        $messages[] = ($affected > 0) ? 'Marked allowance eligible for selected students.' : 'No rows updated.';
      } else {
        $errors[] = 'Bulk update failed (mark).';
      }
    } elseif ($action === 'bulk_clear_allowance') {
      $q = "UPDATE student SET allowance_eligible=0 WHERE student_id IN ($in)";
      if (mysqli_query($con, $q)) {
        $affected = mysqli_affected_rows($con);
        $messages[] = ($affected > 0) ? 'Cleared allowance eligibility for selected students.' : 'No rows updated.';
      } else {
        $errors[] = 'Bulk update failed (clear).';
      }
    }
  }
  // Redirect with flash to avoid resubmission; preserve filters
  $_SESSION['flash_messages'] = $messages;
  $_SESSION['flash_errors'] = $errors;
  $qs = $_GET; // keep current filters in URL
  $loc = $base . '/student/AllowanceEligibility.php';
  if (!empty($qs)) { $loc .= '?' . http_build_query($qs); }
  header('Location: ' . $loc);
  exit;
}

// Flash
if (!empty($_SESSION['flash_messages'])) { $messages = $_SESSION['flash_messages']; unset($_SESSION['flash_messages']); }
if (!empty($_SESSION['flash_errors'])) { $errors = $_SESSION['flash_errors']; unset($_SESSION['flash_errors']); }

// Dropdown data
$departments = [];
if ($r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $departments[] = $row; }
  mysqli_free_result($r);
}
$courses = [];
if ($r = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $courses[] = $row; }
  mysqli_free_result($r);
}

// Build query
$where = [];
if ($fdept !== '') {
  if ($fcourse !== '') {
    $where[] = "c.department_id = '" . mysqli_real_escape_string($con, $fdept) . "'";
  } else {
    $safeDept = mysqli_real_escape_string($con, $fdept);
    $where[] = "EXISTS (SELECT 1 FROM student_enroll e2 JOIN course c2 ON c2.course_id=e2.course_id WHERE e2.student_id=s.student_id AND c2.department_id='".$safeDept."')";
  }
}
if ($fcourse !== '') {
  $where[] = "c.course_id = '" . mysqli_real_escape_string($con, $fcourse) . "'";
}
if ($fonlyAE === '1') {
  $where[] = "s.allowance_eligible = 1";
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT DISTINCT s.student_id, s.student_fullname, s.allowance_eligible, c.course_name, d.department_name
        FROM student s
        LEFT JOIN student_enroll e ON e.student_id = s.student_id
        LEFT JOIN course c ON c.course_id = e.course_id
        LEFT JOIN department d ON d.department_id = c.department_id" . $whereSql . "
        ORDER BY s.student_id ASC";
$res = mysqli_query($con, $sql);
// Materialize rows for further processing (attendance filtering)
$rows = [];
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }
$total_count = count($rows);

// Build considered working days for the selected month (reusing logic from MonthlyAttendanceReport)
// Compute month range
$firstDay = $fmonth . '-01';
$lastDay  = date('Y-m-t', strtotime($firstDay));
$daysInMonth = (int)date('t', strtotime($firstDay));
$dayDates = [];
for ($d = 1; $d <= $daysInMonth; $d++) { $dayDates[$d] = date('Y-m-d', strtotime($fmonth.'-'.str_pad($d,2,'0',STR_PAD_LEFT))); }

// Load holidays and vacations if tables exist (scoped helpers)
if (!function_exists('load_holidays_set')) {
  function load_holidays_set($con, $firstDay, $lastDay) {
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
}
if (!function_exists('load_vacations_set')) {
  function load_vacations_set($con, $firstDay, $lastDay) {
    $set = [];
    $candsSingle = [
      ['table' => 'vacation_days', 'col' => 'vacation_date'],
      ['table' => 'vacations_days', 'col' => 'date'],
    ];
    foreach ($candsSingle as $c) {
      $t = mysqli_real_escape_string($con, $c['table']);
      $rs = mysqli_query($con, "SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
      if ($rs && mysqli_fetch_row($rs)) {
        $col = $c['col'];
        $q = mysqli_query($con, "SELECT `${col}` AS d FROM `${t}` WHERE `${col}` BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
        if ($q) { while($r=mysqli_fetch_assoc($q)){ if (!empty($r['d'])) { $set[$r['d']] = true; } } }
        return $set; // prefer single-date style if found
      }
    }
    $candsRange = [
      ['table' => 'vacations', 'start' => 'start_date', 'end' => 'end_date'],
      ['table' => 'academic_vacations', 'start' => 'start_date', 'end' => 'end_date'],
      ['table' => 'institution_vacations', 'start' => 'from_date', 'end' => 'to_date'],
    ];
    foreach ($candsRange as $c) {
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
}

$holidaySet = load_holidays_set($con, $firstDay, $lastDay);
$vacationSet = load_vacations_set($con, $firstDay, $lastDay);

// Exceptional (any day in month with attendance present)
$exceptionalSet = [];
$exq = mysqli_query($con, "SELECT DISTINCT date AS d FROM attendance WHERE date BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
if ($exq) { while($row=mysqli_fetch_assoc($exq)){ if (!empty($row['d'])) { $exceptionalSet[$row['d']] = true; } } }

// NWD overrides (attendance_status=-1) never counted
$nwdOverrideSet = [];
$nq = mysqli_query($con, "SELECT DISTINCT date AS d FROM attendance WHERE attendance_status=-1 AND date BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
if ($nq) { while($row=mysqli_fetch_assoc($nq)){ if (!empty($row['d'])) { $nwdOverrideSet[$row['d']] = true; } } }

// Compute considered working days
$workDayDates = [];
$countWorking = 0;
foreach ($dayDates as $idx=>$dstr) {
  $w = (int)date('w', strtotime($dstr)); // 0=Sun,6=Sat
  if (isset($nwdOverrideSet[$dstr])) { continue; }
  // Exclude weekends entirely, regardless of attendance
  if ($w === 0 || $w === 6) { continue; }
  if (isset($holidaySet[$dstr]) || isset($vacationSet[$dstr])) { continue; }
  $workDayDates[$idx] = $dstr; $countWorking++;
}

// Compute per-student present days and percentage, then apply min-percent filter if set
$percentMap = [];
if (!empty($rows) && $countWorking > 0) {
  $dateList = implode(',', array_map(function($d){ return "'".addslashes($d)."'"; }, array_values($workDayDates)));
  if ($dateList === '') { $dateList = "''"; }
  $ids = [];
  foreach ($rows as $r2) { $ids[] = "'".mysqli_real_escape_string($con, $r2['student_id'])."'"; }
  $idList = $ids ? implode(',', $ids) : "''";
  $q = mysqli_query($con, "SELECT a.student_id, COUNT(*) AS present_days FROM (SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE date IN (".$dateList.") AND student_id IN (".$idList.") GROUP BY student_id, date) a WHERE a.st=1 GROUP BY a.student_id");
  $presentMap = [];
  if ($q) { while($r=mysqli_fetch_assoc($q)){ $presentMap[$r['student_id']] = (int)$r['present_days']; } }
  foreach ($rows as $r3) {
    $sid = $r3['student_id'];
    $p = isset($presentMap[$sid]) ? $presentMap[$sid] : 0;
    $pct = $countWorking > 0 ? round(($p / $countWorking) * 100) : 0;
    $percentMap[$sid] = $pct;
  }
  if ($fminpct !== '') {
    $rows = array_values(array_filter($rows, function($it) use ($percentMap, $fminpct) {
      $sid = $it['student_id'];
      $pct = isset($percentMap[$sid]) ? (int)$percentMap[$sid] : 0;
      return $pct >= (int)$fminpct;
    }));
    $total_count = count($rows);
  }
}

// UI
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid px-0 px-sm-2 px-md-4">
  <div class="row align-items-center mt-2 mb-2 mt-sm-1 mb-sm-3">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm mb-1">
          <li class="breadcrumb-item"><a href="<?php echo $base; ?>/dashboard/index.php">Dashboard</a></li>
          <li class="breadcrumb-item active" aria-current="page">Allowance Eligibility</li>
        </ol>
      </nav>
      <h4 class="d-flex align-items-center page-title">
        <i class="fas fa-hand-holding-usd text-primary mr-2"></i>
        Allowance Eligibility
      </h4>
    </div>
  </div>

  <?php if (!empty($messages)): ?>
    <?php foreach ($messages as $m): ?>
      <div class="alert alert-success"><?php echo h($m); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><?php echo h($e); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center">
      <div class="font-weight-semibold"><i class="fa fa-sliders-h mr-1"></i> Filters</div>
      <button class="btn btn-sm btn-outline-secondary d-md-none ml-auto" type="button" data-toggle="collapse" data-target="#filtersBox" aria-expanded="false" aria-controls="filtersBox">Show/Hide</button>
    </div>
    <div id="filtersBox" class="collapse show">
      <div class="card-body">
        <form class="mb-0" method="get" action="">
          <div class="form-row">
            <div class="form-group col-12 col-md-4">
              <label for="fdept" class="small text-muted mb-1">Department</label>
              <select id="fdept" name="department_id" class="form-control">
                <option value="">-- Any --</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?php echo h($d['department_id']); ?>" <?php echo ($fdept === $d['department_id'] ? 'selected' : ''); ?>><?php echo h($d['department_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-12 col-md-4">
              <label for="fcourse" class="small text-muted mb-1">Course</label>
              <select id="fcourse" name="course_id" class="form-control">
                <option value="">-- Any --</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?php echo h($c['course_id']); ?>" data-dept="<?php echo h($c['department_id']); ?>" <?php echo ($fcourse === $c['course_id'] ? 'selected' : ''); ?>><?php echo h($c['course_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-6 col-md-2">
              <label for="fmonth" class="small text-muted mb-1">Month</label>
              <input type="month" id="fmonth" name="month" class="form-control" value="<?php echo h($fmonth); ?>">
            </div>
            <div class="form-group col-6 col-md-2">
              <label for="fminpct" class="small text-muted mb-1">Min %</label>
              <input type="number" id="fminpct" name="min_percent" min="0" max="100" step="1" class="form-control" value="<?php echo h($fminpct); ?>" placeholder="e.g. 80">
            </div>
            <div class="form-group col-12 col-md-2">
              <div class="custom-control custom-checkbox mt-4">
                <input type="checkbox" class="custom-control-input" id="fonlyAE" name="only_allowance" value="1" <?php echo ($fonlyAE==='1')?'checked':''; ?>>
                <label class="custom-control-label" for="fonlyAE">Only allowance-eligible</label>
              </div>
            </div>
            <div class="form-group col-12 col-md-12 d-flex align-items-end">
              <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <style>
    .table.table-sm td, .table.table-sm th { padding: .45rem .5rem; }
    .table-sticky thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
    .table-scroll { max-height: 70vh; overflow-y: auto; }
  </style>
  <script>
    // Limit course options by selected department
    (function() {
      var dept = document.getElementById('fdept');
      var course = document.getElementById('fcourse');
      if (!dept || !course) return;
      var all = Array.prototype.slice.call(course.options).map(function(o){
        return { value:o.value, text:o.text, dept:o.getAttribute('data-dept') };
      });
      function apply(){
        var d = dept.value;
        var keep = course.value;
        while (course.options.length) course.remove(0);
        var ph = document.createElement('option'); ph.value=''; ph.text='-- Any --'; course.add(ph);
        all.forEach(function(it){
          if (!it.value) return;
          if (!d || it.dept === d){ var o=document.createElement('option'); o.value=it.value; o.text=it.text; if (it.value===keep) o.selected=true; course.add(o);} 
        });
      }
      dept.addEventListener('change', apply);
      apply();
    })();
  </script>

  <form method="post">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-2">
      <div class="mb-2 mb-md-0">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="var c=document.querySelectorAll('.sel'); var allChecked=[].every.call(c,function(i){return i.checked}); [].forEach.call(c,function(i){i.checked=!allChecked});">Toggle Select All</button>
        <button type="submit" name="bulk_action" value="bulk_mark_allowance" class="btn btn-primary btn-sm ml-2" onclick="return confirm('Mark allowance eligible for selected students?');">Mark Eligible</button>
        <button type="submit" name="bulk_action" value="bulk_clear_allowance" class="btn btn-secondary btn-sm ml-1" onclick="return confirm('Clear allowance eligibility for selected students?');">Clear Eligible</button>
      </div>
      <div class="mb-2 mb-md-0">
        <?php
          $repUrl = ($base ?: '') . '/attendance/MonthlyAttendanceReport.php?view=detailed'
                  . '&month=' . urlencode($fmonth)
                  . ($fdept !== '' ? ('&department_id=' . urlencode($fdept)) : '')
                  . ($fcourse !== '' ? ('&course_id=' . urlencode($fcourse)) : '')
                  . ($fonlyAE === '1' ? '&only_allowance=1' : '');
        ?>
        <a class="btn btn-success btn-sm" href="<?php echo $repUrl; ?>" target="_blank" rel="noopener">
          <i class="fas fa-external-link-alt mr-1"></i> Open Monthly Attendance Report
        </a>
      </div>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="font-weight-semibold"><i class="fa fa-users mr-1"></i> Students <span class="badge badge-secondary ml-2"><?php echo (int)$total_count; ?></span></div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive table-scroll">
          <table class="table table-striped table-bordered table-hover table-sm table-sticky mb-0">
            <thead>
              <tr>
                <th style="width:36px;"><input type="checkbox" onclick="var c=this.checked; document.querySelectorAll('.sel').forEach(function(i){i.checked=c;});"></th>
                <th>No</th>
                <th>Student ID</th>
                <th>Full Name</th>
                <th class="d-none d-md-table-cell">Allowance</th>
                <th class="d-none d-md-table-cell">Attendance %</th>
                <th class="d-none d-md-table-cell">Course</th>
                <th class="d-none d-lg-table-cell">Department</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($rows)): $i=0; foreach ($rows as $row): ?>
                <tr>
                  <td class="align-middle"><input type="checkbox" class="sel" name="sids[]" value="<?php echo h($row['student_id']); ?>"></td>
                  <td class="text-muted align-middle"><?php echo ++$i; ?></td>
                  <td class="align-middle"><?php echo h($row['student_id']); ?></td>
                  <td class="align-middle"><?php echo h($row['student_fullname']); ?></td>
                  <td class="align-middle d-none d-md-table-cell">
                    <?php $ae = isset($row['allowance_eligible']) ? (int)$row['allowance_eligible'] : 0; ?>
                    <span class="badge badge-<?php echo $ae ? 'success' : 'secondary'; ?>"><?php echo $ae ? 'Eligible' : 'Not Eligible'; ?></span>
                  </td>
                  <td class="align-middle d-none d-md-table-cell">
                    <?php $sid = $row['student_id']; $pct = isset($percentMap[$sid]) ? (int)$percentMap[$sid] : 0; ?>
                    <span class="badge badge-<?php echo ($pct>=80?'success':($pct>=60?'warning':'secondary')); ?>"><?php echo $pct; ?>%</span>
                  </td>
                  <td class="align-middle d-none d-md-table-cell"><?php echo h($row['course_name'] ?? ''); ?></td>
                  <td class="align-middle d-none d-lg-table-cell"><?php echo h($row['department_name'] ?? ''); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted">No students found for the selected filters.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
