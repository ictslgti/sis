<?php
// Include database configuration
require_once __DIR__ . '/../config.php';

function nocache_headers() {
    // Strongly discourage browser/proxy caching so updated images reflect immediately
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Get student ID from query string
$student_id = isset($_GET['Sid']) ? $_GET['Sid'] : null;

function send_default_image() {
    nocache_headers();
    header('Content-Type: image/png');
    @readfile(__DIR__ . '/../img/profile/user.png');
    exit;
}

if (!$student_id) {
    send_default_image();
}

// Query to get the student's profile image (may be BLOB/base64 or relative PATH)
$sql = "SELECT student_profile_img FROM student WHERE student_id = ? LIMIT 1";
if (!$stmt = mysqli_prepare($con, $sql)) { send_default_image(); }
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
mysqli_stmt_bind_result($stmt, $imgData);
if (!mysqli_stmt_fetch($stmt)) {
    mysqli_stmt_close($stmt);
    send_default_image();
}
mysqli_stmt_close($stmt);

if (empty($imgData)) { send_default_image(); }

// Case 1: looks like a relative path (e.g., img/Studnet_profile/ID.jpg)
$val = trim((string)$imgData);
$looksLikePath = (strpos($val, '/') !== false || strpos($val, '\\') !== false) && strlen($val) < 512 && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $val);
if ($looksLikePath) {
    // Normalize and constrain to project directory
    $rel = ltrim(str_replace(['..', "\0"], '', $val), '/\\');
    $abs = realpath(__DIR__ . '/../' . $rel);
    if ($abs && is_file($abs) && strpos($abs, realpath(__DIR__ . '/..')) === 0) {
        // Detect MIME by extension first
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = 'image/jpeg';
        if ($ext === 'png') $mime = 'image/png';
        elseif ($ext === 'gif') $mime = 'image/gif';
        elseif ($ext === 'webp') $mime = 'image/webp';
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        @readfile($abs);
        exit;
    }
    // If path invalid, fallback
    send_default_image();
}

// Case 2: treat as binary/blob or base64
$raw = $val;
$maybeDecoded = base64_decode($val, true);
if ($maybeDecoded !== false && strlen($maybeDecoded) > 64) { // avoid decoding tiny random strings
    $raw = $maybeDecoded;
}
if ($raw === '' || $raw === false) { send_default_image(); }

$mime = 'image/jpeg';
if (class_exists('finfo')) {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    if ($fi) {
        $detected = $fi->buffer($raw);
        if ($detected) { $mime = $detected; }
    }
}
nocache_headers();
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($raw));
echo $raw;
exit;
?>
