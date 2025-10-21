<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

require_roles(['ADM','SAO','DIR','IN3']);
$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$is_sao   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO';
$is_dir   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'DIR';
$is_in3   = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'IN3';
// Fine-grained permissions
$can_edit_profile = ($is_admin || $is_sao || $is_in3);
$can_change_enroll = ($is_admin || $is_sao || $is_in3);
// Legacy flag (used in Hostel tab actions)
$can_mutate = ($is_admin || $is_sao); // IN3 cannot mutate hostel

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

// Ensure profile image column exists (path-based)
@mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_profile_img` VARCHAR(255) NULL");

// Load student ID
$sid = isset($_GET['Sid']) ? trim($_GET['Sid']) : '';
if ($sid === '') {
  header('Location: ' . $base . '/student/ManageStudents.php');
  exit;
}

// Fetch base data
$student = null;
$enroll  = null;
$profileImgPath = null;
$departments = [];
$courses = [];

if ($r = mysqli_query($con, "SELECT * FROM `student` WHERE `student_id`='".mysqli_real_escape_string($con,$sid)."' LIMIT 1")) {
  $student = mysqli_fetch_assoc($r) ?: null;
  mysqli_free_result($r);
}

// Latest enrollment (if any)
$enrollSql = "SELECT e.* , c.course_name, c.department_id FROM student_enroll e 
              LEFT JOIN course c ON c.course_id=e.course_id
              WHERE e.student_id='".mysqli_real_escape_string($con,$sid)."' 
              ORDER BY e.student_enroll_date DESC LIMIT 1";
if ($r = mysqli_query($con, $enrollSql)) {
  $enroll = mysqli_fetch_assoc($r) ?: null;
  mysqli_free_result($r);
}

// Determine current profile image (by path) and build cache-busted URL
if ($student) {
  $tmp = trim((string)($student['student_profile_img'] ?? ''));
  if ($tmp !== '') {
    $abs = realpath(__DIR__ . '/../' . $tmp);
    // If the file exists under the project, use the relative path; otherwise leave null
    if ($abs && file_exists($abs)) { $profileImgPath = $tmp; }
  }
}
// Build cache-busting query using filemtime (prevents stale cached image after update)
$profileImgUrl = null;
if ($profileImgPath) {
  $abs = realpath(__DIR__ . '/../' . $profileImgPath);
  $ver = ($abs && file_exists($abs)) ? (string)@filemtime($abs) : (string)time();
  $profileImgUrl = rtrim($base, '/') . '/' . $profileImgPath . '?v=' . urlencode($ver);
}

// Dropdown data
if ($r = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $departments[] = $row; }
  mysqli_free_result($r);
}
if ($r = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name")) {
  while ($row = mysqli_fetch_assoc($r)) { $courses[] = $row; }
  mysqli_free_result($r);
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $form = isset($_POST['form']) ? $_POST['form'] : '';
  if ($form === 'profile') {
    if (!$can_edit_profile) {
      http_response_code(403);
      echo 'Forbidden: cannot edit profile';
      exit;
    }
    // Update student table core fields
    $fields = [
      'student_title','student_fullname','student_ininame','student_gender','student_civil','student_email',
      'student_nic','student_dob','student_phone','student_whatsapp','student_address','student_zip',
      'student_district','student_divisions','student_provice','student_blood','student_nationality',
      'student_em_name','student_em_address','student_em_phone','student_em_relation'
    ];
    $set = [];
    foreach ($fields as $f) {
      if (!array_key_exists($f, $_POST)) {
        // Field not posted at all: preserve existing value
        $set[] = "`$f` = `$f`";
        continue;
      }
      $raw = $_POST[$f];
      // Normalize type-specific behavior: only date field should use NULL when blank
      if ($f === 'student_dob') {
        if ($raw === '' || $raw === null) {
          $val = 'NULL';
        } else {
          $val = "'".mysqli_real_escape_string($con, $raw)."'";
        }
      } else {
        // For text fields, write empty string instead of NULL to avoid NOT NULL violations
        $val = "'".mysqli_real_escape_string($con, (string)$raw)."'";
      }
      $set[] = "`$f` = $val";
    }
    $sql = "UPDATE `student` SET ".implode(',', $set)." WHERE `student_id`='".mysqli_real_escape_string($con,$sid)."'";
    if (!mysqli_query($con, $sql)) {
      $errors[] = 'Failed to update profile: '.mysqli_error($con);
    } else {
      $messages[] = 'Profile updated successfully';
    }
  }

  // Upload profile image (path-based) with auto-compress and optional data URL support
  if ($form === 'profile_img') {
    if (!$can_edit_profile) { http_response_code(403); echo 'Forbidden'; exit; }

    // Helper: cover-fit to 600x800 JPEG (target <=200KB via quality search)
    if (!function_exists('ue_cover_to_id_jpeg')) {
      function ue_cover_to_id_jpeg($blobOrPath, $isPath = false, $outW = 600, $outH = 800, $quality = 85, $maxBytes = 0) {
        if (!function_exists('imagecreatefromstring')) return false;
        $src = $isPath ? @imagecreatefromstring(@file_get_contents($blobOrPath)) : @imagecreatefromstring($blobOrPath);
        if (!$src) return false;
        $sw = imagesx($src); $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) { imagedestroy($src); return false; }
        $scale = max($outW / $sw, $outH / $sh);
        $rw = (int)ceil($sw * $scale); $rh = (int)ceil($sh * $scale);
        $tmp = imagecreatetruecolor($rw, $rh);
        imagecopyresampled($tmp, $src, 0,0,0,0, $rw,$rh, $sw,$sh);
        imagedestroy($src);
        $dx = (int)max(0, ($rw - $outW) / 2);
        $dy = (int)max(0, ($rh - $outH) / 2);
        $dst = imagecreatetruecolor($outW, $outH);
        imagecopy($dst, $tmp, 0,0, $dx,$dy, $outW,$outH);
        imagedestroy($tmp);
        // If no max size targeting, single pass
        $quality = (int)max(1, min(100, $quality));
        if ($maxBytes <= 0) {
          ob_start(); imagejpeg($dst, null, $quality); imagedestroy($dst); $out = ob_get_clean();
          return $out !== false ? $out : false;
        }
        // Binary search JPEG quality to target max bytes
        $lo = 35; // do not go too low to avoid severe artifacts
        $hi = min(92, max($quality, 80));
        $best = null; $bestLen = PHP_INT_MAX; $iter = 0; $maxIter = 7;
        while ($lo <= $hi && $iter++ < $maxIter) {
          $mid = (int)floor(($lo + $hi) / 2);
          ob_start(); imagejpeg($dst, null, $mid); $buf = ob_get_clean();
          if ($buf === false) { break; }
          $len = strlen($buf);
          // Track best under limit, or smallest overall
          if ($len <= $maxBytes) { $best = $buf; $bestLen = $len; $lo = $mid + 1; }
          else { if ($len < $bestLen) { $best = $buf; $bestLen = $len; } $hi = $mid - 1; }
        }
        // Fallback one more try at low quality if still over
        if ($best === null) { ob_start(); imagejpeg($dst, null, $lo); $best = ob_get_clean(); $bestLen = $best!==false?strlen($best):PHP_INT_MAX; }
        imagedestroy($dst);
        return $best !== false ? $best : false;
      }
    }

    // Determine output directory and target filename (robust creation without realpath dependency)
    $baseImg = __DIR__ . '/../img';
    if (!is_dir($baseImg)) { @mkdir($baseImg, 0777, true); }
    $dir = rtrim($baseImg, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . 'Studnet_profile';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    // As a final guard, if directory still doesn't exist or not writable, disable compression and bail gracefully later
    $safeId = preg_replace('/[^A-Za-z0-9_.-]/', '_', $sid);
    $filename = $safeId . '.jpg'; // normalize to JPEG
    $dest = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    $dataUrl = isset($_POST['profile_img_data']) ? (string)$_POST['profile_img_data'] : '';
    $saved = false;
    $canWriteDest = is_dir($dir) && is_writable($dir);
    $gdAvailable = function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    if ($canWriteDest && $dataUrl !== '' && strpos($dataUrl, 'data:image') === 0) {
      $parts = explode(',', $dataUrl, 2);
      if (count($parts) === 2) {
        $raw = base64_decode($parts[1]);
        if ($raw !== false) {
          if ($gdAvailable) {
            $jpeg = ue_cover_to_id_jpeg($raw, false, 600, 800, 85, 200*1024);
            if ($jpeg !== false) { $saved = @file_put_contents($dest, $jpeg) !== false; }
          }
          // If GD is not available or conversion failed, write the decoded bytes directly (already client-cropped JPEG)
          if (!$saved) { $saved = @file_put_contents($dest, $raw) !== false; }
        }
      }
    }

    // Fallback: handle uploaded file and compress to JPEG
    if (!$saved) {
      if (!isset($_FILES['profile_img']) || $_FILES['profile_img']['error'] !== UPLOAD_ERR_OK) {
        $err = isset($_FILES['profile_img']['error']) ? (int)$_FILES['profile_img']['error'] : UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE) { $errors[] = 'Image exceeds server limit (upload_max_filesize).'; }
        else if ($err === UPLOAD_ERR_FORM_SIZE) { $errors[] = 'Image exceeds form limit.'; }
        else if ($err === UPLOAD_ERR_PARTIAL) { $errors[] = 'Image was only partially uploaded.'; }
        else if ($err === UPLOAD_ERR_NO_FILE) { $errors[] = 'Select an image to upload.'; }
        else if ($err === UPLOAD_ERR_NO_TMP_DIR) { $errors[] = 'Missing temporary folder on server.'; }
        else if ($err === UPLOAD_ERR_CANT_WRITE) { $errors[] = 'Failed to write file to disk.'; }
        else if ($err === UPLOAD_ERR_EXTENSION) { $errors[] = 'Upload stopped by a PHP extension.'; }
        else { $errors[] = 'Image upload failed.'; }
      } else {
        $up = $_FILES['profile_img'];
        $tmp = $up['tmp_name'];
        if ($canWriteDest && $gdAvailable) {
          $jpeg = ue_cover_to_id_jpeg($tmp, true, 600, 800, 85, 200*1024);

          if ($jpeg === false) {
            // As a last resort, move original
            $saved = @move_uploaded_file($tmp, $dest);
          } else {
            $saved = @file_put_contents($dest, $jpeg) !== false;
          }
        } else if ($canWriteDest) {
          // GD not available; just move original
          $saved = @move_uploaded_file($tmp, $dest);
        } else {
          $errors[] = 'Upload directory is not writable.';
        }
      }
    }

    if ($saved) {
      // Remove possible old files with other extensions
      foreach (['jpeg','png','gif','webp'] as $e) {
        $old = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeId . '.' . $e;
        if ($e !== 'jpg' && is_file($old)) { @unlink($old); }
      }
      $rel = 'img/Studnet_profile/' . $filename;
      $qs = "UPDATE `student` SET `student_profile_img`='" . mysqli_real_escape_string($con, $rel) . "' WHERE `student_id`='" . mysqli_real_escape_string($con, $sid) . "'";
      if (!mysqli_query($con, $qs)) {
        $errors[] = 'Image path save failed: ' . mysqli_error($con);
      } else {
        $messages[] = 'Profile image updated (auto-cropped & compressed).';
        $profileImgPath = $rel;
      }
    } else if (empty($errors)) {
      $errors[] = 'Failed to process and save the image.';
    }
  }

  if ($form === 'enroll') {
    if (!$can_change_enroll) {
      http_response_code(403);
      echo 'Forbidden: cannot change enrollment';
      exit;
    }
    // Update or insert latest enrollment
    $course_id = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
    $course_mode = isset($_POST['course_mode']) ? trim($_POST['course_mode']) : '';
    $academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
    $student_enroll_date = isset($_POST['student_enroll_date']) ? trim($_POST['student_enroll_date']) : '';
    $student_enroll_exit_date = isset($_POST['student_enroll_exit_date']) ? trim($_POST['student_enroll_exit_date']) : '';
    $student_enroll_status = isset($_POST['student_enroll_status']) ? trim($_POST['student_enroll_status']) : '';

    // Server-side restriction for IN3: can only change course within SAME department and cannot change other fields
    if ($is_in3) {
      // Determine current department of student's latest enrollment
      $curDeptId = null;
      if ($r = mysqli_query($con, "SELECT c.department_id FROM student_enroll e LEFT JOIN course c ON c.course_id=e.course_id WHERE e.student_id='".mysqli_real_escape_string($con,$sid)."' ORDER BY e.student_enroll_date DESC, e.id DESC LIMIT 1")) {
        if ($tmp = mysqli_fetch_assoc($r)) { $curDeptId = $tmp['department_id'] ?? null; }
        mysqli_free_result($r);
      }
      // Department of target course
      $targetDeptId = null;
      if ($course_id !== '') {
        if ($r = mysqli_query($con, "SELECT department_id FROM course WHERE course_id='".mysqli_real_escape_string($con,$course_id)."' LIMIT 1")) {
          if ($tmp = mysqli_fetch_assoc($r)) { $targetDeptId = $tmp['department_id'] ?? null; }
          mysqli_free_result($r);
        }
      }
      if (!$curDeptId || !$targetDeptId || (string)$curDeptId !== (string)$targetDeptId) {
        $errors[] = 'You can only change the course within the same department.';
        $_SESSION['flash_errors'] = $errors; header('Location: ' . $base . '/student/StudentUnifiedEdit.php?Sid='.urlencode($sid)); exit;
      }
      // Force other fields to remain as previous values for IN3
      $rs = mysqli_query($con, "SELECT * FROM student_enroll WHERE student_id='".mysqli_real_escape_string($con,$sid)."' ORDER BY student_enroll_date DESC, id DESC LIMIT 1");
      $prev = $rs ? mysqli_fetch_assoc($rs) : null; if ($rs) mysqli_free_result($rs);
      if ($prev) {
        $course_mode = $prev['course_mode'] ?? $course_mode;
        $academic_year = $prev['academic_year'] ?? $academic_year;
        $student_enroll_date = $prev['student_enroll_date'] ?? $student_enroll_date;
        $student_enroll_exit_date = $prev['student_enroll_exit_date'] ?? $student_enroll_exit_date;
        $student_enroll_status = $prev['student_enroll_status'] ?? $student_enroll_status;
      }
    }

    // Upsert to avoid duplicate key errors and always target the composite key
    $ins = "INSERT INTO student_enroll(
              student_id, course_id, course_mode, academic_year, student_enroll_date, student_enroll_exit_date, student_enroll_status
            ) VALUES (
              '".mysqli_real_escape_string($con,$sid)."',
              '".mysqli_real_escape_string($con,$course_id)."',
              '".mysqli_real_escape_string($con,$course_mode)."',
              '".mysqli_real_escape_string($con,$academic_year)."',
              '".mysqli_real_escape_string($con,$student_enroll_date)."',
              ".($student_enroll_exit_date!==''?"'".mysqli_real_escape_string($con,$student_enroll_exit_date)."'":"NULL").",
              '".mysqli_real_escape_string($con,$student_enroll_status)."'
            )
            ON DUPLICATE KEY UPDATE
              course_mode=VALUES(course_mode),
              student_enroll_date=VALUES(student_enroll_date),
              student_enroll_exit_date=VALUES(student_enroll_exit_date),
              student_enroll_status=VALUES(student_enroll_status)";
    if (!mysqli_query($con, $ins)) { $errors[] = 'Failed to save enrollment: '.mysqli_error($con); }
    else {
      $messages[] = 'Enrollment saved successfully';
      // If set to Dropout, inactivate student and disable login
      if (strcasecmp($student_enroll_status, 'Dropout') === 0) {
        // Update student status to Inactive (string schema)
        if ($st = mysqli_prepare($con, "UPDATE student SET student_status='Inactive' WHERE student_id=?")) {
          mysqli_stmt_bind_param($st, 's', $sid);
          mysqli_stmt_execute($st);
          mysqli_stmt_close($st);
        }
        // Deactivate user login
        if ($us = mysqli_prepare($con, "UPDATE `user` SET `user_active`=0 WHERE `user_name`=?")) {
          mysqli_stmt_bind_param($us, 's', $sid);
          mysqli_stmt_execute($us);
          mysqli_stmt_close($us);
        }
      }
    }
  }

  // Redirect PRG
  if ($messages || $errors) {
    $_SESSION['flash_messages'] = $messages;
    $_SESSION['flash_errors'] = $errors;
    header('Location: ' . $base . '/student/StudentUnifiedEdit.php?Sid='.urlencode($sid));
    exit;
  }
}

// Flash
if (!empty($_SESSION['flash_messages'])) { $messages = $_SESSION['flash_messages']; unset($_SESSION['flash_messages']); }
if (!empty($_SESSION['flash_errors'])) { $errors = $_SESSION['flash_errors']; unset($_SESSION['flash_errors']); }

$title = 'Unified Student Edit | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<div class="container-fluid px-0 px-sm-2 px-md-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb bg-white shadow-sm mb-2">
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/dashboard/index.php">Dashboard</a></li>
      <li class="breadcrumb-item"><a href="<?php echo $base; ?>/student/ManageStudents.php">Students</a></li>
      <li class="breadcrumb-item active" aria-current="page">Unified Edit</li>
    </ol>
  </nav>
  <h4 class="d-flex align-items-center mb-3"><i class="fas fa-user-cog text-primary mr-2"></i> Unified Edit: <?php echo h($sid); ?></h4>

  <?php foreach ($messages as $m): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo h($e); ?></div><?php endforeach; ?>

  <ul class="nav nav-tabs" id="ueTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Profile</a></li>
    <li class="nav-item"><a class="nav-link" id="enroll-tab" data-toggle="tab" href="#enroll" role="tab">Enrollment</a></li>
    <li class="nav-item"><a class="nav-link" id="docs-tab" data-toggle="tab" href="#documents" role="tab">Documents</a></li>
    <li class="nav-item"><a class="nav-link" id="bank-tab" data-toggle="tab" href="#bank" role="tab">Bank Details</a></li>
  </ul>
  <div class="tab-content border-left border-right border-bottom p-3 bg-white" id="ueContent">
    <!-- Profile Tab -->
    <div class="tab-pane fade show active" id="profile" role="tabpanel">
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div></div>
        <div class="text-right">
          <?php if ($profileImgUrl): ?>
            <img src="<?php echo h($profileImgUrl); ?>" alt="Profile Photo" class="img-thumbnail" style="width:120px;height:160px;object-fit:cover;">
          <?php endif; ?>
          <?php if ($can_edit_profile): ?>
            <form method="post" enctype="multipart/form-data" class="mt-2" id="profileImgForm">
              <input type="hidden" name="form" value="profile_img">
              <input type="hidden" name="profile_img_data" id="profile_img_data">
              <div class="form-row align-items-center">
                <div class="col-auto">
                  <input type="file" name="profile_img" id="profile_img_file" accept="image/*" class="form-control-file" required>
                </div>
                <div class="col-auto">
                  <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-upload"></i> Upload</button>
                </div>
              </div>
              <div class="mt-2">
                <img id="profile_img_preview" class="img-thumbnail" style="width:120px;height:160px;object-fit:cover;display:none;" alt="Preview" />
                <small class="form-text text-muted">Image will be auto-cropped to ID size (3:4) around the face and compressed before upload. You can also adjust the crop before saving.</small>
              </div>
            </form>
            <!-- Cropper.js modal for manual adjustment -->
            <link rel="stylesheet" href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css">
            <div class="modal fade" id="cropperModal" tabindex="-1" role="dialog" aria-labelledby="cropperModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="cropperModalLabel">Adjust Photo (3:4)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                  </div>
                  <div class="modal-body">
                    <div class="w-100" style="max-height:70vh;">
                      <img id="cropper_image" src="" alt="Crop" style="max-width:100%; display:block;">
                    </div>
                    <div class="btn-toolbar mt-2" role="toolbar">
                      <div class="btn-group mr-2" role="group">
                        <button type="button" class="btn btn-sm btn-secondary" id="cropZoomIn">Zoom +</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="cropZoomOut">Zoom -</button>
                      </div>
                      <div class="btn-group mr-2" role="group">
                        <button type="button" class="btn btn-sm btn-secondary" id="cropRotateL">Rotate -10°</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="cropRotateR">Rotate +10°</button>
                      </div>
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cropReset">Reset</button>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyCrop">Apply Crop</button>
                  </div>
                </div>
              </div>
            </div>
            <script src="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js"></script>
            <script>
              (function(){
                const form = document.getElementById('profileImgForm');
                const fileInput = document.getElementById('profile_img_file');
                const hidden = document.getElementById('profile_img_data');
                const preview = document.getElementById('profile_img_preview');
                const OUT_W = 600, OUT_H = 800;
                let cropper = null;
                const cropperModal = document.getElementById('cropperModal');
                const cropperImg = document.getElementById('cropper_image');

                async function detectFaceRect(img){
                  try {
                    if ('FaceDetector' in window) {
                      const fd = new window.FaceDetector({ fastMode: true, maxDetectedFaces: 1 });
                      const faces = await fd.detect(img);
                      if (faces && faces.length) {
                        const b = faces[0].boundingBox; // {x,y,width,height}
                        return { x: b.x, y: b.y, w: b.width, h: b.height };
                      }
                    }
                  } catch(e) { /* ignore and fallback */ }
                  return null;
                }

                function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }

                async function processFile(file){
                  return new Promise((resolve, reject) => {
                    const fr = new FileReader();
                    fr.onload = async () => {
                      const url = fr.result;
                      const img = new Image();
                      img.onload = async () => {
                        // Compute crop rect
                        const sw = img.naturalWidth; const sh = img.naturalHeight;
                        const face = await detectFaceRect(img);
                        let cx, cy, cw, ch;
                        const targetRatio = OUT_W / OUT_H; // 0.75
                        if (face) {
                          // ID-card oriented crop: face ~55% of output height, slight upward bias
                          const fx = face.x, fy = face.y, fw = face.w, fh = face.h;
                          // Desired crop height so face occupies ~55% of output height
                          let desiredCh = fh / 0.55; // ~1.82 * fh
                          // Clamp to sensible range to avoid over-zoom/out
                          desiredCh = Math.max(fh * 1.6, Math.min(fh * 3.0, desiredCh));
                          // Ensure not exceeding source bounds
                          desiredCh = Math.min(desiredCh, sh);
                          let desiredCw = desiredCh * targetRatio;
                          if (desiredCw > sw) {
                            // Reduce proportionally if too wide
                            const scale = sw / desiredCw;
                            desiredCw = sw;
                            desiredCh = desiredCh * scale;
                          }
                          cw = desiredCw; ch = desiredCh;
                          // Center X at face center; Y with slight upward bias (place eyes ~0.42 of crop height)
                          cx = fx + fw / 2;
                          cy = fy + fh * 0.6; // bias upwards for headroom
                        } else {
                          // Center crop to 3:4
                          if (sw / sh > targetRatio) { // too wide
                            ch = sh; cw = ch * targetRatio; cx = sw/2; cy = sh/2;
                          } else {
                            cw = sw; ch = cw / targetRatio; cx = sw/2; cy = sh/2;
                          }
                        }
                        // Ensure crop within bounds
                        let x = clamp(cx - cw/2, 0, sw - cw);
                        let y = clamp(cy - ch/2, 0, sh - ch);

                        // Draw to output canvas
                        const canvas = document.createElement('canvas');
                        canvas.width = OUT_W; canvas.height = OUT_H;
                        const ctx = canvas.getContext('2d');
                        ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
                        ctx.drawImage(img, x, y, cw, ch, 0, 0, OUT_W, OUT_H);
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                        if (preview) { preview.src = dataUrl; preview.style.display = 'inline-block'; }
                        resolve(dataUrl);
                      };
                      img.onerror = () => reject(new Error('Invalid image'));
                      img.src = url;
                    };
                    fr.onerror = () => reject(new Error('Failed to read file'));
                    fr.readAsDataURL(file);
                  });
                }

                function openCropper(url){
                  if (!cropperImg || !window.jQuery) return false; // rely on Bootstrap jQuery for modal
                  cropperImg.src = url;
                  // Ensure previous instance destroyed
                  if (cropper) { try { cropper.destroy(); } catch(e) {} cropper = null; }
                  $('#cropperModal').modal('show');
                  $('#cropperModal').on('shown.bs.modal', function(){
                    cropper = new Cropper(cropperImg, {
                      aspectRatio: OUT_W / OUT_H,
                      viewMode: 1,
                      autoCropArea: 0.9,
                      background: false,
                      movable: true,
                      zoomable: true,
                      rotatable: true,
                      minCropBoxWidth: 120,
                      minCropBoxHeight: 160
                    });
                  }).on('hidden.bs.modal', function(){
                    if (cropper) { try { cropper.destroy(); } catch(e) {} cropper = null; }
                  });
                  // Controls
                  document.getElementById('cropZoomIn').onclick = () => { if (cropper) cropper.zoom(0.1); };
                  document.getElementById('cropZoomOut').onclick = () => { if (cropper) cropper.zoom(-0.1); };
                  document.getElementById('cropRotateL').onclick = () => { if (cropper) cropper.rotate(-10); };
                  document.getElementById('cropRotateR').onclick = () => { if (cropper) cropper.rotate(10); };
                  document.getElementById('cropReset').onclick = () => { if (cropper) cropper.reset(); };
                  document.getElementById('applyCrop').onclick = () => {
                    if (!cropper) return;
                    const canvas = cropper.getCroppedCanvas({ width: OUT_W, height: OUT_H, fillColor: '#fff' });
                    if (canvas) {
                      const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
                      hidden.value = dataUrl;
                      if (preview) { preview.src = dataUrl; preview.style.display = 'inline-block'; }
                    }
                    $('#cropperModal').modal('hide');
                  };
                  return true;
                }

                fileInput && fileInput.addEventListener('change', async function(){
                  hidden.value = '';
                  const f = this.files && this.files[0];
                  if (!f) return;
                  const reader = new FileReader();
                  reader.onload = async () => {
                    const url = reader.result;
                    // If Cropper is available and Bootstrap modal present, use manual adjust; else auto-process
                    if (window.Cropper && window.jQuery && openCropper(url)) {
                      // wait for user to click Apply
                    } else {
                      try {
                        const dataUrl = await processFile(f);
                        hidden.value = dataUrl;
                      } catch(e) {
                        console.warn('Preprocess failed, will upload original:', e);
                      }
                    }
                  };
                  reader.readAsDataURL(f);
                });

                form && form.addEventListener('submit', function(e){
                  // If we have generated a data URL, server will use it. Otherwise proceed as-is.
                  if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    e.preventDefault(); alert('Select an image to upload.'); return false;
                  }
                  if (fileInput && fileInput.files && fileInput.files[0] && fileInput.files[0].size > 10*1024*1024) {
                    e.preventDefault(); alert('Image is larger than 10MB. Please choose a smaller image.'); return false;
                  }
                  // If a compressed data URL is present, avoid sending the original large file
                  // to prevent 413 (Request Entity Too Large) at the Nginx layer.
                  // Keep the file input enabled so server has a fallback even if it cannot process data URL
                });
              })();
            </script>
          <?php endif; ?>
        </div>
      </div>
      <form method="post">
        <input type="hidden" name="form" value="profile">
        <div class="card mb-3">
          <div class="card-header bg-white"><strong>Personal Details</strong></div>
          <div class="card-body">
            <div class="form-row">
          <div class="form-group col-md-2">
            <label>Title</label>
            <input type="text" class="form-control" name="student_title" value="<?php echo h($student['student_title'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-10">
            <label>Full Name</label>
            <input type="text" class="form-control" name="student_fullname" value="<?php echo h($student['student_fullname'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
            </div>
            <div class="form-row">
          <div class="form-group col-md-6">
            <label>Name with Initials</label>
            <input type="text" class="form-control" name="student_ininame" value="<?php echo h($student['student_ininame'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-2">
            <label>Gender</label>
            <select class="form-control" name="student_gender" <?php echo $can_edit_profile?'':'disabled'; ?>>
              <?php foreach (["Male","Female","Other"] as $g): ?>
                <option value="<?php echo h($g); ?>" <?php echo (($student['student_gender'] ?? '')===$g?'selected':''); ?>><?php echo h($g); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Civil Status</label>
            <select class="form-control" name="student_civil" <?php echo $can_edit_profile?'':'disabled'; ?>>
              <?php foreach (["Single","Married"] as $c): ?>
                <option value="<?php echo h($c); ?>" <?php echo (($student['student_civil'] ?? '')===$c?'selected':''); ?>><?php echo h($c); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>NIC</label>
            <input type="text" class="form-control" name="student_nic" value="<?php echo h($student['student_nic'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
            </div>
            <div class="form-row">
          <div class="form-group col-md-4">
            <label>Email</label>
            <input type="email" class="form-control" name="student_email" value="<?php echo h($student['student_email'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-4">
            <label>Phone</label>
            <input type="text" class="form-control" name="student_phone" value="<?php echo h($student['student_phone'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-4">
            <label>WhatsApp</label>
            <input type="text" class="form-control" name="student_whatsapp" value="<?php echo h($student['student_whatsapp'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
            </div>
            <div class="form-row">
          <div class="form-group col-md-3">
            <label>Date of Birth</label>
            <input type="date" class="form-control" name="student_dob" value="<?php echo h($student['student_dob'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Nationality</label>
            <input type="text" class="form-control" name="student_nationality" value="<?php echo h($student['student_nationality'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>ZIP</label>
            <input type="text" class="form-control" name="student_zip" value="<?php echo h($student['student_zip'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Blood</label>
            <select class="form-control" name="student_blood" <?php echo $can_edit_profile?'':'disabled'; ?>>
              <?php $bloodOpts = ['',"A+","A-","B+","B-","AB+","AB-","O+","O-"]; $curBlood = $student['student_blood'] ?? ''; foreach($bloodOpts as $b): ?>
                <option value="<?php echo h($b); ?>" <?php echo ($curBlood===$b?'selected':''); ?>><?php echo h($b===''?'-- Select --':$b); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
            </div>
            <div class="form-row">
          <div class="form-group col-md-6">
            <label>Address</label>
            <input type="text" class="form-control" name="student_address" value="<?php echo h($student['student_address'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Province</label>
            <select class="form-control" name="student_provice" id="province_select" <?php echo $can_edit_profile?'':'disabled'; ?>></select>
          </div>
          <div class="form-group col-md-3">
            <label>District</label>
            <select class="form-control" name="student_district" id="district_select" <?php echo $can_edit_profile?'':'disabled'; ?>></select>
          </div>
            </div>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header bg-white"><strong>Parent/Guardian Details</strong></div>
          <div class="card-body">
            <div class="form-row">
          <div class="form-group col-md-3">
            <label>Emergency Name</label>
            <input type="text" class="form-control" name="student_em_name" value="<?php echo h($student['student_em_name'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Emergency Address</label>
            <input type="text" class="form-control" name="student_em_address" value="<?php echo h($student['student_em_address'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Emergency Phone</label>
            <input type="text" class="form-control" name="student_em_phone" value="<?php echo h($student['student_em_phone'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
            </div>
            <div class="form-row">
          <div class="form-group col-md-3">
            <label>Emergency Relation</label>
            <input type="text" class="form-control" name="student_em_relation" value="<?php echo h($student['student_em_relation'] ?? ''); ?>" <?php echo $can_edit_profile?'':'disabled'; ?>>
          </div>
            </div>
          </div>
        </div>
        <?php if ($can_edit_profile): ?>
        <script>
          (function(){
            var provinces = [
              'Northern','Eastern','Western','Southern','Central','North Western','Uva','North Central','Sabaragamuwa'
            ];
            var districtsByProvince = {
              'Northern': ['Jaffna','Kilinochchi','Mannar','Mullaitivu','Vavuniya'],
              'Eastern': ['Trincomalee','Batticaloa','Ampara'],
              'Western': ['Colombo','Gampaha','Kalutara'],
              'Southern': ['Galle','Matara','Hambantota'],
              'Central': ['Kandy','Matale','Nuwara Eliya'],
              'North Western': ['Kurunegala','Puttalam'],
              'Uva': ['Badulla','Monaragala'],
              'North Central': ['Anuradhapura','Polonnaruwa'],
              'Sabaragamuwa': ['Ratnapura','Kegalle']
            };
            var provSel = document.getElementById('province_select');
            var distSel = document.getElementById('district_select');
            if (!provSel || !distSel) return;
            function fillProvinces(){
              provSel.innerHTML='';
              var curProv = <?php echo json_encode($student['student_provice'] ?? ''); ?>;
              var opt = document.createElement('option'); opt.value=''; opt.text='-- Select Province --'; provSel.add(opt);
              provinces.forEach(function(p){ var o=document.createElement('option'); o.value=p; o.text=p; if (p===curProv) o.selected=true; provSel.add(o); });
            }
            function fillDistricts(){
              distSel.innerHTML='';
              var curDist = <?php echo json_encode($student['student_district'] ?? ''); ?>;
              var p = provSel.value;
              var list = districtsByProvince[p] || [];
              var opt = document.createElement('option'); opt.value=''; opt.text='-- Select District --'; distSel.add(opt);
              list.forEach(function(d){ var o=document.createElement('option'); o.value=d; o.text=d; if (d===curDist) o.selected=true; distSel.add(o); });
            }
            provSel.addEventListener('change', function(){ fillDistricts(); });
            fillProvinces();
            fillDistricts();
          })();
        </script>
        <?php endif; ?>
        <?php if ($can_edit_profile): ?>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Profile</button>
        <?php endif; ?>
      </form>
    </div>

    <!-- Enrollment Tab -->
    <div class="tab-pane fade" id="enroll" role="tabpanel">
      <form method="post">
        <input type="hidden" name="form" value="enroll">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Course</label>
            <select class="form-control" name="course_id" <?php echo $can_change_enroll?'':'disabled'; ?>>
              <?php $enrollDeptId = $enroll['department_id'] ?? null; foreach ($courses as $c): ?>
                <?php if ($is_in3 && $enrollDeptId && (string)$c['department_id'] !== (string)$enrollDeptId) continue; ?>
                <option value="<?php echo h($c['course_id']); ?>" <?php echo (($enroll['course_id'] ?? '')===$c['course_id']?'selected':''); ?>><?php echo h($c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Mode</label>
            <select class="form-control" name="course_mode" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
              <?php foreach (["Full","Part"] as $m): ?>
                <option value="<?php echo h($m); ?>" <?php echo (($enroll['course_mode'] ?? '')===$m?'selected':''); ?>><?php echo h($m==='Full'?'Full Time':'Part Time'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Academic Year</label>
            <input type="text" class="form-control" name="academic_year" value="<?php echo h($enroll['academic_year'] ?? ''); ?>" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-2">
            <label>Status</label>
            <select class="form-control" name="student_enroll_status" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
              <?php foreach (["Following","Completed","Dropout","Long Absent"] as $st): ?>
                <option value="<?php echo h($st); ?>" <?php echo (($enroll['student_enroll_status'] ?? '')===$st?'selected':''); ?>><?php echo h($st); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Enroll Date</label>
            <input type="date" class="form-control" name="student_enroll_date" value="<?php echo h($enroll['student_enroll_date'] ?? ''); ?>" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-3">
            <label>Exit Date</label>
            <input type="date" class="form-control" name="student_enroll_exit_date" value="<?php echo h($enroll['student_enroll_exit_date'] ?? ''); ?>" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
          </div>
        </div>
        <?php if ($can_change_enroll): ?>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Enrollment</button>
        <?php endif; ?>
      </form>
    </div>

    <!-- Documents Tab -->
    <div class="tab-pane fade" id="documents" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div class="mb-2">Manage student documents.</div>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>/student/StudentDocuments.php?Sid=<?php echo urlencode($sid); ?>">Open Documents</a>
        </div>
      </div>
    </div>

    <!-- Bank Details Tab -->
    <div class="tab-pane fade" id="bank" role="tabpanel">
      <div class="card">
        <div class="card-body">
          <div class="mb-2">Edit student bank details.</div>
          <a class="btn btn-sm btn-outline-primary" href="<?php echo $base; ?>/finance/StudentBankDetails.php?Sid=<?php echo urlencode($sid); ?>">Open Bank Details</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
