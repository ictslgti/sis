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

// Get course_id from request
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid course ID']);
    exit;
}

// For HODs, verify they have access to this course's department
if ($_SESSION['user_type'] === 'HOD') {
    $stmt = $con->prepare("
        SELECT 1 FROM course c 
        WHERE c.course_id = ? AND c.department_id = ?
    ") or die($con->error);
    $stmt->bind_param('ii', $course_id, $_SESSION['department_id']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
}

// Get modules for the course
$stmt = $con->prepare("
    SELECT module_id, module_code, module_name 
    FROM module 
    WHERE course_id = ? AND active = 1
    ORDER BY module_code, module_name
") or die($con->error);

$stmt->bind_param('i', $course_id);
$stmt->execute();
$result = $stmt->get_result();

$modules = [];
while ($row = $result->fetch_assoc()) {
    $modules[] = [
        'module_id' => $row['module_id'],
        'module_code' => $row['module_code'],
        'module_name' => $row['module_name']
    ];
}

echo json_encode($modules);
?>
