<?php
// Report 2: Student ID, Name, NIC, Student_paid, slgti_paid, ctb_paid, total_amount
// Filter by payment_reference (month)

// Check for export FIRST - handle it before any includes
$export = isset($_GET['export']) ? trim($_GET['export']) : '';

// If export requested, handle it immediately
if ($export === 'excel' || $export === 'csv') {
    // Start session and get basic config for database
    if (session_status() === PHP_SESSION_NONE) { 
        session_start(); 
    }
    
    // Minimal database connection
    require_once(__DIR__ . '/../config.php');
    require_once(__DIR__ . '/../auth.php');
    
    // Check roles for export
    $allowed = ['HOD', 'ADM', 'FIN', 'SAO'];
    if (!is_any($allowed)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        die('Access denied.');
    }
    
    // Get filter
    $filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';
    if (!empty($filter_month) && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
        $filter_month = '';
    }
    
    // Get HOD department if needed
    $user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
    $is_hod = is_role('HOD');
    $dept_code = null;
    if ($is_hod) {
        $dept_code = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
        if (empty($dept_code) && !empty($user_id)) {
            $staff_sql = "SELECT department_id FROM staff WHERE staff_id = '".mysqli_real_escape_string($con, $user_id)."' LIMIT 1";
            $staff_result = mysqli_query($con, $staff_sql);
            if ($staff_result && mysqli_num_rows($staff_result) > 0) {
                $staff_row = mysqli_fetch_assoc($staff_result);
                $dept_code = $staff_row['department_id'] ?? '';
            }
        }
    }
    
    // Build WHERE clause
    $where = [];
    $where[] = "sr.status = 'approved'";
    $where[] = "sp.id IS NOT NULL";
    if (!empty($filter_month)) {
        $where[] = "sp.payment_reference = '".mysqli_real_escape_string($con, $filter_month)."'";
    }
    if ($is_hod && !empty($dept_code)) {
        $where[] = "d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
    }
    $where_clause = "WHERE " . implode(" AND ", $where);
    
    // Query
    $sql = "SELECT 
            sr.student_id,
            s.student_fullname,
            s.student_nic,
            sp.student_paid,
            sp.slgti_paid,
            sp.ctb_paid,
            sp.total_amount,
            sp.payment_reference
            FROM season_requests sr
            LEFT JOIN season_payments sp ON sr.id = sp.request_id
            LEFT JOIN student s ON sr.student_id = s.student_id
            LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
            LEFT JOIN course c ON c.course_id = se.course_id
            LEFT JOIN department d ON d.department_id = c.department_id
            $where_clause
            ORDER BY sr.student_id, sp.payment_reference";
    
    $result = mysqli_query($con, $sql);
    if (!$result) {
        http_response_code(500);
        header('Content-Type: text/plain');
        die('Database error');
    }
    
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set headers
    $filename = 'season_report2_' . ($filter_month ? $filter_month : 'all') . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output BOM and CSV
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Student Name', 'NIC Number', 'Student Paid', 'SLGTI Paid', 'CTB Paid', 'Total Amount', 'Payment Month']);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($out, [
                $row['student_id'] ?? '',
                $row['student_fullname'] ?? '',
                $row['student_nic'] ?? '',
                number_format((float)($row['student_paid'] ?? 0), 2, '.', ''),
                number_format((float)($row['slgti_paid'] ?? 0), 2, '.', ''),
                number_format((float)($row['ctb_paid'] ?? 0), 2, '.', ''),
                number_format((float)($row['total_amount'] ?? 0), 2, '.', ''),
                $row['payment_reference'] ?? ''
            ]);
        }
        mysqli_free_result($result);
    }
    
    fclose($out);
    exit;
}

// Normal page load continues here
$title = "Season Report 2 - Payment Details | SLGTI";
include_once("../config.php");
require_once("../auth.php");
require_roles(['HOD', 'ADM', 'FIN', 'SAO']);

$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_hod = is_role('HOD');

// Get HOD's department code
$dept_code = null;
if ($is_hod) {
    $dept_code = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
    if (empty($dept_code) && !empty($user_id)) {
        $staff_sql = "SELECT department_id FROM staff WHERE staff_id = '".mysqli_real_escape_string($con, $user_id)."' LIMIT 1";
        $staff_result = mysqli_query($con, $staff_sql);
        if ($staff_result && mysqli_num_rows($staff_result) > 0) {
            $staff_row = mysqli_fetch_assoc($staff_result);
            $dept_code = $staff_row['department_id'] ?? '';
        }
    }
}

// Get filter parameters
$filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';

// Validate month format
if (!empty($filter_month) && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = '';
}

// Build WHERE clause
$where = [];
$where[] = "sr.status = 'approved'";
$where[] = "sp.id IS NOT NULL";

// Filter by payment_reference (month)
if (!empty($filter_month)) {
    $where[] = "sp.payment_reference = '".mysqli_real_escape_string($con, $filter_month)."'";
}

// Filter by HOD's department
if ($is_hod && !empty($dept_code)) {
    $where[] = "d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Query for report data
$sql = "SELECT 
        sr.student_id,
        s.student_fullname,
        s.student_nic,
        sp.student_paid,
        sp.slgti_paid,
        sp.ctb_paid,
        sp.total_amount,
        sp.payment_reference
        FROM season_requests sr
        LEFT JOIN season_payments sp ON sr.id = sp.request_id
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        $where_clause
        ORDER BY sr.student_id, sp.payment_reference";

// Fetch data for display
$result = mysqli_query($con, $sql);
$report_data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
    }
    if ($result) mysqli_free_result($result);
}

include_once("../head.php");
include_once("../menu.php");
?>

<div class="container-fluid mt-3">
    <div class="card shadow">
        <div class="card-header bg-info text-white">
            <h3 class="mb-0"><i class="fas fa-file-excel"></i> Season Report 2 - Payment Details</h3>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filter</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4">
                            <label>Payment Month (YYYY-MM)</label>
                            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label><br>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
                            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport2.php" class="btn btn-secondary">Reset</a>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label><br>
                            <?php 
                            $exportQuery = array_merge($_GET, ['export' => 'excel']);
                            unset($exportQuery['export']);
                            $exportQuery['export'] = 'excel';
                            ?>
                            <a href="?<?= http_build_query($exportQuery) ?>" class="btn btn-success">
                                <i class="fas fa-file-excel"></i> Download Excel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>NIC Number</th>
                            <th>Student Paid</th>
                            <th>SLGTI Paid</th>
                            <th>CTB Paid</th>
                            <th>Total Amount</th>
                            <th>Payment Month</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No data found.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $total_student = 0;
                            $total_slgti = 0;
                            $total_ctb = 0;
                            $total_amount = 0;
                            ?>
                            <?php foreach ($report_data as $row): ?>
                                <?php
                                $student_paid = floatval($row['student_paid'] ?? 0);
                                $slgti_paid = floatval($row['slgti_paid'] ?? 0);
                                $ctb_paid = floatval($row['ctb_paid'] ?? 0);
                                $total_amt = floatval($row['total_amount'] ?? 0);
                                $total_student += $student_paid;
                                $total_slgti += $slgti_paid;
                                $total_ctb += $ctb_paid;
                                $total_amount += $total_amt;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['student_id']) ?></td>
                                    <td><?= htmlspecialchars($row['student_fullname'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['student_nic'] ?? '-') ?></td>
                                    <td>Rs. <?= number_format($student_paid, 2) ?></td>
                                    <td>Rs. <?= number_format($slgti_paid, 2) ?></td>
                                    <td>Rs. <?= number_format($ctb_paid, 2) ?></td>
                                    <td>Rs. <?= number_format($total_amt, 2) ?></td>
                                    <td><?= htmlspecialchars($row['payment_reference'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-info font-weight-bold">
                                <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                <td><strong>Rs. <?= number_format($total_student, 2) ?></strong></td>
                                <td><strong>Rs. <?= number_format($total_slgti, 2) ?></strong></td>
                                <td><strong>Rs. <?= number_format($total_ctb, 2) ?></strong></td>
                                <td><strong>Rs. <?= number_format($total_amount, 2) ?></strong></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once("../footer.php"); ?>
