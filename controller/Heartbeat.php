<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../config.php';
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$con) { http_response_code(500); echo 'DB'; exit; }
mysqli_set_charset($con, 'utf8');
$u = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$sess = session_id();
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'],0,255) : '';
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
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
$sel = mysqli_prepare($con, 'SELECT id FROM user_login_log WHERE session_id=? LIMIT 1');
mysqli_stmt_bind_param($sel, 's', $sess);
mysqli_stmt_execute($sel);
$res = mysqli_stmt_get_result($sel);
if ($res && mysqli_fetch_assoc($res)) {
  $upd = mysqli_prepare($con, 'UPDATE user_login_log SET last_seen=NOW(), user_agent=COALESCE(?, user_agent), ip=COALESCE(?, ip) WHERE session_id=?');
  mysqli_stmt_bind_param($upd, 'sss', $ua, $ip, $sess);
  mysqli_stmt_execute($upd);
  mysqli_stmt_close($upd);
} else {
  $ins = mysqli_prepare($con, 'INSERT INTO user_login_log (user_name, session_id, login_time, last_seen, method, user_agent, ip) VALUES (?,?,NOW(),NOW(),"active",?,?)');
  mysqli_stmt_bind_param($ins, 'ssss', $u, $sess, $ua, $ip);
  mysqli_stmt_execute($ins);
  mysqli_stmt_close($ins);
}
if ($res) mysqli_free_result($res);
mysqli_stmt_close($sel);
mysqli_close($con);
header('Content-Type: application/json');
echo json_encode(['ok'=>true]);
