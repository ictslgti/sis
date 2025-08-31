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
<div class="shadow p-3 mb-5  alert bg-dark rounded  text-white text-center" role="alert">

        <div class="highlight-blue">
            <div class="container">
                <div class="intro">
                    <h1 class="display-4 text-center">SLGTI Academic Year</h1>
                    
                   
                </div>
            </div>
        </div>
    </div>
    <?php if(($_SESSION['user_type'] =='ADM')) { ?><a href="AddAcademicYear" button type="button" class="btn btn-success"><i class="fas fa-plus"></i>&nbsp;Add Academic Year </a>


    <?php }?>
<!-- <h1 class="col text-center">Department Details</h1> -->
<br><br>
<table class="table table-hover">
  <thead class="thead-dark">
 
    <tr >
      <th scope="col">Academic Year</th>
      <th scope="col">First Semi Start Date</th>
      <th scope="col">First Semi End Date</th>
      <th scope="col">Second Semi Start Date</th>
      <th scope="col">Second Semi End Date</th>
      <th scope="col">Academic Year Status</th>
      <?php if(($_SESSION['user_type'] =='ADM')) { ?><th scope="col">Option</th><?php }?>
      </tr>
      </thead>
      <tbody>
      <tr class="table-light">
      </tr>
      <?php

if(isset($_GET['delete'])){
    $academic_year = $_GET['delete'];
    $sql = "DELETE FROM `academic` WHERE `academic_year` = '$academic_year'";
    
    if (mysqli_query($con, $sql)){
        echo '<a class = "text-danger"><div class="fa-1.5x"><i class="fas fa-trash fa-pulse "></i>&nbsp;&nbsp;Delete Success</div></a>';
    }else{
        echo "Error deleting record:" . mysqli_error($con);
    }
}

?>
      <?php

// Updated to use the correct table name 'academic' instead of 'academic_year'
$sql = "SELECT * FROM academic ORDER BY academic_year DESC";
$result = mysqli_query($con, $sql);
if (mysqli_num_rows($result)>0){
    while ($row = mysqli_fetch_assoc($result)){
        echo'
        <tr>
        <td>' . $row ["academic_year"].'</td>
        <td>' . $row ["first_semi_start_date"].'</td>
        <td>' . $row ["first_semi_end_date"].'</td>
        <td>' . $row ["second_semi_start_date"].'</td>
        <td>' . $row ["second_semi_end_date"].'</td>
        <td><span class="badge badge-success">' . $row ["academic_year_status"].'</span></td>
        <td>';?>
        <?php if(($_SESSION['user_type'] =='ADM')) { ?><?php echo'
        <a href="'.(defined('APP_BASE') ? APP_BASE : '').'/academic/AddAcademicYear.php?edit='.rawurlencode($row["academic_year"]).'" class="btn btn-sm btn-warning"><i class="far fa-edit"></i></a>
    <button class="btn btn-sm btn-danger" data-href="?delete='.$row["academic_year"].'" data-toggle="modal" data-target="#confirm-delete"><i class="fas fa-trash"></i> </button>
    ';?>
         <?php }?>
    

    
      
         <?php echo'</tr>';
    }
}else{
echo "0 results";
}


?>

  
</tbody>
  </form>
</table>
</div>

<!-- END YOUR CODER HERE -->

    <!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
    <?php 
    include_once("../footer.php");
    ?>
    <!-- END DON'T CHANGE THE ORDER -->
