<!--Block#1 start dont change the order-->
<?php 
$title="Login Activity | SLGTI";    
include_once ("../config.php");
include_once ("../auth.php");
require_roles(['ADM']);
include_once ("../head.php");
include_once ("../menu.php");
?>
<!-- end dont change the order-->

<!-- Block#2 start your code -->
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Ensure table exists (safe if already exists)
@mysqli_query($con, "CREATE TABLE IF NOT EXISTS user_login_log (
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

$filter_user = isset($_GET['user']) ? trim($_GET['user']) : '';
$limit = 100; $page = max(1, (int)($_GET['page'] ?? 1)); $offset = ($page-1)*$limit;
$where = '1=1'; $params = []; $types='';
if ($filter_user !== '') { $where .= ' AND user_name LIKE ?'; $params[] = '%'.$filter_user.'%'; $types .= 's'; }

// Count
$sqlCount = "SELECT COUNT(*) AS c FROM user_login_log WHERE $where";
if ($types !== '') {
  $stmtC = mysqli_prepare($con, $sqlCount);
  mysqli_stmt_bind_param($stmtC, $types, ...$params);
  mysqli_stmt_execute($stmtC); $rc = mysqli_stmt_get_result($stmtC); $total = ($rc && ($r=mysqli_fetch_assoc($rc))) ? (int)$r['c'] : 0; mysqli_stmt_close($stmtC);
} else {
  $rc = mysqli_query($con, $sqlCount); $total = ($rc && ($r=mysqli_fetch_assoc($rc))) ? (int)$r['c'] : 0; if ($rc) mysqli_free_result($rc);
}
$pages = max(1, (int)ceil($total/$limit)); if ($page > $pages) { $page = $pages; $offset = ($page-1)*$limit; }

// Data
$sql = "SELECT id, user_name, session_id, login_time, last_seen, logout_time, method, user_agent, ip
        FROM user_login_log WHERE $where ORDER BY COALESCE(last_seen, login_time) DESC LIMIT $limit OFFSET $offset";
$rows = [];
if ($types !== '') {
  $stmt = mysqli_prepare($con, $sql);
  mysqli_stmt_bind_param($stmt, $types, ...$params);
  mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
  while ($res && ($row = mysqli_fetch_assoc($res))) { $rows[] = $row; }
  mysqli_stmt_close($stmt);
} else {
  $res = mysqli_query($con, $sql);
  while ($res && ($row = mysqli_fetch_assoc($res))) { $rows[] = $row; }
  if ($res) mysqli_free_result($res);
}
?>

<div class="container-fluid mt-3">
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Login Activity</h5>
        <small class="text-muted">User login/logout with last seen. Auto-logout on close tracked via beacon.</small>
      </div>
      <form class="form-inline" method="GET">
        <input type="text" class="form-control form-control-sm mr-2" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Filter by username">
        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-filter mr-1"></i>Filter</button>
      </form>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover">
          <thead class="thead-dark">
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Session</th>
              <th>Login</th>
              <th>Last Seen</th>
              <th>Logout</th>
              <th>Method</th>
              <th>IP</th>
              <th>User Agent</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)) { echo '<tr><td colspan="9" class="text-center text-muted">No activity</td></tr>'; } ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['user_name']); ?></td>
                <td class="text-monospace small"><?php echo htmlspecialchars($r['session_id']); ?></td>
                <td><?php echo htmlspecialchars($r['login_time']); ?></td>
                <td><?php echo htmlspecialchars($r['last_seen']); ?></td>
                <td><?php echo htmlspecialchars($r['logout_time']); ?></td>
                <td><?php echo htmlspecialchars($r['method']); ?></td>
                <td><?php echo htmlspecialchars($r['ip']); ?></td>
                <td class="small text-truncate" style="max-width:360px;" title="<?php echo htmlspecialchars($r['user_agent']); ?>"><?php echo htmlspecialchars($r['user_agent']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <nav>
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p=1; $p<=$pages; $p++): $active = ($p===$page)?'active':''; $qs = http_build_query(['user'=>$filter_user,'page'=>$p]); ?>
            <li class="page-item <?php echo $active; ?>"><a class="page-link" href="?<?php echo $qs; ?>"><?php echo $p; ?></a></li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  </div>
</div>

<!-- end your code -->

<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
