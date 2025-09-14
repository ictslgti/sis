<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only HODs can access
require_login();
require_roles(['HOD', 'ADM']); // allow ADM too for testing

$base = defined('APP_BASE') ? APP_BASE : '';
$hodId = $_SESSION['user_name'] ?? '';
$deptId = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';
$deptName = '';

// Lightweight AJAX: fetch staff details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'staff') {
  header('Content-Type: application/json');
  $sid = isset($_GET['staff_id']) ? trim((string)$_GET['staff_id']) : '';
  if ($sid === '') {
    echo json_encode(['ok' => false, 'error' => 'missing_id']);
    exit;
  }
  $whereDept = ($deptId !== '') ? " AND department_id='" . mysqli_real_escape_string($con, $deptId) . "'" : '';
  $sql = "SELECT staff_id, staff_name, COALESCE(staff_address,'') AS staff_address, COALESCE(staff_dob,'') AS staff_dob, COALESCE(staff_date_of_join,'') AS staff_date_of_join, COALESCE(staff_email,'') AS staff_email, COALESCE(staff_pno,'') AS staff_pno, COALESCE(staff_nic,'') AS staff_nic, COALESCE(staff_gender,'') AS staff_gender, COALESCE(staff_epf,'') AS staff_epf, COALESCE(staff_position,'') AS staff_position, COALESCE(staff_type,'') AS staff_type, COALESCE(staff_status,'') AS staff_status FROM staff WHERE staff_id='" . mysqli_real_escape_string($con, $sid) . "'" . $whereDept . " LIMIT 1";
  $rs = mysqli_query($con, $sql);
  if ($rs && ($row = mysqli_fetch_assoc($rs))) {
    echo json_encode(['ok' => true, 'data' => $row]);
  } else {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
  }
  exit;
}

// Resolve department name
if ($deptId !== '') {
  $q = sprintf("SELECT department_name FROM department WHERE department_id='%s' LIMIT 1", mysqli_real_escape_string($con, $deptId));
  if ($r = mysqli_query($con, $q)) {
    $row = mysqli_fetch_assoc($r);
    $deptName = $row['department_name'] ?? $deptId;
    mysqli_free_result($r);
  }
}

// Metrics for this department
$counts = [
  'students' => 0,
  'staff' => 0,
  'courses' => 0,
  'onpeak_pending' => 0,
  'hostel_pending' => 0,
];

// Handle OnPeak approve/reject actions (HOD)
$_op_msg = '';
$_staff_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['op_action'], $_POST['op_id'])) {
  $action = strtolower(trim((string)$_POST['op_action']));
  $id = (int) $_POST['op_id'];
  $newStatus = null;
  if ($action === 'approve') $newStatus = 'Approved by HOD';
  if ($action === 'reject')  $newStatus = 'Not Approved';
  if ($newStatus && $id > 0) {
    // Determine department for this request if not available in session
    $reqDept = '';
    if ($r0 = mysqli_query($con, 'SELECT department_id, TRIM(LOWER(onpeak_request_status)) st FROM onpeak_request WHERE id=' . (int)$id . ' LIMIT 1')) {
      if ($row0 = mysqli_fetch_assoc($r0)) {
        $reqDept = trim((string)$row0['department_id']);
        $curSt = (string)($row0['st'] ?? '');
      }
      mysqli_free_result($r0);
    }
    $targetDept = ($deptId !== '') ? $deptId : $reqDept;
    if ($targetDept !== '' || $id > 0) { // allow update by id even if department_id is NULL
      // Consider anything that is not already approved/rejected as actionable
      $sql = "UPDATE onpeak_request 
              SET onpeak_request_status='" . mysqli_real_escape_string($con, $newStatus) . "' 
              WHERE id=" . $id . " 
                AND COALESCE(TRIM(LOWER(onpeak_request_status)),'') NOT LIKE 'approv%'
                AND COALESCE(TRIM(LOWER(onpeak_request_status)),'') NOT LIKE 'not%'";
      $ok = mysqli_query($con, $sql);
      if ($ok) {
        if (mysqli_affected_rows($con) > 0) {
          $_op_msg = '<div class="alert alert-success alert-dismissible fade show m-2" role="alert">Action completed.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
        } else {
          // If status already equals desired value, treat as success (idempotent)
          $chk = mysqli_query($con, 'SELECT onpeak_request_status FROM onpeak_request WHERE id=' . (int)$id . ' LIMIT 1');
          $cur = ($chk && ($rr = mysqli_fetch_assoc($chk))) ? trim((string)$rr['onpeak_request_status']) : '';
          if ($chk) mysqli_free_result($chk);
          if (strcasecmp($cur, $newStatus) === 0) {
            $_op_msg = '<div class="alert alert-success alert-dismissible fade show m-2" role="alert">Already ' + htmlspecialchars($newStatus) + '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
          } else {
            $_op_msg = '<div class="alert alert-warning alert-dismissible fade show m-2" role="alert">No change made. Current status: ' . htmlspecialchars($cur ?: 'Unknown') . '.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
          }
        }
      } else {
        $_op_msg = '<div class="alert alert-danger alert-dismissible fade show m-2" role="alert">DB error while updating OnPeak: ' . htmlspecialchars(mysqli_error($con)) . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
      }
    } else {
      $_op_msg = '<div class="alert alert-danger alert-dismissible fade show m-2" role="alert">Cannot determine department for this request.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
    }
  }
}

// Handle Staff add/edit/delete (department-scoped)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['staff_action'])) {
  $act = strtolower(trim((string)$_POST['staff_action']));
  $sid = isset($_POST['staff_id']) ? trim((string)$_POST['staff_id']) : '';
  $name = isset($_POST['staff_name']) ? trim((string)$_POST['staff_name']) : '';
  $address = isset($_POST['staff_address']) ? trim((string)$_POST['staff_address']) : '';
  $dob = isset($_POST['staff_dob']) ? trim((string)$_POST['staff_dob']) : '';
  $doj = isset($_POST['staff_date_of_join']) ? trim((string)$_POST['staff_date_of_join']) : '';
  $nic = isset($_POST['staff_nic']) ? trim((string)$_POST['staff_nic']) : '';
  $email = isset($_POST['staff_email']) ? trim((string)$_POST['staff_email']) : '';
  $phone = isset($_POST['staff_pno']) ? trim((string)$_POST['staff_pno']) : '';
  $gender = isset($_POST['staff_gender']) ? trim((string)$_POST['staff_gender']) : '';
  $epf  = isset($_POST['staff_epf']) ? trim((string)$_POST['staff_epf']) : '';
  $pos  = isset($_POST['staff_position']) ? trim((string)$_POST['staff_position']) : '';
  $stype = isset($_POST['staff_type']) ? trim((string)$_POST['staff_type']) : '';
  $status = isset($_POST['staff_status']) ? trim((string)$_POST['staff_status']) : '';

  if ($deptId === '') {
    $_staff_msg = '<div class="alert alert-danger alert-dismissible fade show m-2">Department not set in session.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
  } else if ($act === 'add') {
    if ($sid === '' || $name === '') {
      $_staff_msg = '<div class="alert alert-warning alert-dismissible fade show m-2">Staff ID and Name are required.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
    } else {
      $sql = sprintf(
        "INSERT INTO staff (staff_id, department_id, staff_name, staff_address, staff_dob, staff_date_of_join, staff_email, staff_pno, staff_nic, staff_gender, staff_epf, staff_position, staff_type, staff_status) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
        mysqli_real_escape_string($con, $sid),
        mysqli_real_escape_string($con, $deptId),
        mysqli_real_escape_string($con, $name),
        mysqli_real_escape_string($con, $address),
        mysqli_real_escape_string($con, $dob),
        mysqli_real_escape_string($con, $doj),
        mysqli_real_escape_string($con, $email),
        mysqli_real_escape_string($con, $phone),
        mysqli_real_escape_string($con, $nic),
        mysqli_real_escape_string($con, $gender),
        mysqli_real_escape_string($con, $epf),
        mysqli_real_escape_string($con, $pos),
        mysqli_real_escape_string($con, $stype),
        mysqli_real_escape_string($con, $status)
      );
      if (@mysqli_query($con, $sql)) {
        $_staff_msg = '<div class="alert alert-success alert-dismissible fade show m-2">Staff added.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
      } else {
        $_staff_msg = '<div class="alert alert-danger alert-dismissible fade show m-2">Failed to add staff: ' . htmlspecialchars(mysqli_error($con)) . '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
      }
    }
  } else if ($act === 'edit') {
    if ($sid === '' || $name === '') {
      $_staff_msg = '<div class="alert alert-warning alert-dismissible fade show m-2">Staff ID and Name are required.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
    } else {
      $sql = sprintf(
        "UPDATE staff SET staff_name='%s', staff_address='%s', staff_dob='%s', staff_date_of_join='%s', staff_email='%s', staff_pno='%s', staff_nic='%s', staff_gender='%s', staff_epf='%s', staff_position='%s', staff_type='%s', staff_status='%s' WHERE staff_id='%s' AND department_id='%s'",
        mysqli_real_escape_string($con, $name),
        mysqli_real_escape_string($con, $address),
        mysqli_real_escape_string($con, $dob),
        mysqli_real_escape_string($con, $doj),
        mysqli_real_escape_string($con, $email),
        mysqli_real_escape_string($con, $phone),
        mysqli_real_escape_string($con, $nic),
        mysqli_real_escape_string($con, $gender),
        mysqli_real_escape_string($con, $epf),
        mysqli_real_escape_string($con, $pos),
        mysqli_real_escape_string($con, $stype),
        mysqli_real_escape_string($con, $status),
        mysqli_real_escape_string($con, $sid),
        mysqli_real_escape_string($con, $deptId)
      );
      if (@mysqli_query($con, $sql) && mysqli_affected_rows($con) >= 0) {
        $_staff_msg = '<div class="alert alert-success alert-dismissible fade show m-2">Staff updated.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
      } else {
        $_staff_msg = '<div class="alert alert-danger alert-dismissible fade show m-2">Failed to update staff: ' . htmlspecialchars(mysqli_error($con)) . '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
      }
    }
  } else if ($act === 'delete') {
    if ($sid === '') {
      $_staff_msg = '<div class="alert alert-warning alert-dismissible fade show m-2">Staff ID required to delete.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
    } else {
      $sql = sprintf(
        "DELETE FROM staff WHERE staff_id='%s' AND department_id='%s'",
        mysqli_real_escape_string($con, $sid),
        mysqli_real_escape_string($con, $deptId)
      );
      if (@mysqli_query($con, $sql) && mysqli_affected_rows($con) > 0) {
        $_staff_msg = '<div class="alert alert-success alert-dismissible fade show m-2">Staff deleted.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
      } else {
        $_staff_msg = '<div class="alert alert-danger alert-dismissible fade show m-2">Failed to delete staff.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
      }
    }
  }
}

// Students actively enrolled in this department
if ($deptId !== '') {
  $sqlStudents = "SELECT COUNT(DISTINCT s.student_id) AS c
                   FROM student s
                   JOIN student_enroll se ON se.student_id=s.student_id AND se.student_enroll_status IN ('Following','Active')
                   JOIN course c ON c.course_id=se.course_id
                   WHERE c.department_id='" . mysqli_real_escape_string($con, $deptId) . "'";
  if ($r = mysqli_query($con, $sqlStudents)) {
    $row = mysqli_fetch_assoc($r);
    $counts['students'] = (int)($row['c'] ?? 0);
    mysqli_free_result($r);
  }
}

// Staff in department
if ($deptId !== '') {
  $sqlStaff = "SELECT COUNT(*) AS c FROM staff WHERE department_id='" . mysqli_real_escape_string($con, $deptId) . "'";
  if ($r = mysqli_query($con, $sqlStaff)) {
    $row = mysqli_fetch_assoc($r);
    $counts['staff'] = (int)($row['c'] ?? 0);
    mysqli_free_result($r);
  }
}

// Courses in department
if ($deptId !== '') {
  $sqlCourses = "SELECT COUNT(*) AS c FROM course WHERE department_id='" . mysqli_real_escape_string($con, $deptId) . "'";
  if ($r = mysqli_query($con, $sqlCourses)) {
    $row = mysqli_fetch_assoc($r);
    $counts['courses'] = (int)($row['c'] ?? 0);
    mysqli_free_result($r);
  }
}

// OnPeak pending requests for this department
if ($deptId !== '') {
  $sqlOnPeak = "SELECT COUNT(*) AS c FROM onpeak_request WHERE department_id='" . mysqli_real_escape_string($con, $deptId) . "' AND TRIM(LOWER(onpeak_request_status)) LIKE 'pending%'";
  if ($r = mysqli_query($con, $sqlOnPeak)) {
    $row = mysqli_fetch_assoc($r);
    $counts['onpeak_pending'] = (int)($row['c'] ?? 0);
    mysqli_free_result($r);
  }
}

// Hostel pending requests (guard table existence)
if ($deptId !== '') {
  $tblCheck = mysqli_query($con, "SHOW TABLES LIKE 'hostel_requests'");
  if ($tblCheck && mysqli_num_rows($tblCheck) > 0) {
    $sqlHostel = "SELECT COUNT(*) AS c FROM hostel_requests WHERE department_id='" . mysqli_real_escape_string($con, $deptId) . "' AND TRIM(LOWER(status)) LIKE 'pending%'";
    if ($r = mysqli_query($con, $sqlHostel)) {
      $row = mysqli_fetch_assoc($r);
      $counts['hostel_pending'] = (int)($row['c'] ?? 0);
      mysqli_free_result($r);
    }
    mysqli_free_result($tblCheck);
  }
}

$title = 'HOD Dashboard | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
// Preload staff position types for modal select
$__posTypes = [];
$qpos = mysqli_query($con, 'SELECT staff_position_type_id, staff_position_type_name FROM staff_position_type ORDER BY staff_position_type_name');
if ($qpos) {
  while ($row = mysqli_fetch_assoc($qpos)) {
    $__posTypes[] = $row;
  }
  mysqli_free_result($qpos);
}
?>
<div class="container mt-3 hod-container hod-desktop-offset">
  <?php echo $_op_msg; ?>
  <?php echo $_staff_msg; ?>
  <div class="row mb-2 align-items-center">
    <div class="col">
      <h4 class="mb-0">HOD Dashboard</h4>
      <small class="text-muted">Department: <?php echo htmlspecialchars($deptName ?: $deptId ?: 'Unknown'); ?></small>
    </div>
    <div class="col-auto">
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo $base; ?>/logout.php"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
    </div>
  </div>

  <!-- Course Edit Modal -->
  <div class="modal fade" id="courseEditModal" tabindex="-1" role="dialog" aria-labelledby="courseEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h6 class="modal-title" id="courseEditModalLabel"><i class="fas fa-graduation-cap mr-1"></i> Edit Course</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body p-0" style="height: 80vh;">
          <iframe id="courseEditFrame" src="" style="border:0;width:100%;height:100%" title="Course Edit"></iframe>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-6 col-lg-3 mb-3">
      <div class="card metric-card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="metric-icon bg-primary text-white mr-2"><i class="fas fa-user-graduate"></i></div>
            <div>
              <div class="text-muted small">Students</div>
              <div class="h5 mb-0"><?php echo (int)$counts['students']; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
      <div class="card metric-card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="metric-icon bg-info text-white mr-2"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
              <div class="text-muted small">Staff</div>
              <div class="h5 mb-0"><?php echo (int)$counts['staff']; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
      <div class="card metric-card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="metric-icon bg-success text-white mr-2"><i class="fas fa-book"></i></div>
            <div>
              <div class="text-muted small">Courses</div>
              <div class="h5 mb-0"><?php echo (int)$counts['courses']; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
      <div class="card metric-card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="metric-icon bg-warning text-dark mr-2"><i class="far fa-clock"></i></div>
            <div>
              <div class="text-muted small">OnPeak Pending</div>
              <div class="h5 mb-0"><?php echo (int)$counts['onpeak_pending']; ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Left: Staff Management -->
    <div class="col-12 col-lg-8 mb-3">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><i class="fas fa-user-tie mr-1"></i> Staff Management</strong>
          <div>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>/staff/StaffManage.php?department_id=<?php echo urlencode($deptId); ?>">Manage</a>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Position</th>

                </tr>
              </thead>
              <tbody>
                <?php
                $staffSql = "SELECT 
                              staff_id, 
                              staff_name, 
                              COALESCE(staff_address,'') AS addr,
                              COALESCE(staff_dob,'') AS dob,
                              COALESCE(staff_date_of_join,'') AS doj,
                              COALESCE(staff_email,'') AS email,
                              COALESCE(staff_pno,'') AS pno,
                              COALESCE(staff_nic,'') AS nic,
                              COALESCE(staff_gender,'') AS gender,
                              COALESCE(staff_epf,'') AS epf,
                              COALESCE(staff_position,'') AS pos,
                              COALESCE(staff_type,'') AS stype,
                              COALESCE(staff_status,'') AS stat
                            FROM staff 
                            WHERE department_id='" . mysqli_real_escape_string($con, $deptId) . "' 
                            ORDER BY staff_name 
                            LIMIT 10";
                if ($rsS = mysqli_query($con, $staffSql)) {
                  if (mysqli_num_rows($rsS) === 0) echo '<tr><td colspan="4" class="text-center text-muted">No staff in department</td></tr>';
                  while ($s = mysqli_fetch_assoc($rsS)) {
                    echo '<tr>'
                      . '<td>' . htmlspecialchars($s['staff_id']) . '</td>'
                      . '<td>' . htmlspecialchars($s['staff_name']) . '</td>'
                      . '<td>' . htmlspecialchars($s['pos']) . '</td>'

                      . '</tr>';
                  }
                  mysqli_free_result($rsS);
                } else {
                  echo '<tr><td colspan="4" class="text-center text-muted">Unable to load staff</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!-- Right: Latest OnPeak Requests (compact, max 8 with scroll) -->
    <div class="col-12 col-lg-4 mb-3">
      <div class="card shadow-sm h-100 small">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><i class="far fa-calendar-check mr-1"></i> Latest OnPeak Requests</strong>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>/onpeak/RequestOnPeak.php">Open</a>
        </div>
        <div class="card-body p-0" style="max-height: 360px; overflow-y: auto;">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr>
                <th>Student</th>
                <th>Exit</th>
                <th>Return</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $q = "SELECT o.*, s.student_ininame, s.student_fullname FROM onpeak_request o
                  LEFT JOIN student s ON s.student_id = o.student_id
                  WHERE o.department_id='" . mysqli_real_escape_string($con, $deptId) . "'
                    AND (o.onpeak_request_status IS NULL OR TRIM(LOWER(o.onpeak_request_status)) LIKE 'pending%')
                  ORDER BY o.id DESC LIMIT 8";
              if ($rs = mysqli_query($con, $q)) {
                if (mysqli_num_rows($rs) === 0) {
                  echo '<tr><td colspan=\"5\" class=\"text-center text-muted\">No requests</td></tr>';
                }
                while ($r = mysqli_fetch_assoc($rs)) {
                  $name = $r['student_ininame'] ?: ($r['student_fullname'] ?: $r['student_id']);
                  $st = trim(strtolower($r['onpeak_request_status'] ?? ''));
                  $badge = 'secondary';
                  if ($st === '' || strpos($st, 'pend') === 0) $badge = 'warning';
                  elseif (strpos($st, 'approv') === 0) $badge = 'success';
                  elseif (strpos($st, 'reject') === 0 || strpos($st, 'not') === 0) $badge = 'danger';
                  $isPending = ($st === '' || strpos($st, 'pend') === 0);
                  echo '<tr>'
                    . '<td>' . htmlspecialchars($name) . ' <small class="text-muted">(' . htmlspecialchars($r['student_id']) . ')</small></td>'
                    . '<td>' . htmlspecialchars($r['exit_date']) . ' ' . htmlspecialchars($r['exit_time']) . '</td>'
                    . '<td>' . htmlspecialchars($r['return_date']) . ' ' . htmlspecialchars($r['return_time']) . '</td>'
                    . '<td class="text-right">'
                    . ($isPending
                      ? '<form method="post" class="d-inline">'
                      . '<input type="hidden" name="op_id" value="' . (int)$r['id'] . '">'
                      . '<div class="btn-group btn-group-sm" role="group">'
                      . '<button name="op_action" value="approve" class="btn btn-outline-success btn-icon" title="Approve" onclick="return confirm(\'Approve this request?\');"><i class="fas fa-check"></i></button>'
                      . '<button name="op_action" value="reject" class="btn btn-outline-danger btn-icon" title="Reject" onclick="return confirm(\'Reject this request?\');"><i class="fas fa-times"></i></button>'
                      . '</div>'
                      . '</form>'
                      : '<span class="text-muted small">—</span>'
                    )
                    . '</td>'
                    . '</tr>';
                }
                mysqli_free_result($rs);
              } else {
                echo '<tr><td colspan=\"5\" class=\"text-center text-muted\">Failed to load</td></tr>';
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>



  <!-- Department Courses List -->
  <div class="row">
    <div class="col-12 mb-3">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong><i class="fas fa-graduation-cap mr-1"></i> Courses (Department)</strong>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>/course/Course.php?department_id=<?php echo urlencode($deptId); ?>">View All</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="thead-light">
                <tr>
                  <th>Course ID</th>
                  <th>Course Name</th>
                  <th class="text-center">NVQ Level</th>
                  <th class="text-center">Active Groups</th>
                  <th class="text-center">Enrolled Students</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $rows = [];
                // primary: department-scoped using provided SQL
                if ($deptId !== '') {
                  $sql = "SELECT 
                            c.course_id, 
                            c.course_name, 
                            d.department_name,
                            COALESCE(c.course_nvq_level, '') AS nvq_level,
                            COALESCE(g.gc, 0) AS groups_count,
                            COALESCE(s.sc, 0) AS students_count
                          FROM course c
                          INNER JOIN department d ON d.department_id = c.department_id
                          LEFT JOIN (
                              SELECT course_id, COUNT(*) AS gc
                              FROM `groups`
                              WHERE status = 'active'
                              GROUP BY course_id
                          ) g ON g.course_id = c.course_id
                          LEFT JOIN (
                              SELECT se.course_id, COUNT(DISTINCT se.student_id) AS sc
                              FROM student_enroll se
                              WHERE se.student_enroll_status IN ('Following', 'Active')
                              GROUP BY se.course_id
                          ) s ON s.course_id = c.course_id
                          WHERE c.department_id='" . mysqli_real_escape_string($con, $deptId) . "'
                          ORDER BY c.course_name
                          LIMIT 0, 25";
                  $qr = mysqli_query($con, $sql);
                  if ($qr) {
                    while ($r = mysqli_fetch_assoc($qr)) {
                      $rows[] = $r;
                    }
                    mysqli_free_result($qr);
                  }
                }
                // fallback: show all courses if none found or no dept id
                if (empty($rows)) {
                  $sqlAll = "SELECT 
                              c.course_id, 
                              c.course_name, 
                              d.department_name,
                              COALESCE(c.course_nvq_level, '') AS nvq_level,
                              COALESCE(g.gc, 0) AS groups_count,
                              COALESCE(s.sc, 0) AS students_count
                            FROM course c
                            INNER JOIN department d ON d.department_id = c.department_id
                            LEFT JOIN (
                                SELECT course_id, COUNT(*) AS gc
                                FROM `groups`
                                WHERE status = 'active'
                                GROUP BY course_id
                            ) g ON g.course_id = c.course_id
                            LEFT JOIN (
                                SELECT se.course_id, COUNT(DISTINCT se.student_id) AS sc
                                FROM student_enroll se
                                WHERE se.student_enroll_status IN ('Following', 'Active')
                                GROUP BY se.course_id
                            ) s ON s.course_id = c.course_id
                            ORDER BY c.course_name
                            LIMIT 0, 25";
                  $qr2 = mysqli_query($con, $sqlAll);
                  if ($qr2) {
                    while ($r = mysqli_fetch_assoc($qr2)) {
                      $rows[] = $r;
                    }
                    mysqli_free_result($qr2);
                  }
                }
                if (empty($rows)) {
                  echo '<tr><td colspan="5" class="text-center text-muted">No courses found</td></tr>';
                } else {
                  foreach ($rows as $r) {
                    $cid = $r['course_id'];
                    $isADM = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
                    $editUrl = $base . '/course/AddCourse.php?edits=' . urlencode($cid);
                    $delUrl  = $base . '/course/Course.php?delete_id=' . urlencode($cid);
                    echo '<tr>'
                      . '<td>' . htmlspecialchars($cid) . '</td>'
                      . '<td>' . htmlspecialchars($r['course_name']) . '</td>'
                      . '<td class="text-center">' . htmlspecialchars($r['nvq_level']) . '</td>'
                      . '<td class="text-center">' . (int)$r['groups_count'] . '</td>'
                      . '<td class="text-center">' . (int)$r['students_count'] . '</td>'
                      . '<td class="text-right">'
                      . '<div class="btn-group btn-group-sm" role="group">'
                      . '<a class="btn btn-light border btn-icon" href="' . $base . '/module/Module.php?course_id=' . urlencode($cid) . '" title="Modules"><i class="fas fa-th-list"></i></a>'
                      . '<a class="btn btn-light border btn-icon" href="' . $base . '/group/Groups.php?course_id=' . urlencode($cid) . '" title="Groups"><i class="fas fa-users"></i></a>'
                      . '</div>'
                      . '</td>'
                      . '</tr>';
                  }
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Staff Add/Edit/Delete Modal (inline form matching StaffManage.php) -->
<div class="modal fade" id="staffFormModal" tabindex="-1" role="dialog" aria-labelledby="staffFormModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h6 class="modal-title" id="staffFormModalLabel"><i class="fas fa-user-tie mr-1"></i> Staff</h6>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="staff_action" id="staff_action" value="add">
          <div class="form-group">
            <label for="staff_id">Staff ID</label>
            <input type="text" class="form-control" name="staff_id" id="staff_id" required>
          </div>
          <div class="form-group">
            <label for="staff_name">Full Name</label>
            <input type="text" class="form-control" name="staff_name" id="staff_name" required>
          </div>
          <div class="form-group">
            <label for="staff_address">Address</label>
            <input type="text" class="form-control" name="staff_address" id="staff_address">
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label for="staff_dob">Date of Birth</label>
              <input type="date" class="form-control" name="staff_dob" id="staff_dob">
            </div>
            <div class="form-group col-6">
              <label for="staff_date_of_join">Date of Join</label>
              <input type="date" class="form-control" name="staff_date_of_join" id="staff_date_of_join">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label for="staff_email">Email</label>
              <input type="email" class="form-control" name="staff_email" id="staff_email">
            </div>
            <div class="form-group col-6">
              <label for="staff_pno">Telephone</label>
              <input type="text" class="form-control" name="staff_pno" id="staff_pno" placeholder="0770123456">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label for="staff_nic">NIC</label>
              <input type="text" class="form-control" name="staff_nic" id="staff_nic">
            </div>
            <div class="form-group col-6">
              <label for="staff_gender">Gender</label>
              <select class="custom-select" name="staff_gender" id="staff_gender">
                <option value="">Choose Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Transgender">Transgender</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label for="staff_epf">EPF No</label>
              <input type="text" class="form-control" name="staff_epf" id="staff_epf">
            </div>
            <div class="form-group col-6">
              <label for="staff_position">Position</label>
              <select class="custom-select" name="staff_position" id="staff_position">
                <option value="">-- Select --</option>
                <?php foreach ($__posTypes as $pt): ?>
                  <option value="<?php echo htmlspecialchars($pt['staff_position_type_id']); ?>"><?php echo htmlspecialchars($pt['staff_position_type_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-6">
              <label for="staff_type">Type</label>
              <select class="custom-select" name="staff_type" id="staff_type">
                <option value="">Choose Type</option>
                <option value="Permanent">Permanent</option>
                <option value="On Contract">On Contract</option>
                <option value="Visiting Lecturer">Visiting Lecturer</option>
              </select>
            </div>
            <div class="form-group col-6">
              <label for="staff_status">Status</label>
              <select class="custom-select" name="staff_status" id="staff_status">
                <option value="">Choose Status</option>
                <option value="Working">Working</option>
                <option value="Terminated">Terminated</option>
                <option value="Resigned">Resigned</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-danger d-none" id="staffDeleteBtn">Delete</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  (function() {
    if (!window.jQuery) return;
    jQuery('#staffFormModal').on('show.bs.modal', function(e) {
      var btn = e.relatedTarget || null;

      function getAttr(name) {
        return (btn && typeof btn.getAttribute === 'function') ? btn.getAttribute(name) : null;
      }
      var mode = getAttr('data-mode') || 'edit';
      var form = this.querySelector('form');
      var fields = ['staff_id', 'staff_name', 'staff_address', 'staff_dob', 'staff_date_of_join', 'staff_email', 'staff_pno', 'staff_nic', 'staff_gender', 'staff_epf', 'staff_position', 'staff_type', 'staff_status'];
      var delBtn = document.getElementById('staffDeleteBtn');
      if (mode === 'add') {
        form.staff_action.value = 'add';
        fields.forEach(function(f) {
          var el = form.querySelector('#' + f);
          if (el) {
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
          }
        });
        form.querySelector('#staff_id').readOnly = false;
        if (delBtn) delBtn.classList.add('d-none');
      } else {
        form.staff_action.value = 'edit';
        fields.forEach(function(f) {
          var el = form.querySelector('#' + f);
          if (!el) return;
          var v = getAttr('data-' + f);
          if (v === null || typeof v === 'undefined') {
            v = getAttr('data-' + f.replace(/_/g, '-'));
          }
          v = (v == null) ? '' : v;
          if (el.tagName === 'SELECT') el.value = v;
          else el.value = v;
        });
        // If key fields are empty, fetch via AJAX as fallback
        var needAjax = !form.staff_id.value || !form.staff_name.value || !form.staff_position.value || !form.staff_email.value;
        if (needAjax) {
          var sid = getAttr('data-staff_id') || getAttr('data-staff-id') || form.staff_id.value || '';
          if (sid) {
            // Always call the same page (Dashboard.php) to avoid base path issues
            var url = window.location.pathname + '?ajax=staff&staff_id=' + encodeURIComponent(sid);
            fetch(url)
              .then(function(r) {
                return r.json();
              })
              .then(function(json) {
                if (!json || json.ok !== true || !json.data) return;
                var d = json.data;

                function setVal(id, val) {
                  var el = form.querySelector('#' + id);
                  if (!el) return;
                  if (el.tagName === 'SELECT') {
                    el.value = val || '';
                  } else {
                    el.value = val || '';
                  }
                }
                setVal('staff_id', d.staff_id);
                setVal('staff_name', d.staff_name);
                setVal('staff_address', d.staff_address);
                setVal('staff_dob', d.staff_dob);
                setVal('staff_date_of_join', d.staff_date_of_join);
                setVal('staff_email', d.staff_email);
                setVal('staff_pno', d.staff_pno);
                setVal('staff_nic', d.staff_nic);
                setVal('staff_gender', d.staff_gender);
                setVal('staff_epf', d.staff_epf);
                setVal('staff_position', d.staff_position);
                setVal('staff_type', d.staff_type);
                setVal('staff_status', d.staff_status);
              })
              .catch(function() {
                /* silent */ });
          }
        }
        form.querySelector('#staff_id').readOnly = true; // ID cannot be changed on edit
        if (delBtn) {
          delBtn.classList.remove('d-none');
          delBtn.onclick = function() {
            if (confirm('Delete this staff?')) {
              form.staff_action.value = 'delete';
              form.submit();
            }
          };
        }
      }
    });
    // Wire up Course Edit modal (loads AddCourse.php into iframe)
    jQuery('#courseEditModal').on('show.bs.modal', function(e) {
      var btn = e.relatedTarget || {};
      var url = btn.getAttribute ? (btn.getAttribute('data-url') || '') : '';
      var fr = document.getElementById('courseEditFrame');
      if (fr && url) {
        fr.src = url;
      }
    });
    jQuery('#courseEditModal').on('hidden.bs.modal', function() {
      var fr = document.getElementById('courseEditFrame');
      if (fr) {
        fr.src = '';
      }
    });
  })();
</script>
<style>
  .hod-container {
    max-width: 1200px;
    margin: 0 auto;
  }

  .metric-card .metric-icon {
    width: 40px;
    height: 40px;
    border-radius: .5rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Apply negative left margin only on desktop */
  @media (min-width: 992px) {

    /* lg and up */
    .hod-desktop-offset {
      margin-left: -100px;
    }
  }

  @media (max-width: 991.98px) {

    /* below lg */
    .hod-desktop-offset {
      margin-left: 0 !important;
    }
  }

  /* Icon buttons: compact square, centered icons */
  .btn-icon {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .btn-group-sm .btn-icon {
    width: 28px;
    height: 28px;
  }

  .btn-icon i {
    font-size: 0.9rem;
    line-height: 1;
  }

  .table .btn-group.btn-group-sm .btn {
    margin-right: 4px;
  }

  .table .btn-group.btn-group-sm .btn:last-child {
    margin-right: 0;
  }
</style>
<?php include_once __DIR__ . '/../footer.php'; ?>