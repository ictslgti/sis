<?php
// UploadDocumentation.php - upload a single consolidated PDF for a student
// BLOCK#1 START DON'T CHANGE THE ORDER
$title = "Upload Student Documentation | SLGTI";
include_once("../config.php");

// Allow Admins, MA2 and Students (students can only upload their own doc)
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','MA2','STU'])) {
  include_once("../head.php");
  include_once("../menu.php");
  http_response_code(403);
  echo '<div class="alert alert-danger m-3">Access denied.</div>';
  include_once("../footer.php");
  exit;
}

// Handler 2: People's Bank details + front page upload (PDF/JPG/PNG)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bank'])) {
  if ($studentId === '') {
    $errors[] = 'Student ID is required.';
  } else {
    $stmt = mysqli_prepare($con, "SELECT 1 FROM student WHERE student_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || mysqli_num_rows($res) !== 1) { $errors[] = 'Student not found.'; }
    mysqli_stmt_close($stmt);
  }

  $acc = isset($_POST['bank_account_no']) ? trim($_POST['bank_account_no']) : '';
  $br  = isset($_POST['bank_branch']) ? trim($_POST['bank_branch']) : '';
  if ($acc === '' || $br === '') {
    $errors[] = 'Bank account number and branch are required.';
  }

  $frontRelPath = null;
  if (empty($errors)) {
    $hasUpload = isset($_FILES['bank_front']) && $_FILES['bank_front']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($hasUpload) {
      if ($_FILES['bank_front']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Failed to read bank front page file.';
      } else {
        $tmp = $_FILES['bank_front']['tmp_name'];
        $name = $_FILES['bank_front']['name'];
        $size = (int)$_FILES['bank_front']['size'];
        $type = mime_content_type($tmp);
        if ($size <= 0) {
          $errors[] = 'Empty bank front page file.';
        } elseif ($size > 15*1024*1024) {
          $errors[] = 'Bank front page too large (max 15MB).';
        } else {
          $ok = false; $ext = 'dat';
          if (stripos($type, 'pdf') !== false) { $ok = true; $ext = 'pdf'; }
          if (stripos($type, 'jpeg') !== false || stripos($type, 'jpg') !== false) { $ok = true; $ext = 'jpg'; }
          if (stripos($type, 'png') !== false) { $ok = true; $ext = 'png'; }
          if (!$ok) { $errors[] = 'Only PDF, JPG, or PNG allowed for bank front page.'; }
          if (empty($errors)) {
            $destDir = __DIR__ . '/documentation';
            if (!is_dir($destDir)) {
              if (!mkdir($destDir, 0775, true)) {
                $errors[] = 'Failed to create destination directory: ' . htmlspecialchars($destDir);
              }
            }
            if (empty($errors) && !is_writable($destDir)) {
              $errors[] = 'Destination directory is not writable: ' . htmlspecialchars($destDir);
            }
            $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $studentId);
            $destPath = $destDir . '/' . $safeId . '_bankfront.' . $ext;
            if (empty($errors)) {
              if (!move_uploaded_file($tmp, $destPath)) {
                $data = @file_get_contents($tmp);
                if ($data === false || @file_put_contents($destPath, $data) === false) {
                  $lastErr = error_get_last();
                  $errors[] = 'Failed to save bank front page on server at ' . htmlspecialchars($destPath) .
                              (isset($lastErr['message']) ? (' | Reason: ' . htmlspecialchars($lastErr['message'])) : '');
                }
              }
            }
            if (empty($errors)) {
              @chmod($destPath, 0664);
              $frontRelPath = 'student/documentation/' . $safeId . '_bankfront.' . $ext;
            }
          }
        }
      }
    }
  }

  // Ensure columns exist: bank_account_no, bank_branch, bank_frontsheet_path, bank_name
  if (empty($errors)) {
    $dbNameRes = mysqli_query($con, 'SELECT DATABASE() as db');
    $dbRow = $dbNameRes ? mysqli_fetch_assoc($dbNameRes) : null;
    $dbName = $dbRow && isset($dbRow['db']) ? $dbRow['db'] : null;
    if ($dbName) {
      $needCols = [
        'bank_account_no' => "ALTER TABLE student ADD COLUMN bank_account_no VARCHAR(32) NULL",
        'bank_branch' => "ALTER TABLE student ADD COLUMN bank_branch VARCHAR(128) NULL",
        'bank_frontsheet_path' => "ALTER TABLE student ADD COLUMN bank_frontsheet_path VARCHAR(255) NULL",
        'bank_name' => "ALTER TABLE student ADD COLUMN bank_name VARCHAR(64) NULL"
      ];
      foreach ($needCols as $col=>$ddl) {
        $chk = mysqli_prepare($con, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='student' AND COLUMN_NAME=? LIMIT 1");
        mysqli_stmt_bind_param($chk, 'ss', $dbName, $col);
        mysqli_stmt_execute($chk);
        $cres = mysqli_stmt_get_result($chk);
        $exists = ($cres && mysqli_num_rows($cres) === 1);
        mysqli_stmt_close($chk);
        if (!$exists) { @mysqli_query($con, $ddl); }
      }
    }

    // Build update
    $sql = 'UPDATE student SET bank_name=?, bank_account_no=?, bank_branch=?' . ($frontRelPath ? ', bank_frontsheet_path=?' : '') . ' WHERE student_id=? LIMIT 1';
    if ($stU = mysqli_prepare($con, $sql)) {
      $bankName = "People\'s Bank";
      if ($frontRelPath) {
        mysqli_stmt_bind_param($stU, 'sssss', $bankName, $acc, $br, $frontRelPath, $studentId);
      } else {
        mysqli_stmt_bind_param($stU, 'ssss', $bankName, $acc, $br, $studentId);
      }
      if (!mysqli_stmt_execute($stU)) {
        $errors[] = 'Failed to save bank details: ' . htmlspecialchars(mysqli_stmt_error($stU));
      }
      mysqli_stmt_close($stU);
    } else {
      $errors[] = 'Failed to prepare bank details update.';
    }
  }

  if (empty($errors)) {
    $success = 'Bank details saved successfully.';
  }
}

include_once("../head.php");
include_once("../menu.php");
// END DON'T CHANGE THE ORDER

function sp_safe($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$success = null;

// Determine effective studentId based on role
if ($_SESSION['user_type'] === 'STU') {
  // Students can only upload for themselves
  $studentId = isset($_SESSION['user_name']) ? trim($_SESSION['user_name']) : '';
} else {
  // Admin/MA2 can specify via POST first, then GET Sid
  $studentId = isset($_POST['student_id']) ? trim($_POST['student_id']) : (isset($_GET['Sid']) ? trim($_GET['Sid']) : '');
}

// Handler 1: Consolidated documentation PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_pdf'])) {
  if ($studentId === '') {
    $errors[] = 'Student ID is required.';
  } else {
    // Validate student exists
    $stmt = mysqli_prepare($con, "SELECT 1 FROM student WHERE student_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $studentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || mysqli_num_rows($res) !== 1) {
      $errors[] = 'Student not found.';
    }
    mysqli_stmt_close($stmt);
  }

  // Validate file
  if (empty($errors)) {
    if (!isset($_FILES['doc_pdf']) || $_FILES['doc_pdf']['error'] !== UPLOAD_ERR_OK) {
      $errors[] = 'Please select a PDF file to upload.';
    } else {
      $tmp = $_FILES['doc_pdf']['tmp_name'];
      $name = $_FILES['doc_pdf']['name'];
      $size = (int)$_FILES['doc_pdf']['size'];
      $type = mime_content_type($tmp);
      if ($size <= 0) {
        $errors[] = 'Empty file.';
      } elseif ($size > 50*1024*1024) {
        $errors[] = 'File too large (max 50MB before compression).';
      } elseif (stripos($type, 'pdf') === false) {
        $errors[] = 'Only PDF files are accepted.';
      }
    }
  }

  if (empty($errors)) {
    // Ensure destination dir exists
    $destDir = __DIR__ . '/documentation';
    if (!is_dir($destDir)) {
      if (!mkdir($destDir, 0775, true)) {
        $errors[] = 'Failed to create destination directory: ' . htmlspecialchars($destDir);
      }
    }
    if (empty($errors) && !is_writable($destDir)) {
      $errors[] = 'Destination directory is not writable: ' . htmlspecialchars($destDir);
    }

    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $studentId);
    $origPath = $destDir . '/' . $safeId . '_orig.pdf';
    $destPath = $destDir . '/' . $safeId . '.pdf';
    if (empty($errors)) {
      // Save original upload to a temporary path first
      if (!move_uploaded_file($tmp, $origPath)) {
        // Some servers require copy
        $data = @file_get_contents($tmp);
        if ($data === false || @file_put_contents($origPath, $data) === false) {
          $lastErr = error_get_last();
          $errors[] = 'Failed to save file on server at ' . htmlspecialchars($origPath) .
                      (isset($lastErr['message']) ? (' | Reason: ' . htmlspecialchars($lastErr['message'])) : '');
        }
      }
    }

    if (empty($errors)) {
      // Try to compress to <= 10MB using Ghostscript, falling back gracefully
      $targetBytes = 10 * 1024 * 1024; // 10MB
      $gsAvailable = function_exists('exec');
      $compressionSucceeded = false;

      if ($gsAvailable) {
        // Try multiple quality presets
        $presets = ['/ebook', '/screen']; // ebook first (better quality), then screen (stronger compression)
        foreach ($presets as $preset) {
          $cmd = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH '
               . '-dPDFSETTINGS=' . escapeshellarg($preset) . ' '
               . '-sOutputFile=' . escapeshellarg($destPath) . ' '
               . escapeshellarg($origPath) . ' 2>&1';
          $out = [];
          $ret = 0;
          @exec($cmd, $out, $ret);
          if ($ret === 0 && is_file($destPath)) {
            if (filesize($destPath) <= $targetBytes) { $compressionSucceeded = true; break; }
          }
        }
        // As a last resort, try aggressive downsampling
        if (!$compressionSucceeded) {
          $cmd = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH '
               . '-dColorImageDownsampleType=/Average -dColorImageResolution=100 '
               . '-dGrayImageDownsampleType=/Average -dGrayImageResolution=100 '
               . '-dMonoImageDownsampleType=/Subsample -dMonoImageResolution=200 '
               . '-sOutputFile=' . escapeshellarg($destPath) . ' '
               . escapeshellarg($origPath) . ' 2>&1';
          $out = [];
          $ret = 0;
          @exec($cmd, $out, $ret);
          if ($ret === 0 && is_file($destPath) && filesize($destPath) <= $targetBytes) {
            $compressionSucceeded = true;
          }
        }
      }

      if (!$gsAvailable) {
        // Ghostscript not available; keep original but enforce size
        @rename($origPath, $destPath);
        if (is_file($destPath) && filesize($destPath) > $targetBytes) {
          $errors[] = 'Uploaded PDF is larger than 10MB and server compression is unavailable. Please upload a smaller file.';
        }
      } else {
        if ($compressionSucceeded) {
          @unlink($origPath);
        } else {
          // If compression failed or still too big, fall back to original
          @rename($origPath, $destPath);
          if (is_file($destPath) && filesize($destPath) > $targetBytes) {
            $errors[] = 'Uploaded PDF exceeds 10MB after compression. Please reduce the file and try again.';
          }
        }
      }
    }

    if (empty($errors)) {
      @chmod($destPath, 0664);
      $relPath = 'student/documentation/' . $safeId . '.pdf';

      // Ensure column student_profile_doc exists; if not, add it
      $hasCol = false;
      $dbNameRes = mysqli_query($con, 'SELECT DATABASE() as db');
      $dbRow = $dbNameRes ? mysqli_fetch_assoc($dbNameRes) : null;
      $dbName = $dbRow && isset($dbRow['db']) ? $dbRow['db'] : null;
      if ($dbName) {
        $check = mysqli_prepare($con, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='student' AND COLUMN_NAME='student_profile_doc' LIMIT 1");
        mysqli_stmt_bind_param($check, 's', $dbName);
        mysqli_stmt_execute($check);
        $cres = mysqli_stmt_get_result($check);
        $hasCol = ($cres && mysqli_num_rows($cres) === 1);
        mysqli_stmt_close($check);
      }

      if (!$hasCol) {
        // Try to add the column
        @mysqli_query($con, "ALTER TABLE student ADD COLUMN student_profile_doc VARCHAR(255) NULL");
      }

      // Update the path in student table if column now exists
      $hasCol2 = false;
      if ($dbName) {
        $check2 = mysqli_prepare($con, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='student' AND COLUMN_NAME='student_profile_doc' LIMIT 1");
        mysqli_stmt_bind_param($check2, 's', $dbName);
        mysqli_stmt_execute($check2);
        $cres2 = mysqli_stmt_get_result($check2);
        $hasCol2 = ($cres2 && mysqli_num_rows($cres2) === 1);
        mysqli_stmt_close($check2);
      }
      if ($hasCol2) {
        $up = mysqli_prepare($con, "UPDATE student SET student_profile_doc=? WHERE student_id=? LIMIT 1");
        mysqli_stmt_bind_param($up, 'ss', $relPath, $studentId);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);
      }

      $success = 'Documentation uploaded successfully.';
    }
  }
}
?>

<div class="container mt-3">
  <div class="row">
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><i class="fa fa-file-pdf"></i> Upload Student Documentation (PDF)</div>
        <div class="card-body">
          <?php if (!empty($errors)) { echo '<div class="alert alert-danger py-2">'.sp_safe(implode(' | ', $errors)).'</div>'; }
                if ($success) { echo '<div class="alert alert-success py-2">'.sp_safe($success).'</div>'; } ?>
          <form method="post" enctype="multipart/form-data">
            <?php if ($_SESSION['user_type'] === 'STU') { ?>
              <div class="form-group">
                <label>Your Student ID</label>
                <input type="text" class="form-control" value="<?php echo sp_safe($studentId); ?>" readonly>
                <input type="hidden" name="student_id" value="<?php echo sp_safe($studentId); ?>">
              </div>
            <?php } else { ?>
              <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" class="form-control" id="student_id" name="student_id" value="<?php echo sp_safe($studentId); ?>" placeholder="Enter Student ID" required>
                <small class="form-text text-muted">Use a valid Student Registration Number (e.g., 23CSE001).</small>
              </div>
            <?php } ?>
            <div class="form-group">
              <label for="doc_pdf">PDF File</label>
              <input type="file" class="form-control-file" id="doc_pdf" name="doc_pdf" accept="application/pdf" required>
              <small class="form-text text-muted">Upload one consolidated PDF. Files up to 50 MB are accepted and will be auto-compressed to 10 MB. If compression cannot reduce below 10 MB, the upload will be rejected.</small>
            </div>
            <button type="submit" name="upload_pdf" value="1" class="btn btn-primary"><i class="fa fa-upload"></i> Upload</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card mb-3">
        <div class="card-header"><i class="fa fa-university"></i> People's Bank Details</div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <?php if ($_SESSION['user_type'] === 'STU') { ?>
              <input type="hidden" name="student_id" value="<?php echo sp_safe($studentId); ?>">
            <?php } else { ?>
              <div class="form-group">
                <label for="student_id_bank">Student ID</label>
                <input type="text" class="form-control" id="student_id_bank" name="student_id" value="<?php echo sp_safe($studentId); ?>" required>
              </div>
            <?php } ?>
            <div class="form-group">
              <label>Bank Name</label>
              <input type="text" class="form-control" value="People's Bank" readonly>
            </div>
            <div class="form-group">
              <label for="bank_account_no">Account Number</label>
              <input type="text" pattern="[0-9]{6,20}" title="Enter 6-20 digits" class="form-control" id="bank_account_no" name="bank_account_no" required>
            </div>
            <div class="form-group">
              <label for="bank_branch">Branch</label>
              <input type="text" class="form-control" id="bank_branch" name="bank_branch" required>
            </div>
            <div class="form-group">
              <label for="bank_front">Front Page (PDF/JPG/PNG) - optional</label>
              <input type="file" class="form-control-file" id="bank_front" name="bank_front" accept="application/pdf,image/jpeg,image/png">
            </div>
            <button type="submit" name="save_bank" value="1" class="btn btn-success"><i class="fa fa-save"></i> Save Bank Details</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once("../footer.php"); ?>
