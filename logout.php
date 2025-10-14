<?php
// logout.php - Securely end the session and redirect to login/home
ini_set('session.use_strict_mode', 1);

// Load app config to get APP_BASE and COOKIE_DOMAIN (important for nginx/vhost paths)
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Record logout_time for this session
$__sid = session_id();
require_once __DIR__ . '/config.php';
$__con = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($__con) {
    @mysqli_set_charset($__con, 'utf8');
    @mysqli_query($__con, "CREATE TABLE IF NOT EXISTS user_login_log (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_name VARCHAR(100) NOT NULL,
      session_id VARCHAR(128) NOT NULL,
      login_time DATETIME NULL,
      last_seen DATETIME NULL,
      logout_time DATETIME NULL,
      method VARCHAR(16) NULL,
      user_agent VARCHAR(255) NULL,
      ip VARCHAR(64) NULL,
      KEY idx_user (user_name),
      KEY idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    if ($__upd = @mysqli_prepare($__con, 'UPDATE user_login_log SET logout_time = COALESCE(logout_time, NOW()), method="logout" WHERE session_id=?')) {
        @mysqli_stmt_bind_param($__upd, 's', $__sid);
        @mysqli_stmt_execute($__upd);
        @mysqli_stmt_close($__upd);
    }
    @mysqli_close($__con);
}

// Unset all session variables
$_SESSION = [];

// Delete session cookie (if any)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Do not specify domain so it matches current host
    setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
}

// Also delete remember-me cookie without explicit domain
setcookie('rememberme', '', time() - 3600, '/', '', false, true);

// Destroy the session
session_destroy();
session_write_close();

// Prevent cached auth pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Redirect to login/home using APP_BASE for correct subdir under nginx
$base = rtrim((defined('APP_BASE') ? APP_BASE : ''), '/');
$target = $base . '/index.php';
header('Location: ' . $target);
exit;
