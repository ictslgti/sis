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

// Lightweight API: next student id suggestion for a course + academic year
if (isset($_GET['action']) && $_GET['action']==='next_sid') {
  header('Content-Type: application/json');
  if (!is_any(['ADM','SAO','IN3'])) { echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
  $courseQ = isset($_GET['course_id']) ? trim($_GET['course_id']) : '';
  $ayQ = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
  if ($courseQ==='' || $ayQ==='') { echo json_encode(['ok'=>false,'error'=>'missing_params']); exit; }
  $courseEsc = mysqli_real_escape_string($con, $courseQ);
  $ayEsc = mysqli_real_escape_string($con, $ayQ);
  $sql = "SELECT se.student_id FROM student_enroll se WHERE se.course_id='${courseEsc}' AND se.academic_year='${ayEsc}' AND se.student_id IS NOT NULL AND se.student_id<>'' ORDER BY se.student_id DESC LIMIT 1";
  $last = null;
  if ($rs = mysqli_query($con, $sql)) { $row = mysqli_fetch_assoc($rs); if ($row) { $last = $row['student_id']; } mysqli_free_result($rs); }
  if (!$last) { echo json_encode(['ok'=>true,'next_id'=>'','note'=>'no_existing_ids_for_course_year']); exit; }
  // Extract trailing digits and preserve prefix and zero padding
  $prefix = $last; $num = '';
  if (preg_match('/^(.*?)(\d+)$/', (string)$last, $m)) { $prefix = $m[1]; $num = $m[2]; }
  if ($num==='') { echo json_encode(['ok'=>true,'next_id'=>'','note'=>'last_id_has_no_trailing_number','last'=>$last]); exit; }
  $len = strlen($num); $n = (int)$num + 1; $next = $prefix . str_pad((string)$n, $len, '0', STR_PAD_LEFT);
  echo json_encode(['ok'=>true,'next_id'=>$next,'last_id'=>$last]);
  exit;
}

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

// Academic years for selection
$academicYears = [];
if ($r = mysqli_query($con, "SELECT academic_year FROM academic ORDER BY academic_year DESC")) {
  while ($row = mysqli_fetch_assoc($r)) { $academicYears[] = $row['academic_year']; }
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
    $dir = rtrim($baseImg, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . 'student_profile';
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
      $rel = 'img/student_profile/' . $filename;
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

      // Optional: assign next student ID from modal
      if (($is_admin || $is_sao) && isset($_POST['assign_next_sid']) && $_POST['assign_next_sid'] === '1') {
        $proposed = isset($_POST['new_student_id']) ? trim($_POST['new_student_id']) : '';
        if ($proposed !== '' && $proposed !== $sid) {
          // Ensure proposed SID not already in use
          $exists = false;
          if ($stC = mysqli_prepare($con, "SELECT 1 FROM student WHERE student_id=? LIMIT 1")) {
            mysqli_stmt_bind_param($stC, 's', $proposed);
            mysqli_stmt_execute($stC);
            mysqli_stmt_store_result($stC);
            $exists = mysqli_stmt_num_rows($stC) > 0;
            mysqli_stmt_close($stC);
          }
          if ($exists) {
            $errors[] = 'Cannot assign next ID; it already exists.';
          } else {
            // Best-effort transactional rename across key tables
            $ok = true; $err = '';
            mysqli_begin_transaction($con);
            // student
            if ($ok) { $ok = (bool)mysqli_query($con, "UPDATE student SET student_id='".mysqli_real_escape_string($con,$proposed)."' WHERE student_id='".mysqli_real_escape_string($con,$sid)."' LIMIT 1"); if(!$ok){$err=mysqli_error($con);} }
            // student_enroll
            if ($ok) { $ok = (bool)mysqli_query($con, "UPDATE student_enroll SET student_id='".mysqli_real_escape_string($con,$proposed)."' WHERE student_id='".mysqli_real_escape_string($con,$sid)."'"); if(!$ok){$err=mysqli_error($con);} }
            // user table username
            if ($ok) { $ok = (bool)mysqli_query($con, "UPDATE `user` SET user_name='".mysqli_real_escape_string($con,$proposed)."' WHERE user_name='".mysqli_real_escape_string($con,$sid)."'"); if(!$ok){$err=mysqli_error($con);} }
            // attendance (if exists)
            @mysqli_query($con, "UPDATE attendance SET student_id='".mysqli_real_escape_string($con,$proposed)."' WHERE student_id='".mysqli_real_escape_string($con,$sid)."'");
            // pays (if exists)
            @mysqli_query($con, "UPDATE pays SET student_id='".mysqli_real_escape_string($con,$proposed)."' WHERE student_id='".mysqli_real_escape_string($con,$sid)."'");

            if ($ok) {
              mysqli_commit($con);
              $messages[] = 'Student ID updated to '.$proposed;
              $sid = $proposed; // update redirect target
            } else {
              mysqli_rollback($con);
              $errors[] = 'Failed to assign next ID. '.$err;
            }
          }
        }
      }
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

  // Handle Bank details update (SAO/ADM)
  if ($form === 'bank') {
    if (!($is_admin || $is_sao)) { http_response_code(403); echo 'Forbidden: cannot edit bank details'; exit; }
    // Ensure columns exist (best-effort)
    @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `bank_name` VARCHAR(128) NULL");
    @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `bank_account_no` VARCHAR(32) NULL");
    @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `bank_branch` VARCHAR(128) NULL");
    @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `bank_frontsheet_path` VARCHAR(255) NULL");

    $acc = trim($_POST['bank_account_no'] ?? '');
    $br  = trim($_POST['bank_branch'] ?? '');
    $bankName = "People's Bank";
    $frontRelPath = null;

    // Optional front page upload
    if (isset($_FILES['bank_front']) && $_FILES['bank_front']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['bank_front']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['bank_front']['tmp_name'];
        $size = (int)$_FILES['bank_front']['size'];
        $type = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
        if ($size > 0 && $size <= 50*1024*1024) {
          $ok = false; $ext = 'dat';
          if (stripos((string)$type, 'pdf') !== false) { $ok = true; $ext = 'pdf'; }
          if (stripos((string)$type, 'jpeg') !== false || stripos((string)$type, 'jpg') !== false) { $ok = true; $ext = 'jpg'; }
          if (stripos((string)$type, 'png') !== false) { $ok = true; $ext = 'png'; }
          if ($ok) {
            $destDir = __DIR__ . '/documentation';
            if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
            $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $sid);
            $destPath = $destDir . '/' . $safeId . '_bankfront.' . $ext;
            if (!@move_uploaded_file($tmp, $destPath)) {
              $data = @file_get_contents($tmp);
              if ($data !== false) { @file_put_contents($destPath, $data); }
            }
            if (is_file($destPath)) { $frontRelPath = 'student/documentation/' . $safeId . '_bankfront.' . $ext; }
          }
        }
      }
    }

    // Update only if there is data to change
    if ($acc !== '' || $br !== '' || $frontRelPath) {
      $sql = 'UPDATE student SET bank_name=?, bank_account_no=?, bank_branch=?' . ($frontRelPath ? ', bank_frontsheet_path=?' : '') . ' WHERE student_id=? LIMIT 1';
      if ($st = mysqli_prepare($con, $sql)) {
        if ($frontRelPath) { mysqli_stmt_bind_param($st, 'sssss', $bankName, $acc, $br, $frontRelPath, $sid); }
        else { mysqli_stmt_bind_param($st, 'ssss', $bankName, $acc, $br, $sid); }
        if (mysqli_stmt_execute($st)) {
          $messages[] = 'Bank details updated.';
          // refresh student row
          if ($r = mysqli_query($con, "SELECT * FROM `student` WHERE `student_id`='".mysqli_real_escape_string($con,$sid)."' LIMIT 1")) {
            $student = mysqli_fetch_assoc($r) ?: $student; mysqli_free_result($r);
          }
        } else { $errors[] = 'Failed to update bank details: '.h(mysqli_error($con)); }
        mysqli_stmt_close($st);
      } else { $errors[] = 'Failed to prepare bank update.'; }
    } else {
      $errors[] = 'Provide Account Number or Branch or a file to update.';
    }
  }

  if ($form === 'reset_password') {
    if (!($is_admin || $is_sao)) {
      http_response_code(403);
      echo 'Forbidden: cannot reset passwords';
      exit;
    }
    $new = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    $rep = isset($_POST['repeat_password']) ? (string)$_POST['repeat_password'] : '';
    if ($new === '' || $rep === '') {
      $errors[] = 'Password fields cannot be empty';
    } elseif ($new !== $rep) {
      $errors[] = 'Passwords do not match';
    } elseif (strlen($new) < 8) {
      $errors[] = 'Password too short (min 8 characters)';
    } else {
      $exists = false;
      if ($rs = mysqli_prepare($con, "SELECT 1 FROM `user` WHERE `user_name`=? LIMIT 1")) {
        mysqli_stmt_bind_param($rs, 's', $sid);
        mysqli_stmt_execute($rs);
        mysqli_stmt_store_result($rs);
        $exists = mysqli_stmt_num_rows($rs) > 0;
        mysqli_stmt_close($rs);
      }
      if (!$exists) {
        $errors[] = 'User account not found for this student.';
      } else {
        $hash = hash('sha256', $new);
        if ($st = mysqli_prepare($con, "UPDATE `user` SET `user_password_hash`=? WHERE `user_name`=?")) {
          mysqli_stmt_bind_param($st, 'ss', $hash, $sid);
          if (mysqli_stmt_execute($st)) {
            $messages[] = 'Password reset successfully.';
          } else {
            $errors[] = 'Failed to reset password.';
          }
          mysqli_stmt_close($st);
        } else {
          $errors[] = 'Database error while preparing reset.';
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
      <li class="breadcrumb-item active" aria-current="page">Student Edit</li>
    </ol>
  </nav>
  <h4 class="d-flex align-items-center mb-3"><i class="fas fa-user-cog text-primary mr-2"></i> Student Edit: <?php echo h($sid); ?></h4>

  <?php foreach ($messages as $m): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endforeach; ?>
  <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo h($e); ?></div><?php endforeach; ?>

  <ul class="nav nav-tabs" id="ueTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Profile</a></li>
    <li class="nav-item"><a class="nav-link" id="enroll-tab" data-toggle="tab" href="#enroll" role="tab">Enrollment</a></li>
    <li class="nav-item"><a class="nav-link" id="docs-tab" data-toggle="tab" href="#documents" role="tab">Documents</a></li>
    <li class="nav-item"><a class="nav-link" id="bank-tab" data-toggle="tab" href="#bank" role="tab">Bank Details</a></li>
    <?php if ($is_admin || $is_sao): ?>
    <li class="nav-item"><a class="nav-link" id="pwd-tab" data-toggle="tab" href="#password" role="tab">Password</a></li>
    <?php endif; ?>
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
                  if (hidden && typeof hidden.value === 'string' && hidden.value.indexOf('data:image') === 0) {
                    fileInput.disabled = true;
                  }
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
      <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#nextSidModal">
          <i class="fas fa-exchange-alt mr-1"></i> Change Enrollment / Get Next ID
        </button>
      </div>
      <form method="post" id="enrollForm">
        <input type="hidden" name="form" value="enroll">
        <input type="hidden" name="assign_next_sid" id="assign_next_sid" value="0">
        <input type="hidden" name="new_student_id" id="new_student_id" value="">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Course</label>
            <select class="form-control" id="enroll_course_id" name="course_id" <?php echo $can_change_enroll?'':'disabled'; ?>>
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
            <input type="text" class="form-control" id="enroll_academic_year" name="academic_year" value="<?php echo h($enroll['academic_year'] ?? ''); ?>" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
          </div>
          <div class="form-group col-md-2">
            <label>Status</label>
            <select class="form-control" name="student_enroll_status" <?php echo ($can_change_enroll && !$is_in3)?'':'disabled'; ?>>
              <?php foreach (["Following","Internship","Completed","Dropout"] as $st): ?>
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
        <div class="card-header bg-white"><strong>People's Bank Details</strong></div>
        <div class="card-body">
          <?php if (!empty($errors)): ?><div class="alert alert-danger py-2"><?php echo h(implode(' | ', $errors)); ?></div><?php endif; ?>
          <?php if (!empty($messages)): ?><div class="alert alert-success py-2"><?php echo h(implode(' | ', $messages)); ?></div><?php endif; ?>
          <form method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="form" value="bank">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Bank</label>
                <input type="text" class="form-control" value="People's Bank" readonly>
              </div>
              <div class="form-group col-md-4">
                <label>Account Number</label>
                <input type="text" class="form-control" name="bank_account_no" pattern="[0-9]{6,20}" title="Enter 6-20 digits" value="<?php echo h($student['bank_account_no'] ?? ''); ?>" <?php echo ($is_admin||$is_sao)?'':'disabled'; ?>>
              </div>
              <div class="form-group col-md-4">
                <label>Branch</label>
                <input type="text" class="form-control" name="bank_branch" value="<?php echo h($student['bank_branch'] ?? ''); ?>" <?php echo ($is_admin||$is_sao)?'':'disabled'; ?>>
              </div>
            </div>
            <div class="form-group">
              <label>Front Page (PDF/JPG/PNG) - optional</label>
              <input type="file" class="form-control-file" name="bank_front" accept="application/pdf,image/jpeg,image/png" <?php echo ($is_admin||$is_sao)?'':'disabled'; ?>>
              <?php if (!empty($student['bank_frontsheet_path'])): ?>
                <small class="form-text text-muted">Existing file: <a target="_blank" href="/<?php echo h($student['bank_frontsheet_path']); ?>">View current</a></small>
              <?php endif; ?>
            </div>
            <?php if ($is_admin || $is_sao): ?>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Bank Details</button>
            <?php else: ?>
              <div class="alert alert-info py-2">You do not have permission to change bank details.</div>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <?php if ($is_admin || $is_sao): ?>
    <div class="tab-pane fade" id="password" role="tabpanel">
      <div class="card">
        <div class="card-header bg-white"><strong>Reset Password</strong></div>
        <div class="card-body">
          <form method="post" autocomplete="off">
            <input type="hidden" name="form" value="reset_password">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>New Password</label>
                <input type="password" class="form-control" name="new_password" minlength="8" required>
              </div>
              <div class="form-group col-md-4">
                <label>Confirm Password</label>
                <input type="password" class="form-control" name="repeat_password" minlength="8" required>
              </div>
            </div>
            <button type="submit" class="btn btn-danger"><i class="fas fa-key mr-1"></i>Reset Password</button>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<!-- Next Student ID Modal -->
<div class="modal fade" id="nextSidModal" tabindex="-1" role="dialog" aria-labelledby="nextSidModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="nextSidModalLabel">Next Student ID Suggestion</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Department</label>
            <select class="form-control" id="ns_dept">
              <option value="">-- Select --</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo h($d['department_id']); ?>"><?php echo h($d['department_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Course</label>
            <select class="form-control" id="ns_course" disabled>
              <option value="">-- Select --</option>
              <?php foreach ($courses as $c): ?>
                <option data-dept="<?php echo h($c['department_id']); ?>" value="<?php echo h($c['course_id']); ?>"><?php echo h($c['course_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Academic Year</label>
            <select class="form-control" id="ns_academic">
              <option value="">-- Select --</option>
              <?php foreach ($academicYears as $ay): ?>
                <option value="<?php echo h($ay); ?>"><?php echo h($ay); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Last Student ID (detected)</label>
            <input type="text" class="form-control" id="ns_last" readonly>
          </div>
          <div class="form-group col-md-6">
            <label>Next Student ID</label>
            <input type="text" class="form-control font-weight-bold" id="ns_next" readonly>
          </div>
        </div>
        <div class="custom-control custom-checkbox mb-2">
          <input type="checkbox" class="custom-control-input" id="ns_assign">
          <label class="custom-control-label" for="ns_assign">Assign this next ID to the student</label>
        </div>
        <div id="ns_note" class="text-muted small"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="ns_apply_btn"><i class="fas fa-save mr-1"></i>Apply & Save</button>
      </div>
    </div>
  </div>
  <script>
    (function(){
      var deptSel = document.getElementById('ns_dept');
      var courseSel = document.getElementById('ns_course');
      var aySel = document.getElementById('ns_academic');
      var lastInp = document.getElementById('ns_last');
      var nextInp = document.getElementById('ns_next');
      var assignCb = document.getElementById('ns_assign');
      var noteDiv = document.getElementById('ns_note');
      var applyBtn = document.getElementById('ns_apply_btn');
      function filterCourses(){
        var d = deptSel.value;
        var any=false; courseSel.disabled=false; courseSel.value='';
        Array.prototype.forEach.call(courseSel.options, function(opt, idx){
          if (idx===0) return; // keep placeholder
          var show = (d==='') || (opt.getAttribute('data-dept')===d);
          opt.style.display = show ? '' : 'none';
          if (show) any=true;
        });
        if (!any) { courseSel.disabled=true; }
      }
      function updateNext(){
        lastInp.value=''; nextInp.value=''; noteDiv.textContent='';
        var cid = courseSel.value; var ay = aySel.value;
        if (!cid || !ay) return;
        var url = '<?php echo $base; ?>/student/StudentUnifiedEdit.php?action=next_sid&course_id=' + encodeURIComponent(cid) + '&academic_year=' + encodeURIComponent(ay);
        fetch(url, {credentials:'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(j){
            if (!j || j.ok===false) { noteDiv.textContent = 'Unable to fetch next ID'; return; }
            lastInp.value = j.last_id || '';
            nextInp.value = j.next_id || '';
            if (j.note) noteDiv.textContent = j.note;
          })
          .catch(function(){ noteDiv.textContent = 'Network error while fetching next ID'; });
      }
      if (deptSel && courseSel && aySel){
        deptSel.addEventListener('change', function(){ filterCourses(); updateNext(); });
        courseSel.addEventListener('change', updateNext);
        aySel.addEventListener('change', updateNext);
        filterCourses();
      }
      if (applyBtn){
        applyBtn.addEventListener('click', function(){
          var cid = courseSel.value; var ay = aySel.value;
          if (!cid || !ay) { noteDiv.textContent = 'Select course and academic year first'; return; }
          var courseField = document.getElementById('enroll_course_id');
          var ayField = document.getElementById('enroll_academic_year');
          if (courseField) { courseField.value = cid; }
          if (ayField) { ayField.value = ay; }
          // Handle optional assign of next student id
          var assignHidden = document.getElementById('assign_next_sid');
          var newSidHidden = document.getElementById('new_student_id');
          if (assignCb && assignCb.checked && nextInp && nextInp.value){
            if (assignHidden) assignHidden.value = '1';
            if (newSidHidden) newSidHidden.value = nextInp.value;
          } else {
            if (assignHidden) assignHidden.value = '0';
            if (newSidHidden) newSidHidden.value = '';
          }
          var form = document.getElementById('enrollForm');
          if (form) { form.submit(); }
          // Hide modal after submit (best effort)
          try { if (window.jQuery) { jQuery('#nextSidModal').modal('hide'); } } catch(e) {}
        });
      }
    })();
  </script>
</div>
<?php include_once __DIR__ . '/../footer.php'; ?>
