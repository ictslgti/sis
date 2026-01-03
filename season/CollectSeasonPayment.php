<?php
// Form to collect payment for approved season requests
$title = "Collect Season Payment | SLGTI";
include_once("../config.php");
require_once("../auth.php");
require_once(__DIR__ . "/SeasonModel.php");

require_roles(['HOD', 'ADM', 'FIN', 'SAO']);
$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_hod = is_role('HOD');
$is_sao = is_role('SAO');
$is_admin = is_role('ADM');

$message = '';
$error = '';

// Initialize model
if (!isset($con) || !$con) {
    die("Database connection not available. Please check config.php");
}
$seasonModel = new SeasonModel($con);

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

// Handle payment collection - MUST be before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'collect') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $student_id = mysqli_real_escape_string($con, $_POST['student_id'] ?? '');
        $season_rate = floatval($_POST['season_rate'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $payment_method = mysqli_real_escape_string($con, $_POST['payment_method'] ?? 'Cash');
        $payment_date = mysqli_real_escape_string($con, $_POST['payment_date'] ?? date('Y-m-d'));
        $paid_month = isset($_POST['paid_month']) ? trim($_POST['paid_month']) : '';
        $collected_by = mysqli_real_escape_string($con, $user_id);
        
        // Use model to collect payment
        $result = $seasonModel->collectPayment(
            $request_id,
            $student_id,
            $season_rate,
            $paid_amount,
            $payment_method,
            $payment_date,
            $collected_by,
            $paid_month
        );
        
        if ($result['success']) {
            // Get student_id from request to pass to IssueSeason
            $req_sql = "SELECT student_id FROM season_requests WHERE id = " . intval($request_id);
            $req_result = mysqli_query($con, $req_sql);
            $student_id_for_redirect = '';
            if ($req_result && mysqli_num_rows($req_result) > 0) {
                $req_row = mysqli_fetch_assoc($req_result);
                $student_id_for_redirect = urlencode($req_row['student_id']);
            }
            
            // Redirect to IssueSeason page with student_id filter
            $redirect_url = (defined('APP_BASE') ? APP_BASE : '') . "/season/IssueSeason.php?student_id=" . $student_id_for_redirect . "&from_payment=1";
            header("Location: " . $redirect_url);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// Now include head.php and menu.php after POST processing (to allow redirects)
include_once("../head.php");
include_once("../menu.php");

// Get request_id from URL or POST (after form submission)
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : (isset($_POST['request_id']) ? intval($_POST['request_id']) : 0);
$request_data = null;
$payment_data = null;

// Show success message if redirected after successful payment
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = "Payment collected successfully!";
}

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
        
        // Get existing payment if any
        $pay_sql = "SELECT * FROM season_payments WHERE request_id = $request_id";
        $pay_result = mysqli_query($con, $pay_sql);
        if ($pay_result && mysqli_num_rows($pay_result) > 0) {
            $payment_data = mysqli_fetch_assoc($pay_result);
        }
    }
}

// Fetch all approved requests without payment or with partial payment
// Filter by HOD's department
$where_dept = '';
if ($is_hod && !empty($dept_code)) {
    $where_dept = "AND d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
}

$sql = "SELECT sr.*, s.student_fullname, sp.id as payment_id, sp.paid_amount, sp.remaining_balance
        FROM season_requests sr
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN season_payments sp ON sr.id = sp.request_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        WHERE sr.status = 'approved' $where_dept
        ORDER BY sr.id DESC";
$result = mysqli_query($con, $sql);
$approved_requests = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $approved_requests[] = $row;
    }
}
?>

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-md-<?= $request_id > 0 ? '8' : '12' ?>">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h3 class="mb-0"><i class="fas fa-money-bill-wave"></i> Collect Season Payment</h3>
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

                    <!-- Live Search Filter -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-search"></i> Live Search</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-10">
                                    <label>Search by Student ID or Name</label>
                                    <input type="text" id="searchStudentInput" class="form-control" 
                                           placeholder="Type to search by student ID or name...">
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label><br>
                                    <button type="button" id="resetSearchBtn" class="btn btn-secondary btn-block">Reset</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="requestsTable" class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($approved_requests)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No approved requests found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($approved_requests as $req): ?>
                                        <tr class="<?= $req['id'] == $request_id ? 'table-info' : '' ?>">
                                            <td><?= htmlspecialchars($req['student_id']) ?></td>
                                            <td><?= htmlspecialchars($req['student_fullname'] ?? '-') ?></td>
                                            <td>
                                                <?php if ($is_sao && !$is_hod && !$is_admin): ?>
                                                    <!-- SAO: Use modal for quick payment collection -->
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            data-toggle="modal" 
                                                            data-target="#paymentModal<?= $req['id'] ?>"
                                                            title="Collect Payment">
                                                        <i class="fas fa-money-bill-wave"></i> Collect Payment
                                                    </button>
                                                <?php else: ?>
                                                    <a href="?request_id=<?= $req['id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i> <?= $req['payment_id'] ? 'Update' : 'Collect' ?> Payment
                                                    </a>
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

        <?php if ($request_id > 0 && $request_data): ?>
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Payment Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php?request_id=<?= $request_data['id'] ?>">
                        <input type="hidden" name="action" value="collect">
                        <input type="hidden" name="request_id" value="<?= $request_data['id'] ?>">
                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($request_data['student_id']) ?>">
                        
                        <div class="form-group">
                            <label>Student</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($request_data['student_fullname'] ?? $request_data['student_id']) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Season</label>
                            <input type="text" class="form-control" 
                                   value="<?= htmlspecialchars($request_data['depot_name'] ?? '') ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Season Rate (Rs.)</label>
                            <input type="number" name="season_rate" class="form-control" step="0.01" min="0" 
                                   value="0" placeholder="Enter full season rate (default: 0)">
                        </div>

                        <div class="form-group">
                            <label>Paid Amount (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" name="paid_amount" class="form-control" step="0.01" min="0.01" 
                                   value="" required placeholder="Enter payment amount">
                            <small class="form-text text-muted">Can be partial payment (less than season rate)</small>
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
                            <label>Paid Month <span class="text-danger">*</span></label>
                            <input type="month" name="paid_month" class="form-control" 
                                   value="<?= date('Y-m') ?>" required>
                            <small class="form-text text-muted">Select the month for which payment is made</small>
                        </div>

                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-save"></i> Collect Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Collection Modals for SAO -->
<?php if ($is_sao && !$is_hod && !$is_admin): ?>
    <?php foreach ($approved_requests as $req): ?>
        <?php
        // Get payment data for this request
        $modal_payment_data = null;
        $pay_check_sql = "SELECT * FROM season_payments WHERE request_id = " . intval($req['id']);
        $pay_check_result = mysqli_query($con, $pay_check_sql);
        if ($pay_check_result && mysqli_num_rows($pay_check_result) > 0) {
            $modal_payment_data = mysqli_fetch_assoc($pay_check_result);
        }
        ?>
        <div class="modal fade" id="paymentModal<?= $req['id'] ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <form method="POST" action="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="fas fa-money-bill-wave"></i> Collect Payment - Request #<?= $req['id'] ?></h5>
                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3 p-2 bg-light rounded">
                                <div class="col-md-6">
                                    <strong>Student:</strong> <?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?><br>
                                    <?php if (!empty($req['depot_name'])): ?>
                                        <strong>Depot:</strong> <?= htmlspecialchars($req['depot_name']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Route:</strong> <?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?><br>
                                    <strong>Season Year:</strong> <?= htmlspecialchars($req['season_year']) ?>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <input type="hidden" name="action" value="collect">
                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="student_id" value="<?= htmlspecialchars($req['student_id']) ?>">
                            
                            <div class="form-group">
                                <label>Season Rate (Rs.)</label>
                                <input type="number" name="season_rate" class="form-control" step="0.01" min="0" 
                                       value="0" placeholder="Enter full season rate (default: 0)">
                            </div>

                            <div class="form-group">
                                <label>Paid Amount (Rs.) <span class="text-danger">*</span></label>
                                <input type="number" name="paid_amount" class="form-control" step="0.01" min="0.01" 
                                       value="" required placeholder="Enter payment amount">
                                <small class="form-text text-muted">Can be partial payment (less than season rate)</small>
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
                                <label>Paid Month <span class="text-danger">*</span></label>
                                <input type="month" name="paid_month" class="form-control" 
                                       value="<?= date('Y-m') ?>" required>
                                <small class="form-text text-muted">Select the month for which payment is made</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Collect Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
// Live search filter functionality
(function() {
    var searchInput = document.getElementById('searchStudentInput');
    var resetBtn = document.getElementById('resetSearchBtn');
    var table = document.getElementById('requestsTable');
    
    if (!searchInput || !table) return;
    
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    function filterTable() {
        var searchTerm = searchInput.value.toLowerCase().trim();
        var rows = tbody.querySelectorAll('tr');
        var visibleCount = 0;
        
        rows.forEach(function(row) {
            // Skip the "No requests found" row
            if (row.querySelector('td[colspan]')) {
                return;
            }
            
            var studentId = '';
            var studentName = '';
            var cells = row.querySelectorAll('td');
            
            if (cells.length >= 2) {
                studentId = (cells[0].textContent || '').toLowerCase();
                studentName = (cells[1].textContent || '').toLowerCase();
            }
            
            // Check if search term matches student ID or name
            var matches = searchTerm === '' || 
                         studentId.includes(searchTerm) || 
                         studentName.includes(searchTerm);
            
            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide "No requests found" message
        var noResultsRow = tbody.querySelector('tr td[colspan]');
        if (noResultsRow) {
            var noResultsTr = noResultsRow.closest('tr');
            if (visibleCount === 0 && searchTerm !== '') {
                noResultsTr.style.display = '';
                noResultsRow.textContent = 'No matching requests found.';
            } else if (visibleCount === 0) {
                noResultsTr.style.display = '';
                noResultsRow.textContent = 'No approved requests found.';
            } else {
                noResultsTr.style.display = 'none';
            }
        }
    }
    
    // Add event listener for live search
    searchInput.addEventListener('input', filterTable);
    searchInput.addEventListener('keyup', filterTable);
    
    // Reset button functionality
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterTable();
            searchInput.focus();
        });
    }
    
    // Initial filter (in case there's a value on page load)
    filterTable();
})();
</script>

<?php include_once("../footer.php"); ?>

