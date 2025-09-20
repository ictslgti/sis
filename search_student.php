<?php
// Public Search Student page (no session required)
$title = "Search Student | SLGTI SIS";
include_once(__DIR__ . "/config.php");
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Simple rate limit: max 30 searches in 5 minutes per session
$_SESSION['srch_win_start'] = $_SESSION['srch_win_start'] ?? time();
$_SESSION['srch_count']     = $_SESSION['srch_count'] ?? 0;
$winSecs = 300; // 5 minutes
if (time() - $_SESSION['srch_win_start'] > $winSecs) {
  $_SESSION['srch_win_start'] = time();
  $_SESSION['srch_count'] = 0;
}

// Build base path for assets
$__base = (defined('APP_BASE') ? APP_BASE : '');
if ($__base !== '' && substr($__base, -1) !== '/') { $__base .= '/'; }

// Captcha removed per request; rate limiting remains in place

// Helpers
function exec_query_collect($con, $sql, $types, $param, &$rows, &$err) {
  $rows = [];
  if ($st = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($st, $types, $param);
    if (!mysqli_stmt_execute($st)) { $err = mysqli_error($con); mysqli_stmt_close($st); return false; }
    // Bind result columns dynamically
    $resmd = mysqli_stmt_result_metadata($st);
    if (!$resmd) { $err = mysqli_error($con); mysqli_stmt_close($st); return false; }
    $fields = [];
    $row = [];
    $binds = [];
    while ($field = mysqli_fetch_field($resmd)) { $fields[] = $field->name; $row[$field->name] = null; $binds[] = &$row[$field->name]; }
    call_user_func_array('mysqli_stmt_bind_result', array_merge([$st], $binds));
    while (mysqli_stmt_fetch($st)) { $rows[] = $row; $row = array_fill_keys($fields, null); $binds = []; foreach ($fields as $f) { $binds[] = &$row[$f]; } call_user_func_array('mysqli_stmt_bind_result', array_merge([$st], $binds)); }
    mysqli_stmt_close($st);
    return true;
  } else { $err = mysqli_error($con); return false; }
}

// Handle search
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : '';
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$resultRow = null;
$enrollments = [];
$error = '';

if ($mode && $query !== '') {
    // Rate limit check
    if ($_SESSION['srch_count'] >= 30) {
      $error = 'Too many requests. Please wait a few minutes and try again.';
    } else {
      $_SESSION['srch_count']++;
      // Determine field
      $sqlBase = "SELECT s.student_id, s.student_fullname, s.student_ininame, s.student_nic, s.student_status, s.student_profile_img,
                         d.department_id, d.department_name,
                         c.course_id, c.course_name,
                         e.student_enroll_status, e.student_enroll_date
                  FROM student s
                  LEFT JOIN student_enroll e ON e.student_id = s.student_id
                  LEFT JOIN course c ON c.course_id = e.course_id
                  LEFT JOIN department d ON d.department_id = c.department_id ";
      if ($mode === 'id') {
        $sql = $sqlBase . " WHERE s.student_id = ? ORDER BY e.student_enroll_date DESC";
        if (!exec_query_collect($con, $sql, 's', $query, $enrollments, $error)) {
          $error = $error ?: 'Database error.';
        }
      } elseif ($mode === 'nic') {
        $sql = $sqlBase . " WHERE REPLACE(UPPER(s.student_nic),' ','') = REPLACE(UPPER(?),' ','') ORDER BY e.student_enroll_date DESC";
        if (!exec_query_collect($con, $sql, 's', $query, $enrollments, $error)) {
          $error = $error ?: 'Database error.';
        }
      } elseif ($mode === 'app') {
        // Try to find by application id if schema supports it
        $found = false;
        // Strategy 1: student table has student_application_id
        $chk = mysqli_query($con, "SHOW COLUMNS FROM student LIKE 'student_application_id'");
        if ($chk && mysqli_num_rows($chk) === 1) {
          mysqli_free_result($chk);
          $sql = $sqlBase . " WHERE s.student_application_id = ? ORDER BY e.student_enroll_date DESC";
          if (exec_query_collect($con, $sql, 's', $query, $enrollments, $error)) { $found = true; }
        }
        // Strategy 2: application table maps to student
        if (!$found) {
          $tbl = mysqli_query($con, "SHOW TABLES LIKE 'student_application'");
          if ($tbl && mysqli_num_rows($tbl) === 1) {
            mysqli_free_result($tbl);
            $sql = "SELECT s.student_id, s.student_fullname, s.student_ininame, s.student_nic, s.student_status, s.student_profile_img,
                           d.department_id, d.department_name, c.course_id, c.course_name,
                           e.student_enroll_status, e.student_enroll_date
                    FROM student_application a
                    JOIN student s ON s.student_id = a.student_id
                    LEFT JOIN student_enroll e ON e.student_id = s.student_id
                    LEFT JOIN course c ON c.course_id = e.course_id
                    LEFT JOIN department d ON d.department_id = c.department_id
                    WHERE a.application_id = ? ORDER BY e.student_enroll_date DESC";
            if (exec_query_collect($con, $sql, 's', $query, $enrollments, $error)) { $found = true; }
          }
        }
        if (!$found && $error === '') { $error = 'Application ID search not available on this system.'; }
      }
      if (!empty($enrollments)) { $resultRow = $enrollments[0]; }
    }
}

// Determine enrollment confirmation
$hasActive = false;
foreach ($enrollments as $e) {
    $st = strtoupper(trim((string)($e['student_enroll_status'] ?? '')));
    if (in_array($st, ['FOLLOWING','ACTIVE'], true)) { $hasActive = true; break; }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <base href="<?php echo $__base === '' ? '/' : $__base; ?>">
  <title><?php echo htmlspecialchars($title); ?></title>
  <link rel="shortcut icon" href="<?php echo $__base; ?>img/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?php echo $__base; ?>css/bootstrap.min.css">
  <link rel="stylesheet" href="<?php echo $__base; ?>css/all.min.css">
  <style>
    body { background: #f8f9fa; }
    .card { border: 0; }
    .brand { text-align:center; }
    .brand img { max-height: 72px; width:auto; }
    .badge-status { font-size: 0.9rem; }
    .result-label { font-weight: 600; color: #6c757d; }
    #qr-region { width: 100%; }
    @media (min-width: 576px) { #qr-region { width: 360px; margin: 0 auto; } }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="brand mb-3">
      <img src="<?php echo $__base; ?>img/SLGTI_logo.png" alt="SLGTI">
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white">
        <strong>Search Student</strong>
        <small class="text-muted d-block">Search by Student ID or NIC</small>
      </div>
      <div class="card-body">
        <form class="row" method="get" action="search_student.php">
          <div class="col-md-3 mb-2">
            <label for="mode" class="sr-only">Search By</label>
            <select id="mode" name="mode" class="form-control" required>
              <option value="" disabled <?php echo $mode === '' ? 'selected' : ''; ?>>Select</option>
              <option value="id" <?php echo $mode === 'id' ? 'selected' : ''; ?>>Student ID</option>
              <option value="nic" <?php echo $mode === 'nic' ? 'selected' : ''; ?>>NIC</option>
              <option value="app" <?php echo $mode === 'app' ? 'selected' : ''; ?>>Application ID</option>
            </select>
          </div>
          <div class="col-md-6 mb-2">
            <label for="q" class="sr-only">Query</label>
            <input id="q" name="q" type="text" class="form-control" placeholder="Enter Student ID or NIC" value="<?php echo htmlspecialchars($query); ?>" required>
          </div>
          <div class="col-md-3 mb-2">
            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> Search</button>
          </div>
        </form>
        <div class="text-right">
          <a href="index.php" class="small">Back to Sign In</a>
          <button class="btn btn-link btn-sm" id="btn-scan"><i class="fas fa-qrcode"></i> Scan QR</button>
        </div>
      </div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
          <strong>Search Result</strong>
          <small class="text-muted d-block">Enrollment confirmation is shown if the student is Following/Active</small>
        </div>
        <?php if ($resultRow): ?>
          <span class="badge badge-status <?php echo $hasActive ? 'badge-success' : 'badge-secondary'; ?>">
            <?php echo $hasActive ? 'Enrollment Confirmed' : 'No Active Enrollment'; ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!$resultRow): ?>
          <div class="text-muted">No student found. Please enter a valid Student ID or NIC.</div>
        <?php else: ?>
          <div class="row">
            <div class="col-md-3 mb-2 text-center">
              <?php
                $imgUrl = '';
                if (!empty($resultRow['student_profile_img'])) {
                  $imgUrl = $__base . ltrim($resultRow['student_profile_img'], '/');
                } else {
                  // try default location: img/student_profile/{id}.jpg
                  $guess = __DIR__ . '/img/student_profile/' . ($resultRow['student_id'] ?? '') . '.jpg';
                  if (file_exists($guess)) { $imgUrl = $__base . 'img/student_profile/' . ($resultRow['student_id'] ?? '') . '.jpg'; }
                }
              ?>
              <?php if ($imgUrl !== ''): ?>
                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Student Photo" class="img-thumbnail mb-2" style="max-height:160px;">
              <?php else: ?>
                <div class="text-muted small">No photo available</div>
              <?php endif; ?>
            </div>
            <div class="col-md-6 mb-2">
              <div class="result-label">Student ID</div>
              <div><?php echo htmlspecialchars($resultRow['student_id'] ?? ''); ?></div>
            </div>
            <div class="col-md-6 mb-2">
              <div class="result-label">NIC</div>
              <div><?php echo htmlspecialchars($resultRow['student_nic'] ?? ''); ?></div>
            </div>
            <div class="col-md-6 mb-2">
              <div class="result-label">Full Name</div>
              <div><?php echo htmlspecialchars($resultRow['student_fullname'] ?? ($resultRow['student_ininame'] ?? '')); ?></div>
            </div>
            <div class="col-md-6 mb-2">
              <div class="result-label">Student Status</div>
              <div><?php echo htmlspecialchars($resultRow['student_status'] ?? ''); ?></div>
            </div>
          </div>

          <?php if (!empty($enrollments)): ?>
            <div class="table-responsive mt-3">
              <table class="table table-sm table-striped">
                <thead class="thead-light">
                  <tr>
                    <th>Department</th>
                    <th>Course</th>
                    <th>Enroll Status</th>
                    <th>Enroll Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($enrollments as $en): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($en['department_name'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($en['course_name'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($en['student_enroll_status'] ?? ''); ?></td>
                      <td><?php echo htmlspecialchars($en['student_enroll_date'] ?? ''); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <div class="text-right mt-3">
            <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="<?php echo $__base; ?>js/jquery-3.3.1.slim.min.js"></script>
  <script src="<?php echo $__base; ?>js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script>
    // QR Scan support: expects URL with mode & q; on success redirect
    (function(){
      var btn = document.getElementById('btn-scan');
      if (!btn) return;
      btn.addEventListener('click', function(ev){
        ev.preventDefault();
        var dlg = document.createElement('div');
        dlg.className = 'modal fade';
        dlg.innerHTML = '\
        <div class="modal-dialog">\
          <div class="modal-content">\
            <div class="modal-header"><h5 class="modal-title">Scan QR</h5>\
              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>\
            </div>\
            <div class="modal-body">\
              <div id="qr-region"></div>\
              <div class="small text-muted mt-2">Point your camera at a QR code containing a link like: <?php echo htmlspecialchars($__base); ?>search_student.php?mode=id&q=STUDENTID</div>\
            </div>\
          </div>\
        </div>';
        document.body.appendChild(dlg);
        $(dlg).modal('show');
        $(dlg).on('hidden.bs.modal', function(){ $(dlg).remove(); });
        var scanner = new Html5Qrcode("qr-region");
        function onScanSuccess(decodedText) {
          try {
            var u = new URL(decodedText, window.location.origin);
            if (u.pathname.toLowerCase().indexOf('search_student.php') !== -1) {
              window.location.href = decodedText;
            } else {
              // Try to parse q and mode from plain text format: MODE:VALUE
              var m = /^([a-zA-Z]+):(.*)$/.exec(decodedText);
              if (m) {
                var md = m[1].toLowerCase(); var qv = m[2];
                window.location.href = 'search_student.php?mode=' + encodeURIComponent(md) + '&q=' + encodeURIComponent(qv);
              } else {
                alert('QR code is not a recognized search URL.');
              }
            }
          } catch(e) {
            window.location.href = decodedText; // may still be a relative link
          }
          scanner.stop();
        }
        Html5Qrcode.getCameras().then(function(devices){
          var id = (devices && devices.length) ? devices[0].id : null;
          scanner.start({ facingMode: "environment", deviceId: id }, { fps: 10, qrbox: 250 }, onScanSuccess);
        });
      });
    })();
  </script>
</body>
</html>
