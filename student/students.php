<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Access control: Admin, Director (DIR), or SAO can view
require_roles(['ADM', 'DIR', 'SAO']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$is_dir   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'DIR';
$is_sao   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO';
// Mutations allowed for Admin and SAO; DIR is strictly view-only
$can_mutate = ($is_admin || $is_sao);

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Display helper: Title-case names while preserving initials like H.A.R.C.
function display_name($name)
{
  $name = trim((string)$name);
  if ($name === '') return '';
  $parts = preg_split('/\s+/', $name);
  $out = [];
  foreach ($parts as $p) {
    if (strpos($p, '.') !== false || (preg_match('/^[A-Z]+$/', $p) && strlen($p) <= 4)) {
      $out[] = strtoupper($p);
      continue;
    }
    $lower = mb_strtolower($p, 'UTF-8');
    $out[] = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
  }
  return implode(' ', $out);
}

$messages = [];
$errors = [];

// Handle actions (single delete, bulk inactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$can_mutate) {
    http_response_code(403);
    echo 'Forbidden: View-only access';
    exit;
  }
  // Single delete (set Inactive)
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
      $errors[] = 'DB error (single delete)';
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
  // Redirect back preserving current filters to avoid resubmission
  $_SESSION['flash_messages'] = $messages;
  $_SESSION['flash_errors'] = $errors;
  $qsBack = $_GET ?? [];
  $redir = $base . '/student/students.php';
  if (!empty($qsBack)) { $redir .= '?' . http_build_query($qsBack); }
  header('Location: ' . $redir);
  exit;
}

// Flash messages (from redirects)
if (!empty($_SESSION['flash_messages'])) {
  $messages = $_SESSION['flash_messages'];
  unset($_SESSION['flash_messages']);
}
if (!empty($_SESSION['flash_errors'])) {
  $errors = $_SESSION['flash_errors'];
  unset($_SESSION['flash_errors']);
}

// Filters (IGNORED: page lists all students regardless of filters)
$fyear   = '';
$fstatus = '';
$fconduct = '';
$fdept   = '';
$fcourse = '';
$fgender = '';

// Do not default academic year; list all students

// Build base SQL and filter conditions
$joinYearCond = '';
if ($fyear !== '') {
  $safeYear = mysqli_real_escape_string($con, $fyear);
  $joinYearCond = " AND (REPLACE(TRIM(e.academic_year),' ','') LIKE CONCAT(REPLACE(TRIM('$safeYear'),' ',''),'%')
                         OR LEFT(TRIM(e.academic_year), 9) = LEFT(TRIM('$safeYear'), 9))";
}

$baseSql = "SELECT s.student_id, s.student_fullname, s.student_email, s.student_phone, s.student_status, s.student_gender,
               s.student_conduct_accepted_at,
               e.course_id, c.course_name, d.department_id, d.department_name
        FROM student s
        LEFT JOIN student_enroll e ON e.student_id = s.student_id$joinYearCond
        LEFT JOIN course c ON c.course_id = e.course_id
        LEFT JOIN department d ON d.department_id = c.department_id";

$where = [];
if ($fstatus !== '') { $where[] = "s.student_status = '" . mysqli_real_escape_string($con, $fstatus) . "'"; }
if ($fdept   !== '') { $where[] = "d.department_id = '" . mysqli_real_escape_string($con, $fdept) . "'"; }
if ($fcourse !== '') { $where[] = "c.course_id = '" . mysqli_real_escape_string($con, $fcourse) . "'"; }
if ($fgender !== '') { $where[] = "s.student_gender = '" . mysqli_real_escape_string($con, $fgender) . "'"; }
if ($fconduct === 'accepted') { $where[] = 's.student_conduct_accepted_at IS NOT NULL'; }
elseif ($fconduct === 'pending') { $where[] = 's.student_conduct_accepted_at IS NULL'; }

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
$requireEnrollForYear = ($fyear !== '');
$sqlWhereFinal = $whereSql;
if ($requireEnrollForYear) { $sqlWhereFinal .= ($sqlWhereFinal ? ' AND ' : ' WHERE ') . ' e.student_id IS NOT NULL'; }

$sqlList   = $baseSql . $sqlWhereFinal . ' GROUP BY s.student_id ORDER BY s.student_id ASC LIMIT 500';
$sqlExport = $baseSql . $sqlWhereFinal . ' GROUP BY s.student_id ORDER BY s.student_id ASC';
$res = mysqli_query($con, $sqlList);
$total_count = ($res ? mysqli_num_rows($res) : 0);

// Export: download CSV
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
  $filename = 'students_' . date('Ymd_His') . '.csv';
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename=' . $filename);
  header('Pragma: no-cache');
  header('Expires: 0');
  if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
  echo "\xEF\xBB\xBF"; // UTF-8 BOM
  $out = fopen('php://output', 'w');
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

// Export: save CSV to server
if (isset($_GET['export']) && $_GET['export'] === 'excel_save') {
  $dir = __DIR__ . '/../exports';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $filename = 'students_' . date('Ymd_His') . '.csv';
  $path = $dir . '/' . $filename;
  $ok = false;
  if ($fp = fopen($path, 'w')) {
    fputcsv($fp, ['Student ID', 'Full Name', 'Email', 'Phone', 'Status', 'Gender', 'Course', 'Department', 'Conduct Accepted At']);
    if ($qr = mysqli_query($con, $sqlExport)) {
      while ($r = mysqli_fetch_assoc($qr)) {
        fputcsv($fp, [
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
    fclose($fp);
    $ok = true;
  }
  if ($ok) { $_SESSION['flash_messages'][] = 'Export saved to exports/' . $filename; }
  else { $_SESSION['flash_errors'][] = 'Failed to create export file at exports/' . $filename; }
  $qsBack = $_GET; unset($qsBack['export']);
  $redir = $base . '/student/students.php';
  if (!empty($qsBack)) { $redir .= '?' . http_build_query($qsBack); }
  header('Location: ' . $redir);
  exit;
}

// Load dropdown data
$departments = [];
if ($r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $departments[] = $row; }
  mysqli_free_result($r);
}
$courses = [];
if ($r = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $courses[] = $row; }
  mysqli_free_result($r);
}
$years = [];
if ($r = mysqli_query($con, "SELECT academic_year FROM academic ORDER BY academic_year DESC")) {
  while ($row = mysqli_fetch_assoc($r)) { $years[] = $row['academic_year']; }
  mysqli_free_result($r);
}

$title = 'Students | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid px-0 px-sm-1 px-md-4">
  <div class="row align-items-center mt-2 mb-2 mt-sm-1 mb-sm-3">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm mb-1">
          <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
          <li class="breadcrumb-item active" aria-current="page">Students</li>
        </ol>
      </nav>
      <h4 class="d-flex align-items-center page-title">
        <i class="fas fa-users text-primary mr-2"></i>
        Students (View Only)
      </h4>
    </div>
  </div>

  <?php foreach (($messages ?? []) as $m): ?>
    <div class="alert alert-success"><?php echo h($m); ?></div>
  <?php endforeach; ?>
  <?php foreach (($errors ?? []) as $e): ?>
    <div class="alert alert-danger"><?php echo h($e); ?></div>
  <?php endforeach; ?>
</div>

<div class="container-fluid px-0 px-sm-2 px-md-4">
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0 mb-3 first-section-card">
        <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center">
          <div class="font-weight-semibold"><i class="fa fa-search mr-1"></i> Search</div>
          <div class="d-flex align-items-center w-100 w-md-auto mt-2 mt-md-0 ml-md-auto justify-content-between justify-content-md-end">
            <div class="d-none d-md-block mr-2" style="width: 260px;">
              <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" id="quickSearch" class="form-control" placeholder="Quick search... (ID, name, email, phone)">
              </div>
            </div>
            <!-- Filters UI disabled -->
          </div>
        </div>
        <!-- Filters UI disabled on this page -->
      </div>

      <style>
        .table.table-sm td, .table.table-sm th { padding: .4rem .5rem; }
        @media (max-width: 575.98px) {
          .breadcrumb { margin-bottom: .35rem; padding: .25rem .5rem; }
          .page-title { font-size: 1.15rem; line-height: 1.25; }
          .page-title i { margin-right: .35rem !important; font-size: 1rem; }
          .first-section-card { margin-top: .5rem !important; }
          .table td, .table th { white-space: nowrap; }
        }
        .table-sticky thead th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
        .table-scroll { max-height: 70vh; overflow-y: auto; }
        .btn-group.flex-wrap>.btn { margin: 2px; }
      </style>
      <script>
        (function() {
          var dept = document.getElementById('fdept');
          var course = document.getElementById('fcourse');
          if (!dept || !course) return;
          var all = Array.prototype.slice.call(course.options).map(function(o){ return {value:o.value, text:o.text, dept:o.getAttribute('data-dept')}; });
          function apply(){
            var d = dept.value; var keepSelected = course.value;
            while (course.options.length) course.remove(0);
            var opt = document.createElement('option'); opt.value=''; opt.text='-- Any --'; course.add(opt);
            all.forEach(function(it){ if(!it.value) return; if(!d || it.dept===d){ var o=document.createElement('option'); o.value=it.value; o.text=it.text; course.add(o);} });
            if (keepSelected) { for (var i=0;i<course.options.length;i++){ if(course.options[i].value===keepSelected){ course.selectedIndex=i; break; } } }
          }
          dept.addEventListener('change', apply); apply();
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
          <?php $qs = $_GET; $qs['export'] = 'excel'; $exportUrl = $base . '/student/students.php?' . http_build_query($qs); ?>
          <a href="<?php echo h($exportUrl); ?>" class="btn btn-success btn-sm"><i class="fa fa-file-excel mr-1"></i> Export Excel</a>
          <?php $qs2 = $_GET; $qs2['export'] = 'excel_save'; $exportSaveUrl = $base . '/student/students.php?' . http_build_query($qs2); ?>
          <a href="<?php echo h($exportSaveUrl); ?>" class="btn btn-outline-success btn-sm ml-2"><i class="fa fa-save mr-1"></i> Export to File</a>
          </div>
        </div>

      <div class="card shadow-sm border-0">
        <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-md-between">
          <div class="font-weight-semibold mb-2 mb-md-0"><i class="fa fa-users mr-1"></i> Students <span class="badge badge-secondary ml-2"><?php echo (int)$total_count; ?></span></div>
          <div class="d-md-none w-100">
            <div class="input-group input-group-sm">
              <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-search"></i></span></div>
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
                <?php if ($res && mysqli_num_rows($res) > 0): $i = 0; while ($row = mysqli_fetch_assoc($res)): ?>
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
                    <td><?php echo h(display_name($row['student_fullname'])); ?></td>
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
                          <button type="submit" name="delete_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-danger" onclick="return confirm('Inactivate <?php echo h($row['student_id']); ?>?');"><i class="far fa-trash-alt"></i></button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
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
                          <?php
                            $viewUrl = $base . '/student/Student_profile.php?Sid=' . urlencode($row['student_id']);
                            $editUrl = $base . '/student/StudentEditAdmin.php?Sid=' . urlencode($row['student_id']);
                          ?>
                          <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Actions">
                            <?php if ($can_mutate): ?>
                              <a class="btn btn-success" title="Edit" href="<?php echo $editUrl; ?>"><i class="far fa-edit"></i></a>
                            <?php endif; ?>
                            <a class="btn btn-info" title="View" href="<?php echo $viewUrl; ?>"><i class="fas fa-angle-double-right"></i></a>
                            <?php if ($can_mutate): ?>
                              <button type="submit" name="delete_sid" value="<?php echo h($row['student_id']); ?>" class="btn btn-danger" onclick="return confirm('Inactivate <?php echo h($row['student_id']); ?>?');"><i class="far fa-trash-alt"></i></button>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php if (!empty($row['course_name'])): ?>
                          <div><strong>Course:</strong> <?php echo h($row['course_name']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($row['department_name'])): ?>
                          <div><strong>Department:</strong> <?php echo h($row['department_name']); ?></div>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
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
        </div>
      </div>
      </form>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
