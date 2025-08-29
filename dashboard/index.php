<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "Home | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!--END DON'T CHANGE THE ORDER-->



<?php
// echo $_SESSION['user_table'];
// echo $_SESSION['user_name'];
if($_SESSION['user_type']  == 'STU'){
   $u_type= $_SESSION['user_name'];
   $table = $_SESSION['user_table'];
    $sql ="SELECT course_id from student_enroll where  student_id= '$u_type'";

    $result = mysqli_query($con,$sql);
    if (mysqli_num_rows($result)>0) {
         while($row=mysqli_fetch_assoc($result)){
            $course=$row["course_id"];
            $sql1="SELECT module_id from module where course_id='$course'";
            $result4 = mysqli_query($con,$sql1);
            if (mysqli_num_rows($result4)>0) {
                 while($row=mysqli_fetch_assoc($result4)){
                      $module=$row["module_id"];
                     $sql2="SELECT survey_id FROM feedback_survey where  course_id='$course' and module_id='$module' and end_date > curdate() and start_date <= curdate()";
                     $result5 = mysqli_query($con,$sql2);
                     if (mysqli_num_rows($result5)>0) {
                          while($row=mysqli_fetch_assoc($result5)){
                           $survey_id=$row["survey_id"];
                           $sql3="SELECT survey_id,student_id FROM feedback_done where survey_id='$survey_id' and student_id='$u_type'";
                           $result6 = mysqli_query($con,$sql3);
                           if (mysqli_num_rows($result6)==0) {

                           echo '
                           <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong > New Notification! <span style="font-size:20px;">&#129335;</span> </strong> New Survey Added For <strong> '.$module.' </strong>&nbsp;Pleace Give Your Feedback &nbsp;
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                                </button>
                            <a href="Addfbdetail.php?id='. $row["survey_id"].'" class="btn btn-sm btn-warning float-right mr-5"><i class="fas fa-eye"></i></a> 
                                </div>
                           ';
                     
                          }}
                        }else{
                           
                        }
                     
                 }
                }else{
                   
                }

    }}else{
       
    }
}else{
   // echo 'your not a student'.$_SESSION['user_type'];
}

?>

<!--BLOCK#2 START YOUR CODE HERE -->
<?php
// Determine if current user is a student
$isStudent = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU');
?>

<?php if ($isStudent): ?>
<?php
    // Load the logged-in student's core profile data for personalized dashboard
    $username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
    $p_title = $p_fname = $p_ininame = $p_nic = $p_depth = $p_course = $p_level = $p_batch = $p_exit = null;
    if ($username) {
        $sql = "SELECT u.user_name, e.course_id, s.student_title, s.student_fullname, s.student_ininame, s.student_nic,
                       d.department_name, c.course_name, c.course_nvq_level, e.academic_year, e.student_enroll_exit_date
                  FROM student s
                  JOIN student_enroll e ON s.student_id = e.student_id
                  JOIN user u ON u.user_name = s.student_id
                  JOIN course c ON c.course_id = e.course_id
                  JOIN department d ON d.department_id = c.department_id
                 WHERE e.student_enroll_status = 'Following' AND u.user_name = '" . mysqli_real_escape_string($con, $username) . "'";
        $result = mysqli_query($con, $sql);
        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_assoc($result);
            $p_title  = $row['student_title'];
            $p_fname  = $row['student_fullname'];
            $p_ininame= $row['student_ininame'];
            $p_nic    = $row['student_nic'];
            $p_depth  = $row['department_name'];
            $p_course = $row['course_name'];
            $p_level  = $row['course_nvq_level'];
            $p_batch  = $row['academic_year'];
            $p_exit   = $row['student_enroll_exit_date'];
        }
    }
?>

<div class="row mt-3">
  <div class="col-md-4 col-sm-12">
    <div class="card mb-3 text-center">
      <div class="card-body">
        <img src="/MIS/student/get_student_image.php?Sid=<?php echo urlencode($username); ?>&t=<?php echo time(); ?>" alt="user image" class="img-thumbnail mb-3" style="width:160px;height:160px;object-fit:cover;">
        <h5 class="card-title mb-1"><?php echo htmlspecialchars(($p_title ? $p_title.'. ' : '').$p_fname); ?></h5>
        <div class="text-muted">ID: <?php echo htmlspecialchars($username); ?></div>
        <?php if ($p_nic): ?><div class="text-muted">NIC: <?php echo htmlspecialchars($p_nic); ?></div><?php endif; ?>
        <div class="mt-3">
          <a href="/MIS/student/Student_profile.php" class="btn btn-primary btn-sm">View Full Profile</a>
          <a href="/MIS/student/Student_profile.php#nav-modules" class="btn btn-outline-secondary btn-sm">My Modules</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8 col-sm-12">
    <div class="card mb-3">
      <div class="card-body">
        <h6 class="card-header font-weight-lighter mb-3 bg-white px-0">My Academic Summary</h6>
        <div class="row">
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">Department</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_depth ?: '—'); ?></div>
          </div>
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">Course</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_course ?: '—'); ?></div>
          </div>
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">NVQ Level</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_level !== null ? ('Level - '.$p_level) : '—'); ?></div>
          </div>
          <div class="col-md-6 mb-2">
            <div class="small text-uppercase text-muted">Batch</div>
            <div class="h6 mb-0"><?php echo htmlspecialchars($p_batch ?: '—'); ?><?php echo $p_exit ? ' ('.$p_exit.')' : ''; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="alert alert-info">
      This dashboard is personalized for students. Use the sidebar to access Attendance, Assessments, Notices, and more.
    </div>
  </div>
</div>

<?php else: ?>

<?php
$total_course = 0;
$total_students = 0;
?>

<div class="row mt-3">
    <div class="col-md-2 col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Departments</h5>
                <p class="card-text display-2 ">
                    <?php          
                    $sql = "SELECT COUNT(`department_id`) AS `d_count` FROM `department`";
                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                        echo $row['d_count'];
                    }
                ?>
                </p>
                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Courses</h5>
                <p class="card-text display-2 ">
                    <?php          
                    $sql = "SELECT COUNT(`course_id`) AS `d_count` FROM `course`";
                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                        echo $row['d_count'];
                        $total_course = $row['d_count'];
                    }
                ?>
                </p>
                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Modules</h5>
                <p class="card-text display-2 ">
                    <?php          
                    $sql = "SELECT COUNT(`module_id`) AS `d_count` FROM `module`";
                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                        echo $row['d_count'];
                    }
                ?>
                </p>
                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Academic Years</h5>
                <p class="card-text display-2 ">
                    <?php          
                    $sql = "SELECT COUNT(`academic_year`) AS `d_count` FROM `academic`";
                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                        echo $row['d_count'];
                    }
                ?>
                </p>
                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
            </div>
        </div>
    </div>

    <div class="col-md-2 col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Staff</h5>
                <p class="card-text display-2 ">
                    <?php          
                    $sql = "SELECT COUNT(`staff_id`) AS `d_count` FROM `staff`";
                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                        echo $row['d_count'];
                    }
                ?>
                </p>
                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Students</h5>
                <p class="card-text display-2 font-weight-lighter">
                    <?php          
                    $sql = "SELECT COUNT(`student_id`) AS `d_count` FROM `student`";
                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                        echo $row['d_count'];
                        $total_students = $row['d_count'];
                    }
                ?>
                </p>
                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
            </div>
        </div>
    </div>
</div>
<hr>




<div class="row">
<div class="col-md-2">
    Academic Year is : 
                </div>
    <div class="col-md-3">
        <select class="mb-2 selectpicker show-tick custom-select-sm" required onchange="showStudent(this.value)" data-live-search="true" data-width="100%">
            <option value="ALL" selected>ALL</option>
            <?php
            $sql = "SELECT * FROM `academic` ORDER BY `academic_year`  DESC ";
            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)){
            echo '<option  value="'.$row ['academic_year'].'" data-subtext="'.$row ['academic_year_status'].'">'.$row ['academic_year'].'</option>';
            }
            }
            ?>
        </select>
    </div>
</div>
<div class="text-center loading">
  <div class="spinner-border text-primary" role="completed">
    <span class="sr-only">Loading...</span>
  </div>
</div>
<div class="row m-2">
    <div class="col-md-12">
        <canvas id="myChart1"></canvas>
    </div>
</div>
<hr>






<div class="row mt-4">
    <div class="col-12">
        <?php include __DIR__ . '/gender_pie.php'; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-4 col-sm-12">
        <!-- <button type="button" class="btn btn-primary btn-sm btn-block mb-2">Small button</button> -->

        <div class="card overflow-auto mh-20">
            <h6 class="card-header font-weight-lighter">Students Course Enrollment Distribution</h6>
            <div class="card-body">
                <?php
$sql = "SELECT * FROM `course` ORDER BY `course_name` ASC ";
$result = mysqli_query($con, $sql);
if (mysqli_num_rows($result) > 0) {
while($row = mysqli_fetch_assoc($result)){

    $cid = $row['course_id'];
    $cname = $row['course_name'];
    $sql_c = "SELECT COUNT(`student_id`) AS `c_count` FROM `student_enroll` WHERE `course_id` = '$cid' ";
    $result_c = mysqli_query($con, $sql_c);
    $row_c = mysqli_fetch_assoc($result_c);
    $course_count =  $row_c['c_count'];
    $student_percentage = 0;
    $student_percentage = round ( ($course_count/$total_students)*100); 
    // echo $total_students;
    echo '
    <h6 class="card-title font-weight-lighter"><small>'.$cname.'</small></h6>
    <p class="card-text">
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: '.$student_percentage.'%;" aria-valuenow="'.$student_percentage.'"
                aria-valuemin="0" aria-valuemax="100">'.$student_percentage.'%</div>
        </div>
    </p>
    ';
}
}
?>
            </div>
        </div>

    </div>

    <!-- COL-1 END -->

    <div class="col-md-4 col-sm-12">
        <div class="card">
            <h6 class="card-header font-weight-lighter">Students Course Dropout Distribution </h6>
            <div class="card-body">
                <?php
$sql = "SELECT * FROM `course` ORDER BY `course_name` ASC ";
$result = mysqli_query($con, $sql);
if (mysqli_num_rows($result) > 0) {
while($row = mysqli_fetch_assoc($result)){

    $cid = $row['course_id'];
    $cname = $row['course_name'];
    $sql_c = "SELECT COUNT(`student_id`) AS `c_count` FROM `student_enroll` WHERE `course_id` = '$cid' AND `student_enroll_status` = 'Dropout' ";
    $result_c = mysqli_query($con, $sql_c);
    $row_c = mysqli_fetch_assoc($result_c);
    $course_count =  $row_c['c_count'];
    $student_percentage = 0;
    $student_percentage = round ( ($course_count/$total_students)*100); 
    // echo $total_students;
    echo '
    <h6 class="card-title font-weight-lighter"><small>'.$cname.'</small></h6>
    <p class="card-text">
        <div class="progress">
            <div class="progress-bar progress-bar-striped bg-danger progress-bar-animated" role="progressbar" style="width: '.$student_percentage.'%;" aria-valuenow="'.$student_percentage.'"
                aria-valuemin="0" aria-valuemax="100">'.$student_percentage.'%</div>
        </div>
    </p>
    ';
}
}
?>
            </div>
        </div>
    </div>
    <!-- <col2-end -->
        <div class="col-md-4 col-sm-12">
        <div class="card">
            <h6 class="card-header font-weight-lighter">Students Course Following Distribution </h6>
            <div class="card-body">
                <?php
$sql = "SELECT * FROM `course` ORDER BY `course_name` ASC ";
$result = mysqli_query($con, $sql);
if (mysqli_num_rows($result) > 0) {
while($row = mysqli_fetch_assoc($result)){

    $cid = $row['course_id'];
    $cname = $row['course_name'];
    $sql_c = "SELECT COUNT(`student_id`) AS `c_count` FROM `student_enroll` WHERE `course_id` = '$cid' AND `student_enroll_status` = 'Following' ";
    $result_c = mysqli_query($con, $sql_c);
    $row_c = mysqli_fetch_assoc($result_c);
    $course_count =  $row_c['c_count'];
    $student_percentage = 0;
    $student_percentage = round ( ($course_count/$total_students)*100); 
    // echo $total_students;
    echo '
    <h6 class="card-title font-weight-lighter"><small>'.$cname.'</small></h6>
    <p class="card-text">
        <div class="progress">
            <div class="progress-bar progress-bar-striped bg-danger progress-bar-animated" role="progressbar" style="width: '.$student_percentage.'%;" aria-valuenow="'.$student_percentage.'"
                aria-valuemin="0" aria-valuemax="100">'.$student_percentage.'%</div>
        </div>
    </p>
    ';
}
}
?>
            </div>
        </div>
    </div>
    <!-- <col2-end -->
    <div class="col-md-4 col-sm-12">
        <div class="card">
            <h6 class="card-header font-weight-lighter">Students Course Completion Distribution</h6>
            <div class="card-body">
                <?php
$sql = "SELECT * FROM `course` ORDER BY `course_name` ASC ";
$result = mysqli_query($con, $sql);
if (mysqli_num_rows($result) > 0) {
while($row = mysqli_fetch_assoc($result)){

    $cid = $row['course_id'];
    $cname = $row['course_name'];
    $sql_c = "SELECT COUNT(`student_id`) AS `c_count` FROM `student_enroll` WHERE `course_id` = '$cid' AND `student_enroll_status` = 'Completed'";
    $result_c = mysqli_query($con, $sql_c);
    $row_c = mysqli_fetch_assoc($result_c);
    $course_count =  $row_c['c_count'];
    $student_percentage = 0;
    $student_percentage = round ( ($course_count/$total_students)*100); 
    // echo $total_students;
    echo '
    <h6 class="card-title font-weight-lighter"><small>'.$cname.'</small></h6>
    <p class="card-text">
        <div class="progress">
            <div class="progress-bar progress-bar-striped bg-success progress-bar-animated" role="progressbar" style="width: '.$student_percentage.'%;" aria-valuenow="'.$student_percentage.'"
                aria-valuemin="0" aria-valuemax="100">'.$student_percentage.'%</div>
        </div>
    </p>
    ';
}
}
?>
            </div>
        </div>
    </div>
    <!-- COL-3 END -->

    <div class="col-md-4 col-sm-12">
        <div class="card">
            <h6 class="card-header font-weight-lighter">Students Course Dropout Distribution </h6>
            <div class="card-body">
                <?php
$sql = "SELECT * FROM `course` ORDER BY `course_name` ASC ";
$result = mysqli_query($con, $sql);
if (mysqli_num_rows($result) > 0) {
while($row = mysqli_fetch_assoc($result)){

    $cid = $row['course_id'];
    $cname = $row['course_name'];
    $sql_c = "SELECT COUNT(`student_id`) AS `c_count` FROM `student_enroll` WHERE `course_id` = '$cid' AND `student_enroll_status` = 'Dropout' ";
    $result_c = mysqli_query($con, $sql_c);
    $row_c = mysqli_fetch_assoc($result_c);
    $course_count =  $row_c['c_count'];
    $student_percentage = 0;
    $student_percentage = round ( ($course_count/$total_students)*100); 
    // echo $total_students;
    echo '
    <h6 class="card-title font-weight-lighter"><small>'.$cname.'</small></h6>
    <p class="card-text">
        <div class="progress">
            <div class="progress-bar progress-bar-striped bg-danger progress-bar-animated" role="progressbar" style="width: '.$student_percentage.'%;" aria-valuenow="'.$student_percentage.'"
                aria-valuemin="0" aria-valuemax="100">'.$student_percentage.'%</div>
        </div>
    </p>
    ';
}
}
?>
            </div>
        </div>
    </div>
    <!-- <col2-end -->
</div>
<hr>




<!-- 
<div class="row m-2">
    <div class="col-md-12  ">
        <canvas id="myChart"></canvas>
    </div>
</div> -->


<!-- 
<script>
function showCouese(val) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Course").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getCourse", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("department=" + val);
}

function showModule(val) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Module").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getModule", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("course=" + val);
}

function showTeacher() {
    var did = document.getElementById("Departmentx").value;
    var cid = document.getElementById("Course").value;
    var mid = document.getElementById("Module").value;
    var aid = null;
    var tid = null;

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Teacher").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getTeacher", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("StaffModuleEnrollment=1&staff_id=" + tid + "&course_id=" + cid + "&module_id=" + mid +
        "&academic_year=" + aid);
}
</script>

 -->


<script>
showStudent('ALL');

function showStudent(val) {
    var course_id_label = [];
    var course_total_count = [];
    var course_completed_count = [];
    var course_droupout_count = [];
    var course_following_count = [];

    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var data_students_count = JSON.parse(this.responseText);
            for (var i in data_students_count) {
                course_id_label.push(data_students_count[i].course_id);
                course_total_count.push(data_students_count[i].t_count);
                course_completed_count.push(data_students_count[i].c_count);
                course_droupout_count.push(data_students_count[i].d_count);
                course_following_count.push(data_students_count[i].d_count);
            }
            
            var ctx = document.getElementById('myChart1');
            var myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: course_id_label,
                    datasets: [{
                        label: "Total Students ",
                        backgroundColor: "#5a407d",
                        data: course_total_count
                    }, {
                        label: "Dropout Students ",
                        backgroundColor: "#dc3545",
                        data: course_droupout_count
                    }, {
                        label: "Completed Students ",
                        backgroundColor: "#28a745",
                        data: course_completed_count
                    },{
                        label: "Following Students ",
                        backgroundColor: "#007bff",
                        data: course_droupout_count
                    },{
                        label: "LongAbsent Students",
                        backgroundColor: "#deb647",
                        data: course_droupout_count
                    }]
                },
                options: {
                    title: {
                        display: true,
                        text: 'Course vs Students Enrollments Distribution'
                    },
                    legend: {
                        display: true,
                        labels: {
                            fontColor: 'rgb(0, 0, 0)'
                        }
                    }
                }
            });

            document.getElementsByClassName('loading')[0].style.visibility = 'hidden';
        }
    };
    xmlhttp.open("POST", "controller/StudentsCourseDistribution", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("AcademicYear=" + val);
}
</script>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->

<?php endif; ?>