<?php
// menu3.php - Minimal top navbar for MA4 (no sidebar)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$u_n  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$u_t  = isset($_SESSION['user_type']) ? strtoupper(trim($_SESSION['user_type'])) : '';
$base = defined('APP_BASE') ? APP_BASE : '';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top px-2 px-md-3 px-lg-4">
  <a class="navbar-brand" href="<?php echo $base; ?>/finance/CollectPayment.php">MIS@SLGTI</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mabar" aria-controls="mabar" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse justify-content-end" id="mabar">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/finance/CollectPayment.php"><i class="fas fa-cash-register"></i> Collect Payment</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?php echo $base; ?>/finance/PaymentsSummary.php"><i class="fas fa-table"></i> Payments Summary</a>
      </li>
    </ul>

    <ul class="navbar-nav ml-3">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fa fa-user"></i> <?php echo htmlspecialchars($u_n ?: ''); ?>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userMenu">
          <h6 class="dropdown-header"><?php echo htmlspecialchars($u_t); ?></h6>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo $base; ?>/Profile.php">Profile</a>
          <a class="dropdown-item" href="<?php echo $base; ?>/logout.php">Logout</a>
        </div>
      </li>
    </ul>
  </div>
</nav>

<!-- Open the main content wrappers expected by footer.php -->
<main class="page-content">
  <div class="container">
