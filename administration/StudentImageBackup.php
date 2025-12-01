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
      $rel = str_replace('\\', '/', ltrim(substr($abs, strlen($projectRoot)), '/\\'));
      if ($rel === '' || strpos($rel, 'img/') !== 0) {
        // fallback: use folder name + basename
        $rel = 'img/' . basename(dirname($abs)) . '/' . basename($abs);
      }
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

if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive extension is not enabled on the server.';
    exit;
  }
  if (!isset($_FILES['backup_zip']) || $_FILES['backup_zip']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_errors'] = ['Please choose a valid ZIP file.'];
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
  $restored = 0; $skipped = 0;
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    if (!$stat) { $skipped++; continue; }
    $name = $stat['name'];
    if (substr($name, -1) === '/') { continue; } // skip directories
    // normalize path
    $clean = str_replace(['..', "\0"], '', $name);
    $clean = ltrim(str_replace('\\', '/', $clean), '/');
    $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { $skipped++; continue; }

    // Only allow images under img/student_profile or img/Studnet_profile in the archive
    $lower = strtolower($clean);
    $allowed = (strpos($lower, 'img/student_profile/') === 0) || (strpos($lower, 'img/studnet_profile/') === 0);
    if (!$allowed) { $skipped++; continue; }

    $destAbs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    $destDir = dirname($destAbs);
    ensure_dir($destDir);

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
  $_SESSION['flash_messages'] = ["Restore completed. Restored: {$restored}, Skipped: {$skipped}."];
  header('Location: ' . $base . '/administration/StudentImageBackup.php');
  exit;
}

$messages = isset($_SESSION['flash_messages']) ? $_SESSION['flash_messages'] : [];
$errors = isset($_SESSION['flash_errors']) ? $_SESSION['flash_errors'] : [];
unset($_SESSION['flash_messages'], $_SESSION['flash_errors']);

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
        <small class="text-muted">Creates a ZIP including both <code>img/student_profile</code> and <code>img/Studnet_profile</code>.</small>
      </div>
      <a class="btn btn-primary" href="<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php?action=download">
        Download ZIP
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Restore From Backup</h5>
      <p class="text-muted mb-2">Upload a ZIP previously created by this tool. Only image files under <code>img/student_profile</code> and <code>img/Studnet_profile</code> will be restored.</p>
      <form method="post" enctype="multipart/form-data" action="<?php echo h(rtrim($base,'/')); ?>/administration/StudentImageBackup.php?action=restore">
        <div class="form-group">
          <input type="file" name="backup_zip" accept="application/zip,.zip" class="form-control-file" required>
        </div>
        <button type="submit" class="btn btn-success">Restore</button>
      </form>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
