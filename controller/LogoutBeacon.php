<?php
// Marks logout_time on browser close/unload and also destroys session if still active
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($con) { mysqli_set_charset($con, 'utf8'); }
$sess = session_id();
$reason = isset($_GET['r']) ? substr($_GET['r'],0,32) : 'close';
if ($con) {
  $tbl = "CREATE TABLE IF NOT EXISTS user_login_log (
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
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
  mysqli_query($con, $tbl);
  $upd = mysqli_prepare($con, 'UPDATE user_login_log SET logout_time = COALESCE(logout_time, NOW()), method=? WHERE session_id=?');
  mysqli_stmt_bind_param($upd, 'ss', $reason, $sess);
  mysqli_stmt_execute($upd);
  mysqli_stmt_close($upd);
  mysqli_close($con);
}
// Best-effort session cleanup
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
}
setcookie('rememberme', '', time() - 3600, '/', '', false, true);
@session_destroy();
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
