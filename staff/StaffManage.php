<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php
$title = "Manage Staff | SLGTI";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/add_staff.php';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';
?>
<!--END DON'T CHANGE THE ORDER-->

<style>
  #staff-mgr .form-group{ margin-bottom:.75rem }
  #staff-mgr label{ font-weight:600; margin-bottom:.25rem }
  #staff-mgr .table thead th{ white-space:nowrap }
  #staff-mgr .actions > *{ margin-right:.35rem; margin-bottom:.35rem }
  #staff-mgr .section-title{ font-size:1.05rem; font-weight:700; margin:.5rem 0 }
</style>

<?php
// Flash helper
function flash($class, $msg){
  echo '<div class="alert '.$class.' alert-dismissible fade show" role="alert">'
     . htmlspecialchars($msg)
     . '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
     . '<span aria-hidden="true">&times;</span>'
     . '</button></div>';
}

$isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : null;

$mode = isset($_GET['edit']) ? 'edit' : 'add';
$prefill = [
  'staff_id' => '', 'department_id' => '', 'staff_name' => '', 'staff_address' => '',
  'staff_dob' => '', 'staff_nic' => '', 'staff_email' => '', 'staff_pno' => '',
  'staff_date_of_join' => '', 'staff_gender' => '', 'staff_epf' => '',
  'staff_position' => '', 'staff_type' => '', 'staff_status' => ''
];

// Load for edit mode with HOD ownership enforcement
if ($mode === 'edit') {
  $sid = trim($_GET['edit']);
  $row = get_staff_by_id($con, $sid);
  if ($row) {
    if ($isHOD && $deptCode && $row['department_id'] !== $deptCode) {
      flash('alert-danger', 'Not permitted. You can only edit staff in your department.');
      $mode = 'add';
    } else {
      $prefill = $row;
    }
  } else {
    flash('alert-danger', 'Staff ID not found: '.$sid);
    $mode = 'add';
  }
}

// Handle POST actions (block mutations for HOD)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  // Enforce HOD scoping on mutations
  if ($action === 'create') {
    if ($isHOD && $deptCode) {
      // Force department to HOD's department
      $_POST['department_id'] = $deptCode;
    }
  }
  if (in_array($action, ['update','delete'], true) && $isHOD && $deptCode) {
    $sidCheck = $_POST['staff_id'] ?? '';
    $rowCheck = $sidCheck ? get_staff_by_id($con, $sidCheck) : null;
    if (!$rowCheck || $rowCheck['department_id'] !== $deptCode) {
      flash('alert-danger', 'Not permitted. You can only modify staff in your department.');
      $action = '';
    }
  }

  {
    if ($action === 'create') {
      $data = [
        'staff_id' => $_POST['staff_id'] ?? '',
        // If HOD, this was forced above to HOD dept
        'department_id' => $_POST['department_id'] ?? '',
        'staff_name' => $_POST['staff_name'] ?? '',
        'staff_address' => $_POST['staff_address'] ?? '',
        'staff_dob' => $_POST['staff_dob'] ?? '',
        'staff_nic' => $_POST['staff_nic'] ?? '',
        'staff_email' => $_POST['staff_email'] ?? '',
        'staff_pno' => $_POST['staff_pno'] ?? '',
        'staff_date_of_join' => $_POST['staff_date_of_join'] ?? '',
        'staff_gender' => $_POST['staff_gender'] ?? '',
        'staff_epf' => $_POST['staff_epf'] ?? '',
        'staff_position' => $_POST['staff_position'] ?? '',
        'staff_type' => $_POST['staff_type'] ?? '',
        'staff_status' => $_POST['staff_status'] ?? '',
      ];
      if (create_staff($con, $data, $err)) {
        flash('alert-success', 'Staff created successfully.');
        echo '<script>setTimeout(()=>location.replace(location.origin + "'.(defined('APP_BASE')?APP_BASE:'').'/staff/StaffManage.php"), 300);</script>';
      } else {
        flash('alert-danger', $err ?: 'Create failed');
      }
    } elseif ($action === 'update') {
      $sid = $_POST['staff_id'] ?? '';
      // For HOD, ensure department remains theirs
      if ($isHOD && $deptCode) { $_POST['department_id'] = $deptCode; }
      $data = [
        'staff_id' => $sid,
        'department_id' => $_POST['department_id'] ?? '',
        'staff_name' => $_POST['staff_name'] ?? '',
        'staff_address' => $_POST['staff_address'] ?? '',
        'staff_dob' => $_POST['staff_dob'] ?? '',
        'staff_nic' => $_POST['staff_nic'] ?? '',
        'staff_email' => $_POST['staff_email'] ?? '',
        'staff_pno' => $_POST['staff_pno'] ?? '',
        'staff_date_of_join' => $_POST['staff_date_of_join'] ?? '',
        'staff_gender' => $_POST['staff_gender'] ?? '',
        'staff_epf' => $_POST['staff_epf'] ?? '',
        'staff_position' => $_POST['staff_position'] ?? '',
        'staff_type' => $_POST['staff_type'] ?? '',
        'staff_status' => $_POST['staff_status'] ?? '',
      ];
      if (update_staff($con, $sid, $data, $err)) {
        flash('alert-success', 'Staff updated successfully.');
        echo '<script>setTimeout(()=>location.replace(location.origin + "'.(defined('APP_BASE')?APP_BASE:'').'/staff/StaffManage.php"), 300);</script>';
      } else {
        flash('alert-danger', $err ?: 'Update failed');
      }
    } elseif ($action === 'delete') {
      $sid = $_POST['staff_id'] ?? '';
      if (delete_staff($con, $sid, $err)) {
        flash('alert-success', 'Staff deleted successfully.');
        echo '<script>setTimeout(()=>location.replace(location.origin + "'.(defined('APP_BASE')?APP_BASE:'').'/staff/StaffManage.php"), 300);</script>';
      } else {
        flash('alert-danger', $err ?: 'Delete failed');
      }
    }
  }
}

// Scope list to HOD's department
$filters = [];
if ($isHOD && !empty($deptCode)) { $filters['department_id'] = $deptCode; }
$staff_rows = list_staff($con, $filters);
?>

<div id="staff-mgr" class="container-fluid">
  <div class="row">
    <div class="col-lg-7">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <strong>Staff List</strong>
          <a href="?" class="btn btn-sm btn-outline-secondary">Refresh</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
              <thead class="thead-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Dept</th>
                  <th>Position</th>
                  <th>Status</th>
                  <?php if ($isHOD || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM')) { ?>
                  <th class="text-right">Actions</th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <?php if ($staff_rows): foreach ($staff_rows as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['staff_id']); ?></td>
                    <td><?php echo htmlspecialchars($r['staff_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['department_id']); ?></td>
                    <td><?php echo htmlspecialchars($r['staff_position']); ?></td>
                    <td><?php echo htmlspecialchars($r['staff_status']); ?></td>
                    <?php if ($isHOD && $r['department_id'] === $deptCode || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM')) { ?>
                    <td class="text-right actions">
                      <a class="btn btn-sm btn-primary" href="staff/StaffManage.php?edit=<?php echo urlencode($r['staff_id']); ?>">Edit</a>
                      <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this staff?');">
                        <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($r['staff_id']); ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                      </form>
                    </td>
                    <?php } ?>
                  </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="6" class="text-center text-muted">No staff found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <?php if ($isHOD || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM')) { ?>
    <div class="col-lg-5">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <strong><?php echo $mode === 'edit' ? 'Edit Staff' : 'Add Staff'; ?></strong>
          <?php if ($mode === 'edit') { ?>
            <a href="?" class="btn btn-sm btn-outline-secondary">Add New</a>
          <?php } ?>
        </div>
        <div class="card-body">
          <form method="POST" action="">
            <div class="section-title">Personal</div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="staff_id">Staff ID</label>
                <input required type="text" id="staff_id" name="staff_id" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_id']); ?>" <?php echo $mode==='edit'?'readonly':''; ?>>
              </div>
              <div class="form-group col-md-6">
                <label for="department_id">Department</label>
                <select required id="department_id" name="department_id" class="custom-select">
                  <?php if ($isHOD && $deptCode) { 
                    // Lock to HOD department only
                    $dq = mysqli_query($con, "SELECT department_id, department_name FROM department WHERE department_id='".mysqli_real_escape_string($con, $deptCode)."'");
                    $drow = $dq ? mysqli_fetch_assoc($dq) : null;
                    $depName = $drow ? $drow['department_name'] : $deptCode;
                    ?>
                    <option value="<?php echo htmlspecialchars($deptCode); ?>" selected><?php echo htmlspecialchars($depName); ?></option>
                  <?php } else { ?>
                    <option value="" disabled <?php echo $prefill['department_id']===''?'selected':''; ?>>-- Select --</option>
                    <?php
                      $q = mysqli_query($con, 'SELECT department_id, department_name FROM department ORDER BY department_name');
                      if ($q) {
                        while ($row = mysqli_fetch_assoc($q)) {
                          $sel = ($row['department_id'] === $prefill['department_id']) ? 'selected' : '';
                          echo '<option value="'.htmlspecialchars($row['department_id']).'" '.$sel.'>'.htmlspecialchars($row['department_name']).'</option>';
                        }
                      }
                    ?>
                  <?php } ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-12">
                <label for="staff_name">Full Name</label>
                <input required type="text" id="staff_name" name="staff_name" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_name']); ?>">
              </div>
            </div>

            <div class="section-title">Contact & Identity</div>
            <div class="form-row">
              <div class="form-group col-md-12">
                <label for="staff_address">Address</label>
                <input required type="text" id="staff_address" name="staff_address" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_address']); ?>">
              </div>
              <div class="form-group col-md-6">
                <label for="staff_dob">Date of Birth</label>
                <input required type="date" id="staff_dob" name="staff_dob" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_dob']); ?>">
              </div>
              <div class="form-group col-md-6">
                <label for="staff_date_of_join">Date of Join</label>
                <input required type="date" id="staff_date_of_join" name="staff_date_of_join" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_date_of_join']); ?>">
              </div>
              <div class="form-group col-md-6">
                <label for="staff_email">Email</label>
                <input required type="email" id="staff_email" name="staff_email" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_email']); ?>">
              </div>
              <div class="form-group col-md-6">
                <label for="staff_pno">Telephone</label>
                <input required type="tel" id="staff_pno" name="staff_pno" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_pno']); ?>" placeholder="0770123456">
              </div>
              <div class="form-group col-md-6">
                <label for="staff_nic">NIC</label>
                <input required type="text" id="staff_nic" name="staff_nic" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_nic']); ?>">
              </div>
            </div>

            <div class="section-title">Employment</div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="staff_gender">Gender</label>
                <select required id="staff_gender" name="staff_gender" class="custom-select">
                  <option disabled <?php echo $prefill['staff_gender']===''?'selected':''; ?>>Choose Gender</option>
                  <option value="Male" <?php echo $prefill['staff_gender']==='Male'?'selected':''; ?>>Male</option>
                  <option value="Female" <?php echo $prefill['staff_gender']==='Female'?'selected':''; ?>>Female</option>
                  <option value="Transgender" <?php echo $prefill['staff_gender']==='Transgender'?'selected':''; ?>>Transgender</option>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label for="staff_epf">EPF No</label>
                <input required type="text" id="staff_epf" name="staff_epf" class="form-control" value="<?php echo htmlspecialchars($prefill['staff_epf']); ?>">
              </div>
              <div class="form-group col-md-6">
                <label for="staff_position">Position</label>
                <select required id="staff_position" name="staff_position" class="custom-select">
                  <option value="" disabled <?php echo $prefill['staff_position']===''?'selected':''; ?>>-- Select --</option>
                  <?php
                    $p = mysqli_query($con, 'SELECT staff_position_type_id, staff_position_type_name FROM staff_position_type ORDER BY staff_position_type_name');
                    if ($p) {
                      while ($row = mysqli_fetch_assoc($p)) {
                        $sel = ($row['staff_position_type_id'] === $prefill['staff_position']) ? 'selected' : '';
                        echo '<option value="'.htmlspecialchars($row['staff_position_type_id']).'" '.$sel.'>'.htmlspecialchars($row['staff_position_type_name']).'</option>';
                      }
                    }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label for="staff_type">Type</label>
                <select required id="staff_type" name="staff_type" class="custom-select">
                  <option disabled <?php echo $prefill['staff_type']===''?'selected':''; ?>>Choose Type</option>
                  <option value="Permanent" <?php echo $prefill['staff_type']==='Permanent'?'selected':''; ?>>Permanent</option>
                  <option value="On Contract" <?php echo $prefill['staff_type']==='On Contract'?'selected':''; ?>>On Contract</option>
                  <option value="Visiting Lecturer" <?php echo $prefill['staff_type']==='Visiting Lecturer'?'selected':''; ?>>Visiting Lecturer</option>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label for="staff_status">Status</label>
                <select required id="staff_status" name="staff_status" class="custom-select">
                  <option disabled <?php echo $prefill['staff_status']===''?'selected':''; ?>>Choose Status</option>
                  <option value="Working" <?php echo $prefill['staff_status']==='Working'?'selected':''; ?>>Working</option>
                  <option value="Terminated" <?php echo $prefill['staff_status']==='Terminated'?'selected':''; ?>>Terminated</option>
                  <option value="Resigned" <?php echo $prefill['staff_status']==='Resigned'?'selected':''; ?>>Resigned</option>
                </select>
              </div>
            </div>

            <div class="d-flex actions pt-2">
              <?php if ($mode === 'edit'): ?>
                <button type="submit" name="action" value="update" class="btn btn-primary">Update</button>
                <a href="?" class="btn btn-outline-secondary">Cancel</a>
              <?php else: ?>
                <button type="submit" name="action" value="create" class="btn btn-success">Add</button>
                <button type="reset" class="btn btn-outline-secondary">Reset</button>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php } ?>
  </div>
</div>

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once __DIR__ . '/../footer.php'; ?>
<!--END DON'T CHANGE THE ORDER-->
