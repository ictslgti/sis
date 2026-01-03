<?php
// Form for students to request a season
$title = "Request Season | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
require_once("../auth.php");

require_login();
$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_student = is_role('STU');
$is_hod = is_role('HOD');
$is_admin = is_role('ADM');
$is_sao = is_role('SAO');

// Ensure tables exist (same as SeasonRequests.php)
@mysqli_query($con, "CREATE TABLE IF NOT EXISTS `season_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` VARCHAR(64) NOT NULL,
  `season_year` VARCHAR(20) NOT NULL,
  `season_name` VARCHAR(100) NOT NULL,
  `depot_name` ENUM('Kilinochchi','Vavuniya','PointPedro','Kondavil') DEFAULT NULL,
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

// Add depot_name column if it doesn't exist
$col_check = mysqli_query($con, "SHOW COLUMNS FROM `season_requests` LIKE 'depot_name'");
if (!$col_check || mysqli_num_rows($col_check) === 0) {
    @mysqli_query($con, "ALTER TABLE `season_requests` ADD COLUMN `depot_name` ENUM('Kilinochchi','Vavuniya','PointPedro','Kondavil') DEFAULT NULL AFTER `season_name`");
}

$message = '';
$error = '';
$edit_mode = false;
$request_data = null;

// Handle edit/view mode
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $sql = "SELECT sr.*, s.student_fullname 
            FROM season_requests sr
            LEFT JOIN student s ON sr.student_id = s.student_id
            WHERE sr.id = $edit_id";
    if ($is_student) {
        $sql .= " AND sr.student_id = '".mysqli_real_escape_string($con, $user_id)."'";
    }
    // For HOD, filter by department
    if ($is_hod && !$is_admin) {
        $dept_code = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
        if (empty($dept_code) && !empty($user_id)) {
            $staff_sql = "SELECT department_id FROM staff WHERE staff_id = '".mysqli_real_escape_string($con, $user_id)."' LIMIT 1";
            $staff_result = mysqli_query($con, $staff_sql);
            if ($staff_result && mysqli_num_rows($staff_result) > 0) {
                $staff_row = mysqli_fetch_assoc($staff_result);
                $dept_code = $staff_row['department_id'] ?? '';
            }
        }
        if (!empty($dept_code)) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM student_enroll se
                JOIN course c ON c.course_id = se.course_id
                WHERE se.student_id = sr.student_id 
                AND se.student_enroll_status IN ('Following','Active')
                AND c.department_id = '".mysqli_real_escape_string($con, $dept_code)."'
            )";
        }
    }
    $result = mysqli_query($con, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $request_data = mysqli_fetch_assoc($result);
        $edit_mode = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $student_id = mysqli_real_escape_string($con, $_POST['student_id'] ?? $user_id);
        $season_year = mysqli_real_escape_string($con, $_POST['season_year'] ?? '');
        $depot_name = mysqli_real_escape_string($con, $_POST['depot_name'] ?? '');
        $route_from = mysqli_real_escape_string($con, $_POST['route_from'] ?? '');
        $route_to = mysqli_real_escape_string($con, $_POST['route_to'] ?? '');
        $change_point = mysqli_real_escape_string($con, $_POST['change_point'] ?? '');
        $distance_km = !empty($_POST['distance_km']) ? floatval($_POST['distance_km']) : 'NULL';
        $notes = mysqli_real_escape_string($con, $_POST['notes'] ?? '');
        
        // Validation
        if (empty($season_year) || empty($depot_name) || empty($route_from) || empty($route_to)) {
            $error = "Please fill all required fields.";
        } else {
            // Validate depot_name
            if (!in_array($depot_name, ['Kilinochchi', 'Vavuniya', 'PointPedro', 'Kondavil'])) {
                $error = "Invalid depot name selected.";
            } else {
                // Check for duplicate season year for same student
                $check_sql = "SELECT id FROM season_requests WHERE student_id = '$student_id' AND season_year = '$season_year'";
                $check_result = mysqli_query($con, $check_sql);
                if ($check_result && mysqli_num_rows($check_result) > 0) {
                    $error = "You already have a request for season year: $season_year";
                } else {
                    $sql = "INSERT INTO season_requests (student_id, season_year, depot_name, route_from, route_to, change_point, distance_km, notes, status)
                            VALUES ('$student_id', '$season_year', '$depot_name', '$route_from', '$route_to', 
                                    ".($change_point ? "'$change_point'" : "NULL").", 
                                    $distance_km, ".($notes ? "'$notes'" : "NULL").", 'pending')";
                    
                    if (mysqli_query($con, $sql)) {
                        $message = "Season request submitted successfully! It will be reviewed by HOD.";
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = "Error: " . mysqli_error($con);
                    }
                }
            }
        }
    } elseif ($action === 'update' && $edit_mode) {
        $id = intval($_POST['id']);
        $depot_name = mysqli_real_escape_string($con, $_POST['depot_name'] ?? '');
        $route_from = mysqli_real_escape_string($con, $_POST['route_from'] ?? '');
        $route_to = mysqli_real_escape_string($con, $_POST['route_to'] ?? '');
        $change_point = mysqli_real_escape_string($con, $_POST['change_point'] ?? '');
        $distance_km = !empty($_POST['distance_km']) ? floatval($_POST['distance_km']) : 'NULL';
        $notes = mysqli_real_escape_string($con, $_POST['notes'] ?? '');
        $status = mysqli_real_escape_string($con, $_POST['status'] ?? '');
        
        if (empty($depot_name) || empty($route_from) || empty($route_to)) {
            $error = "Please fill all required fields.";
        } else {
            // Validate depot_name
            if (!in_array($depot_name, ['Kilinochchi', 'Vavuniya', 'PointPedro', 'Kondavil'])) {
                $error = "Invalid depot name selected.";
            } else {
            // Students can only update if status is pending
            // HOD/ADM can update any status
            $can_update = false;
            if ($is_student && $request_data && $request_data['status'] === 'pending') {
                $can_update = true;
                $status = 'pending'; // Students cannot change status
            } elseif (($is_hod || $is_admin || $is_sao) && $request_data) {
                $can_update = true;
                // Validate status if provided
                if (!empty($status) && !in_array($status, ['pending','approved','rejected','cancelled'])) {
                    $status = $request_data['status']; // Keep existing if invalid
                } elseif (empty($status)) {
                    $status = $request_data['status']; // Keep existing if not provided
                }
            }
            
            if ($can_update) {
                $update_fields = [
                    "depot_name = '$depot_name'",
                    "route_from = '$route_from'",
                    "route_to = '$route_to'",
                    "change_point = ".($change_point ? "'$change_point'" : "NULL"),
                    "distance_km = $distance_km",
                    "notes = ".($notes ? "'$notes'" : "NULL")
                ];
                
                // Only update status if HOD/ADM/SAO
                if (($is_hod || $is_admin || $is_sao) && !empty($status)) {
                    $update_fields[] = "status = '$status'";
                }
                
                $sql = "UPDATE season_requests SET ".implode(', ', $update_fields)." WHERE id = $id";
                
                if (mysqli_query($con, $sql)) {
                    $message = "Request updated successfully!";
                    // Reload data
                    $sql = "SELECT sr.*, s.student_fullname 
                            FROM season_requests sr
                            LEFT JOIN student s ON sr.student_id = s.student_id
                            WHERE sr.id = $id";
                    $result = mysqli_query($con, $sql);
                    if ($result) $request_data = mysqli_fetch_assoc($result);
                } else {
                    $error = "Error: " . mysqli_error($con);
                }
            } else {
                $error = "Cannot update request. Status must be 'pending' for students.";
            }
            }
        }
    }
}

// Get student info - use request student_id if in edit mode, otherwise session user_id
$display_student_id = $edit_mode && $request_data ? $request_data['student_id'] : $user_id;
$student_name = '';
if ($edit_mode && $request_data && isset($request_data['student_fullname'])) {
    $student_name = $request_data['student_fullname'];
} else {
    $student_sql = "SELECT student_fullname FROM student WHERE student_id = '".mysqli_real_escape_string($con, $display_student_id)."'";
    $student_result = mysqli_query($con, $student_sql);
    if ($student_result && mysqli_num_rows($student_result) > 0) {
        $student_row = mysqli_fetch_assoc($student_result);
        $student_name = $student_row['student_fullname'];
    }
}
?>

<div class="container mt-3">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h3 class="mb-0"><i class="fas fa-bus"></i> <?= $edit_mode ? 'View/Edit' : 'New' ?> Season Request</h3>
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

            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $edit_mode ? 'update' : 'create' ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($request_data['id']) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Student ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($display_student_id) ?>" 
                           readonly disabled>
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($display_student_id) ?>">
                    <small class="form-text text-muted">
                        <?= htmlspecialchars($student_name ?: 'Student name not found') ?>
                    </small>
                </div>

                <div class="form-group">
                    <label>Season Year <span class="text-danger">*</span></label>
                    <input type="text" name="season_year" class="form-control" 
                           value="<?= htmlspecialchars($request_data['season_year'] ?? '') ?>" 
                           placeholder="e.g., 2025/2026" required 
                           <?= ($edit_mode && ($is_student || $request_data['status'] !== 'pending')) ? 'readonly' : '' ?>>
                    <small class="form-text text-muted">Format: YYYY/YYYY</small>
                </div>

                <div class="form-group">
                    <label>Depot Name <span class="text-danger">*</span></label>
                    <select name="depot_name" class="form-control" required>
                        <option value="">-- Select Depot --</option>
                        <option value="Kilinochchi" <?= ($request_data['depot_name'] ?? '') === 'Kilinochchi' ? 'selected' : '' ?>>Kilinochchi</option>
                        <option value="Vavuniya" <?= ($request_data['depot_name'] ?? '') === 'Vavuniya' ? 'selected' : '' ?>>Vavuniya</option>
                        <option value="PointPedro" <?= ($request_data['depot_name'] ?? '') === 'PointPedro' ? 'selected' : '' ?>>PointPedro</option>
                        <option value="Kondavil" <?= ($request_data['depot_name'] ?? '') === 'Kondavil' ? 'selected' : '' ?>>Kondavil</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Route From <span class="text-danger">*</span></label>
                            <input type="text" name="route_from" class="form-control" 
                                   value="<?= htmlspecialchars($request_data['route_from'] ?? '') ?>" 
                                   placeholder="Starting point" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Route To <span class="text-danger">*</span></label>
                            <input type="text" name="route_to" class="form-control" 
                                   value="<?= htmlspecialchars($request_data['route_to'] ?? '') ?>" 
                                   placeholder="Destination" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Change Point</label>
                    <input type="text" name="change_point" class="form-control" 
                           value="<?= htmlspecialchars($request_data['change_point'] ?? '') ?>" 
                           placeholder="Optional change point">
                </div>

                <div class="form-group">
                    <label>Distance (km)</label>
                    <input type="number" name="distance_km" class="form-control" step="0.01" 
                           value="<?= htmlspecialchars($request_data['distance_km'] ?? '') ?>" 
                           placeholder="Distance in kilometers">
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Additional notes"><?= htmlspecialchars($request_data['notes'] ?? '') ?></textarea>
                </div>

                <?php if ($edit_mode): ?>
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <?php if ($is_hod || $is_admin || $is_sao): ?>
                            <select name="status" class="form-control" required>
                                <option value="pending" <?= ($request_data['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= ($request_data['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= ($request_data['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="cancelled" <?= ($request_data['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        <?php else: ?>
                            <input type="text" class="form-control" value="<?= ucfirst($request_data['status'] ?? 'pending') ?>" readonly>
                        <?php endif; ?>
                        
                        <?php if (!empty($request_data['approved_by'])): ?>
                            <small class="form-text text-muted">
                                <strong>Approved by:</strong> <?= htmlspecialchars($request_data['approved_by']) ?>
                                <?php if (!empty($request_data['approved_at'])): ?>
                                    <br><strong>Approved at:</strong> <?= htmlspecialchars($request_data['approved_at']) ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                        
                        <?php if (!empty($request_data['created_at'])): ?>
                            <small class="form-text text-muted">
                                <strong>Created:</strong> <?= htmlspecialchars($request_data['created_at']) ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <?php if ($edit_mode): ?>
                        <?php if ($is_student && $request_data['status'] === 'pending'): ?>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Request</button>
                        <?php elseif ($is_hod || $is_admin || $is_sao): ?>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Request</button>
                        <?php endif; ?>
                    <?php elseif (!$edit_mode): ?>
                        <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Submit Request</button>
                    <?php endif; ?>
                    <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonRequests.php" class="btn btn-secondary">Back to List</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once("../footer.php"); ?>

