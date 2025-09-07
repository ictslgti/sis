<?php
// student/AllowanceEligibility.php - SAO view for allowance-eligible students (stub)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

// Role check: only SAO users can access
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'SAO') {
  echo '<div class="container mt-4"><div class="alert alert-danger">Access denied.</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}
?>
<div class="container mt-3">
  <h3>Allowance Eligibility</h3>
  <p class="text-muted">This page will list allowance-eligible students and support bulk insert. (Work in progress)</p>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
