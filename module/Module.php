<!--Block#1 start dont change the order-->
<?php
$title = "Module details | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!-- end dont change the order-->


<!-- Block#2 start your code -->
<?php
$gcourse_id = $gcourse_i = $sum = $mid = $cid = null;
$base = defined('APP_BASE') ? APP_BASE : '';
?>

<div class="container-fluid mt-3">
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Module Details</h5>
        <small class="text-muted">Browse and manage modules</small>
      </div>
      <div>
        <?php if (($_SESSION['user_type'] == 'ADM') || ($_SESSION['user_type'] == 'HOD')) { ?>
          <a href="<?php echo $base; ?>/module/AddModule.php<?php echo isset($_GET['course_id']) && $_GET['course_id'] !== '' ? ('?course_id=' . urlencode($_GET['course_id'])) : ''; ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Module</a>
        <?php } ?>
      </div>
    </div>
    <div class="card-body">
      <form class="form-row mb-3" method="GET">
        <div class="form-group col-md-6 mb-2">
          <label class="small text-muted">Filter by Course</label>
          <select class="custom-select" name="course_id" id="search">
            <option value="">-- All Courses --</option>
            <?php
            // Course list, scope to HOD's department if applicable
            $sql = "SELECT * FROM `course` WHERE 1=1";
            if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD' && !empty($_SESSION['department_code'])) {
              $dc = mysqli_real_escape_string($con, $_SESSION['department_code']);
              $sql .= " AND department_id='" . $dc . "'";
            }
            $sql .= " ORDER BY `course_name` ASC";

            $result = mysqli_query($con, $sql);
            $sel = isset($_GET['course_id']) ? (string)$_GET['course_id'] : '';
            if ($result && mysqli_num_rows($result) > 0) {
              while ($row = mysqli_fetch_assoc($result)) {
                $s = ($sel !== '' && $sel === $row['course_id']) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($row["course_id"]) . '" ' . $s . '>' . htmlspecialchars($row["course_name"]) . ' (' . htmlspecialchars($row['course_id']) . ')</option>';
              }
              mysqli_free_result($result);
            }
            ?>
          </select>
        </div>
        <div class="form-group col-md-6 d-flex align-items-end mb-2">
          <button type="submit" class="btn btn-outline-primary mr-2"><i class="fas fa-filter mr-1"></i>Apply</button>
          <a href="<?php echo $base; ?>/module/Module.php" class="btn btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="table-responsive table-responsive-sm">
        <table class="table table-hover table-striped mb-0">
          <thead class="thead-dark">
            <tr>
              <th style="width:60px">#</th>
              <th style="width:120px">Module ID</th>
              <th>Module Name</th>
              <th>Course</th>
              <th style="width:120px">Semester</th>
              <th style="width:140px" class="text-center">Notional Hours</th>
              <th style="width:140px" class="text-right">Actions</th>
            </tr>
          </thead>
          <?php
          // Delete module (ADM or HOD). HOD can delete only modules within their department
          if ((isset($_GET['dlt'])) && (isset($_GET['dllt']))) {
            $m_id = $_GET['dlt'];
            $cid = $_GET['dllt'];
            $isADM = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
            $isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
            $deptCode = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';
            $ok = false;
            $msg = '';
            if ($isADM) {
              $stmt = mysqli_prepare($con, 'DELETE FROM module WHERE module_id=? AND course_id=?');
              if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ss', $m_id, $cid);
                $ok = @mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
              }
            } elseif ($isHOD && $deptCode !== '') {
              // Ensure the module's course belongs to HOD department
              $stmt = mysqli_prepare($con, 'DELETE m FROM module m INNER JOIN course c ON c.course_id=m.course_id WHERE m.module_id=? AND m.course_id=? AND c.department_id=?');
              if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sss', $m_id, $cid, $deptCode);
                $ok = @mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
              }
            }
            if ($ok && mysqli_affected_rows($con) > 0) {
              echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>' . htmlspecialchars($m_id) . '</strong> deleted successfully<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
            } else {
              echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to delete (not found, not permitted, or referenced elsewhere).<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
            }
          }
          ?>
          <br>
          <tbody>
            <?php

            function getTotal($cid, $mid)
            {
              $con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
              // Replacing stored procedure with direct query
              $sql_r = "SELECT SUM(module_self_study_hours + module_lecture_hours + module_practical_hours) as value_sum 
                             FROM module 
                             WHERE module_id = '" . mysqli_real_escape_string($con, $mid) . "' 
                             AND course_id = '" . mysqli_real_escape_string($con, $cid) . "'";
              $result_r = mysqli_query($con, $sql_r);
              $x = 0;
              if ($result_r && $row_r = mysqli_fetch_array($result_r)) {
                $x = $row_r[0];
              }
              mysqli_close($con);
              return $x;
            }
            $sql = "SELECT `module_id`,
                    `module_name`,
                    `module_learning_hours`,
                    `semester_id`,
                    `module`.`course_id` AS `course_id`,
                    `module_relative_unit`,
                    `module_lecture_hours`,
                    `module_practical_hours`,
                    `module_self_study_hours`,
                    course.course_name as course_name FROM `module` INNER JOIN `course`
                    ON module.course_id = course.course_id WHERE 1=1";
            // Scope to HOD's department
            if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD' && !empty($_SESSION['department_code'])) {
              $dc = mysqli_real_escape_string($con, $_SESSION['department_code']);
              $sql .= " AND course.department_id='" . $dc . "'";
            }
            if (isset($_GET['course_id'])) {
              $gcourse_id = mysqli_real_escape_string($con, $_GET['course_id']);
              $sql .= " AND `module`.`course_id`= '" . $gcourse_id . "'";
            }

            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) {
              $count = 1;
              while ($row = mysqli_fetch_assoc($result)) {
                $mid = $row["module_id"];
                $cid = $row["course_id"];
                echo '
                    <tr>
                      <td>' . ($count) . '</td>
                      <td>' . htmlspecialchars($row["module_id"]) . '</td>
                      <td>' . htmlspecialchars($row["module_name"]) . '</td>
                      <td>' . htmlspecialchars($row["course_name"]) . '</td>
                      <td>' . htmlspecialchars($row["semester_id"]) . '</td>
                      <td class="text-center">' . (int)getTotal($cid, $mid) . '</td>
                      <td class="text-right">
                        <div class="btn-group btn-group-sm" role="group">
                          <a href="module/AddModule.php?edits=' . urlencode($row["module_id"]) . '&editc=' . urlencode($row["course_id"]) . '" class="btn btn-warning" title="Edit"><i class="far fa-edit"></i></a>
                          <button class="btn btn-danger" data-href="module/Module.php?dlt=' . htmlspecialchars($row["module_id"]) . '&dllt=' . htmlspecialchars($row["course_id"]) . '" data-toggle="modal" data-target="#confirm-delete" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                      </td>
                    </tr>';
                $count = $count + 1;
              }
            } else {
              echo '<tr><td colspan="7" class="text-center text-muted">No modules found</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>


</tbody>
</table>
</div>

</div>
</div>
<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title" id="confirmDeleteLabel">Confirm delete</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">This action cannot be undone. Delete this module?</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <a href="#" class="btn btn-danger btn-ok">Delete</a>
      </div>
    </div>
  </div>
</div>
<script>
  $('#confirm-delete').on('show.bs.modal', function(e) {
    var href = $(e.relatedTarget).data('href') || '#';
    $(this).find('.btn-ok').attr('href', href);
  });
</script>
<style>
  .card-header h5 {
    font-weight: 600;
  }

  label {
    font-weight: 600;
  }

  .table thead th {
    vertical-align: middle;
  }

  @media (max-width: 575.98px) {
    .card-body .form-row>[class^="col-"] {
      margin-bottom: .75rem;
    }
  }
</style>
<!-- end your code -->
<!--Block#3 start dont change the order-->
<?php include_once("../footer.php"); ?>
<!--  end dont change the order-->