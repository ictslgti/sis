<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// FIN, ACC, MA4, and ADM can access this page
require_roles(['FIN','ACC','MA4','ADM']);
$is_ma4 = isset($_SESSION['user_type']) && strtoupper(trim($_SESSION['user_type'])) === 'MA4';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';
function url_join_base($base, $path){
  $b = rtrim((string)$base, '/');
  $p = '/' . ltrim((string)$path, '/');
  return $b . $p;
}

$messages = [];
$errors = [];

// Ensure optional columns exist in target table
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `payment_method` VARCHAR(20) NULL AFTER `payment_reason`");
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `reference_no` VARCHAR(64) NULL AFTER `pays_qty`");

// Fetch student details helper with fallback to latest enrollment when no Active/Following exists
function fetch_student_info(mysqli $con, $sid) {
  $sid = trim((string)$sid);
  if ($sid === '') return null;
  $sidEsc = mysqli_real_escape_string($con, $sid);
  
  // 1) Prefer Active/Following enrollment
  $sql1 = "SELECT s.student_id, s.student_fullname, s.student_profile_img,
                  c.department_id, d.department_name
           FROM student s
           LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
           LEFT JOIN course c ON c.course_id = se.course_id
           LEFT JOIN department d ON d.department_id = c.department_id
           WHERE s.student_id = '$sidEsc'
           ORDER BY se.student_enroll_date DESC LIMIT 1";
  if ($rs = mysqli_query($con, $sql1)) {
    if ($rs === false) {
      error_log('fetch_student_info query1 error: ' . mysqli_error($con));
    } else {
      $row = mysqli_fetch_assoc($rs);
      mysqli_free_result($rs);
      if ($row && !empty($row['student_id'])) { 
        return $row; 
      }
    }
  } else {
    error_log('fetch_student_info query1 failed: ' . mysqli_error($con));
  }
  
  // 2) Fallback: latest enrollment regardless of status
  $sql2 = "SELECT s.student_id, s.student_fullname, s.student_profile_img,
                  c.department_id, d.department_name
           FROM student s
           LEFT JOIN student_enroll se ON se.student_id = s.student_id
           LEFT JOIN course c ON c.course_id = se.course_id
           LEFT JOIN department d ON d.department_id = c.department_id
           WHERE s.student_id = '$sidEsc'
           ORDER BY se.student_enroll_date DESC LIMIT 1";
  if ($rs2 = mysqli_query($con, $sql2)) {
    if ($rs2 === false) {
      error_log('fetch_student_info query2 error: ' . mysqli_error($con));
    } else {
      $row2 = mysqli_fetch_assoc($rs2);
      mysqli_free_result($rs2);
      if ($row2 && !empty($row2['student_id'])) { 
        return $row2; 
      }
    }
  } else {
    error_log('fetch_student_info query2 failed: ' . mysqli_error($con));
  }
  
  // 3) Minimal baseline: student row only
  $sql3 = "SELECT s.student_id, s.student_fullname, s.student_profile_img 
           FROM student s 
           WHERE s.student_id = '$sidEsc' 
           LIMIT 1";
  if ($rs3 = mysqli_query($con, $sql3)) {
    if ($rs3 === false) {
      error_log('fetch_student_info query3 error: ' . mysqli_error($con));
      return null;
    } else {
      $row3 = mysqli_fetch_assoc($rs3);
      mysqli_free_result($rs3);
      if ($row3 && !empty($row3['student_id'])) {
        return $row3;
      }
    }
  } else {
    error_log('fetch_student_info query3 failed: ' . mysqli_error($con));
  }
  
  return null;
}

// PRG: handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
  $student_id = trim($_POST['student_id'] ?? '');
  $payment_type = trim($_POST['payment_type'] ?? '');
  $payment_reason = trim($_POST['payment_reason'] ?? '');
  $payment_method = trim($_POST['payment_method'] ?? '');
  $payment_amount = trim($_POST['payment_amount'] ?? '');
  $payment_qty = trim($_POST['payment_qty'] ?? '1');
  $pays_note = trim($_POST['payment_note'] ?? '');
  $reference_no = trim($_POST['reference_no'] ?? '');
  $pays_department = trim($_POST['pays_department'] ?? '');
  $pays_date = trim($_POST['pays_date'] ?? '');

  // Basic validations
  if ($student_id === '') { $errors[] = 'Student ID is required.'; }
  if ($payment_type === '') { $errors[] = 'Payment Type is required.'; }
  if ($payment_reason === '') { $errors[] = 'Payment Reason is required.'; }
  if ($payment_method === '') { $errors[] = 'Payment Method is required.'; }
  if ($payment_amount === '' || !is_numeric($payment_amount) || $payment_amount <= 0) { $errors[] = 'Valid Amount is required.'; }
  if ($payment_qty === '' || !ctype_digit($payment_qty) || (int)$payment_qty < 1) { $errors[] = 'Valid Quantity is required.'; }
  if ($pays_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pays_date)) { $errors[] = 'Invalid date.'; }

  // Verify student exists
  $stu = fetch_student_info($con, $student_id);
  if (!$stu) { $errors[] = 'Student not found.'; }

  if (!$errors) {
    // Insert into pays using prepared statement
    $sql = "INSERT INTO `pays`
              (`student_id`,`payment_type`,`payment_reason`,`payment_method`,`pays_note`,`pays_amount`,`pays_qty`,`reference_no`,`pays_date`,`pays_department`)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt) {
      $amount = (float)$payment_amount;
      $qty = (int)$payment_qty;
      $dateToUse = ($pays_date !== '') ? $pays_date : date('Y-m-d');
      mysqli_stmt_bind_param($stmt, 'sssssdisss',
        $student_id,
        $payment_type,
        $payment_reason,
        $payment_method,
        $pays_note,
        $amount,
        $qty,
        $reference_no,
        $dateToUse,
        $pays_department
      );
      if (mysqli_stmt_execute($stmt)) {
        $messages[] = 'Payment recorded successfully for ' . h($student_id) . '.';
      } else {
        $errors[] = 'Failed to save payment: ' . h(mysqli_error($con));
      }
      mysqli_stmt_close($stmt);
    } else {
      $errors[] = 'DB error preparing insert.';
    }
  }
  $_SESSION['flash_messages'] = $messages;
  $_SESSION['flash_errors'] = $errors;
  header('Location: ' . $base . '/finance/CollectPayment.php?Sid=' . urlencode($student_id));
  exit;
}

// Flash
if (!empty($_SESSION['flash_messages'])) { $messages = $_SESSION['flash_messages']; unset($_SESSION['flash_messages']); }
if (!empty($_SESSION['flash_errors'])) { $errors = $_SESSION['flash_errors']; unset($_SESSION['flash_errors']); }

// Load student if provided
$sid = isset($_GET['Sid']) ? trim($_GET['Sid']) : '';
$student = $sid ? fetch_student_info($con, $sid) : null;
$p_dept = $student['department_id'] ?? '';
$p_dept_name = $student['department_name'] ?? '';
// Fallback: infer department from student_id pattern like YYYY/DEPT/...
if (($p_dept === '' || $p_dept === null) && !empty($student['student_id'])) {
  if (preg_match('/^\d{4}\/([A-Za-z0-9_-]+)\//', $student['student_id'], $m)) {
    $p_dept = strtoupper($m[1]);
  }
}
// If we inferred department id but no name, try to resolve name
if ($p_dept_name === '' && $p_dept !== '') {
  $depEsc = mysqli_real_escape_string($con, $p_dept);
  if ($rsd = mysqli_query($con, "SELECT department_name FROM department WHERE department_id='$depEsc' LIMIT 1")) {
    if ($rowd = mysqli_fetch_assoc($rsd)) { $p_dept_name = $rowd['department_name'] ?? ''; }
    mysqli_free_result($rsd);
  }
}

// Load payment types list
$paymentTypes = [];
if ($r = mysqli_query($con, "SELECT DISTINCT payment_type FROM payment ORDER BY payment_type")) {
  while ($row = mysqli_fetch_assoc($r)) { if (!empty($row['payment_type'])) $paymentTypes[] = $row['payment_type']; }
  mysqli_free_result($r);
}

$title = 'Collect Payment | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-white shadow-sm">
      
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/finance/PaymentsSummary.php">Payments</a></li>
      <li class="breadcrumb-item active" aria-current="page">Collect Payment</li>
    </ol>
  </nav>
  <h3 class="mb-3"><i class="fas fa-cash-register text-primary mr-2"></i> Collect Payment</h3>

  <?php foreach ($messages as $m): ?><div class="alert alert-success py-2"><?php echo h($m); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?php echo h($e); ?></div><?php endforeach; ?>

  <div class="card mb-3">
    <div class="card-header bg-primary border-bottom">
      <h5 class="mb-0 text-white"><i class="fas fa-search mr-2 text-white"></i> Find Student</h5>
    </div>
    <div class="card-body">
      <div class="form-row align-items-end">
        <div class="form-group col-md-6 mb-3 mb-md-0">
          <label for="sidInput" class="form-label font-weight-semibold">
            <i class="fas fa-id-card-alt mr-1 text-primary"></i>Search Student
          </label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text bg-primary text-white"><i class="fas fa-search"></i></span>
            </div>
            <input type="text" id="sidInput" class="form-control" placeholder="Type Student ID or Name to search...">
          </div>
        </div>
        <div class="form-group col-md-6 mb-3 mb-md-0">
          <label for="sidSelect" class="form-label font-weight-semibold">
            <i class="fas fa-list mr-1 text-primary"></i>Select Student
          </label>
          <select id="sidSelect" class="form-control" disabled>
            <option value="">-- Type to search --</option>
          </select>
        </div>
      </div>
      <?php if ($student): ?>
        <div class="alert alert-info mt-3 mb-0">
          <div class="d-flex align-items-center">
            <i class="fas fa-user-check fa-2x text-primary mr-3"></i>
            <div>
              <div class="font-weight-bold h5 mb-1">
                <?php echo h($student['student_id']); ?> — <?php echo h($student['student_fullname']); ?>
              </div>
              <div class="text-muted">
                <i class="fas fa-building mr-1"></i>Department: <?php echo h($p_dept_name !== '' ? $p_dept_name : ($p_dept ?: 'N/A')); ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-primary border-bottom">
      <h5 class="mb-0 text-white"><i class="fas fa-money-bill-wave mr-2 text-white"></i> Payment Details</h5>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        
        <!-- Student Information Row -->
        <div class="form-row mb-3">
          <div class="form-group col-md-3 mb-3 mb-md-0">
            <label for="student_id" class="form-label font-weight-semibold">
              <i class="fas fa-user-graduate mr-1 text-primary"></i>Student ID
            </label>
            <input type="text" id="student_id" name="student_id" class="form-control" value="<?php echo h($student['student_id'] ?? $sid); ?>" readonly required>
          </div>
          <div class="form-group col-md-3 mb-3 mb-md-0">
            <label for="department" class="form-label font-weight-semibold">
              <i class="fas fa-building mr-1 text-primary"></i>Department
            </label>
            <input type="hidden" name="pays_department" value="<?php echo h($p_dept); ?>">
            <input type="text" id="department" class="form-control" value="<?php echo h($p_dept_name !== '' ? $p_dept_name : ($p_dept ?: '')); ?>" readonly>
          </div>
          <div class="form-group col-md-3 mb-3 mb-md-0">
            <label for="pays_date" class="form-label font-weight-semibold">
              <i class="fas fa-calendar-alt mr-1 text-primary"></i>Date
            </label>
            <input type="date" id="pays_date" name="pays_date" class="form-control" value="<?php echo h(isset($_GET['date'])?$_GET['date']:date('Y-m-d')); ?>" required>
          </div>
          <div class="form-group col-md-3 mb-3 mb-md-0">
            <label for="reference_no" class="form-label font-weight-semibold">
              <i class="fas fa-hashtag mr-1 text-primary"></i>Reference No <span class="text-muted small">(optional)</span>
            </label>
            <input type="text" id="reference_no" name="reference_no" class="form-control" maxlength="64" placeholder="Bank slip / internal ref">
          </div>
        </div>

        <!-- Payment Information Row -->
        <div class="form-row mb-3">
          <?php if ($is_ma4): ?>
            <?php $DEF_TYPE = 'Student Charges'; $DEF_REASON = 'Bus Season'; ?>
            <div class="form-group col-md-4 mb-3 mb-md-0">
              <label for="payment_type_ma4" class="form-label font-weight-semibold">
                <i class="fas fa-money-bill-wave mr-1 text-primary"></i>Payment Type
              </label>
              <input type="hidden" name="payment_type" value="<?php echo h($DEF_TYPE); ?>">
              <input type="text" id="payment_type_ma4" class="form-control" value="<?php echo h($DEF_TYPE); ?>" readonly>
            </div>
            <div class="form-group col-md-4 mb-3 mb-md-0">
              <label for="payment_reason_ma4" class="form-label font-weight-semibold">
                <i class="fas fa-list-alt mr-1 text-primary"></i>Payment Reason
              </label>
              <input type="hidden" name="payment_reason" value="<?php echo h($DEF_REASON); ?>">
              <input type="text" id="payment_reason_ma4" class="form-control" value="<?php echo h($DEF_REASON); ?>" readonly>
            </div>
            <div class="form-group col-md-4 mb-3 mb-md-0">
              <label for="payment_method" class="form-label font-weight-semibold">
                <i class="fas fa-credit-card mr-1 text-primary"></i>Payment Method
              </label>
              <select class="form-control" id="payment_method" name="payment_method" required>
                <option value="">-- Select Method --</option>
                <option value="SLGTI">SLGTI</option>
                <option value="BANK">Bank</option>
              </select>
            </div>
          <?php else: ?>
            <div class="form-group col-md-4 mb-3 mb-md-0">
              <label for="payment_type" class="form-label font-weight-semibold">
                <i class="fas fa-money-bill-wave mr-1 text-primary"></i>Payment Type <span class="text-danger">*</span>
              </label>
              <select class="form-control" id="payment_type" name="payment_type" onchange="loadReasons(this.value)" required>
                <option value="">-- Select a Payment Type --</option>
                <?php foreach ($paymentTypes as $pt): ?>
                  <option value="<?php echo h($pt); ?>"><?php echo h($pt); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-4 mb-3 mb-md-0">
              <label for="payment_reason" class="form-label font-weight-semibold">
                <i class="fas fa-list-alt mr-1 text-primary"></i>Payment Reason <span class="text-danger">*</span>
              </label>
              <select class="form-control" id="payment_reason" name="payment_reason" required>
                <option value="">-- Select a Payment Reason --</option>
              </select>
            </div>
            <div class="form-group col-md-4 mb-3 mb-md-0">
              <label for="payment_method" class="form-label font-weight-semibold">
                <i class="fas fa-credit-card mr-1 text-primary"></i>Payment Method <span class="text-danger">*</span>
              </label>
              <select class="form-control" id="payment_method" name="payment_method" required>
                <option value="">-- Select Method --</option>
                <option value="SLGTI">SLGTI</option>
                <option value="BANK">Bank</option>
              </select>
            </div>
          <?php endif; ?>
        </div>

        <!-- Amount and Quantity Row -->
        <div class="form-row mb-3">
          <div class="form-group col-md-3 mb-3 mb-md-0">
            <label for="payment_amount" class="form-label font-weight-semibold">
              <i class="fas fa-dollar-sign mr-1 text-primary"></i>Amount <span class="text-danger">*</span>
            </label>
            <input type="number" id="payment_amount" min="1" step="0.01" class="form-control" name="payment_amount" placeholder="0.00" required>
          </div>
          <div class="form-group col-md-3 mb-3 mb-md-0">
            <label for="payment_qty" class="form-label font-weight-semibold">
              <i class="fas fa-sort-numeric-up mr-1 text-primary"></i>Quantity <span class="text-danger">*</span>
            </label>
            <input type="number" id="payment_qty" min="1" max="50" class="form-control" name="payment_qty" value="1" required>
          </div>
          <div class="form-group col-md-6 mb-3 mb-md-0">
            <label for="payment_note" class="form-label font-weight-semibold">
              <i class="fas fa-sticky-note mr-1 text-primary"></i>Note <span class="text-muted small">(optional)</span>
            </label>
            <input type="text" id="payment_note" class="form-control" name="payment_note" placeholder="Short note (optional)">
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="form-row mt-4">
          <div class="col-12 text-right">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="fas fa-save mr-2"></i>Record Payment
            </button>
            <?php if ($student): ?>
              <a class="btn btn-outline-secondary btn-lg ml-2" href="<?php echo $base; ?>/finance/CollectPayment.php?Sid=<?php echo urlencode($student['student_id']); ?>">
                <i class="fa fa-redo mr-2"></i>Reset
              </a>
            <?php else: ?>
              <a class="btn btn-outline-secondary btn-lg ml-2" href="<?php echo $base; ?>/finance/CollectPayment.php">
                <i class="fa fa-redo mr-2"></i>Reset
              </a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
  
  <style>
    .form-label {
      font-size: 0.875rem;
      margin-bottom: 0.5rem;
      color: #495057;
      display: block;
    }
    .form-label i {
      width: 18px;
      text-align: center;
    }
    .form-control {
      height: calc(2.25rem + 2px);
      font-size: 0.9375rem;
      padding: 0.375rem 0.75rem;
      line-height: 1.5;
    }
    .form-control:focus {
      border-color: #6366f1;
      box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
    }
    /* Select dropdown styling */
    select.form-control {
      height: calc(2.25rem + 2px);
      padding: 0.375rem 2rem 0.375rem 0.75rem;
      font-size: 0.9375rem;
      line-height: 1.5;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 16px 12px;
      cursor: pointer;
    }
    select.form-control:focus {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236366f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    }
    select.form-control option {
      padding: 0.5rem 0.75rem;
      font-size: 0.9375rem;
      line-height: 1.5;
    }
    select.form-control:disabled {
      background-color: #e9ecef;
      cursor: not-allowed;
      opacity: 0.6;
    }
    .form-group {
      margin-bottom: 0;
    }
    .card-header {
      padding: 1rem 1.25rem;
    }
    .card-header h5 {
      font-size: 1.1rem;
      font-weight: 600;
    }
    @media (max-width: 768px) {
      .form-group {
        margin-bottom: 1rem;
      }
    }
  </style>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
<script>
function loadReasons(val, preselect){
  var el = document.getElementById('payment_reason');
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

// Debounced student search: textbox populates dropdown; selecting navigates to ?Sid=
(function(){
  var input = document.getElementById('sidInput');
  var select = document.getElementById('sidSelect');
  if (!input || !select) return;
  var t = null;
  function setLoading(){ select.innerHTML = '<option>Loading...</option>'; select.disabled = true; }
  function setPrompt(){ select.innerHTML = '<option value="">-- Type to search --</option>'; select.disabled = true; }
  function search(q){
    if (!q){ setPrompt(); return; }
    setLoading();
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          select.innerHTML = xhr.responseText;
          select.disabled = false;
        } else {
          setPrompt();
        }
      }
    };
    xhr.open('POST', '<?php echo $base; ?>/controller/FindStudents.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('q=' + encodeURIComponent(q));
  }
  input.addEventListener('input', function(){
    var q = input.value.trim();
    if (t) clearTimeout(t);
    t = setTimeout(function(){ search(q); }, 250);
  });
  select.addEventListener('change', function(){
    var v = select.value;
    if (v){ window.location.href = '<?php echo $base; ?>/finance/CollectPayment.php?Sid=' + encodeURIComponent(v); }
  });
})();

// Default selections for MA4 use case (disabled if not MA4 or if fields are fixed)
(function(){
  var DEFAULT_TYPE = 'Student Charges';
  var DEFAULT_REASON = 'Bus Season';
  var typeSel = document.getElementById('payment_type');
  var reasonSel = document.getElementById('payment_reason');
  if (!typeSel || !reasonSel) return; // MA4 fixed fields not present
  // Only apply defaults on initial load (no existing value selected)
  var hasType = typeSel.value && typeSel.value !== '';
  if (!hasType){
    // Try select default type if present
    var opts = typeSel.options; var foundType = false; var target = DEFAULT_TYPE.toLowerCase();
    for (var i=0;i<opts.length;i++){
      if ((opts[i].value||'').toLowerCase() === target){ typeSel.value = opts[i].value; foundType = true; break; }
    }
    if (foundType){
      // Load reasons and preselect default reason
      loadReasons(typeSel.value, DEFAULT_REASON);
    }
  }
})();
</script>
