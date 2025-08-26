<?php
// finance/RegistrationPaymentReport.php — Finance Officer: Registration payments filtered report
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_login();
require_roles('FIN');
if (!headers_sent()) { ob_start(); }

// Inputs
$start  = isset($_GET['start']) ? trim($_GET['start']) : '';
$end    = isset($_GET['end']) ? trim($_GET['end']) : '';
$method = isset($_GET['method']) ? trim($_GET['method']) : '';// ''|SLGTI|Bank (case-insensitive)
$export = isset($_GET['export']) ? strtolower(trim($_GET['export'])) : '';// ''|csv|excel|xls|pdf

// Defaults: last 30 days
if ($start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
  $start = date('Y-m-d', strtotime('-30 days'));
}
if ($end === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
  $end = date('Y-m-d');
}

// Build SQL (filter by date range and optional method). Include qty*amount as line total
$sql = "SELECT p.pays_id, p.student_id, p.payment_type, p.payment_reason, COALESCE(p.payment_method,'') AS payment_method, p.pays_note, p.pays_amount, p.pays_qty, p.pays_date, (p.pays_amount * p.pays_qty) AS line_total, p.pays_department FROM pays p WHERE p.approved = 1 AND p.pays_date BETWEEN ? AND ?";
$params = [$start, $end];
$ptypes = 'ss';

if ($method !== '') {
  // Normalize to uppercase and compare case-insensitively
  $normMethod = strtoupper($method);
  $sql .= " AND UPPER(p.payment_method) = ?";
  $params[] = $normMethod;
  $ptypes .= 's';
}

$sql .= " ORDER BY p.pays_date ASC, p.pays_id ASC";

// Execute
$data = [];
$total_amount = 0.0;
$total_count = 0;
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st, $ptypes, ...$params);
  if (mysqli_stmt_execute($st)) {
    $res = mysqli_stmt_get_result($st);
    if ($res) {
      while ($r = mysqli_fetch_assoc($res)) {
        $data[] = $r;
        $total_amount += (float)$r['line_total'];
        $total_count++;
      }
      mysqli_free_result($res);
    }
  }
  mysqli_stmt_close($st);
}

// CSV/Excel export
if (in_array($export, ['csv','excel','xls'], true)) {
  // Clear any previous output to avoid header issues
  if (function_exists('ob_get_length') && ob_get_length()) { @ob_clean(); }
  $filename = 'registration_payments_' . $start . '_to_' . $end . ($method?('_'.strtolower($method)):'') . '.csv';
  // Use Excel-friendly headers when export is excel/xls
  if ($export === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
  } else {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  }
  header('Content-Disposition: attachment; filename=' . $filename);
  header('Pragma: no-cache');
  header('Expires: 0');
  $out = fopen('php://output', 'w');
  // Prepend UTF-8 BOM for Excel compatibility
  fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, ['Pays ID','Date','Student ID','Type','Reason','Method','Note','Amount','Qty','Line Total','Department']);
  foreach ($data as $row) {
    fputcsv($out, [
      $row['pays_id'],
      $row['pays_date'],
      $row['student_id'],
      $row['payment_type'],
      $row['payment_reason'],
      $row['payment_method'] !== '' ? $row['payment_method'] : '-',
      $row['pays_note'],
      number_format((float)$row['pays_amount'], 2, '.', ''),
      (int)$row['pays_qty'],
      number_format((float)$row['line_total'], 2, '.', ''),
      $row['pays_department'],
    ]);
  }
  // totals row
  fputcsv($out, ['','','','','','','','', 'Total', number_format($total_amount, 2, '.', ''), '']);
  fclose($out);
  if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
  exit;
}

// PDF export (TCPDF)
if ($export === 'pdf') {
  $tcpdf = __DIR__ . '/../library/pdf/tcpdf.php';
  if (!is_readable($tcpdf)) {
    if (!headers_sent()) {
      header('Content-Type: text/plain; charset=UTF-8');
    }
    echo "PDF library not found at: " . $tcpdf . "\nPlease ensure TCPDF is installed in library/pdf.";
    if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
    exit;
  }
  require_once $tcpdf;
  if (!class_exists('TCPDF')) {
    if (!headers_sent()) {
      header('Content-Type: text/plain; charset=UTF-8');
    }
    echo "TCPDF class not available after include. Check library/pdf/tcpdf.php integrity.";
    if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
    exit;
  }
  $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
  $pdf->SetCreator('SLGTI MIS');
  $pdf->SetAuthor('Finance');
  $pdf->SetTitle('Registration Payments Report');
  $pdf->SetMargins(10, 10, 10);
  $pdf->AddPage();
  $title = 'Registration Payments Report';
  $sub = 'Date: ' . htmlspecialchars($start) . ' to ' . htmlspecialchars($end) . ($method?(' | Method: '.htmlspecialchars($method)):'');
  $pdf->SetFont('helvetica', 'B', 14);
  $pdf->Write(0, $title, '', 0, 'L', true, 0, false, false, 0);
  $pdf->SetFont('helvetica', '', 10);
  $pdf->Write(0, $sub, '', 0, 'L', true, 0, false, false, 0);

  // Table header
  $html = '<table border="1" cellpadding="3" cellspacing="0">'
        . '<tr style="font-weight:bold; background-color:#f0f0f0">'
        . '<th width="6%">ID</th><th width="10%">Date</th><th width="12%">Student</th><th width="10%">Type</th><th width="18%">Reason</th><th width="8%">Method</th><th width="18%">Note</th><th width="8%">Amount</th><th width="5%">Qty</th><th width="9%">Line Total</th>'
        . '</tr>';
  foreach ($data as $row) {
    $html .= '<tr>'
          . '<td>'.htmlspecialchars($row['pays_id']).'</td>'
          . '<td>'.htmlspecialchars($row['pays_date']).'</td>'
          . '<td>'.htmlspecialchars($row['student_id']).'</td>'
          . '<td>'.htmlspecialchars($row['payment_type']).'</td>'
          . '<td>'.htmlspecialchars($row['payment_reason']).'</td>'
          . '<td>'.htmlspecialchars($row['payment_method'] !== '' ? $row['payment_method'] : '-').'</td>'
          . '<td>'.htmlspecialchars($row['pays_note']).'</td>'
          . '<td style="text-align:right">'.number_format((float)$row['pays_amount'],2).'</td>'
          . '<td style="text-align:right">'.(int)$row['pays_qty'].'</td>'
          . '<td style="text-align:right">'.number_format((float)$row['line_total'],2).'</td>'
          . '</tr>';
  }
  $html .= '<tr style="font-weight:bold;"><td colspan="9" align="right">Total</td><td style="text-align:right">'.number_format($total_amount,2).'</td></tr>';
  $html .= '</table>';
  $pdf->writeHTML($html, true, false, true, false, '');
  $pdf->Output('registration_payments_'.$start.'_to_'.$end.($method?('_'.strtolower($method)):'').'.pdf', 'I');
  if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
  exit;
}

require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<style>
  @media print {
    #filters, #actions, .sidebar, .navbar, .footer { display: none !important; }
    .card { border: none; }
  }
</style>
<div class="container mt-3">
  <h2 class="text-center">Finance: Registration Payments Report</h2>
  <p class="text-center">Filter by date and payment method. Export to CSV or PDF.</p>

  <div id="filters" class="card mb-3">
    <div class="card-body">
      <form method="get" class="form-row mb-0">
        <div class="form-group col-md-3">
          <label>Start</label>
          <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start); ?>">
        </div>
        <div class="form-group col-md-3">
          <label>End</label>
          <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end); ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Payment Method</label>
          <select name="method" class="form-control">
            <option value="" <?php echo $method===''?'selected':''; ?>>All</option>
            <option value="SLGTI" <?php echo $method==='SLGTI'?'selected':''; ?>>SLGTI</option>
            <option value="Bank" <?php echo $method==='Bank'?'selected':''; ?>>Bank</option>
          </select>
        </div>
        <div id="actions" class="form-group col-md-3 align-self-end">
          <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply</button>
          <a class="btn btn-outline-secondary" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/finance/RegistrationPaymentReport.php?start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>&method=<?php echo urlencode($method); ?>&export=csv"><i class="fa fa-file-excel"></i> Excel (CSV)</a>
          <a class="btn btn-outline-secondary" href="<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/finance/RegistrationPaymentReport.php?start=<?php echo urlencode($start); ?>&end=<?php echo urlencode($end); ?>&method=<?php echo urlencode($method); ?>&export=pdf"><i class="fa fa-file-pdf"></i> PDF</a>
          <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>Student</th>
              <th>Type</th>
              <th>Reason</th>
              <th>Method</th>
              <th>Note</th>
              <th class="text-right">Amount</th>
              <th class="text-right">Qty</th>
              <th class="text-right">Line Total</th>
              <th>Department</th>
            </tr>
          </thead>
          <tbody>
            <?php
              if (count($data) > 0) {
                foreach ($data as $row) {
                  echo '<tr>';
                  echo '<td>'.htmlspecialchars($row['pays_id']).'</td>';
                  echo '<td>'.htmlspecialchars($row['pays_date']).'</td>';
                  echo '<td>'.htmlspecialchars($row['student_id']).'</td>';
                  echo '<td>'.htmlspecialchars($row['payment_type']).'</td>';
                  echo '<td>'.htmlspecialchars($row['payment_reason']).'</td>';
                  echo '<td>'.htmlspecialchars($row['payment_method'] !== '' ? $row['payment_method'] : '-').'</td>';
                  echo '<td>'.htmlspecialchars($row['pays_note']).'</td>';
                  echo '<td class="text-right">'.number_format((float)$row['pays_amount'], 2).'</td>';
                  echo '<td class="text-right">'.number_format((int)$row['pays_qty']).'</td>';
                  echo '<td class="text-right">'.number_format((float)$row['line_total'], 2).'</td>';
                  echo '<td>'.htmlspecialchars($row['pays_department']).'</td>';
                  echo '</tr>';
                }
                echo '<tr class="font-weight-bold">';
                echo '<td colspan="9" class="text-right">Total</td>';
                echo '<td class="text-right">'.number_format($total_amount, 2).'</td>';
                echo '<td></td>';
                echo '</tr>';
              } else {
                echo '<tr><td colspan="11" class="text-center">No records for the selected filters.</td></tr>';
              }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); } ?>
