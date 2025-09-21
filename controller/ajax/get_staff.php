<?php
require_once('../../config.php');
require_once('../../library/access_control.php');

header('Content-Type: application/json');

// Check if user is logged in (accept either user_id or user_name)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// For HOD/IN roles, prefer filtering by their department if available
$department_condition = '';
$params = [];
$types = '';

if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['HOD','IN1','IN2','IN3','ADM','ADMIN'], true)) {
    $dept = $_SESSION['department_id'] ?? $_SESSION['department_code'] ?? '';
    if ($dept !== '') {
        $department_condition = " AND s.department_id = ?";
        $params[] = $dept;
        $types .= 's';
    }
}

// Get staff members (avoid assuming an 'active' boolean column)
$sql = "
    SELECT s.staff_id, s.staff_name, s.staff_email AS email, d.department_name
    FROM staff s
    LEFT JOIN department d ON s.department_id = d.department_id
    WHERE (COALESCE(TRIM(LOWER(s.staff_status)),'active') NOT LIKE 'inactive%') {$department_condition}
    ORDER BY s.staff_name
";

try {
    $stmt = $con->prepare($sql);
} catch (Throwable $e) {
    echo json_encode([]);
    exit;
}

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
