<?php
// administration/BulkIDRenumber.php
// Bulk renumber Student IDs for students who ACCEPTED Code of Conduct, with cascade across all related tables

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Admin only
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'ADM') {
  http_response_code(403);
  echo 'Forbidden: Admins only';
  exit;
}

$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) { die('Failed DB connection: '.mysqli_connect_error()); }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Inputs
$dept   = isset($_GET['dept']) && $_GET['dept'] !== '' ? $_GET['dept'] : '';
$course = isset($_GET['course']) && $_GET['course'] !== '' ? $_GET['course'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Config for renumber
$prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : '';
$start  = isset($_POST['start']) ? (int)$_POST['start'] : 1;
$width  = isset($_POST['width']) ? (int)$_POST['width'] : 3;
$orderBy= isset($_POST['order_by']) ? $_POST['order_by'] : 'student_id'; // or 'fullname', 'enroll_date'

// Build student list: accepted conduct, current Following/Active enrollment, filtered by dept/course
$filterJoin = "JOIN (SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date FROM student_enroll se GROUP BY se.student_id) le ON le.student_id = s.student_id
              JOIN student_enroll e ON e.student_id = le.student_id AND e.student_enroll_date = le.max_enroll_date
              JOIN course c ON c.course_id = e.course_id";
$where = "s.student_conduct_accepted_at IS NOT NULL AND e.student_enroll_status IN ('Following','Active')";
$params = [];
$types = '';
if ($dept !== '')   { $where .= " AND c.department_id = ?"; $params[] = $dept; $types .= 's'; }
if ($course !== '') { $where .= " AND c.course_id = ?";     $params[] = $course; $types .= 's'; }

$orderSql = 's.student_id ASC';
if ($orderBy === 'fullname') { $orderSql = 's.student_fullname ASC'; }
if ($orderBy === 'enroll_date') { $orderSql = 'e.student_enroll_date ASC'; }

$sqlList = "SELECT s.student_id, s.student_fullname, c.course_id, c.department_id, e.student_enroll_date
            FROM student s $filterJoin
            WHERE $where
            ORDER BY c.department_id, c.course_id, $orderSql";

$students = [];
if ($st = mysqli_prepare($con, $sqlList)) {
  if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($rs && ($r = mysqli_fetch_assoc($rs))) { $students[] = $r; }
  mysqli_stmt_close($st);
}

// Default prefix: if single course filter selected, use that course_id as prefix; else department; else blank
// Note: We will preserve each student's existing prefix (front format) and only change the trailing numbers.

$errors = [];
$messages = [];
$preview = [];

// Build proposed mapping and avoid conflicts
// If a Prefix is provided, apply that prefix to all students and use a single global counter starting from Start with Width.
// If Prefix is empty, preserve each student's existing prefix and restart numbering per prefix.
$oldSet = [];
foreach ($students as $srowTmp) { $oldSet[$srowTmp['student_id']] = true; }
$assigned = [];
$counters = []; // counter per groupKey
$existsStmt = mysqli_prepare($con, 'SELECT student_id FROM student WHERE student_id=? LIMIT 1');
$useGlobalPrefix = ($prefix !== '');
foreach ($students as $srow) {
  $old = $srow['student_id'];
  $prefixPart = $useGlobalPrefix ? $prefix : $old;
  $origWidth = $width; // default to user width
  if (!$useGlobalPrefix && preg_match('/^(.*?)(\d+)$/', $old, $m)) {
    $prefixPart = $m[1];
    $origWidth = max($width, (int)strlen($m[2])); // keep at least the existing width
  }
  $groupKey = $useGlobalPrefix ? $prefixPart : $prefixPart; // same variable but explicit for clarity
  if (!isset($counters[$groupKey])) { $counters[$groupKey] = $start; }
  // Find next available candidate for this prefix
  while (true) {
    $seq = str_pad((string)$counters[$groupKey], max(1,$origWidth), '0', STR_PAD_LEFT);
    $candidate = $prefixPart . $seq;
    // Avoid assigning the same new ID twice in this batch
    if (isset($assigned[$candidate])) { $counters[$prefixPart]++; continue; }
    // If candidate equals some other existing student's ID (not in our oldSet), skip
    $conflict = false;
    if ($existsStmt) {
      mysqli_stmt_bind_param($existsStmt, 's', $candidate);
      mysqli_stmt_execute($existsStmt);
      $rrc = mysqli_stmt_get_result($existsStmt);
      if ($rrc && ($rowc = mysqli_fetch_assoc($rrc))) {
        if (!isset($oldSet[$candidate])) { $conflict = true; }
      }
    }
    if ($conflict) { $counters[$groupKey]++; continue; }
    // Found free candidate
    $assigned[$candidate] = true;
    $preview[] = [ 'old' => $old, 'new' => $candidate, 'name' => $srow['student_fullname'], 'course_id' => $srow['course_id'], 'department_id' => $srow['department_id']];
    $counters[$groupKey]++;
    break;
  }
}

// Check conflicts with existing IDs not in our list
if ($preview) {
  $in = [];
  $inTypes = '';
  $inParams = [];
  foreach ($preview as $p) { $in[] = '?'; $inTypes .= 's'; $inParams[] = $p['new']; }
  $sqlDup = 'SELECT student_id FROM student WHERE student_id IN ('.implode(',', $in).')';
  if ($chk = mysqli_prepare($con, $sqlDup)) {
    mysqli_stmt_bind_param($chk, $inTypes, ...$inParams);
    mysqli_stmt_execute($chk);
    $rs = mysqli_stmt_get_result($chk);
    $existing = [];
    while ($rs && ($r = mysqli_fetch_assoc($rs))) { $existing[$r['student_id']] = true; }
    mysqli_stmt_close($chk);
    // If existing contains IDs that are also old IDs to be renamed, they are okay; otherwise conflict
    $oldSet = [];
    foreach ($preview as $p) { $oldSet[$p['old']] = true; }
    foreach ($existing as $id => $_) {
      if (!isset($oldSet[$id])) { $errors[] = 'Target ID already exists: '.$id; }
    }
  }
}

// Discover referencing columns for cascade
$refCols = [ 'student_id','member_id','qualification_student_id','user_name' ];
$targets = [];
$placeholders = rtrim(str_repeat('?,', count($refCols)), ',');
$sqlInfo = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN (".$placeholders.")";
if ($inf = mysqli_prepare($con, $sqlInfo)) {
  mysqli_stmt_bind_param($inf, str_repeat('s', count($refCols)), ...$refCols);
  mysqli_stmt_execute($inf);
  $ir = mysqli_stmt_get_result($inf);
  while ($ir && ($row = mysqli_fetch_assoc($ir))) {
    $targets[] = [ $row['TABLE_NAME'], $row['COLUMN_NAME'] ];
  }
  mysqli_stmt_close($inf);
} else {
  $errors[] = 'Failed to inspect schema: '.mysqli_error($con);
}

// Apply renumbering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'apply' && !$errors) {
  if (count($preview) === 0) { $errors[] = 'No students match the filter.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'apply' && !$errors) {
  mysqli_begin_transaction($con);
  @mysqli_query($con, 'SET FOREIGN_KEY_CHECKS=0');
  $ok = true;

  // Build mapping old => new
  $map = [];
  foreach ($preview as $p) { $map[$p['old']] = $p['new']; }

  // Helper to apply a single id change across all referencing columns
  $applyChange = function($from, $to) use ($con, $targets, &$errors) {
    foreach ($targets as $tc) {
      list($t,$c) = $tc;
      $sqlu = "UPDATE `".$t."` SET `".$c."`=? WHERE `".$c."`=?";
      if ($ps = mysqli_prepare($con, $sqlu)) {
        mysqli_stmt_bind_param($ps, 'ss', $to, $from);
        if (!mysqli_stmt_execute($ps)) { $errors[] = 'Update failed on '. $t .'.'. $c .': '. mysqli_error($con); return false; }
        mysqli_stmt_close($ps);
      } else { $errors[] = 'Prepare failed for '. $t .'.'. $c .': '. mysqli_error($con); return false; }
    }
    return true;
  };

  // Sets for cycle detection
  $remaining = $map; // associative old => new
  $maxLoops = count($remaining) * 3 + 10;
  while ($ok && $remaining && $maxLoops-- > 0) {
    $progress = false;
    // First, apply all changes where target is not someone else's source (no collision)
    foreach (array_keys($remaining) as $old) {
      $new = $remaining[$old];
      if (!isset($remaining[$new])) { // safe to update directly
        if (!$applyChange($old, $new)) { $ok = false; break; }
        unset($remaining[$old]);
        $progress = true;
      }
    }
    if (!$ok) break;
    if ($progress) continue;

    // No safe moves -> cycle(s) exist. Break one cycle using a spare ID matching the format
    $cycleOld = array_key_first($remaining);
    // Derive prefix and width from cycleOld to build a spare value that fits
    $prefixPart = $cycleOld; $origWidth = 3;
    if (preg_match('/^(.*?)(\\d+)$/', $cycleOld, $mcyc)) { $prefixPart = $mcyc[1]; $origWidth = strlen($mcyc[2]); }

    // Find spare candidate that doesn't exist and not used in map values or keys
    $try = 1; $spare = null;
    while ($try < 100000) { // reasonable cap
      $seq = str_pad((string)$try, max(1,$origWidth), '0', STR_PAD_LEFT);
      $cand = $prefixPart . $seq;
      if (isset($remaining[$cand])) { $try++; continue; }
      $inMapVals = in_array($cand, $map, true);
      if ($inMapVals) { $try++; continue; }
      // Check DB existence
      $exists = false;
      if ($stmt = mysqli_prepare($con, 'SELECT 1 FROM student WHERE student_id=? LIMIT 1')) {
        mysqli_stmt_bind_param($stmt, 's', $cand);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if ($r && mysqli_fetch_row($r)) { $exists = true; }
        mysqli_stmt_close($stmt);
      }
      if ($exists) { $try++; continue; }
      $spare = $cand; break;
    }
    if ($spare === null) { $ok = false; $errors[] = 'Failed to find spare ID to break cycle for prefix '. $prefixPart; break; }

    // Move cycleOld -> spare, then loop continues to resolve remaining safely
    if (!$applyChange($cycleOld, $spare)) { $ok = false; break; }
    // Update mapping to reflect that original target wanting cycleOld now points to spare
    $newOfCycle = $remaining[$cycleOld];
    unset($remaining[$cycleOld]);
    // Replace any source that had target equal to cycleOld to now target spare
    foreach ($remaining as $k => $v) {
      if ($v === $cycleOld) { $remaining[$k] = $spare; }
    }
    // And also fix the full map values so further checks stay consistent
    foreach ($map as $k => $v) { if ($v === $cycleOld) { $map[$k] = $spare; } }
  }

  @mysqli_query($con, 'SET FOREIGN_KEY_CHECKS=1');

  if ($ok && !$errors && empty($remaining)) {
    mysqli_commit($con);
    $messages[] = 'Bulk ID renumber completed successfully.';
  } else {
    if ($ok && $remaining) { $errors[] = 'Unable to complete due to unresolved cycles.'; }
    mysqli_rollback($con);
  }
}

$title = 'Bulk Renumber Student IDs | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h3 class="mb-0">Bulk Renumber Student IDs (Accepted Conduct)</h3>
      <small class="text-muted">Filters by Department/Course. Generates new IDs with your prefix and sequential numbers. Updates across all tables.</small>
    </div>
  </div>

  <?php foreach ($messages as $m): ?>
    <div class="alert alert-success"><?php echo h($m); ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?php echo h($e); ?></div>
  <?php endforeach; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="get" class="form-inline">
        <label class="mr-2">Department</label>
        <select name="dept" class="form-control mr-3">
          <option value="">All</option>
          <?php
          $dres = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name');
          while ($dres && ($d = mysqli_fetch_assoc($dres))) {
            $sel = ($dept === $d['department_id']) ? 'selected' : '';
            echo '<option value="'.h($d['department_id']).'" '.$sel.'>'.h($d['department_name']).'</option>';
          }
          ?>
        </select>
        <label class="mr-2">Course</label>
        <select name="course" class="form-control mr-3">
          <option value="">All</option>
          <?php
          $cres = mysqli_query($con, 'SELECT course_id, course_name FROM course ORDER BY course_name');
          while ($cres && ($c = mysqli_fetch_assoc($cres))) {
            $sel = ($course === $c['course_id']) ? 'selected' : '';
            echo '<option value="'.h($c['course_id']).'" '.$sel.'>'.h($c['course_name']).' ('.h($c['course_id']).')</option>';
          }
          ?>
        </select>
        <button type="submit" class="btn btn-outline-primary">Apply Filters</button>
      </form>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="preview">
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Prefix</label>
            <input type="text" name="prefix" class="form-control" value="<?php echo h($prefix); ?>" placeholder="e.g., <?php echo h($course ?: ($dept ?: '2025')); ?>/">
            <small class="text-muted">Left side of the ID. Example: ICT/EM/2025/</small>
          </div>
          <div class="form-group col-md-2">
            <label>Starting Number</label>
            <input type="number" name="start" class="form-control" value="<?php echo h($start); ?>" min="1">
          </div>
          <div class="form-group col-md-2">
            <label>Padding Width</label>
            <input type="number" name="width" class="form-control" value="<?php echo h($width); ?>" min="1" max="10">
          </div>
          <div class="form-group col-md-3">
            <label>Order By</label>
            <select name="order_by" class="form-control">
              <option value="student_id" <?php echo $orderBy==='student_id'?'selected':''; ?>>Current ID</option>
              <option value="fullname" <?php echo $orderBy==='fullname'?'selected':''; ?>>Full Name</option>
              <option value="enroll_date" <?php echo $orderBy==='enroll_date'?'selected':''; ?>>Enroll Date</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-secondary">Refresh Preview</button>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="mb-3">Preview mapping (<?php echo count($preview); ?> students)</h5>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead class="thead-light">
            <tr>
              <th>#</th>
              <th>Old ID</th>
              <th>New ID</th>
              <th>New Last No.</th>
              <th>Name</th>
              <th>Course</th>
              <th>Department</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; foreach ($preview as $p): ?>
              <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo h($p['old']); ?></td>
                <td class="text-primary font-weight-bold"><?php echo h($p['new']); ?></td>
                <td>
                  <?php
                  $suffixNum = '';
                  if (preg_match('/(\d+)$/', $p['new'], $mm)) { $suffixNum = (string)intval($mm[1]); }
                  echo h($suffixNum);
                  ?>
                </td>
                <td><?php echo h($p['name']); ?></td>
                <td><?php echo h($p['course_id']); ?></td>
                <td><?php echo h($p['department_id']); ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$preview): ?>
              <tr><td colspan="6" class="text-center text-muted">No students found for current filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <form method="post" onsubmit="return confirm('This will rename student IDs across ALL tables. Proceed?');">
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="prefix" value="<?php echo h($prefix); ?>">
        <input type="hidden" name="start" value="<?php echo h($start); ?>">
        <input type="hidden" name="width" value="<?php echo h($width); ?>">
        <input type="hidden" name="order_by" value="<?php echo h($orderBy); ?>">
        <button type="submit" class="btn btn-danger" <?php echo $preview? '':'disabled'; ?>>Apply Renumbering</button>
        <a href="<?php echo $base; ?>/administration/ConductReport.php" class="btn btn-outline-secondary ml-2">Back to Conduct Report</a>
      </form>
      <small class="text-muted d-block mt-2">Operation runs in a transaction and uses a two-phase rename (via temporary IDs) to avoid collisions. Foreign key checks are temporarily disabled.</small>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
