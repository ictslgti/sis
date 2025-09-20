<?php // Start buffering BEFORE any output
if (!ob_get_level()) { ob_start(); }
?>
<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php 
$title = "Department Details | SLGTI" ;
// Removed session_start() since it's already in config.php
include_once("../config.php"); 
// Only admins can access this page
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'ADM') {
    header('Location: ../dashboard/index.php');
    exit;
}
include_once("../head.php"); 
include_once("../menu.php");
?>
<!-- END DON'T CHANGE THE ORDER -->

<!-- BLOCK#2 START YOUR CODER HERE -->
<?php
// Safe redirect helper
function safe_redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
    }
    exit;
}

// Preload values for edit before rendering the form
$id = $name = null;
if(isset($_GET['edit'])){
    $id = $_GET['edit'];
    $sql = "SELECT department_id, department_name FROM `department` WHERE `department_id` = ?";
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, 's', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1){
            $row = mysqli_fetch_assoc($result);
            $id = $row['department_id'];
            $name = $row['department_name']; 
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<div class="container my-4">
  <div class="card shadow-sm">
    <div class="card-header bg-dark text-white">
      <h3 class="mb-0"><?php echo isset($_GET['edit']) ? 'Edit Department' : 'Add New Department'; ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($_GET['error'])) { echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>'; } ?>
      <form method="POST">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label for="deptId">Department ID</label>
            <input id="deptId" class="form-control" type="text" name="id" value="<?php echo htmlspecialchars($id ?? ''); ?>" placeholder="Department ID" required <?php echo isset($_GET['edit']) ? 'readonly' : ''; ?>>
          </div>
          <div class="form-group col-md-8">
            <label for="deptName">Department Name</label>
            <input id="deptName" class="form-control" type="text" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" placeholder="Department Name" required>
          </div>
        </div>
        <div class="d-flex justify-content-between">
          <a href="../department/Department.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
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
<?php

// Handle Add
if(isset($_POST['Add'])){
   if (!empty($_POST['id']) && !empty($_POST['name'])){
       $id = $_POST['id'];
       $name = $_POST['name'];
       $sql = "INSERT INTO `department` (`department_id`,`department_name`) VALUES (?, ?)";
       if ($stmt = mysqli_prepare($con, $sql)) {
           mysqli_stmt_bind_param($stmt, 'ss', $id, $name);
           if (mysqli_stmt_execute($stmt)){
               safe_redirect('../department/Department.php?status=added');
           } else {
               echo '<div class="alert alert-danger">Error adding department: ' . htmlspecialchars(mysqli_error($con)) . '</div>';
           }
           mysqli_stmt_close($stmt);
       }
   }
}

// Handle Edit
if(isset($_POST['Edit'])){
    if (!empty($_GET['edit']) && !empty($_POST['name'])){
        $editId = $_GET['edit'];
        $name = $_POST['name'];
        $sql = "UPDATE `department` SET `department_name` = ? WHERE `department_id` = ?";
        if ($stmt = mysqli_prepare($con, $sql)) {
            mysqli_stmt_bind_param($stmt, 'ss', $name, $editId);
            if (mysqli_stmt_execute($stmt)){
                safe_redirect('../department/Department.php?status=updated');
            } else {
                echo '<div class="alert alert-danger">Error updating department: ' . htmlspecialchars(mysqli_error($con)) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

?>


<!-- END YOUR CODER HERE -->

    <!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
    <?php 
   include_once("../footer.php");
    ?>
    <!-- END DON'T CHANGE THE ORDER -->
