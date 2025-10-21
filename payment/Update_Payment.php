<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_roles(['ACC']);

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Ensure optional columns exist
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `payment_method` VARCHAR(20) NULL AFTER `payment_reason`");

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// Flash helpers
$flash_ok = isset($_SESSION['flash_ok']) ? $_SESSION['flash_ok'] : '';
$flash_err = isset($_SESSION['flash_err']) ? $_SESSION['flash_err'] : '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// Load payment to edit
$pays_id = isset($_GET['upt']) ? (int)$_GET['upt'] : 0;
$pay = null;
if ($pays_id > 0) {
  $st = mysqli_prepare($con, "SELECT p.pays_id, p.student_id, s.student_fullname, p.payment_type, p.payment_reason, COALESCE(p.payment_method,'') AS payment_method, p.pays_amount, p.pays_qty, p.pays_date, p.pays_department, d.department_name FROM pays p LEFT JOIN student s ON s.student_id=p.student_id LEFT JOIN department d ON d.department_id=p.pays_department WHERE p.pays_id=?");
  if ($st) {
    mysqli_stmt_bind_param($st,'i',$pays_id);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $pay = ($rs && mysqli_num_rows($rs) === 1) ? mysqli_fetch_assoc($rs) : null;
    mysqli_stmt_close($st);
  }
}

// Payment types list (like CollectPayment)
$paymentTypes = [];
if ($r = mysqli_query($con, "SELECT DISTINCT payment_type FROM payment ORDER BY payment_type")) {
  while ($row = mysqli_fetch_assoc($r)) { if (!empty($row['payment_type'])) $paymentTypes[] = $row['payment_type']; }
  mysqli_free_result($r);
}

// Handle update (mirrors CollectPayment validation style)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='update') {
  $token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
  $pid  = isset($_POST['pays_id']) ? (int)$_POST['pays_id'] : 0;
  $ptype= trim($_POST['payment_type'] ?? '');
  $preas= trim($_POST['payment_reason'] ?? '');
  $pmeth= trim($_POST['payment_method'] ?? '');
  $amt  = trim($_POST['payment_amount'] ?? '');
  $qty  = trim($_POST['payment_qty'] ?? '1');
  $note = trim($_POST['payment_note'] ?? '');
  $pdate = trim($_POST['pays_date'] ?? '');

  $errors=[];
  if (!$token_ok) { $errors[]='Invalid CSRF token.'; }
  if ($pid <= 0) { $errors[]='Invalid payment record.'; }
  if ($ptype==='') { $errors[]='Payment Type is required.'; }
  if ($preas==='') { $errors[]='Payment Reason is required.'; }
  if ($pmeth==='') { $errors[]='Payment Method is required.'; }
  if ($amt==='' || !is_numeric($amt) || $amt<=0) { $errors[]='Valid Amount is required.'; }
  if ($qty==='' || !ctype_digit($qty) || (int)$qty<1) { $errors[]='Valid Quantity is required.'; }

  if ($pdate!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pdate)) { $errors[] = 'Invalid date.'; }

  if (!$errors) {
    $st = mysqli_prepare($con, 'UPDATE pays SET payment_type=?, payment_reason=?, payment_method=?, pays_note=?, pays_amount=?, pays_qty=?, pays_date=? WHERE pays_id=?');
    if ($st) {
      $amount = (float)$amt; $iqty=(int)$qty; $dateToUse = $pdate;
      mysqli_stmt_bind_param($st,'ssssdisi',$ptype,$preas,$pmeth,$note,$amount,$iqty,$dateToUse,$pid);
      if (mysqli_stmt_execute($st)) {
        $_SESSION['flash_ok'] = 'Payment updated successfully (ID: '.$pid.').';
        header('Location: '.$base.'/payment/Update_Payment.php?upt='.$pid);
        exit;
      } else {
        $flash_err = 'Update failed: '.h(mysqli_error($con));
      }
      mysqli_stmt_close($st);
    } else {
      $flash_err = 'Database error.';
    }
  } else {
    $flash_err = implode(' ', $errors);
  }
}

$title = 'Update Payment | SLGTI';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-white shadow-sm">
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/finance/PaymentsSummary.php">Payments</a></li>
      <li class="breadcrumb-item active" aria-current="page">Update Payment</li>
    </ol>
  </nav>
  <h3 class="mb-3"><i class="fas fa-edit text-primary mr-2"></i> Update Payment</h3>

  <?php if ($flash_ok): ?><div class="alert alert-success py-2"><?php echo h($flash_ok); ?></div><?php endif; ?>
  <?php if ($flash_err): ?><div class="alert alert-danger py-2"><?php echo h($flash_err); ?></div><?php endif; ?>

  <?php if (!$pay): ?>
    <div class="alert alert-warning">Payment record not found.</div>
  <?php else: ?>
  <div class="card">
    <div class="card-header">Payment Details</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="pays_id" value="<?php echo (int)$pay['pays_id']; ?>">

        <div class="form-row">
          <div class="form-group col-md-3">
            <label class="small text-muted">Pays ID</label>
            <input type="text" class="form-control" value="<?php echo (int)$pay['pays_id']; ?>" readonly>
          </div>
          <div class="form-group col-md-3">
            <label class="small text-muted">Student ID</label>
            <input type="text" class="form-control" value="<?php echo h($pay['student_id']); ?>" readonly>
          </div>
          <div class="form-group col-md-6">
            <label class="small text-muted">Name</label>
            <input type="text" class="form-control" value="<?php echo h($pay['student_fullname'] ?? ''); ?>" readonly>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label class="small text-muted">Department</label>
            <input type="text" class="form-control" value="<?php echo h((($pay['pays_department'] ?? '') ?: '').((isset($pay['department_name']) && $pay['department_name'])?(' - '.$pay['department_name']):'')); ?>" readonly>
          </div>
          <div class="form-group col-md-4">
            <label class="small text-muted">Date</label>
            <input type="date" class="form-control" name="pays_date" value="<?php echo h($pay['pays_date'] ?? ''); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label class="small text-muted">Payment Type</label>
            <select class="form-control" id="payment_type" name="payment_type" onchange="loadReasons(this.value)" required>
              <option value="">-- Select a Payment Type --</option>
              <?php foreach ($paymentTypes as $pt): $sel = ((($pay['payment_type'] ?? '')===$pt)?' selected':''); ?>
                <option value="<?php echo h($pt); ?>"<?php echo $sel; ?>><?php echo h($pt); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label class="small text-muted">Payment Reason</label>
            <select class="form-control" id="payment_reason" name="payment_reason" required>
              <option value="">-- Select a Payment Reason --</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label class="small text-muted">Payment Method</label>
            <select class="form-control" id="payment_method" name="payment_method" required>
              <option value="">-- Select Method --</option>
              <option value="SLGTI" <?php echo ((($pay['payment_method'] ?? '')==='SLGTI')?'selected':''); ?>>SLGTI</option>
              <option value="BANK" <?php echo ((($pay['payment_method'] ?? '')==='BANK')?'selected':''); ?>>Bank</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-3">
            <label class="small text-muted">Amount</label>
            <input type="number" min="1" step="0.01" class="form-control" name="payment_amount" value="<?php echo h($pay['pays_amount'] ?? ''); ?>" required>
          </div>
          <div class="form-group col-md-3">
            <label class="small text-muted">Quantity</label>
            <input type="number" min="1" max="50" class="form-control" name="payment_qty" value="<?php echo (int)($pay['pays_qty'] ?? 1); ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label class="small text-muted">Note</label>
            <input type="text" class="form-control" name="payment_note" value="<?php echo h($pay['pays_note'] ?? ''); ?>" placeholder="Short note (optional)">
          </div>
        </div>

        <div class="text-right">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update Payment</button>
          <a class="btn btn-outline-secondary" href="<?php echo $base; ?>/finance/PaymentEditDelete.php"><i class="fa fa-list mr-1"></i> Back</a>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
<script>
function loadReasons(val, preselect){
  var el = document.getElementById('payment_reason');
  if (!el) return;
  if (!val) { el.innerHTML = '<option value="">-- Select a Payment Reason --</option>'; return; }
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function(){
    if (xhr.readyState === 4 && xhr.status === 200) {
      el.innerHTML = xhr.responseText;
      if (preselect) {
        var opts = el.options; var target = (''+preselect).toLowerCase();
        for (var i=0;i<opts.length;i++){ if ((opts[i].value||'').toLowerCase()===target){ el.value = opts[i].value; break; } }
      }
    }
  };
  xhr.open('POST', '<?php echo $base; ?>/controller/getPaymentReason.php');
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('payment_type=' + encodeURIComponent(val));
}
// Initialize reason list for current type
(function(){
  var typeSel = document.getElementById('payment_type');
  if (!typeSel) return;
  var currentType = typeSel.value;
  if (currentType) {
    loadReasons(currentType, <?php echo json_encode($pay['payment_reason'] ?? ''); ?>);
  }
})();
</script>
