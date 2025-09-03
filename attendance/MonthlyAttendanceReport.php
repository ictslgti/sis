<?php if (ob_get_level() === 0) { ob_start(); } ?>
<!--Block#1 start dont change the order-->
<?php 
$title="Monthly Attendance Report | SLGTI";    
include_once ("../config.php");
// Only HODs can use this page
require_roles(['HOD']);
?>
<!-- end dont change the order-->
<?php
$isExport = isset($_GET['export']) && in_array($_GET['export'], ['csv','1','xls'], true);

// Include layout only when not exporting (avoid any output before headers)
if (!$isExport) {
  include_once ("../head.php");
  include_once ("../menu.php");
  include_once ("Attendancenav.php");
}

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

// Filters
$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
// Course not needed; always include all courses within department
$course = '';
// View mode: summary (default) or detailed
$view = (isset($_GET['view']) && $_GET['view']==='detailed') ? 'detailed' : 'summary';

// Compute month range
$firstDay = $month.'-01';
$lastDay = date('Y-m-t', strtotime($firstDay));
$daysInMonth = (int)date('t', strtotime($firstDay));
$dayDates = [];
for ($d=1; $d<=$daysInMonth; $d++) {
  $dayDates[$d] = date('Y-m-d', strtotime($month.'-'.str_pad($d,2,'0',STR_PAD_LEFT)));
}

// Load department courses
$courses = [];
if ($deptCode !== '') {
  $dq = mysqli_query($con, "SELECT course_id, course_name FROM course WHERE department_id='".mysqli_real_escape_string($con,$deptCode)."' ORDER BY course_name");
  if ($dq) { while($row=mysqli_fetch_assoc($dq)){ $courses[]=$row; } }
}

// Load students in scope
$students = [];
if ($deptCode !== '') {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."'";
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

  // Determine total distinct marked days in month for dept (support legacy and new module names)
  $qDays = mysqli_query($con, "SELECT COUNT(DISTINCT date) AS dcnt FROM attendance WHERE date BETWEEN '".$firstDay."' AND '".$lastDay."' AND (module_name='DAILY' OR module_name LIKE 'DAILY SLOT %' OR module_name LIKE 'DAILY-S%') AND student_id IN ($idList)");
  if ($qDays && ($rowd=mysqli_fetch_assoc($qDays))) { $totalDays = (int)$rowd['dcnt']; }

  // Per-student present days using max per date (support legacy and new module names)
  $q = mysqli_query($con, "SELECT a.student_id, COUNT(*) AS present_days FROM (SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE date BETWEEN '".$firstDay."' AND '".$lastDay."' AND (module_name='DAILY' OR module_name LIKE 'DAILY SLOT %' OR module_name LIKE 'DAILY-S%') AND student_id IN ($idList) GROUP BY student_id, date) a WHERE a.st=1 GROUP BY a.student_id");
  $presentMap = [];
  if ($q) { while($r=mysqli_fetch_assoc($q)){ $presentMap[$r['student_id']] = (int)$r['present_days']; } }

  // Build per-day attendance map for detailed view (P/A per date)
  $attByStudent = [];
  $q2 = mysqli_query($con, "SELECT student_id, date, MAX(attendance_status) AS st FROM attendance WHERE date BETWEEN '".$firstDay."' AND '".$lastDay."' AND (module_name='DAILY' OR module_name LIKE 'DAILY SLOT %' OR module_name LIKE 'DAILY-S%') AND student_id IN ($idList) GROUP BY student_id, date");
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
    // Header row with days
    $html .= "<tr><th>Student ID</th><th>Student Name</th>";
    for ($d=1; $d<=$daysInMonth; $d++) { $html .= "<th>".$d."</th>"; }
    $html .= "<th>Present</th><th>Total</th><th>%</th></tr>";
    if (!empty($results)) {
      foreach ($results as $row) {
        $sid = $row['student_id'];
        $html .= "<tr>";
        $html .= "<td>".htmlspecialchars($sid)."</td>";
        $html .= "<td>".htmlspecialchars($row['student_fullname'])."</td>";
        for ($d=1; $d<=$daysInMonth; $d++) {
          $dateKey = $dayDates[$d];
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
          <small class="text-muted">(<?php echo htmlspecialchars($deptCode); ?>)</small>
        </h5>
      </div>
      <div class="d-flex flex-column flex-md-row">
        <form class="form-inline mb-2 mb-md-0" method="get" action="">
          <div class="form-group mb-2 mr-2">
            <label for="month" class="mr-2">Month:</label>
            <input type="month" id="month" name="month" class="form-control form-control-sm" 
                   value="<?php echo htmlspecialchars($month); ?>" required>
          </div>
          <button class="btn btn-primary btn-sm">
                <i class="fas fa-sync-alt mr-1"></i> Generate
              </button>
            <a href="<?php echo APP_BASE; ?>/attendance/MonthlyAttendanceReport.php?export=1&month=<?php echo urlencode($month); ?>&view=<?php echo urlencode($view); ?>" 
               class="btn btn-success btn-sm ml-1" id="exportBtn">
              <i class="fas fa-file-excel mr-1"></i> Export to Excel
            </a>
          </div>
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
            <table class="table table-sm table-bordered table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Student ID</th>
                  <th>Name</th>
                  <?php for ($d=1; $d<=$daysInMonth; $d++): ?>
                    <th class="text-center"><?php echo $d; ?></th>
                  <?php endfor; ?>
                  <th class="text-right">Present</th>
                  <th class="text-right">Total</th>
                  <th class="text-right">%</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($results)): ?>
                  <?php foreach ($results as $r): $sid=$r['student_id']; ?>
                    <tr>
                      <td><?php echo htmlspecialchars($sid); ?></td>
                      <td><?php echo htmlspecialchars($r['student_fullname']); ?></td>
                      <?php for ($d=1; $d<=$daysInMonth; $d++): $dateKey=$dayDates[$d]; $mark = isset($attByStudent[$sid][$dateKey]) ? ($attByStudent[$sid][$dateKey] ? 'P' : 'A') : '-'; ?>
                        <td class="text-center <?php echo $mark==='P'?'table-success':($mark==='A'?'table-danger':''); ?>">
                          <?php echo $mark; ?>
                        </td>
                      <?php endfor; ?>
                      <td class="text-right"><?php echo (int)$r['present_days']; ?></td>
                      <td class="text-right"><?php echo (int)$r['total_days']; ?></td>
                      <td class="text-right"><?php echo number_format($r['percentage'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="<?php echo 3+$daysInMonth; ?>" class="text-center text-muted">No data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          <?php else: ?>
            <table class="table table-striped table-hover">
              <thead class="thead-light">
                <tr>
                  <th>Student ID</th>
                  <th>Name</th>
                  <th class="text-right">Present Days</th>
                  <th class="text-right">Total Days</th>
                  <th class="text-right">%</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($results)): ?>
                  <?php foreach ($results as $r): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                      <td><?php echo htmlspecialchars($r['student_fullname']); ?></td>
                      <td class="text-right"><?php echo (int)$r['present_days']; ?></td>
                      <td class="text-right"><?php echo (int)$r['total_days']; ?></td>
                      <td class="text-right"><?php echo number_format($r['percentage'], 2); ?></td>
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
