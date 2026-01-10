<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_roles(['ADM']);

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

$imgRoots = [
  realpath(__DIR__ . '/../img/student_profile') ?: (__DIR__ . '/../img/student_profile'),
  realpath(__DIR__ . '/../img/Studnet_profile') ?: (__DIR__ . '/../img/Studnet_profile'),
];

function ensure_dir($path){ if (!is_dir($path)) { @mkdir($path, 0777, true); } }
foreach ($imgRoots as $d) { ensure_dir($d); }

$action = isset($_GET['action']) ? $_GET['action'] : '';

function restore_from_zip($zipPath, $projectRoot){
  $restored = 0; $skipped = 0;
  $zip = new ZipArchive();
  if ($zip->open($zipPath) !== true) { return [false, 0, 0, 'Failed to open the ZIP file.']; }
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    if (!$stat) { $skipped++; continue; }
    $name = $stat['name'];
    if (substr($name, -1) === '/') { continue; }
    $clean = str_replace(['..', "\0"], '', $name);
    $clean = ltrim(str_replace('\\', '/', $clean), '/');
    $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $skipped++; continue; }
    $lower = strtolower($clean);
    // Determine target relative path. Accept:
    // 1) img/student_profile/... or img/studnet_profile/... (normalize to student_profile)
    // 2) student_profile/... or studnet_profile/... -> map to img/student_profile/...
    // 3) files at ZIP root like 123.jpg -> map to img/student_profile/123.jpg
    // Otherwise, skip.
    $targetRel = '';
    if (strpos($lower, 'img/student_profile/') === 0 || strpos($lower, 'img/studnet_profile/') === 0) {
      // Normalize to img/student_profile/...
      $parts = explode('/', $clean);
      if (count($parts) >= 3 && strtolower($parts[0]) === 'img' && stripos($parts[1], 'student') !== false) {
        $rest = implode('/', array_slice($parts, 2));
        $targetRel = 'img/student_profile/' . ltrim($rest, '/');
      } else {
        $targetRel = 'img/student_profile/' . basename($clean);
      }
    } elseif (strpos($lower, 'student_profile/') === 0 || strpos($lower, 'studnet_profile/') === 0) {
      // Map to img/student_profile/...
      $rest = preg_replace('#^(student|studnet)_profile/#i', '', $clean);
      $targetRel = 'img/student_profile/' . ltrim($rest, '/');
    } elseif (strpos($lower, 'img/') !== 0) {
      // Place loose files at ZIP root under the desired directory
      $targetRel = 'img/student_profile/' . basename($clean);
    } else {
      // Handle variants like img/student_profile.*/... or img/studnet_profile.*/...
      $partsLower = explode('/', $lower);
      $partsOrig  = explode('/', $clean);
      if (count($partsLower) >= 2 && $partsLower[0] === 'img' && (strpos($partsLower[1], 'student_profile') === 0 || strpos($partsLower[1], 'studnet_profile') === 0)) {
        $rest = implode('/', array_slice($partsOrig, 2));
        if ($rest === '') { $skipped++; continue; }
        $targetRel = 'img/student_profile/' . ltrim($rest, '/');
      } else {
        $skipped++; continue;
      }
    }
    $destAbs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRel);
    $destDir = dirname($destAbs);
    if (!is_dir($destDir)) { @mkdir($destDir, 0777, true); }
    $fp = $zip->getStream($name);
    if ($fp === false) { $skipped++; continue; }
    $out = @fopen($destAbs, 'wb');
    if ($out === false) { @fclose($fp); $skipped++; continue; }
    while (!feof($fp)) { $buf = fread($fp, 8192); if ($buf !== false) fwrite($out, $buf); }
    fclose($out); fclose($fp);
    @chmod($destAbs, 0664);
    $restored++;
  }
  $zip->close();
  return [true, $restored, $skipped, ''];
}

if ($action === 'download') {
  if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive extension is not enabled on the server.';
    exit;
  }
  $zip = new ZipArchive();
  $tmpZip = tempnam(sys_get_temp_dir(), 'spzip_');
  @unlink($tmpZip); // ZipArchive requires non-existing file
  if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    echo 'Failed to create ZIP archive.';
    exit;
  }

  $projectRoot = realpath(__DIR__ . '/..');
  $added = 0;
  foreach ($imgRoots as $root) {
    $rootAbs = realpath($root) ?: $root;
    if (!is_dir($rootAbs)) { continue; }
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootAbs, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
      if (!$file->isFile()) continue;
      $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
      $abs = $file->getPathname();
      // Store with path relative to project root (e.g., img/student_profile/123.jpg)
      // Normalize both Studnet_profile and student_profile to student_profile
      $rel = str_replace('\\', '/', ltrim(substr($abs, strlen($projectRoot)), '/\\'));
      if ($rel === '' || strpos($rel, 'img/') !== 0) {
        // fallback: use folder name + basename
        $rel = 'img/' . basename(dirname($abs)) . '/' . basename($abs);
      }
      // Normalize Studnet_profile to student_profile in ZIP
      $rel = preg_replace('#img/studnet_profile/#i', 'img/student_profile/', $rel);
      $zip->addFile($abs, $rel);
      $added++;
    }
  }
  $zip->close();

  $fname = 'student_profile_backup_' . date('Ymd_His') . '.zip';
  header('Content-Type: application/zip');
  header('Content-Disposition: attachment; filename="' . $fname . '"');
  header('Content-Length: ' . filesize($tmpZip));
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  readfile($tmpZip);
  @unlink($tmpZip);
  exit;
}

// Chunked upload endpoint
if ($action === 'upload_chunk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  @set_time_limit(600);
  @ini_set('memory_limit', '512M');
  if (!class_exists('ZipArchive')) { echo json_encode(['ok'=>false,'error'=>'ZipArchive not available']); exit; }

  $uploadId   = isset($_POST['upload_id']) ? preg_replace('/[^A-Za-z0-9_.-]/','', (string)$_POST['upload_id']) : '';
  $fileName   = isset($_POST['file_name']) ? basename((string)$_POST['file_name']) : '';
  $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
  $totalChunks= isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : -1;
  $finalize   = isset($_POST['finalize']) ? (int)$_POST['finalize'] : 0;

  if ($uploadId === '' || $fileName === '' || $chunkIndex < 0 || $totalChunks < 1) {
    echo json_encode(['ok'=>false,'error'=>'Invalid parameters']); exit;
  }
  if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'zip') {
    echo json_encode(['ok'=>false,'error'=>'Only .zip is allowed']); exit;
  }

  $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sis_chunks_' . $uploadId;
  if (!is_dir($base)) { @mkdir($base, 0777, true); }

  // Read chunk data (supports raw body or multipart field "chunk")
  $data = null;
  if (isset($_FILES['chunk']) && $_FILES['chunk']['error'] === UPLOAD_ERR_OK) {
    $data = @file_get_contents($_FILES['chunk']['tmp_name']);
  } else {
    $data = @file_get_contents('php://input');
  }
  if ($data === false) { echo json_encode(['ok'=>false,'error'=>'Failed to read chunk']); exit; }

  $partPath = $base . DIRECTORY_SEPARATOR . sprintf('part_%06d', $chunkIndex);
  if (@file_put_contents($partPath, $data) === false) { echo json_encode(['ok'=>false,'error'=>'Failed to write chunk']); exit; }

  // If last chunk or finalize requested, assemble and restore
  $done = ($chunkIndex + 1 >= $totalChunks) || ($finalize === 1);
  if ($done) {
    $zipPath = $base . DIRECTORY_SEPARATOR . $fileName;
    $out = @fopen($zipPath, 'wb');
    if (!$out) { echo json_encode(['ok'=>false,'error'=>'Failed to assemble zip']); exit; }
    for ($i=0; $i<$totalChunks; $i++) {
      $p = $base . DIRECTORY_SEPARATOR . sprintf('part_%06d', $i);
      if (!is_file($p)) { fclose($out); echo json_encode(['ok'=>false,'error'=>'Missing chunk '.$i]); exit; }
      $in = @fopen($p, 'rb'); if(!$in){ fclose($out); echo json_encode(['ok'=>false,'error'=>'Cannot open chunk '.$i]); exit; }
      while (!feof($in)) { $buf = fread($in, 8192); if ($buf!==false) fwrite($out, $buf); }
      fclose($in);
    }
    fclose($out);

    $projectRoot = realpath(__DIR__ . '/..');
    list($ok, $restored, $skipped, $err) = restore_from_zip($zipPath, $projectRoot);

    // cleanup
    foreach (glob($base . DIRECTORY_SEPARATOR . 'part_*') as $f) { @unlink($f); }
    @unlink($zipPath);
    @rmdir($base);

    if (!$ok) { echo json_encode(['ok'=>false,'error'=>$err ?: 'Restore failed']); exit; }
    $_SESSION['flash_messages'] = ["Restore completed. Restored: {$restored}, Skipped: {$skipped}."];
    echo json_encode(['ok'=>true,'restored'=>$restored,'skipped'=>$skipped]);
    exit;
  }

  echo json_encode(['ok'=>true,'received'=>$chunkIndex]);
  exit;
}

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  @set_time_limit(300); // allow up to 5 minutes for large archives
  @ini_set('memory_limit', '512M'); // more headroom for extraction
  if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive extension is not enabled on the server.';
    exit;
  }
  if (!isset($_FILES['backup_zip']) || $_FILES['backup_zip']['error'] !== UPLOAD_ERR_OK) {
    $err = isset($_FILES['backup_zip']['error']) ? (int)$_FILES['backup_zip']['error'] : UPLOAD_ERR_NO_FILE;
    $msg = 'Please choose a valid ZIP file.';
    // Detect post_max_size overflow: PHP discards $_POST/$_FILES when Content-Length exceeds post_max_size
    $cl = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    $pms = ini_get('post_max_size');
    // helper to convert shorthand like 128M to bytes
    $toBytes = function($val){
      $val = trim((string)$val);
      if ($val === '') return 0;
      $unit = strtolower(substr($val, -1));
      $num = (float)$val;
      if ($unit === 'g') return (int)($num * 1024 * 1024 * 1024);
      if ($unit === 'm') return (int)($num * 1024 * 1024);
      if ($unit === 'k') return (int)($num * 1024);
      return (int)$num;
    };
    $pmsBytes = $toBytes($pms);
    if ($cl > 0 && $pmsBytes > 0 && $cl > $pmsBytes) {
      $msg = 'The uploaded request exceeds post_max_size (php.ini). Use the Chunk Upload button or increase post_max_size and upload_max_filesize.';
    }
    if ($err === UPLOAD_ERR_INI_SIZE) {
      $msg = 'The uploaded ZIP exceeds the server limit (upload_max_filesize). Increase upload_max_filesize and post_max_size in php.ini.';
    } elseif ($err === UPLOAD_ERR_FORM_SIZE) {
      $msg = 'The uploaded ZIP exceeds the form limit (MAX_FILE_SIZE).';
    } elseif ($err === UPLOAD_ERR_PARTIAL) {
      $msg = 'The ZIP was only partially uploaded. Please try again.';
    } elseif ($err === UPLOAD_ERR_NO_FILE) {
      $msg = 'No file was uploaded. Please choose a ZIP file.';
    } elseif ($err === UPLOAD_ERR_NO_TMP_DIR) {
      $msg = 'Missing temporary folder on server.';
    } elseif ($err === UPLOAD_ERR_CANT_WRITE) {
      $msg = 'Failed to write uploaded file to disk.';
    } elseif ($err === UPLOAD_ERR_EXTENSION) {
      $msg = 'Upload stopped by a PHP extension.';
    }
    $_SESSION['flash_errors'] = [$msg];
    header('Location: ' . $base . '/administration/StudentImageBackup.php');
    exit;
  }
  $tmp = $_FILES['backup_zip']['tmp_name'];
  $zip = new ZipArchive();
  if ($zip->open($tmp) !== true) {
    $_SESSION['flash_errors'] = ['Failed to open the ZIP file.'];
    header('Location: ' . $base . '/administration/StudentImageBackup.php');
    exit;
  }
  $projectRoot = realpath(__DIR__ . '/..');
  // Delegate to common restore function
  list($ok, $restored, $skipped, $err) = restore_from_zip($tmp, $projectRoot);
  if (!$ok) {
    $_SESSION['flash_errors'] = [$err ?: 'Failed to restore files from ZIP.'];
  } else {
    $_SESSION['flash_messages'] = ["Restore completed. Restored: {$restored}, Skipped: {$skipped}."];
  }
  header('Location: ' . $base . '/administration/StudentImageBackup.php');
  exit;
}

$messages = isset($_SESSION['flash_messages']) ? $_SESSION['flash_messages'] : [];
$errors = isset($_SESSION['flash_errors']) ? $_SESSION['flash_errors'] : [];
unset($_SESSION['flash_messages'], $_SESSION['flash_errors']);

// Load all photos from filesystem
$allPhotos = [];
$photoCount = 0;
$projectRoot = realpath(__DIR__ . '/..');
foreach ($imgRoots as $root) {
  $rootAbs = realpath($root) ?: $root;
  if (!is_dir($rootAbs)) { continue; }
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootAbs, FilesystemIterator::SKIP_DOTS));
  foreach ($rii as $file) {
    if (!$file->isFile()) continue;
    $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
    $abs = $file->getPathname();
    // Calculate relative path from project root
    $rel = str_replace('\\', '/', ltrim(substr($abs, strlen($projectRoot)), '/\\'));
    // Accept both img/student_profile and img/Studnet_profile, normalize to img/student_profile
    $relLower = strtolower($rel);
    if (strpos($relLower, 'img/student_profile/') === 0 || strpos($relLower, 'img/studnet_profile/') === 0) {
      // Normalize path to img/student_profile for consistency
      $normalizedPath = preg_replace('#^img/studnet_profile/#i', 'img/student_profile/', $rel);
      $allPhotos[] = [
        'path' => $normalizedPath,
        'abs' => $abs,
        'name' => basename($abs),
        'size' => filesize($abs),
        'modified' => filemtime($abs)
      ];
      $photoCount++;
    }
  }
}
// Sort by filename
usort($allPhotos, function($a, $b) {
  return strcmp($a['name'], $b['name']);
});

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 24;
$totalPages = max(1, ceil(count($allPhotos) / $perPage));
$offset = ($page - 1) * $perPage;
$photosOnPage = array_slice($allPhotos, $offset, $perPage);

// Try to match filenames with student IDs from database
$studentMap = [];
if ($photoCount > 0 && isset($con)) {
  $ids = [];
  foreach ($allPhotos as $p) {
    $basename = pathinfo($p['name'], PATHINFO_FILENAME);
    // Only add if it looks like a student ID (alphanumeric, reasonable length)
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $basename)) {
      $ids[] = $basename;
    }
  }
  if (!empty($ids)) {
    // Batch in groups of 100 to avoid query limits
    $batches = array_chunk(array_unique($ids), 100);
    foreach ($batches as $batch) {
      $placeholders = implode(',', array_fill(0, count($batch), '?'));
      $sql = "SELECT student_id, student_fullname FROM student WHERE student_id IN ($placeholders)";
      if ($stmt = mysqli_prepare($con, $sql)) {
        $types = str_repeat('s', count($batch));
        mysqli_stmt_bind_param($stmt, $types, ...$batch);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        while ($rs && ($r = mysqli_fetch_assoc($rs))) {
          $studentMap[$r['student_id']] = $r['student_fullname'];
        }
        mysqli_stmt_close($stmt);
      }
    }
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Student Profile Images - Backup & Restore</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container my-4">
  <h3 class="mb-3">Student Profile Images - Backup & Restore</h3>

  <?php foreach ($messages as $m): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo h($m); ?>
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo h($e); ?>
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
  <?php endforeach; ?>

  <div class="card mb-3">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
      <div class="mb-2 mb-md-0">
        <h5 class="card-title mb-1">Download Backup</h5>
        <small class="text-muted">Creates a ZIP including <code>img/student_profile</code> and <code>img/Studnet_profile</code>.</small>
      </div>
      <a class="btn btn-primary" href="<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php?action=download">
        Download ZIP
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Restore From Backup</h5>
      <p class="text-muted mb-2">Upload a ZIP previously created by this tool. Image files from <code>img/student_profile</code> or <code>img/Studnet_profile</code> will be restored to <code>img/student_profile</code>.</p>
      <form id="restoreForm" method="post" enctype="multipart/form-data" action="<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php?action=restore">
        <div class="form-group">
          <input id="backup_zip" type="file" name="backup_zip" accept="application/zip,.zip,application/x-zip-compressed" class="form-control-file" required>
        </div>
        <div class="progress mb-2" style="height: 8px; display:none;">
          <div id="chunkProgress" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>
        <div class="d-flex">
          <button id="restoreBtn" type="submit" class="btn btn-success mr-2" disabled>Restore</button>
          <button id="chunkBtn" type="button" class="btn btn-outline-primary" disabled>Restore via Chunk Upload</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-body">
      <h5 class="card-title">All Photos (<?php echo number_format($photoCount); ?> total)</h5>
      <p class="text-muted mb-3">Photos from <code>img/student_profile</code> and <code>img/Studnet_profile</code> directories.</p>
      
      <?php if (empty($allPhotos)): ?>
        <div class="alert alert-info">No photos found in the directory.</div>
      <?php else: ?>
        <div class="row mb-3">
          <div class="col-md-6">
            <small class="text-muted">
              Showing <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $perPage, $photoCount)); ?> of <?php echo number_format($photoCount); ?> photos
            </small>
          </div>
          <div class="col-md-6 text-right">
            <?php if ($totalPages > 1): ?>
              <nav>
                <ul class="pagination pagination-sm justify-content-end mb-0">
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                  </li>
                  <?php
                  $startPage = max(1, $page - 2);
                  $endPage = min($totalPages, $page + 2);
                  if ($startPage > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                    <?php if ($startPage > 2): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                  <?php endfor; ?>
                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
                  <?php endif; ?>
                  <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </div>

        <div class="row">
          <?php foreach ($photosOnPage as $photo): ?>
            <?php
              $filename = $photo['name'];
              $baseName = pathinfo($filename, PATHINFO_FILENAME);
              $studentName = isset($studentMap[$baseName]) ? $studentMap[$baseName] : null;
              // Construct web path: base + / + img/student_profile/filename.jpg
              // If base is empty, use root-relative path
              $webPath = ($base !== '' ? rtrim($base, '/') . '/' : '/') . $photo['path'];
              $fileSize = $photo['size'];
              $sizeStr = $fileSize < 1024 ? $fileSize . ' B' : ($fileSize < 1024*1024 ? round($fileSize/1024, 1) . ' KB' : round($fileSize/(1024*1024), 1) . ' MB');
              $modifiedDate = date('Y-m-d H:i', $photo['modified']);
            ?>
            <div class="col-6 col-md-4 col-lg-3 mb-4">
              <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                  <div class="small font-weight-bold text-truncate" title="<?php echo h($filename); ?>">
                    <?php echo h($filename); ?>
                  </div>
                  <?php if ($studentName): ?>
                    <div class="text-muted small text-truncate" title="<?php echo h($studentName); ?>">
                      <?php echo h($studentName); ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="position-relative" style="height: 200px; overflow: hidden; background: #f8f9fa;">
                  <img src="<?php echo h($webPath); ?>" 
                       class="w-100 h-100" 
                       style="object-fit: cover; cursor: pointer;" 
                       alt="<?php echo h($filename); ?>"
                       onclick="window.open('<?php echo h($webPath); ?>', '_blank')"
                       title="Click to view full size">
                </div>
                <div class="card-footer py-2">
                  <div class="small text-muted">
                    <div><?php echo h($sizeStr); ?></div>
                    <div><?php echo h($modifiedDate); ?></div>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="row mt-3">
            <div class="col-12 text-center">
              <nav>
                <ul class="pagination justify-content-center">
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>">Previous</a>
                  </li>
                  <?php
                  $startPage = max(1, $page - 2);
                  $endPage = min($totalPages, $page + 2);
                  if ($startPage > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                    <?php if ($startPage > 2): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                  <?php endfor; ?>
                  <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                      <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
                  <?php endif; ?>
                  <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo min($totalPages, $page + 1); ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){
    var fileInput = document.getElementById('backup_zip');
    var submitBtn = document.getElementById('restoreBtn');
    var form = document.getElementById('restoreForm');
    var chunkBtn = document.getElementById('chunkBtn');
    var bar = document.getElementById('chunkProgress');
    var progressWrap = bar ? bar.parentElement : null;
    function isZipFile(f){
      if (!f) return false;
      var name = (f.name||'').toLowerCase();
      var type = (f.type||'').toLowerCase();
      return name.endsWith('.zip') || type === 'application/zip' || type === 'application/x-zip-compressed';
    }
    function update(){
      var f = (fileInput && fileInput.files && fileInput.files[0]) ? fileInput.files[0] : null;
      var valid = isZipFile(f);
      submitBtn.disabled = !valid;
      if (chunkBtn) chunkBtn.disabled = !valid;
    }
    if (fileInput) {
      fileInput.addEventListener('change', update);
    }
    async function chunkUpload(file){
      var CHUNK = 2 * 1024 * 1024; // 2MB chunks
      var total = Math.ceil(file.size / CHUNK);
      var uploadId = (Date.now() + '_' + (file.size||0) + '_' + (file.name||'zip')).replace(/[^A-Za-z0-9_.-]/g,'_');
      if (progressWrap) { progressWrap.style.display='block'; }
      for (let i=0; i<total; i++){
        var start = i * CHUNK;
        var end = Math.min(start + CHUNK, file.size);
        var blob = file.slice(start, end);
        var fd = new FormData();
        fd.append('upload_id', uploadId);
        fd.append('file_name', file.name);
        fd.append('chunk_index', String(i));
        fd.append('total_chunks', String(total));
        fd.append('chunk', blob, 'chunk');
        var res = await fetch('<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php?action=upload_chunk', { method:'POST', body: fd, credentials:'same-origin' });
        var json = {};
        try { json = await res.json(); } catch(_){ }
        if (!json || json.ok !== true && i + 1 < total){ throw new Error((json && json.error) || 'Upload failed at chunk ' + i); }
        var pct = Math.round(((i+1)/total)*100);
        if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', pct); }
      }
      // Send finalize flag (optional; server assembles on last chunk already)
      var fd2 = new FormData();
      fd2.append('upload_id', uploadId);
      fd2.append('file_name', file.name);
      fd2.append('chunk_index', String(total-1));
      fd2.append('total_chunks', String(total));
      fd2.append('finalize', '1');
      var res2 = await fetch('<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php?action=upload_chunk', { method:'POST', body: fd2, credentials:'same-origin' });
      var json2 = {};
      try { json2 = await res2.json(); } catch(_){ }
      if (!json2 || json2.ok !== true){ throw new Error((json2 && json2.error) || 'Finalize failed'); }
      // On success, reload to show flash message
      window.location.href = '<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php';
    }
    if (form) {
      form.addEventListener('submit', function(e){
        var f = (fileInput && fileInput.files && fileInput.files[0]) ? fileInput.files[0] : null;
        if (!isZipFile(f)) { e.preventDefault(); alert('Please choose a valid ZIP file.'); return; }
        // Use chunk upload for files > 20MB
        if (f && f.size > 20 * 1024 * 1024){
          e.preventDefault();
          submitBtn.disabled = true;
          chunkUpload(f).catch(function(err){
            alert('Upload failed: ' + (err && err.message ? err.message : err));
            submitBtn.disabled = false;
            if (progressWrap) { progressWrap.style.display='none'; }
            if (bar) { bar.style.width = '0%'; }
          });
        }
      });
    }
    if (chunkBtn) {
      chunkBtn.addEventListener('click', function(){
        var f = (fileInput && fileInput.files && fileInput.files[0]) ? fileInput.files[0] : null;
        if (!isZipFile(f)) { alert('Please choose a valid ZIP file.'); return; }
        submitBtn.disabled = true; chunkBtn.disabled = true;
        chunkUpload(f).catch(function(err){
          alert('Upload failed: ' + (err && err.message ? err.message : err));
          submitBtn.disabled = false; chunkBtn.disabled = false;
          if (progressWrap) { progressWrap.style.display='none'; }
          if (bar) { bar.style.width = '0%'; }
        });
      });
    }
    update();
  })();
</script>
</body>
</html>
