<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Access: ACC, FIN, MA4, ADM
require_roles(['ACC','FIN','MA4','ADM']);
$is_ma4 = isset($_SESSION['user_type']) && strtoupper(trim($_SESSION['user_type'])) === 'MA4';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Filters
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to   = isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'])   ? $_GET['to']   : date('Y-m-t');
$dept = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$ptype= isset($_GET['payment_type']) ? trim($_GET['payment_type']) : '';
$preas= isset($_GET['payment_reason']) ? trim($_GET['payment_reason']) : '';
// MA4: lock to StudentCharges / BusSeason (no spaces)
if ($is_ma4) { $ptype = 'StudentCharges'; $preas = 'BusSeason'; }
$sid  = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$group= isset($_GET['group']) && in_array($_GET['group'], ['student','department','type','detailed'], true) ? $_GET['group'] : 'detailed';
$export = isset($_GET['export']) && $_GET['export'] === '1';

// Data sources
// Departments
$departments = [];
if ($rs = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($rs)) { $departments[] = $row; }
  mysqli_free_result($rs);
}
// Payment types (from pays within date and current dept/student filters)
$paymentTypes = [];
{
  $optsWhere = [];
  $optsWhere[] = "p.pays_date BETWEEN '".mysqli_real_escape_string($con,$from)."' AND '".mysqli_real_escape_string($con,$to)."'";
  if ($dept !== '') { $optsWhere[] = "UPPER(TRIM(p.pays_department))=UPPER(TRIM('".mysqli_real_escape_string($con,$dept)."'))"; }
  if ($sid !== '')  { $optsWhere[] = "TRIM(p.student_id)='".mysqli_real_escape_string($con,$sid)."'"; }
  $ow = implode(' AND ', $optsWhere);
  $q = "SELECT DISTINCT p.payment_type FROM pays p WHERE $ow AND p.payment_type<>'' ORDER BY p.payment_type";
  if ($rs = mysqli_query($con, $q)) {
    while ($row = mysqli_fetch_assoc($rs)) { if (!empty($row['payment_type'])) $paymentTypes[] = $row['payment_type']; }
    mysqli_free_result($rs);
  }
}

// Build base query
$where = [];
$where[] = "p.pays_date BETWEEN '".mysqli_real_escape_string($con,$from)."' AND '".mysqli_real_escape_string($con,$to)."'";
if ($dept !== '') {
  $where[] = "UPPER(TRIM(p.pays_department))=UPPER(TRIM('".mysqli_real_escape_string($con,$dept)."'))";
}
if ($ptype !== '') {
  if ($is_ma4) {
    // Accept both with/without space variants
    $t1 = mysqli_real_escape_string($con, 'StudentCharges');
    $t2 = mysqli_real_escape_string($con, 'Student Charges');
    $where[] = "UPPER(TRIM(p.payment_type)) IN (UPPER(TRIM('$t1')), UPPER(TRIM('$t2')))";
  } else {
    $where[] = "UPPER(TRIM(p.payment_type))=UPPER(TRIM('".mysqli_real_escape_string($con,$ptype)."'))";
  }
}
if ($preas !== '') {
  if ($is_ma4) {
    $r1 = mysqli_real_escape_string($con, 'BusSeason');
    $r2 = mysqli_real_escape_string($con, 'Bus Season');
    $where[] = "UPPER(TRIM(p.payment_reason)) IN (UPPER(TRIM('$r1')), UPPER(TRIM('$r2')))";
  } else {
    $where[] = "UPPER(TRIM(p.payment_reason))=UPPER(TRIM('".mysqli_real_escape_string($con,$preas)."'))";
  }
}
if ($sid !== '') {
  $where[] = "TRIM(p.student_id)='".mysqli_real_escape_string($con,$sid)."'";
}
$wsql = implode(' AND ', $where);

// Detailed rows (base dataset)
$sql = "SELECT p.student_id, s.student_fullname, p.payment_type, p.payment_reason,
               p.pays_amount, p.pays_qty, (p.pays_amount * p.pays_qty) AS total,
               p.pays_date, p.pays_department, d.department_name
        FROM pays p
        LEFT JOIN student s ON s.student_id = p.student_id
        LEFT JOIN department d ON d.department_id = p.pays_department
        WHERE $wsql
        ORDER BY p.pays_date ASC, p.student_id ASC";

$res = mysqli_query($con, $sql);
$rows = [];
if ($res) { while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; } }

// Aggregations
$agg = [];
if ($group === 'student') {
  foreach ($rows as $r) {
    $k = $r['student_id'];
    if (!isset($agg[$k])) { $agg[$k] = ['student_id'=>$k,'student_fullname'=>$r['student_fullname'],'amount'=>0.0,'qty'=>0,'total'=>0.0]; }
    $agg[$k]['amount'] += (float)$r['pays_amount'];
    $agg[$k]['qty']    += (int)$r['pays_qty'];
    $agg[$k]['total']  += (float)$r['total'];
  }
}
if ($group === 'department') {
  foreach ($rows as $r) {
    $k = $r['pays_department'];
    if (!isset($agg[$k])) { $agg[$k] = ['department_id'=>$k,'department_name'=>$r['department_name'],'amount'=>0.0,'qty'=>0,'total'=>0.0]; }
    $agg[$k]['amount'] += (float)$r['pays_amount'];
    $agg[$k]['qty']    += (int)$r['pays_qty'];
    $agg[$k]['total']  += (float)$r['total'];
  }
}
if ($group === 'type') {
  foreach ($rows as $r) {
    $k = $r['payment_type'].' | '.$r['payment_reason'];
    if (!isset($agg[$k])) { $agg[$k] = ['label'=>$k,'amount'=>0.0,'qty'=>0,'total'=>0.0]; }
    $agg[$k]['amount'] += (float)$r['pays_amount'];
    $agg[$k]['qty']    += (int)$r['pays_qty'];
    $agg[$k]['total']  += (float)$r['total'];
  }
}

if ($export) {
  // Build HTML table and send as Excel
  $html = "<table border='1'>";
  $html .= "<tr><th colspan='6' style='text-align:left'>Payments Summary | From ".h($from)." to ".h($to)."</th></tr>";
  if ($group === 'detailed') {
    $html .= "<tr><th>Date</th><th>Student ID</th><th>Name</th><th>Department</th><th>Type</th><th>Reason</th><th>Amount</th><th>Qty</th><th>Total</th></tr>";
    foreach ($rows as $r) {
      $html .= '<tr>'
        .'<td>'.h($r['pays_date']).'</td>'
        .'<td>'.h($r['student_id']).'</td>'
        .'<td>'.h($r['student_fullname']).'</td>'
        .'<td>'.h(($r['pays_department'] ?: '').(($r['department_name'])?(' - '.$r['department_name']):'')).'</td>'
        .'<td>'.h($r['payment_type']).'</td>'
        .'<td>'.h($r['payment_reason']).'</td>'
        .'<td>'.number_format((float)$r['pays_amount'],2).'</td>'
        .'<td>'.(int)$r['pays_qty'].'</td>'
        .'<td>'.number_format((float)$r['total'],2).'</td>'
        .'</tr>';
    }
  } elseif ($group === 'student') {
    $html .= "<tr><th>Student ID</th><th>Name</th><th>Amount</th><th>Qty</th><th>Total</th></tr>";
    foreach ($agg as $a) {
      $html .= '<tr>'
        .'<td>'.h($a['student_id']).'</td>'
        .'<td>'.h($a['student_fullname']).'</td>'
        .'<td>'.number_format((float)$a['amount'],2).'</td>'
        .'<td>'.(int)$a['qty'].'</td>'
        .'<td>'.number_format((float)$a['total'],2).'</td>'
        .'</tr>';
    }
  } elseif ($group === 'department') {
    $html .= "<tr><th>Department</th><th>Amount</th><th>Qty</th><th>Total</th></tr>";
    foreach ($agg as $k=>$a) {
      $label = ($a['department_id'] ?: '').(($a['department_name'])?(' - '.$a['department_name']):'');
      $html .= '<tr>'
        .'<td>'.h($label).'</td>'
        .'<td>'.number_format((float)$a['amount'],2).'</td>'
        .'<td>'.(int)$a['qty'].'</td>'
        .'<td>'.number_format((float)$a['total'],2).'</td>'
        .'</tr>';
    }
  } else { // type
    $html .= "<tr><th>Payment Type | Reason</th><th>Amount</th><th>Qty</th><th>Total</th></tr>";
    foreach ($agg as $a) {
      $html .= '<tr>'
        .'<td>'.h($a['label']).'</td>'
        .'<td>'.number_format((float)$a['amount'],2).'</td>'
        .'<td>'.(int)$a['qty'].'</td>'
        .'<td>'.number_format((float)$a['total'],2).'</td>'
        .'</tr>';
    }
  }
  $html .= "</table>";

  // Export headers
  while (ob_get_level() > 0) { ob_end_clean(); }
  header('Content-Type: application/vnd.ms-excel; charset=utf-8');
  header('Content-Disposition: attachment; filename="payments_summary_'.date('Ymd_His').'.xls"');
  header('Pragma: public');
  header('Cache-Control: no-store, no-cache, must-revalidate');
  echo $html; exit;
}

$sumAmount = 0.0; $sumQty = 0; $sumTotal = 0.0;
if ($group === 'detailed') {
  foreach ($rows as $r) { $sumAmount += (float)$r['pays_amount']; $sumQty += (int)$r['pays_qty']; $sumTotal += (float)$r['total']; }
} elseif ($group === 'student') {
  foreach ($agg as $a) { $sumAmount += (float)$a['amount']; $sumQty += (int)$a['qty']; $sumTotal += (float)$a['total']; }
} elseif ($group === 'department') {
  foreach ($agg as $a) { $sumAmount += (float)$a['amount']; $sumQty += (int)$a['qty']; $sumTotal += (float)$a['total']; }
} else { // type
  foreach ($agg as $a) { $sumAmount += (float)$a['amount']; $sumQty += (int)$a['qty']; $sumTotal += (float)$a['total']; }
}

$title = 'Payments Summary | SLGTI';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <h3 class="mb-3"><i class="fas fa-file-invoice-dollar text-primary mr-2"></i> Payments Summary</h3>

  <div class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0 text-white"><i class="fas fa-filter mr-2 text-white"></i> Filters</h5>
    </div>
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
          <label class="small text-muted">Department</label>
          <select name="department_id" class="form-control">
            <option value="">-- All --</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?php echo h($d['department_id']); ?>" <?php echo ($dept===$d['department_id'])?'selected':''; ?>><?php echo h($d['department_id'].' - '.$d['department_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label class="small text-muted">Student ID</label>
          <input type="text" name="student_id" class="form-control" value="<?php echo h($sid); ?>" placeholder="Optional">
        </div>
        <?php if ($is_ma4): ?>
          <div class="form-group col-md-3">
            <label class="small text-muted">Payment Type</label>
            <input type="text" class="form-control" value="StudentCharges" readonly>
          </div>
          <div class="form-group col-md-3">
            <label class="small text-muted">Payment Reason</label>
            <input type="text" class="form-control" value="BusSeason" readonly>
          </div>
        <?php else: ?>
          <div class="form-group col-md-3">
            <label class="small text-muted">Payment Type</label>
            <select name="payment_type" class="form-control" onchange="document.getElementById('preason').value=''; this.form.submit()">
              <option value="">-- All --</option>
              <?php foreach ($paymentTypes as $pt): ?>
                <option value="<?php echo h($pt); ?>" <?php echo ($ptype===$pt)?'selected':''; ?>><?php echo h($pt); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label class="small text-muted">Payment Reason</label>
            <select name="payment_reason" id="preason" class="form-control">
              <option value="">-- All --</option>
              <?php if ($ptype !== ''): ?>
                <?php
                  // Load reasons from pays within same date/dept/student filters for selected type
                  $subW = [];
                  $subW[] = "p.pays_date BETWEEN '".mysqli_real_escape_string($con,$from)."' AND '".mysqli_real_escape_string($con,$to)."'";
                  if ($dept !== '') { $subW[] = "UPPER(TRIM(p.pays_department))=UPPER(TRIM('".mysqli_real_escape_string($con,$dept)."'))"; }
                  if ($sid !== '')  { $subW[] = "TRIM(p.student_id)='".mysqli_real_escape_string($con,$sid)."'"; }
                  $subW[] = "UPPER(TRIM(p.payment_type))=UPPER(TRIM('".mysqli_real_escape_string($con,$ptype)."'))";
                  $subSql = implode(' AND ', $subW);
                  $qr = "SELECT DISTINCT p.payment_reason FROM pays p WHERE $subSql AND p.payment_reason<>'' ORDER BY p.payment_reason";
                  if ($rsr = mysqli_query($con, $qr)) {
                    while ($rr = mysqli_fetch_assoc($rsr)) {
                      $val = $rr['payment_reason'];
                      echo '<option value="'.h($val).'"'.(($preas===$val)?' selected':'').'>'.h($val).'</option>';
                    }
                    mysqli_free_result($rsr);
                  }
                ?>
              <?php endif; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="form-group col-md-3">
          <label class="small text-muted">Group By</label>
          <select name="group" class="form-control">
            <option value="detailed" <?php echo $group==='detailed'?'selected':''; ?>>Detailed</option>
            <option value="student"  <?php echo $group==='student'?'selected':''; ?>>Student</option>
            <option value="department" <?php echo $group==='department'?'selected':''; ?>>Department</option>
            <option value="type" <?php echo $group==='type'?'selected':''; ?>>Payment Type/Reason</option>
          </select>
        </div>
        <div class="form-group col-md-12 text-right">
          <button class="btn btn-primary"><i class="fas fa-sync-alt mr-1"></i> Apply</button>
          <a class="btn btn-success" href="<?php echo $base; ?>/finance/PaymentsSummary.php?export=1&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?><?php echo $dept?('&department_id='.urlencode($dept)) : ''; ?><?php echo $sid?('&student_id='.urlencode($sid)) : ''; ?><?php echo $ptype?('&payment_type='.urlencode($ptype)) : ''; ?><?php echo $preas?('&payment_reason='.urlencode($preas)) : ''; ?>&group=<?php echo urlencode($group); ?>" title="Export to Excel"><i class="fas fa-file-excel mr-1"></i> Export</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 text-white"><i class="fas fa-table mr-2 text-white"></i> Payment Summary Results</h5>
    </div>
    <div class="card-body">
      <?php if ($group === 'detailed'): ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th style="color: #ffffff !important;">Date</th>
                <th style="color: #ffffff !important;">Student ID</th>
                <th style="color: #ffffff !important;">Name</th>
                <th style="color: #ffffff !important;">Department</th>
                <th style="color: #ffffff !important;">Type</th>
                <th style="color: #ffffff !important;">Reason</th>
                <th class="text-right" style="color: #ffffff !important;">Amount</th>
                <th class="text-right" style="color: #ffffff !important;">Qty</th>
                <th class="text-right" style="color: #ffffff !important;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($rows)): foreach ($rows as $r): ?>
                <tr>
                  <td><?php echo h($r['pays_date']); ?></td>
                  <td><?php echo h($r['student_id']); ?></td>
                  <td><?php echo h($r['student_fullname']); ?></td>
                  <td><?php echo h(($r['pays_department'] ?: '').(($r['department_name'])?(' - '.$r['department_name']):'')); ?></td>
                  <td><?php echo h($r['payment_type']); ?></td>
                  <td><?php echo h($r['payment_reason']); ?></td>
                  <td class="text-right"><?php echo number_format((float)$r['pays_amount'],2); ?></td>
                  <td class="text-right"><?php echo (int)$r['pays_qty']; ?></td>
                  <td class="text-right"><?php echo number_format((float)$r['total'],2); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot>
              <tr class="font-weight-bold">
                <td colspan="6" class="text-right">Grand Total</td>
                <td class="text-right"><?php echo number_format((float)$sumAmount,2); ?></td>
                <td class="text-right"><?php echo (int)$sumQty; ?></td>
                <td class="text-right"><?php echo number_format((float)$sumTotal,2); ?></td>
              </tr>
            </tfoot>
            <?php endif; ?>
          </table>
        </div>
      <?php elseif ($group === 'student'): ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th style="color: #ffffff !important;">Student ID</th>
                <th style="color: #ffffff !important;">Name</th>
                <th class="text-right" style="color: #ffffff !important;">Amount</th>
                <th class="text-right" style="color: #ffffff !important;">Qty</th>
                <th class="text-right" style="color: #ffffff !important;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($agg)): foreach ($agg as $a): ?>
                <tr>
                  <td><?php echo h($a['student_id']); ?></td>
                  <td><?php echo h($a['student_fullname']); ?></td>
                  <td class="text-right"><?php echo number_format((float)$a['amount'],2); ?></td>
                  <td class="text-right"><?php echo (int)$a['qty']; ?></td>
                  <td class="text-right"><?php echo number_format((float)$a['total'],2); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
            <?php if (!empty($agg)): ?>
            <tfoot>
              <tr class="font-weight-bold">
                <td class="text-right">Grand Total</td>
                <td></td>
                <td class="text-right"><?php echo number_format((float)$sumAmount,2); ?></td>
                <td class="text-right"><?php echo (int)$sumQty; ?></td>
                <td class="text-right"><?php echo number_format((float)$sumTotal,2); ?></td>
              </tr>
            </tfoot>
            <?php endif; ?>
          </table>
        </div>
      <?php elseif ($group === 'department'): ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th style="color: #ffffff !important;">Department</th>
                <th class="text-right" style="color: #ffffff !important;">Amount</th>
                <th class="text-right" style="color: #ffffff !important;">Qty</th>
                <th class="text-right" style="color: #ffffff !important;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($agg)): foreach ($agg as $k=>$a): ?>
                <tr>
                  <td><?php echo h(($a['department_id'] ?: '').(($a['department_name'])?(' - '.$a['department_name']):'')); ?></td>
                  <td class="text-right"><?php echo number_format((float)$a['amount'],2); ?></td>
                  <td class="text-right"><?php echo (int)$a['qty']; ?></td>
                  <td class="text-right"><?php echo number_format((float)$a['total'],2); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
            <?php if (!empty($agg)): ?>
            <tfoot>
              <tr class="font-weight-bold">
                <td class="text-right">Grand Total</td>
                <td class="text-right"><?php echo number_format((float)$sumAmount,2); ?></td>
                <td class="text-right"><?php echo (int)$sumQty; ?></td>
                <td class="text-right"><?php echo number_format((float)$sumTotal,2); ?></td>
              </tr>
            </tfoot>
            <?php endif; ?>
          </table>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th style="color: #ffffff !important;">Payment Type | Reason</th>
                <th class="text-right" style="color: #ffffff !important;">Amount</th>
                <th class="text-right" style="color: #ffffff !important;">Qty</th>
                <th class="text-right" style="color: #ffffff !important;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($agg)): foreach ($agg as $a): ?>
                <tr>
                  <td><?php echo h($a['label']); ?></td>
                  <td class="text-right"><?php echo number_format((float)$a['amount'],2); ?></td>
                  <td class="text-right"><?php echo (int)$a['qty']; ?></td>
                  <td class="text-right"><?php echo number_format((float)$a['total'],2); ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted">No data</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
  /* White and Blue Theme - Payments Summary */
  
  /* Page Header */
  h3 {
    color: #1e3a8a;
    font-weight: 600;
  }
  
  /* Card Styling */
  .card {
    border: 1px solid #e0e7ff;
    box-shadow: 0 2px 4px rgba(30, 58, 138, 0.1);
    border-radius: 0.5rem;
  }
  
  .card-header {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    color: #ffffff !important;
    border-bottom: 2px solid #1e40af;
    padding: 1rem 1.25rem;
    font-weight: 600;
  }
  
  .card-header * {
    color: #ffffff !important;
  }
  
  .card-header h1,
  .card-header h2,
  .card-header h3,
  .card-header h4,
  .card-header h5,
  .card-header h6 {
    color: #ffffff !important;
  }
  
  .card-header i,
  .card-header .fa,
  .card-header .fas,
  .card-header .far,
  .card-header .icon {
    color: #ffffff !important;
  }
  
  .card-header .text-white,
  .card-header span,
  .card-header div,
  .card-header label {
    color: #ffffff !important;
  }
  
  .card-body {
    background-color: #ffffff;
    padding: 1.5rem;
  }
  
  /* Form Elements */
  .form-group label {
    color: #1e3a8a;
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
  }
  
  .form-control {
    border: 1px solid #cbd5e1;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.9375rem;
    transition: all 0.2s ease;
  }
  
  .form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    outline: 0;
  }
  
  select.form-control {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%231e3a8a' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    padding-right: 2.5rem;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
  }
  
  select.form-control:focus {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%233b82f6' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
  }
  
  /* Buttons */
  .btn-primary {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    border: none;
    color: #ffffff;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
  }
  
  .btn-primary:hover {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(30, 58, 138, 0.3);
  }
  
  .btn-success {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    border: none;
    color: #ffffff;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
  }
  
  .btn-success:hover {
    background: linear-gradient(135deg, #047857 0%, #059669 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(5, 150, 105, 0.3);
  }
  
  /* Table Styling */
  .table {
    background-color: #ffffff;
    border-collapse: separate;
    border-spacing: 0;
  }
  
  .table thead th {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%) !important;
    color: #ffffff !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8125rem;
    letter-spacing: 0.5px;
    padding: 0.875rem 0.75rem;
    border: none;
    border-bottom: 2px solid #1e40af;
  }
  
  .table thead th * {
    color: #ffffff !important;
  }
  
  .table thead th:first-child {
    border-top-left-radius: 0.5rem;
  }
  
  .table thead th:last-child {
    border-top-right-radius: 0.5rem;
  }
  
  /* Override thead-light class */
  .table thead.thead-light th {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%) !important;
    color: #ffffff !important;
  }
  
  .table tbody td {
    padding: 0.875rem 0.75rem;
    border-bottom: 1px solid #e0e7ff;
    color: #1e293b;
    vertical-align: middle;
  }
  
  .table tbody tr {
    transition: background-color 0.2s ease;
  }
  
  .table tbody tr:hover {
    background-color: #f0f9ff;
  }
  
  .table tbody tr:nth-child(even) {
    background-color: #f8fafc;
  }
  
  .table tbody tr:nth-child(even):hover {
    background-color: #f0f9ff;
  }
  
  .table tfoot {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  }
  
  .table tfoot td {
    border-top: 2px solid #3b82f6;
    font-weight: 700;
    color: #1e3a8a;
    padding: 1rem 0.75rem;
  }
  
  .table-bordered {
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    overflow: hidden;
  }
  
  .table-bordered thead th {
    border: none;
  }
  
  .table-bordered tbody td {
    border-right: 1px solid #e0e7ff;
  }
  
  .table-bordered tbody td:last-child {
    border-right: none;
  }
  
  /* Text Colors */
  .text-primary {
    color: #1e3a8a !important;
  }
  
  .text-muted {
    color: #64748b !important;
  }
  
  /* Input Group */
  .input-group-text {
    background-color: #eff6ff;
    border: 1px solid #cbd5e1;
    color: #1e3a8a;
  }
  
  /* Breadcrumb */
  .breadcrumb {
    background-color: #ffffff;
    border: 1px solid #e0e7ff;
  }
  
  .breadcrumb-item a {
    color: #3b82f6;
  }
  
  .breadcrumb-item.active {
    color: #1e3a8a;
  }
  
  /* Responsive */
  @media (max-width: 768px) {
    .card-body {
      padding: 1rem;
    }
    
    .table {
      font-size: 0.875rem;
    }
    
    .table thead th,
    .table tbody td {
      padding: 0.5rem;
    }
  }
  
  /* Empty State */
  .text-center.text-muted {
    color: #94a3b8 !important;
    font-style: italic;
    padding: 2rem !important;
  }
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
