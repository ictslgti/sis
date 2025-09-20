<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php
$title = "Department Details | SLGTI";
// Removed session_start() since it's already in config.php
include_once("../config.php");
// Restrict students from accessing department details (use JS redirect due to prior HTML comment)
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU') {
  echo '<script>window.location.href = "../dashboard/index.php";</script>';
  exit;
}
include_once("../head.php");
include_once("../menu.php");

// Ensure database connection is established
if (!isset($con) || !$con) {
  die("Database connection failed: " . mysqli_connect_error());
}
// Determine role and department
$isADM = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
$deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : null;
?>
<!-- END DON'T CHANGE THE ORDER -->

<!-- BLOCK#2 START YOUR CODER HERE -->


<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0">Department Details</h3>
        <small class="text-light-50">Manage and view all departments</small>
      </div>
      <?php if ($isADM) { ?>
        <a href="department/AddDepartment.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Department</a>
      <?php } ?>
    </div>
    <div class="card-body p-0">
      <?php if (!empty($_GET['status'])) {
        $status = $_GET['status']; ?>
        <div class="alert <?php echo ($status === 'added' || $status === 'updated') ? 'alert-success' : (($status === 'deleted') ? 'alert-success' : 'alert-info'); ?> m-3 mb-0">
          <?php
          if ($status === 'added') echo 'Department added successfully.';
          elseif ($status === 'updated') echo 'Department updated successfully.';
          elseif ($status === 'deleted') echo 'Department deleted successfully.';
          ?>
        </div>
      <?php } ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="thead-dark">
            <tr>
              <th scope="col">Department ID</th>
              <th scope="col">Department Name</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php

            // Simple CSRF token for delete
            if (empty($_SESSION['csrf_token'])) {
              $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            }

            // Also handle delete via GET with CSRF token (fallback if forms are blocked by markup/JS)
            if(isset($_GET['delete']) && isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])){
                if ($isADM) {
                    $department_id = $_GET['delete'];
                    // Check dependencies in course table
                    $chkSql = "SELECT COUNT(*) AS cnt FROM course WHERE department_id = ?";
                    if ($chk = mysqli_prepare($con, $chkSql)) {
                        mysqli_stmt_bind_param($chk, 's', $department_id);
                        mysqli_stmt_execute($chk);
                        mysqli_stmt_bind_result($chk, $cnt);
                        $cnt = 0;
                        mysqli_stmt_fetch($chk);
                        mysqli_stmt_close($chk);
                        if ((int)$cnt > 0) {
                            echo '<div class="alert alert-warning m-3">This department cannot be deleted because one or more courses are allocated to it.</div>';
                        } else {
                            $sql = "DELETE FROM `department` WHERE `department_id` = ?";
                            $stmt = mysqli_prepare($con, $sql);
                            mysqli_stmt_bind_param($stmt, 's', $department_id);
                            if (mysqli_stmt_execute($stmt)){
                                echo '<div class="alert alert-success m-3">Department deleted successfully.</div>';
                            } else {
                                $errno = mysqli_errno($con);
                                if ($errno == 1451) {
                                    echo '<div class="alert alert-warning m-3">Cannot delete: this department is referenced by other records (e.g., courses).</div>';
                                } else {
                                    echo '<div class="alert alert-danger m-3">Error deleting record: '. htmlspecialchars(mysqli_error($con)) .'</div>';
                                }
                            }
                            mysqli_stmt_close($stmt);
                        }
                    }
                } else {
                    echo '<div class="alert alert-danger m-3">Unauthorized action.</div>';
                }
            }

            // Handle delete via POST for reliability and security
            if(isset($_POST['delete']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
                if ($isADM) {
                    $department_id = $_POST['delete'];
                    // Check dependencies in course table
                    $chkSql = "SELECT COUNT(*) AS cnt FROM course WHERE department_id = ?";
                    if ($chk = mysqli_prepare($con, $chkSql)) {
                        mysqli_stmt_bind_param($chk, 's', $department_id);
                        mysqli_stmt_execute($chk);
                        mysqli_stmt_bind_result($chk, $cnt);
                        $cnt = 0;
                        mysqli_stmt_fetch($chk); // on success, $cnt is populated
                        mysqli_stmt_close($chk);
                        if ((int)$cnt > 0) {
                            echo '<div class="alert alert-warning m-3">This department cannot be deleted because one or more courses are allocated to it.</div>';
                        } else {
                            $sql = "DELETE FROM `department` WHERE `department_id` = ?";
                            $stmt = mysqli_prepare($con, $sql);
                            mysqli_stmt_bind_param($stmt, 's', $department_id);
                            if (mysqli_stmt_execute($stmt)){
                                echo '<div class="alert alert-success m-3">Department deleted successfully.</div>';
                            } else {
                                // Handle potential FK constraint error
                                $errno = mysqli_errno($con);
                                if ($errno == 1451) {
                                    echo '<div class="alert alert-warning m-3">Cannot delete: this department is referenced by other records (e.g., courses).</div>';
                                } else {
                                    echo '<div class="alert alert-danger m-3">Error deleting record: '. htmlspecialchars(mysqli_error($con)) .'</div>';
                                }
                            }
                            mysqli_stmt_close($stmt);
                        }
                    }
                } else {
                    echo '<div class="alert alert-danger m-3">Unauthorized action.</div>';
                }
            }

            // Replace the stored procedure call with a direct query
            if ($isHOD && !empty($deptCode)) {
              $sql = "SELECT d.*, (SELECT COUNT(*) FROM course c WHERE c.department_id = d.department_id) AS course_count FROM department d WHERE d.department_id = ? ORDER BY d.department_id ASC";
              $stmt = mysqli_prepare($con, $sql);
              mysqli_stmt_bind_param($stmt, 's', $deptCode);
              mysqli_stmt_execute($stmt);
              $result = mysqli_stmt_get_result($stmt);
            } else {
              $sql = "SELECT d.*, (SELECT COUNT(*) FROM course c WHERE c.department_id = d.department_id) AS course_count FROM department d ORDER BY d.department_id ASC";
              $result = mysqli_query($con, $sql);
            }

            if ($result === false) {
              die("Error executing query: " . mysqli_error($con));
            }

            if (mysqli_num_rows($result) > 0) {
              while ($row = mysqli_fetch_assoc($result)) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row["department_id"]) . '</td>';
                echo '<td>' . htmlspecialchars($row["department_name"]) . '</td>';
                echo '<td class="text-right">';
                echo '<a href="' . (defined('APP_BASE') ? APP_BASE : '') . '/course/Course.php?id=' . urlencode($row["department_id"]) . '" class="btn btn-sm btn-primary mr-1" title="View Courses"><i class="fas fa-book"></i></a>';
                if ($isADM) {
                  echo '<a href="AddDepartment.php?edit=' . urlencode($row["department_id"]) . '" class="btn btn-sm btn-warning mr-1"><i class="far fa-edit"></i> Edit</a>';
                  if (!empty($row['course_count']) && (int)$row['course_count'] > 0) {
                    echo '<button class="btn btn-sm btn-danger" disabled title="Cannot delete: ' . (int)$row['course_count'] . ' course(s) allocated"><i class="fas fa-trash"></i> Delete</button>';
                  } else {
                    // Direct GET link including CSRF token as a robust fallback
                    echo '<a href="?delete=' . urlencode($row["department_id"]) . '&csrf_token=' . urlencode($_SESSION['csrf_token']) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this department?\');"><i class="fas fa-trash"></i> Delete</a>';
                  }
                }
                echo '</td>';
                echo '</tr>';
              }
            } else {
              echo '<tr><td colspan="3" class="text-center text-muted">No departments found.</td></tr>';
            }


            ?>

            <!-- <tr class="table-light"> -->


            <!-- <td>MT/002</td>
      <td>Mechanical Technology Department</td> -->
            <!-- <td><div class="btn-toolbar mb-3" role="toolbar" aria-label="Toolbar with button groups"> -->
            <!-- <div class="btn-group mr-2" role="group" aria-label="First group"> -->
            <!-- <a href="Course"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Course</i></a> -->
            <!-- <a href="BatchDetails" class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Batch</i></a> -->
            <!-- </td> -->

            <!-- <button type="button" class="btn btn-secondary">Courses</button> -->
            <!-- <div class="input-group-text" ><i class="fas fa-eye"></i></div> -->
            <!-- <button type="button" class="btn btn-secondary">Batches</button></td> -->


            <!-- <td><button type="button" class="btn btn-link">Add</button> </td>  -->
            <!-- </tr> -->
            <!-- <tr class="table-light"> -->


            <!-- <td>EET/003</td> -->
            <!-- <td>Electrical & Electronic Technology Department</td> -->
            <!-- <td><div class="btn-toolbar mb-3" role="toolbar" aria-label="Toolbar with button groups"> -->
            <!-- <div class="btn-group mr-2" role="group" aria-label="First group"> -->
            <!-- <a href="Course"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Course</i></a> -->
            <!-- <a href="BatchDetails"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Batch</i></a> -->
            <!-- </td> -->
            <!-- <button type="button" class="btn btn-secondary">Courses</button> -->
            <!-- <div class="input-group-text" ><i class="fas fa-eye"></i></div> -->
            <!-- <button type="button" class="btn btn-secondary">Batches</button></td> -->
            <!-- <td><button type="button" class="btn btn-link">Add</button> </td>  -->
            <!-- </tr> -->
            <!-- <tr class="table-light "> -->


            <!-- <td>FT/004</td> -->
            <!-- <td>Food Technology Department</td> -->
            <!-- <td><div class="btn-toolbar mb-3" role="toolbar" aria-label="Toolbar with button groups"> -->
            <!-- <div class="btn-group mr-2" role="group" aria-label="First group"> -->
            <!-- <a href="Course"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Course</i></a> -->
            <!-- <a href="BatchDetails" class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Batch</i></a> -->
            <!-- </td> -->
            <!-- <button type="button" class="btn btn-secondary">Courses</button> -->
            <!-- <div class="input-group-text" ><i class="fas fa-eye"></i></div> -->
            <!-- <button type="button" class="btn btn-secondary">Batches</button></td> -->
            <!-- <td><button type="button" class="btn btn-link">Add</button> </td>  -->
            <!-- </tr> -->
            <!-- <tr class="table-light"> -->


            <!-- <td>AAT/005</td> -->
            <!-- <td>Automotive & Agricultural Technology Department</td> -->
            <!-- <td><div class="btn-toolbar mb-3" role="toolbar" aria-label="Toolbar with button groups"> -->
            <!-- <div class="btn-group mr-2" role="group" aria-label="First group"> -->
            <!-- <a href="Course"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Course</i></a> -->
            <!-- <a href="BatchDetails"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Batch</i></a> -->
            <!-- </td> -->
            <!-- <button type="button" class="btn btn-secondary">Courses</button> -->
            <!-- <div class="input-group-text" ><i class="fas fa-eye"></i></div> -->
            <!-- <button type="button" class="btn btn-secondary">Batches</button></td> -->
            <!-- <td><button type="button" class="btn btn-link">Add</button> </td>  -->
            <!-- </tr> -->
            <!-- <tr class="table-light"> -->


            <!-- <td>CT/006</td> -->
            <!-- <td>  </td> -->
            <!-- <td><div class="btn-toolbar mb-3" role="toolbar" aria-label="Toolbar with button groups"> -->
            <!-- <div class="btn-group mr-2" role="group" aria-label="First group"> -->
            <!-- <a href="Course"  class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Course</i></a> -->
            <!-- <a href="BatchDetails" class="btn btn-outline-secondary" role="button" aria-pressed="true"><i class="fas fa-eye">&nbsp;&nbsp;Batch</i></a> -->
            <!-- </td> -->
            <!-- <button type="button" class="btn btn-secondary">Courses</button> -->
            <!-- <div class="input-group-text" ><i class="fas fa-eye"></i></div> -->
            <!-- <button type="button" class="btn btn-secondary">Batches</button></td> -->
            <!-- <td><button type="button" class="btn btn-link">Add</button> </td>  -->
            <!-- </tr> -->

          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmDeleteLabel">Confirm Delete</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete this department? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <a class="btn btn-danger btn-ok">Delete</a>
      </div>
    </div>
  </div>
</div>

<script>
  // Pass the delete href to the modal's Delete button
  document.addEventListener('DOMContentLoaded', function() {
    $('#confirm-delete').on('show.bs.modal', function(e) {
      var href = $(e.relatedTarget).data('href');
      $(this).find('.btn-ok').attr('href', href);
    });
  });
</script>


<!-- END YOUR CODER HERE -->

<!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
<?php
include_once("../footer.php");
?>
<!-- END DON'T CHANGE THE ORDER -->