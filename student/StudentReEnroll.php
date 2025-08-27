<?php 
// Initialize database connection
include_once("../config.php");
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$title = "STUDENT COURSEREENROLL | SLGTI";
include_once("../head.php");
include_once("../menu.php");

// Initialize variables
$stid = $coid = $year = $enroll = $exit = $enstatus = $mode = '';

// Edit coding
if(isset($_GET['stid'], $_GET['coid'], $_GET['ayear'])) {
    $stid = mysqli_real_escape_string($con, $_GET['stid']);
    $coid = mysqli_real_escape_string($con, $_GET['coid']);
    $year = mysqli_real_escape_string($con, $_GET['ayear']);
    
    $sql = "SELECT `student_id`,`course_id`,`course_mode`,`student_enroll_date`,`student_enroll_exit_date`,`student_enroll_status`,`academic_year`
            FROM `student_enroll` 
            WHERE `student_id`='$stid' AND `course_id`='$coid' AND `academic_year`='$year'";
    
    $result = mysqli_query($con, $sql);
    if($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $stid = $row['student_id'];
        $coid = $row['course_id'];
        $mode = $row['course_mode'];
        $year = $row['academic_year'];
        $enstatus = $row['student_enroll_status'];
        $enroll = $row['student_enroll_date'];
        $exit = $row['student_enroll_exit_date'];
    } else {
        echo "<div class='alert alert-warning'>No enrollment record found.</div>";
    }
}

// Update coding
if(isset($_POST['Edit'])) {
    if(!empty($_POST['stid']) && !empty($_POST['coid']) && !empty($_POST['mode']) && 
       !empty($_POST['ayear']) && !empty($_POST['status']) && 
       !empty($_POST['edate']) && !empty($_POST['exdate'])) {
        
        $stid = mysqli_real_escape_string($con, $_POST['stid']);
        $coid = mysqli_real_escape_string($con, $_POST['coid']);
        $mode = mysqli_real_escape_string($con, $_POST['mode']);
        $year = mysqli_real_escape_string($con, $_POST['ayear']);
        $enstatus = mysqli_real_escape_string($con, $_POST['status']);
        $enroll = mysqli_real_escape_string($con, $_POST['edate']);
        $exit = mysqli_real_escape_string($con, $_POST['exdate']);

        $sql2 = "UPDATE `student_enroll` 
                SET `course_mode`='$mode',
                    `student_enroll_date`='$enroll',
                    `student_enroll_exit_date`='$exit',
                    `student_enroll_status`='$enstatus' 
                WHERE `student_id`='$stid' 
                AND `course_id`='$coid' 
                AND `academic_year`='$year'";

        if(mysqli_query($con, $sql2)) {
            echo "<div class='alert alert-success'>Record Updated Successfully</div>";
        } else {
            echo "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Please fill all required fields.</div>";
    }
}

// Insert coding
if(isset($_POST['Submit'])) {
    if(!empty($_POST['stid']) && !empty($_POST['coid']) && !empty($_POST['mode']) && 
       !empty($_POST['ayear']) && !empty($_POST['status']) && 
       !empty($_POST['edate']) && !empty($_POST['exdate'])) {
        
        $stid = mysqli_real_escape_string($con, $_POST['stid']);
        $coid = mysqli_real_escape_string($con, $_POST['coid']);
        $mode = mysqli_real_escape_string($con, $_POST['mode']);
        $year = mysqli_real_escape_string($con, $_POST['ayear']);
        $enstatus = mysqli_real_escape_string($con, $_POST['status']);
        $enroll = mysqli_real_escape_string($con, $_POST['edate']);
        $exit = mysqli_real_escape_string($con, $_POST['exdate']);

        $sqlenroll = "INSERT INTO `student_enroll`
                     (`student_id`, `course_id`, `course_mode`, `academic_year`, 
                      `student_enroll_date`, `student_enroll_exit_date`, `student_enroll_status`) 
                     VALUES 
                     ('$stid', '$coid', '$mode', '$year', '$enroll', '$exit', '$enstatus')";

        if(mysqli_query($con, $sqlenroll)) {
            echo "<div class='alert alert-success'>Record Inserted Successfully</div>";
        } else {
            echo "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Please fill all required fields.</div>";
    }
}

// Delete coding
if(isset($_GET['delete'])) {
    if(isset($_GET['stid'], $_GET['coid'])) {
        $stid = mysqli_real_escape_string($con, $_GET['stid']);
        $coid = mysqli_real_escape_string($con, $_GET['coid']);
        
        $sql = "DELETE FROM `student_enroll` 
                WHERE `student_id`='$stid' 
                AND `course_id`='$coid'";
                
        if(mysqli_query($con, $sql)) {
            echo "<div class='alert alert-success'>Record Deleted Successfully</div>";
        } else {
            echo "<div class='alert alert-danger'>Error deleting record: " . mysqli_error($con) . "</div>";
        }
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-12 text-center shadow p-5 mb-5 bg-white rounded">
            <h1>Students ReEnrollment Information</h1>
            <h2>SLGTI</h2>
        </div>
    </div>

    <form class="needs-validation" action="" method="POST" novalidate>
        <div class="form-row">
            <div class="col">
                <p style="font-size: 20px; font-weight: 700; border-bottom: 2px solid #aaa;">ENROLLMENT</p>
            </div>  
        </div>
        <br>

        <div class="form-row">
            <div class="col-md-5 mb-3">
                <label for="coid">Course Name:</label>
                <select name="coid" id="coid" class="custom-select" required>
                    <option value="" selected disabled>Select Course</option>
                    <?php 
                    $sql = "SELECT * FROM course";
                    $result = mysqli_query($con, $sql);
                    if($result && mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            $selected = ($row['course_id'] == $coid) ? 'selected' : '';
                            echo "<option value='{$row['course_id']}' $selected>{$row['course_name']}</option>";
                        }
                    }
                    ?>
                </select>
                <div class="invalid-feedback">Please select a course.</div>
            </div>
            
            <div class="col-md-1 mb-3"></div>

            <div class="col-md-5 mb-3">
                <label for="ayear">Academic Year:</label>
                <select name="ayear" id="ayear" class="custom-select" required>
                    <option value="" selected disabled>Select Academic Year</option>
                    <?php
                    $sql = "SELECT * FROM academic ORDER BY academic_year DESC";
                    $result = mysqli_query($con, $sql);
                    if($result && mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            $selected = ($row['academic_year'] == $year) ? 'selected' : '';
                            echo "<option value='{$row['academic_year']}' $selected>
                                    {$row['academic_year']} - {$row['academic_year_status']}
                                  </option>";
                        }
                    }
                    ?>
                </select>
                <div class="invalid-feedback">Please select an academic year.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-5 mb-3">
                <label for="mode">Course Mode:</label>
                <select name="mode" id="mode" class="custom-select" required>
                    <option value="" selected disabled>Select Course Mode</option>
                    <option value="Full" <?php if($mode == "Full") echo 'selected'; ?>>Full Time</option>
                    <option value="Part" <?php if($mode == "Part") echo 'selected'; ?>>Part Time</option>
                </select>
                <div class="invalid-feedback">Please select a course mode.</div>
            </div>
            
            <div class="col-md-1 mb-3"></div>

            <div class="col-md-5 mb-3">
                <label for="stid">Student Id:</label>
                <select name="stid" id="stid" class="custom-select" required>
                    <option value="" selected disabled>Select Student Id</option>
                    <?php
                    $sql = "SELECT * FROM student";
                    $result = mysqli_query($con, $sql);
                    if($result && mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            $selected = ($row['student_id'] == $stid) ? 'selected' : '';
                            echo "<option value='{$row['student_id']}' $selected>{$row['student_id']}</option>";
                        }
                    }
                    ?>
                </select>
                <div class="invalid-feedback">Please select a student id.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-5 mb-3">
                <label for="edate">ReEnroll Date:</label>
                <input type="date" class="form-control" id="edate" name="edate" value="<?php echo $enroll; ?>" required>
                <div class="invalid-feedback">Please enter a re-enroll date.</div>
            </div>
            
            <div class="col-md-1 mb-3"></div>

            <div class="col-md-5 mb-3">
                <label for="exdate">ReExit Date:</label>
                <input type="date" class="form-control" id="exdate" name="exdate" value="<?php echo $exit; ?>" required>
                <div class="invalid-feedback">Please enter a re-exit date.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-5 mb-3">
                <label for="status">Status:</label>
                <select name="status" id="status" class="custom-select" required>
                    <option value="" selected disabled>Select Status</option>
                    <option value="Following" <?php if($enstatus == "Following") echo 'selected'; ?>>Following</option>
                    <option value="Completed" <?php if($enstatus == "Completed") echo 'selected'; ?>>Completed</option>
                    <option value="Dropout" <?php if($enstatus == "Dropout") echo 'selected'; ?>>Dropout</option>
                    <option value="Long Absent" <?php if($enstatus == "Long Absent") echo 'selected'; ?>>Long Absent</option>
                </select>
                <div class="invalid-feedback">Please select a status.</div>
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-12 text-center">
                <?php
                if(isset($_GET['stid'], $_GET['coid'])) {
                    echo '<button type="submit" name="Edit" class="btn btn-primary">Update</button>';
                    echo '<button class="btn btn-sm btn-danger" value="delete" name="delete" data-toggle="modal" data-target="#confirm-delete">Delete</button>';
                } else {
                    echo '<button type="submit" name="Submit" class="btn btn-primary">Submit</button>';
                    echo '<button type="reset" value="Reset" class="btn btn-secondary">Reset</button>';
                }
                ?>
            </div>
        </div>
    </form>
</div>

<div class="container">
    <div class="row">
        <div class="col-12 text-center shadow p-5 mb-5 bg-white rounded">
            <h1>Enrollment Information</h1>
        </div>
    </div>

    <table class="table table-sm table-hover" id="table">
        <thead>
            <tr>
                <th scope="col">No</th>
                <th scope="col">Student Id</th>
                <th scope="col">Course Id</th>
                <th scope="col">Course Mode</th>
                <th scope="col">Academic Year</th>
                <th scope="col">ReEnroll Date</th>
                <th scope="col">ReExit Date</th>
                <th scope="col">Enroll Status</th>
                <th scope="col">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = "SELECT `student_id`, `course_id`, `course_mode`, `academic_year`, `student_enroll_date`, `student_enroll_exit_date`, `student_enroll_status` 
                    FROM `student_enroll`";
            $result = mysqli_query($con, $sql);
            if($result && mysqli_num_rows($result) > 0) {
                $num = 1;
                while($row = mysqli_fetch_assoc($result)) {
                    echo '<tr style="text-align:left;">
                            <td scope="row">'.$num.'</td>
                            <td>'.$row["student_id"].'</td>
                            <td>'.$row["course_id"].'</td>
                            <td>'.$row["course_mode"].'</td>
                            <td>'.$row["academic_year"].'</td>
                            <td>'.$row["student_enroll_date"].'</td>
                            <td>'.$row["student_enroll_exit_date"].'</td>
                            <td>'.$row["student_enroll_status"].'</td>
                            <td>
                                <a href="StudentReEnroll.php?stid='.$row["student_id"].'&&coid='.$row["course_id"].'&&ayear='.$row["academic_year"].'" class="btn btn-sm btn-success" title="Edit Re-Enrollment"><i class="far fa-edit"></i></a> |
                                <a href="ChangeEnrollment.php?stid='.$row["student_id"].'&&coid='.$row["course_id"].'&&ayear='.$row["academic_year"].'" class="btn btn-sm btn-warning" title="Change Course/Reg No"><i class="fas fa-exchange-alt"></i></a> |
                                <a href="Student_profile.php?Sid='.$row["student_id"].'" class="btn btn-info " title="View Profile"><i class="fas fa-angle-double-right"></i></a>
                            </td>
                        </tr>';
                    $num++;
                }
            } else {
                echo "<tr><td colspan='9'>No records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<?php
// Close database connection
mysqli_close($con);
include_once("../footer.php"); 
?>