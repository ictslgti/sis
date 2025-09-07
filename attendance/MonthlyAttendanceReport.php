<?php if (ob_get_level() === 0) { ob_start(); } ?>
<!--Block#1 start dont change the order-->
<?php 
$title="Monthly Attendance Report | SLGTI";    
include_once ("../config.php");
// Allow HODs and SAO (and ADM) to use this page
require_roles(['HOD','SAO','ADM']);
?>
<!-- end dont change the order-->
<?php
$isExport = isset($_GET['export']) && in_array($_GET['export'], ['csv','1','xls'], true);

// Include layout only when not exporting (avoid any output before headers)
if (!$isExport) {
  include_once ("../head.php");
  include_once ("../menu.php");
  include_once ("Attendancenav.php");
  // Scoped styles for better alignment
  echo '<style>
    .att-filter .form-group { margin-right: .75rem; }
    .table-responsive { overflow-x: auto; position: relative; }
    .att-report { table-layout: fixed; font-size: 0.92rem; border-collapse: separate; border-spacing: 0; }
    .att-report th, .att-report td { vertical-align: middle; box-sizing: border-box; }
    /* Sticky header */
    .att-report thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 10; text-align: center; white-space: nowrap; }
    /* Sticky first two columns for both head and body */
    .att-report .id-col { width: 160px; white-space: nowrap; position: sticky; left: 0; z-index: 12; background: #fff; text-align: left; border-right: 1px solid #dee2e6; overflow: hidden; text-overflow: ellipsis; padding-right: .5rem; }
    .att-report .name-col { min-width: 320px; white-space: nowrap; position: sticky; left: 160px; z-index: 12; background: #fff; text-align: left; border-right: 1px solid #dee2e6; overflow: hidden; text-overflow: ellipsis; padding-right: .5rem; }
    .att-report thead .id-col, .att-report thead .name-col { z-index: 13; background: #fff; }
    /* Visual separator for sticky columns */
    .att-report .id-col { box-shadow: 2px 0 0 rgba(0,0,0,.05); }
    .att-report .name-col { box-shadow: 2px 0 0 rgba(0,0,0,.05); }
    /* Day and numeric columns */
    .att-report .day-col { width: 34px; padding: .3rem .4rem; text-align: center; white-space: nowrap; border-left: 1px solid #dee2e6; }
    .att-report .num-col { width: 72px; text-align: right; white-space: nowrap; }
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

$holidaySet = load_holidays_set($con, $firstDay, $lastDay);

// Working days (exclude Saturday, Sunday, and holidays if available)
$workDayDates = [];
foreach ($dayDates as $idx=>$dstr) {
  $w = (int)date('w', strtotime($dstr)); // 0=Sun,6=Sat
  if ($w === 0 || $w === 6) { continue; }
  if (isset($holidaySet[$dstr])) { continue; }
  $workDayDates[$idx] = $dstr;
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
  $sql = "SELECT s.student_id, s.student_fullname, se.course_id, c.course_name
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

    // Total working days in month (exclude weekends/holidays)
  $totalDays = count($workDayDates);

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
    // Header row with working days (exclude weekends/holidays) and no Name column
    $html .= "<tr><th>Student ID</th>";
    foreach ($workDayDates as $idx=>$dstr) { $html .= "<th>".(int)$idx."</th>"; }
    $html .= "<th>Present</th><th>Total</th><th>%</th></tr>";
    if (!empty($results)) {
      foreach ($results as $row) {
        $sid = $row['student_id'];
        $html .= "<tr>";
        $html .= "<td>".htmlspecialchars($sid)."</td>";
        foreach ($workDayDates as $idx=>$dateKey) {
          $mark = isset($attByStudent[$sid][$dateKey]) ? ($attByStudent[$sid][$dateKey] ? 'P' : 'A') : '-';
          $html .= "<td>".$mark."</td>";
        }
        $html .= "<td>".(int)$row['present_days']."</td>";
        $html .= "<td>".(int)$row['total_days']."</td>";
        $html .= "<td>".number_format($row['percentage'], 2)."%</td>";
        $html .= "</tr>";
      }
    } else {
      $html .= "<tr><td colspan='".($daysInMonth+5)."'>No data available</td></tr>";
    }
  } else {
    // Summary header
    $html .= "<tr>
      <th>Student ID</th>
      <th>Student Name</th>
      <th>Present Days</th>
      <th>Total Days</th>
      <th>Percentage</th>
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
        $html .= "</tr>";
      }
    } else {
      $html .= "<tr><td colspan='5'>No data available</td></tr>";
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

<div class="container" style="margin-top:30px">
  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
      <div class="mb-2 mb-md-0">
        <h5 class="mb-0">
          <i class="fas fa-calendar-alt mr-2"></i>Monthly Attendance Report
          <small class="text-muted">(<?php echo htmlspecialchars($deptCode ?: 'All'); ?>)</small>
        </h5>
      </div>
      <div class="d-flex flex-column flex-md-row">
        <form class="form-inline mb-2 mb-md-0 att-filter" method="get" action="">
          <?php if ($canPickDept): ?>
          <div class="form-group mb-2 mr-2">
            <label for="department_id" class="mr-2">Department:</label>
            <select id="department_id" name="department_id" class="form-control form-control-sm" onchange="this.form.submit()">
              <option value="">-- Select --</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo htmlspecialchars($d['department_id']); ?>" <?php echo ($deptCode===$d['department_id'])?'selected':''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="form-group mb-2 mr-2">
            <label for="course_id" class="mr-2">Course:</label>
            <select id="course_id" name="course_id" class="form-control form-control-sm" <?php echo $deptCode? '':'disabled'; ?> onchange="this.form.submit()">
              <option value="">-- All --</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo ($course===$c['course_id'])?'selected':''; ?>><?php echo htmlspecialchars($c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2 mr-2">
            <label for="month" class="mr-2">Month:</label>
            <input type="month" id="month" name="month" class="form-control form-control-sm" value="<?php echo htmlspecialchars($month); ?>" required>
          </div>
          <input type="hidden" name="view" value="detailed">
          <button class="btn btn-primary btn-sm"><i class="fas fa-sync-alt mr-1"></i> Generate</button>
          <a href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php?export=1&view=detailed&month=<?php echo urlencode($month); ?><?php echo $deptCode?('&department_id='.urlencode($deptCode)) : ''; ?><?php echo $course?('&course_id='.urlencode($course)) : ''; ?>" class="btn btn-success btn-sm ml-1" id="exportBtn">
            <i class="fas fa-file-excel mr-1"></i> Export to Excel
          </a>
        </form>
      </div>
    </div>
    <div class="card-body">
      <?php if ($deptCode===''): ?>
        <div class="alert alert-warning">Department not configured for your account. Please contact admin.</div>
      <?php else: ?>
        <div class="mb-2"><strong>Department:</strong> <?php echo htmlspecialchars($deptCode); ?> | <strong>Month:</strong> <?php echo htmlspecialchars($month); ?> | <strong>Total Marked Days:</strong> <?php echo (int)$totalDays; ?></div>
        <div class="table-responsive">
          <?php if ($view === 'detailed'): ?>
            <table class="table table-sm table-bordered table-hover att-report">
              <thead class="thead-light">
                <tr>
                  <th class="id-col">Student ID</th>
                  <?php foreach ($workDayDates as $idx=>$dstr): ?>
                    <th class="day-col"><?php echo (int)$idx; ?></th>
                  <?php endforeach; ?>
                  <th class="num-col">Present</th>
                  <th class="num-col">Total</th>
                  <th class="num-col">%</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($results)): ?>
                  <?php foreach ($results as $r): $sid=$r['student_id']; ?>
                    <tr>
                      <td class="id-col"><?php echo htmlspecialchars($sid); ?></td>
                      <?php foreach ($workDayDates as $idx=>$dateKey): $mark = isset($attByStudent[$sid][$dateKey]) ? ($attByStudent[$sid][$dateKey] ? 'P' : 'A') : '-'; ?>
                        <td class="day-col <?php echo $mark==='P'?'table-success':($mark==='A'?'table-danger':''); ?>"><?php echo $mark; ?></td>
                      <?php endforeach; ?>
                      <td class="num-col"><?php echo (int)$r['present_days']; ?></td>
                      <td class="num-col"><?php echo (int)$r['total_days']; ?></td>
                      <td class="num-col"><?php echo number_format($r['percentage'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="<?php echo 1 + count($workDayDates) + 3; ?>" class="text-center text-muted">No data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          <?php else: ?>
            <table class="table table-striped table-hover att-report">
              <thead class="thead-light">
                <tr>
                  <th class="id-col">Student ID</th>
                  <th class="num-col">Present Days</th>
                  <th class="num-col">Total Days</th>
                  <th class="num-col">%</th>
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
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
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
