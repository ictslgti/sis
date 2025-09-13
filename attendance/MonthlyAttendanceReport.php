<?php if (ob_get_level() === 0) { ob_start(); } ?>
<!--Block#1 start dont change the order-->
<?php 
$title="Monthly Attendance Report | SLGTI";    
include_once ("../config.php");
// Allow HODs, IN3, SAO, and ADM to use this page
require_roles(['HOD','IN3','SAO','ADM']);
?>
<!-- end dont change the order-->
<?php
$isExport = isset($_GET['export']) && in_array($_GET['export'], ['csv','1','xls'], true);

// Include layout only when not exporting (avoid any output before headers)
if (!$isExport) {
  include_once ("../head.php");
  include_once ("../menu.php");
  
  $__isADM = (isset($_SESSION['user_type']) && $_SESSION['user_type']==='ADM');
  // Scoped styles for better alignment
  echo '<style>
    .att-filter .form-group { margin-right: .75rem; }
    .table-responsive { overflow-x: auto; position: relative; }
    .att-report { table-layout: fixed; font-size: 0.92rem; border-collapse: separate; border-spacing: 0; }
    .att-report th, .att-report td { vertical-align: middle; box-sizing: border-box; padding: .35rem .4rem; }
    /* Sticky header */
    .att-report thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 10; text-align: center; white-space: nowrap; }
    /* Sticky first two columns for both head and body */
    .att-report .id-col { width: 160px; white-space: nowrap; position: sticky; left: 0; z-index: 12; background: #fff; text-align: left; border-right: 1px solid #dee2e6; overflow: hidden; text-overflow: ellipsis; padding-right: .5rem; }
    .att-report .name-col { min-width: 260px; white-space: nowrap; position: sticky; left: 160px; z-index: 12; background: #fff; text-align: left; border-right: 1px solid #dee2e6; overflow: hidden; text-overflow: ellipsis; padding-right: .5rem; }
    .att-report .nic-col { width: 170px; white-space: nowrap; background: #fff; text-align: left; border-right: 1px solid #dee2e6; overflow: hidden; text-overflow: ellipsis; padding-right: .5rem; }
    .att-report thead .id-col, .att-report thead .name-col { z-index: 13; background: #fff; }
    /* Visual separator for sticky columns */
    .att-report .id-col { box-shadow: 2px 0 0 rgba(0,0,0,.05); }
    .att-report .name-col { box-shadow: 2px 0 0 rgba(0,0,0,.05); }
    /* Day and numeric columns */
    .att-report .day-col { width: 30px; text-align: center; white-space: nowrap; border-left: 1px solid #dee2e6; }
    .att-report .num-col { width: 70px; text-align: center; white-space: nowrap; }
    /* Vertical label for non-considered dates */
    .att-report .nc-col { writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg); white-space: nowrap; text-align: center; padding: .2rem .15rem; min-width: 22px; font-size: .75rem; }
  </style>';
}

// Resolve department
$deptCode = '';
$canPickDept = in_array($_SESSION['user_type'], ['SAO','ADM'], true);
if ($canPickDept) {
  $deptCode = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
} else {
  $deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
  if ($deptCode === '' && !empty($_SESSION['user_name'])) {
    $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
    $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
    if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
  }
}

// Filters
$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$course = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
// Default to detailed (month all days)
$view = (isset($_GET['view']) && in_array($_GET['view'], ['summary','detailed'], true)) ? $_GET['view'] : 'detailed';

// Compute month range
$firstDay = $month.'-01';
$lastDay = date('Y-m-t', strtotime($firstDay));
$daysInMonth = (int)date('t', strtotime($firstDay));
$dayDates = [];
for ($d=1; $d<=$daysInMonth; $d++) {
  $dayDates[$d] = date('Y-m-d', strtotime($month.'-'.str_pad($d,2,'0',STR_PAD_LEFT)));
}

// Optional focus date to explain how it is treated
$focusDate = isset($_GET['focus_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['focus_date']) ? $_GET['focus_date'] : '';
if ($focusDate !== '') {
  // If date is outside selected month, ignore for clarity
  if (substr($focusDate,0,7) !== $month) { $focusDate = ''; }
}

// Build set of Sri Lanka public holidays for the month if a table exists
function load_holidays_set($con, $firstDay, $lastDay) {
  $set = [];
  // Try common table names and column names
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

// Optional: load institute vacation days for the month (if a table exists)
function load_vacations_set($con, $firstDay, $lastDay) {
  $set = [];
  // Candidate schemas: either a list of specific dates or date ranges
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
  // Range-based candidates
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

$holidaySet = load_holidays_set($con, $firstDay, $lastDay);
$vacationSet = load_vacations_set($con, $firstDay, $lastDay);

// Detect exceptional days (classes conducted on non-working days) via attendance present in the month
$exceptionalSet = [];
$exq = mysqli_query($con, "SELECT DISTINCT date AS d FROM attendance WHERE date BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
if ($exq) { while($row=mysqli_fetch_assoc($exq)){ if (!empty($row['d'])) { $exceptionalSet[$row['d']] = true; } } }

// Dates explicitly marked as NWD via attendance_status = -1 (override to NOT count)
$nwdOverrideSet = [];
$nq = mysqli_query($con, "SELECT DISTINCT date AS d FROM attendance WHERE attendance_status=-1 AND date BETWEEN '".mysqli_real_escape_string($con,$firstDay)."' AND '".mysqli_real_escape_string($con,$lastDay)."'");
if ($nq) { while($row=mysqli_fetch_assoc($nq)){ if (!empty($row['d'])) { $nwdOverrideSet[$row['d']] = true; } } }

// Classify days in the month and compute considered working days
$workDayDates = [];
$countMonthDays = $daysInMonth;
$countWeekends = 0;
$countHolidays = 0;
$countVacations = 0;
$countWorking = 0;
$countExceptions = 0; // weekend days that were conducted and COUNTED
$countNWDOverrides = 0; // days forced to NOT count via -1
foreach ($dayDates as $idx=>$dstr) {
  $w = (int)date('w', strtotime($dstr)); // 0=Sun,6=Sat
  // NWD override (never counted)
  if (isset($nwdOverrideSet[$dstr])) { $countNWDOverrides++; continue; }
  // Weekend: count ONLY if attendance exists (exception weekend day)
  if ($w === 0 || $w === 6) {
    if (isset($exceptionalSet[$dstr])) { $workDayDates[$idx] = $dstr; $countWorking++; $countExceptions++; }
    else { $countWeekends++; }
    continue;
  }
  // Holiday (never counted in totals)
  if (isset($holidaySet[$dstr])) { $countHolidays++; continue; }
  // Vacation (never counted in totals)
  if (isset($vacationSet[$dstr])) { $countVacations++; continue; }
  // Working day
  $workDayDates[$idx] = $dstr;
  $countWorking++;
}

// Note: holidays/vacations with attendance are NOT counted and not added to $workDayDates
// Note: dates with attendance_status=-1 (NWD override) are also NOT counted.

// Build explicit lists of excluded dates for holidays and vacations (not considered), regardless of attendance
$holidayExcludedDates = [];
$vacationExcludedDates = [];
foreach ($dayDates as $i => $d) {
  if (isset($holidaySet[$d])) { $holidayExcludedDates[] = $d; }
  if (isset($vacationSet[$d])) { $vacationExcludedDates[] = $d; }
}

// Build NWD override lists by type (holiday vs vacation) for display
$nwdHolidayDates = [];
$nwdVacationDates = [];
$nwdOtherDates = [];
foreach ($nwdOverrideSet as $d => $_v) {
  if (isset($holidaySet[$d])) { $nwdHolidayDates[] = $d; }
  else if (isset($vacationSet[$d])) { $nwdVacationDates[] = $d; }
  else { $nwdOtherDates[] = $d; }
}

// Prepare focus date explanation
$focusExplain = '';
if ($focusDate !== '') {
  $fw = (int)date('w', strtotime($focusDate));
  $isWeekendFD = ($fw===0 || $fw===6);
  $isHolidayFD = isset($holidaySet[$focusDate]);
  $isVacationFD = isset($vacationSet[$focusDate]);
  $isNWDOverrideFD = isset($nwdOverrideSet[$focusDate]);
  $hasAttendanceFD = isset($exceptionalSet[$focusDate]);
  $typeParts = [];
  if ($isWeekendFD) { $typeParts[] = 'Weekend'; }
  if ($isHolidayFD) { $typeParts[] = 'Holiday'; }
  if ($isVacationFD) { $typeParts[] = 'Vacation'; }
  if ($isNWDOverrideFD) { $typeParts[] = 'NWD Override'; }
  if (empty($typeParts)) { $typeParts[] = 'Working Day'; }
  $countedFD = false;
  if ($isNWDOverrideFD || $isHolidayFD || $isVacationFD) { $countedFD = false; }
  else if ($isWeekendFD) { $countedFD = $hasAttendanceFD; }
  else { $countedFD = true; }
  $effect = $countedFD ? '0 days' : '-1 day';
  $focusExplain = $focusDate.' — '.implode(', ', $typeParts).'. Counted in totals: '.($countedFD ? 'Yes' : 'No')." (Effect on Considered Days: $effect)";
}

// Load departments (for SAO/ADM picker)
$departments = [];
if ($canPickDept) {
  $dqAll = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
  if ($dqAll) { while($row=mysqli_fetch_assoc($dqAll)){ $departments[]=$row; } }
}

// Load courses for current department
$courses = [];
if ($deptCode !== '') {
  $dq = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con,$deptCode)."' ORDER BY course_name");
  if ($dq) { while($row=mysqli_fetch_assoc($dq)){ $courses[]=$row; } }
}

// Load students in scope
$students = [];
if ($deptCode !== '') {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
  if ($course !== '') {
    $where .= " AND c.course_id='".mysqli_real_escape_string($con,$course)."'";
  }
  $sql = "SELECT s.student_id, s.student_fullname, s.student_nic, se.course_id, c.course_name
          FROM student_enroll se
          JOIN course c ON c.course_id = se.course_id
          JOIN student s ON s.student_id = se.student_id
          $where
          ORDER BY s.student_id ASC";
  $res = mysqli_query($con, $sql);
  if ($res) { while($r=mysqli_fetch_assoc($res)){ $students[$r['student_id']]=$r; } }
}

$results = [];
$totalDays = 0;
if (!empty($students)) {
  // Build IN list
  $ids = [];
  foreach ($students as $sid => $info) {
    $ids[] = "'".mysqli_real_escape_string($con, $sid)."'";
  }
  $idList = implode(',', $ids);

    // Total considered working days in month
  $totalDays = $countWorking;

  // Per-student present days using max per date (support legacy and new module names)
  // Restrict attendance queries to working dates
  $dateList = implode(',', array_map(function($d){ return "'".addslashes($d)."'"; }, array_values($workDayDates)));
  if ($dateList === '') { $dateList = "''"; }
  $q = mysqli_query($con, "SELECT a.student_id, COUNT(*) AS present_days FROM (SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE date IN (".$dateList.") AND student_id IN ($idList) GROUP BY student_id, date) a WHERE a.st=1 GROUP BY a.student_id");
  $presentMap = [];
  if ($q) { while($r=mysqli_fetch_assoc($q)){ $presentMap[$r['student_id']] = (int)$r['present_days']; } }

  // Build per-day attendance map for detailed view (P/A per date)
  $attByStudent = [];
  $q2 = mysqli_query($con, "SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE date IN (".$dateList.") AND student_id IN ($idList) GROUP BY student_id, date");
  if ($q2) { while($r=mysqli_fetch_assoc($q2)){ $attByStudent[$r['student_id']][$r['date']] = (int)$r['st']; } }

  foreach ($students as $sid=>$info) {
    $pd = isset($presentMap[$sid]) ? $presentMap[$sid] : 0;
    $pct = $totalDays>0 ? round(($pd/$totalDays)*100, 2) : 0.0;
    $results[] = [
      'student_id' => $sid,
      'student_fullname' => $info['student_fullname'],
      'course_id' => $info['course_id'],
      'course_name' => $info['course_name'],
      'present_days' => $pd,
      'total_days' => $totalDays,
      'percentage' => $pct,
    ];
  }
}

if ($isExport) {
  // Build HTML content first
  $html = "<table border='1'>";
  if ($view === 'detailed') {
    // Pre-header breakdown and excluded dates
    $html .= "<tr><th colspan='".(3 + count($workDayDates) + 4)."' style='text-align:left'>".
             "Department: ".htmlspecialchars($deptCode)." | Month: ".htmlspecialchars($month)." | Considered Days: ".(int)$totalDays.
             " (Month Days: ".$countMonthDays.", Weekends: ".$countWeekends.", Holidays: ".$countHolidays.", Vacations: ".$countVacations.", Exceptions counted (Sat/Sun): ".$countExceptions.")".
             "</th></tr>";
    $html .= "<tr><td colspan='".(3 + count($workDayDates) + 4)."' style='text-align:left'>".
             "Excluded Holiday Dates: ".(!empty($holidayExcludedDates) ? htmlspecialchars(implode(', ', $holidayExcludedDates)) : 'None').
             " | Excluded Vacation Dates: ".(!empty($vacationExcludedDates) ? htmlspecialchars(implode(', ', $vacationExcludedDates)) : 'None').
             "</td></tr>";
    $html .= "<tr><td colspan='".(3 + count($workDayDates) + 4)."'>&nbsp;</td></tr>";
    // Header row with working days (exclude weekends/holidays/vacations unless exception)
    $html .= "<tr><th>Student ID</th><th>Student Name</th><th>NIC</th>";
    foreach ($workDayDates as $idx=>$dstr) {
      $isExc = isset($exceptionalSet[$dstr]) && in_array((int)date('w', strtotime($dstr)), [0,6], true);
      $lbl = (int)$idx . ($isExc ? '*' : '');
      $title = $isExc ? " title=\"".htmlspecialchars($dstr)."\"" : '';
      $html .= "<th$title>".$lbl."</th>";
    }
    $html .= "<th>Present</th><th>Considered Days</th><th>%</th><th>Allowance</th></tr>";
    if (!empty($results)) {
      foreach ($results as $row) {
        $sid = $row['student_id'];
        $html .= "<tr>";
        $html .= "<td>".htmlspecialchars($sid)."</td>";
        $html .= "<td>".htmlspecialchars(isset($students[$sid]['student_fullname'])?$students[$sid]['student_fullname']:'')."</td>";
        $html .= "<td>".htmlspecialchars(isset($students[$sid]['student_nic'])?$students[$sid]['student_nic']:'')."</td>";
        foreach ($workDayDates as $idx=>$dateKey) {
          $mark = isset($attByStudent[$sid][$dateKey]) ? ($attByStudent[$sid][$dateKey] ? 'P' : 'A') : '-';
          $html .= "<td>".$mark."</td>";
        }
        $html .= "<td>".(int)$row['present_days']."</td>";
        $html .= "<td>".(int)$row['total_days']."</td>";
        $html .= "<td>".number_format($row['percentage'], 2)."%</td>";
        $allow = ($row['percentage']>=90?5000:($row['percentage']>=70?4000:0));
        $html .= "<td>LKR ".number_format($allow,0)."</td>";
        $html .= "</tr>";
      }
    } else {
      $html .= "<tr><td colspan='".(3 + count($workDayDates) + 4)."'>No data available</td></tr>";
    }
  } else {
    // Pre-header breakdown and excluded dates
    $html .= "<tr><th colspan='6' style='text-align:left'>".
             "Department: ".htmlspecialchars($deptCode)." | Month: ".htmlspecialchars($month)." | Considered Days: ".(int)$totalDays.
             " (Month Days: ".$countMonthDays.", Weekends: ".$countWeekends.", Holidays: ".$countHolidays.", Vacations: ".$countVacations.", Exceptions counted (Sat/Sun): ".$countExceptions.")".
             "</th></tr>";
    $html .= "<tr><td colspan='6' style='text-align:left'>".
             "Excluded Holiday Dates: ".(!empty($holidayExcludedDates) ? htmlspecialchars(implode(', ', $holidayExcludedDates)) : 'None').
             " | Excluded Vacation Dates: ".(!empty($vacationExcludedDates) ? htmlspecialchars(implode(', ', $vacationExcludedDates)) : 'None').
             "</td></tr>";
    $html .= "<tr><td colspan='6'>&nbsp;</td></tr>";
    // Summary header
    $html .= "<tr>
      <th>Student ID</th>
      <th>Student Name</th>
      <th>Present Days</th>
      <th>Considered Days</th>
      <th>Percentage</th>
      <th>Allowance</th>
    </tr>";
    // Summary rows
    if (!empty($results)) {
      foreach ($results as $row) {
        $html .= "<tr>";
        $html .= "<td>" . htmlspecialchars($row['student_id']) . "</td>";
        $html .= "<td>" . htmlspecialchars($row['student_fullname']) . "</td>";
        $html .= "<td>" . (int)$row['present_days'] . "</td>";
        $html .= "<td>" . (int)$row['total_days'] . "</td>";
        $html .= "<td>" . number_format($row['percentage'], 2) . "%</td>";
        $allow = ($row['percentage']>=90?5000:($row['percentage']>=70?4000:0));
        $html .= "<td>LKR ".number_format($allow,0)."</td>";
        $html .= "</tr>";
      }
    } else {
      $html .= "<tr><td colspan='6'>No data available</td></tr>";
    }
  }
  $html .= "</table>";

  // Disable compression to allow Content-Length and download reliability
  if (function_exists('ini_set')) { @ini_set('zlib.output_compression', 'Off'); }

  // Clear any previous output
  while (ob_get_level() > 0) { ob_end_clean(); }

  // Set headers and send content
  $filename = 'monthly_attendance_' . date('Y-m-d') . '.xls';
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Transfer-Encoding: binary');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: public');
  header('X-Content-Type-Options: nosniff');
  header('Content-Length: ' . strlen($html));
  echo $html;
  flush();
  exit();
}
?>

<div class="container<?php echo $__isADM ? '' : ' hod-desktop-offset'; ?>" style="margin-top:30px">
<?php include_once ("Attendancenav.php"); ?>
  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
      <div class="mb-2 mb-md-0">
        <p class="mb-0">
          Monthly Attendance Report
          <small class="text-muted">(<?php echo htmlspecialchars($deptCode ?: 'All'); ?>)</small>
</p>
      </div>
      <div class="w-100">
        <form method="get" action="">
          <div class="form-row align-items-end">
            <?php if ($canPickDept): ?>
            <div class="col-12 col-md-4 mb-2">
              <label for="department_id" class="small mb-1 text-muted">Department</label>
              <select id="department_id" name="department_id" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">-- Select --</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?php echo htmlspecialchars($d['department_id']); ?>" <?php echo ($deptCode===$d['department_id'])?'selected':''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-12 col-md-4 mb-2">
              <label for="course_id" class="small mb-1 text-muted">Course</label>
              <select id="course_id" name="course_id" class="form-control form-control-sm" <?php echo $deptCode? '':'disabled'; ?> onchange="this.form.submit()">
                <option value="">-- All --</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($course===$c['course_id'])?'selected':''; ?>><?php echo htmlspecialchars($c['course_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-3 mb-2">
              <label for="month" class="small mb-1 text-muted">Month</label>
              <input type="month" id="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($month); ?>" required>
            </div>
            <div class="col-6 col-md-1 mb-2 d-flex justify-content-md-end">
              <div class="btn-group btn-group-sm ml-md-auto" role="group">
                <input type="hidden" name="view" value="detailed">
                <button class="btn btn-primary"><i class="fas fa-sync-alt mr-1"></i></button>
                <a href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php?export=1&view=detailed&month=<?php echo urlencode($month); ?><?php echo $deptCode?('&department_id='.urlencode($deptCode)) : ''; ?><?php echo $course?('&course_id='.urlencode($course)) : ''; ?>" class="btn btn-success" id="exportBtn" title="Export to Excel">
                  <i class="fas fa-file-excel mr-1"></i>
                </a>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="card-body">
      <?php if ($deptCode===''): ?>
        <div class="alert alert-warning">Department not configured for your account. Please contact admin.</div>
      <?php else: ?>
        <?php if (isset($_GET['ok'])): ?>
          <div class="alert alert-success py-2">Operation completed successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['err'])): ?>
          <div class="alert alert-danger py-2">
            Operation failed. Code: <?php echo htmlspecialchars($_GET['err']); ?>
            <?php if (!empty($_GET['errm'])): ?>
              <div class="small mt-1"><strong>Details:</strong> <?php echo htmlspecialchars($_GET['errm']); ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="mb-2">
          <strong>Department:</strong> <?php echo htmlspecialchars($deptCode); ?> |
          <strong>Month:</strong> <?php echo htmlspecialchars($month); ?> |
          <strong>Considered Days:</strong> <?php echo (int)$totalDays; ?>
          <span class="text-muted ml-2">(Month Days: <?php echo (int)$countMonthDays; ?>, Weekends: <?php echo (int)$countWeekends; ?>, Exceptions counted (Sat/Sun): <?php echo (int)$countExceptions; ?>, NWD overrides: <?php echo (int)$countNWDOverrides; ?>)</span>
        </div>
        
        <div class="alert alert-warning py-2 small mb-2">
          <strong class="mr-2">NWD overrides:</strong>
          <span class="mr-3"><strong>Holiday:</strong> <?php echo !empty($nwdHolidayDates) ? htmlspecialchars(implode(', ', $nwdHolidayDates)) : 'None'; ?></span>
          <span class="mr-3"><strong>Vacation:</strong> <?php echo !empty($nwdVacationDates) ? htmlspecialchars(implode(', ', $nwdVacationDates)) : 'None'; ?></span>
          <?php if (!empty($nwdOtherDates)): ?>
            <span class="mr-3"><strong>Other:</strong> <?php echo htmlspecialchars(implode(', ', $nwdOtherDates)); ?></span>
          <?php endif; ?>
        </div>
        <?php if ($focusExplain !== ''): ?>
          <div class="text-info small mb-2"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($focusExplain); ?></div>
        <?php endif; ?>
        <?php if ($focusDate !== ''): ?>
          <?php if (isset($nwdOverrideSet[$focusDate])): ?>
            <form method="post" action="<?php echo APP_BASE; ?>/controller/RemoveNWDOverride.php" class="mb-2">
              <input type="hidden" name="date" value="<?php echo htmlspecialchars($focusDate); ?>">
              <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($deptCode); ?>">
              <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course); ?>">
              <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Remove NWD override for '+document.getElementById('focus_date').value+' for the selected scope?');">
                <i class="fas fa-undo"></i> Remove NWD override (focus date)
              </button>
            </form>
          <?php else: ?>
            <form method="post" action="<?php echo APP_BASE; ?>/controller/SetNWDOverride.php" class="mb-2">
              <input type="hidden" name="date" value="<?php echo htmlspecialchars($focusDate); ?>">
              <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($deptCode); ?>">
              <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course); ?>">
              <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Mark '+document.getElementById('focus_date').value+' as Non-Working (attendance_status = -1) for the selected scope?');">
                <i class="fas fa-ban"></i> Mark Focus date as NWD (-1)
              </button>
            </form>
          <?php endif; ?>
        <?php endif; ?>

        <div class="mb-3 p-2 border rounded bg-light">
          <div class="d-flex flex-wrap align-items-center">
            <label class="mr-2 font-weight-bold mb-0">NWD Overrides:</label>
            <input type="date" id="nwd_date_any" class="form-control form-control-sm mr-2" value="<?php echo htmlspecialchars($focusDate ?: $firstDay); ?>" style="max-width: 180px;">
            <form method="post" action="<?php echo APP_BASE; ?>/controller/SetNWDOverride.php" class="mr-2 mb-0" onsubmit="document.getElementById('nwd_set_date').value=document.getElementById('nwd_date_any').value; return confirm('Mark '+document.getElementById('nwd_date_any').value+' as NWD (-1) for the selected scope?');">
              <input type="hidden" id="nwd_set_date" name="date" value="">
              <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($deptCode); ?>">
              <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course); ?>">
              <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban mr-1"></i>Add NWD override</button>
            </form>
            <form method="post" action="<?php echo APP_BASE; ?>/controller/RemoveNWDOverride.php" class="mb-0" onsubmit="document.getElementById('nwd_remove_date').value=document.getElementById('nwd_date_any').value; return confirm('Remove NWD override for '+document.getElementById('nwd_date_any').value+'?');">
              <input type="hidden" id="nwd_remove_date" name="date" value="">
              <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($deptCode); ?>">
              <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course); ?>">
              <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo mr-1"></i>Remove NWD override</button>
            </form>
          </div>
        </div>
        <div class="table-responsive">
          <?php if ($view === 'detailed'): ?>
            <?php
              // Build list of dates to display as columns: considered working days + NWD overrides
              $visibleDates = [];
              foreach ($dayDates as $idx=>$dstr) {
                if (isset($workDayDates[$idx]) || isset($nwdOverrideSet[$dstr])) {
                  $visibleDates[$idx] = $dstr;
                }
              }
            ?>
            <table class="table table-sm table-bordered table-hover att-report">
              <thead class="thead-light">
                <tr>
                  <th class="id-col">Student ID</th>
                  <th class="name-col">Name</th>
                  <th class="nic-col">NIC</th>
                  <?php foreach ($visibleDates as $idx=>$dstr): $w = (int)date('w', strtotime($dstr)); $isExc = isset($exceptionalSet[$dstr]) && ($w===0 || $w===6); ?>
                    <th class="day-col <?php echo $isExc ? 'table-warning' : ''; ?>" title="<?php echo htmlspecialchars($dstr); ?>"><?php echo (int)$idx; ?></th>
                  <?php endforeach; ?>
                  <th class="num-col">Present</th>
                  <th class="num-col">Considered</th>
                  <th class="num-col">%</th>
                  <th class="num-col">Allowance</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($results)): $__rowspan = count($results); $__printedFor = []; ?>
                  <?php foreach ($results as $r): $sid=$r['student_id']; ?>
                    <tr>
                      <td class="id-col"><?php echo htmlspecialchars($sid); ?></td>
                      <td class="name-col"><?php echo htmlspecialchars(isset($students[$sid]['student_fullname'])?$students[$sid]['student_fullname']:''); ?></td>
                      <td class="nic-col"><?php echo htmlspecialchars(isset($students[$sid]['student_nic'])?$students[$sid]['student_nic']:''); ?></td>
                      <?php foreach ($visibleDates as $idx=>$dateKey): ?>
                        <?php
                          $w = (int)date('w', strtotime($dateKey));
                          $isHoliday = isset($holidaySet[$dateKey]);
                          $isVacation = isset($vacationSet[$dateKey]);
                          $isNWD = isset($nwdOverrideSet[$dateKey]);
                          $isWeekend = ($w===0 || $w===6);
                          // In visibleDates, either considered or NWD. Considered excludes holidays/vacations/NWD and includes weekend only if exception
                          $isConsidered = isset($workDayDates[$idx]);
                        ?>
                        <?php if ($isConsidered): ?>
                          <?php $mark = isset($attByStudent[$sid][$dateKey]) ? ($attByStudent[$sid][$dateKey] ? 'P' : 'A') : '-'; ?>
                          <td class="day-col <?php echo $mark==='P'?'table-success':($mark==='A'?'table-danger':''); ?>"><?php echo $mark; ?></td>
                        <?php else: ?>
                          <?php if (!isset($__printedFor[$dateKey])): $__printedFor[$dateKey]=true; ?>
                            <td class="day-col nc-col table-warning" rowspan="<?php echo (int)$__rowspan; ?>" title="<?php echo htmlspecialchars($dateKey); ?>">Holiday or Vacation</td>
                          <?php endif; ?>
                        <?php endif; ?>
                      <?php endforeach; ?>
                      <td class="num-col"><?php echo (int)$r['present_days']; ?></td>
                      <td class="num-col"><?php echo (int)$r['total_days']; ?></td>
                      <td class="num-col"><?php echo number_format($r['percentage'], 2); ?></td>
                      <?php $allow = ($r['percentage']>=90?5000:($r['percentage']>=70?4000:0)); ?>
                      <td class="num-col">LKR <?php echo number_format($allow,0); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="<?php echo 1 + count($visibleDates) + 4; ?>" class="text-center text-muted">No data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          <?php else: ?>
            <table class="table table-striped table-hover att-report">
              <thead class="thead-light">
                <tr>
                  <th class="id-col">Student ID</th>
                  <th class="num-col">Present Days</th>
                  <th class="num-col">Considered Days</th>
                  <th class="num-col">%</th>
                  <th class="num-col">Allowance</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($results)): ?>
                  <?php foreach ($results as $r): ?>
                    <tr>
                      <td class="id-col"><?php echo htmlspecialchars($r['student_id']); ?></td>
                      <td class="num-col"><?php echo (int)$r['present_days']; ?></td>
                      <td class="num-col"><?php echo (int)$r['total_days']; ?></td>
                      <td class="num-col"><?php echo number_format($r['percentage'], 2); ?></td>
                      <?php $allow = ($r['percentage']>=90?5000:($r['percentage']>=70?4000:0)); ?>
                      <td class="num-col">LKR <?php echo number_format($allow,0); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
