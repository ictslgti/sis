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
    <div class="card-body">
      <?php if ($group === 'detailed'): ?>
        <div class="table-responsive">
          <table class="table table-sm table-bordered table-hover">
            <thead class="thead-light">
              <tr>
                <th>Date</th>
                <th>Student ID</th>
                <th>Name</th>
                <th>Department</th>
                <th>Type</th>
                <th>Reason</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total</th>
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
                <th>Student ID</th>
                <th>Name</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total</th>
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
                <th>Department</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total</th>
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
                <th>Payment Type | Reason</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total</th>
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
<?php require_once __DIR__ . '/../footer.php'; ?>
