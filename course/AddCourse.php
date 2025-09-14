	
<!--Block#1 start dont change the order-->
<?php 
$title="Add Course details | SLGTI";    
include_once("../config.php"); 
include_once("../head.php"); 
include_once("../menu.php");
?>
<!-- end dont change the order-->


<!-- Block#2 start your code -->
<?php
  $cid = $cname = $ctraining = $cojt =  $nvq = $did = null;
  $isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
  $deptCode = isset($_SESSION['department_code']) ? trim((string)$_SESSION['department_code']) : '';

  if (isset($_GET['edits'])) {
    $cid = $_GET['edits'];
    $stmt = mysqli_prepare($con, 'SELECT * FROM course WHERE course_id=?');
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 's', $cid);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);
      if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        $cname = $row['course_name'];
        $ctraining = $row['course_institute_training'];
        $cojt = $row['course_ojt_duration'];
        $did = $row['department_id'];
        $nvq = $row['course_nvq_level'];
        // HODs may only edit their own department courses
        if ($isHOD && $deptCode !== '' && $did !== $deptCode) {
          echo '<div class="container mt-4"><div class="alert alert-danger">You can only edit courses in your department.</div></div>';
          include_once ("../footer.php");
          exit;
        }
      }
      mysqli_stmt_close($stmt);
    }
  }

if (isset($_POST['Editing'])) {
  if (!empty($_POST['co_training']) && !empty($_POST['co_name']) && !empty($_POST['co_ojt']) && !empty($_POST['n_level']) && !empty($_GET['edits'])) {
    $cname = $_POST['co_name'];
    $ctraining = $_POST['co_training'];
    $cojt = $_POST['co_ojt'];
    $nvq = $_POST['n_level'];
    $cid = $_GET['edits'];
    // HOD cannot change department; enforce their own
    $did = $isHOD && $deptCode !== '' ? $deptCode : ($_POST['d_name'] ?? '');
    if ($stmt = mysqli_prepare($con, 'UPDATE course SET course_name=?, course_nvq_level=?, course_ojt_duration=?, course_institute_training=?, department_id=? WHERE course_id=?' . ($isHOD && $deptCode !== '' ? ' AND department_id=?' : ''))) {
      if ($isHOD && $deptCode !== '') {
        mysqli_stmt_bind_param($stmt, 'sssssss', $cname, $nvq, $cojt, $ctraining, $did, $cid, $deptCode);
      } else {
        mysqli_stmt_bind_param($stmt, 'ssssss', $cname, $nvq, $cojt, $ctraining, $did, $cid);
      }
      if (@mysqli_stmt_execute($stmt)) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>'.htmlspecialchars($cid).'</strong> updated successfully<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
      } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to update. It may be referenced elsewhere.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
      }
      mysqli_stmt_close($stmt);
    }
  }
}

if (isset($_POST['Adding'])) {
  if (!empty($_POST['co_training']) && !empty($_POST['co_name']) && !empty($_POST['co_ojt']) && !empty($_POST['n_level']) && !empty($_POST['co_id'])) {
    $cid = $_POST['co_id'];
    $cname = $_POST['co_name'];
    $ctraining = $_POST['co_training'];
    $cojt = $_POST['co_ojt'];
    // HOD can only add to own department
    $did = $isHOD && $deptCode !== '' ? $deptCode : ($_POST['d_name'] ?? '');
    $nvq = $_POST['n_level'];
    if ($stmt = mysqli_prepare($con, 'INSERT INTO course(course_id, course_name, course_nvq_level, department_id, course_ojt_duration, course_institute_training) VALUES (?,?,?,?,?,?)')) {
      mysqli_stmt_bind_param($stmt, 'ssssss', $cid, $cname, $nvq, $did, $cojt, $ctraining);
      if (@mysqli_stmt_execute($stmt)) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>'.htmlspecialchars($cid).'</strong> added successfully<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
      } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Failed to add course. It may already exist.<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
      }
      mysqli_stmt_close($stmt);
    }
  }
}

?>

<hr class="mb-8 mt-4">
<div class="container-fluid mt-3">
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">
              <?php
                if(isset($_GET['edits'])) { echo 'Edit Course'; }
                else { echo 'Add Course'; }
              ?>
            </h5>
            <small class="text-muted">Manage course information and settings</small>
          </div>
        </div>
        <div class="card-body">
          <form method="POST">
            <div class="row">

              <div class="col-md-6 mb-3">
                <div class="form-group mb-0">
                  <label for="co_id">Course ID</label>
                  <input type="text" id="co_id" name="co_id" class="form-control" placeholder="e.g., BIT" value="<?php echo $cid ?>" required  <?php if(isset($_GET['edits'])) { echo "disabled='true'"; } ?>>
                </div>
              </div>

              
              <div class="col-md-6 mb-3">
                <div class="form-group mb-0">
                  <label for="co_name">Course Name</label>
                  <input type="text" id="co_name" class="form-control" placeholder="Enter course name" name="co_name" value="<?php echo $cname ?>" required>
                </div>
              </div>

            </div>

            <div class="row">

              <div class="col-md-6 mb-3"> 
                <div class="form-group mb-0">
                  <label for="co_training">Duration – Institute Training</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Months</span>
                    </div>
                    <input type="text" id="co_training" class="form-control" placeholder="Months in digits" name="co_training" value ="<?php echo $ctraining ?>" onkeypress="Number(event)" min="1" maxlength="4" required>
                  </div>
                </div>
              </div>
              
              <div class="col-md-6 mb-3"> 
                <div class="form-group mb-0">
                  <label for="co_ojt">Duration – OJT</label>
                  <div class="input-group">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Months</span>
                    </div>
                    <input type="text" id="co_ojt" class="form-control" placeholder="Months in digits" name="co_ojt" value="<?php echo $cojt ?>" onkeypress="Number(event)" min="1"  maxlength="2" required>
                  </div>
                </div>
              </div>
            
            </div>

            <div class="row">

              <div class="col-md-6 mb-3">
                <label for="Department">Department</label>
                <?php if ($isHOD && $deptCode !== ''): ?>
                  <?php
                    // Fetch HOD department name for display
                    $dn = $deptCode;
                    $dnm = $deptCode;
                    if ($rsd = mysqli_query($con, "SELECT department_name FROM department WHERE department_id='".mysqli_real_escape_string($con,$deptCode)."' LIMIT 1")) {
                      if ($rr = mysqli_fetch_assoc($rsd)) { $dnm = $rr['department_name']; }
                      mysqli_free_result($rsd);
                    }
                  ?>
                  <input type="text" class="form-control" value="<?php echo htmlspecialchars($dnm); ?>" disabled>
                  <input type="hidden" name="d_name" value="<?php echo htmlspecialchars($deptCode); ?>">
                <?php else: ?>
                  <select class="custom-select d-block w-100" name="d_name" required>
                    <option disabled <?php echo $did? '' : 'selected'; ?>>Select Department Name...</option>
                    <?php
                      $sql = "SELECT * FROM department ORDER BY department_name";
                      $result = mysqli_query($con, $sql);
                      if ($result && mysqli_num_rows($result)>0) {
                        while($row = mysqli_fetch_assoc($result)) {
                          $sel = ($row['department_id'] == $did) ? 'selected' : '';
                          echo '<option value="'.htmlspecialchars($row['department_id']).'" '.$sel.'>'.htmlspecialchars($row['department_name']).'</option>';
                        }
                        mysqli_free_result($result);
                      }
                    ?>
                  </select>
                <?php endif; ?>
              </div>

              <div class="col-md-6 mb-3">
                <div class="form-group mb-0">
                  <label for="n_level">NVQ Level</label>
                  <input type="text" class="form-control" id="n_level" placeholder="NVQ: 3-6 or BRI" name="n_level" value="<?php echo $nvq ?>" onkeypress="NumberR(event)" min="3" max="6" maxlength="3" required>
                </div>
              </div>
            
                
                
      
<br><br>
<?php
      if(isset($_GET['edits'])) {
        echo '<button class="btn btn-primary btn-block" type="submit" name="Editing"><i class="fas fa-save mr-1"></i> Save Changes</button>';
      } else {
        echo '<button class="btn btn-success btn-block" type="submit" name="Adding"><i class="fas fa-plus mr-1"></i> Create Course</button>';
      }
      ?>
            </div>
            </form>
            <script>
            function Number(evt) {
            var num = String.fromCharCode(evt.which);

            if (!(/[1-9]/.test(num))) {
                evt.preventDefault();
                alert("Duration must be above 0");
            } else if ((/[1-9]/.test(num))) {
            }
        }

        function NumberR(evtT) {
            var numb = String.fromCharCode(evtT.which);

            if (!(/[3-6]/.test(numb))&& !(/["BRI"]/.test(numb))) {
                evtT.preventDefault();
                alert("NVQ Level must be 3-5 and BRI for Bridging  !");
            } else if ((/[3-6]/.test(numb))) {
            }
        }
        </script>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- end your code -->


<!--Block#3 start dont change the order-->
<style>
  /* Small visual tweaks for a modern look */
  .card-header h5 { font-weight: 600; }
  label { font-weight: 600; }
  .input-group-text { min-width: 70px; justify-content: center; }
  @media (max-width: 575.98px) {
    .card-body .row > [class^="col-"] { margin-bottom: .75rem; }
  }
</style>

<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
    
    
  
