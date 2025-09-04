<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "Timetable | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
$today = date('Y-m-d');
?>
<!--END DON'T CHANGE THE ORDER-->


<?php
$department_id=$course_id=$module_id=$academic_year=$staff_id=$weekdays=$timep=$classroom=$start_date=$end_date=$tid=null;
// Determine if current user is a student and fetch their current course for scoping
$isSTU = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU';
$studentCourseId = null;
if ($isSTU && isset($_SESSION['user_name'])) {
    $sid = mysqli_real_escape_string($con, $_SESSION['user_name']);
    // Try to get most recent enrollment (optionally could join academic for Active year)
    $q = "SELECT course_id FROM student_enroll WHERE student_id = '$sid' ORDER BY academic_year DESC LIMIT 1";
    $r = mysqli_query($con, $q);
    if ($r && mysqli_num_rows($r) > 0) {
        $row = mysqli_fetch_assoc($r);
        $studentCourseId = $row['course_id'];
    }
}

// Optional Group filter: if provided, use group's course_id and academic_year to scope timetable
$selectedGroup = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$groupInfo = null; $groupCourseId = null; $groupAcademicYear = null;
if ($selectedGroup > 0) {
    $stg = mysqli_prepare($con, 'SELECT id, name, course_id, academic_year FROM `groups` WHERE id = ?');
    if ($stg) {
        mysqli_stmt_bind_param($stg, 'i', $selectedGroup);
        mysqli_stmt_execute($stg);
        $rg = mysqli_stmt_get_result($stg);
        if ($rg && ($g = mysqli_fetch_assoc($rg))) { $groupInfo = $g; $groupCourseId = $g['course_id']; $groupAcademicYear = $g['academic_year']; }
        mysqli_stmt_close($stg);
    }
}
?>
<div class="row">
    <div class="col-md-12 col-sm-12">
        <h3 class="text-center">Timetable<?php if($groupInfo){ echo ' — Group: ' . htmlspecialchars($groupInfo['name']) . ' (' . htmlspecialchars($groupInfo['course_id']). ', ' . htmlspecialchars($groupInfo['academic_year']). ')'; } ?></h3>
    </div>
</div>

<?php if (!$isSTU) { ?>
<form method="GET">

    <div class="form-row pb-4">
        <div class="col-md-3 col-sm-12">
            <div class="form-row align-items-center">
                <select class="selectpicker mr-sm-2" id="GroupSelect" name="group_id" data-live-search="true" data-width="100%">
                    <option value="0" <?php echo ($selectedGroup===0?'selected':'');?>>-- Select a Group (optional) --</option>
                    <?php
                    $sql = "SELECT id, name, course_id, academic_year FROM `groups` ORDER BY created_at DESC";
                    $result = mysqli_query($con, $sql);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)) {
                            echo '<option value="'.(int)$row['id'].'"';
                            if ($selectedGroup === (int)$row['id']) echo ' selected';
                            echo '>'.htmlspecialchars($row['name'].' — '.$row['course_id'].' — '.$row['academic_year']).'</option>';
                        }
                    } else {
                        echo '<option value="0" disabled>-- No Groups --</option>';
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="col-3">
            <div class="form-row align-items-center">
                <select class="selectpicker mr-sm-2" id="TeacherName" name="staff_id" data-live-search="true"
                    data-width="100%">
                    <option value="null" selected disabled>-- Select a Teacher --</option>
                    <?php
          $sql = "SELECT * FROM `staff`";
          $result = mysqli_query($con, $sql);
          if (mysqli_num_rows($result) > 0) {
          while($row = mysqli_fetch_assoc($result)) {
            echo '<option  value="'.$row["staff_id"].'"'; 
            if($_SESSION['user_name']==$row["staff_id"]) echo 'selected ';
            echo 'required>('.$row["staff_epf"].') '.$row["staff_name"].'</option>';
          }
          }else{
            echo '<option value="null"   selected disabled>-- No Teacher --</option>';
          }
          ?>
                </select>
            </div>
        </div>

        <div class="col-md-3 col-sm-12">
            <div class="form-row align-items-center">
                <select class="selectpicker mr-sm-2" id="Course" onchange="showModule(this.value)" name="course_id"
                    data-live-search="true" data-width="100%">
                    <option value="null" selected disabled>-- Select a Course --</option>
                    <?php
          $sql = "SELECT * FROM `course`";
          $result = mysqli_query($con, $sql);
          if (mysqli_num_rows($result) > 0) {
          while($row = mysqli_fetch_assoc($result)) {
            echo '<option  value="'.$row["course_id"].'" required>(' . $row["course_id"].') '.$row["course_name"].'</option>';
          }
          }else{
            echo '<option value="null"   selected disabled>-- No Teacher --</option>';
          }
          ?>
                </select>
            </div>
        </div>

        <div class="col-md-3 col-sm-12">
            <div class="form-row align-items-center">
                <select class="custom-select mr-sm-2" id="Module" name="module_id">
                    <option value="null" selected disabled>-- Select a Course --</option>
                </select>
            </div>
        </div>

        <div class="col-md-2 col-sm-12">
            <div class="form-row align-items-center">
                <select class="selectpicker mr-sm-2" id="academic_year" name="academic_year" data-live-search="true"
                    data-width="100%">
                    <option value="null" selected disabled>-- Select a Academic Year --</option>
                    <?php
          $sql = "SELECT * FROM `academic` ORDER BY `academic_year` DESC";
          $result = mysqli_query($con, $sql);
          if (mysqli_num_rows($result) > 0) {
          while($row = mysqli_fetch_assoc($result)) {
            echo '<option  value="'.$row["academic_year"].'"'; 
            if($row["academic_year_status"]=='Active') echo ' selected ';
            echo 'required>'.$row["academic_year"].'</option>';
          }
          }else{
            echo '<option value="null"   selected disabled>-- No Teacher --</option>';
          }
          ?>
                </select>
            </div>
        </div>

        <div class="col-md-1 col-sm-12">
            <div class="form-row align-items-center">
                <button type="button" class="btn btn-primary btn-block ml-2" onclick="showTimetable()"><i
                        class="fas fa-search"></i></button>
            </div>
        </div>
    </div>
</form>
<?php } ?>


<?php

?>
<div class="row">
    <div class="col-md-12 col-sm-12">
        <div class="table-responsive">
        <table id="dtHorizontalVerticalExample" >
                <thead>
                    <tr>
                        <th scope="col" class="p-3 bg-info text-light" style="width: 8%;">Date : <?php echo $today; ?>
                        
                        </th>
                        <?php
                         $weeks = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");

                        foreach ($weeks as $value) {



                   
                           
                          ?>
                         <th scope="col" class="p-3 <?php if(date('l')==$value) echo ' bg-warning'; ?>" style="width: 6%;"> 
                         <?php echo $value; ?>




                        </th>
                    
                        <?php
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="align-middle" scope="row" style="height: 50px;">08:30 AM - 10.00 AM</th>
                        <?php
       
        foreach ($weeks as $value) {
            echo '<td class="align-middle ">';
            $sql = "SELECT * FROM `timetable` WHERE `weekdays` = '".mysqli_real_escape_string($con,$value)."' AND `timep` = 'P1' AND `end_date` >= '".mysqli_real_escape_string($con,$today)."'";
            if ($selectedGroup > 0 && $groupCourseId && $groupAcademicYear) {
                $escC = mysqli_real_escape_string($con, $groupCourseId);
                $escY = mysqli_real_escape_string($con, $groupAcademicYear);
                $sql .= " AND `course_id` = '".$escC."' AND `academic_year` = '".$escY."'";
            } elseif ($isSTU && $studentCourseId) {
                $esc = mysqli_real_escape_string($con, $studentCourseId);
                $sql .= " AND `course_id` = '".$esc."'";
            }
            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) 

              
                echo '<p class="text-center alert-info border border-info p-2 rounded">'. $row['course_id'].'-'.$row['module_id'] . '
                 <span class="badge badge-dark"> '. $row['classroom'].'</span> <span class="badge badge-info"> '.$row['staff_id'] . ' </span>';
                if (!$isSTU) { echo ' <a href="AddTimetable.php?edit='.$row["time_id"].'" class=" btn-outline-light"><i class="far fa-edit"></i>'; }
                echo '<p>';
            }
            echo '</td>';   
        }
    ?>

                    </tr>
                    <tr class="table-secondary">
                        <th sclass="align-middle p-3" scope="row">10.00 AM - 10.30 AM</th>
                        <td class="align-middle text-center p-3" colspan="7">Tea Break</td>
                    </tr>
                    <tr>
                        <th class="align-middle" scope="row" style="height: 50px;">10:30 AM - 12.00 AM</th>
                        <?php
        foreach ($weeks as $value) {
            echo '<td class="align-middle ">';
            $sql = "SELECT * FROM `timetable` WHERE `weekdays` = '".mysqli_real_escape_string($con,$value)."' AND `timep` = 'P2' AND `end_date` >= '".mysqli_real_escape_string($con,$today)."'";
            if ($selectedGroup > 0 && $groupCourseId && $groupAcademicYear) {
                $escC = mysqli_real_escape_string($con, $groupCourseId);
                $escY = mysqli_real_escape_string($con, $groupAcademicYear);
                $sql .= " AND `course_id` = '".$escC."' AND `academic_year` = '".$escY."'";
            } elseif ($isSTU && $studentCourseId) { $esc = mysqli_real_escape_string($con, $studentCourseId); $sql .= " AND `course_id` = '".$esc."'"; }
            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) 
                echo '<p class="text-center alert-info border border-info p-2 rounded">'. $row['course_id'].'-'.$row['module_id'] . '
                 <span class="badge badge-dark"> '. $row['classroom'].'</span> <span class="badge badge-info"> '.$row['staff_id'] . ' </span>';
                if (!$isSTU) { echo ' <a href="AddTimetable.php?edit='.$row["time_id"].'" class=" btn-outline-light"><i class="far fa-edit"></i>'; }
                echo '';
            }
            echo '</td>';   
        }
    ?>

                    </tr>
                    <tr class="table-secondary">
                        <th sclass="align-middle p-3" scope="row">12.00 AM - 01.00 PM</th>
                        <td class="align-middle text-center p-3" colspan="7">Lunch Break</td>
                    </tr>
                    <tr>
                        <th class="align-middle" scope="row" style="height: 50px;">01.00 PM - 02.30 PM</th>
                        <?php
        foreach ($weeks as $value) {
            echo '<td class="align-middle ">';
            $sql = "SELECT * FROM `timetable` WHERE `weekdays` = '".mysqli_real_escape_string($con,$value)."' AND `timep` = 'P3' AND `end_date` >= '".mysqli_real_escape_string($con,$today)."'";
            if ($selectedGroup > 0 && $groupCourseId && $groupAcademicYear) {
                $escC = mysqli_real_escape_string($con, $groupCourseId);
                $escY = mysqli_real_escape_string($con, $groupAcademicYear);
                $sql .= " AND `course_id` = '".$escC."' AND `academic_year` = '".$escY."'";
            } elseif ($isSTU && $studentCourseId) { $esc = mysqli_real_escape_string($con, $studentCourseId); $sql .= " AND `course_id` = '".$esc."'"; }
            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) 
                echo '<p class="text-center alert-info border border-info p-2 rounded">'. $row['course_id'].'-'.$row['module_id'] . '
                 <span class="badge badge-dark"> '. $row['classroom'].'</span> <span class="badge badge-info"> '.$row['staff_id'] . ' </span>';
                if (!$isSTU) { echo ' <a href="AddTimetable.php?edit='.$row["time_id"].'" class=" btn-outline-light"><i class="far fa-edit"></i>'; }
                echo ' <p>';
            }
            echo '</td>';   
        }
    ?>

                    </tr>
                    <tr class="table-secondary">
                        <th sclass="align-middle p-3" scope="row">02.30 PM - 02.45 PM</th>
                        <td class="align-middle text-center p-3" colspan="7">Tea Break</td>
                    </tr>
                    <tr>
                        <th class="align-middle" scope="row" style="height: 50px;">02.45 PM - 04.15 PM</th>
                        <?php
        foreach ($weeks as $value) {
            echo '<td class="align-middle ">';
            $sql = "SELECT * FROM `timetable` WHERE `weekdays` = '".mysqli_real_escape_string($con,$value)."' AND `timep` = 'P4' AND `end_date` >= '".mysqli_real_escape_string($con,$today)."'";
            if ($selectedGroup > 0 && $groupCourseId && $groupAcademicYear) {
                $escC = mysqli_real_escape_string($con, $groupCourseId);
                $escY = mysqli_real_escape_string($con, $groupAcademicYear);
                $sql .= " AND `course_id` = '".$escC."' AND `academic_year` = '".$escY."'";
            } elseif ($isSTU && $studentCourseId) { $esc = mysqli_real_escape_string($con, $studentCourseId); $sql .= " AND `course_id` = '".$esc."'"; }
            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) 
                echo '<p class="text-center alert-info border border-info p-2 rounded">'. $row['course_id'].'-'.$row['module_id'] . '
                 <span class="badge badge-dark"> '. $row['classroom'].'</span> <span class="badge badge-info"> '.$row['staff_id'] . ' </span>';
                if (!$isSTU) { echo ' <a href="AddTimetable.php?edit='.$row["time_id"].'" class=" btn-outline-light"><i class="far fa-edit"></i>'; }
                echo '  <br>  <p>';
            
            }
            echo '</td>';   
        }
    ?>

                    </tr>
                    

                </tbody>
            </table>
    
        </div>
    </div>
</div>

<?php
                    if(isset($_GET['delete_id']))
                    {                
                        $timep = $_GET['delete_id'];

                        $sql = "DELETE from timetable where timep = '$timep' ";

                        if(mysqli_query($con,$sql))
                        {
                          echo '
                      
                          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>  ';
                        }
                        else
                        {
                          echo '
                          <div class="alert alert-danger alert-dismissible fade show" role="alert">
                          <strong> Error </strong> Cannot delete or update a parent row (foreign key constraint fails)
                          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>  ';               
                         
                        }
                    }
                    ?>
   
<?php if (!$isSTU) { ?>
<div class="text-right">
<a href="AddTimetable.php" class="btn btn-primary ">Add</a> 


         
</div>
<?php } ?>


<html>
<head>
<style>
.pagination {
  display: inline-block;
}

.pagination a {
  color: black;
  float: left;
  padding: 8px 16px;
  text-decoration: none;
  transition: background-color .3s;
}

.pagination a.active {
  background-color: #4CAF50;
  color: white;
}

.pagination a:hover:not(.active) {background-color: #ddd;}
</style>
</head>
<body>

<div class="pagination">
  <a href="#">❮</a>
  <a href="#">❯</a>
</div>


</body>



<script>



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
</script>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->