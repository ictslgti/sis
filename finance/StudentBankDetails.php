<?php
// finance/StudentBankDetails.php — Finance Officer: Student Bank Directory
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

require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';
?>
<div class="container mt-3">
  <h2 class="text-center">Finance: Student Bank Details</h2>
  <p class="text-center">Browse and filter students' bank details.</p>

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
              <th>Account No</th>
              <th>Branch</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $sql = "SELECT s.student_id, s.student_fullname, s.bank_name, s.bank_account_no, s.bank_branch, s.bank_frontsheet_path,\n                             d.department_name, d.department_id\n                      FROM student s\n                      LEFT JOIN student_enroll se ON se.student_id = s.student_id\n                      LEFT JOIN course c ON c.course_id = se.course_id\n                      LEFT JOIN department d ON d.department_id = c.department_id";
              $conds = [];
              $params = [];
              $types = '';
              if ($q_sid !== '') { $conds[] = 's.student_id LIKE ?'; $types.='s'; $params[] = "%$q_sid%"; }
              if ($q_name !== '') { $conds[] = 's.student_fullname LIKE ?'; $types.='s'; $params[] = "%$q_name%"; }
              if ($q_dept !== '') { $conds[] = '(d.department_id = ? OR d.department_name LIKE ?)'; $types.='ss'; $params[] = $q_dept; $params[] = "%$q_dept%"; }
              if ($q_has === 'yes') { $conds[] = "(COALESCE(s.bank_account_no,'') <> '' OR COALESCE(s.bank_frontsheet_path,'') <> '')"; }
              elseif ($q_has === 'no') { $conds[] = "(COALESCE(s.bank_account_no,'') = '' AND COALESCE(s.bank_frontsheet_path,'') = '')"; }
              if ($conds) { $sql .= ' WHERE ' . implode(' AND ', $conds); }
              $sql .= ' GROUP BY s.student_id ORDER BY s.student_id ASC LIMIT 500';

              if ($st = mysqli_prepare($con, $sql)) {
                if ($types !== '') { mysqli_stmt_bind_param($st, $types, ...$params); }
                if (mysqli_stmt_execute($st)) {
                  $res = mysqli_stmt_get_result($st);
                  if ($res && mysqli_num_rows($res) > 0) {
                    while ($r = mysqli_fetch_assoc($res)) {
                      echo '<tr>';
                      echo '<td>'.htmlspecialchars($r['student_id']).'</td>';
                      echo '<td>'.htmlspecialchars($r['student_fullname']).'</td>';
                      echo '<td>'.htmlspecialchars(($r['department_name'] ?: '')).'</td>';
                      echo '<td>'.htmlspecialchars($r['bank_account_no'] ?: '—').'</td>';
                      echo '<td>'.htmlspecialchars($r['bank_branch'] ?: '—').'</td>';
                    $front = isset($r['bank_frontsheet_path']) ? $r['bank_frontsheet_path'] : '';
                    $dept  = isset($r['department_name']) ? $r['department_name'] : '';
                    echo '<td class="text-center">'
                       . '<button type="button" class="btn btn-sm btn-outline-primary bank-view-btn" '
                       . 'data-toggle="modal" data-target="#viewBankModal" '
                       . 'data-student="'.htmlspecialchars($r['student_id'], ENT_QUOTES).'" '
                       . 'data-name="'.htmlspecialchars($r['student_fullname'], ENT_QUOTES).'" '
                       . 'data-dept="'.htmlspecialchars(($r['department_name'] ?: ''), ENT_QUOTES).'" '
                       . 'data-acc="'.htmlspecialchars($r['bank_account_no'] ?: '—', ENT_QUOTES).'" '
                       . 'data-branch="'.htmlspecialchars($r['bank_branch'] ?: '—', ENT_QUOTES).'" '
                       . 'data-front="'.htmlspecialchars($front, ENT_QUOTES).'">'
                       . '<i class="fa fa-eye"></i>'
                       . '</button>'
                       . '</td>';
                    echo '</tr>';
                    }
                  } else {
                    echo '<tr><td colspan="6" class="text-center">No students found.</td></tr>';
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
  
  <!-- View Bank Details Modal -->
  <div class="modal fade" id="viewBankModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Student Bank Details</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <ul class="list-unstyled mb-0">
                <li><strong>Student ID:</strong> <span id="mb-student"></span></li>
                <li><strong>Name:</strong> <span id="mb-name"></span></li>
                <li><strong>Department:</strong> <span id="mb-dept"></span></li>
                <li><strong>Account No:</strong> <span id="mb-acc"></span></li>
                <li><strong>Branch:</strong> <span id="mb-branch"></span></li>
              </ul>
            </div>
            <div class="col-md-6">
              <div id="mb-front-wrapper" class="border p-2 text-center">
                <img id="mb-front" src="" alt="Bank Front Page" style="max-width:100%; max-height:360px; display:none;" />
                <div id="mb-front-missing" style="display:none;">No bank front page uploaded.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <a id="mb-open" href="#" target="_blank" class="btn btn-primary" style="display:none;">Open Image</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('viewBankModal');
    $('#viewBankModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var student = button.data('student') || '';
      var name = button.data('name') || '';
      var dept = button.data('dept') || '';
      var acc = button.data('acc') || '';
      var branch = button.data('branch') || '';
      var front = button.data('front') || '';

      $('#mb-student').text(student);
      $('#mb-name').text(name);
      $('#mb-dept').text(dept);
      $('#mb-acc').text(acc);
      $('#mb-branch').text(branch);

      if (front) {
        var url = '/' + front.replace(/^\/+/, '');
        $('#mb-front').attr('src', url).show();
        $('#mb-front-missing').hide();
        $('#mb-open').attr('href', url).show();
      } else {
        $('#mb-front').hide();
        $('#mb-front-missing').show();
        $('#mb-open').hide();
      }
    });
  });
</script>
<?php require_once __DIR__ . '/../footer.php'; if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_end_flush(); } ?>
