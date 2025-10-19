<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

require_roles(['ADM','SAO','DIR','IN3','STU','MA2']);
$base = defined('APP_BASE') ? APP_BASE : '';

$sid = isset($_GET['Sid']) ? trim($_GET['Sid']) : '';
if ($sid === '') { header('Location: ' . $base . '/student/ManageStudents.php'); exit; }

// Students may only access their own documents page
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU') {
  $self = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';
  if ($self === '' || $self !== $sid) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

// Ensure column exists to store the common PDF path
@mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_documents_pdf` VARCHAR(255) NULL");

// Fetch student row
$student = null;
if ($r = mysqli_query($con, "SELECT student_id, student_fullname, student_documents_pdf FROM student WHERE student_id='".mysqli_real_escape_string($con,$sid)."' LIMIT 1")) {
  $student = mysqli_fetch_assoc($r) ?: null; mysqli_free_result($r);
}
if (!$student) { http_response_code(404); echo 'Student not found'; exit; }

$messages = []; $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // For STU, enforce self-edit only
  if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU') {
    $self = isset($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : '';
    if ($self === '' || $self !== $sid) { http_response_code(403); echo 'Forbidden'; exit; }
  }
  $action = isset($_POST['action']) ? $_POST['action'] : '';
  if ($action === 'upload') {
    if (!isset($_FILES['common_pdf']) || $_FILES['common_pdf']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Select a PDF to upload.';
    } else {
      $up = $_FILES['common_pdf'];
      $tmp = $up['tmp_name'];
      // Validate MIME (best effort)
      $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
      $mime = $finfo ? finfo_file($finfo, $tmp) : $up['type'];
      if ($finfo) finfo_close($finfo);
      if (stripos((string)$mime, 'pdf') === false) {
        $errors[] = 'Only PDF files are allowed.';
      } else {
        // Destination folder
        $docsBase = __DIR__ . '/../docs';
        if (!is_dir($docsBase)) { @mkdir($docsBase, 0777, true); }
        $dir = $docsBase . '/students';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $safeId = preg_replace('/[^A-Za-z0-9_.-]/', '_', $sid);
        $dest = $dir . '/' . $safeId . '.pdf';
        if (!@move_uploaded_file($tmp, $dest)) {
          // fallback copy then unlink
          if (!@copy($tmp, $dest)) {
            $errors[] = 'Failed to save PDF file.';
          } else { @unlink($tmp); }
        }
        if (empty($errors)) {
          $rel = 'docs/students/' . $safeId . '.pdf';
          $qs = "UPDATE student SET student_documents_pdf='" . mysqli_real_escape_string($con, $rel) . "' WHERE student_id='" . mysqli_real_escape_string($con, $sid) . "'";
          if (!mysqli_query($con, $qs)) { $errors[] = 'Failed to save document path: ' . mysqli_error($con); }
          else { $messages[] = 'Common PDF updated successfully.'; $student['student_documents_pdf'] = $rel; }
        }
      }
    }
  } elseif ($action === 'remove') {
    $rel = trim((string)($student['student_documents_pdf'] ?? ''));
    if ($rel !== '') {
      $abs = realpath(__DIR__ . '/../' . $rel);
      if ($abs && is_file($abs)) { @unlink($abs); }
    }
    if (!mysqli_query($con, "UPDATE student SET student_documents_pdf=NULL WHERE student_id='".mysqli_real_escape_string($con,$sid)."'")) {
      $errors[] = 'Failed to clear document reference: ' . mysqli_error($con);
    } else {
      $messages[] = 'Common PDF removed.';
      $student['student_documents_pdf'] = null;
    }
  }
}

$title = 'Student Documents | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';

$rel = trim((string)($student['student_documents_pdf'] ?? ''));
$abs = $rel ? realpath(__DIR__ . '/../' . $rel) : null;
$ver = ($abs && is_file($abs)) ? (string)@filemtime($abs) : '';
$url = $rel ? (rtrim($base,'/') . '/' . $rel . ($ver ? ('?v=' . urlencode($ver)) : '')) : '';
?>
<div class="container-fluid px-0 px-sm-2 px-md-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-white shadow-sm mb-2">
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/student/ManageStudents.php">Students</a></li>
      <li class="breadcrumb-item active" aria-current="page">Documents</li>
    </ol>
  </nav>
  <h4 class="d-flex align-items-center mb-3"><i class="fas fa-file-pdf text-danger mr-2"></i> Documents: <?php echo htmlspecialchars($sid); ?></h4>

  <?php foreach ($messages as $m): ?><div class="alert alert-success"><?php echo htmlspecialchars($m); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

  <div class="card mb-3">
    <div class="card-header bg-white">Common PDF</div>
    <div class="card-body">
      <?php if ($url): ?>
        <div class="mb-2">
          <a class="btn btn-sm btn-outline-primary mr-2" href="<?php echo $url; ?>" target="_blank"><i class="fas fa-download mr-1"></i>View / Download</a>
          <form method="post" class="d-inline" onsubmit="return confirm('Remove current PDF?');">
            <input type="hidden" name="action" value="remove">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt mr-1"></i>Remove</button>
          </form>
        </div>
        <div class="embed-responsive embed-responsive-4by3 border" style="max-height:70vh;">
          <iframe class="embed-responsive-item" src="<?php echo $url; ?>" title="Student PDF"></iframe>
        </div>
      <?php else: ?>
        <div class="text-muted mb-2">No PDF uploaded.</div>
      <?php endif; ?>
      <hr>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <div class="form-row align-items-center">
          <div class="col-auto mb-2">
            <input type="file" name="common_pdf" accept="application/pdf" class="form-control-file" required>
          </div>
          <div class="col-auto mb-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload mr-1"></i>Upload / Replace</button>
          </div>
        </div>
        <small class="text-muted">Upload a single PDF containing all student documents (max size per server limits).</small>
      </form>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
