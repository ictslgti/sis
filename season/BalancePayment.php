<?php
// Form to collect balance payments or issue arrears
$title = "Balance Payment / Arrears | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
require_once("../auth.php");

require_roles(['HOD', 'ADM', 'FIN', 'SAO']);
$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_hod = is_role('HOD');

$message = '';
$error = '';

// Get HOD's department code
$dept_code = null;
if ($is_hod) {
    $dept_code = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
    // Fallback: if not in session, get from staff table
    if (empty($dept_code) && !empty($user_id)) {
        $staff_sql = "SELECT department_id FROM staff WHERE staff_id = '".mysqli_real_escape_string($con, $user_id)."' LIMIT 1";
        $staff_result = mysqli_query($con, $staff_sql);
        if ($staff_result && mysqli_num_rows($staff_result) > 0) {
            $staff_row = mysqli_fetch_assoc($staff_result);
            $dept_code = $staff_row['department_id'] ?? '';
        }
    }
}

// Handle balance payment or arrears
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'balance_payment' || $action === 'issue_arrears') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $additional_payment = floatval($_POST['additional_payment'] ?? 0);
        $payment_method = mysqli_real_escape_string($con, $_POST['payment_method'] ?? 'Cash');
        $payment_reference = mysqli_real_escape_string($con, $_POST['payment_reference'] ?? '');
        $payment_date = mysqli_real_escape_string($con, $_POST['payment_date'] ?? date('Y-m-d'));
        $notes = mysqli_real_escape_string($con, $_POST['notes'] ?? '');
        $collected_by = mysqli_real_escape_string($con, $user_id);
        
        // Get current payment record
        $pay_sql = "SELECT * FROM season_payments WHERE request_id = $request_id";
        $pay_result = mysqli_query($con, $pay_sql);
        
        if ($pay_result && mysqli_num_rows($pay_result) > 0) {
            $payment = mysqli_fetch_assoc($pay_result);
            
            if ($action === 'balance_payment') {
                // Collect additional payment
                if ($additional_payment <= 0) {
                    $error = "Payment amount must be greater than zero.";
                } elseif ($additional_payment > $payment['remaining_balance']) {
                    $error = "Payment amount cannot exceed remaining balance.";
                } else {
                    $new_paid = $payment['paid_amount'] + $additional_payment;
                    $new_balance = $payment['remaining_balance'] - $additional_payment;
                    $new_status = ($new_balance <= 0) ? 'Completed' : 'Paid';
                    
                    // Update payment amounts
                    $update_sql = "UPDATE season_payments SET
                            paid_amount = $new_paid,
                            remaining_balance = $new_balance,
                            status = '$new_status',
                            payment_date = '$payment_date',
                            payment_method = '$payment_method',
                            payment_reference = ".($payment_reference ? "'$payment_reference'" : "NULL").",
                            collected_by = '$collected_by',
                            notes = CONCAT(COALESCE(notes, ''), '\n', 'Balance payment: Rs. ".number_format($additional_payment, 2)." on $payment_date".($notes ? " - $notes" : "")."')
                            WHERE request_id = $request_id";
                    
                    if (mysqli_query($con, $update_sql)) {
                        $message = "Balance payment collected successfully! New balance: Rs. " . number_format($new_balance, 2);
                    } else {
                        $error = "Error: " . mysqli_error($con);
                    }
                }
            } else {
                // Issue arrears (mark as arrears in notes)
                $arrears_note = "ARREARS ISSUED on $payment_date by $collected_by. Remaining balance: Rs. ".number_format($payment['remaining_balance'], 2);
                if ($notes) {
                    $arrears_note .= "\n$notes";
                }
                
                $update_sql = "UPDATE season_payments SET
                        notes = CONCAT(COALESCE(notes, ''), '\n', '".mysqli_real_escape_string($con, $arrears_note)."')
                        WHERE request_id = $request_id";
                
                if (mysqli_query($con, $update_sql)) {
                    $message = "Arrears issued successfully!";
                } else {
                    $error = "Error: " . mysqli_error($con);
                }
            }
        } else {
            $error = "Payment record not found.";
        }
    }
}

// Fetch requests with outstanding balance
// Filter by HOD's department
$where_dept = '';
if ($is_hod && !empty($dept_code)) {
    $where_dept = "AND d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
}

$sql = "SELECT sr.*, s.student_fullname, sp.id as payment_id, sp.paid_amount, sp.season_rate, 
               sp.remaining_balance, sp.status as payment_status
        FROM season_requests sr
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN season_payments sp ON sr.id = sp.request_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        WHERE sr.status = 'approved' 
        AND sp.id IS NOT NULL
        AND sp.remaining_balance > 0
        $where_dept
        ORDER BY sp.remaining_balance DESC, sr.created_at DESC";
$result = mysqli_query($con, $sql);
$balance_requests = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $balance_requests[] = $row;
    }
}

// Get request_id from URL
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$request_data = null;
$payment_data = null;

if ($request_id > 0) {
    // For HOD, verify the request belongs to their department
    $where_dept_check = '';
    if ($is_hod && !empty($dept_code)) {
        $where_dept_check = "AND d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
    }
    
    $sql = "SELECT sr.*, s.student_fullname 
            FROM season_requests sr
            LEFT JOIN student s ON sr.student_id = s.student_id
            LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
            LEFT JOIN course c ON c.course_id = se.course_id
            LEFT JOIN department d ON d.department_id = c.department_id
            WHERE sr.id = $request_id AND sr.status = 'approved' $where_dept_check";
    $result = mysqli_query($con, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $request_data = mysqli_fetch_assoc($result);
        
        $pay_sql = "SELECT * FROM season_payments WHERE request_id = $request_id";
        $pay_result = mysqli_query($con, $pay_sql);
        if ($pay_result && mysqli_num_rows($pay_result) > 0) {
            $payment_data = mysqli_fetch_assoc($pay_result);
        }
    }
}
?>

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-md-<?= $request_id > 0 ? '8' : '12' ?>">
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h3 class="mb-0"><i class="fas fa-balance-scale"></i> Balance Payment / Arrears</h3>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Depot</th>
                                    <th>Paid Amount</th>
                                    <th>Season Rate</th>
                                    <th>Remaining Balance</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($balance_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No outstanding balances found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($balance_requests as $req): ?>
                                        <tr class="<?= $req['id'] == $request_id ? 'table-secondary' : '' ?>">
                                            <td><?= htmlspecialchars($req['id']) ?></td>
                                            <td><?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?></td>
                                            <td><?= htmlspecialchars($req['depot_name'] ?? '-') ?></td>
                                            <td>Rs. <?= number_format($req['paid_amount'], 2) ?></td>
                                            <td>Rs. <?= number_format($req['season_rate'], 2) ?></td>
                                            <td>
                                                <span class="badge badge-danger">
                                                    Rs. <?= number_format($req['remaining_balance'], 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?request_id=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Process
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($request_id > 0 && $request_data && $payment_data && $payment_data['remaining_balance'] > 0): ?>
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Process Balance / Arrears</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-3 bg-light rounded">
                        <strong>Student:</strong> <?= htmlspecialchars($request_data['student_fullname'] ?? $request_data['student_id']) ?><br>
                        <?php if (!empty($request_data['depot_name'])): ?>
                            <strong>Depot:</strong> <?= htmlspecialchars($request_data['depot_name']) ?><br>
                        <?php endif; ?>
                        <strong>Paid:</strong> Rs. <?= number_format($payment_data['paid_amount'], 2) ?><br>
                        <strong>Total:</strong> Rs. <?= number_format($payment_data['season_rate'], 2) ?><br>
                        <strong class="text-danger">Balance:</strong> Rs. <?= number_format($payment_data['remaining_balance'], 2) ?>
                    </div>

                    <!-- Balance Payment Form -->
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">Collect Balance Payment</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="balance_payment">
                                <input type="hidden" name="request_id" value="<?= $request_data['id'] ?>">
                                
                                <div class="form-group">
                                    <label>Payment Amount (Rs.) <span class="text-danger">*</span></label>
                                    <input type="number" name="additional_payment" class="form-control" 
                                           step="0.01" min="0.01" max="<?= $payment_data['remaining_balance'] ?>" 
                                           placeholder="Max: <?= number_format($payment_data['remaining_balance'], 2) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" class="form-control" required>
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Payment Reference</label>
                                    <input type="text" name="payment_reference" class="form-control" 
                                           placeholder="Receipt/Transaction number">
                                </div>

                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"></textarea>
                                </div>

                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-money-bill"></i> Collect Payment
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Issue Arrears Form -->
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">Issue Arrears</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="issue_arrears">
                                <input type="hidden" name="request_id" value="<?= $request_data['id'] ?>">
                                
                                <div class="form-group">
                                    <label>Issue Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="3" 
                                              placeholder="Reason for issuing arrears"></textarea>
                                </div>

                                <button type="submit" class="btn btn-warning btn-block">
                                    <i class="fas fa-exclamation-triangle"></i> Issue Arrears
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once("../footer.php"); ?>

