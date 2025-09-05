<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Access control: Admin, Director (DIR), or SAO.
require_roles(['ADM', 'DIR', 'SAO']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$is_dir   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'DIR';
$is_sao   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO';
// Mutations allowed for Admin and SAO; DIR is strictly view-only
$can_mutate = ($is_admin || $is_sao);

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

// Ensure conduct column exists (no-op if already there)
@mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_conduct_accepted_at` DATETIME NULL");

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
        $messages[] = "Student $sid set to Inactive";
      } else {
        $errors[] = "No changes for $sid";
      }
    } else {
      $errors[] = 'DB error (single)';
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
          $messages[] = ($affected > 0) ? 'Selected students set to Inactive' : 'No rows updated';
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

// For DIR (view-only), restrict to Active students regardless of requested filter
if ($is_dir) {
  $fstatus = 'Active';
}

$where = [];
$params = [];
// Base SQL for both list and export
$baseSql = "SELECT 
              `s`.`student_id`,
              `s`.`student_fullname`,
              `s`.`student_email`,
              `s`.`student_phone`,
              `s`.`student_status`,
              `s`.`student_gender`,
              `s`.`student_conduct_accepted_at`,
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
  $where[] = "d.department_id = '" . mysqli_real_escape_string($con, $fdept) . "'";
}
if ($fcourse !== '') {
  $where[] = "c.course_id = '" . mysqli_real_escape_string($con, $fcourse) . "'";
}
if ($fgender !== '') {
  $where[] = "s.student_gender = '" . mysqli_real_escape_string($con, $fgender) . "'";
}
if ($fconduct === 'accepted') {
  $where[] = "s.student_conduct_accepted_at IS NOT NULL";
} elseif ($fconduct === 'pending') {
  $where[] = "s.student_conduct_accepted_at IS NULL";
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$sqlWhereFinal = $whereSql; // No Academic Year constraint

// Base ORDER/GROUP for list and export (no pagination)
$groupOrder = ' GROUP BY s.student_id ORDER BY s.student_id ASC';

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
  fputcsv($out, ['Student ID', 'Full Name', 'Email', 'Phone', 'Status', 'Gender', 'Course', 'Department', 'Conduct Accepted At']);
  if ($qr = mysqli_query($con, $sqlExport)) {
    while ($r = mysqli_fetch_assoc($qr)) {
      fputcsv($out, [
        $r['student_id'],
        display_name($r['student_fullname'] ?? ''),
        $r['student_email'] ?? '',
        $r['student_phone'] ?? '',
        $r['student_status'] ?? '',
        $r['student_gender'] ?? '',
        $r['course_name'] ?? '',
        $r['department_name'] ?? '',
        $r['student_conduct_accepted_at'] ?? ''
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

// No Academic Year dropdown

// Include standard head and menu to load CSS/JS
$title = 'Manage Students | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid px-0 px-sm-1 px-md-4">
  <div class="row align-items-center mt-2 mb-2 mt-sm-1 mb-sm-3">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm mb-1">
          <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="../student/ImportStudentEnroll.php">Import & Enroll</a></li>
          <li class="breadcrumb-item active" aria-current="page">Students</a></li>

        </ol>
      </nav>
      <h4 class="d-flex align-items-center page-title">
        <i class="fas fa-users text-primary mr-2"></i>
        Manage Students
      </h4>
    </div>
  </div>

  <!-- Flash messages rendered below -->
</div>
<div class="container-fluid px-0 px-sm-2 px-md-4">
  <div class="row">
    <div class="col-12">


      <?php foreach ($messages as $m): ?>
        <div class="alert alert-success"><?php echo h($m); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?php echo h($e); ?></div>
      <?php endforeach; ?>

      <!-- Filters: Modern card layout with responsive grid -->
      <div class="card shadow-sm border-0 mb-3 first-section-card">
        <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center">
          <div class="font-weight-semibold"><i class="fa fa-sliders-h mr-1"></i> Filters</div>
          <div class="d-flex align-items-center w-100 w-md-auto mt-2 mt-md-0 ml-md-auto justify-content-between justify-content-md-end">
            <div class="d-none d-md-block mr-2" style="width: 260px;">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" id="quickSearch" class="form-control" placeholder="Quick search... (ID, name, email, phone)">
              </div>
            </div>
            <button class="btn btn-sm btn-outline-secondary d-md-none ml-auto" type="button" data-toggle="collapse" data-target="#filtersBox" aria-expanded="false" aria-controls="filtersBox">
              Show/Hide
            </button>
          </div>
        </div>
        <div id="filtersBox" class="collapse show">
          <div class="card-body">
            <form class="mb-0" method="get" action="">
              <div class="form-row">
                <div class="form-group col-12 col-md-4">
                  <label for="fdept" class="small text-muted mb-1">Department</label>
                  <select id="fdept" name="department_id" class="form-control">
                    <option value="">-- Any --</option>
                    <?php foreach ($departments as $d): ?>
                      <option value="<?php echo h($d['department_id']); ?>" <?php echo ($fdept === $d['department_id'] ? 'selected' : ''); ?>><?php echo h($d['department_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-4">
                  <label for="fcourse" class="small text-muted mb-1">Course</label>
                  <select id="fcourse" name="course_id" class="form-control">
                    <option value="">-- Any --</option>
                    <?php foreach ($courses as $c): ?>
                      <option value="<?php echo h($c['course_id']); ?>" data-dept="<?php echo h($c['department_id']); ?>" <?php echo ($fcourse === $c['course_id'] ? 'selected' : ''); ?>><?php echo h($c['course_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-3">
                  <label for="fgender" class="small text-muted mb-1">Gender</label>
                  <select id="fgender" name="gender" class="form-control">
                    <option value="">-- Any --</option>
                    <?php foreach (["Male", "Female", "Other"] as $g): ?>
                      <option value="<?php echo h($g); ?>" <?php echo ($fgender === $g ? 'selected' : ''); ?>><?php echo h($g); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-3">
                  <label for="fstatus" class="small text-muted mb-1">Status</label>
                  <select id="fstatus" name="status" class="form-control" <?php echo $is_dir ? 'disabled' : ''; ?>>
                    <option value="">-- Any --</option>
                    <?php foreach (["Active", "Inactive", "Following", "Completed", "Suspended"] as $st): ?>
                      <?php if (!$is_dir || $st === 'Active'): ?>
                        <option value="<?php echo h($st); ?>" <?php echo ($fstatus === $st ? 'selected' : ''); ?>><?php echo h($st); ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($is_dir): ?>
                    <input type="hidden" name="status" value="Active">
                  <?php endif; ?>
                </div>
                <div class="form-group col-12 col-md-3">
                  <label for="fconduct" class="small text-muted mb-1">Conduct</label>
                  <select id="fconduct" name="conduct" class="form-control">
                    <option value="">-- Any --</option>
                    <option value="accepted" <?php echo ($fconduct === 'accepted' ? 'selected' : ''); ?>>Accepted</option>
                    <option value="pending" <?php echo ($fconduct === 'pending' ? 'selected' : ''); ?>>Pending</option>
                  </select>
                </div>
                <div class="form-group col-12 col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <style>
        /* Compact table spacing */
        .table.table-sm td,
        .table.table-sm th {
          padding: .4rem .5rem;
        }

        /* On very small screens, avoid horizontal overflow on key columns */
        @media (max-width: 575.98px) {
          .breadcrumb { margin-bottom: .35rem; padding: .25rem .5rem; }
          .page-title { font-size: 1.15rem; line-height: 1.25; }
          .page-title i { margin-right: .35rem !important; font-size: 1rem; }
          .first-section-card { margin-top: .5rem !important; }

          .table td,
          .table th {
            white-space: nowrap;
          }
        }

        /* Sticky header within scroll container */
        .table-sticky thead th {
          position: sticky;
          top: 0;
          background: #f8f9fa;
          z-index: 2;
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
      </style>
      <script>
        // Client-side filter: limit course options by selected department
        (function() {
          var dept = document.getElementById('fdept');
          var course = document.getElementById('fcourse');
          if (!dept || !course) return;
          var all = Array.prototype.slice.call(course.options).map(function(o) {
            return {
              value: o.value,
              text: o.text,
              dept: o.getAttribute('data-dept')
            };
          });

          function apply() {
            var d = dept.value;
            var keepSelected = course.value;
            // Rebuild
            while (course.options.length) course.remove(0);
            var opt = document.createElement('option');
            opt.value = '';
            opt.text = '-- Any --';
            course.add(opt);
            all.forEach(function(it) {
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
          }
          dept.addEventListener('change', apply);
          // Initialize on load
          apply();
        })();
      </script>

      <form method="post" <?php echo $is_admin ? "onsubmit=\"return confirm('Inactivate selected students?');\"" : 'onsubmit="return false;"'; ?>>
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-2">
          <div class="mb-2 mb-md-0">
            <?php if ($is_admin): ?>
              <button type="submit" name="bulk_action" value="bulk_inactivate" class="btn btn-danger btn-sm"><i class="fa fa-user-times mr-1"></i> Bulk Inactivate</button>
            <?php endif; ?>
          </div>
          <div class="mb-2 mb-md-0">
            <a href="<?php echo $base; ?>/student/ManageStudents.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-redo mr-1"></i> Clear Filters</a>
            <?php $qs = $_GET; $qs['export'] = 'excel'; $exportUrl = $base . '/student/ManageStudents.php?' . http_build_query($qs); ?>
            <a href="<?php echo h($exportUrl); ?>" class="btn btn-success btn-sm ml-2"><i class="fa fa-file-excel mr-1"></i> Export Excel</a>
          </div>
        </div>

        <!-- Results card -->
        <div class="card shadow-sm border-0">
          <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-md-between">
            <div class="font-weight-semibold mb-2 mb-md-0"><i class="fa fa-users mr-1"></i> Students <span class="badge badge-secondary ml-2"><?php echo (int)$total_count; ?></span></div>
            <div class="d-md-none w-100">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" id="quickSearchMobile" class="form-control" placeholder="Quick search (ID, name, email, phone)">
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive table-scroll" style="border-top-left-radius:.25rem;border-top-right-radius:.25rem;">
              <table id="studentsTable" class="table table-striped table-bordered table-hover table-sm table-sticky mb-0">
                <thead>
                  <tr>
                    <?php if ($is_admin): ?>
                      <th class="d-none d-sm-table-cell"><input type="checkbox" onclick="var c=this.checked; var list=document.querySelectorAll('.sel'); for(var i=0;i<list.length;i++){ list[i].checked=c; }"></th>
                    <?php endif; ?>
                    <th class="d-md-none">Info</th>
                    <th>No</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th class="d-none d-md-table-cell">Status</th>
                    <th class="d-none d-lg-table-cell">Conduct</th>
                    <th class="d-none d-md-table-cell">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($res && mysqli_num_rows($res) > 0): $i = 0;
                    while ($row = mysqli_fetch_assoc($res)): ?>
                      <tr data-sid="<?php echo h($row['student_id']); ?>" data-rowtext="<?php echo h(strtolower(trim(($row['student_id'] ?? '') . ' ' . ($row['student_fullname'] ?? '') . ' ' . ($row['student_email'] ?? '') . ' ' . ($row['student_phone'] ?? '')))); ?>">
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
                        <td class="d-none d-md-table-cell">
                          <?php
                          $st = $row['student_status'] ?: '';
                          $statusClass = 'secondary';
                          if ($st === 'Active') $statusClass = 'success';
                          elseif ($st === 'Following') $statusClass = 'info';
                          elseif ($st === 'Completed') $statusClass = 'primary';
                          elseif ($st === 'Suspended') $statusClass = 'danger';
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
                          ?>
                          <div class="btn-group btn-group-sm flex-wrap" role="group">
                            <?php if ($can_mutate): ?>
                              <a class="btn btn-success" title="Edit" href="<?php echo $editUrl; ?>"><i class="far fa-edit"></i></a>
                            <?php endif; ?>
                            <a class="btn btn-info" title="View" href="<?php echo $viewUrl; ?>"><i class="fas fa-angle-double-right"></i></a>
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
                        </td>
                      </tr>
                      <!-- Mobile details row -->
                      <tr class="details-row d-md-none" id="det-<?php echo h($row['student_id']); ?>">
                        <td colspan="<?php echo $is_admin ? 8 : 7; ?>" class="bg-light">
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
                            <div class="mt-2">
                              <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Actions">
                                <?php if ($can_mutate): ?>
                                  <a class="btn btn-success" title="Edit" href="<?php echo $editUrl; ?>"><i class="far fa-edit"></i></a>
                                <?php endif; ?>
                                <a class="btn btn-info" title="View" href="<?php echo $viewUrl; ?>"><i class="fas fa-angle-double-right"></i></a>
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
                      <td colspan="<?php echo $is_admin ? 8 : 7; ?>" class="text-center py-5 text-muted">
                        <div><i class="fa fa-user-graduate fa-2x mb-2"></i></div>
                        <div><strong>No students found</strong></div>
                        <div class="small">Try adjusting filters or clearing them to see more results.</div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="p-2 small text-muted">Total: <?php echo (int)$total_count; ?></div>
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