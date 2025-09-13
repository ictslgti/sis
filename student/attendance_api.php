<?php
// Returns JSON: { month: 'YYYY-MM', days: { 'YYYY-MM-DD': 1|0 }, present: N, total_marked: M }
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_table']) || $_SESSION['user_table'] !== 'student') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}
$studentId = $_SESSION['user_name'] ?? '';
$month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$firstDay = $month . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

$days = [];
$present = 0; $totalMarked = 0;

// Query attendance table for the student for the month; use MAX(attendance_status) per date
$sql = "SELECT `date`, MAX(attendance_status) AS st FROM attendance WHERE student_id=? AND `date` BETWEEN ? AND ? GROUP BY `date`";
if ($st = mysqli_prepare($con, $sql)) {
  mysqli_stmt_bind_param($st, 'sss', $studentId, $firstDay, $lastDay);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($row = $rs ? mysqli_fetch_assoc($rs) : null) {
    if (!$row) break;
    $d = $row['date']; $s = is_null($row['st']) ? null : (int)$row['st'];
    if ($d) {
      // Normalize st to 0/1; null indicates unmarked day
      if ($s === 1) { $days[$d] = 1; $present++; $totalMarked++; }
      elseif ($s === 0) { $days[$d] = 0; $totalMarked++; }
    }
  }
  mysqli_stmt_close($st);
}

echo json_encode([
  'month' => $month,
  'days' => $days,
  'present' => $present,
  'total_marked' => $totalMarked,
]);
