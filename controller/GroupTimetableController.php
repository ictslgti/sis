<?php
require_once('../config.php');
require_once('../library/access_control.php');

class GroupTimetableController {
    private $con;
    private $user_id;
    private $user_role;
    private $department_id;

    public function __construct($con, $user_id, $user_role, $department_id) {
        $this->con = $con;
        $this->user_id = $user_id;
        $this->user_role = $user_role;
        $this->department_id = $department_id;
    }

    public function handleRequest() {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
        
        // Verify user has permission
        if (!$this->hasPermission()) {
            header('HTTP/1.0 403 Forbidden');
            echo 'Access Denied: Insufficient permissions';
            exit;
        }

        switch ($action) {
            case 'save':
                $this->saveTimetable();
                break;
            case 'delete':
                $this->deleteTimetable();
                break;
            case 'get':
                $this->getTimetable();
                break;
            case 'list':
            default:
                $this->listTimetable();
                break;
        }
    }

    private function hasPermission() {
        // Allow admin (ADM/ADMIN) or HOD of the department
        return in_array($this->user_role, ['ADMIN', 'ADM', 'HOD'], true);
    }

    private function validateGroupAccess($group_id) {
        if ($this->user_role === 'ADMIN' || $this->user_role === 'ADM') return true;
        
        // For HOD, verify the group's course belongs to their department (note: table is `groups`, PK `id`)
        $stmt = $this->con->prepare("
            SELECT 1 FROM `groups` g 
            JOIN course c ON g.course_id = c.course_id 
            WHERE g.id = ? AND c.department_id = ?
        ");
        $stmt->bind_param('ii', $group_id, $this->department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    private function saveTimetable() {
        $group_id = intval($_POST['group_id'] ?? 0);
        $module_id = trim($_POST['module_id'] ?? '');
        $staff_id = trim($_POST['staff_id'] ?? '');
        $weekday = intval($_POST['weekday'] ?? 0);
        $period = trim($_POST['period'] ?? '');
        $classroom = trim($_POST['classroom'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $timetable_id = intval($_POST['timetable_id'] ?? 0);

        // Validate inputs
        if (!$this->validateGroupAccess($group_id) || 
            $module_id === '' || $staff_id === '' || 
            $weekday < 1 || $weekday > 7 || 
            !in_array($period, ['P1', 'P2', 'P3', 'P4']) || 
            empty($classroom) || empty($start_date) || empty($end_date)) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            return;
        }

        // Check if module belongs to group's course
        $stmt = $this->con->prepare("
            SELECT 1 FROM `groups` g 
            JOIN module m ON g.course_id = m.course_id 
            WHERE g.id = ? AND m.module_id = ?
        ");
        $stmt->bind_param('is', $group_id, $module_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid module for group']);
            return;
        }

        // Check for conflicts
        $conflict = $this->checkConflict($group_id, $weekday, $period, $start_date, $end_date, $timetable_id);
        if ($conflict) {
            echo json_encode(['success' => false, 'message' => 'Schedule conflict detected']);
            return;
        }

        // Save to database
        if ($timetable_id > 0) {
            // Update existing
            $stmt = $this->con->prepare("
                UPDATE timetable SET 
                    module_id = ?, staff_id = ?, weekday = ?, 
                    period = ?, classroom = ?, 
                    start_date = ?, end_date = ?,
                    updated_at = NOW()
                WHERE timetable_id = ? AND group_id = ?
            ");
            $stmt->bind_param('ssissssii', 
                $module_id, $staff_id, $weekday, 
                $period, $classroom, 
                $start_date, $end_date,
                $timetable_id, $group_id
            );
        } else {
            // Insert new
            $stmt = $this->con->prepare("
                INSERT INTO timetable 
                (group_id, module_id, staff_id, weekday, period, classroom, start_date, end_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param('ississss', 
                $group_id, $module_id, $staff_id, 
                $weekday, $period, $classroom, 
                $start_date, $end_date
            );
        }

        if ($stmt->execute()) {
            $timetable_id = $timetable_id > 0 ? $timetable_id : $stmt->insert_id;
            echo json_encode([
                'success' => true, 
                'timetable_id' => $timetable_id,
                'message' => 'Timetable saved successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    }

    private function checkConflict($group_id, $weekday, $period, $start_date, $end_date, $exclude_id = 0) {
        $sql = "
            SELECT 1 FROM timetable 
            WHERE group_id = ? 
            AND weekday = ? 
            AND period = ? 
            AND (
                (start_date BETWEEN ? AND ?) 
                OR (end_date BETWEEN ? AND ?)
                OR (? <= start_date AND ? >= end_date)
            )
            AND active = 1
        ";
        
        $params = [
            $group_id, $weekday, $period,
            $start_date, $end_date,
            $start_date, $end_date,
            $start_date, $end_date
        ];
        
        if ($exclude_id > 0) {
            $sql .= " AND timetable_id != ?";
            $params[] = $exclude_id;
        }
        
        $types = 'iissssss';
        if ($exclude_id > 0) { $types .= 'i'; }
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function deleteTimetable() {
        $timetable_id = intval($_POST['timetable_id'] ?? 0);
        $hard_delete = isset($_POST['hard_delete']) && $_POST['hard_delete'] === 'true';
        
        if ($timetable_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            return;
        }
        
        // Get group_id for permission check
        $group_id = $this->getGroupIdForTimetable($timetable_id);
        if (!$group_id || !$this->validateGroupAccess($group_id)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        if ($hard_delete) {
            $stmt = $this->con->prepare("DELETE FROM timetable WHERE timetable_id = ?");
        } else {
            $stmt = $this->con->prepare("UPDATE timetable SET active = 0, updated_at = NOW() WHERE timetable_id = ?");
        }
        
        $stmt->bind_param('i', $timetable_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
        }
    }
    
    private function getGroupIdForTimetable($timetable_id) {
        $stmt = $this->con->prepare("SELECT group_id FROM timetable WHERE timetable_id = ?");
        $stmt->bind_param('i', $timetable_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['group_id'];
        }
        return null;
    }

    private function getTimetable() {
        $timetable_id = intval($_GET['timetable_id'] ?? 0);
        if ($timetable_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            return;
        }
        
        $stmt = $this->con->prepare("
            SELECT t.*, m.module_name, s.staff_name 
            FROM timetable t
            LEFT JOIN module m ON t.module_id = m.module_id
            LEFT JOIN staff s ON t.staff_id = s.staff_id
            WHERE t.timetable_id = ? AND t.active = 1
        ");
        $stmt->bind_param('i', $timetable_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Check permission
            if (!$this->validateGroupAccess($row['group_id'])) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Not found']);
        }
    }

    private function listTimetable() {
        $group_id = intval($_GET['group_id'] ?? 0);
        $academic_year = trim($_GET['academic_year'] ?? '');
        
        if ($group_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Group ID required']);
            return;
        }
        
        if (!$this->validateGroupAccess($group_id)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        $sql = "
            SELECT t.*, m.module_name, m.module_code, s.staff_name,
                   CONCAT(m.module_code, ' - ', m.module_name) as module_full_name
            FROM timetable t
            LEFT JOIN module m ON t.module_id = m.module_id
            LEFT JOIN staff s ON t.staff_id = s.staff_id
            WHERE t.group_id = ? AND t.active = 1
        ";
        
        $params = [$group_id];
        $types = 'i';
        
        if (!empty($academic_year)) {
            $sql .= " AND ? BETWEEN t.start_date AND t.end_date";
            $params[] = $academic_year . '-01-01'; // Assuming YYYY format
            $types .= 's';
        }
        
        $sql .= " ORDER BY t.weekday, t.period, t.start_date";
        
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $timetable = [];
        while ($row = $result->fetch_assoc()) {
            $timetable[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $timetable]);
    }
}

// Initialize and run controller
$uid = $_SESSION['user_id'] ?? ($_SESSION['user_name'] ?? null);
if ($uid !== null && isset($_SESSION['user_type'])) {
    $controller = new GroupTimetableController(
        $con, 
        $uid,
        $_SESSION['user_type'] ?? '',
        $_SESSION['department_id'] ?? 0
    );
    $controller->handleRequest();
} else {
    header('HTTP/1.0 401 Unauthorized');
    echo 'Unauthorized';
}
