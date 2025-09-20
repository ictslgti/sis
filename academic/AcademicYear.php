<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php 
$title = "Academic Year Details | SLGTI" ;
include_once("../config.php"); 
// Restrict students from accessing academic year details
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU') {
    header('Location: ../dashboard/index.php');
    exit;
}
include_once("../head.php"); 
include_once("../menu.php");

 ?>
<!-- END DON'T CHANGE THE ORDER -->

<!-- BLOCK#2 START YOUR CODER HERE -->
<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0">SLGTI Academic Year</h3>
        <small class="text-light-50">Manage academic years and dates</small>
      </div>
      <?php if(($_SESSION['user_type'] =='ADM')) { ?>
        <a href="AddAcademicYear.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Academic Year</a>
      <?php }?>
    </div>
    <div class="card-body p-0">
      <?php if (!empty($_GET['status'])) { $status = $_GET['status']; ?>
        <div class="alert <?php echo ($status === 'added' || $status === 'updated' || $status === 'deleted') ? 'alert-success' : 'alert-info'; ?> m-3 mb-0">
          <?php
            if ($status === 'added') echo 'Academic Year added successfully.';
            elseif ($status === 'updated') echo 'Academic Year updated successfully.';
            elseif ($status === 'deleted') echo 'Academic Year deleted successfully.';
          ?>
        </div>
      <?php } ?>
      <?php
        // CSRF token for delete
        if (empty($_SESSION['csrf_token'])) {
          $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        // Handle delete via GET with CSRF token
        if(isset($_GET['delete']) && isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])){
          $academic_year = $_GET['delete'];
          $sql = "DELETE FROM `academic` WHERE `academic_year` = ?";
          if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, 's', $academic_year);
            if (mysqli_stmt_execute($stmt)){
              echo '<div class="alert alert-success m-3">Academic Year deleted successfully.</div>';
            } else {
              echo '<div class="alert alert-danger m-3">Error deleting record: '. htmlspecialchars(mysqli_error($con)) .'</div>';
            }
            mysqli_stmt_close($stmt);
          }
        }
      ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="thead-dark">
            <tr>
              <th scope="col">Academic Year</th>
              <th scope="col">First Semester Start</th>
              <th scope="col">First Semester End</th>
              <th scope="col">Second Semester Start</th>
              <th scope="col">Second Semester End</th>
              <th scope="col">Status</th>
              <?php if(($_SESSION['user_type'] =='ADM')) { ?><th scope="col" class="text-right">Actions</th><?php }?>
            </tr>
          </thead>
          <tbody>
<?php
  $sql = "SELECT * FROM academic ORDER BY academic_year DESC";
  $result = mysqli_query($con, $sql);
  if ($result && mysqli_num_rows($result)>0){
      while ($row = mysqli_fetch_assoc($result)){
          $statusClass = strtolower($row['academic_year_status']) === 'active' ? 'badge-success' : 'badge-secondary';
          echo '<tr>';
          echo '<td>' . htmlspecialchars($row["academic_year"]) . '</td>';
          echo '<td>' . htmlspecialchars($row["first_semi_start_date"]) . '</td>';
          echo '<td>' . htmlspecialchars($row["first_semi_end_date"]) . '</td>';
          echo '<td>' . htmlspecialchars($row["second_semi_start_date"]) . '</td>';
          echo '<td>' . htmlspecialchars($row["second_semi_end_date"]) . '</td>';
          echo '<td><span class="badge ' . $statusClass . '">' . htmlspecialchars($row["academic_year_status"]) . '</span></td>';
          if(($_SESSION['user_type'] =='ADM')) {
            echo '<td class="text-right">';
            echo '<a href="AddAcademicYear.php?edit=' . rawurlencode($row["academic_year"]) . '" class="btn btn-sm btn-warning mr-1"><i class="far fa-edit"></i> Edit</a>';
            echo '<a href="?delete=' . rawurlencode($row["academic_year"]) . '&csrf_token=' . urlencode($_SESSION['csrf_token']) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this academic year?\');"><i class="fas fa-trash"></i> Delete</a>';
            echo '</td>';
          }
          echo '</tr>';
      }
  } else {
      echo '<tr><td colspan="7" class="text-center text-muted">No academic years found.</td></tr>';
  }
?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- END YOUR CODER HERE -->

    <!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
    <?php 
    include_once("../footer.php");
    ?>
    <!-- END DON'T CHANGE THE ORDER -->
