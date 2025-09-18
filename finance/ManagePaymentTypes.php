<?php
// finance/ManagePaymentTypes.php - CRUD for payment types and reasons
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Allow FIN and ACC (and ADM for safety) to access
$userType = isset($_SESSION['user_type']) ? strtoupper(trim($_SESSION['user_type'])) : '';
if (!in_array($userType, ['FIN','ACC','ADM'], true)) {
  require_once __DIR__ . '/../head.php';
  require_once __DIR__ . '/../menu.php';
  echo '<div class="container mt-4"><div class="alert alert-danger">Access denied.</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
$base = defined('APP_BASE') ? APP_BASE : '';

$messages = [];
$errors = [];

// Actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  if ($action === 'create') {
    $ptype = trim($_POST['payment_type'] ?? '');
    $preason = trim($_POST['payment_reason'] ?? '');
    if ($ptype === '' || $preason === '') {
      $errors[] = 'Payment Type and Payment Reason are required.';
    } else {
      // Check duplicate
      $st = mysqli_prepare($con, 'SELECT 1 FROM payment WHERE payment_reason=? LIMIT 1');
      if ($st) {
        mysqli_stmt_bind_param($st, 's', $preason);
        mysqli_stmt_execute($st);
        mysqli_stmt_store_result($st);
        $exists = mysqli_stmt_num_rows($st) > 0;
        mysqli_stmt_close($st);
        if ($exists) {
          $errors[] = 'A record with this Payment Reason already exists.';
        } else {
          if ($ins = mysqli_prepare($con, 'INSERT INTO payment (payment_reason, payment_type) VALUES (?, ?)')) {
            mysqli_stmt_bind_param($ins, 'ss', $preason, $ptype);
            if (mysqli_stmt_execute($ins)) { $messages[] = 'Payment type created.'; }
            else { $errors[] = 'Insert failed: '.h(mysqli_error($con)); }
            mysqli_stmt_close($ins);
          } else {
            $errors[] = 'DB error (prepare insert).';
          }
        }
      } else { $errors[] = 'DB error (check duplicate).'; }
    }
  }

  if ($action === 'update') {
    $orig = trim($_POST['orig_reason'] ?? '');
    $ptype = trim($_POST['payment_type'] ?? '');
    $preason = trim($_POST['payment_reason'] ?? '');
    if ($orig === '' || $ptype === '' || $preason === '') {
      $errors[] = 'All fields are required for update.';
    } else {
      // If reason changed, ensure not colliding with another row
      if (strcasecmp($orig, $preason) !== 0) {
        $st = mysqli_prepare($con, 'SELECT 1 FROM payment WHERE payment_reason=? LIMIT 1');
        if ($st) {
          mysqli_stmt_bind_param($st, 's', $preason);
          mysqli_stmt_execute($st);
          mysqli_stmt_store_result($st);
          if (mysqli_stmt_num_rows($st) > 0) { $errors[] = 'Another record with this Payment Reason already exists.'; }
          mysqli_stmt_close($st);
        }
      }
      if (!$errors) {
        if ($up = mysqli_prepare($con, 'UPDATE payment SET payment_reason=?, payment_type=? WHERE payment_reason=? LIMIT 1')) {
          mysqli_stmt_bind_param($up, 'sss', $preason, $ptype, $orig);
          if (mysqli_stmt_execute($up)) { $messages[] = 'Payment type updated.'; }
          else { $errors[] = 'Update failed: '.h(mysqli_error($con)); }
          mysqli_stmt_close($up);
        } else { $errors[] = 'DB error (prepare update).'; }
      }
    }
  }

  if ($action === 'delete') {
    $orig = trim($_POST['orig_reason'] ?? '');
    if ($orig === '') { $errors[] = 'Missing record identifier.'; }
    else {
      if ($del = mysqli_prepare($con, 'DELETE FROM payment WHERE payment_reason=? LIMIT 1')) {
        mysqli_stmt_bind_param($del, 's', $orig);
        if (mysqli_stmt_execute($del)) {
          if (mysqli_stmt_affected_rows($del) > 0) { $messages[] = 'Payment type deleted.'; }
          else { $errors[] = 'No matching record to delete.'; }
        } else { $errors[] = 'Delete failed: '.h(mysqli_error($con)); }
        mysqli_stmt_close($del);
      } else { $errors[] = 'DB error (prepare delete).'; }
    }
  }

  // Redirect to avoid resubmission
  $_SESSION['flash_messages'] = $messages;
  $_SESSION['flash_errors'] = $errors;
  header('Location: ' . $base . '/finance/ManagePaymentTypes.php');
  exit;
}

// Flash messages
if (!empty($_SESSION['flash_messages'])) { $messages = $_SESSION['flash_messages']; unset($_SESSION['flash_messages']); }
if (!empty($_SESSION['flash_errors'])) { $errors = $_SESSION['flash_errors']; unset($_SESSION['flash_errors']); }

// Filters/search
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = '';
if ($q !== '') {
  $safe = '%' . mysqli_real_escape_string($con, $q) . '%';
  $where = " WHERE payment_reason LIKE '$safe' OR payment_type LIKE '$safe' ";
}
$sql = 'SELECT payment_reason, payment_type FROM payment' . $where . ' ORDER BY payment_type, payment_reason';
$list = mysqli_query($con, $sql);

// Layout
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0"><i class="fas fa-tags text-primary mr-2"></i> Manage Payment Types</h3>
    <form class="form-inline" method="get" action="">
      <div class="input-group input-group-sm">
        <input type="text" name="q" class="form-control" placeholder="Search..." value="<?php echo h($q); ?>">
        <div class="input-group-append">
          <button class="btn btn-outline-secondary" type="submit"><i class="fa fa-search"></i></button>
          <a class="btn btn-outline-dark" href="<?php echo $base; ?>/finance/ManagePaymentTypes.php"><i class="fa fa-times"></i></a>
        </div>
      </div>
    </form>
  </div>

  <?php foreach ($messages as $m): ?>
    <div class="alert alert-success py-2"><?php echo h($m); ?></div>
  <?php endforeach; ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2"><?php echo h($e); ?></div>
  <?php endforeach; ?>

  <div class="card mb-3">
    <div class="card-header"><strong>Create New</strong></div>
    <div class="card-body">
      <form method="post" class="form">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label class="small text-muted">Payment Type</label>
            <input type="text" name="payment_type" class="form-control" maxlength="100" required>
          </div>
          <div class="form-group col-md-6">
            <label class="small text-muted">Payment Reason</label>
            <input type="text" name="payment_reason" class="form-control" maxlength="50" required>
          </div>
          <div class="form-group col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-block">Add</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Payment Types</strong>
      <span class="badge badge-secondary"><?php echo $list ? (int)mysqli_num_rows($list) : 0; ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm mb-0">
          <thead class="thead-light">
            <tr>
              <th style="width: 48px">#</th>
              <th>Payment Type</th>
              <th>Payment Reason</th>
              <th class="text-nowrap" style="width: 160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($list && mysqli_num_rows($list)>0): $i=0; while($r=mysqli_fetch_assoc($list)): ?>
              <tr>
                <td class="text-muted align-middle"><?php echo ++$i; ?></td>
                <td class="align-middle"><?php echo h($r['payment_type']); ?></td>
                <td class="align-middle"><?php echo h($r['payment_reason']); ?></td>
                <td class="align-middle">
                  <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-secondary" data-toggle="collapse" data-target="#edit-<?php echo h(md5($r['payment_reason'])); ?>" aria-expanded="false">Edit</button>
                    <form method="post" onsubmit="return confirm('Delete this payment type?');" class="ml-1">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="orig_reason" value="<?php echo h($r['payment_reason']); ?>">
                      <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
              <tr class="collapse" id="edit-<?php echo h(md5($r['payment_reason'])); ?>">
                <td colspan="4" class="bg-light">
                  <form method="post" class="p-2 border rounded">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="orig_reason" value="<?php echo h($r['payment_reason']); ?>">
                    <div class="form-row">
                      <div class="form-group col-md-4">
                        <label class="small text-muted">Payment Type</label>
                        <input type="text" name="payment_type" class="form-control" maxlength="100" required value="<?php echo h($r['payment_type']); ?>">
                      </div>
                      <div class="form-group col-md-6">
                        <label class="small text-muted">Payment Reason</label>
                        <input type="text" name="payment_reason" class="form-control" maxlength="50" required value="<?php echo h($r['payment_reason']); ?>">
                      </div>
                      <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-block">Save</button>
                      </div>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="text-center text-muted">No records.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
