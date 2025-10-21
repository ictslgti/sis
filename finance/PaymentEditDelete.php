<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_roles(['ACC']);

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// Flash helpers
$flash_ok = isset($_SESSION['flash_ok']) ? $_SESSION['flash_ok'] : '';
$flash_err = isset($_SESSION['flash_err']) ? $_SESSION['flash_err'] : '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
  $pid = isset($_POST['pays_id']) ? (int)$_POST['pays_id'] : 0;
  if (!$token_ok) {
    $_SESSION['flash_err'] = 'Invalid CSRF token';
  } elseif ($pid <= 0) {
    $_SESSION['flash_err'] = 'Invalid payment ID';
  } else {
    $st = mysqli_prepare($con, 'DELETE FROM `pays` WHERE `pays_id` = ?');
    if ($st) {
      mysqli_stmt_bind_param($st, 'i', $pid);
      if (mysqli_stmt_execute($st)) {
        $_SESSION['flash_ok'] = 'Payment deleted: ID '.$pid;
      } else {
        $_SESSION['flash_err'] = 'Delete failed: '.h(mysqli_error($con));
      }
      mysqli_stmt_close($st);
    } else {
      $_SESSION['flash_err'] = 'Database error';
    }
  }
  header('Location: '.$base.'/finance/PaymentEditDelete.php');
  exit;
}

// Filters
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-t');
$sid  = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$dept = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$ptype= isset($_GET['payment_type']) ? trim($_GET['payment_type']) : '';
$preas= isset($_GET['payment_reason']) ? trim($_GET['payment_reason']) : '';

// Load departments for filter
$departments = [];
if ($rs = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name')) {
  while ($row = mysqli_fetch_assoc($rs)) { $departments[] = $row; }
  mysqli_free_result($rs);
}

// Build query for listing (limit rows for safety)
$where = [];
$where[] = "p.pays_date BETWEEN '".mysqli_real_escape_string($con,$from)."' AND '".mysqli_real_escape_string($con,$to)."'";
if ($sid !== '')  { $where[] = "TRIM(p.student_id)='".mysqli_real_escape_string($con,$sid)."'"; }
if ($dept !== '') { $where[] = "UPPER(TRIM(p.pays_department))=UPPER(TRIM('".mysqli_real_escape_string($con,$dept)."'))"; }
if ($ptype !== ''){ $where[] = "UPPER(TRIM(p.payment_type))=UPPER(TRIM('".mysqli_real_escape_string($con,$ptype)."'))"; }
if ($preas !== ''){ $where[] = "UPPER(TRIM(p.payment_reason))=UPPER(TRIM('".mysqli_real_escape_string($con,$preas)."'))"; }
$wsql = implode(' AND ', $where);

$sql = "SELECT p.pays_id, p.student_id, s.student_fullname, p.payment_type, p.payment_reason, p.pays_amount, p.pays_qty, (p.pays_amount*p.pays_qty) AS total, p.pays_date, p.pays_department, d.department_name
        FROM pays p
        LEFT JOIN student s ON s.student_id = p.student_id
        LEFT JOIN department d ON d.department_id = p.pays_department
        WHERE $wsql
        ORDER BY p.pays_date DESC, p.pays_id DESC
        LIMIT 200";

$rows = [];
if ($res = mysqli_query($con, $sql)) {
  while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
  mysqli_free_result($res);
}

$title = 'Edit/Delete Payments | SLGTI';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <h3 class="mb-3"><i class="fas fa-edit text-primary mr-2"></i> Edit/Delete Payments</h3>

  <?php if ($flash_ok): ?><div class="alert alert-success"><?php echo h($flash_ok); ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger"><?php echo h($flash_err); ?></div><?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="form-row align-items-end">
        <div class="form-group col-md-3">
          <label class="small text-muted">From</label>
          <input type="date" name="from" class="form-control" value="<?php echo h($from); ?>" required>
        </div>
        <div class="form-group col-md-3">
          <label class="small text-muted">To</label>
          <input type="date" name="to" class="form-control" value="<?php echo h($to); ?>" required>
        </div>
        <div class="form-group col-md-3">
          <label class="small text-muted">Student ID</label>
          <input type="text" name="student_id" class="form-control" value="<?php echo h($sid); ?>" placeholder="Optional">
        </div>
        <div class="form-group col-md-3">
          <label class="small text-muted">Department</label>
          <select name="department_id" class="form-control">
            <option value="">-- All --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?php echo h($d['department_id']); ?>" <?php echo ($dept===$d['department_id'])?'selected':''; ?>><?php echo h($d['department_id'].' - '.$d['department_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label class="small text-muted">Payment Type</label>
          <input type="text" name="payment_type" class="form-control" value="<?php echo h($ptype); ?>" placeholder="Optional">
        </div>
        <div class="form-group col-md-3">
          <label class="small text-muted">Payment Reason</label>
          <input type="text" name="payment_reason" class="form-control" value="<?php echo h($preas); ?>" placeholder="Optional">
        </div>
        <div class="form-group col-md-12 text-right">
          <button class="btn btn-primary"><i class="fas fa-sync-alt mr-1"></i> Apply</button>
          <a class="btn btn-secondary" href="<?php echo $base; ?>/finance/PaymentEditDelete.php"><i class="fas fa-undo mr-1"></i> Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover">
          <thead class="thead-light">
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>Student ID</th>
              <th>Name</th>
              <th>Department</th>
              <th>Type</th>
              <th>Reason</th>
              <th class="text-right">Amount</th>
              <th class="text-right">Qty</th>
              <th class="text-right">Total</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['pays_id']; ?></td>
                <td><?php echo h($r['pays_date']); ?></td>
                <td><?php echo h($r['student_id']); ?></td>
                <td><?php echo h($r['student_fullname']); ?></td>
                <td><?php echo h(($r['pays_department'] ?: '').(($r['department_name'])?(' - '.$r['department_name']):'')); ?></td>
                <td><?php echo h($r['payment_type']); ?></td>
                <td><?php echo h($r['payment_reason']); ?></td>
                <td class="text-right"><?php echo number_format((float)$r['pays_amount'],2); ?></td>
                <td class="text-right"><?php echo (int)$r['pays_qty']; ?></td>
                <td class="text-right"><?php echo number_format((float)$r['total'],2); ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>/payment/Update_Payment.php?upt=<?php echo (int)$r['pays_id']; ?>" title="Edit">
                    <i class="far fa-edit"></i>
                  </a>
                  <form method="post" action="" class="d-inline" onsubmit="return confirm('Delete payment ID <?php echo (int)$r['pays_id']; ?>? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="pays_id" value="<?php echo (int)$r['pays_id']; ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" title="Delete">
                      <i class="far fa-trash-alt"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="11" class="text-center text-muted">No data</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="small text-muted">Showing up to 200 latest records.</div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
