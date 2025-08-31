<?php
$title = "Group Reports | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$base = defined('APP_BASE') ? APP_BASE : '';
if ($role !== 'HOD') { echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>'; require_once __DIR__.'/../footer.php'; exit; }

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$from = isset($_GET['from']) && $_GET['from']!=='' ? $_GET['from'] : '';
$to   = isset($_GET['to']) && $_GET['to']!=='' ? $_GET['to']   : '';
$staff_id = isset($_GET['staff_id']) ? trim($_GET['staff_id']) : '';
$report = isset($_GET['report']) ? $_GET['report'] : 'coverage'; // coverage | attendance
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;     // 1 = CSV

// Groups list for filter
$groups = [];
$qg = mysqli_query($con, 'SELECT id, name, course_id, academic_year FROM `groups` ORDER BY created_at DESC');
while ($qg && ($r=mysqli_fetch_assoc($qg))) { $groups[]=$r; }

// Staff list (working only)
$staff = [];
$qs = mysqli_query($con, "SELECT staff_id, staff_name FROM staff WHERE staff_status='Working' ORDER BY staff_name");
while ($qs && ($r=mysqli_fetch_assoc($qs))) { $staff[]=$r; }

// Build WHERE fragments
$where = [];
$params = [];
typeof_bind:
$types = '';
if ($group_id>0) { $where[] = 's.group_id = ?'; $params[] = $group_id; $types .= 'i'; }
if ($from !== '') { $where[] = 's.session_date >= ?'; $params[] = $from; $types .= 's'; }
if ($to   !== '') { $where[] = 's.session_date <= ?'; $params[] = $to;   $types .= 's'; }
if ($staff_id !== '') {
  // filter sessions created by staff or assigned staff
  $where[] = '(s.created_by = ? OR EXISTS(SELECT 1 FROM group_staff gs WHERE gs.group_id=s.group_id AND gs.staff_id=? AND gs.active=1))';
  $params[] = $staff_id; $types .= 's';
  $params[] = $staff_id; $types .= 's';
}
$wsql = empty($where) ? '1=1' : implode(' AND ', $where);

// Coverage report
$coverage_rows = [];
if ($report === 'coverage') {
  $sql = "SELECT s.id, s.group_id, s.session_date, s.start_time, s.end_time, s.coverage_title, s.coverage_notes, s.created_by, g.name as group_name, g.course_id, g.academic_year
          FROM group_sessions s INNER JOIN `groups` g ON g.id=s.group_id WHERE $wsql ORDER BY s.session_date DESC, s.id DESC";
  $st = mysqli_prepare($con, $sql);
  if ($st) {
    if (!empty($params)) { mysqli_stmt_bind_param($st, $types, ...$params); }
    mysqli_stmt_execute($st); $res = mysqli_stmt_get_result($st);
    while ($res && ($r=mysqli_fetch_assoc($res))) { $coverage_rows[]=$r; }
    mysqli_stmt_close($st);
  }
  if ($export===1) {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=coverage_report.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Group','Course','AY','Start','End','Title','Notes','Created By']);
    foreach ($coverage_rows as $r) {
      fputcsv($out, [$r['session_date'],$r['group_name'],$r['course_id'],$r['academic_year'],$r['start_time'],$r['end_time'],$r['coverage_title'],$r['coverage_notes'],$r['created_by']]);
    }
    fclose($out); exit;
  }
}

// Attendance summary report (per student, Present/Total)
$att_rows = [];
if ($report === 'attendance') {
  if ($group_id<=0) { $att_rows = []; }
  else {
    $awhere = [];$ap=[];$at='';
    $awhere[] = 'ga.session_id = s.id';
    $awhere[] = 's.group_id = ?'; $ap[]=$group_id; $at.='i';
    if ($from!=='') { $awhere[]='s.session_date >= ?'; $ap[]=$from; $at.='s'; }
    if ($to!=='') { $awhere[]='s.session_date <= ?'; $ap[]=$to; $at.='s'; }
    $w = implode(' AND ',$awhere);
    $sql = "SELECT ga.student_id, st.student_fullname,
                   SUM(CASE WHEN ga.present=1 THEN 1 ELSE 0 END) AS presents,
                   COUNT(*) AS total
            FROM group_attendance ga
            INNER JOIN group_sessions s ON s.id = ga.session_id
            INNER JOIN student st ON st.student_id = ga.student_id
            WHERE $w
            GROUP BY ga.student_id, st.student_fullname
            ORDER BY st.student_fullname";
    $st2 = mysqli_prepare($con, $sql);
    if ($st2) {
      mysqli_stmt_bind_param($st2, $at, ...$ap);
      mysqli_stmt_execute($st2); $res = mysqli_stmt_get_result($st2);
      while ($res && ($r=mysqli_fetch_assoc($res))) { $att_rows[]=$r; }
      mysqli_stmt_close($st2);
    }
    if ($export===1) {
      header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename=attendance_summary.csv');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['Student ID','Student Name','Presents','Total','%']);
      foreach ($att_rows as $r) {
        $pct = ($r['total']>0) ? round(($r['presents']/$r['total'])*100,2) : 0;
        fputcsv($out, [$r['student_id'],$r['student_fullname'],$r['presents'],$r['total'],$pct]);
      }
      fclose($out); exit;
    }
  }
}
?>
<div class="container mt-4">
  <h3>Group Reports</h3>
  <?php if (isset($_GET['ok']) && $_GET['ok']==='export'): ?>
    <div class="alert alert-success">Exported to legacy attendance. Inserted: <?php echo (int)($_GET['ins']??0); ?>, Updated: <?php echo (int)($_GET['upd']??0); ?>, Errors: <?php echo (int)($_GET['errc']??0); ?>.</div>
  <?php elseif (isset($_GET['err']) && $_GET['err']==='legacy_missing'): ?>
    <div class="alert alert-warning">Legacy table <code>attendance</code> not found. Create it via Admin Attendance page or DB before exporting.</div>
  <?php elseif (isset($_GET['err']) && $_GET['err']==='invalid'): ?>
    <div class="alert alert-danger">Invalid parameters for export.</div>
  <?php elseif (isset($_GET['err']) && $_GET['err']==='query'): ?>
    <div class="alert alert-danger">Query failed during export.</div>
  <?php endif; ?>
  <form method="GET" class="card mb-3">
    <div class="card-body">
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Group</label>
          <select name="group_id" class="form-control">
            <option value="0">All</option>
            <?php foreach ($groups as $g): $sel = ($group_id==(int)$g['id'])?' selected':''; ?>
              <option value="<?php echo (int)$g['id']; ?>"<?php echo $sel; ?>><?php echo h($g['name']).' ('.h($g['course_id']).' - '.h($g['academic_year']).')'; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>From</label>
          <input type="date" name="from" class="form-control" value="<?php echo h($from); ?>">
        </div>
        <div class="form-group col-md-2">
          <label>To</label>
          <input type="date" name="to" class="form-control" value="<?php echo h($to); ?>">
        </div>
        <div class="form-group col-md-2">
          <label>Staff</label>
          <select name="staff_id" class="form-control">
            <option value="">All</option>
            <?php foreach ($staff as $s): $sel = ($staff_id===$s['staff_id'])?' selected':''; ?>
              <option value="<?php echo h($s['staff_id']); ?>"<?php echo $sel; ?>><?php echo h($s['staff_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>Report</label>
          <select name="report" class="form-control">
            <option value="coverage" <?php echo ($report==='coverage')?'selected':''; ?>>Coverage log</option>
            <option value="attendance" <?php echo ($report==='attendance')?'selected':''; ?>>Attendance summary</option>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Run</button>
      <button class="btn btn-outline-secondary ml-2" type="submit" name="export" value="1">Export CSV</button>
    </div>
  </form>

  <?php if ($report==='coverage'): ?>
    <div class="card">
      <div class="card-header">Coverage Log</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Date</th><th>Group</th><th>Course</th><th>AY</th><th>Time</th><th>Title</th><th>Notes</th><th>Created By</th></tr></thead>
            <tbody>
              <?php foreach ($coverage_rows as $r): ?>
                <tr>
                  <td><?php echo h($r['session_date']); ?></td>
                  <td><?php echo h($r['group_name']); ?></td>
                  <td><?php echo h($r['course_id']); ?></td>
                  <td><?php echo h($r['academic_year']); ?></td>
                  <td><?php echo h(($r['start_time']?:'').(($r['end_time'])?(' - '.$r['end_time']):'')); ?></td>
                  <td><b><?php echo h($r['coverage_title']); ?></b></td>
                  <td><?php echo h($r['coverage_notes']); ?></td>
                  <td><?php echo h($r['created_by']); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($coverage_rows)): ?>
                <tr><td colspan="8" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Attendance Summary</span>
        <?php if ($group_id>0): ?>
        <form method="post" action="<?php echo $base; ?>/controller/GroupAttendanceExport.php" class="mb-0" id="exportForm">
          <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
          <input type="hidden" name="from" value="<?php echo h($from); ?>">
          <input type="hidden" name="to" value="<?php echo h($to); ?>">
          <button type="submit" class="btn btn-sm btn-outline-primary" id="exportBtn">Export to legacy attendance</button>
        </form>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead><tr><th>Student ID</th><th>Student Name</th><th>Presents</th><th>Total</th><th>%</th></tr></thead>
            <tbody>
              <?php foreach ($att_rows as $r): $pct = ($r['total']>0)? round(($r['presents']/$r['total'])*100,2):0; ?>
                <tr>
                  <td><?php echo h($r['student_id']); ?></td>
                  <td><?php echo h($r['student_fullname']); ?></td>
                  <td><?php echo (int)$r['presents']; ?></td>
                  <td><?php echo (int)$r['total']; ?></td>
                  <td><?php echo $pct; ?>%</td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($att_rows)): ?>
                <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
  (function(){
    var form = document.getElementById('exportForm');
    if (!form) return;
    var btn = document.getElementById('exportBtn');
    var busy = false;
    form.addEventListener('submit', function(){
      if (busy) return false;
      busy = true;
      if (btn){
        btn.disabled = true;
        btn.classList.add('disabled');
        btn.textContent = 'Exporting...';
      }
      // allow normal form submit; server will redirect back with ok/err
    });
  })();
</script>
<?php require_once __DIR__ . '/../footer.php'; ?>
