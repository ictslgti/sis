<!--Block#1 start dont change the order-->
<?php 
$title="Module details | SLGTI";    
include_once ("../config.php");
include_once ("../head.php");
include_once ("../menu.php");
?>
<!-- end dont change the order-->


<!-- Block#2 start your code -->
<?php
$gcourse_id=$gcourse_i=$sum=$mid=$cid=null;
?>
	<div class="shadow  p-3 mb-1 bg-white rounded">
	    <div class="highlight-blue">
	        <h1 class="display-4 text-center">Module Details</h1>
	    </div>
	</div>
<br>
<form method="GET">
  <div class="form-row">
        <div class="col-5">
            <div class="form-row align-items-center">
                <select class="selectpicker mr-sm-2" id="search"  name="course_id" data-live-search="true" data-width="100%">
                    <option value="null" selected disabled>-- Select a Course --</option>
                    <?php
                    // Course list, scope to HOD's department if applicable
                    $sql = "SELECT * FROM `course` WHERE 1=1";
                    if (isset($_SESSION['user_type']) && $_SESSION['user_type']==='HOD' && !empty($_SESSION['department_code'])) {
                      $dc = mysqli_real_escape_string($con, $_SESSION['department_code']);
                      $sql .= " AND department_id='".$dc."'";
                    }
                    $sql .= " ORDER BY `course_id` ASC";

                    $result = mysqli_query($con, $sql);
                    if (mysqli_num_rows($result) > 0){
                    while($row = mysqli_fetch_assoc($result)){
                      echo '<option  value="'.$row["course_id"].'" required>'.$row["course_name"].'</option>';
                    }
                    }else{
                      echo '<option value="null"  selected disabled>-- 0 Position --</option>';
                    }
                    ?>
                 </select>
            </div>
          </div>
        <div class="col-1">
          <div class="form-row align-items-center" style="float:left">
            <button  type="submit" class="btn btn-primary btn-block" name="search" ><i class="fas fa-search"></i></button>
          </div>
        </div>
        <div class="col-6">
          <div class="form-row align-items-center" style="float:right;">
          <a href="Module.php" style="text-align:center;font-weight: 650;font-size:15px;background-color:#e8e3e3;" class="text-primary page-link">VIEW ALL MODULES</a>
          </div>
        </div>
  </div>
</form>
  <div class="row">
	    <div class="col-md-12 col-sm-12">
              <div class="table-responsive table-responsive-sm">
                  <table class="table table-hover">
                  <thead class="thead-dark">
                    <tr style="text-align:center">
                    <th scope="col">NO.</th>
                    <th scope="col">Module ID</th>
                    <th scope="col">Module Name</th>
                    <th scope="col">Course Name</th>
                    <th scope="col">Semester ID </th>
                    <th scope="col">Notional Hours</th>
                    <th scope="col">Options</th>
                    </tr>
                  </thead>
                    <?php
                    if((isset($_GET['dlt']))&&(isset($_GET['dllt'])))
                    {
                    $m_id = $_GET['dlt'];
                    $cid = $_GET['dllt'];

                    $sql = "DELETE from module where module_id ='$m_id' AND course_id ='$cid'";
                    if(mysqli_query($con,$sql)){
                    echo '
                    <div class="alert alert-sucess alert-dismissible fade show" role="alert">
                    <strong>'.$m_id.' </strong> Record has been Deleted Succesfully 
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                    </div>  ';
                    }else{
                    echo '
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong> '.$m_id.' </strong> Is Used In Another Table
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                    </div>  ';}}?>
                    <br>
                  <tbody>
                    <?php
                    
                    function getTotal($cid,$mid){
                    $con=mysqli_connect(DB_HOST,DB_USER,DB_PASS,DB_NAME);
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
                    return $x;}
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
                     if (isset($_SESSION['user_type']) && $_SESSION['user_type']==='HOD' && !empty($_SESSION['department_code'])) {
                       $dc = mysqli_real_escape_string($con, $_SESSION['department_code']);
                       $sql .= " AND course.department_id='".$dc."'";
                     }
                     if(isset($_GET['course_id'])){
                      $gcourse_id = mysqli_real_escape_string($con, $_GET['course_id']);
                      $sql .= " AND `module`.`course_id`= '".$gcourse_id."'";
                    }

                     $result = mysqli_query($con,$sql);
                     if(mysqli_num_rows($result)>0){
                     $count=1;
                     while($row = mysqli_fetch_assoc($result)){ 
                     $mid = $row["module_id"];
                     $cid = $row["course_id"];
                     echo'
                     <tr style="text-align:center">
                     <td>'.$count.'.'. "<br>" .' </td>
                     <td>'. $row["module_id"] . "<br>" .' </td>
                     <td>'. $row["module_name"] . "<br>" .' </td>
                     <td>'. $row["course_name"] . "<br>" .'</td>
                     <td>'. $row["semester_id"] . "<br>" .'</td>
                     <td>'.getTotal($cid,$mid). "<br>" .'</td>
                     <td>
                     <a href="AddModule.php ?edits='.$row["module_id"].'&&editc='.$row["course_id"].'" class="btn btn-sm btn-warning"><i class="far fa-edit"></i></a>
                     <button class="btn btn-sm btn-danger" data-href=" ?dlt='.$row["module_id"].'&&dllt='.$row["course_id"].' " data-toggle="modal" data-target="#confirm-delete"><i class="fas fa-trash"></i> </button>
                     </td> 
                     </tr>';
                     $count=$count+1;
                     }}
                     else{
                     echo "0 results";
                     }                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                
                    ?>
                </tbody>
              </table>
              </div>
  <div class="form-row">
      <div class="col-md-12">
                    <?php if(($_SESSION['user_type'] =='ADM') || ($_SESSION['user_type'] =='HOD')) { ?><a href="AddModule.php" style="text-align:center;font-weight: 900;font-size:15px;" class="text-primary page-link"><i class="fas fa-plus">&nbsp;&nbsp;ADD MODULE</a></i><?php }?>
      </div>
  </div>
      </div>
  </div>
<body>
</body>
<!-- end your code -->
<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->