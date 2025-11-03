<?php
// SAO Export Filters Page
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

$role = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
if ($role !== 'SAO' && $role !== 'ADM') {
  echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';
$title = 'Export Students (CSV) | SLGTI';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// build filter options
$departments = [];
if ($res = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name')) {
  while ($row = mysqli_fetch_assoc($res)) { $departments[] = $row; }
  mysqli_free_result($res);
}
$provinces = [];
if ($res = mysqli_query($con, "SELECT DISTINCT COALESCE(NULLIF(TRIM(student_provice), ''),'') AS v FROM student ORDER BY v")) {
  while ($row = mysqli_fetch_assoc($res)) { if ($row['v'] !== '') $provinces[] = $row['v']; }
  mysqli_free_result($res);
}
$districts = [];
if ($res = mysqli_query($con, "SELECT DISTINCT COALESCE(NULLIF(TRIM(student_district), ''),'') AS v FROM student ORDER BY v")) {
  while ($row = mysqli_fetch_assoc($res)) { if ($row['v'] !== '') $districts[] = $row['v']; }
  mysqli_free_result($res);
}
$genders = ['Male','Female','Other'];

// current selections
$qs = [
  'active' => isset($_GET['active']) ? (string)$_GET['active'] : '1',
  'department_id' => isset($_GET['department_id']) ? trim((string)$_GET['department_id']) : '',
  'course_id' => isset($_GET['course_id']) ? trim((string)$_GET['course_id']) : '',
  'gender' => isset($_GET['gender']) ? trim((string)$_GET['gender']) : '',
  'province' => isset($_GET['province']) ? trim((string)$_GET['province']) : '',
  'district' => isset($_GET['district']) ? trim((string)$_GET['district']) : '',
  'no_photo' => isset($_GET['no_photo']) ? (string)$_GET['no_photo'] : '',
];
?>
<div class="container mt-4">
  <h3>Export Students (CSV)</h3>
  <p class="text-muted">Choose filters and click Export to download a CSV. Open in Excel.</p>
  <form method="get" action="<?php echo $base; ?>/student/ExportStudentsCSV.php" class="card shadow-sm p-3">
    <div class="form-row">
      <div class="form-group col-md-3">
        <label>Department</label>
        <select name="department_id" class="form-control">
          <option value="">All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?php echo h($d['department_id']); ?>" <?php echo ($qs['department_id']===(string)$d['department_id']?'selected':''); ?>><?php echo h($d['department_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Province</label>
        <select name="province" class="form-control">
          <option value="">All</option>
          <?php foreach ($provinces as $p): ?>
            <option value="<?php echo h($p); ?>" <?php echo ($qs['province']===$p?'selected':''); ?>><?php echo h($p); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>District</label>
        <select name="district" class="form-control">
          <option value="">All</option>
          <?php foreach ($districts as $d): ?>
            <option value="<?php echo h($d); ?>" <?php echo ($qs['district']===$d?'selected':''); ?>><?php echo h($d); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Gender</label>
        <select name="gender" class="form-control">
          <option value="">All</option>
          <?php foreach ($genders as $g): ?>
            <option value="<?php echo h($g); ?>" <?php echo ($qs['gender']===$g?'selected':''); ?>><?php echo h($g); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group col-md-3">
        <div class="custom-control custom-checkbox mt-4">
          <input type="checkbox" class="custom-control-input" id="onlyActive" name="active" value="1" <?php echo ($qs['active']==='1'?'checked':''); ?>>
          <label class="custom-control-label" for="onlyActive">Only Active (exclude Inactive/Dropout)</label>
        </div>
      </div>
      <div class="form-group col-md-3">
        <div class="custom-control custom-checkbox mt-4">
          <input type="checkbox" class="custom-control-input" id="onlyNoPhoto" name="no_photo" value="1" <?php echo ($qs['no_photo']==='1'?'checked':''); ?>>
          <label class="custom-control-label" for="onlyNoPhoto">Only students without profile image</label>
        </div>
      </div>
    </div>
    <div class="d-flex">
      <button type="submit" class="btn btn-primary"><i class="fas fa-file-download mr-1"></i> Export CSV</button>
      <a href="<?php echo $base; ?>/student/ExportStudentsCSV.php" class="btn btn-outline-secondary ml-2">Reset</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
