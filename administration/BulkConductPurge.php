<?php
// administration/BulkConductPurge.php
// Bulk delete students who did NOT accept Code of Conduct, across all related tables

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

// Filters
$dept = isset($_GET['dept']) && $_GET['dept'] !== '' ? $_GET['dept'] : '';
$course = isset($_GET['course']) && $_GET['course'] !== '' ? $_GET['course'] : '';
$do = isset($_POST['action']) ? $_POST['action'] : '';

// Build subquery selecting target student IDs based on acceptance and optional filters
// Uses latest enrollment per student and filters by department/course when provided
$filterJoin = "JOIN (SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date FROM student_enroll se GROUP BY se.student_id) le ON le.student_id = s.student_id
              JOIN student_enroll e ON e.student_id = le.student_id AND e.student_enroll_date = le.max_enroll_date
              JOIN course c ON c.course_id = e.course_id";
$where = "s.student_conduct_accepted_at IS NULL";
$params = [];
$types = '';
if ($dept !== '') { $where .= " AND c.department_id = ?"; $params[] = $dept; $types .= 's'; }
if ($course !== '') { $where .= " AND c.course_id = ?"; $params[] = $course; $types .= 's'; }

$subSQL = "SELECT s.student_id FROM student s $filterJoin WHERE $where";

// Count candidates
$totalCandidates = 0;
if ($st = mysqli_prepare($con, "SELECT COUNT(*) AS cnt FROM (".$subSQL.") x")) {
  if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  if ($rs && ($row = mysqli_fetch_assoc($rs))) { $totalCandidates = (int)$row['cnt']; }
  mysqli_stmt_close($st);
}

$errors = [];
$messages = [];
$previewRows = [];

// Discover referencing tables and columns
$refCols = [ 'student_id','member_id','qualification_student_id','user_name' ];
$targets = [];
$placeholders = rtrim(str_repeat('?,', count($refCols)), ',');
$sqlInfo = "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME IN (".$placeholders.")";
if ($inf = mysqli_prepare($con, $sqlInfo)) {
  mysqli_stmt_bind_param($inf, str_repeat('s', count($refCols)), ...$refCols);
  mysqli_stmt_execute($inf);
  $ir = mysqli_stmt_get_result($inf);
  while ($ir && ($row = mysqli_fetch_assoc($ir))) {
    if ($row['TABLE_NAME'] === 'student') continue; // handle last
    $targets[] = [ $row['TABLE_NAME'], $row['COLUMN_NAME'] ];
  }
  mysqli_stmt_close($inf);
} else {
  $errors[] = 'Failed to inspect schema: '.mysqli_error($con);
}

// Preview affected counts per table (excluding student)
if (!$errors) {
  foreach ($targets as $tc) {
    list($t,$c) = $tc;
    $q = "SELECT COUNT(*) AS cnt FROM `".$t."` t JOIN (".$subSQL.") del ON t.`".$c."` = del.student_id";
    if ($ps = mysqli_prepare($con, $q)) {
      if ($types !== '') { mysqli_stmt_bind_param($ps, $types, ...$params); }
      mysqli_stmt_execute($ps);
      $rr = mysqli_stmt_get_result($ps);
      $cnt = 0;
      if ($rr && ($r = mysqli_fetch_assoc($rr))) { $cnt = (int)$r['cnt']; }
      $previewRows[] = [ 'table' => $t, 'column' => $c, 'count' => $cnt ];
      mysqli_stmt_close($ps);
    }
  }
  // Student table count equals totalCandidates
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $do === 'purge') {
  if ($totalCandidates <= 0) {
    $errors[] = 'No matching students to delete.';
  }
  if (!$errors) {
    mysqli_begin_transaction($con);
    // Temporarily disable FK checks to avoid ordering issues
    @mysqli_query($con, 'SET FOREIGN_KEY_CHECKS=0');

    $ok = true;
    // Delete from referencing tables first
    foreach ($targets as $tc) {
      if (!$ok) break;
      list($t,$c) = $tc;
      $dq = "DELETE t FROM `".$t."` t JOIN (".$subSQL.") del ON t.`".$c."` = del.student_id";
      if ($ps = mysqli_prepare($con, $dq)) {
        if ($types !== '') { mysqli_stmt_bind_param($ps, $types, ...$params); }
        if (!mysqli_stmt_execute($ps)) { $ok = false; $errors[] = 'Failed to delete from '. $t .'.'. $c .': '. mysqli_error($con); }
        mysqli_stmt_close($ps);
      } else {
        $ok = false; $errors[] = 'Prepare failed for delete on '. $t .'.'. $c .': '. mysqli_error($con);
      }
    }

    // Delete from student last
    if ($ok) {
      $ds = "DELETE s FROM student s $filterJoin WHERE $where";
      if ($ps = mysqli_prepare($con, $ds)) {
        if ($types !== '') { mysqli_stmt_bind_param($ps, $types, ...$params); }
        if (!mysqli_stmt_execute($ps)) { $ok = false; $errors[] = 'Failed to delete from student: '. mysqli_error($con); }
        mysqli_stmt_close($ps);
      } else {
        $ok = false; $errors[] = 'Prepare failed for delete on student: '. mysqli_error($con);
      }
    }

    // Re-enable FK checks
    @mysqli_query($con, 'SET FOREIGN_KEY_CHECKS=1');

    if ($ok && !$errors) {
      mysqli_commit($con);
      $messages[] = 'Bulk purge completed successfully.';
      // Recompute counts after purge
      $totalCandidates = 0;
      if ($st = mysqli_prepare($con, "SELECT COUNT(*) AS cnt FROM (".$subSQL.") x")) {
        if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        if ($rs && ($row = mysqli_fetch_assoc($rs))) { $totalCandidates = (int)$row['cnt']; }
        mysqli_stmt_close($st);
      }
    } else {
      mysqli_rollback($con);
    }
  }
}

$title = 'Bulk Purge: Conduct Not Accepted | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h3 class="mb-0">Bulk Delete: Students who did NOT accept the Code of Conduct</h3>
      <small class="text-muted">This will permanently remove students and all related records.</small>
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

  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="mb-3">Preview</h5>
      <p class="mb-1">Matching students: <strong><?php echo number_format($totalCandidates); ?></strong></p>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead class="thead-light">
            <tr>
              <th>Table</th>
              <th>Column</th>
              <th class="text-right">Rows to delete</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($previewRows as $r): ?>
              <tr>
                <td><?php echo h($r['table']); ?></td>
                <td><?php echo h($r['column']); ?></td>
                <td class="text-right"><?php echo number_format((int)$r['count']); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="font-weight-bold">
              <td>student</td>
              <td>student_id (PK)</td>
              <td class="text-right"><?php echo number_format($totalCandidates); ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <form method="post" onsubmit="return confirm('This will permanently delete matching students and all related records. Are you sure?');">
        <input type="hidden" name="action" value="purge">
        <button type="submit" class="btn btn-danger" <?php echo $totalCandidates>0? '':'disabled'; ?>>Delete Permanently</button>
        <a href="<?php echo $base; ?>/administration/ConductReport.php" class="btn btn-outline-secondary ml-2">Back to Conduct Report</a>
      </form>
      <small class="text-muted d-block mt-2">Deletes are executed within a transaction. Foreign key checks are temporarily disabled to avoid ordering issues, then restored.</small>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
