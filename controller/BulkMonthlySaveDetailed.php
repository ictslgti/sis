<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';
// Allow longer execution for large months/groups and prevent premature abort
@set_time_limit(300);
@ignore_user_abort(true);

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if (!in_array($role, ['HOD','IN3'], true)) { http_response_code(403); echo 'Forbidden'; exit; }

function back($params){ global $base; header('Location: '.$base.'/attendance/BulkMonthlyMark.php?'.http_build_query($params)); exit; }

// Resolve department
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : '';
if ($deptCode === '' && !empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $rs = mysqli_query($con, "SELECT department_id FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($rs && ($r=mysqli_fetch_assoc($rs))) { $deptCode = $r['department_id']; }
}
if ($deptCode === '') { back(['err'=>'nodept']); }

$month = isset($_POST['month']) && preg_match('/^\d{4}-\d{2}$/', $_POST['month']) ? $_POST['month'] : date('Y-m');
$courseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$groupId = isset($_POST['group_id']) ? trim($_POST['group_id']) : '';
$includeWeekends = !empty($_POST['include_weekends']) ? 1 : 0;
$respectHolidays = !empty($_POST['respect_holidays']) ? 1 : 0;
$respectVacations = !empty($_POST['respect_vacations']) ? 1 : 0;

$dates = isset($_POST['dates']) && is_array($_POST['dates']) ? array_values(array_unique($_POST['dates'])) : [];
if (empty($dates)) { back(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'nodates']); }

// Only consider past or today
$today = date('Y-m-d');
$dates = array_values(array_filter($dates, function($d) use ($today){ return preg_match('/^\d{4}-\d{2}-\d{2}$/',$d) && $d <= $today; }));
if (empty($dates)) { back(['month'=>$month,'course_id'=>$courseId,'err'=>'nodates']); }

// Students scope (Active/Following) — by group if provided, else by course/department
$students = [];
if ($groupId !== '') {
  $sqlSt = "SELECT s.student_id
            FROM group_students gs
            JOIN student s ON s.student_id = gs.student_id
            WHERE gs.group_id = ? AND (gs.status='active' OR gs.status IS NULL OR gs.status='')
            ORDER BY s.student_id";
  if ($st = mysqli_prepare($con, $sqlSt)) {
    $gid = (int)$groupId;
    mysqli_stmt_bind_param($st, 'i', $gid);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($res && ($r = mysqli_fetch_assoc($res))) { $students[] = $r['student_id']; }
    mysqli_stmt_close($st);
  }
} else {
  $where = "WHERE c.department_id='".mysqli_real_escape_string($con,$deptCode)."' AND se.student_enroll_status IN ('Following','Active')";
  if ($courseId !== '') { $where .= " AND se.course_id='".mysqli_real_escape_string($con,$courseId)."'"; }
  $sqlSt = "SELECT s.student_id FROM student_enroll se JOIN course c ON c.course_id=se.course_id JOIN student s ON s.student_id=se.student_id $where ORDER BY s.student_id";
  $res = mysqli_query($con, $sqlSt);
  if ($res) { while ($r=mysqli_fetch_assoc($res)) { $students[] = $r['student_id']; } }
}
if (empty($students)) { back(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'nostudents']); }

// Input present map: present[sid][] = list of dates with present
$present = isset($_POST['present']) && is_array($_POST['present']) ? $_POST['present'] : [];
$presentSet = [];
foreach ($present as $sid => $dlist) {
  if (!is_array($dlist)) continue;
  foreach ($dlist as $d) {
    $k = (string)$sid.'|'.$d;
    $presentSet[$k] = true;
  }
}

// Staff name for attribution
$staff_name = '';
if (!empty($_SESSION['user_name'])) {
  $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
  $qr = mysqli_query($con, "SELECT staff_name FROM staff WHERE staff_id='$sid' LIMIT 1");
  if ($qr && ($rr=mysqli_fetch_assoc($qr))) { $staff_name = $rr['staff_name']; }
}

// Ensure unique key for idempotent upserts (best-effort; may fail if duplicates already exist)
@mysqli_query($con, "ALTER TABLE `attendance` ADD UNIQUE KEY `uniq_student_date_module` (`student_id`,`date`,`module_name`)");
// Helpful supporting indexes (best-effort)
@mysqli_query($con, "CREATE INDEX IF NOT EXISTS `idx_att_mod_date` ON `attendance` (`module_name`,`date`)");
@mysqli_query($con, "CREATE INDEX IF NOT EXISTS `idx_att_mod_sid`  ON `attendance` (`module_name`,`student_id`)");
$module_name = 'DAILY-S1';

// Build flat rows [ [sid, date, val], ... ]
$rows = [];
foreach ($students as $sid) {
  foreach ($dates as $d) {
    $key = (string)$sid.'|'.$d;
    $rows[] = [$sid, $d, isset($presentSet[$key]) ? 1 : 0];
  }
}

mysqli_begin_transaction($con);
$ok = true; $ins=0; $upd=0; $skip=0;

// Process in chunks to avoid huge SQL statements (adaptive)
$CHUNK = 500; // default rows per batch
$totalRows = count($rows);
if ($totalRows > 10000) { $CHUNK = 300; }
elseif ($totalRows < 4000) { $CHUNK = 800; }
for ($i = 0; $i < $totalRows; $i += $CHUNK) {
  $batch = array_slice($rows, $i, $CHUNK);

  // 1) Delete existing rows for these keys (handles legacy duplicates)
  $tuples = [];
  foreach ($batch as $r) {
    $sid = mysqli_real_escape_string($con, $r[0]);
    $dt  = mysqli_real_escape_string($con, $r[1]);
    $tuples[] = "('{$sid}','{$dt}')";
  }
  if (!empty($tuples)) {
    $mn = mysqli_real_escape_string($con, $module_name);
    // Use composite IN to speed up match
    $sqlDel = "DELETE FROM attendance WHERE module_name='{$mn}' AND (student_id, date) IN (".implode(',', $tuples).")";
    if (!mysqli_query($con, $sqlDel)) { $ok=false; break; }
  }

  // 2) Insert all rows with ON DUPLICATE KEY UPDATE
  $values = [];
  foreach ($batch as $r) {
    $val = (int)$r[2];
    $sid = mysqli_real_escape_string($con, $r[0]);
    $dt  = mysqli_real_escape_string($con, $r[1]);
    $stf = mysqli_real_escape_string($con, $staff_name);
    $mn  = mysqli_real_escape_string($con, $module_name);
    $values[] = "({$val},'{$stf}','{$sid}','{$dt}','{$mn}')";
  }
  if (!empty($values)) {
    $sqlIns = "INSERT INTO attendance (attendance_status, staff_name, student_id, date, module_name) VALUES ".implode(',', $values)
            . " ON DUPLICATE KEY UPDATE attendance_status=VALUES(attendance_status), staff_name=VALUES(staff_name)";
    if (!mysqli_query($con, $sqlIns)) { $ok=false; break; }

    // Estimate counts for feedback using affected_rows heuristic
    $aff = mysqli_affected_rows($con); // sums across inserted/updated rows
    // We cannot perfectly split insert vs update without probing; keep totals approximate:
    // Treat all as updates if unique key existed previously; otherwise new inserts.
    // To provide stable UX, just track total changed rows and skip remains zero in batch context.
    // However, we can increment ins/upd roughly by assuming every row changed (no skip in grid mode).
    // We'll just accumulate into $upd since the grid overwrites state.
    if ($aff > 0) { $upd += $aff; }
  }
}

if ($ok) {
  mysqli_commit($con);
  back([
    'month'=>$month,
    'course_id'=>$courseId,
    'group_id'=>$groupId,
    'ok'=>1,
    'ins'=>$ins,
    'upd'=>$upd,
    'skip'=>$skip,
    'load'=>1,
    'include_weekends'=>$includeWeekends,
    'respect_holidays'=>$respectHolidays,
    'respect_vacations'=>$respectVacations
  ]);
} else {
  mysqli_rollback($con);
  back(['month'=>$month,'course_id'=>$courseId,'group_id'=>$groupId,'err'=>'dberror','load'=>1]);
}
