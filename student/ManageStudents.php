<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Access control: Admin; Director (DIR), Accounts (ACC), and Finance (FIN) have view-only; SAO, IN3, HOD, and EXAM allowed per-page rules
require_roles(['ADM', 'DIR', 'ACC', 'FIN', 'SAO', 'IN3', 'HOD', 'EXAM']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
// Treat DIR, ACC, and FIN the same (view-only access on this page)
$is_dir   = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['DIR','ACC','FIN'], true);
$is_sao   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO';
$is_hod   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
$is_in3   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'IN3';
// Mutations allowed for Admin and SAO; DIR and HOD are strictly view-only
$can_mutate = ($is_admin || $is_sao);
// Finance/Accounts role flag (view bank details)
$is_finacc = isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['FIN','ACC'], true);

// Helpers
function h($s)
{
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
$base = defined('APP_BASE') ? APP_BASE : '';

// Display helper: Title-case names while preserving initials like H.A.R.C.
function display_name($name)
{
  $name = trim((string)$name);
  if ($name === '') return '';
  // Split by spaces, transform tokens individually
  $parts = preg_split('/\s+/', $name);
  $out = [];
  $hasMb = function_exists('mb_strtolower') && function_exists('mb_convert_case');
  foreach ($parts as $p) {
    // Keep tokens with periods or that are short ALL-CAPS as-is (initials)
    if (strpos($p, '.') !== false || (preg_match('/^[A-Z]+$/', $p) && strlen($p) <= 4)) {
      $out[] = strtoupper($p);
      continue;
    }
    if ($hasMb) {
      // Unicode-aware title case
      $lower = mb_strtolower($p, 'UTF-8');
      $out[] = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    } else {
      // Fallback: apply ASCII-only title case; leave non-ASCII tokens unchanged
      if (preg_match('/^[\x20-\x7E]+$/', $p)) {
        $out[] = ucwords(strtolower($p));
      } else {
        $out[] = $p; // preserve original (e.g., Tamil) to avoid corruption
      }
    }
  }
  return implode(' ', $out);
}

// Handle actions
$messages = [];
$errors = [];

// Block mutations for users without permission (only ADM and SAO can mutate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$can_mutate) {
    http_response_code(403);
    echo 'Forbidden: View-only access';
    exit;
  }
  // Mark conduct as accepted (single)
  if (isset($_POST['mark_accept_sid'])) {
    $sid = $_POST['mark_accept_sid'];
    $stmt = mysqli_prepare($con, "UPDATE student SET student_conduct_accepted_at = NOW() WHERE student_id = ?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $sid);
      mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);
      if ($affected > 0) {
        $messages[] = "Marked conduct accepted for $sid";
      } else {
        $errors[] = "No changes for $sid";
      }
    } else {
      $errors[] = 'DB error (mark accept)';
    }
  }
  // Clear conduct acceptance (single)
  if (isset($_POST['clear_accept_sid'])) {
    $sid = $_POST['clear_accept_sid'];
    $stmt = mysqli_prepare($con, "UPDATE student SET student_conduct_accepted_at = NULL WHERE student_id = ?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $sid);
      mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);
      if ($affected > 0) {
        $messages[] = "Cleared conduct acceptance for $sid";
      } else {
        $errors[] = "No changes for $sid";
      }
    } else {
      $errors[] = 'DB error (clear accept)';
    }
  }
  // Single delete
  if (isset($_POST['delete_sid'])) {
    $sid = $_POST['delete_sid'];
    $stmt = mysqli_prepare($con, "UPDATE student SET student_status='Inactive' WHERE student_id=?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $sid);
      mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);
      if ($affected > 0) {
        // Also deactivate login for this student
        if ($u = mysqli_prepare($con, "UPDATE `user` SET `user_active`=0 WHERE `user_name`=?")) {
          mysqli_stmt_bind_param($u, 's', $sid);
          mysqli_stmt_execute($u);
          mysqli_stmt_close($u);
        }
        $messages[] = "Student $sid set to Inactive";
      } else {
        $errors[] = "No changes for $sid";
      }
    } else {
      $errors[] = 'DB error (single)';
    }
  }
  // Single activate (set status Active and enable login)
  if (isset($_POST['activate_sid'])) {
    $sid = $_POST['activate_sid'];
    $stmt = mysqli_prepare($con, "UPDATE student SET student_status='Active' WHERE student_id=?");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $sid);
      mysqli_stmt_execute($stmt);
      $affected = mysqli_stmt_affected_rows($stmt);
      mysqli_stmt_close($stmt);
      if ($affected > 0) {
        if ($u = mysqli_prepare($con, "UPDATE `user` SET `user_active`=1 WHERE `user_name`=?")) {
          mysqli_stmt_bind_param($u, 's', $sid);
          mysqli_stmt_execute($u);
          mysqli_stmt_close($u);
        }
        $messages[] = "Student $sid set to Active";
      } else {
        $errors[] = "No changes for $sid";
      }
    } else {
      $errors[] = 'DB error (activate)';
    }
  }
  // Bulk delete (Admin only)
  if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_inactivate') {
    if (!$is_admin) {
      $errors[] = 'You are not authorized to perform bulk inactivate.';
    } else {
      $sids = isset($_POST['sids']) && is_array($_POST['sids']) ? array_values(array_filter($_POST['sids'])) : [];
      if (!$sids) {
        $errors[] = 'No students selected for bulk inactivate.';
      } else {
        // Build a single UPDATE ... IN (...) with proper escaping
        $inParts = [];
        foreach ($sids as $sid) {
          $inParts[] = "'" . mysqli_real_escape_string($con, $sid) . "'";
        }
        $in = implode(',', $inParts);
        $q = "UPDATE student SET student_status='Inactive' WHERE student_id IN ($in)";
        if (mysqli_query($con, $q)) {
          $affected = mysqli_affected_rows($con);
          // Also deactivate corresponding user accounts
          $uq = "UPDATE `user` SET `user_active`=0 WHERE `user_name` IN ($in)";
          mysqli_query($con, $uq);
          $messages[] = ($affected > 0) ? 'Selected students set to Inactive and logins deactivated' : 'No rows updated';
        } else {
          $errors[] = 'Bulk update failed';
        }
      }
    }
  }
  // Redirect to avoid resubmission
  if ($messages || $errors) {
    $_SESSION['flash_messages'] = $messages;
    $_SESSION['flash_errors'] = $errors;
    header('Location: ' . $base . '/student/ManageStudents.php');
    exit;
  }
}

// Flash
if (!empty($_SESSION['flash_messages'])) {
  $messages = $_SESSION['flash_messages'];
  unset($_SESSION['flash_messages']);
}
if (!empty($_SESSION['flash_errors'])) {
  $errors = $_SESSION['flash_errors'];
  unset($_SESSION['flash_errors']);
}

// Filters (no Academic Year)
$fstatus = isset($_GET['status']) ? $_GET['status'] : '';
// Conduct acceptance filter: '', 'accepted', 'pending'
$fconduct = isset($_GET['conduct']) ? trim($_GET['conduct']) : '';

// Other filters: department, course, gender
$fdept   = isset($_GET['department_id']) ? trim($_GET['department_id']) : '';
$fcourse = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
$fgender = isset($_GET['gender']) ? trim($_GET['gender']) : '';
// Group filter (HOD group-wise)
$fgroup  = isset($_GET['group_id']) ? trim($_GET['group_id']) : '';
// Academic Year filter
$fyear   = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';

// For DIR (view-only), restrict to Active students regardless of requested filter
if ($is_dir) {
  $fstatus = 'Active';
}

// For HOD and IN3, force department to their own and do not allow changing it via UI
if ($is_hod || $is_in3) {
  $ownDept = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
  if ($ownDept !== '') { $fdept = $ownDept; }
}

$where = [];
$params = [];
// Base SQL for both list and export
$baseSql = "SELECT DISTINCT
              `s`.`student_id`,
              `s`.`student_fullname`,
              `s`.`student_ininame`,
              `s`.`student_email`,
              `s`.`student_phone`,
              `s`.`student_nic`,
              `s`.`student_status`,
              `s`.`student_gender`,
              `s`.`student_conduct_accepted_at`,
              `s`.`bank_name`,
              `s`.`bank_account_no`,
              `s`.`bank_branch`,
              `s`.`bank_frontsheet_path`,
              `e`.`student_enroll_status`,
              `e`.`course_id`,
              `c`.`course_name`,
              `d`.`department_id`,
              `d`.`department_name`
            FROM `student` AS `s`
            LEFT JOIN `student_enroll` AS `e` ON `e`.`student_id` = `s`.`student_id`
            LEFT JOIN `course` AS `c` ON `c`.`course_id` = `e`.`course_id`
            LEFT JOIN `department` AS `d` ON `d`.`department_id` = `c`.`department_id`";
if ($fstatus !== '') {
  $where[] = "s.student_status = '" . mysqli_real_escape_string($con, $fstatus) . "'";
}
if ($fdept !== '') {
  // If a specific course is selected, normal join filter is fine.
  // If no course is selected, use EXISTS to tolerate missing outer course rows while still enforcing department via enrollment mapping.
  if ($fcourse !== '') {
    $where[] = "c.department_id = '" . mysqli_real_escape_string($con, $fdept) . "'";
  } else {
    $safeDept = mysqli_real_escape_string($con, $fdept);
    $where[] = "EXISTS (SELECT 1 FROM `student_enroll` AS `e2` JOIN `course` AS `c2` ON `c2`.`course_id` = `e2`.`course_id` WHERE `e2`.`student_id` = `s`.`student_id` AND `c2`.`department_id` = '" . $safeDept . "')";
  }
}
if ($fcourse !== '') {
  $where[] = "c.course_id = '" . mysqli_real_escape_string($con, $fcourse) . "'";
}
if ($fgender !== '') {
  $where[] = "s.student_gender = '" . mysqli_real_escape_string($con, $fgender) . "'";
}
if ($fgroup !== '') {
  $gid = (int)$fgroup;
  $where[] = "EXISTS (SELECT 1 FROM `group_students` AS `gs` WHERE `gs`.`student_id` = `s`.`student_id` AND `gs`.`group_id` = {$gid} AND `gs`.`status` = 'active')";
}
if ($fyear !== '') {
  $where[] = "e.academic_year = '" . mysqli_real_escape_string($con, $fyear) . "'";
}
if ($fconduct === 'accepted') {
  $where[] = "s.student_conduct_accepted_at IS NOT NULL";
} elseif ($fconduct === 'pending') {
  $where[] = "s.student_conduct_accepted_at IS NULL";
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$sqlWhereFinal = $whereSql; // No Academic Year constraint

// Base ORDER/GROUP for list and export (no pagination)
// Prioritize Active students first, then Inactive students
$groupOrder = " ORDER BY CASE WHEN s.student_status='Active' THEN 1 WHEN s.student_status='Inactive' THEN 2 ELSE 3 END ASC, s.`student_id` ASC";

// List and export SQL (full result set)
$sqlList = $baseSql . $sqlWhereFinal . $groupOrder;
$sqlExport = $sqlList;
$res = mysqli_query($con, $sqlList);
$total_count = ($res ? mysqli_num_rows($res) : 0);

// Optional debug: show filters/SQL on demand for admins/SAO
if (($is_admin || $is_sao) && isset($_GET['debug']) && $_GET['debug'] == '1') {
  echo '<div class="container-fluid"><div class="alert alert-warning small">'
    . '<div><strong>Debug (server):</strong></div>'
    . '<div><strong>Filters</strong> ' . h(json_encode([
      'status' => $fstatus,
      'department_id' => $fdept,
      'course_id' => $fcourse,
      'gender' => $fgender,
      'conduct' => $fconduct,
      'group_id' => $fgroup,
      'academic_year' => $fyear,
    ])) . '</div>'
    . '<div><strong>SQL (list)</strong> <code style="white-space:pre-wrap;">' . h($sqlList) . '</code></div>'
    . '<div><strong>Rows</strong> ' . (int)$total_count . '</div>'
    . '<div><strong>DB Error</strong> ' . h(mysqli_error($con)) . '</div>';
  // Extra counts to compare
  $cntAll = 0; $cntYear = 0;
  if ($r0 = mysqli_query($con, 'SELECT COUNT(*) AS `c` FROM `student`')) { $cntAll = (int)mysqli_fetch_assoc($r0)['c']; mysqli_free_result($r0); }
  echo '<div><strong>Total students</strong> ' . $cntAll . '</div>'
     . '</div></div>';
}

// Export (CSV opened by Excel) with current filters
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  $filename = 'students_' . date('Ymd_His') . '.csv';
  // Send Excel-friendly CSV headers
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename=' . $filename);
  header('Pragma: no-cache');
  header('Expires: 0');
  // Clear any existing output buffers to avoid stray bytes
  if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
  }
  // Output BOM for Excel UTF-8
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  // Header row
  fputcsv($out, ['Student ID', 'Full Name', 'Name with Initial', 'Email', 'Phone', 'NIC', 'Status', 'Gender', 'Course', 'Department', 'Conduct Accepted At', 'Bank', 'Account No', 'Branch', 'Bank Front Page']);
  if ($qr = mysqli_query($con, $sqlExport)) {
    while ($r = mysqli_fetch_assoc($qr)) {
      fputcsv($out, [
        $r['student_id'],
        display_name($r['student_fullname'] ?? ''),
        $r['student_ininame'] ?? '',
        $r['student_email'] ?? '',
        $r['student_phone'] ?? '',
        $r['student_nic'] ?? '',
        $r['student_status'] ?? '',
        $r['student_gender'] ?? '',
        $r['course_name'] ?? '',
        $r['department_name'] ?? '',
        $r['student_conduct_accepted_at'] ?? '',
        $r['bank_name'] ?? '',
        $r['bank_account_no'] ?? '',
        $r['bank_branch'] ?? '',
        $r['bank_frontsheet_path'] ?? ''
      ]);
    }
    mysqli_free_result($qr);
  }
  fclose($out);
  exit;
}

// Export-to-file (excel_save) handler removed intentionally

// Load dropdown data: departments and courses (for filters)
$departments = [];
if ($r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($r)) {
    $departments[] = $row;
  }
  mysqli_free_result($r);
}
$courses = [];
if ($r = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name")) {
  while ($row = mysqli_fetch_assoc($r)) {
    $courses[] = $row;
  }
  mysqli_free_result($r);
}

// Load academic years (descending)
$years = [];
if ($r = mysqli_query($con, "SELECT academic_year FROM academic ORDER BY academic_year DESC")) {
  while ($row = mysqli_fetch_assoc($r)) { $years[] = $row['academic_year']; }
  mysqli_free_result($r);
}

// Load groups list (scoped by department/course when provided)
$groups = [];
$grpSql = "SELECT g.id, g.name, g.course_id, g.academic_year, c.department_id FROM `groups` g JOIN `course` c ON c.course_id = g.course_id WHERE 1=1";
if ($fdept !== '') { $grpSql .= " AND c.department_id='" . mysqli_real_escape_string($con, $fdept) . "'"; }
if ($fcourse !== '') { $grpSql .= " AND g.course_id='" . mysqli_real_escape_string($con, $fcourse) . "'"; }
if ($fyear !== '') { $grpSql .= " AND g.academic_year='" . mysqli_real_escape_string($con, $fyear) . "'"; }
$grpSql .= " ORDER BY g.name";
if ($r = mysqli_query($con, $grpSql)) {
  while ($row = mysqli_fetch_assoc($r)) { $groups[] = $row; }
  mysqli_free_result($r);
}

// No Academic Year dropdown

// Include standard head and menu to load CSS/JS
$title = 'Manage Students | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
// Desktop-only offset on non-ADM
$__isADM = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM');
?>
<div class="page-content">
  <div class="container-fluid" style="max-width: 100% !important; width: 100% !important; margin-left: 0 !important; margin-right: 0 !important; padding-left: 15px; padding-right: 15px;">
    <div class="row align-items-center mt-3 mb-3">
      <div class="col">
        <?php if (!$is_hod): ?>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb bg-white shadow-sm mb-2">
            <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="../student/ImportStudentEnroll.php">Import & Enroll</a></li>
            <li class="breadcrumb-item active" aria-current="page">Students</a></li>
          </ol>
        </nav>
        <h4 class="d-flex align-items-center page-title mb-0">
          <i class="fas fa-users text-primary mr-2"></i>
          Manage Students
        </h4>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <div class="container-fluid" style="max-width: 100% !important; width: 100% !important; margin-left: 0 !important; margin-right: 0 !important; padding-left: 15px; padding-right: 15px;">
    <div class="row">
      <div class="col-12">
        <?php foreach ($messages as $m): ?>
          <div class="alert alert-success mb-3"><?php echo h($m); ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $e): ?>
          <div class="alert alert-danger mb-3"><?php echo h($e); ?></div>
        <?php endforeach; ?>

      <!-- Filters: Modern card layout with responsive grid -->
      <div class="card shadow-sm border-0 mb-4 first-section-card">
        <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff; padding: 1rem 1.25rem;">
          <div class="font-weight-semibold mb-2 mb-md-0" style="color: #ffffff !important;"><i class="fa fa-sliders-h mr-1"></i> Filters</div>
          <div class="d-flex align-items-center w-100 w-md-auto justify-content-between justify-content-md-end">
            <div class="d-none d-md-block mr-3" style="width: 260px;">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" id="quickSearch" class="form-control" placeholder="Quick search... (ID, name, email, phone, NIC)">
              </div>
            </div>
            <button class="btn btn-sm btn-outline-light d-md-none ml-auto" type="button" data-toggle="collapse" data-target="#filtersBox" aria-expanded="false" aria-controls="filtersBox">
              Show/Hide
            </button>
          </div>
        </div>
        <div id="filtersBox" class="collapse show">
          <div class="card-body" style="padding: 1.25rem;">
            <form class="mb-0" method="get" action="">
              <div class="form-row">
                <div class="form-group col-12 col-md-4 mb-3">
                  <label for="fdept" class="form-label">
                    <i class="fas fa-building mr-1"></i>Department
                  </label>
                  <select id="fdept" name="department_id" class="form-control custom-select" <?php echo ($is_hod || $is_in3) ? 'disabled' : ''; ?>>
                    <option value="">-- Any --</option>
                    <?php foreach ($departments as $d): ?>
                      <option value="<?php echo h($d['department_id']); ?>" <?php echo ($fdept === $d['department_id'] ? 'selected' : ''); ?>><?php echo h($d['department_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($is_hod || $is_in3): ?>
                    <input type="hidden" name="department_id" value="<?php echo h($fdept); ?>">
                    <small class="form-text text-muted mt-1">
                      <i class="fas fa-info-circle mr-1"></i>Showing students for your department only.
                    </small>
                  <?php endif; ?>
                </div>
                <div class="form-group col-12 col-md-4 mb-3">
                  <label for="fcourse" class="form-label">
                    <i class="fas fa-graduation-cap mr-1"></i>Course
                  </label>
                  <select id="fcourse" name="course_id" class="form-control custom-select">
                    <option value="">-- Any --</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?php echo h($c['course_id']); ?>" data-dept="<?php echo h($c['department_id']); ?>" <?php echo ($fcourse === $c['course_id'] ? 'selected' : ''); ?>><?php echo h($c['course_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-4 mb-3">
                  <label for="fyear" class="form-label">
                    <i class="fas fa-calendar-alt mr-1"></i>Academic Year
                  </label>
                  <select id="fyear" name="academic_year" class="form-control custom-select">
                    <option value="">-- Any --</option>
                    <?php foreach ($years as $y): ?>
                      <option value="<?php echo h($y); ?>" <?php echo ($fyear === $y ? 'selected' : ''); ?>><?php echo h($y); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-4 mb-3">
                  <label for="fgroup" class="form-label">
                    <i class="fas fa-users mr-1"></i>Group
                  </label>
                  <select id="fgroup" name="group_id" class="form-control custom-select">
                    <option value="">-- Any --</option>
                    <?php foreach ($groups as $g): ?>
                      <option value="<?php echo (int)$g['id']; ?>" data-dept="<?php echo h($g['department_id']); ?>" data-course="<?php echo h($g['course_id']); ?>" data-year="<?php echo h($g['academic_year']); ?>" <?php echo ($fgroup !== '' && (int)$fgroup === (int)$g['id'] ? 'selected' : ''); ?>><?php echo h($g['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-3 mb-3">
                  <label for="fgender" class="form-label">
                    <i class="fas fa-venus-mars mr-1"></i>Gender
                  </label>
                  <select id="fgender" name="gender" class="form-control custom-select">
                    <option value="">-- Any --</option>
                    <?php foreach (["Male", "Female", "Other"] as $g): ?>
                      <option value="<?php echo h($g); ?>" <?php echo ($fgender === $g ? 'selected' : ''); ?>><?php echo h($g); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-3 mb-3">
                  <label for="fstatus" class="form-label">
                    <i class="fas fa-check-circle mr-1"></i>Status
                  </label>
                  <select id="fstatus" name="status" class="form-control custom-select" <?php echo $is_dir ? 'disabled' : ''; ?>>
                    <option value="">-- Any --</option>
                    <?php foreach (["Active", "Inactive", "Following", "Completed", "Suspended", "Dropout"] as $st): ?>
                      <?php if (!$is_dir || $st === 'Active'): ?>
                        <option value="<?php echo h($st); ?>" <?php echo ($fstatus === $st ? 'selected' : ''); ?>><?php echo h($st); ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($is_dir): ?>
                    <input type="hidden" name="status" value="Active">
                  <?php endif; ?>
                </div>
                <div class="form-group col-12 col-md-3 mb-3">
                  <label for="fconduct" class="form-label">
                    <i class="fas fa-clipboard-check mr-1"></i>Conduct
                  </label>
                  <select id="fconduct" name="conduct" class="form-control custom-select">
                    <option value="">-- Any --</option>
                    <option value="accepted" <?php echo ($fconduct === 'accepted' ? 'selected' : ''); ?>>Accepted</option>
                    <option value="pending" <?php echo ($fconduct === 'pending' ? 'selected' : ''); ?>>Pending</option>
                  </select>
                </div>
                <div class="form-group col-12 col-md-3 mb-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <style>
        /* Full width container with balanced padding */
        .page-content .container-fluid {
          max-width: 100% !important;
          width: 100% !important;
          margin-left: 0 !important;
          margin-right: 0 !important;
        }
        
        @media (min-width: 576px) {
          .page-content .container-fluid {
            padding-left: 20px;
            padding-right: 20px;
          }
        }
        
        @media (min-width: 992px) {
          .page-content .container-fluid {
            padding-left: 30px;
            padding-right: 30px;
          }
        }
        
        /* Card header white text */
        .card-header {
          color: #ffffff !important;
        }
        .card-header * {
          color: #ffffff !important;
        }
        .card-header .badge {
          background: rgba(255, 255, 255, 0.3) !important;
          color: #ffffff !important;
        }
        
        /* Balanced card spacing */
        .card {
          margin-bottom: 1.5rem;
        }
        
        /* Compact table spacing */
        .table.table-sm td,
        .table.table-sm th {
          padding: .5rem .75rem;
        }
        
        /* Disable table hover effects */
        .table tbody tr:hover {
          background-color: transparent !important;
        }
        .table-striped tbody tr:hover {
          background-color: rgba(0, 0, 0, 0.05) !important;
        }
        .table tbody tr:hover td {
          background-color: transparent !important;
        }
        
        /* Balanced form spacing */
        .form-group {
          margin-bottom: 1rem;
        }
        
        /* Form Label Styling */
        .form-label {
          display: block;
          font-weight: 600;
          font-size: 0.875rem;
          color: #374151;
          margin-bottom: 0.5rem;
          line-height: 1.5;
        }
        
        .form-label i {
          color: #6366f1;
          margin-right: 0.25rem;
        }
        
        /* Proper sizing for select dropdowns */
        .form-control.custom-select,
        select.form-control {
          display: block;
          width: 100%;
          height: calc(2.5rem + 2px);
          padding: 0.625rem 2.5rem 0.625rem 0.875rem;
          font-size: 0.9375rem;
          font-weight: 400;
          line-height: 1.5;
          color: #374151;
          background-color: #ffffff;
          background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236366f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
          background-repeat: no-repeat;
          background-position: right 0.875rem center;
          background-size: 16px 12px;
          border: 1.5px solid #d1d5db;
          border-radius: 0.5rem;
          transition: all 0.2s ease-in-out;
          appearance: none;
          -webkit-appearance: none;
          -moz-appearance: none;
          cursor: pointer;
        }
        
        .form-control.custom-select:hover,
        select.form-control:hover {
          border-color: #6366f1;
          box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-control.custom-select:focus,
        select.form-control:focus {
          border-color: #6366f1;
          outline: 0;
          box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
          background-color: #ffffff;
        }
        
        .form-control.custom-select:disabled,
        select.form-control:disabled {
          background-color: #f3f4f6;
          color: #6b7280;
          cursor: not-allowed;
          opacity: 0.7;
        }
        
        /* Proper sizing for select options */
        .form-control.custom-select option,
        select.form-control option {
          padding: 0.75rem 1rem;
          font-size: 0.9375rem;
          line-height: 1.6;
          min-height: 2.5rem;
          color: #374151;
          background-color: #ffffff;
        }
        
        .form-control.custom-select option:checked,
        select.form-control option:checked {
          background-color: #6366f1;
          color: #ffffff;
          font-weight: 500;
        }
        
        .form-control.custom-select option:hover,
        select.form-control option:hover {
          background-color: #e0e7ff;
        }
        
        /* Select wrapper for better control */
        .form-group {
          position: relative;
        }
        
        /* Custom select arrow styling */
        .form-control.custom-select::-ms-expand,
        select.form-control::-ms-expand {
          display: none;
        }
        
        /* Balanced button spacing */
        .btn-group .btn {
          margin-right: 0.25rem;
        }
        .btn-group .btn:last-child {
          margin-right: 0;
        }

        /* On very small screens, avoid horizontal overflow on key columns */
        @media (max-width: 575.98px) {
          .breadcrumb { 
            margin-bottom: 0.75rem; 
            padding: 0.5rem 0.75rem; 
          }
          .page-title { 
            font-size: 1.15rem; 
            line-height: 1.25; 
            margin-bottom: 1rem;
          }
          .page-title i { 
            margin-right: 0.5rem !important; 
            font-size: 1rem; 
          }
          .first-section-card { 
            margin-top: 0.5rem !important; 
          }
          .card-header {
            padding: 0.75rem 1rem !important;
          }
          .card-body {
            padding: 1rem !important;
          }

          .table td,
          .table th {
            white-space: nowrap;
            padding: 0.4rem 0.5rem;
          }
          
          /* Smaller select on mobile */
          .form-label {
            font-size: 0.8125rem;
            margin-bottom: 0.375rem;
          }
          
          .form-control.custom-select,
          select.form-control {
            height: calc(2.25rem + 2px);
            padding: 0.5rem 2rem 0.5rem 0.75rem;
            font-size: 0.875rem;
            background-position: right 0.625rem center;
            background-size: 14px 10px;
          }
          
          .form-control.custom-select option,
          select.form-control option {
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            min-height: 2rem;
          }
        }

        /* Sticky header within scroll container */
        .table-sticky thead th {
          position: sticky;
          top: 0;
          background: #f8f9fa;
          z-index: 2;
          color: #1e293b;
        }

        /* Scroll container to enable sticky header */
        .table-scroll {
          max-height: 70vh;
          overflow-y: auto;
        }

        /* Wrap action buttons nicely */
        .btn-group.flex-wrap>.btn {
          margin: 2px;
        }

        /* Details rows for mobile */
        .details-row {
          display: none;
        }

        .details-row.show {
          display: table-row;
        }

        @media (min-width: 768px) {
          .details-row {
            display: none !important;
          }
        }
        
        /* Proper text color balance */
        .table td {
          color: #1e293b !important;
        }
        .table th {
          color: #1e293b !important;
          font-weight: 600;
        }
      </style>
      <script>
        // Client-side filter: limit course options by selected department and group options by department/course/year
        (function() {
          var dept = document.getElementById('fdept');
          var course = document.getElementById('fcourse');
          var groupSel = document.getElementById('fgroup');
          var yearSel = document.getElementById('fyear');
          if (!dept || !course) return;
          var allCourses = Array.prototype.slice.call(course.options).map(function(o) {
            return {
              value: o.value,
              text: o.text,
              dept: o.getAttribute('data-dept')
            };
          });
          var allGroups = groupSel ? Array.prototype.slice.call(groupSel.options).map(function(o){
            return { value:o.value, text:o.text, dept:o.getAttribute('data-dept'), course:o.getAttribute('data-course'), year:o.getAttribute('data-year') };
          }) : [];

          function apply() {
            var d = dept.value;
            var keepSelected = course.value;
            // Rebuild
            while (course.options.length) course.remove(0);
            var opt = document.createElement('option');
            opt.value = '';
            opt.text = '-- Any --';
            course.add(opt);
            allCourses.forEach(function(it) {
              if (!it.value) return; // skip placeholder
              if (!d || it.dept === d) {
                var o = document.createElement('option');
                o.value = it.value;
                o.text = it.text;
                course.add(o);
              }
            });
            // Try to restore selection if still valid
            if (keepSelected) {
              for (var i = 0; i < course.options.length; i++) {
                if (course.options[i].value === keepSelected) {
                  course.selectedIndex = i;
                  break;
                }
              }
            }

            // Rebuild groups based on dept and selected course
            if (groupSel) {
              var selectedCourse = course.value;
              var selectedYear = yearSel ? yearSel.value : '';
              var keepGroup = groupSel.value;
              while (groupSel.options.length) groupSel.remove(0);
              var g0 = document.createElement('option');
              g0.value = '';
              g0.text = '-- Any --';
              groupSel.add(g0);
              allGroups.forEach(function(it){
                if (!it.value) return;
                if (( !d || it.dept === d ) && ( !selectedCourse || it.course === selectedCourse ) && ( !selectedYear || it.year === selectedYear )) {
                  var go = document.createElement('option');
                  go.value = it.value; go.text = it.text;
                  groupSel.add(go);
                }
              });
              if (keepGroup) {
                for (var j=0; j<groupSel.options.length; j++) {
                  if (groupSel.options[j].value === keepGroup) { groupSel.selectedIndex = j; break; }
                }
              }
            }
          }
          dept.addEventListener('change', apply);
          if (course) course.addEventListener('change', apply);
          if (yearSel) yearSel.addEventListener('change', apply);
          // Initialize on load
          apply();
        })();
      </script>

      <form method="post" <?php echo $is_admin ? "onsubmit=\"return confirm('Inactivate selected students?');\"" : 'onsubmit="return false;"'; ?>>
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
          <div class="mb-2 mb-md-0">
            <?php if ($is_admin): ?>
              <button type="submit" name="bulk_action" value="bulk_inactivate" class="btn btn-danger btn-sm"><i class="fa fa-user-times mr-1"></i> Bulk Inactivate</button>
            <?php endif; ?>
          </div>
          <div class="mb-2 mb-md-0">
            <a href="<?php echo $base; ?>/student/ManageStudents.php" class="btn btn-outline-secondary btn-sm mr-2"><i class="fa fa-redo mr-1"></i> Clear Filters</a>
            <?php $qs = $_GET; $qs['export'] = 'excel'; $exportUrl = $base . '/student/ManageStudents.php?' . http_build_query($qs); ?>
            <a href="<?php echo h($exportUrl); ?>" class="btn btn-success btn-sm"><i class="fa fa-file-excel mr-1"></i> Export Excel</a>
          </div>
        </div>

        <!-- Results card -->
        <div class="card shadow-sm border-0">
          <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-md-between" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff; padding: 1rem 1.25rem;">
            <div class="font-weight-semibold mb-2 mb-md-0" style="color: #ffffff !important;"><i class="fa fa-users mr-1"></i> Students <span class="badge badge-light ml-2" style="background: rgba(255, 255, 255, 0.3); color: #ffffff;"><?php echo (int)$total_count; ?></span></div>
            <div class="d-md-none w-100">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" id="quickSearchMobile" class="form-control" placeholder="Quick search (ID, name, email, phone, NIC)">
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive table-scroll" style="border-top-left-radius:.25rem;border-top-right-radius:.25rem;">
              <table id="studentsTable" class="table table-striped table-bordered table-sm table-sticky mb-0">
                <thead>
                  <tr>
                    <?php if ($is_admin): ?>
                      <th class="d-none d-sm-table-cell"><input type="checkbox" onclick="var c=this.checked; var list=document.querySelectorAll('.sel'); for(var i=0;i<list.length;i++){ list[i].checked=c; }"></th>
                    <?php endif; ?>
                    <th class="d-md-none">Info</th>
                    <th>No</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th class="d-none d-md-table-cell">NIC</th>
                    <th class="d-none d-md-table-cell">Status</th>
                    <th class="d-none d-lg-table-cell">Conduct</th>
                    <th class="d-none d-md-table-cell">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($res && mysqli_num_rows($res) > 0): $i = 0;
                    while ($row = mysqli_fetch_assoc($res)): ?>
                      <tr data-sid="<?php echo h($row['student_id']); ?>" data-rowtext="<?php echo h(strtolower(trim(($row['student_id'] ?? '') . ' ' . ($row['student_fullname'] ?? '') . ' ' . ($row['student_email'] ?? '') . ' ' . ($row['student_phone'] ?? '') . ' ' . ($row['student_nic'] ?? '')))); ?>">
                        <?php if ($is_admin): ?>
                          <td class="d-none d-sm-table-cell"><input type="checkbox" class="sel" name="sids[]" value="<?php echo h($row['student_id']); ?>"></td>
                        <?php endif; ?>
                        <td class="d-md-none align-middle">
                          <button type="button" class="btn btn-link p-0 toggle-details" data-target="det-<?php echo h($row['student_id']); ?>" aria-label="Toggle details">
                            <i class="fa fa-chevron-down"></i>
                          </button>
                        </td>
                        <td class="text-muted align-middle"><?php echo ++$i; ?></td>
                        <td><?php echo h($row['student_id']); ?></td>
                        <td>
                          <?php echo h(display_name($row['student_fullname'])); ?>
                          <?php if (empty($row['course_name'])): ?>
                            <span class="badge badge-warning ml-1">No enrollment</span>
                          <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell"><?php echo h($row['student_nic'] ?? ''); ?></td>
                        <td class="d-none d-md-table-cell">
                          <?php
                          $st = $row['student_status'] ?: '';
                          $statusClass = 'secondary';
                          if ($st === 'Active') $statusClass = 'success';
                          elseif ($st === 'Following') $statusClass = 'info';
                          elseif ($st === 'Completed') $statusClass = 'primary';
                          elseif ($st === 'Suspended') $statusClass = 'danger';
                          elseif ($st === 'Dropout') $statusClass = 'dark';
                          ?>
                          <span class="badge badge-<?php echo $statusClass; ?>"><?php echo h($st ?: '—'); ?></span>
                        </td>
                        <td class="d-none d-lg-table-cell">
                          <?php if (!empty($row['student_conduct_accepted_at'])): ?>
                            <span class="badge badge-success">Accepted</span>
                            <small class="text-muted d-block"><?php echo h($row['student_conduct_accepted_at']); ?></small>
                          <?php else: ?>
                            <span class="badge badge-warning">Pending</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-nowrap d-none d-md-table-cell">
                          <?php
                          $viewUrl = $base . '/student/Student_profile.php?Sid=' . urlencode($row['student_id']);
                          $editUrl = $base . '/student/StudentEditAdmin.php?Sid=' . urlencode($row['student_id']);
                          $unifiedUrl = $base . '/student/StudentUnifiedEdit.php?Sid=' . urlencode($row['student_id']);
                          $bankUrl = $base . '/finance/StudentBankDetails.php?sb_student_id=' . urlencode($row['student_id']);
                          ?>
                          <div class="btn-group btn-group-sm flex-wrap" role="group">
                            <?php if ($can_mutate): ?>
                              <a class="btn btn-secondary" title="Unified Edit" href="<?php echo $unifiedUrl; ?>"><i class="fas fa-user-cog"></i></a>
                            <?php endif; ?>
                            <?php if ($can_mutate): ?>
                              <a class="btn btn-success" title="Edit" href="<?php echo $editUrl; ?>"><i class="far fa-edit"></i></a>
                            <?php endif; ?>
                            <a class="btn btn-info" title="View" href="<?php echo $viewUrl; ?>"><i class="fas fa-angle-double-right"></i></a>
                            <?php if ($is_finacc): ?>
                              <a class="btn btn-outline-primary" title="Bank Details" href="<?php echo $bankUrl; ?>"><i class="fa fa-university"></i></a>
                            <?php endif; ?>
                            <?php if ($can_mutate): ?>
                              <?php if (($row['student_status'] ?? '') === 'Inactive'): ?>
                                <button type="submit" name="activate_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-outline-success" onclick="return confirm('Activate <?php echo h($row['student_id']); ?>?');"><i class="fa fa-user-check"></i></button>
                              <?php endif; ?>
                              <?php if (empty($row['student_conduct_accepted_at'])): ?>
                                <button type="submit" name="mark_accept_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-primary" onclick="return confirm('Mark conduct as accepted for <?php echo h($row['student_id']); ?>?');">Accept</button>
                              <?php else: ?>
                                <?php if ($is_admin): ?>
                                  <button type="submit" name="clear_accept_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-secondary" title="Clear conduct acceptance" onclick="return confirm('Clear conduct acceptance for <?php echo h($row['student_id']); ?>?');"><i class="fas fa-eraser"></i></button>
                                <?php endif; ?>
                              <?php endif; ?>
                              <button type="submit" name="delete_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-danger" onclick="return confirm('Inactivate <?php echo h($row['student_id']); ?>?');"><i class="far fa-trash-alt"></i></button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                      <!-- Mobile details row -->
                      <tr class="details-row d-md-none" id="det-<?php echo h($row['student_id']); ?>">
                        <td colspan="<?php echo $is_admin ? 9 : 8; ?>" class="bg-light">
                          <div class="p-2 small">
                            <div><strong>Status:</strong> <span class="badge badge-<?php echo ($row['student_status'] === 'Active' ? 'success' : ($row['student_status'] === 'Inactive' ? 'secondary' : 'info')); ?>"><?php echo h($row['student_status'] ?: '—'); ?></span></div>
                            <div><strong>Conduct:</strong>
                              <?php if (!empty($row['student_conduct_accepted_at'])): ?>
                                <span class="badge badge-success">Accepted</span>
                                <small class="text-muted ml-1"><?php echo h($row['student_conduct_accepted_at']); ?></small>
                              <?php else: ?>
                                <span class="badge badge-warning">Pending</span>
                              <?php endif; ?>
                            </div>
                            <?php if (!empty($row['student_nic'])): ?>
                              <div><strong>NIC:</strong> <?php echo h($row['student_nic']); ?></div>
                            <?php endif; ?>
                            <div class="mt-2">
                              <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Actions">
                                <?php if ($can_mutate): ?>
                                  <a class="btn btn-success" title="Edit" href="<?php echo $editUrl; ?>"><i class="far fa-edit"></i></a>
                                <?php endif; ?>
                                <a class="btn btn-info" title="View" href="<?php echo $viewUrl; ?>"><i class="fas fa-angle-double-right"></i></a>
                                <?php if ($is_finacc): ?>
                                  <a class="btn btn-outline-primary" title="Bank Details" href="<?php echo $bankUrl; ?>"><i class="fa fa-university"></i></a>
                                <?php endif; ?>
                                <?php if ($can_mutate): ?>
                                  <?php if (empty($row['student_conduct_accepted_at'])): ?>
                                    <button type="submit" name="mark_accept_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-primary" onclick="return confirm('Mark conduct as accepted for <?php echo h($row['student_id']); ?>?');">Accept</button>
                                  <?php else: ?>
                                    <?php if ($is_admin): ?>
                                      <button type="submit" name="clear_accept_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-secondary" title="Clear conduct acceptance" onclick="return confirm('Clear conduct acceptance for <?php echo h($row['student_id']); ?>?');"><i class="fas fa-eraser"></i></button>
                                    <?php endif; ?>
                                  <?php endif; ?>
                                  <button type="submit" name="delete_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-danger" onclick="return confirm('Inactivate <?php echo h($row['student_id']); ?>?');"><i class="far fa-trash-alt"></i></button>
                                <?php endif; ?>
                              </div>
                            </div>
                            <?php if (!empty($row['course_name'])): ?>
                              <div><strong>Course:</strong> <?php echo h($row['course_name']); ?></div>
                            <?php elseif (empty($row['course_name'])): ?>
                              <div><strong>Enrollment:</strong> <span class="badge badge-warning">No enrollment</span></div>
                            <?php endif; ?>
                            <?php if (!empty($row['department_name'])): ?>
                              <div><strong>Department:</strong> <?php echo h($row['department_name']); ?></div>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endwhile;
                  else: ?>
                    <tr>
                      <td colspan="<?php echo $is_admin ? 9 : 8; ?>" class="text-center py-5 text-muted">
                        <div><i class="fa fa-user-graduate fa-2x mb-2"></i></div>
                        <div><strong>No students found</strong></div>
                        <div class="small">Try adjusting filters or clearing them to see more results.</div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="p-3 small text-muted border-top">Total: <?php echo (int)$total_count; ?></div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>

<script>
  // ManageStudents: quick search and persistent row expand
  (function() {
    var KEY = 'ms_expanded';

    function loadExpanded() {
      try {
        return JSON.parse(sessionStorage.getItem(KEY) || '[]');
      } catch (e) {
        return [];
      }
    }

    function saveExpanded(list) {
      sessionStorage.setItem(KEY, JSON.stringify(list));
    }

    function addExpanded(sid) {
      var l = loadExpanded();
      if (l.indexOf(sid) === -1) {
        l.push(sid);
        saveExpanded(l);
      }
    }

    function removeExpanded(sid) {
      var l = loadExpanded();
      var i = l.indexOf(sid);
      if (i !== -1) {
        l.splice(i, 1);
        saveExpanded(l);
      }
    }

    // Restore expanded rows on load (with safe CSS.escape fallback)
    document.addEventListener('DOMContentLoaded', function() {
      try {
        var expanded = loadExpanded();
        expanded.forEach(function(sid) {
          try {
            var det = document.getElementById('det-' + sid);
            if (det) { det.classList.add('show'); }
            // Flip icon if the toggle button exists
            var selectorSid = sid;
            if (window.CSS && typeof window.CSS.escape === 'function') {
              selectorSid = CSS.escape(sid);
            }
            var toggleBtn = document.querySelector('tr[data-sid="' + selectorSid + '"] .toggle-details i');
            if (toggleBtn) {
              toggleBtn.classList.remove('fa-chevron-down');
              toggleBtn.classList.add('fa-chevron-up');
            }
          } catch(_inner){}
        });
      } catch(_e){}
    });

    // Toggle mobile details rows and persist state (with closest fallback)
    document.addEventListener('click', function(e) {
      try {
        var target = e.target;
        var btn = target.closest ? target.closest('.toggle-details') : null;
        if (!btn) {
          // Fallback: manual traversal for older browsers
          var n = target;
          while (n && n !== document) {
            if (n.classList && n.classList.contains('toggle-details')) { btn = n; break; }
            n = n.parentNode;
          }
        }
        if (!btn) return;
        var id = btn.getAttribute('data-target');
        if (!id) return;
        var row = document.getElementById(id);
        if (!row) return;
        if (row.classList) {
          row.classList.toggle('show');
        } else {
          // Very old fallback
          row.style.display = (row.style.display === 'table-row') ? 'none' : 'table-row';
        }
        // Toggle icon
        var icon = btn.querySelector ? btn.querySelector('i') : null;
        if (icon && icon.classList) {
          icon.classList.toggle('fa-chevron-down');
          icon.classList.toggle('fa-chevron-up');
        }
        // Persist by sid (data-sid on main row)
        var tr = btn.closest ? btn.closest('tr[data-sid]') : null;
        if (!tr) {
          var p = btn.parentNode;
          while (p && p !== document && !tr) {
            if (p.getAttribute && p.getAttribute('data-sid')) { tr = p; break; }
            p = p.parentNode;
          }
        }
        var sid = tr ? tr.getAttribute('data-sid') : null;
        if (sid) {
          if ((row.classList && row.classList.contains('show')) || row.style.display === 'table-row') addExpanded(sid);
          else removeExpanded(sid);
        }
      } catch(_e){}
    });

    // Quick search filter (desktop and mobile)
    function applyQuickSearch(q) {
      q = (q || '').toLowerCase().trim();
      var rows = document.querySelectorAll('#studentsTable tbody tr[data-rowtext]');
      for (var i = 0; i < rows.length; i++) {
        var tr = rows[i];
        var text = tr.getAttribute('data-rowtext') || '';
        var match = !q || text.indexOf(q) !== -1;
        tr.style.display = match ? '' : 'none';
        // Also hide/show its details row if exists
        var sid = tr.getAttribute('data-sid');
        if (sid) {
          var det = document.getElementById('det-' + sid);
          if (det) {
            det.style.display = match ? ((det.classList && det.classList.contains('show')) ? 'table-row' : 'none') : 'none';
          }
        }
      }
    }

    var qs = document.getElementById('quickSearch');
    if (qs) {
      qs.addEventListener('input', function() { applyQuickSearch(qs.value); });
    }
    var qsm = document.getElementById('quickSearchMobile');
    if (qsm) {
      qsm.addEventListener('input', function() { applyQuickSearch(qsm.value); });
    }
  })();
</script>