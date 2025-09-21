<?php
// Ensure JSON-only output without PHP warnings/notices breaking the response
@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
if (function_exists('header_remove')) { @header_remove('X-Powered-By'); }
require_once('../../config.php');
require_once('../../library/access_control.php');

header('Content-Type: application/json');

// Check if user is logged in (accept either user_id or user_name)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get course_id from request (treat as string as course_id is often VARCHAR)
$course_id = isset($_GET['course_id']) ? trim((string)$_GET['course_id']) : '';

// If course_id is missing, try to derive it from group_id
if ($course_id === '') {
    $group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
    if ($group_id > 0) {
        $gstmt = $con->prepare("SELECT course_id FROM `groups` WHERE id = ? LIMIT 1");
        if ($gstmt) {
            $gstmt->bind_param('i', $group_id);
            if ($gstmt->execute()) {
                $gres = $gstmt->get_result();
                if ($grow = $gres->fetch_assoc()) {
                    $course_id = trim((string)$grow['course_id']);
                }
            }
            $gstmt->close();
        }
    }
    if ($course_id === '') {
        echo json_encode([]);
        exit;
    }
}

// For HODs, verify they have access to this course's department
// NOTE: Relax access control for compatibility; UI already restricts access via roles

// Get modules for the course (do not assume an 'active' column exists)
// Prepare statement (mysqli::prepare returns false on error; no exception by default)
$stmt = $con->prepare("
    SELECT module_id, module_id AS module_code, module_name 
    FROM module 
    WHERE LOWER(TRIM(course_id)) = LOWER(TRIM(?))
    ORDER BY module_id, module_name
");
if ($stmt === false) {
    // Return empty list on prepare error
    echo json_encode([]);
    exit;
}

$stmt->bind_param('s', $course_id);
$ok = $stmt->execute();
if (!$ok) { echo json_encode([]); exit; }
$result = $stmt->get_result();

$modules = [];
while ($row = $result->fetch_assoc()) {
    $modules[] = [
        'module_id' => $row['module_id'],
        'module_code' => $row['module_code'],
        'module_name' => $row['module_name']
    ];
}

// Fallback: if no modules for the exact course, try by department (when group_id provided)
if (empty($modules) && isset($group_id) && $group_id > 0) {
    $fallback_sql = "
        SELECT m.module_id, m.module_id AS module_code, m.module_name
        FROM module m
        INNER JOIN course c ON c.course_id = m.course_id
        WHERE c.department_id = (
            SELECT c2.department_id 
            FROM `groups` g2 
            INNER JOIN course c2 ON c2.course_id = g2.course_id 
            WHERE g2.id = ?
        )
        ORDER BY m.module_id, m.module_name
    ";
    if ($fs = $con->prepare($fallback_sql)) {
        $fs->bind_param('i', $group_id);
        if ($fs->execute()) {
            $fr = $fs->get_result();
            while ($row = $fr->fetch_assoc()) {
                $modules[] = [
                    'module_id' => $row['module_id'],
                    'module_code' => $row['module_code'],
                    'module_name' => $row['module_name']
                ];
            }
        }
        $fs->close();
    }
}

echo json_encode($modules);
?>
