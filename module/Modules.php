<?php
// Simple alias to Module.php preserving query string
// BLOCK#1 START DON'T CHANGE THE ORDER
require_once __DIR__ . '/../config.php';
// END DON'T CHANGE THE ORDER

$base = defined('APP_BASE') ? APP_BASE : '';
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
$target = rtrim($base, '/') . '/module/Module.php' . $query;
header('Location: ' . $target);
exit;
