<?php
// Student Gallery (ADM only)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if ($role !== 'ADM') {
  echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';
$title = 'Student Photo Gallery | SLGTI';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Optional search
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
// New filters
$filterDept = isset($_GET['department_id']) ? trim((string)$_GET['department_id']) : '';
$filterProv = isset($_GET['province']) ? trim((string)$_GET['province']) : '';
$filterDist = isset($_GET['district']) ? trim((string)$_GET['district']) : '';

$where = '';
$params = [];
$types = '';
if ($q !== '') {
  $where = "WHERE s.student_fullname LIKE CONCAT('%', ?, '%') OR s.student_id LIKE CONCAT('%', ?, '%')";
  $params[] = $q; $params[] = $q; $types .= 'ss';
}
// Append filters
if ($filterDept !== '') {
  $where .= ($where === '' ? 'WHERE ' : ' AND ') . ' d.department_id = ? ';
  $params[] = $filterDept; $types .= 's';
}
if ($filterProv !== '') {
  $where .= ($where === '' ? 'WHERE ' : ' AND ') . ' COALESCE(s.student_provice,\'\') = ? ';
  $params[] = $filterProv; $types .= 's';
}
if ($filterDist !== '') {
  $where .= ($where === '' ? 'WHERE ' : ' AND ') . ' COALESCE(s.student_district,\'\') = ? ';
  $params[] = $filterDist; $types .= 's';
}

// Build query: latest enrollment (if any) to get department, else NULL
$sql = "SELECT s.student_id, s.student_fullname, s.student_profile_img, d.department_name
        FROM student s
        LEFT JOIN (
          SELECT se1.student_id, se1.course_id
          FROM student_enroll se1
          INNER JOIN (
            SELECT student_id, MAX(COALESCE(student_enroll_date, '0000-00-00')) AS last_dt
            FROM student_enroll
            GROUP BY student_id
          ) last ON last.student_id = se1.student_id AND COALESCE(se1.student_enroll_date, '0000-00-00') = last.last_dt
        ) se ON se.student_id = s.student_id
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        $where
        ORDER BY s.student_fullname";

$rows = [];
if ($stmt = mysqli_prepare($con, $sql)) {
  if ($types !== '') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  while ($rs && ($r = mysqli_fetch_assoc($rs))) { $rows[] = $r; }
  mysqli_stmt_close($stmt);
}

// Build filter option lists
$departments = [];
if ($dres = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name')) {
  while ($dr = mysqli_fetch_assoc($dres)) { $departments[] = $dr; }
  mysqli_free_result($dres);
}
$provinces = [];
if ($pres = mysqli_query($con, "SELECT DISTINCT COALESCE(NULLIF(TRIM(student_provice), ''),'') AS v FROM student ORDER BY v")) {
  while ($pr = mysqli_fetch_assoc($pres)) { if ($pr['v'] !== '') $provinces[] = $pr['v']; }
  mysqli_free_result($pres);
}
$districts = [];
if ($dres2 = mysqli_query($con, "SELECT DISTINCT COALESCE(NULLIF(TRIM(student_district), ''),'') AS v FROM student ORDER BY v")) {
  while ($dr2 = mysqli_fetch_assoc($dres2)) { if ($dr2['v'] !== '') $districts[] = $dr2['v']; }
  mysqli_free_result($dres2);
}

// Helpers
function gallery_placeholder(): string {
  return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300"><rect width="100%" height="100%" fill="#f0f0f0"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9aa0a6" font-family="Arial" font-size="16">No Photo</text></svg>');
}

function is_probably_blob(?string $val): bool {
  if ($val === null) return false;
  // If contains many non-printable characters or is very long, treat as blob
  if (strlen($val) > 1024) return true;
  $nonPrintable = preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $val);
  return $nonPrintable === 1;
}

function student_img_src_fs(array $row, string $baseWeb, string $baseFs): string {
  $sid = $row['student_id'] ?? '';
  $sp  = $row['student_profile_img'] ?? '';

  // 1) If DB stores a relative path like 'img/student_profile/ABC.jpg', prefer it
  if (is_string($sp) && $sp !== '' && !is_probably_blob($sp)) {
    $raw = trim($sp);
    // If it's already a data URI, return as-is
    if (stripos($raw, 'data:image') === 0) {
      return $raw;
    }
    // Normalize slashes and strip query/hash (non-regex to avoid delimiter issues)
    $raw = str_replace('\\', '/', $raw);
    $qPos = strpos($raw, '?');
    $hPos = strpos($raw, '#');
    $cutPos = false;
    if ($qPos !== false && $hPos !== false) { $cutPos = min($qPos, $hPos); }
    elseif ($qPos !== false) { $cutPos = $qPos; }
    elseif ($hPos !== false) { $cutPos = $hPos; }
    if ($cutPos !== false) { $raw = substr($raw, 0, $cutPos); }
    // Remove leading ./ and ../ segments
    $raw = preg_replace('#^(?:\./|\.\./)+#', '', $raw);
    // If absolute FS path, take basename
    if (preg_match('#^[a-zA-Z]:/#', $raw) || strpos($raw, '/www/sis/') !== false) {
      $raw = basename($raw);
    }
    // If it contains student_profile, extract filename after it
    if (stripos($raw, 'student_profile/') !== false) {
      $pos = stripos($raw, 'student_profile/');
      $raw = substr($raw, $pos + strlen('student_profile/'));
    }
    // If path contains directories, reduce to filename
    if (strpos($raw, '/') !== false) {
      $raw = basename($raw);
    }
    // Now $raw should be a filename like STU001.jpg
    if ($raw !== '') {
      // Check only the correct folder on disk
      $fs1 = rtrim($baseFs, '/\\') . DIRECTORY_SEPARATOR . $raw;
      if (is_file($fs1)) { return rtrim($baseWeb, '/') . '/' . 'img/student_profile/' . rawurlencode($raw); }
    }
    // Secondary: if original looked like a full relative path under img/student_profile
    $rel = ltrim($sp, '/');
    if (stripos($rel, 'img/student_profile/') === 0) {
      $sub = substr($rel, strlen('img/student_profile/'));
      $fs = rtrim($baseFs, '/\\') . DIRECTORY_SEPARATOR . $sub;
      if (is_file($fs)) { return rtrim($baseWeb, '/') . '/' . str_replace('\\', '/', $rel); }
    }
  }

  // 2) Guess by student_id with common extensions
  if ($sid !== '') {
    $candidates = [
      $sid . '.jpg', $sid . '.jpeg', $sid . '.png', $sid . '.webp'
    ];
    foreach ($candidates as $fn) {
      $fs = rtrim($baseFs, '/\\') . DIRECTORY_SEPARATOR . $fn;
      if (is_file($fs)) { return rtrim($baseWeb, '/') . '/' . 'img/student_profile/' . rawurlencode($fn); }
    }
  }

  // 3) Fallback to DB blob if present
  if (!empty($sp) && is_probably_blob($sp)) {
    return 'data:image/jpeg;base64,' . base64_encode($sp);
  }

  // 4) Placeholder
  return gallery_placeholder();
}
?>
<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Student Photo Gallery</h3>
    <form class="form-inline" method="get" action="">
      <input class="form-control form-control-sm mr-2" type="text" name="q" placeholder="Search name or ID" value="<?php echo h($q); ?>">
      <select class="form-control form-control-sm mr-2" name="department_id">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dep): ?>
          <option value="<?php echo h($dep['department_id']); ?>" <?php echo ($filterDept === (string)$dep['department_id'] ? 'selected' : ''); ?>><?php echo h($dep['department_name']); ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control form-control-sm mr-2" name="province">
        <option value="">All Provinces</option>
        <?php foreach ($provinces as $pv): ?>
          <option value="<?php echo h($pv); ?>" <?php echo ($filterProv === (string)$pv ? 'selected' : ''); ?>><?php echo h($pv); ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control form-control-sm mr-2" name="district">
        <option value="">All Districts</option>
        <?php foreach ($districts as $dv): ?>
          <option value="<?php echo h($dv); ?>" <?php echo ($filterDist === (string)$dv ? 'selected' : ''); ?>><?php echo h($dv); ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
    </form>
  </div>

  <div class="row">
    <?php if (empty($rows)): ?>
      <div class="col-12">
        <div class="alert alert-info">No students found.</div>
      </div>
    <?php else: ?>
      <?php foreach ($rows as $s): ?>
        <?php 
          $imgSrc = student_img_src_fs(
            $s,
            (string)$base,
            // Filesystem base to sis/img/student_profile/
            realpath(__DIR__ . '/../img/student_profile') ?: (__DIR__ . '/../img/student_profile')
          );
        ?>
        <div class="col-6 col-md-4 col-lg-3 mb-4">
          <div class="card shadow-sm h-100">
            <div class="card-header py-2">
              <div class="small font-weight-bold text-truncate" title="<?php echo h($s['student_fullname']); ?>"><?php echo h($s['student_fullname']); ?></div>
              <div class="text-muted small">Reg: <?php echo h($s['student_id']); ?></div>
            </div>
            <img src="<?php echo $imgSrc; ?>" class="card-img-top" alt="<?php echo h($s['student_fullname']); ?>">
            <div class="card-footer py-2">
              <div class="small text-muted text-truncate" title="<?php echo h($s['department_name'] ?? ''); ?>">
                <?php echo h($s['department_name'] ?? ''); ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
