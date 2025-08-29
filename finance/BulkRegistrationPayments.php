<?php
$title = "Bulk Registration Payments | SLGTI";
include_once(__DIR__ . '/../config.php');

// Access control: Finance/Admin roles
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if (!in_array($userType, ['ACC', 'ADM', 'FIN'])) {
  include_once(__DIR__ . '/../head.php');
  include_once(__DIR__ . '/../menu.php');
  echo '<div class="container mt-4"><div class="alert alert-danger">Access denied.</div></div>';
  include_once(__DIR__ . '/../footer.php');
  exit;
}

// Defaults and helpers
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Ensure columns exist in pays (defensive, same as other pages)
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `approved` TINYINT(1) NOT NULL DEFAULT 0");
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `approved_at` DATETIME NULL");
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `approved_by` VARCHAR(50) NULL");
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `payment_method` VARCHAR(20) NULL AFTER `payment_reason`");

// Handle bulk submit
$flash = '';$flashMsg='';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_create'])) {
  $dept = isset($_POST['pays_department']) ? trim($_POST['pays_department']) : '';
  $studentIds = isset($_POST['student_ids']) && is_array($_POST['student_ids']) ? array_filter($_POST['student_ids']) : [];
  $amount = isset($_POST['pays_amount']) ? (float)$_POST['pays_amount'] : 0.0;
  $qty = isset($_POST['pays_qty']) ? (int)$_POST['pays_qty'] : 1;
  $method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';

  // Fixed fields per request
  $paymentType = 'Other Charges';
  $paymentReason = 'Other Charges';
  $note = 'Register';

  // Validate
  if ($dept==='') { $flash='err'; $flashMsg='Department is required.'; }
  elseif (empty($studentIds)) { $flash='err'; $flashMsg='Please select at least one student.'; }
  elseif ($amount <= 0) { $flash='err'; $flashMsg='Amount must be greater than 0.'; }
  elseif ($qty <= 0) { $flash='err'; $flashMsg='Quantity must be at least 1.'; }
  elseif ($method==='') { $flash='err'; $flashMsg='Select a payment method.'; }
  else {
    // Optional: ensure payment reason exists
    $prOk = true;
    if ($pr = mysqli_prepare($con, "SELECT 1 FROM payment WHERE payment_reason=? LIMIT 1")) {
      mysqli_stmt_bind_param($pr, 's', $paymentReason);
      mysqli_stmt_execute($pr);
      mysqli_stmt_store_result($pr);
      $prOk = (mysqli_stmt_num_rows($pr) > 0);
      mysqli_stmt_close($pr);
    }
    if (!$prOk) {
      $flash='err';
      $flashMsg='Invalid payment reason configured on this system.';
    } else {
      // Optional: verify selected students belong to department and are Following
      $safeDept = mysqli_real_escape_string($con, $dept);
      $idPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
      $types = str_repeat('s', count($studentIds));
      $verifySql = "SELECT s.student_id FROM student s\n                     JOIN student_enroll se ON se.student_id=s.student_id\n                     JOIN course c ON c.course_id = se.course_id\n                     WHERE c.department_id = '$safeDept'\n                       AND s.student_conduct_accepted_at IS NOT NULL\n                       AND s.student_id IN (" . str_repeat('?,', count($studentIds)-1) . "?)";
      $verifyStmt = mysqli_prepare($con, $verifySql);
      if ($verifyStmt) {
        mysqli_stmt_bind_param($verifyStmt, $types, ...$studentIds);
        mysqli_stmt_execute($verifyStmt);
        $vr = mysqli_stmt_get_result($verifyStmt);
        $validIds = [];
        while($row = $vr ? mysqli_fetch_assoc($vr) : null){ if ($row) $validIds[$row['student_id']] = true; }
        mysqli_stmt_close($verifyStmt);
        $studentIds = array_values(array_filter($studentIds, function($id) use ($validIds){ return isset($validIds[$id]); }));
      }

      // Exclude students who already exist in pays (any note)
      if (!empty($studentIds)) {
        $inPlaceholders = str_repeat('?,', count($studentIds)-1) . '?';
        $types2 = str_repeat('s', count($studentIds));
        $existSql = "SELECT DISTINCT student_id FROM pays WHERE student_id IN ($inPlaceholders)";
        if ($existStmt = mysqli_prepare($con, $existSql)) {
          mysqli_stmt_bind_param($existStmt, $types2, ...$studentIds);
          mysqli_stmt_execute($existStmt);
          $er = mysqli_stmt_get_result($existStmt);
          $hasRegister = [];
          while($row = $er ? mysqli_fetch_assoc($er) : null){ if ($row) $hasRegister[$row['student_id']] = true; }
          mysqli_stmt_close($existStmt);
          $studentIds = array_values(array_filter($studentIds, function($id) use ($hasRegister){ return !isset($hasRegister[$id]); }));
        }
      }

      if (empty($studentIds)) {
        $flash='err'; $flashMsg='No valid students found for the selected department.';
      } else {
        mysqli_begin_transaction($con);
        $ok = true; $failed = 0;
        $stmt = mysqli_prepare($con, "INSERT INTO `pays`\n          (`student_id`,`payment_type`,`payment_reason`,`payment_method`,`pays_note`,`pays_amount`,`pays_qty`,`pays_date`,`pays_department`,`approved`,`approved_at`,`approved_by`)\n          VALUES (?,?,?,?,?,?,?, CURDATE(), ?, 0, NULL, NULL)");
        if (!$stmt) { $ok=false; $flashMsg = 'DB error: '.mysqli_error($con); }
        else {
          foreach ($studentIds as $sid) {
            if (!mysqli_stmt_bind_param($stmt, 'sssssdis', $sid, $paymentType, $paymentReason, $method, $note, $amount, $qty, $dept)) { $ok=false; $failed++; break; }
            if (!mysqli_stmt_execute($stmt)) { $ok=false; $failed++; }
          }
          mysqli_stmt_close($stmt);
        }
        if ($ok) { mysqli_commit($con); $flash='ok'; $flashMsg = 'Inserted '.count($studentIds).' payment(s).'; }
        else { mysqli_rollback($con); $flash='err'; if ($failed>0 && $flashMsg==='') { $flashMsg = "$failed insert(s) failed."; } }
      }
    }
  }
}

// Page data
$selDept = isset($_GET['dept']) ? trim($_GET['dept']) : (isset($_POST['pays_department']) ? trim($_POST['pays_department']) : '');
$deptRes = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
$studentsRes = false;
if ($selDept !== '') {
  $studentsSql = "SELECT DISTINCT s.student_id, s.student_fullname\n                  FROM student s\n                  JOIN student_enroll se ON se.student_id = s.student_id\n                  JOIN course c ON c.course_id = se.course_id\n                  WHERE c.department_id='".mysqli_real_escape_string($con,$selDept)."'\n                    AND s.student_conduct_accepted_at IS NOT NULL\n                    AND NOT EXISTS (SELECT 1 FROM pays p WHERE p.student_id = s.student_id)\n                  ORDER BY s.student_fullname";
  $studentsRes = mysqli_query($con, $studentsSql);
}

include_once(__DIR__ . '/../head.php');
include_once(__DIR__ . '/../menu.php');
?>
<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">Bulk Registration Payments</h3>
  </div>

  <?php if ($flash==='ok'): ?>
    <div class="alert alert-success"><?php echo esc($flashMsg); ?></div>
  <?php elseif ($flash==='err'): ?>
    <div class="alert alert-danger"><?php echo esc($flashMsg); ?></div>
  <?php endif; ?>

  <form class="card card-body mb-3" method="get">
    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Department</label>
        <select name="dept" class="form-control" onchange="this.form.submit()">
          <option value="">-- Select Department --</option>
          <?php if ($deptRes && mysqli_num_rows($deptRes)>0) { while($d=mysqli_fetch_assoc($deptRes)) { ?>
            <option value="<?php echo esc($d['department_id']); ?>" <?php echo $selDept===$d['department_id']?'selected':''; ?>><?php echo esc($d['department_name']); ?></option>
          <?php } } ?>
        </select>
      </div>
    </div>
  </form>

  <form class="card" method="post" onsubmit="return confirm('Create payments for selected students?');">
    <div class="card-header">Create Payments</div>
    <div class="card-body">
      <input type="hidden" name="bulk_create" value="1">
      <input type="hidden" name="pays_department" value="<?php echo esc($selDept); ?>">

      <div class="form-row">
        <div class="form-group col-md-3">
          <label>Amount</label>
          <input type="number" step="0.01" min="0.01" name="pays_amount" class="form-control" required>
        </div>
        <div class="form-group col-md-2">
          <label>Qty</label>
          <input type="number" min="1" name="pays_qty" class="form-control" value="1" required>
        </div>
        <div class="form-group col-md-4">
          <label>Payment Method</label>
          <div class="form-control border-0 p-0">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="payment_method" id="pmSLGTI" value="SLGTI" required>
              <label class="form-check-label" for="pmSLGTI">SLGTI</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="payment_method" id="pmBank" value="BANK" required>
              <label class="form-check-label" for="pmBank">Bank</label>
            </div>
          </div>
          <small class="form-text text-muted">Note will be set to "Register" for all rows.</small>
        </div>
      </div>

      <div class="table-responsive mt-2">
        <table class="table table-sm table-hover">
          <thead class="thead-light">
            <tr>
              <th style="width:40px"><input type="checkbox" id="chkAll" onclick="toggleAll(this)"></th>
              <th>Student ID</th>
              <th>Name</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($selDept==='' || !$studentsRes): ?>
              <tr><td colspan="3" class="text-muted text-center">Select a department to load students</td></tr>
            <?php else: ?>
              <?php while($s = mysqli_fetch_assoc($studentsRes)) { ?>
                <tr>
                  <td><input type="checkbox" name="student_ids[]" value="<?php echo esc($s['student_id']); ?>"></td>
                  <td><?php echo esc($s['student_id']); ?></td>
                  <td><?php echo esc($s['student_fullname']); ?></td>
                </tr>
              <?php } ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <button type="submit" class="btn btn-primary" <?php echo $selDept===''?'disabled':''; ?>>Create Payments</button>
      </div>
    </div>
  </form>
</div>
<script>
function toggleAll(cb){
  var boxes = document.querySelectorAll('input[name="student_ids[]"]');
  for (var i=0;i<boxes.length;i++){ boxes[i].checked = cb.checked; }
}
</script>
<?php include_once(__DIR__ . '/../footer.php'); ?>
