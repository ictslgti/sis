	
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
  $cid = $cname = $ctraining = $cojt =  $nvq = $did =null;

  if(isset($_GET['edits']))
  {
    $cid = $_GET['edits'];
    $sql = "SELECT * FROM course WHERE course_id = '$cid'";
    $result = mysqli_query($con,$sql);
    
    if(mysqli_num_rows($result)==1)
    {
    $row = mysqli_fetch_assoc($result);
    $cname = $row['course_name'];
    $ctraining = $row['course_institute_training'];
    $cojt = $row['course_ojt_duration'];
    $did = $row['department_id'];
    $nvq = $row['course_nvq_level'];
    
   }
   
  }

if(isset($_POST['Editing']))
{
  if(!empty($_POST['co_training']) && !empty($_POST['co_name']) && !empty($_POST['co_ojt']) && !empty($_POST['n_level'])&& !empty($_GET['edits']))
  {
  
  $cname = $_POST['co_name'];
  $ctraining = $_POST['co_training'];
  $cojt = $_POST['co_ojt'];
  $did = $_POST['d_name'];
  $nvq = $_POST['n_level'];
  $cid =$_GET['edits'];

  

   $sql="UPDATE `course` SET `course_name`='$cname',`course_nvq_level`='$nvq' ,`course_ojt_duration`='$cojt',`course_institute_training`='$ctraining',`department_id`='$did' WHERE `course_id`='$cid'";

   if(mysqli_query($con,$sql))
   {
     echo '
     <div class="alert alert-sucess alert-dismissible fade show" role="alert">
     <strong> '.$cid.' </strong> Record has been Updated Succesfully 
     <button type="button" class="close" data-dismiss="alert" aria-label="Close">
       <span aria-hidden="true">&times;</span>
     </button>
   </div>  ';
   }
   else
   {
     echo '
     <div class="alert alert-danger alert-dismissible fade show" role="alert">
     <strong> '.$cid.' </strong> Is Used In Another Table
     <button type="button" class="close" data-dismiss="alert" aria-label="Close">
       <span aria-hidden="true">&times;</span>
     </button>
   </div>  ';               
    
   }
   }
}

if(isset($_POST['Adding']))
{
  
  
  if(!empty($_POST['co_training'])&& !empty($_POST['co_name'])&& !empty($_POST['co_ojt'])&& !empty($_POST['d_name'])&& !empty($_POST['n_level'])&&!empty($_POST['co_id']))
  {
    $cid =$_POST['co_id'];
    $cname = $_POST['co_name'];
    $ctraining = $_POST['co_training'];
    $cojt = $_POST['co_ojt'];
    $did = $_POST['d_name'];
    $nvq = $_POST['n_level'];

     $sql = "INSERT INTO course(course_id, course_name, course_nvq_level , department_id,course_ojt_duration,course_institute_training) VALUES ( '$cid' ,'$cname','$nvq', '$did' ,'$cojt','$ctraining' )";
     
     if(mysqli_query($con,$sql))
     {
       echo '
         <div class="alert alert-success alert-dismissible fade show" role="alert">
         <strong>'.$cid.'</strong>Succesfully Has Been Added
         <button type="button" class="close" data-dismiss="alert" aria-label="Close">
         <span aria-hidden="true">&times;</span>
         </button>
         </div>    
       ';
     }
     else{
       
       echo '
       <div class="alert alert-danger alert-dismissible fade show" role="alert">
       <strong>'.$cid.'</strong> Course ID Is Already Exist!
       <button type="button" class="close" data-dismiss="alert" aria-label="Close">
       <span aria-hidden="true">&times;</span>
       </button>
       </div>
       
       ';
     }

  }

}

?>

<hr class="mb-8 mt-4">
		<div class="card mt-12 ">
			<div class="card"><br>
      <?php
       if(isset($_GET['edits']))
       {echo' <h4 style="text-align:center">Edit Course Details</h4> <br>';}
       else
       {echo' <h4 style="text-align:center">Add Course Details</h4> <br>';}
      ?>
				
      </div>
    </div>
 <br>
 <br>  <form method="POST">
            <div class="row">

              <div class="col-md-6 mb-3">
                <label for="Course ID">Course ID</label>
                <input type="text" name="co_id" class="form-control"  placeholder=""  value="<?php echo $cid ?>" required  <?php if(isset($_GET['edits'])) { echo "disabled='true'"; } ?>>
               
              </div>

              
              <div class="col-md-6 mb-3">
                <label for="Course Name">Course Name</label>
                <input type="text" class="form-control"  placeholder="" name="co_name" value="<?php echo $cname ?>" required>
              
              </div>

            </div>

            <div class="row">

              <div class="col-md-6 mb-3"> 
                <label for="Duration-Institute Training">Duration-Institute Training</label>
              <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">Months</span>
              </div>
                <input type="text" class="form-control"  placeholder="Month in Digits" name="co_training" value ="<?php echo $ctraining ?>" onkeypress="Number(event)" min="1" maxlength="4" required>
              
              </div>
              </div>
              
              <div class="col-md-6 mb-3"> 
                <label for="Duration-OJT">Duration-OJT</label>
              <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">Months</span>
              </div>
                <input type="text" class="form-control"  placeholder="Month in Digits" name="co_ojt" value="<?php echo $cojt ?>" onkeypress="Number(event)" min="1"  maxlength="2"required>
              
              </div>
              </div>
            
            </div>

            <div class="row">

              <div class="col-md-6 mb-3">
                <label for="Department">Department</label>
                <select class="custom-select d-block w-100"  name="d_name" required>
                    <option disabled selected>Select Department Name...</option>
                    <?php
                     $sql = "SELECT * FROM department";
                     $result = mysqli_query($con, $sql);
                     if(mysqli_num_rows($result)>0)
                     {
                       while($row = mysqli_fetch_assoc($result))
                       {
                         echo '<option value ="'.$row['department_id'].'" ';

                         if($row['department_id']== $did)
                         {
                           echo 'selected';
                         }
                         echo '>' .$row['department_name'].'</option>';
                       }
                     }
                    ?>
                    
                </select>
              
              </div>

              <div class="col-md-6 mb-3">
                <label for="Level">NVQ Level</label>
              <input type="text" class="form-control" id="unit" placeholder="NVQ are only '3-6' and 'BRI' for Bridging"   name="n_level" value="<?php echo $nvq ?>" onkeypress="NumberR(event)" min="3" max="6" maxlength="3" required>
              </div>
            
                
                
      
<br><br>
<?php
      if(isset($_GET['edits']))
      {
        echo '<input id="button" class="btn btn-primary btn-lg btn-block" type="submit" name="Editing" value ="Continue To Edit Course Details">';
      }
      else
      {
        echo '<input id="buttuon"  class="btn btn-primary btn-lg btn-block"type="submit" name="Adding" value ="Continue To Add Course Details">';
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
<br>
     

<body>

</body>
<!-- end your code -->


<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
    
    
  
