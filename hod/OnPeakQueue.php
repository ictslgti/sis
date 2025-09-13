<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_roles(['HOD','ADM']);

$base = defined('APP_BASE') ? APP_BASE : '';
$deptId = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';
if ($deptId === '') { $deptId = '__NONE__'; }

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['ids']) && is_array($_POST['ids'])) {
  $act = strtolower(trim((string)$_POST['bulk_action']));
  $ids = array_map('intval', $_POST['ids']);
  $ids = array_values(array_filter($ids, function($v){ return $v > 0; }));
  if (!empty($ids)) {
    $idList = implode(',', $ids);
    if ($act === 'approve') {
      $sql = "UPDATE onpeak_request SET onpeak_request_status='Approved by HOD' WHERE department_id='".mysqli_real_escape_string($con,$deptId)."' AND id IN ($idList) AND TRIM(LOWER(onpeak_request_status)) LIKE 'pending%'";
      @mysqli_query($con, $sql);
    } elseif ($act === 'reject') {
      $sql = "UPDATE onpeak_request SET onpeak_request_status='Not Approved' WHERE department_id='".mysqli_real_escape_string($con,$deptId)."' AND id IN ($idList) AND TRIM(LOWER(onpeak_request_status)) LIKE 'pending%'";
      @mysqli_query($con, $sql);
    }
  }
}

// Filters
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '';
$from   = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to     = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where = ["o.department_id='".mysqli_real_escape_string($con,$deptId)."'"];
if ($status !== '') {
  if ($status === 'pending') { $where[] = "TRIM(LOWER(o.onpeak_request_status)) LIKE 'pending%'"; }
  elseif ($status === 'approved') { $where[] = "TRIM(LOWER(o.onpeak_request_status)) LIKE 'approved%'"; }
  elseif ($status === 'rejected') { $where[] = "(TRIM(LOWER(o.onpeak_request_status)) LIKE 'not%' OR TRIM(LOWER(o.onpeak_request_status)) LIKE 'reject%')"; }
}
if ($from !== '') { $where[] = "o.exit_date >= '".mysqli_real_escape_string($con,$from)."'"; }
if ($to   !== '') { $where[] = "o.exit_date <= '".mysqli_real_escape_string($con,$to)."'"; }
if ($q    !== '') {
  $like = '%'.mysqli_real_escape_string($con,$q).'%';
  $where[] = "(o.student_id LIKE '$like' OR s.student_ininame LIKE '$like' OR s.student_fullname LIKE '$like' OR o.reason LIKE '$like')";
}
$whereSql = implode(' AND ', $where);

$title = 'OnPeak Queue | HOD';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu2.php';
?>
<div class="container mt-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h5 class="mb-0"><i class="far fa-calendar-check mr-1 text-primary"></i> OnPeak Requests (Department)</h5>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>/hod/Dashboard.php">Back to Dashboard</a>
  </div>

  <form class="card card-body shadow-sm mb-3" method="get" action="">
    <div class="form-row">
      <div class="col-12 col-md-2 mb-2">
        <label class="small">From</label>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control form-control-sm">
      </div>
      <div class="col-12 col-md-2 mb-2">
        <label class="small">To</label>
        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control form-control-sm">
      </div>
      <div class="col-12 col-md-2 mb-2">
        <label class="small">Status</label>
        <select name="status" class="form-control form-control-sm">
          <option value="">All</option>
          <option value="pending"  <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
          <option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Approved</option>
          <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Not Approved</option>
        </select>
      </div>
      <div class="col-12 col-md-4 mb-2">
        <label class="small">Search</label>
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control form-control-sm" placeholder="Student ID, name, reason">
      </div>
      <div class="col-12 col-md-2 mb-2 align-self-end">
        <button class="btn btn-primary btn-sm btn-block" type="submit">Apply</button>
      </div>
    </div>
  </form>

  <form method="post" class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
      <div>
        <button class="btn btn-sm btn-success" name="bulk_action" value="approve" onclick="return confirm('Approve selected pending requests?');">Approve Selected</button>
        <button class="btn btn-sm btn-danger"  name="bulk_action" value="reject"  onclick="return confirm('Reject selected pending requests?');">Reject Selected</button>
      </div>
      <small class="text-muted">Only Pending will change</small>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="thead-light">
          <tr>
            <th style="width:30px;"><input type="checkbox" onclick="document.querySelectorAll('.opchk').forEach(c=>c.checked=this.checked);"></th>
            <th>Student</th>
            <th>Exit</th>
            <th>Return</th>
            <th>Reason</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sql = "SELECT o.*, s.student_ininame, s.student_fullname FROM onpeak_request o
                  LEFT JOIN student s ON s.student_id=o.student_id
                  WHERE $whereSql
                  ORDER BY o.id DESC";
          $rs = mysqli_query($con, $sql);
          if ($rs && mysqli_num_rows($rs) > 0) {
            while ($r = mysqli_fetch_assoc($rs)) {
              $name = $r['student_ininame'] ?: ($r['student_fullname'] ?: $r['student_id']);
              $st = trim(strtolower($r['onpeak_request_status'] ?? ''));
              $rowClass = '';
              if ($st === '' || strpos($st,'pend')===0) $rowClass = 'table-warning';
              elseif (strpos($st,'approv')===0) $rowClass = 'table-success';
              elseif (strpos($st,'reject')===0 || strpos($st,'not')===0) $rowClass = 'table-danger';
              echo '<tr class="'. $rowClass .'">'
                 .'<td><input type="checkbox" class="opchk" name="ids[]" value="'.(int)$r['id'].'"></td>'
                 .'<td>'.htmlspecialchars($name).' <small class="text-muted">('.htmlspecialchars($r['student_id']).')</small></td>'
                 .'<td>'.htmlspecialchars($r['exit_date'].' '.$r['exit_time']).'</td>'
                 .'<td>'.htmlspecialchars($r['return_date'].' '.$r['return_time']).'</td>'
                 .'<td>'.htmlspecialchars($r['reason']).'</td>'
                 .'<td>'.htmlspecialchars($r['onpeak_request_status'] ?: 'Pending').'</td>'
                 .'</tr>';
            }
            mysqli_free_result($rs);
          } else {
            echo '<tr><td colspan="6" class="text-center text-muted">No requests found</td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </form>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
