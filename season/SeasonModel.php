<?php
/**
 * Season Model
 * Handles all database operations for season requests and payments
 */
class SeasonModel {
    private $con;
    
    public function __construct($connection) {
        if (!$connection) {
            throw new Exception("Database connection is required");
        }
        $this->con = $connection;
    }
    
    /**
     * Issue season and update payment record with calculated percentages
     * 
     * @param int $request_id The season request ID
     * @param float $season_rate The season rate entered by user (same as student portion, 30% of total)
     * @param string $issue_date The date when season is issued
     * @param string $issued_by User ID who issued the season
     * @param string $notes Additional notes
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function issueSeason($request_id, $season_rate, $issue_date, $issued_by, $notes = '') {
        // Validate inputs
        if ($request_id <= 0) {
            return ['success' => false, 'error' => 'Invalid request ID'];
        }
        
        if ($season_rate <= 0) {
            return ['success' => false, 'error' => 'Season rate must be greater than zero'];
        }
        
        // Check if payment exists
        $pay_sql = "SELECT * FROM season_payments WHERE request_id = " . intval($request_id);
        $pay_result = mysqli_query($this->con, $pay_sql);
        
        if (!$pay_result || mysqli_num_rows($pay_result) == 0) {
            return ['success' => false, 'error' => 'No payment record found for this request'];
        }
        
        $payment = mysqli_fetch_assoc($pay_result);
        
        // Calculate percentages
        // Student Portion = Season Rate (same amount, 30% of total)
        $student_paid = floatval($season_rate);
        
        // Total amount: if student portion is 30%, then total = student_paid / 0.30
        $total_amount = round($student_paid / 0.30, 2);
        
        // Calculate SLGTI and CTB portions (35% each of total)
        $slgti_paid = round($total_amount * 0.35, 2);  // 35% of total
        $ctb_paid = round($total_amount * 0.35, 2);    // 35% of total
        
        // Remaining balance should be 0 when issuing
        $remaining_balance = 0;
        
        // Escape values for SQL
        $season_rate_escaped = mysqli_real_escape_string($this->con, $season_rate);
        $student_paid_escaped = mysqli_real_escape_string($this->con, $student_paid);
        $slgti_paid_escaped = mysqli_real_escape_string($this->con, $slgti_paid);
        $ctb_paid_escaped = mysqli_real_escape_string($this->con, $ctb_paid);
        $total_amount_escaped = mysqli_real_escape_string($this->con, $total_amount);
        $remaining_balance_escaped = mysqli_real_escape_string($this->con, $remaining_balance);
        
        // Prepare notes for payment record
        $payment_notes = '';
        if (!empty($notes)) {
            $payment_notes = mysqli_real_escape_string($this->con, $notes);
        }
        $notes_sql = !empty($payment_notes) ? ", notes = '$payment_notes'" : "";
        
        // Update payment record with calculated values and notes
        $update_sql = "UPDATE season_payments SET
                season_rate = $season_rate_escaped,
                total_amount = $total_amount_escaped,
                student_paid = $student_paid_escaped,
                slgti_paid = $slgti_paid_escaped,
                ctb_paid = $ctb_paid_escaped,
                remaining_balance = $remaining_balance_escaped,
                status = 'Completed'
                $notes_sql
                WHERE request_id = " . intval($request_id);
        
        if (!mysqli_query($this->con, $update_sql)) {
            return ['success' => false, 'error' => 'Error updating payment: ' . mysqli_error($this->con)];
        }
        
        // Add issuance note to request
        $note_text = "Season issued on $issue_date by $issued_by";
        if (!empty($notes)) {
            $note_text .= "\n" . $notes;
        }
        
        $note_text_escaped = mysqli_real_escape_string($this->con, $note_text);
        $sql = "UPDATE season_requests SET 
                notes = CONCAT(COALESCE(notes, ''), '\n', '$note_text_escaped')
                WHERE id = " . intval($request_id);
        
        if (!mysqli_query($this->con, $sql)) {
            return ['success' => false, 'error' => 'Error updating request: ' . mysqli_error($this->con)];
        }
        
        return [
            'success' => true, 
            'message' => 'Season issued successfully with calculated percentages!',
            'data' => [
                'season_rate' => $season_rate,
                'student_paid' => $student_paid,
                'slgti_paid' => $slgti_paid,
                'ctb_paid' => $ctb_paid,
                'total_amount' => $total_amount,
                'remaining_balance' => $remaining_balance
            ]
        ];
    }
    
    /**
     * Collect payment for a season request
     * 
     * @param int $request_id The season request ID
     * @param string $student_id The student ID
     * @param float $season_rate The season rate
     * @param float $paid_amount The amount paid
     * @param string $payment_method Payment method (Cash/Bank Transfer)
     * @param string $payment_date Payment date (YYYY-MM-DD)
     * @param string $collected_by User ID who collected the payment
     * @param string $paid_month The month for which payment is made (stored in payment_reference)
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public function collectPayment($request_id, $student_id, $season_rate, $paid_amount, $payment_method, $payment_date, $collected_by, $paid_month = '') {
        // Validate inputs
        if ($request_id <= 0) {
            return ['success' => false, 'error' => 'Invalid request ID'];
        }
        
        if (empty($student_id)) {
            return ['success' => false, 'error' => 'Student ID is required'];
        }
        
        if ($season_rate < 0) {
            return ['success' => false, 'error' => 'Season rate cannot be negative'];
        }
        
        if ($paid_amount <= 0) {
            return ['success' => false, 'error' => 'Payment amount must be greater than zero'];
        }
        
        if ($season_rate > 0 && $paid_amount > $season_rate) {
            return ['success' => false, 'error' => 'Payment amount cannot exceed season rate'];
        }
        
        // Check if payment already exists
        $check_sql = "SELECT id FROM season_payments WHERE request_id = " . intval($request_id);
        $check_result = mysqli_query($this->con, $check_sql);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            return ['success' => false, 'error' => 'Payment already exists for this request. Cannot insert duplicate payment.'];
        }
        
        // Calculate values
        $total_amount = $season_rate; // total_amount equals season_rate
        // If season_rate is 0, remaining_balance is 0 (fully paid), otherwise calculate difference
        $remaining_balance = ($season_rate > 0) ? max(0, $season_rate - $paid_amount) : 0;
        
        // Prepare payment_reference (store paid_month)
        $payment_reference = '';
        if (!empty($paid_month)) {
            $payment_reference = mysqli_real_escape_string($this->con, $paid_month);
        }
        
        // Escape values for SQL
        $student_id_escaped = mysqli_real_escape_string($this->con, $student_id);
        $season_rate_escaped = mysqli_real_escape_string($this->con, $season_rate);
        $paid_amount_escaped = mysqli_real_escape_string($this->con, $paid_amount);
        $total_amount_escaped = mysqli_real_escape_string($this->con, $total_amount);
        $remaining_balance_escaped = mysqli_real_escape_string($this->con, $remaining_balance);
        $payment_method_escaped = mysqli_real_escape_string($this->con, $payment_method);
        $payment_date_escaped = mysqli_real_escape_string($this->con, $payment_date);
        $collected_by_escaped = mysqli_real_escape_string($this->con, $collected_by);
        
        // Insert new payment
        $sql = "INSERT INTO season_payments 
                (request_id, student_id, paid_amount, season_rate, total_amount, 
                 student_paid, slgti_paid, ctb_paid, remaining_balance, status,
                 payment_date, payment_method, payment_reference, collected_by)
                VALUES 
                (" . intval($request_id) . ", '$student_id_escaped', $paid_amount_escaped, $season_rate_escaped, $total_amount_escaped,
                 0.00, 0.00, 0.00, $remaining_balance_escaped, 'Paid',
                 '$payment_date_escaped', '$payment_method_escaped', " . (!empty($payment_reference) ? "'$payment_reference'" : "NULL") . ", '$collected_by_escaped')";
        
        if (mysqli_query($this->con, $sql)) {
            return [
                'success' => true,
                'message' => 'Payment collected successfully!'
            ];
        } else {
            return ['success' => false, 'error' => 'Error inserting payment: ' . mysqli_error($this->con)];
        }
    }
    
    /**
     * Get payment data for a request
     * 
     * @param int $request_id The season request ID
     * @return array|null Payment data or null if not found
     */
    public function getPaymentData($request_id) {
        $sql = "SELECT * FROM season_payments WHERE request_id = " . intval($request_id);
        $result = mysqli_query($this->con, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Get request data with student information
     * 
     * @param int $request_id The season request ID
     * @param string $dept_code Optional department code for HOD filtering
     * @return array|null Request data or null if not found
     */
    public function getRequestData($request_id, $dept_code = '') {
        $where_dept_check = '';
        if (!empty($dept_code)) {
            $dept_code_escaped = mysqli_real_escape_string($this->con, $dept_code);
            $where_dept_check = "AND d.department_id = '$dept_code_escaped'";
        }
        
        // First try with approved status
        $sql = "SELECT sr.*, s.student_fullname, sr.depot_name 
                FROM season_requests sr
                LEFT JOIN student s ON sr.student_id = s.student_id
                LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
                LEFT JOIN course c ON c.course_id = se.course_id
                LEFT JOIN department d ON d.department_id = c.department_id
                WHERE sr.id = " . intval($request_id) . " AND sr.status = 'approved' $where_dept_check";
        
        $result = mysqli_query($this->con, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        // If not found with approved status, try without status check (for debugging)
        $sql2 = "SELECT sr.*, s.student_fullname 
                 FROM season_requests sr
                 LEFT JOIN student s ON sr.student_id = s.student_id
                 WHERE sr.id = " . intval($request_id);
        
        $result2 = mysqli_query($this->con, $sql2);
        
        if ($result2 && mysqli_num_rows($result2) > 0) {
            return mysqli_fetch_assoc($result2);
        }
        
        return null;
    }
    
    /**
     * Get all issueable requests (approved requests with payments)
     * 
     * @param string $dept_code Optional department code for HOD filtering
     * @param string $status_filter Filter by payment status ('Paid', 'Completed', or '' for all when date filters applied)
     * @param string $date_from Start date for filtering (YYYY-MM-DD)
     * @param string $date_to End date for filtering (YYYY-MM-DD)
     * @param string $month Month filter (YYYY-MM)
     * @param string $student_id Optional student ID to filter by specific student
     * @return array Array of issueable requests
     */
    public function getIssueableRequests($dept_code = '', $status_filter = 'Paid', $date_from = '', $date_to = '', $month = '', $student_id = '') {
        $where_dept = '';
        if (!empty($dept_code)) {
            $dept_code_escaped = mysqli_real_escape_string($this->con, $dept_code);
            $where_dept = "AND d.department_id = '$dept_code_escaped'";
        }
        
        // Build status filter
        $where_status = '';
        $has_date_filter = !empty($date_from) || !empty($date_to) || !empty($month);
        
        if ($has_date_filter) {
            // When date filters are applied, show both Paid and Completed
            $where_status = "AND (sp.status = 'Paid' OR sp.status = 'Completed')";
        } else {
            // By default, show only Paid status
            if ($status_filter === 'Paid') {
                $where_status = "AND sp.status = 'Paid'";
            } elseif ($status_filter === 'Completed') {
                $where_status = "AND sp.status = 'Completed'";
            } elseif ($status_filter === '' || $status_filter === 'All') {
                // Show both when All is selected or empty
                $where_status = "AND (sp.status = 'Paid' OR sp.status = 'Completed')";
            }
        }
        
        // Build date filters
        $where_date = '';
        if (!empty($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            // Month filter: filter by payment_date for Paid, updated_at for Completed
            $first_day = $month . '-01';
            $last_day = date('Y-m-t', strtotime($first_day));
            $where_date = "AND (
                (sp.status = 'Paid' AND sp.payment_date BETWEEN '$first_day' AND '$last_day')
                OR (sp.status = 'Completed' AND DATE(sp.updated_at) BETWEEN '$first_day' AND '$last_day')
            )";
        } elseif (!empty($date_from) && !empty($date_to)) {
            // Date range filter
            $date_from_escaped = mysqli_real_escape_string($this->con, $date_from);
            $date_to_escaped = mysqli_real_escape_string($this->con, $date_to);
            $where_date = "AND (
                (sp.status = 'Paid' AND sp.payment_date BETWEEN '$date_from_escaped' AND '$date_to_escaped')
                OR (sp.status = 'Completed' AND DATE(sp.updated_at) BETWEEN '$date_from_escaped' AND '$date_to_escaped')
            )";
        } elseif (!empty($date_from)) {
            // Only start date
            $date_from_escaped = mysqli_real_escape_string($this->con, $date_from);
            $where_date = "AND (
                (sp.status = 'Paid' AND sp.payment_date >= '$date_from_escaped')
                OR (sp.status = 'Completed' AND DATE(sp.updated_at) >= '$date_from_escaped')
            )";
        } elseif (!empty($date_to)) {
            // Only end date
            $date_to_escaped = mysqli_real_escape_string($this->con, $date_to);
            $where_date = "AND (
                (sp.status = 'Paid' AND sp.payment_date <= '$date_to_escaped')
                OR (sp.status = 'Completed' AND DATE(sp.updated_at) <= '$date_to_escaped')
            )";
        }
        
        // Add student_id filter if provided
        $where_student = '';
        if (!empty($student_id)) {
            $student_id_escaped = mysqli_real_escape_string($this->con, $student_id);
            $where_student = "AND sr.student_id = '$student_id_escaped'";
        }
        
        // Determine order by clause
        $order_by = "ORDER BY sr.id DESC";
        if (!empty($student_id)) {
            // If filtering by student, order by payment_date DESC (NULL values last)
            $order_by = "ORDER BY ISNULL(sp.payment_date), sp.payment_date DESC, sr.id DESC";
        }
        
        $sql = "SELECT sr.*, s.student_fullname, sr.depot_name, sp.id as payment_id, sp.paid_amount, sp.season_rate, 
                       sp.remaining_balance, sp.status as payment_status, sp.payment_date, sp.updated_at
                FROM season_requests sr
                LEFT JOIN student s ON sr.student_id = s.student_id
                LEFT JOIN season_payments sp ON sr.id = sp.request_id
                LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
                LEFT JOIN course c ON c.course_id = se.course_id
                LEFT JOIN department d ON d.department_id = c.department_id
                WHERE sr.status = 'approved' 
                AND sp.id IS NOT NULL
                $where_status
                $where_date
                $where_dept
                $where_student
                $order_by";
        
        $result = mysqli_query($this->con, $sql);
        $requests = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $requests[] = $row;
            }
        }
        
        return $requests;
    }
}

