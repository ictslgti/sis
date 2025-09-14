<?php
require_once('../../config.php');
require_once('../../library/access_control.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// For HODs, only show staff from their department
$department_condition = '';
$params = [];
$types = '';

if ($_SESSION['user_type'] === 'HOD' && !empty($_SESSION['department_id'])) {
    $department_condition = " AND s.department_id = ?";
    $params[] = $_SESSION['department_id'];
    $types .= 'i';
}

// Get active staff members
$sql = "
    SELECT s.staff_id, s.staff_name, s.email, d.department_name
    FROM staff s
    LEFT JOIN department d ON s.department_id = d.department_id
    WHERE s.active = 1 {$department_condition}
    ORDER BY s.staff_name
";

$stmt = $con->prepare($sql) or die($con->error);

// Bind parameters if needed
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$staff = [];
while ($row = $result->fetch_assoc()) {
    $staff[] = [
        'staff_id' => $row['staff_id'],
        'staff_name' => $row['staff_name'],
        'email' => $row['email'],
        'department' => $row['department_name']
    ];
}

echo json_encode($staff);
?>
