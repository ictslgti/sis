<?php
// Form to issue season after payment collection
$title = "Issue Season | SLGTI";
include_once("../config.php");
require_once("../auth.php");
require_once(__DIR__ . "/SeasonModel.php");

require_roles(['HOD', 'ADM', 'SAO']);
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

// Initialize model
if (!isset($con) || !$con) {
    die("Database connection not available. Please check config.php");
}
$seasonModel = new SeasonModel($con);

// Handle season issuance - MUST be before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'issue') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $season_rate = floatval($_POST['season_rate'] ?? 0);
        $issue_date = mysqli_real_escape_string($con, $_POST['issue_date'] ?? date('Y-m-d'));
        $issued_by = mysqli_real_escape_string($con, $user_id);
        $notes = mysqli_real_escape_string($con, $_POST['notes'] ?? '');
        
        // Check if season is already issued (payment status = Completed)
        $payment_check = $seasonModel->getPaymentData($request_id);
        if ($payment_check && isset($payment_check['status']) && $payment_check['status'] === 'Completed') {
            $error = "This season has already been issued. Payment status is Completed.";
        } else {
            // Use model to issue season
            $result = $seasonModel->issueSeason($request_id, $season_rate, $issue_date, $issued_by, $notes);
            
            if ($result['success']) {
                // Redirect to prevent duplicate submissions
                $redirect_params = ['success' => '1'];
                if (!empty($filter_student_id)) {
                    $redirect_params['student_id'] = $filter_student_id;
                }
                $redirect_url = (defined('APP_BASE') ? APP_BASE : '') . "/season/IssueSeason.php?" . http_build_query($redirect_params);
                header("Location: " . $redirect_url);
                exit;
            } else {
                $error = $result['error'];
            }
        }
    }
}

// Now include head.php and menu.php after POST processing
include_once("../head.php");
include_once("../menu.php");

// Show success message if redirected
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Season issued successfully with calculated percentages!";
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'Paid';
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($con, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($con, $_GET['date_to']) : '';
$filter_month = isset($_GET['month']) ? mysqli_real_escape_string($con, $_GET['month']) : '';
$filter_student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
$from_payment = isset($_GET['from_payment']) && $_GET['from_payment'] == '1';

// Validate date formats
if (!empty($filter_date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
    $filter_date_from = '';
}
if (!empty($filter_date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
    $filter_date_to = '';
}
if (!empty($filter_month) && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = '';
}

// If redirected from payment collection, show success message
if ($from_payment) {
    $message = "Payment collected successfully! Showing student's requests ordered by payment date.";
}

// Determine if date filters are active
$has_date_filter = !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_month);

// If date filters are active, show both Paid and Completed
// Otherwise, use the status filter (default: Paid only)
$status_for_query = ($has_date_filter || $filter_status === 'All') ? '' : $filter_status;

// Fetch requests with filters
$issueable_requests = $seasonModel->getIssueableRequests(
    $is_hod ? $dept_code : '', 
    $status_for_query, 
    $filter_date_from, 
    $filter_date_to, 
    $filter_month,
    $filter_student_id
);

// Get request_id from URL (for POST redirects)
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
?>

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h3 class="mb-0"><i class="fas fa-ticket-alt"></i> Issue Season</h3>
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
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    

                    <div class="mb-3">
                        <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row">
                                <?php if (!empty($filter_student_id)): ?>
                                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($filter_student_id) ?>">
                                <?php endif; ?>
                                <div class="col-md-3">
                                    <label>Payment Status</label>
                                    <select name="status" class="form-control">
                                        <option value="Paid" <?= $filter_status === 'Paid' ? 'selected' : '' ?>>Paid Only</option>
                                        <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>>Completed Only</option>
                                        <option value="All" <?= $filter_status === 'All' ? 'selected' : '' ?>>All</option>
                                    </select>
                                    <small class="text-muted">Default: Paid only. When date filters applied, shows both Paid & Completed.</small>
                                </div>
                                <div class="col-md-3">
                                    <label>Month (YYYY-MM)</label>
                                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>" placeholder="YYYY-MM">
                                </div>
                                <div class="col-md-2">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>">
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label><br>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/IssueSeason.php<?= !empty($filter_student_id) ? '?student_id=' . urlencode($filter_student_id) : '' ?>" class="btn btn-secondary">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Route</th>
                                    <th>Payment Status</th>
                                    <th>Paid Amount</th>
                                    <th>Season Rate</th>
                                    <th>Balance</th>
                                    <th>Payment Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($issueable_requests)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No requests found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($issueable_requests as $req): ?>
                                        <?php
                                        // Calculate balance: paid_amount - season_rate
                                        $paid_amount = floatval($req['paid_amount'] ?? 0);
                                        $season_rate = floatval($req['season_rate'] ?? 0);
                                        $balance = $paid_amount - $season_rate;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($req['student_id']) ?></td>
                                            <td><?= htmlspecialchars($req['student_fullname'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $req['payment_status'] === 'Completed' ? 'success' : 'info' ?>">
                                                    <?= htmlspecialchars($req['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                Rs. <?= number_format($paid_amount, 2) ?>
                                            </td>
                                            <td>
                                                Rs. <?= number_format($season_rate, 2) ?>
                                            </td>
                                            <td>
                                                <span class="<?= $balance < 0 ? 'text-danger' : ($balance > 0 ? 'text-success' : '') ?>">
                                                    Rs. <?= number_format($balance, 2) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($req['payment_status'] === 'Completed' && !empty($req['updated_at'])) {
                                                    echo date('Y-m-d', strtotime($req['updated_at']));
                                                } elseif (!empty($req['payment_date'])) {
                                                    echo htmlspecialchars($req['payment_date']);
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($req['payment_status'] !== 'Completed'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" 
                                                            data-toggle="modal" 
                                                            data-target="#issueModal<?= $req['id'] ?>"
                                                            title="Issue Season">
                                                        <i class="fas fa-ticket-alt"></i> Issue Season
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Already Issued</span>
                                                <?php endif; ?>
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

        <!-- Issue Season Modals -->
        <?php foreach ($issueable_requests as $req): ?>
            <?php
            // Get payment data for this request
            $modal_payment_data = $seasonModel->getPaymentData($req['id']);
            $modal_current_paid = floatval($modal_payment_data['paid_amount'] ?? 0);
            $modal_is_completed = isset($modal_payment_data['status']) && $modal_payment_data['status'] === 'Completed';
            ?>
            <?php if (!$modal_is_completed): ?>
            <div class="modal fade" id="issueModal<?= $req['id'] ?>" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <form method="POST" action="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/IssueSeason.php">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title"><i class="fas fa-ticket-alt"></i> Issue Season - Request #<?= $req['id'] ?></h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3 p-2 bg-light rounded">
                                    <div class="col-md-6">
                                        <strong>Student ID:</strong> <?= htmlspecialchars($req['student_id']) ?><br>
                                        <strong>Student Name:</strong> <?= htmlspecialchars($req['student_fullname'] ?? '-') ?><br>
                                        <strong>Route:</strong> <?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?><br>
                                        <?php if (!empty($req['depot_name'])): ?>
                                            <strong>Depot:</strong> <?= htmlspecialchars($req['depot_name']) ?><br>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($modal_payment_data): ?>
                                            <strong>Paid Amount:</strong> Rs. <?= number_format($modal_payment_data['paid_amount'] ?? 0, 2) ?><br>
                                            <strong>Season Rate:</strong> Rs. <?= number_format($modal_payment_data['season_rate'] ?? 0, 2) ?><br>
                                            <strong>Balance:</strong> Rs. <?= number_format($modal_payment_data['remaining_balance'] ?? 0, 2) ?>
                                        <?php else: ?>
                                            <div class="alert alert-warning mt-2">
                                                <i class="fas fa-exclamation-triangle"></i> No payment record found.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <input type="hidden" name="action" value="issue">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                
                                <div class="form-group">
                                    <label>Season Rate (Rs.) <span class="text-danger">*</span></label>
                                    <input type="number" name="season_rate" id="season_rate_<?= $req['id'] ?>" class="form-control season-rate-input" 
                                           data-modal-id="<?= $req['id'] ?>"
                                           step="0.01" min="0.01" value="<?= $modal_current_paid > 0 ? number_format($modal_current_paid, 2, '.', '') : '' ?>" 
                                           required placeholder="Enter season rate">
                                    <small class="form-text text-muted">This will be the Student Portion (30% of total)</small>
                                </div>

                                <div class="alert alert-info" id="breakdown_<?= $req['id'] ?>">
                                    <strong><i class="fas fa-info-circle"></i> Payment Breakdown (Auto-calculated):</strong><br>
                                    <small>
                                        Season Rate: Rs. <span id="calc_season_rate_<?= $req['id'] ?>">0.00</span><br>
                                        Student Portion (30%): Rs. <span id="calc_student_<?= $req['id'] ?>">0.00</span><br>
                                        SLGTI Portion (35%): Rs. <span id="calc_slgti_<?= $req['id'] ?>">0.00</span><br>
                                        CTB Portion (35%): Rs. <span id="calc_ctb_<?= $req['id'] ?>">0.00</span><br>
                                        <strong>Total Amount: Rs. <span id="calc_total_<?= $req['id'] ?>">0.00</span></strong>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label>Issue Date <span class="text-danger">*</span></label>
                                    <input type="date" name="issue_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="3" 
                                              placeholder="Additional notes about the issuance"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-check"></i> Confirm Issue Season
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Global function to calculate breakdown for any modal
function calculateSeasonBreakdown(reqId) {
    var inputId = 'season_rate_' + reqId;
    var seasonRateInput = document.getElementById(inputId);
    
    if (!seasonRateInput) {
        return;
    }
    
    var seasonRate = parseFloat(seasonRateInput.value) || 0;
    
    var calcSeasonRate = document.getElementById('calc_season_rate_' + reqId);
    var calcStudent = document.getElementById('calc_student_' + reqId);
    var calcSlgti = document.getElementById('calc_slgti_' + reqId);
    var calcCtb = document.getElementById('calc_ctb_' + reqId);
    var calcTotal = document.getElementById('calc_total_' + reqId);
    
    if (!calcSeasonRate || !calcStudent || !calcSlgti || !calcCtb || !calcTotal) {
        return;
    }
    
    if (seasonRate > 0) {
        var studentPortion = seasonRate;
        var totalAmount = Math.round((studentPortion / 0.30) * 100) / 100;
        var slgtiPortion = Math.round((totalAmount * 0.35) * 100) / 100;
        var ctbPortion = Math.round((totalAmount * 0.35) * 100) / 100;
        
        calcSeasonRate.textContent = seasonRate.toFixed(2);
        calcStudent.textContent = studentPortion.toFixed(2);
        calcSlgti.textContent = slgtiPortion.toFixed(2);
        calcCtb.textContent = ctbPortion.toFixed(2);
        calcTotal.textContent = totalAmount.toFixed(2);
    } else {
        calcSeasonRate.textContent = '0.00';
        calcStudent.textContent = '0.00';
        calcSlgti.textContent = '0.00';
        calcCtb.textContent = '0.00';
        calcTotal.textContent = '0.00';
    }
}

// Setup event listeners for all modals when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Find all season rate inputs
    var seasonRateInputs = document.querySelectorAll('input[id^="season_rate_"]');
    
    seasonRateInputs.forEach(function(input) {
        var reqId = input.id.replace('season_rate_', '');
        
        // Add input event listeners
        input.addEventListener('input', function() {
            calculateSeasonBreakdown(reqId);
        });
        input.addEventListener('change', function() {
            calculateSeasonBreakdown(reqId);
        });
        
        // Setup modal show event
        var modalId = 'issueModal' + reqId;
        var modalElement = document.getElementById(modalId);
        if (modalElement) {
            modalElement.addEventListener('shown.bs.modal', function() {
                calculateSeasonBreakdown(reqId);
            });
        }
    });
    
    // Also use jQuery if available
    if (typeof jQuery !== 'undefined') {
        jQuery(function($) {
            $('[id^="issueModal"]').on('shown.bs.modal', function() {
                var modalId = $(this).attr('id');
                var reqId = modalId.replace('issueModal', '');
                calculateSeasonBreakdown(reqId);
            });
            
            $('input[id^="season_rate_"]').on('input change keyup', function() {
                var reqId = $(this).attr('id').replace('season_rate_', '');
                calculateSeasonBreakdown(reqId);
            });
        });
    }
});
</script>

<?php include_once("../footer.php"); ?>


