<?php
require_once('../config.php');
require_once('../library/access_control.php');
// Force JSON output for all responses from this controller
if (!headers_sent()) {
    header('Content-Type: application/json');
}

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
        // Ensure the target storage table exists for group-based timetable
        $this->ensureGroupTimetableTable();
    }

    public function handleRequest() {
        $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
        
        // For listing timetable, allow any authenticated user (including students)
        if ($action !== 'list' && !$this->hasPermission()) {
            header('HTTP/1.0 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'Access Denied: Insufficient permissions']);
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
        return in_array($this->user_role, ['ADMIN', 'ADM', 'HOD', 'IN3'], true);
    }

    private function ensureGroupTimetableTable() {
        // Create table if it does not exist (idempotent)
        $sql = "
            CREATE TABLE IF NOT EXISTS `group_timetable` (
              `timetable_id` INT NOT NULL AUTO_INCREMENT,
              `group_id` INT NOT NULL,
              `module_id` VARCHAR(64) NOT NULL,
              `staff_id` VARCHAR(64) NOT NULL,
              `weekday` TINYINT(1) NOT NULL COMMENT '1=Mon ... 7=Sun',
              `period` ENUM('P1','P2','P3','P4') NOT NULL,
              `classroom` VARCHAR(64) DEFAULT NULL,
              `start_date` DATE NOT NULL,
              `end_date` DATE NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`timetable_id`),
              KEY `idx_group_day_period_active` (`group_id`,`weekday`,`period`,`active`),
              KEY `idx_dates` (`start_date`,`end_date`),
              KEY `idx_staff_period` (`staff_id`,`weekday`,`period`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        // Suppress errors but attempt creation
        @mysqli_query($this->con, $sql);
    }

    private function validateGroupAccess($group_id) {
        // Admin/ADM unrestricted
        if ($this->user_role === 'ADMIN' || $this->user_role === 'ADM') return true;
        // IN3 allowed for this module per requirements
        if ($this->user_role === 'IN3') return true;

        // For HOD, verify the group's course belongs to their department (note: table is `groups`, PK `id`)
        $dept = (string)$this->department_id; // may be varchar in schema
        if ($dept === '' || $dept === '0') {
            // If we cannot determine department from session, allow access to avoid false 403 in mixed deployments
            return true;
        }
        $stmt = $this->con->prepare("
            SELECT 1 FROM `groups` g 
            JOIN course c ON g.course_id = c.course_id 
            WHERE g.id = ? AND c.department_id = ?
        ");
        $stmt->bind_param('is', $group_id, $dept);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result && $result->num_rows > 0;
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
            echo json_encode(['success' => false, 'message' => 'Selected module does not belong to this group\'s course.']);
            return;
        }

        // Check for conflicts (same group/day/period/date overlap)
        if ($this->checkConflict($group_id, $weekday, $period, $start_date, $end_date, $timetable_id)) {
            echo json_encode(['success' => false, 'message' => 'This group already has a session scheduled for the selected day and period within the date range.']);
            return;
        }

        // Staff clash across groups
        if ($this->checkStaffClash($staff_id, $weekday, $period, $start_date, $end_date, $timetable_id)) {
            echo json_encode(['success' => false, 'message' => 'Staff is already assigned to another group for the selected day and period within the date range.']);
            return;
        }

        // Classroom clash across groups
        if ($this->checkClassroomClash($classroom, $weekday, $period, $start_date, $end_date, $timetable_id)) {
            echo json_encode(['success' => false, 'message' => 'Classroom is already booked for the selected day and period within the date range.']);
            return;
        }

        // Save to database
        if ($timetable_id > 0) {
            // Update existing
            $stmt = $this->con->prepare("
                UPDATE group_timetable SET 
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
                INSERT INTO group_timetable 
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
            $err = mysqli_error($this->con);
            error_log('GroupTimetableController saveTimetable execute error: ' . $err);
            echo json_encode(['success' => false, 'message' => 'Database error', 'error_detail' => $err]);
        }
    }

    private function checkConflict($group_id, $weekday, $period, $start_date, $end_date, $exclude_id = 0) {
        $sql = "
            SELECT 1 FROM group_timetable 
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
        
        // 9 params before optional exclude: ii + 7 strings
        $types = 'iisssssss';
        if ($exclude_id > 0) { $types .= 'i'; }
        $stmt = $this->con->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            return;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function checkStaffClash($staff_id, $weekday, $period, $start_date, $end_date, $exclude_id = 0) {
        if ($staff_id === '') return false;
        $sql = "
            SELECT 1 FROM group_timetable
            WHERE staff_id = ?
              AND weekday = ?
              AND period = ?
              AND (
                    (start_date BETWEEN ? AND ?)
                 OR (end_date BETWEEN ? AND ?)
                 OR (? <= start_date AND ? >= end_date)
              )
              AND active = 1
        ";
        $params = [$staff_id, $weekday, $period, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date];
        $types = 'sisssssss';
        if ($exclude_id > 0) { $sql .= ' AND timetable_id != ?'; $params[] = $exclude_id; $types .= 'i'; }
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    private function checkClassroomClash($classroom, $weekday, $period, $start_date, $end_date, $exclude_id = 0) {
        if ($classroom === '') return false;
        $sql = "
            SELECT 1 FROM group_timetable
            WHERE classroom = ?
              AND weekday = ?
              AND period = ?
              AND (
                    (start_date BETWEEN ? AND ?)
                 OR (end_date BETWEEN ? AND ?)
                 OR (? <= start_date AND ? >= end_date)
              )
              AND active = 1
        ";
        $params = [$classroom, $weekday, $period, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date];
        $types = 'sisssssss';
        if ($exclude_id > 0) { $sql .= ' AND timetable_id != ?'; $params[] = $exclude_id; $types .= 'i'; }
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
            $stmt = $this->con->prepare("DELETE FROM group_timetable WHERE timetable_id = ?");
        } else {
            $stmt = $this->con->prepare("UPDATE group_timetable SET active = 0, updated_at = NOW() WHERE timetable_id = ?");
        }
        
        $stmt->bind_param('i', $timetable_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
        }
    }
    
    private function getGroupIdForTimetable($timetable_id) {
        $stmt = $this->con->prepare("SELECT group_id FROM group_timetable WHERE timetable_id = ?");
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
            FROM group_timetable t
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
        // Accept group_id from query string; if missing, fall back to the last group visited (stored by the page)
        $group_id = intval($_GET['group_id'] ?? ($_SESSION['current_group_id'] ?? 0));
        $academic_year = trim($_GET['academic_year'] ?? '');
        
        if ($group_id <= 0) {
            // When no group is given, return an empty timetable instead of erroring out
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        if (!$this->validateGroupAccess($group_id)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        $sql = "
            SELECT 
                MIN(t.timetable_id) AS timetable_id,
                t.group_id,
                t.weekday,
                t.period,
                t.classroom,
                t.start_date,
                t.end_date,
                t.module_id,
                t.staff_id,
                m.module_name,
                m.module_id AS module_code,
                s.staff_name,
                CONCAT(m.module_id, ' - ', m.module_name) AS module_full_name
            FROM group_timetable t
            LEFT JOIN module m ON t.module_id = m.module_id
            LEFT JOIN staff s ON t.staff_id = s.staff_id
            WHERE t.group_id = ? AND t.active = 1
        ";
        
        $params = [$group_id];
        $types = 'i';
        
        if (!empty($academic_year)) {
            // Expect formats like '2025-2026' or '2025-26'; default to Aug 1 to May 31 window
            $start_year = null; $end_year = null;
            if (preg_match('/^(\d{4})\s*-\s*(\d{2}|\d{4})$/', $academic_year, $m)) {
                $start_year = (int)$m[1];
                $end_year = (int)($m[2] < 100 ? ($start_year - ($start_year % 100) * 0 + (int)$m[2]) : $m[2]);
                // Normalize end year when short form like 25/26 comes in and rolls over century boundaries
                if ($end_year < $start_year) { $end_year = $start_year + 1; }
            }
            if ($start_year === null) {
                // Fallback: try first 4 digits
                $start_year = (int)substr($academic_year, 0, 4);
                $end_year = $start_year + 1;
            }
            $ay_start = sprintf('%04d-08-01', $start_year);
            $ay_end   = sprintf('%04d-05-31', $end_year);
            // Overlap condition: (t.start_date <= ay_end) AND (t.end_date >= ay_start)
            $sql .= " AND t.start_date <= ? AND t.end_date >= ?";
            $params[] = $ay_end;
            $params[] = $ay_start;
            $types .= 'ss';
        }
        
        $sql .= " 
            GROUP BY 
                t.group_id, t.weekday, t.period, t.classroom, 
                t.start_date, t.end_date, t.module_id, t.staff_id, 
                m.module_name, m.module_id, s.staff_name
            ORDER BY t.weekday, t.period, t.start_date
        ";
        
        $stmt = $this->con->prepare($sql);
        if ($stmt === false) {
            // Attempt to auto-create the table then retry once
            $this->ensureGroupTimetableTable();
            $stmt = $this->con->prepare($sql);
            if ($stmt === false) {
                $err = mysqli_error($this->con);
                error_log('GroupTimetableController listTimetable prepare error: ' . $err . ' | SQL: ' . $sql);
                echo json_encode(['success' => false, 'message' => 'Database error', 'error_detail' => $err, 'sql' => $sql]);
                return;
            }
        }
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
}
