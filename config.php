<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Central auth helpers (roles, permissions)
require_once __DIR__ . '/auth.php';
date_default_timezone_set('Asia/Colombo');
// Base URL where the app is served, computed from filesystem path relative to DOCUMENT_ROOT
// Result: '' when served at vhost root (e.g., http://sis/), or '/sis' when under a subdirectory (e.g., http://localhost/sis/)
if (!defined('APP_BASE')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
    $appDir  = rtrim(str_replace('\\','/', realpath(__DIR__)), '/');
    $base = '';
    if ($docRoot && $appDir && strpos($appDir, $docRoot) === 0) {
        $base = substr($appDir, strlen($docRoot));
    }
    $base = trim($base, '/');
    define('APP_BASE', $base === '' ? '' : '/' . $base);
}
//database connection
define('DB_HOST','localhost');  // Changed from 'mis.achchuthan.org'
define('DB_USER','root');       // Default WAMP MySQL username
// Try these passwords one at a time:
// 1. Empty password (default for new WAMP installations)
define('DB_PASS','1234');
// 2. Common WAMP default password
// define('DB_PASS','root');
// 3. No password (uncomment if above don't work)
// define('DB_PASS',null);
define('DB_NAME','sis');

// First try without database name to test connection
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS);

if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
} else {
   // echo "Successfully connected to MySQL server<br>";
    
    // Check if database exists
    $result = mysqli_query($con, "SHOW DATABASES LIKE 'sis'");
    if (mysqli_num_rows($result) > 0) {
        //echo "Database 'mis' exists<br>";
        mysqli_select_db($con, 'sis');
        // Force UTF-8 everywhere (needed for Tamil and other scripts)
        @mysqli_set_charset($con, 'utf8mb4');
        @mysqli_query($con, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    } else {
        die("Error: Database does not exist. Please create it first.");
    }
}

//cookie
define('COOKIE_RUNTIME', 1209600); // 1209600 seconds = 2 weeks
define('COOKIE_DOMAIN',''); // no explicit cookie domain; scope to current host
define('COOKIE_SECRET_KEY', '1Wp@TMPS{+$78sppMJFe-92s'); // use to salt cookie content and when changed, can invalidate all databases users cookies
  


define("EMAIL_USE_SMTP", true);
define("EMAIL_SMTP_HOST", 'smtp.gmail.com');
define("EMAIL_SMTP_AUTH", true); // leave this true until your SMTP can be used without login
define("EMAIL_SMTP_USERNAME", 'slgtimis@gmail.com');
define("EMAIL_SMTP_PASSWORD", 'slgtimis123$');
define("EMAIL_SMTP_PORT", 587);
define("EMAIL_SMTP_ENCRYPTION", 'tls');
/**
 * Configuration file for: password reset email data
 * This is the place where your constants are saved
 * absolute URL to register.php, necessary for email password reset links 
* */

// Build absolute URLs based on current host and APP_BASE when available
$__host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$__scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$__base = (defined('APP_BASE') ? APP_BASE : '');
define("EMAIL_PASSWORDRESET_URL", $__host ? ($__scheme.$__host.$__base.'/passwordrecovery') : ($__base.'/passwordrecovery'));
define("EMAIL_PASSWORDRESET_FROM", "noreply@achchuthan.org");
define("EMAIL_PASSWORDRESET_FROM_NAME", "MIS@SLGTI");
define("EMAIL_PASSWORDRESET_SUBJECT", "[MIS@SLGTI] Password Reset");
define("EMAIL_PASSWORDRESET_CONTENT", "Please click on this link to reset your password: ");
/**
 * Configuration file for: verification email data
 * This is the place where your constants are saved
 * absolute URL to register.php, necessary for email verification links 
 * */
define("EMAIL_VERIFICATION_URL", $__host ? ($__scheme.$__host.$__base.'/signup') : ($__base.'/signup'));
define("EMAIL_VERIFICATION_FROM", "noreply@achchuthan.org");
define("EMAIL_VERIFICATION_FROM_NAME", "MIS@SLGTI");
define("EMAIL_VERIFICATION_SUBJECT", "[MIS@SLGT] Account Activation I");
define("EMAIL_VERIFICATION_CONTENT", "Please click on this link to activate your account:");



?>
