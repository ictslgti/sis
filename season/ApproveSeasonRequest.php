<?php
// Form for HOD to approve/reject season requests
$title = "Approve Season Requests | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
require_once("../auth.php");

require_roles(['HOD', 'ADM']);
$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_hod = is_role('HOD');
$is_admin = is_role('ADM');

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

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    
    if ($action === 'approve' || $action === 'reject') {
        // For HOD, verify the request belongs to their department
        if ($is_hod && !empty($dept_code)) {
            $check_sql = "SELECT sr.id 
                         FROM season_requests sr
                         LEFT JOIN student s ON sr.student_id = s.student_id
                         LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
                         LEFT JOIN course c ON c.course_id = se.course_id
                         WHERE sr.id = $request_id AND c.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
            $check_result = mysqli_query($con, $check_sql);
            if (!$check_result || mysqli_num_rows($check_result) === 0) {
                $error = "You can only approve/reject requests from your own department.";
            }
        }
        
        if (empty($error)) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $notes = mysqli_real_escape_string($con, $_POST['notes'] ?? '');
            $approved_by = mysqli_real_escape_string($con, $user_id);
            $approved_at = date('Y-m-d H:i:s');
            
            $sql = "UPDATE season_requests SET 
                    status = '$status',
                    approved_by = '$approved_by',
                    approved_at = '$approved_at',
                    notes = ".($notes ? "CONCAT(COALESCE(notes, ''), '\n', '$notes')" : "notes")."
                    WHERE id = $request_id AND status = 'pending'";
            
            if (mysqli_query($con, $sql)) {
                if (mysqli_affected_rows($con) > 0) {
                    $message = "Request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
                } else {
                    $error = "Request not found or already processed.";
                }
            } else {
                $error = "Error: " . mysqli_error($con);
            }
        }
    }
}

// Fetch pending requests - filter by HOD's department
$where_dept = '';
if ($is_hod && !empty($dept_code)) {
    $where_dept = "AND d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
}

// Fetch pending requests
$sql_pending = "SELECT sr.*, s.student_fullname, d.department_name
        FROM season_requests sr
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        WHERE sr.status = 'pending' $where_dept
        ORDER BY sr.created_at ASC";
$result_pending = mysqli_query($con, $sql_pending);
$pending_requests = [];
if ($result_pending) {
    while ($row = mysqli_fetch_assoc($result_pending)) {
        $pending_requests[] = $row;
    }
}

// Fetch approved requests - simplified for HOD
$sql_approved = "SELECT sr.id, sr.student_id, s.student_fullname, sr.season_year, sr.depot_name, sr.route_from, sr.route_to, sr.distance_km
        FROM season_requests sr
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        WHERE sr.status = 'approved' $where_dept
        ORDER BY sr.approved_at DESC";
$result_approved = mysqli_query($con, $sql_approved);
$approved_requests = [];
if ($result_approved) {
    while ($row = mysqli_fetch_assoc($result_approved)) {
        $approved_requests[] = $row;
    }
}
?>

<style>
    .nav-tabs .nav-link {
        color: #475569;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
    }
    
    .nav-tabs .nav-link:hover {
        color: #2563eb;
        background: rgba(37, 99, 235, 0.05);
        border-bottom-color: rgba(37, 99, 235, 0.3);
    }
    
    .nav-tabs .nav-link.active {
        color: #2563eb;
        background: rgba(37, 99, 235, 0.05);
        border-bottom-color: #2563eb;
        font-weight: 700;
    }
    
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: white;
        border-radius: 12px 12px 0 0 !important;
    }
    
    .table thead th {
        background: #f8f9fa;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
</style>
<div class="container-fluid mt-3">
    <div class="card shadow border-0">
        <div class="card-header">
            <h3 class="mb-0" style="color: white;"><i class="fas fa-check-circle"></i> Approve Season Requests</h3>
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

            <?php if (!$is_hod): ?>
            <div class="mb-3">
                <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
            </div>
            <?php endif; ?>

            <!-- Tabs for Pending and Approved -->
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                        <i class="fas fa-clock"></i> Pending Requests 
                        <span class="badge badge-warning ml-1"><?= count($pending_requests) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                        <i class="fas fa-check-circle"></i> Approved Requests 
                        <span class="badge badge-success ml-1"><?= count($approved_requests) ?></span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Pending Requests Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel">
                    <?php if (empty($pending_requests)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No pending requests to approve.
                        </div>
                    <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Season Year</th>
                                    <th>Depot</th>
                                <th>Route</th>
                                <th>Distance</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $req): ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['id']) ?></td>
                                    <td><?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?></td>
                                    <td><?= htmlspecialchars($req['season_year']) ?></td>
                                    <td><?= htmlspecialchars($req['depot_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?></td>
                                    <td><?= $req['distance_km'] ? htmlspecialchars($req['distance_km']) . ' km' : '-' ?></td>
                                    <td><?= htmlspecialchars($req['created_at']) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                data-toggle="modal" data-target="#approveModal<?= $req['id'] ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-toggle="modal" data-target="#rejectModal<?= $req['id'] ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/RequestSeason.php?edit=<?= $req['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>

                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?= $req['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title">Approve Request #<?= $req['id'] ?></h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Student:</strong> <?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?></p>
                                                    <?php if (!empty($req['depot_name'])): ?>
                                                        <p><strong>Depot:</strong> <?= htmlspecialchars($req['depot_name']) ?></p>
                                                    <?php endif; ?>
                                                    <p><strong>Route:</strong> <?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?></p>
                                                    
                                                    <div class="form-group">
                                                        <label>Approval Notes (Optional)</label>
                                                        <textarea name="notes" class="form-control" rows="3" 
                                                                  placeholder="Add any notes about this approval"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Confirm Approval</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?= $req['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Reject Request #<?= $req['id'] ?></h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Student:</strong> <?= htmlspecialchars($req['student_fullname'] ?? $req['student_id']) ?></p>
                                                    <?php if (!empty($req['depot_name'])): ?>
                                                        <p><strong>Depot:</strong> <?= htmlspecialchars($req['depot_name']) ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="form-group">
                                                        <label>Rejection Reason <span class="text-danger">*</span></label>
                                                        <textarea name="notes" class="form-control" rows="3" 
                                                                  placeholder="Please provide a reason for rejection" required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                    <?php endif; ?>
                </div>

                <!-- Approved Requests Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel">
                    <?php if (empty($approved_requests)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No approved requests found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Student</th>
                                        <th>Season Year</th>
                                        <th>Depot</th>
                                        <th>Route</th>
                                        <th>Distance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approved_requests as $req): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($req['id']) ?></td>
                                            <td><?= htmlspecialchars($req['student_fullname'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($req['season_year']) ?></td>
                                            <td><?= htmlspecialchars($req['depot_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($req['route_from']) ?> → <?= htmlspecialchars($req['route_to']) ?></td>
                                            <td><?= $req['distance_km'] ? htmlspecialchars($req['distance_km']) . ' km' : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once("../footer.php"); ?>

