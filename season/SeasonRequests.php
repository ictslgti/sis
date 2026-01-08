<?php
// Main listing page for Season Requests
$title = "Season Requests | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
require_once("../auth.php");

// Access control: Students can view their own, HOD/ADM/SAO can view all
$user_type = auth_user_type();
$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_student = is_role('STU');
$is_hod = is_role('HOD');
$is_admin = is_role('ADM');
$is_sao = is_role('SAO');

if (!is_logged_in()) {
    require_login();
}

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

// Ensure tables exist
@mysqli_query($con, "CREATE TABLE IF NOT EXISTS `season_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(64) NOT NULL,
  `season_year` VARCHAR(20) NOT NULL,
  `season_name` VARCHAR(100) NOT NULL,
  `route_from` VARCHAR(255) NOT NULL,
  `route_to` VARCHAR(255) NOT NULL,
  `change_point` VARCHAR(255) DEFAULT NULL,
  `distance_km` DECIMAL(6,2) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` VARCHAR(64) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_season` (`student_id`,`season_year`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

@mysqli_query($con, "CREATE TABLE IF NOT EXISTS `season_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT UNSIGNED NOT NULL,
  `student_id` VARCHAR(64) NOT NULL,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `season_rate` DECIMAL(10,2) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `student_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `slgti_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `ctb_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Paid','Completed') NOT NULL DEFAULT 'Paid',
  `payment_date` DATE DEFAULT NULL,
  `payment_method` ENUM('Cash','Bank Transfer') DEFAULT NULL,
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `collected_by` VARCHAR(64) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_request_payment` (`request_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Filters
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$filter_year = isset($_GET['year']) ? mysqli_real_escape_string($con, $_GET['year']) : '';

$where = [];
if ($is_student) {
    $where[] = "sr.student_id = '".mysqli_real_escape_string($con, $user_id)."'";
}
// SAO can view all requests (removed restriction to only approved)
// Filter by HOD's department
if ($is_hod && !empty($dept_code)) {
    $where[] = "d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
}
if ($filter_status && in_array($filter_status, ['pending','approved','rejected','cancelled'])) {
    $where[] = "sr.status = '".$filter_status."'";
}
if ($filter_year) {
    $where[] = "sr.season_year = '".$filter_year."'";
}

$where_clause = !empty($where) ? "WHERE ".implode(" AND ", $where) : "";

// Fetch requests with payment info
// Join through student_enroll -> course -> department to filter by department
$sql = "SELECT sr.*, 
        sp.id as payment_id, sp.paid_amount, sp.season_rate, sp.remaining_balance, sp.status as payment_status,
        s.student_fullname, d.department_id, d.department_name
        FROM season_requests sr
        LEFT JOIN season_payments sp ON sr.id = sp.request_id
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        $where_clause
        ORDER BY sr.created_at DESC";

$result = mysqli_query($con, $sql);
$requests = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
}
?>

<div class="container-fluid mt-3">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0"><i class="fas fa-bus"></i> Season Requests Management</h3>
        </div>
        <div class="card-body">
            <!-- Action Buttons -->
            <div class="mb-3">
                <?php if ($is_student): ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/RequestSeason.php" class="btn btn-success"><i class="fas fa-plus"></i> New Season Request</a>
                <?php endif; ?>
                <?php if ($is_hod || $is_admin): ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php" class="btn btn-primary"><i class="fas fa-check"></i> Approve Requests</a>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php" class="btn btn-info"><i class="fas fa-money-bill"></i> Collect Payment</a>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/IssueSeason.php" class="btn btn-warning"><i class="fas fa-ticket-alt"></i> Issue Season</a>
                    
                <?php elseif ($is_sao): ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php" class="btn btn-info"><i class="fas fa-money-bill"></i> Collect Payment</a>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/IssueSeason.php" class="btn btn-warning"><i class="fas fa-ticket-alt"></i> Issue Season</a>
                   
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row">
                        <div class="col-md-4 mb-2">
                            <label for="filter_status">Status</label>
                            <select name="status" id="filter_status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="filter_year">Season Year</label>
                            <input type="text" name="year" id="filter_year" class="form-control" 
                                   placeholder="e.g., 2024" value="<?= htmlspecialchars($filter_year) ?>">
                        </div>
                        <div class="col-md-4 mb-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mr-2">Apply Filters</button>
                            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
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

            <!-- Requests Table -->
            <div class="table-responsive">
                <table id="requestsTable" class="table table-bordered table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Season Year</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Payment Status</th>
                            <th>Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No season requests found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['student_id']) ?></td>
                                    <td><?= htmlspecialchars($req['student_fullname'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($req['season_year']) ?></td>
                                    <td><?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?></td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'cancelled' => 'secondary'
                                        ];
                                        $status = $req['status'];
                                        $class = $badge_class[$status] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $class ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($req['payment_id']): ?>
                                            <span class="badge badge-<?= $req['payment_status'] === 'Completed' ? 'success' : 'info' ?>">
                                                <?= htmlspecialchars($req['payment_status']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">No Payment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($req['payment_id']): ?>
                                            Rs. <?= number_format($req['remaining_balance'], 2) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/RequestSeason.php?edit=<?= $req['id'] ?>" 
                                               class="btn btn-sm btn-info" 
                                               title="View Details">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if (($is_hod || $is_admin) && $req['status'] === 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success" 
                                                        data-toggle="modal" 
                                                        data-target="#approveModal<?= $req['id'] ?>"
                                                        title="Approve Request">
                                                    <i class="fas fa-check-circle"></i> Approve
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        data-toggle="modal" 
                                                        data-target="#rejectModal<?= $req['id'] ?>"
                                                        title="Reject Request">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($req['status'] === 'approved' && !$req['payment_id'] && ($is_hod || $is_admin || $is_sao || is_role('FIN'))): ?>
                                                <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/CollectSeasonPayment.php?request_id=<?= $req['id'] ?>" 
                                                   class="btn btn-sm btn-warning"
                                                   title="Collect Payment">
                                                    <i class="fas fa-money-bill-wave"></i> Payment
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Modals for Approve/Reject Actions -->
            <?php if ($is_hod || $is_admin): ?>
                <?php foreach ($requests as $req): ?>
                    <?php if ($req['status'] === 'pending'): ?>
                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?= $req['id'] ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="POST" action="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php">
                                        <div class="modal-header bg-success text-white">
                                            <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Request #<?= $req['id'] ?></h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>Student:</strong> <?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?></p>
                                            <?php if (!empty($req['depot_name'])): ?>
                                                <p><strong>Depot:</strong> <?= htmlspecialchars($req['depot_name']) ?></p>
                                            <?php endif; ?>
                                            <p><strong>Route:</strong> <?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?></p>
                                            <div class="form-group mt-3">
                                                <label>Approval Notes (Optional)</label>
                                                <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes about this approval"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirm Approval</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?= $req['id'] ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <form method="POST" action="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/ApproveSeasonRequest.php">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Request #<?= $req['id'] ?></h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>Student:</strong> <?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?></p>
                                            <?php if (!empty($req['depot_name'])): ?>
                                                <p><strong>Depot:</strong> <?= htmlspecialchars($req['depot_name']) ?></p>
                                            <?php endif; ?>
                                            <div class="form-group mt-3">
                                                <label>Rejection Reason <span class="text-danger">*</span></label>
                                                <textarea name="notes" class="form-control" rows="3" placeholder="Please provide a reason for rejection" required></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Confirm Rejection</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

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
                noResultsRow.textContent = 'No season requests found.';
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

