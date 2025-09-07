	<!--Block#1 start dont change the order-->
	<?php 
$title="Course details | SLGTI";    
include_once("../config.php"); 
include_once("../head.php"); 
include_once("../menu.php");
?>
	<!-- end dont change the order-->


	<!-- Block#2 start your code -->

	<div class="shadow  p-3 mb-1 bg-white rounded">
	    <div class="highlight-blue">
	        <h1 class="display-4 text-center">Course Details</h1>
	        <!-- <p class="text-center"></p> -->
	    </div>
	</div>
    <div class="row">
    <div class="col-sm-8"></div>
	<div class="col-sm-4">
    <form class="form-inline md-form form-sm mt-4" method="GET">
    </form><br>
    </div>
    </div>
	<div class="row">
	    <div class="col-md-12 col-sm-12">
	        <div class="table-responsive table-responsive-sm">
	            <table class="table table-hover" id="employee_table">
	                <thead class="thead-dark">
	                    <tr>
                            <th scope="col">No.</th>
	                        <th scope="col">ID</th>
	                        <th scope="col">Course</th>
	                        <th scope="col">Department</th>
	                        <th scope="col">Level (NVQ)</th>
	                        <?php if(($_SESSION['user_type'] =='ADM')) { ?><th scope="col">Actions</th> <?php }?>
	                    </tr>
	                </thead>

                <?php
                    // Role flags
                    $isADM = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
                    $isHOD = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'HOD';
                    $deptCode = isset($_SESSION['department_code']) ? $_SESSION['department_code'] : null;

                    // Handle admin-only delete
                    if (isset($_GET['delete_id']) && $isADM) {                
                        $c_id = $_GET['delete_id'];
                        $sql = "DELETE FROM course WHERE course_id = '".mysqli_real_escape_string($con, $c_id)."'";
                        if (mysqli_query($con, $sql)) {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                               .'<strong> '.htmlspecialchars($c_id).' </strong> Record has been deleted successfully'
                               .'<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                               .'<span aria-hidden="true">&times;</span>'
                               .'</button>'
                               .'</div>';
                        } else {
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                               .'<strong> '.htmlspecialchars($c_id).' </strong> is used in another table'
                               .'</div>';
                        }
                    }
                ?>


                <tbody>
                    <?php 
                    // Build base query
                    $sql = "SELECT c.course_id AS course_id, 
                                     c.course_name AS course_name, 
                                     d.department_name AS department_name,
                                     c.course_nvq_level AS course_nvq_level
                              FROM course c
                              JOIN department d ON c.department_id = d.department_id
                              WHERE 1=1";

                    // If HOD is logged in, always scope to their own department
                    if ($isHOD && !empty($deptCode)) {
                        $id = $deptCode;
                        $sql .= " AND c.department_id='".mysqli_real_escape_string($con, $id)."'";
                    } elseif (isset($_GET['id'])) {
                        $id = $_GET['id'];
                        $sql .= " AND c.department_id='".mysqli_real_escape_string($con, $id)."'";
                    }

                    $result = mysqli_query($con, $sql);

                    if ($result && mysqli_num_rows($result) > 0) { 
                        $count = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo '<tr>';
                            echo '  <td>'. $count.'.'. "<br>" .'</td>';
                            echo '  <td scope="row">'. htmlspecialchars($row['course_id']) . "<br>" .'</td>';
                            echo '  <td>'. htmlspecialchars($row['course_name']) .  "<br>" .'</td>';
                            echo '  <td>'. htmlspecialchars($row['department_name']) .  "<br>" .'</td>';
                            echo '  <td>'. htmlspecialchars($row['course_nvq_level']) .  "<br>" .'</td>';
                            if (($_SESSION['user_type'] == 'ADM')) {
                                echo '  <td>';
                                echo '    <a href="'.(defined('APP_BASE') ? APP_BASE : '').'/course/Module.php?course_id='.urlencode($row['course_id']).'" class="btn btn-primary btn-sm btn-icon-split"><span class="text">Modules</span></a> ';
                                echo '    <a href="'.(defined('APP_BASE') ? APP_BASE : '').'/batch/BatchDetails.php?course_id='.urlencode($row['course_id']).'" class="btn btn-sm btn-primary btn-icon-split"><span class="text">Batch</span></a> ';
                                echo '    <a href="'.(defined('APP_BASE') ? APP_BASE : '').'/course/AddCourse.php?edits='.urlencode($row['course_id']).'" class="btn btn-sm btn-warning"><i class="far fa-edit"></i></a> ';
                                echo '    <button class="btn btn-sm btn-danger" data-href="?delete_id='.htmlspecialchars($row['course_id']).'" data-toggle="modal" data-target="#confirm-delete"><i class="fas fa-trash"></i></button>';
                                echo '  </td>';
                            }
                            echo '</tr>';
                            $count++;
                        }
                    } else {
                        echo "0 results";
                    }
                    ?>
                </tbody>
            </table>
            <?php if(($_SESSION['user_type'] =='ADM') || ($_SESSION['user_type'] =='HOD')) { ?><a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/course/AddCourse.php" style="text-align:center;font-weight: 900;font-size:15px;" class="text-primary page-link"><i class="fas fa-plus">&nbsp;&nbsp;ADD COURSE</a></i><?php }?>
       
        </div>
    </div>
</div>

<!-- end your code -->


<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>
<!--  end dont change the order-->