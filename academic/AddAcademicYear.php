<?php // Start buffering BEFORE any output
if (!ob_get_level()) { ob_start(); }
?>
<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php 
$title = "Add Academic Year | SLGTI" ;
include_once("../config.php"); 
// Only admins can access this page; use JS redirect due to prior output
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'ADM') {
    echo '<script>window.location.href = "../dashboard/index.php";</script>';
    exit;
}
include_once("../head.php"); 
include_once("../menu.php");

 ?>
<!-- END DON'T CHANGE THE ORDER -->

<!-- BLOCK#2 START YOUR CODER HERE -->
<?php
// Helper for safe redirect
function ay_safe_redirect($url) {
  if (!headers_sent()) {
    header('Location: ' . $url);
  } else {
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
  }
  exit;
}

// Preload edit values before rendering
$academic_year = $first_semi_start_date = $first_semi_end_date = $second_semi_start_date = $second_semi_end_date = $academic_year_status = '';
if(isset($_GET['edit'])){
  $key = $_GET['edit'];
  if ($stmt = mysqli_prepare($con, 'SELECT academic_year, first_semi_start_date, first_semi_end_date, second_semi_start_date, second_semi_end_date, academic_year_status FROM academic WHERE academic_year = ?')) {
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $academic_year, $first_semi_start_date, $first_semi_end_date, $second_semi_start_date, $second_semi_end_date, $academic_year_status);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
  }
}

// Handle Add
if(isset($_POST['Add'])){
  $academic_year = $_POST['academic_year'] ?? '';
  $first_semi_start_date = $_POST['first_semi_start_date'] ?? '';
  $first_semi_end_date = $_POST['first_semi_end_date'] ?? '';
  $second_semi_start_date = $_POST['second_semi_start_date'] ?? '';
  $second_semi_end_date = $_POST['second_semi_end_date'] ?? '';
  $academic_year_status = $_POST['academic_year_status'] ?? '';
  if ($academic_year && $first_semi_start_date && $first_semi_end_date && $second_semi_start_date && $second_semi_end_date && $academic_year_status) {
    $sql = 'INSERT INTO academic(academic_year, first_semi_start_date, first_semi_end_date, second_semi_start_date, second_semi_end_date, academic_year_status) VALUES (?,?,?,?,?,?)';
    if ($stmt = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($stmt, 'ssssss', $academic_year, $first_semi_start_date, $first_semi_end_date, $second_semi_start_date, $second_semi_end_date, $academic_year_status);
      if (mysqli_stmt_execute($stmt)) {
        ay_safe_redirect('academic/AcademicYear.php?status=added');
      } else {
        echo '<div class="container mt-3"><div class="alert alert-danger">Error: ' . htmlspecialchars(mysqli_error($con)) . '</div></div>';
      }
      mysqli_stmt_close($stmt);
    }
  }
}

// Handle Edit
if(isset($_POST['Edit']) && isset($_GET['edit'])){
  $key = $_GET['edit'];
  $first_semi_start_date = $_POST['first_semi_start_date'] ?? '';
  $first_semi_end_date = $_POST['first_semi_end_date'] ?? '';
  $second_semi_start_date = $_POST['second_semi_start_date'] ?? '';
  $second_semi_end_date = $_POST['second_semi_end_date'] ?? '';
  $academic_year_status = $_POST['academic_year_status'] ?? '';
  if ($first_semi_start_date && $first_semi_end_date && $second_semi_start_date && $second_semi_end_date && $academic_year_status) {
    $sql = 'UPDATE academic SET first_semi_start_date=?, first_semi_end_date=?, second_semi_start_date=?, second_semi_end_date=?, academic_year_status=? WHERE academic_year=?';
    if ($stmt = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($stmt, 'ssssss', $first_semi_start_date, $first_semi_end_date, $second_semi_start_date, $second_semi_end_date, $academic_year_status, $key);
      if (mysqli_stmt_execute($stmt)) {
        ay_safe_redirect('academic/AcademicYear.php?status=updated');
      } else {
        echo '<div class="container mt-3"><div class="alert alert-danger">Error: ' . htmlspecialchars(mysqli_error($con)) . '</div></div>';
      }
      mysqli_stmt_close($stmt);
    }
  }
}
?>

<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
      <h3 class="mb-0"><?php echo isset($_GET['edit']) ? 'Edit Academic Year' : 'Add Academic Year'; ?></h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="ay">Academic Year</label>
            <input type="text" id="ay" name="academic_year" class="form-control" placeholder="e.g., 2025/2026" value="<?php echo htmlspecialchars($academic_year); ?>" <?php echo isset($_GET['edit']) ? 'readonly' : 'required'; ?>>
          </div>
          <div class="form-group col-md-6">
            <label for="status">Academic Year Status</label>
            <select id="status" name="academic_year_status" class="form-control" required>
              <option value="" disabled <?php echo $academic_year_status === '' ? 'selected' : ''; ?>>Select status</option>
              <option value="Active" <?php echo strtolower($academic_year_status) === 'active' ? 'selected' : ''; ?>>Active</option>
              <option value="Completed" <?php echo strtolower($academic_year_status) === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="fsd">First Semester Start Date</label>
            <input type="date" id="fsd" name="first_semi_start_date" class="form-control" value="<?php echo htmlspecialchars($first_semi_start_date); ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label for="fed">First Semester End Date</label>
            <input type="date" id="fed" name="first_semi_end_date" class="form-control" value="<?php echo htmlspecialchars($first_semi_end_date); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="ssd">Second Semester Start Date</label>
            <input type="date" id="ssd" name="second_semi_start_date" class="form-control" value="<?php echo htmlspecialchars($second_semi_start_date); ?>" required>
          </div>
          <div class="form-group col-md-6">
            <label for="sed">Second Semester End Date</label>
            <input type="date" id="sed" name="second_semi_end_date" class="form-control" value="<?php echo htmlspecialchars($second_semi_end_date); ?>" required>
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a href="academic/AcademicYear.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
          <div>
            <?php if(isset($_GET['edit'])) { ?>
              <button type="submit" name="Edit" class="btn btn-primary"><i class="far fa-save"></i> Update</button>
            <?php } else { ?>
              <button type="submit" name="Add" class="btn btn-success"><i class="fas fa-plus"></i> Add</button>
            <?php } ?>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- END YOUR CODER HERE -->

    <!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
    <?php 
    include_once("../footer.php");
    ?>
    <!-- END DON'T CHANGE THE ORDER -->
