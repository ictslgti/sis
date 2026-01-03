<?php
// Controller for Season Request AJAX operations
include_once("../config.php");
require_once("../auth.php");

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_request_details':
        // Get request details by ID
        $request_id = intval($_GET['request_id'] ?? 0);
        if ($request_id > 0) {
            $sql = "SELECT sr.*, s.student_fullname, sp.id as payment_id, sp.paid_amount, 
                           sp.season_rate, sp.remaining_balance, sp.status as payment_status
                    FROM season_requests sr
                    LEFT JOIN student s ON sr.student_id = s.student_id
                    LEFT JOIN season_payments sp ON sr.id = sp.request_id
                    WHERE sr.id = $request_id";
            $result = mysqli_query($con, $sql);
            if ($result && mysqli_num_rows($result) > 0) {
                $data = mysqli_fetch_assoc($result);
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Request not found']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
        }
        break;
        
    case 'get_payment_summary':
        // Get payment summary for a request
        $request_id = intval($_GET['request_id'] ?? 0);
        if ($request_id > 0) {
            $sql = "SELECT * FROM season_payments WHERE request_id = $request_id";
            $result = mysqli_query($con, $sql);
            if ($result && mysqli_num_rows($result) > 0) {
                $data = mysqli_fetch_assoc($result);
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Payment not found']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
        }
        break;
        
    case 'get_student_requests':
        // Get all requests for a student
        $student_id = mysqli_real_escape_string($con, $_GET['student_id'] ?? '');
        if ($student_id) {
            $sql = "SELECT sr.*, sp.paid_amount, sp.remaining_balance, sp.status as payment_status
                    FROM season_requests sr
                    LEFT JOIN season_payments sp ON sr.id = sp.request_id
                    WHERE sr.student_id = '$student_id'
                    ORDER BY sr.created_at DESC";
            $result = mysqli_query($con, $sql);
            $requests = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $requests[] = $row;
                }
            }
            echo json_encode(['success' => true, 'data' => $requests]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Student ID required']);
        }
        break;
        
    case 'get_statistics':
        // Get statistics for dashboard
        $stats = [];
        
        // Total requests
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM season_requests");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['total_requests'] = $row['total'];
        }
        
        // Pending requests
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM season_requests WHERE status = 'pending'");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['pending_requests'] = $row['total'];
        }
        
        // Approved requests
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM season_requests WHERE status = 'approved'");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['approved_requests'] = $row['total'];
        }
        
        // Total outstanding balance
        $result = mysqli_query($con, "SELECT SUM(remaining_balance) as total FROM season_payments WHERE remaining_balance > 0");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $stats['outstanding_balance'] = $row['total'] ?? 0;
        }
        
        echo json_encode(['success' => true, 'data' => $stats]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

