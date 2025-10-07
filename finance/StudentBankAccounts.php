<?php
// finance/StudentBankAccounts.php — Finance Officer: Student Bank Accounts + Export
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_login();
require_roles(['FIN','ACC','ADM']);
if (!headers_sent()) { ob_start(); }

// Load departments for dropdown
$departments = [];
if ($ds = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name ASC")) {
  while ($d = mysqli_fetch_assoc($ds)) { $departments[] = $d; }
  mysqli_free_result($ds);
}

// Read filters
$q_sid  = isset($_GET['sb_student_id']) ? trim($_GET['sb_student_id']) : '';
$q_name = isset($_GET['sb_name']) ? trim($_GET['sb_name']) : '';
$q_dept = isset($_GET['sb_dept']) ? trim($_GET['sb_dept']) : '';
$q_has  = isset($_GET['sb_has_bank']) ? trim($_GET['sb_has_bank']) : '';
$q_limit = 1000; // default page cap for HTML

// Build SQL
$sql = "SELECT s.student_id, s.student_fullname, s.bank_name, s.bank_account_no, s.bank_branch, s.bank_frontsheet_path,\n               d.department_name, d.department_id\n        FROM student s\n        LEFT JOIN student_enroll se ON se.student_id = s.student_id\n        LEFT JOIN course c ON c.course_id = se.course_id\n        LEFT JOIN department d ON d.department_id = c.department_id";
$conds = [];
$params = [];
$types = '';
if ($q_sid !== '') { $conds[] = 's.student_id LIKE ?'; $types.='s'; $params[] = "%$q_sid%"; }
if ($q_name !== '') { $conds[] = 's.student_fullname LIKE ?'; $types.='s'; $params[] = "%$q_name%"; }
if ($q_dept !== '') {
  $conds[] = '(d.department_id = ? OR d.department_code = ? OR d.department_name LIKE ? OR d.department_id LIKE ?)';
  // we will recompute $types later for binding; still append params for consistency
  $params[] = $q_dept;           // exact id
  $params[] = $q_dept;           // exact code
  $params[] = "%$q_dept%";     // name like
  $params[] = "%$q_dept%";     // id like (in case id typed as string)
}
if ($q_has === 'yes') { $conds[] = "(COALESCE(s.bank_account_no,'') <> '' OR COALESCE(s.bank_frontsheet_path,'') <> '')"; }
elseif ($q_has === 'no') { $conds[] = "(COALESCE(s.bank_account_no,'') = '' AND COALESCE(s.bank_frontsheet_path,'') = '')"; }
if ($conds) { $sql .= ' WHERE ' . implode(' AND ', $conds); }
$sql .= ' GROUP BY s.student_id ORDER BY s.student_id ASC';

// Export CSV if requested
if (isset($_GET['export']) && $_GET['export'] == '1') {
  // For export do not hard limit rows
  if (empty($params)) {
    // No params: run direct query
    $res = mysqli_query($con, $sql);
    header('Content-Type: text/csv; charset=utf-8');
    $fname = 'student_bank_accounts_' . date('Ymd_His') . '.csv';
    header('Content-Disposition: attachment; filename=' . $fname);
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Student ID','Full Name','Department','Bank Name','Account No','Branch','Frontsheet Path']);
    if ($res) {
      while ($r = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
          $r['student_id'] ?? '',
          $r['student_fullname'] ?? '',
          $r['department_name'] ?? '',
          $r['bank_name'] ?? '',
          $r['bank_account_no'] ?? '',
          $r['bank_branch'] ?? '',
          $r['bank_frontsheet_path'] ?? ''
        ]);
      }
      mysqli_free_result($res);
    }
    fclose($out);
  } else {
    if ($st = mysqli_prepare($con, $sql)) {
      $need = mysqli_stmt_param_count($st);
      if ($need > 0) {
        if (count($params) !== $need) { $params = array_slice($params, 0, $need); }
        $types = str_repeat('s', count($params));
        $bind = [&$types];
        foreach ($params as $i => $v) { $params[$i] = (string)$v; $bind[] = &$params[$i]; }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$st], $bind));
      }
      if (mysqli_stmt_execute($st)) {
        $res = function_exists('mysqli_stmt_get_result') ? mysqli_stmt_get_result($st) : false;
        header('Content-Type: text/csv; charset=utf-8');
        $fname = 'student_bank_accounts_' . date('Ymd_His') . '.csv';
        header('Content-Disposition: attachment; filename=' . $fname);
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Student ID','Full Name','Department','Bank Name','Account No','Branch','Frontsheet Path']);
        if ($res) {
          while ($r = mysqli_fetch_assoc($res)) {
            fputcsv($out, [
              $r['student_id'] ?? '',
              $r['student_fullname'] ?? '',
              $r['department_name'] ?? '',
              $r['bank_name'] ?? '',
              $r['bank_account_no'] ?? '',
              $r['bank_branch'] ?? '',
              $r['bank_frontsheet_path'] ?? ''
            ]);
          }
          mysqli_free_result($res);
        } else {
          // Fallback fetch loop without get_result
          mysqli_stmt_bind_result($st, $f_sid,$f_name,$f_bname,$f_acc,$f_branch,$f_front,$f_dname,$f_did);
          mysqli_stmt_store_result($st);
          while (mysqli_stmt_fetch($st)) {
            fputcsv($out, [
              $f_sid ?? '', $f_name ?? '', $f_dname ?? '', $f_bname ?? '', $f_acc ?? '', $f_branch ?? '', $f_front ?? ''
            ]);
          }
        }
        fclose($out);
      }
      mysqli_stmt_close($st);
    }
  }
  if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); }
  exit;
}

require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <h2 class="text-center">Finance: Student Bank Accounts</h2>
  <p class="text-center">Browse students' bank account details and export to Excel.</p>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="form-row mb-0">
        <div class="form-group col-md-3">
          <label>Student ID</label>
          <input type="text" name="sb_student_id" class="form-control" value="<?php echo htmlspecialchars($q_sid); ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Name</label>
          <input type="text" name="sb_name" class="form-control" value="<?php echo htmlspecialchars($q_name); ?>">
        </div>
        <div class="form-group col-md-3">
          <label>Department</label>
          <select name="sb_dept" class="form-control">
            <option value="" <?php echo ($q_dept==='')?'selected':''; ?>>All Departments</option>
            <?php foreach ($departments as $dep): ?>
              <option value="<?php echo htmlspecialchars($dep['department_id']); ?>" <?php echo ($q_dept === $dep['department_id'])?'selected':''; ?>>
                <?php echo htmlspecialchars($dep['department_name'].' ('.$dep['department_id'].')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label>Has Bank Info</label>
          <select name="sb_has_bank" class="form-control">
            <option value="" <?php echo ($q_has==='')?'selected':''; ?>>Any</option>
            <option value="yes" <?php echo ($q_has==='yes')?'selected':''; ?>>Yes</option>
            <option value="no" <?php echo ($q_has==='no')?'selected':''; ?>>No</option>
          </select>
        </div>
        <div class="form-group col-md-1 align-self-end">
          <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i></button>
        </div>
      </form>
      <div class="mt-2">
        <?php
          // Build export URL preserving filters
          $qs = $_GET; $qs['export'] = '1';
          $export_url = strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($qs);
        ?>
        <a href="<?php echo htmlspecialchars($export_url); ?>" class="btn btn-success"><i class="fa fa-file-excel"></i> Export CSV</a>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
        <table class="table table-sm table-striped table-bordered mb-0">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Department</th>
              <th>Bank</th>
              <th>Account No</th>
              <th>Branch</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $html_sql = $sql . ' LIMIT ' . (int)$q_limit;
              if (empty($params)) {
                // No params: use direct query for speed and simplicity
                $res = mysqli_query($con, $html_sql);
                if ($res && mysqli_num_rows($res) > 0) {
                  while ($r = mysqli_fetch_assoc($res)) {
                    echo '<tr>';
                    echo '<td>'.htmlspecialchars($r['student_id'] ?? '').'</td>';
                    echo '<td>'.htmlspecialchars($r['student_fullname'] ?? '').'</td>';
                    echo '<td>'.htmlspecialchars(($r['department_name'] ?? '')).'</td>';
                    echo '<td>'.htmlspecialchars(($r['bank_name'] ?? '—')).'</td>';
                    echo '<td>'.htmlspecialchars(($r['bank_account_no'] ?? '—')).'</td>';
                    echo '<td>'.htmlspecialchars(($r['bank_branch'] ?? '—')).'</td>';
                    echo '</tr>';
                  }
                } else {
                  echo '<tr><td colspan="6" class="text-center">No students found.</td></tr>';
                }
              } else if ($st = mysqli_prepare($con, $html_sql)) {
                $need = mysqli_stmt_param_count($st);
                if ($need > 0) {
                  if (count($params) !== $need) { $params = array_slice($params, 0, $need); }
                  $types = str_repeat('s', count($params));
                  $bind = [&$types];
                  foreach ($params as $i => $v) { $params[$i] = (string)$v; $bind[] = &$params[$i]; }
                  call_user_func_array('mysqli_stmt_bind_param', array_merge([$st], $bind));
                }
                if (mysqli_stmt_execute($st)) {
                  $fell_back = false;
                  if (function_exists('mysqli_stmt_get_result')) {
                    $res = mysqli_stmt_get_result($st);
                    if ($res !== false && $res && mysqli_num_rows($res) > 0) {
                      while ($r = mysqli_fetch_assoc($res)) {
                        echo '<tr>';
                        echo '<td>'.htmlspecialchars($r['student_id'] ?? '').'</td>';
                        echo '<td>'.htmlspecialchars($r['student_fullname'] ?? '').'</td>';
                        echo '<td>'.htmlspecialchars(($r['department_name'] ?? '')).'</td>';
                        echo '<td>'.htmlspecialchars(($r['bank_name'] ?? '—')).'</td>';
                        echo '<td>'.htmlspecialchars(($r['bank_account_no'] ?? '—')).'</td>';
                        echo '<td>'.htmlspecialchars(($r['bank_branch'] ?? '—')).'</td>';
                        echo '</tr>';
                      }
                    } else {
                      // Fall back to bind_result path when get_result is unavailable or returns false/empty
                      $fell_back = true;
                    }
                  } else {
                    $fell_back = true;
                  }

                  if ($fell_back) {
                    mysqli_stmt_bind_result(
                      $st,
                      $f_student_id,
                      $f_student_fullname,
                      $f_bank_name,
                      $f_bank_account_no,
                      $f_bank_branch,
                      $f_bank_frontsheet_path,
                      $f_department_name,
                      $f_department_id
                    );
                    mysqli_stmt_store_result($st);
                    $rows = 0;
                    while (mysqli_stmt_fetch($st)) {
                      $rows++;
                      echo '<tr>';
                      echo '<td>'.htmlspecialchars($f_student_id ?? '').'</td>';
                      echo '<td>'.htmlspecialchars($f_student_fullname ?? '').'</td>';
                      echo '<td>'.htmlspecialchars(($f_department_name ?? '')).'</td>';
                      echo '<td>'.htmlspecialchars(($f_bank_name ?? '—')).'</td>';
                      echo '<td>'.htmlspecialchars(($f_bank_account_no ?? '—')).'</td>';
                      echo '<td>'.htmlspecialchars(($f_bank_branch ?? '—')).'</td>';
                      echo '</tr>';
                    }
                    if ($rows === 0) {
                      echo '<tr><td colspan="6" class="text-center">No students found.</td></tr>';
                    }
                  }
                }
                mysqli_stmt_close($st);
              }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../footer.php'; if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); } ?>
