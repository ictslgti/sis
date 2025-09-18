<?php
$title = "Registration Payment Approval | SLGTI";
include_once(__DIR__ . '/../config.php');

// Restrict to Finance/Administration
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if (!in_array($userType, ['ACC', 'ADM', 'FIN'])) {
  // Defer rendering includes until after access check to avoid sending output
  include_once(__DIR__ . '/../head.php');
  include_once(__DIR__ . '/../menu.php');
  echo '<div class="container mt-4"><div class="alert alert-danger">Access denied.</div></div>';
  include_once(__DIR__ . '/../footer.php');
  exit;
}

// Ensure approval columns exist in `pays`
mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `approved` TINYINT(1) NOT NULL DEFAULT 0") or true;
mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `approved_at` DATETIME NULL") or true;
mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `approved_by` VARCHAR(50) NULL") or true;
// Ensure payment_method column exists in `pays`
mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `payment_method` VARCHAR(20) NULL AFTER `payment_reason`") or true;

// Handle Approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_pays_id'])) {
  $paysId = intval($_POST['approve_pays_id']);
  $approvedBy = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'ACC');
  $stmt = mysqli_prepare($con, "UPDATE `pays` SET approved=1, approved_at=NOW(), approved_by=? WHERE pays_id=?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'si', $approvedBy, $paysId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
  // Redirect to avoid resubmission
  $qs = $_GET; $qs['flash'] = 'approved';
  $base = (defined('APP_BASE') ? APP_BASE : '');
  $location = $base . '/finance/RegistrationPaymentApproval.php?' . http_build_query($qs);
  // Ensure no output has been sent before header
  header('Location: ' . $location);
  exit;
}

// Handle Create Payment action (add new registration payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment'])) {
  $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
  $pays_amount = isset($_POST['pays_amount']) ? floatval($_POST['pays_amount']) : 0;
  $pays_qty = isset($_POST['pays_qty']) ? intval($_POST['pays_qty']) : 1;
  $pays_note = isset($_POST['pays_note']) ? trim($_POST['pays_note']) : '';
  $pays_department = isset($_POST['pays_department']) ? trim($_POST['pays_department']) : '';
  $payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : 'Registration';
  $payment_reason = isset($_POST['payment_reason']) ? trim($_POST['payment_reason']) : 'Registration Fee';
  $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';

  $err = '';
  if ($student_id === '') { $err = 'Student is required'; }
  elseif ($pays_amount <= 0) { $err = 'Amount must be greater than 0'; }
  elseif ($pays_qty <= 0) { $err = 'Quantity must be at least 1'; }
  elseif ($payment_type === '') { $err = 'Payment type is required'; }
  elseif ($payment_reason === '') { $err = 'Payment reason is required'; }
  elseif ($payment_method === '') { $err = 'Payment method is required'; }

  if ($err === '') {
    // If department selected, ensure the student belongs to that department
    if ($pays_department !== '') {
      $chkSql = "SELECT 1
                 FROM student s
                 JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status='Following'
                 JOIN course c ON c.course_id = se.course_id
                 WHERE s.student_id = ? AND c.department_id = ?
                 LIMIT 1";
      if ($chk = mysqli_prepare($con, $chkSql)) {
        mysqli_stmt_bind_param($chk, 'ss', $student_id, $pays_department);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) === 0) {
          mysqli_stmt_close($chk);
          $qs = $_GET; $qs['flash'] = 'add_error'; $qs['msg'] = 'Selected student is not in the chosen department.';
          $base = (defined('APP_BASE') ? APP_BASE : '');
          header('Location: ' . $base . '/finance/RegistrationPaymentApproval.php?' . http_build_query($qs));
          exit;
        }
        mysqli_stmt_close($chk);
      }
    }
    // Ensure the payment_reason exists to satisfy FK constraint
    if ($pr = mysqli_prepare($con, "SELECT 1 FROM payment WHERE payment_reason=? LIMIT 1")) {
      mysqli_stmt_bind_param($pr, 's', $payment_reason);
      mysqli_stmt_execute($pr);
      mysqli_stmt_store_result($pr);
      if (mysqli_stmt_num_rows($pr) === 0) {
        mysqli_stmt_close($pr);
        $qs = $_GET; $qs['flash'] = 'add_error'; $qs['msg'] = 'Invalid payment reason. Please choose from the list.';
        $base = (defined('APP_BASE') ? APP_BASE : '');
        header('Location: ' . $base . '/finance/RegistrationPaymentApproval.php?' . http_build_query($qs));
        exit;
      }
      mysqli_stmt_close($pr);
    }
    if ($stmt = mysqli_prepare($con, "INSERT INTO `pays` (`student_id`,`payment_type`,`payment_reason`,`payment_method`,`pays_note`,`pays_amount`,`pays_qty`,`pays_date`,`pays_department`,`approved`,`approved_at`,`approved_by`) VALUES (?,?,?,?,?,?,?, CURDATE(), ?, 0, NULL, NULL)")) {
      $bindOk = mysqli_stmt_bind_param($stmt, 'sssssdis', $student_id, $payment_type, $payment_reason, $payment_method, $pays_note, $pays_amount, $pays_qty, $pays_department);
      $execOk = $bindOk && mysqli_stmt_execute($stmt);
      // Use insert_id to determine success more reliably than affected_rows
      $insertId = $execOk ? mysqli_insert_id($con) : 0;
      $stmtErr = $execOk ? '' : mysqli_stmt_error($stmt);
      mysqli_stmt_close($stmt);
      $qs = $_GET;
      if ($insertId > 0) {
        $qs['flash'] = 'added';
      } else {
        $qs['flash'] = 'add_failed';
        // Provide more context for troubleshooting
        $dbErr = mysqli_error($con);
        $msg = trim($stmtErr . (strlen($stmtErr) && strlen($dbErr) ? ' | ' : '') . $dbErr);
        if ($msg !== '') { $qs['msg'] = $msg; }
      }
      $base = (defined('APP_BASE') ? APP_BASE : '');
      header('Location: ' . $base . '/finance/RegistrationPaymentApproval.php?' . http_build_query($qs));
      exit;
    } else {
      $qs = $_GET; $qs['flash'] = 'add_failed'; $qs['msg'] = mysqli_error($con);
      $base = (defined('APP_BASE') ? APP_BASE : '');
      header('Location: ' . $base . '/finance/RegistrationPaymentApproval.php?' . http_build_query($qs));
      exit;
    }
  } else {
    // Preserve inline error via query string
    $qs = $_GET; $qs['flash'] = 'add_error'; $qs['msg'] = $err;
    $base = (defined('APP_BASE') ? APP_BASE : '');
    header('Location: ' . $base . '/finance/RegistrationPaymentApproval.php?' . http_build_query($qs));
    exit;
  }
}

// Only include layout after any potential redirects above
include_once(__DIR__ . '/../head.php');
include_once(__DIR__ . '/../menu.php');

// Data for Add Payment form
$formDept = isset($_GET['form_dept']) ? trim($_GET['form_dept']) : '';
$formType = isset($_GET['form_ptype']) ? trim($_GET['form_ptype']) : '';
if ($formType === '') { $formType = 'Registration'; }
$formStudent = isset($_GET['form_student']) ? trim($_GET['form_student']) : '';
$formReason = isset($_GET['form_reason']) ? trim($_GET['form_reason']) : '';
$deptRes = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
if ($formDept !== '') {
  $sqlStudents = "SELECT s.student_id, s.student_fullname
                  FROM student s
                  JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status='Following'
                  JOIN course c ON c.course_id = se.course_id
                  WHERE c.department_id = '" . mysqli_real_escape_string($con, $formDept) . "'
                  ORDER BY s.student_fullname";
  $studentsRes = mysqli_query($con, $sqlStudents);
} else {
  // No department chosen yet; return empty result to force selection
  $studentsRes = false;
}
// Payment type/reason for Add form
$addTypesRes = mysqli_query($con, "SELECT DISTINCT payment_type FROM payment ORDER BY payment_type");
// Always load reasons for the current (possibly defaulted) type
$addReasonsRes = mysqli_query($con, "SELECT payment_reason FROM payment WHERE payment_type='" . mysqli_real_escape_string($con, $formType) . "' ORDER BY payment_reason");
?>
<div class="container mt-3">
  <?php if (isset($_GET['flash'])) { $f=$_GET['flash']; ?>
    <?php if ($f==='added') { ?><div class="alert alert-success">Payment added. It is pending approval.</div><?php } ?>
    <?php if ($f==='add_failed') { ?><div class="alert alert-warning">Insert failed. Please try again.</div><?php } ?>
    <?php if ($f==='add_error') { ?><div class="alert alert-danger">Error: <?php echo htmlspecialchars(isset($_GET['msg'])?$_GET['msg']:''); ?></div><?php } ?>
    <?php if ($f==='approved') { ?><div class="alert alert-success">Payment approved successfully.</div><?php } ?>
  <?php } ?>

  <div class="card mb-3">
    <div class="card-header">Add Registration Payment</div>
    <form method="post" class="card-body" id="addPaymentForm">
      <input type="hidden" name="create_payment" value="1">
      <div class="form-row">
        <div class="form-group col-md-4">
          <label>Department</label>
          <select name="pays_department" class="form-control" id="formDeptSelect">
            <option value="">-- Select Department --</option>
            <?php if ($deptRes && mysqli_num_rows($deptRes)>0) { mysqli_data_seek($deptRes, 0); while($d=mysqli_fetch_assoc($deptRes)) { ?>
              <option value="<?php echo htmlspecialchars($d['department_id']); ?>" <?php echo ($formDept===$d['department_id'])?'selected':''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
            <?php } } ?>
          </select>
          <small class="form-text text-muted">Select a department to load its students.</small>
        </div>
        <div class="form-group col-md-4">
          <label>Student</label>
          <select name="student_id" class="form-control" id="studentSelect" <?php echo ($formDept==='')?'disabled':''; ?> required>
            <option value=""><?php echo ($formDept==='')?'-- Select Department First --':'-- Select Student --'; ?></option>
            <?php if ($studentsRes && mysqli_num_rows($studentsRes)>0) { while($s=mysqli_fetch_assoc($studentsRes)) { ?>
              <option value="<?php echo htmlspecialchars($s['student_id']); ?>" <?php echo ($formStudent!=='' && $formStudent===$s['student_id'])?'selected':''; ?>><?php echo htmlspecialchars($s['student_fullname'].' ('.$s['student_id'].')'); ?></option>
            <?php } } ?>
          </select>
          <input type="text" id="studentSelectFilter" class="form-control mt-1" placeholder="Type to filter by name or ID" <?php echo ($formDept==='')?'disabled':''; ?>>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-3">
          <label>Payment Type</label>
          <select name="payment_type" class="form-control" id="formTypeSelect">
            <option value="">-- Select Type --</option>
            <?php if ($addTypesRes && mysqli_num_rows($addTypesRes)>0) { mysqli_data_seek($addTypesRes, 0); while($t=mysqli_fetch_assoc($addTypesRes)) { ?>
              <option value="<?php echo htmlspecialchars($t['payment_type']); ?>" <?php echo ($formType===$t['payment_type'])?'selected':''; ?>><?php echo htmlspecialchars($t['payment_type']); ?></option>
            <?php } } ?>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label>Payment Reason</label>
          <select name="payment_reason" class="form-control" id="formReasonSelect" required>
            <option value="">-- Select Reason --</option>
            <?php if ($addReasonsRes && mysqli_num_rows($addReasonsRes)>0) { while($r=mysqli_fetch_assoc($addReasonsRes)) { ?>
              <option value="<?php echo htmlspecialchars($r['payment_reason']); ?>" <?php echo ($formReason!=='' && $formReason===$r['payment_reason'])?'selected':''; ?>><?php echo htmlspecialchars($r['payment_reason']); ?></option>
            <?php } } ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>Amount</label>
          <input type="number" step="0.01" min="0" name="pays_amount" class="form-control" placeholder="0.00" required>
        </div>
        <div class="form-group col-md-2">
          <label>Payment Method</label>
          <select name="payment_method" class="form-control" required>
            <option value="">-- Select Method --</option>
            <option value="SLGTI">SLGTI</option>
            <option value="BANK">Bank</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-2">
          <label>Qty</label>
          <input type="number" min="1" name="pays_qty" class="form-control" value="1" required>
        </div>
        <div class="form-group col-md-10">
          <label>Note</label>
          <input type="text" name="pays_note" class="form-control" placeholder="Optional note">
        </div>
      </div>
      <div>
        <button type="submit" class="btn btn-primary">Add Payment</button>
      </div>
    </form>
  </div>
  <script>
    (function(){
      var deptSel = document.getElementById('formDeptSelect');
      if (deptSel) {
        deptSel.addEventListener('change', function(){
          var val = this.value || '';
          var url = new URL(window.location.href);
          url.searchParams.set('form_dept', val);
          window.location.href = url.toString();
        });
      }
      var typeSel = document.getElementById('formTypeSelect');
      if (typeSel) {
        typeSel.addEventListener('change', function(){
          var val = this.value || '';
          var url = new URL(window.location.href);
          url.searchParams.set('form_ptype', val);
          // Keep currently selected department for convenience
          var dept = document.getElementById('formDeptSelect');
          if (dept) { url.searchParams.set('form_dept', dept.value || ''); }
          // Preserve currently selected student when changing type
          var st = document.getElementById('studentSelect');
          if (st && st.value) { url.searchParams.set('form_student', st.value); }
          // Clear form_reason when type changes to avoid mismatches
          url.searchParams.delete('form_reason');
          window.location.href = url.toString();
        });
      }
      // Student typing filter for the Student select
      var stSel = document.getElementById('studentSelect');
      var stFilter = document.getElementById('studentSelectFilter');
      if (stSel && stFilter) {
        // Cache all options (excluding the first placeholder)
        var allOpts = Array.prototype.slice.call(stSel.options).map(function(o){ return {value:o.value, text:o.text}; });
        var placeholder = allOpts.length ? allOpts[0] : {value:'', text:'-- Select Student --'};
        function rebuild(filterText){
          var keep = stSel.value;
          var ft = (filterText||'').toLowerCase();
          // Remove all options
          while (stSel.options.length) stSel.remove(0);
          // Add placeholder
          var ph = document.createElement('option'); ph.value = placeholder.value; ph.text = placeholder.text; stSel.add(ph);
          // Add filtered
          allOpts.forEach(function(it, idx){
            if (idx === 0) return; // skip placeholder captured
            if (!ft || it.text.toLowerCase().indexOf(ft) !== -1) {
              var o = document.createElement('option');
              o.value = it.value; o.text = it.text;
              if (it.value === keep) { o.selected = true; }
              stSel.add(o);
            }
          });
        }
        stFilter.addEventListener('input', function(){ rebuild(this.value); });
        // Initialize empty filter
        rebuild('');
      }
    })();
  </script>
<?php
// Filters
$status = isset($_GET['status']) ? $_GET['status'] : 'pending'; // pending|approved|all
$paymentType = isset($_GET['payment_type']) ? trim($_GET['payment_type']) : '';
$paymentReason = isset($_GET['payment_reason']) ? trim($_GET['payment_reason']) : 'Registration Fee';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$studentId = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
// New filters
$studentName = isset($_GET['student_name']) ? trim($_GET['student_name']) : '';
$studentExact = isset($_GET['student_exact']) ? trim($_GET['student_exact']) : '';

// Build WHERE clause
$where = [];
$params = [];
if ($status === 'pending') { $where[] = 'p.approved = 0'; }
elseif ($status === 'approved') { $where[] = 'p.approved = 1'; }
if ($paymentType !== '') { $where[] = "p.payment_type = '" . mysqli_real_escape_string($con, $paymentType) . "'"; }
if ($paymentReason !== '') { $where[] = "p.payment_reason = '" . mysqli_real_escape_string($con, $paymentReason) . "'"; }
if ($studentId !== '') { $where[] = "p.student_id LIKE '%" . mysqli_real_escape_string($con, $studentId) . "%'"; }
if ($department !== '') { $where[] = "c.department_id = '" . mysqli_real_escape_string($con, $department) . "'"; }
if ($studentExact !== '') { $where[] = "p.student_id = '" . mysqli_real_escape_string($con, $studentExact) . "'"; }
elseif ($studentName !== '') { $where[] = "s.student_fullname LIKE '%" . mysqli_real_escape_string($con, $studentName) . "%'"; }
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Dropdown data
$typesRes = mysqli_query($con, "SELECT DISTINCT payment_type FROM payment ORDER BY payment_type");
$reasonsRes = mysqli_query($con, ($paymentType !== '')
  ? "SELECT DISTINCT payment_reason FROM payment WHERE payment_type='" . mysqli_real_escape_string($con, $paymentType) . "' ORDER BY payment_reason"
  : "SELECT DISTINCT payment_reason FROM payment ORDER BY payment_reason");
$deptRes = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
// If a department filter is chosen, load its students for an exact dropdown filter
$studentsFilterRes = false;
if ($department !== '') {
  $studentsFilterRes = mysqli_query($con, "SELECT s.student_id, s.student_fullname FROM student s JOIN student_enroll se ON se.student_id=s.student_id AND se.student_enroll_status='Following' JOIN course c ON c.course_id=se.course_id WHERE c.department_id='".mysqli_real_escape_string($con,$department)."' ORDER BY s.student_fullname");
}

// Data query: join student, enrollment and course to get department
$sql = "
SELECT 
  p.pays_id, p.student_id, p.payment_type, p.payment_reason, p.pays_amount, p.pays_qty, p.pays_note, p.pays_date,
  p.pays_department, p.approved, p.approved_at, p.approved_by,
  s.student_fullname,
  c.department_id,
  d.department_name
FROM pays p
LEFT JOIN student s ON s.student_id = p.student_id
LEFT JOIN student_enroll se ON se.student_id = p.student_id AND se.student_enroll_status='Following'
LEFT JOIN course c ON c.course_id = se.course_id
LEFT JOIN department d ON d.department_id = c.department_id
$whereSql
ORDER BY p.pays_date DESC, p.pays_id DESC
";
$res = mysqli_query($con, $sql);
?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Registration Payment Approval</h2>
    <?php if (isset($_GET['flash']) && $_GET['flash'] === 'approved'): ?>
      <div class="alert alert-success py-1 px-2 mb-0">Payment approved.</div>
    <?php endif; ?>
  </div>

  <form class="card card-body mb-3" method="get">
    <div class="form-row">
      <div class="form-group col-md-2">
        <label>Status</label>
        <select name="status" class="form-control">
          <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending</option>
          <option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Approved</option>
          <option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Payment Type</label>
        <select name="payment_type" class="form-control">
          <option value="">-- Any --</option>
          <?php if ($typesRes && mysqli_num_rows($typesRes)>0) { while($r=mysqli_fetch_assoc($typesRes)){ ?>
            <option value="<?php echo htmlspecialchars($r['payment_type']); ?>" <?php echo ($paymentType===$r['payment_type'])?'selected':''; ?>><?php echo htmlspecialchars($r['payment_type']); ?></option>
          <?php }} ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Payment Reason</label>
        <select name="payment_reason" class="form-control">
          <option value="">-- Any --</option>
          <?php if ($reasonsRes && mysqli_num_rows($reasonsRes)>0) { while($r=mysqli_fetch_assoc($reasonsRes)){ ?>
            <option value="<?php echo htmlspecialchars($r['payment_reason']); ?>" <?php echo ($paymentReason===$r['payment_reason'])?'selected':''; ?>><?php echo htmlspecialchars($r['payment_reason']); ?></option>
          <?php }} ?>
        </select>
      </div>
      <div class="form-group col-md-2">
        <label>Department</label>
        <select name="department" class="form-control" onchange="this.form.submit()">
          <option value="">-- Any --</option>
          <?php if ($deptRes && mysqli_num_rows($deptRes)>0) { while($r=mysqli_fetch_assoc($deptRes)){ ?>
            <option value="<?php echo htmlspecialchars($r['department_id']); ?>" <?php echo ($department===$r['department_id'])?'selected':''; ?>><?php echo htmlspecialchars($r['department_name']); ?></option>
          <?php }} ?>
        </select>
      </div>
      <div class="form-group col-md-2">
        <label>Student ID</label>
        <input type="text" name="student_id" value="<?php echo htmlspecialchars($studentId); ?>" class="form-control" placeholder="e.g. 2025ICT...">
      </div>
      <div class="form-group col-md-3">
        <label>Student Name</label>
        <input type="text" name="student_name" value="<?php echo htmlspecialchars($studentName); ?>" class="form-control" placeholder="e.g. AINKARAN">
      </div>
      <div class="form-group col-md-4">
        <label>Student (Exact)</label>
        <select name="student_exact" class="form-control" <?php echo ($department==='') ? 'disabled' : ''; ?>>
          <option value="">-- Any --</option>
          <?php if ($studentsFilterRes && mysqli_num_rows($studentsFilterRes)>0) { while($sx=mysqli_fetch_assoc($studentsFilterRes)) { ?>
            <option value="<?php echo htmlspecialchars($sx['student_id']); ?>" <?php echo ($studentExact!=='' && $studentExact===$sx['student_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sx['student_fullname'] . ' (' . $sx['student_id'] . ')'); ?></option>
          <?php } } ?>
        </select>
        <small class="form-text text-muted">Select Department to enable exact student filter.</small>
      </div>
    </div>
    <div>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/finance/RegistrationPaymentApproval.php" class="btn btn-secondary btn-sm">Reset</a>
    </div>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead class="thead-dark">
            <tr>
              <th>#</th>
              <th>Student ID</th>
              <th>Name</th>
              <th>Department</th>
              <th>Note</th>
              <th>Reason</th>
              <th>Method</th>
              <th>Amount</th>
              <th>Qty</th>
              <th>Date</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($res && mysqli_num_rows($res) > 0) { while($row = mysqli_fetch_assoc($res)) { ?>
            <tr>
              <td><?php echo (int)$row['pays_id']; ?></td>
              <td><?php echo htmlspecialchars($row['student_id']); ?></td>
              <td><?php echo htmlspecialchars($row['student_fullname'] ?: '-'); ?></td>
              <td><?php echo htmlspecialchars($row['department_name'] ?: ($row['pays_department'] ?: '-')); ?></td>
              <td><?php echo htmlspecialchars(($row['pays_note'] !== null && $row['pays_note'] !== '') ? $row['pays_note'] : '-'); ?></td>
              <td><?php echo htmlspecialchars($row['payment_reason']); ?></td>
              <td><?php echo htmlspecialchars(isset($row['payment_method']) && $row['payment_method']!=='' ? $row['payment_method'] : '-'); ?></td>
              <td><?php echo number_format((float)$row['pays_amount'], 2); ?></td>
              <td><?php echo (int)$row['pays_qty']; ?></td>
              <td><?php echo htmlspecialchars($row['pays_date']); ?></td>
              <td>
                <?php if ((int)$row['approved'] === 1) { ?>
                  <span class="badge badge-success">Approved</span><br>
                  <small><?php echo htmlspecialchars($row['approved_by']); ?> @ <?php echo htmlspecialchars($row['approved_at']); ?></small>
                <?php } else { ?>
                  <span class="badge badge-warning">Pending</span>
                <?php } ?>
              </td>
              <td>
                <?php if ((int)$row['approved'] === 0) { ?>
                  <form method="post" class="m-0">
                    <input type="hidden" name="approve_pays_id" value="<?php echo (int)$row['pays_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this payment?');">Approve</button>
                  </form>
                <?php } else { ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled>Approved</button>
                <?php } ?>
              </td>
            </tr>
          <?php } } else { ?>
            <tr><td colspan="11" class="text-center text-muted">No records found.</td></tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include_once(__DIR__ . '/../footer.php'); ?>
