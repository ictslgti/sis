<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// View-only access for Admin, Director, or SAO
require_roles(['ADM', 'DIR', 'SAO']);
$base = defined('APP_BASE') ? APP_BASE : '';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function display_name($name) {
  $name = trim((string)$name);
  if ($name === '') return '';
  $parts = preg_split('/\s+/', $name);
  $out = [];
  foreach ($parts as $p) {
    if (strpos($p, '.') !== false || (preg_match('/^[A-Z]+$/', $p) && strlen($p) <= 4)) { $out[] = strtoupper($p); continue; }
    $lower = mb_strtolower($p, 'UTF-8');
    $out[] = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
  }
  return implode(' ', $out);
}

// Query: list all students (no filters)
$baseSql = "SELECT s.student_id, s.student_fullname, s.student_email, s.student_phone, s.student_status, s.student_gender,
                   s.student_conduct_accepted_at,
                   e.course_id, c.course_name, d.department_id, d.department_name
            FROM student s
            LEFT JOIN student_enroll e ON e.student_id = s.student_id
            LEFT JOIN course c ON c.course_id = e.course_id
            LEFT JOIN department d ON d.department_id = c.department_id";
$sqlList = $baseSql . ' GROUP BY s.student_id ORDER BY s.student_id ASC LIMIT 1000';
$res = mysqli_query($con, $sqlList);
$total_count = ($res ? mysqli_num_rows($res) : 0);

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
</div>

<div class="container-fluid px-0 px-sm-2 px-md-4">
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm border-0 mb-3 first-section-card">
        <div class="card-header d-flex align-items-center">
          <div class="font-weight-semibold"><i class="fa fa-users mr-1"></i> Students</div>
        </div>
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
      </style>

      <div class="card shadow-sm border-0">
        <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-md-between">
          <div class="font-weight-semibold mb-2 mb-md-0"><i class="fa fa-users mr-1"></i> Students <span class="badge badge-secondary ml-2"><?php echo (int)$total_count; ?></span></div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive table-scroll" style="border-top-left-radius:.25rem;border-top-right-radius:.25rem;">
            <table id="studentsTable" class="table table-striped table-bordered table-hover table-sm table-sticky mb-0">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Student ID</th>
                  <th>Full Name</th>
                  <th class="d-none d-lg-table-cell">Email</th>
                  <th class="d-none d-lg-table-cell">Phone</th>
                  <th>Status</th>
                  <th class="d-none d-lg-table-cell">Gender</th>
                  <th class="d-none d-xl-table-cell">Course</th>
                  <th class="d-none d-xl-table-cell">Department</th>
                  <th class="d-none d-xl-table-cell">Conduct</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($res && mysqli_num_rows($res) > 0): $i = 0; while ($row = mysqli_fetch_assoc($res)): ?>
                  <tr>
                    <td class="text-muted align-middle"><?php echo ++$i; ?></td>
                    <td><?php echo h($row['student_id']); ?></td>
                    <td><?php echo h(display_name($row['student_fullname'])); ?></td>
                    <td class="d-none d-lg-table-cell"><?php echo h($row['student_email'] ?? ''); ?></td>
                    <td class="d-none d-lg-table-cell"><?php echo h($row['student_phone'] ?? ''); ?></td>
                    <td>
                      <?php $st = $row['student_status'] ?: ''; $statusClass = 'secondary';
                        if ($st === 'Active') $statusClass = 'success';
                        elseif ($st === 'Following') $statusClass = 'info';
                        elseif ($st === 'Completed') $statusClass = 'primary';
                        elseif ($st === 'Suspended') $statusClass = 'danger';
                      ?>
                      <span class="badge badge-<?php echo $statusClass; ?>"><?php echo h($st ?: '—'); ?></span>
                    </td>
                    <td class="d-none d-lg-table-cell"><?php echo h($row['student_gender'] ?? ''); ?></td>
                    <td class="d-none d-xl-table-cell"><?php echo h($row['course_name'] ?? ''); ?></td>
                    <td class="d-none d-xl-table-cell"><?php echo h($row['department_name'] ?? ''); ?></td>
                    <td class="d-none d-xl-table-cell">
                      <?php if (!empty($row['student_conduct_accepted_at'])): ?>
                        <span class="badge badge-success">Accepted</span>
                        <small class="text-muted d-block"><?php echo h($row['student_conduct_accepted_at']); ?></small>
                      <?php else: ?>
                        <span class="badge badge-warning">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                  <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                      <div><i class="fa fa-user-graduate fa-2x mb-2"></i></div>
                      <div><strong>No students found</strong></div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
