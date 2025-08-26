<?php
$title="payment |SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
if(isset($_SESSION['user_type']) && $_SESSION['user_type']=='FIN'){ 
?>
<!-- dont change -->

    <div class="shadow p-3 mb-s bg-white rounded">
        <h1 class="text-center display-3">SLGTI Student Payment Portal</h1> 
        
    </div>
      
    <?php
    
    
$student_id=$student_name=$student_profile_img =$payment_id=$pays_reason=$payment_note=$pays_amount=$payment_id=$pays_date=$department=null;

// Ensure payment_method column exists on pays
@mysqli_query($con, "ALTER TABLE `pays` ADD COLUMN `payment_method` VARCHAR(20) NULL AFTER `payment_reason`");


if(isset($_POST['Add'])){
    

//   if(!empty($_POST['student_id'])
//   && !empty($_POST['student_name'])
//   && !empty($_POST['pays_depatment'])
//   && !empty($_POST['payment_type'])
// && !empty($_POST['payment_reason'])
// && !empty($_POST['payment_qty'])
// && !empty($_POST['payment_note'])
// && !empty($_POST['payment_amount'])){
    
    if
    (!empty($_POST['payment_type'])
    &&!empty($_POST['payment_reason'])
    &&!empty($_POST['payment_method'])
    &&!empty($_POST['payment_qty'])
    &&!empty($_POST['payment_note'])
    &&!empty($_POST['payment_amount'])){

      $student_id=$_POST['student_id'];
      $pays_department=$_POST['pays_department'];
      $pays_reason=$_POST['payment_reason'];
      $pays_qty=$_POST['payment_qty'];
      $pays_note=$_POST['payment_note'];
      $pays_amount=$_POST['payment_amount'];
      $payment_type=$_POST['payment_type'];
      $payment_method=$_POST['payment_method'];
     
     
      
      $sql="INSERT INTO `pays` (`student_id`,`payment_type`,`payment_reason`,`payment_method`,`pays_note`,`pays_amount`,`pays_qty`,`pays_date`,`pays_department`) 
      VALUES ('$student_id','$payment_type','$pays_reason','$payment_method','$pays_note','$pays_amount','$pays_qty',CURDATE(),'$pays_department')";
        // $sql="INSERT INTO `pays`(`student_id`,`payment_reason`,`pays_note`,`pays_amount`,`pays_qty`,`pays_department`) 
        // VALUES ('$student_id','$pays_reason','$pays_note','$pays_amount','$pays_qty','$pays_department')";

    


if(mysqli_query($con,$sql)){
        echo '
          <div class="alert alert-success alert-dismissible fade show" role="alert">
          <strong>'.$student_id.'</strong> <h4 class="text-center display-3">PAID</h4> 
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
          </button>
          </div>    
        ';
      }
      else{
        
        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>'.$student_id.'</strong> echo "Error".$sql."<br>".mysqli_error($con);
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
        </button>
        </div>
        
        ';
      
      }
      
    }
}

?>

    <?php

if(isset($_POST['edit'])){
      $id=$_POST['edit'];
      //$sql="SELECT student.student_id,student.student_fullname,student.student_profile_img, course.department_id from course,student where course.course_id=
      //(select student_enroll.course_id from student_enroll where student_enroll.student_id='$id') && student.student_id = '$id'";
      $sql="SELECT student.student_id,student.student_fullname,student.student_profile_img, course.department_id from course,student where course.course_id=
      (select student_enroll.course_id from student_enroll where student_enroll.student_id='$id'&&student_enroll.student_enroll_status='Following')&& student.student_id = '$id'";
      

      $result=mysqli_query($con,$sql);
      if(mysqli_num_rows($result)==1){   
          $row=mysqli_fetch_assoc($result);
           $student_id=$row['student_id'];
           $student_name=$row['student_fullname'];
           $department=$row['department_id'];
           $student_profile_img=$row['student_profile_img'];
          
          
      }
      else{
        echo "Error".$sql."<br>".mysqli_error($con);
      }
  }

?>
    <!-- Search ID -->

<div class="container">

<br>
    <div class="row ">
    <div class="col-sm-8"></div>
        <div class="col-sm-4 shadow p-3 mb-s bg-white rounded">
        <!-- <div class="col-sm-2"> </div>
        <div class="col-sm-4" > -->

            <form method="POST" action="#" class="form-inline"> 
                <div class="input-group  ">
                    <div class="input-group-prepend ">
                        <div class="input-group-text "><i class="fas fa-user"></i></div>
                    </div>
                    <div class="">
                    <input type="text" class="form-control" name="edit" placeholder=" Student ID">
                    <button type="submit" class="btn btn-primary">&nbsp;Search&nbsp;&nbsp;</button>
                    </div>
                </div>
                
                
                
            </form>

            
            </div>
        
    </div>
    <br>
    <form method="POST" action="#">
  
        <div class="row shadow p-3 mb-s bg-white rounded">
        <div class="col-sm-6"><?php if($student_profile_img!=null) { ?> <img src="<?php echo $student_profile_img; ?>"
                alt="..." width="150px" height="150px"> <?php }?><br>

            
                    <div class="form-row"><br>
                    <div class="form-group col-md-12"><i class="fas fa-id-card-alt"></i>&nbsp;
                        <label for="inputEmail4">ID</label>
                        <input type="text" name="student_id" value="<?php echo  $student_id;?>"
                            class="form-control<?php  if(isset($_POST['Add']) && empty($_POST['student_id'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['student_id'])){echo ' is-valid';} ?>"
                            id="inputEmail4" placeholder="ID" readonly="readonly">
                    </div>
                    <div class="form-group col-md-12"><i class="fas fa-user"></i>&nbsp;
                        <label for="inputEmail4">Name</label>
                        <input type="text" value="<?php echo  $student_name; ?>" name="student_name"
                            class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['student_name'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['student_name'])){echo ' is-valid';} ?>" id="inputEmail4" placeholder="Name" readonly="readonly" >
                    </div>
                    <div class="form-group col-md-12"><i class="fas fa-building"></i>&nbsp;
                        <label for="inputEmail4">Department</label>
                        <input type="Department" 
                            class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['pays_department'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['pays_department'])){echo ' is-valid';} ?>"" id="
                            inputEmail4" placeholder="Department"  readonly="readonly" name="pays_department" value="<?php echo  $department;?>">
                    </div>
                </div>

           
        </div>
        <div class="col-sm-6">


            
        <br> <br> 
                <div class="form-row">

                    <div class="input-group mb-3 col-md-12">

                        <div class="input-group-prepend">
                        <br>
                            <label class="input-group-text" for="inputGroupSelect01"><i class="fas
                        fa-swatchbook"></i>&nbsp;Payment Type&nbsp;&nbsp;&nbsp;&nbsp;</label>
                        </div>
                        <select class="custom-select <?php  if(isset($_POST['Add']) && empty($_POST['payment_type'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_type'])){echo ' is-valid';} ?> "
                            id="payment_type" name="payment_type" onchange="showpaymentreason(this.value)">
                            <option value="null" selected disabled>-- Select a Payment Type --</option>
                            <?php
                                $sql = "select DISTINCT payment_type from payment";
                                $result = mysqli_query($con, $sql);
                                if (mysqli_num_rows($result) > 0) {
                                while($row = mysqli_fetch_assoc($result)) {
                                    echo '<option  value="'.$row["payment_type"].'" required>'.$row["payment_type"].'</option>';
                                    
                                }
                                }else{
                                    echo '<option value="null"   selected disabled>-- No Course --</option>';
                                }
                                
                                ?>
                                        </select>
                                        
                                </div>


                    <div class="input-group mb-3 col-md-12 ">
                        <div class="input-group-prepend">

                            <label class="input-group-text" for="inputGroupSelect01"><i class="fas
        fa-swatchbook"></i>&nbsp;Payment Reason</label>
                        </div>
                        
                        <select class="custom-select  <?php  if(isset($_POST['Add']) && empty($_POST['payment_reason'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_reason'])){echo ' is-valid';} ?>
                        " id="payment_reason" name="payment_reason">
                             <option value="null" selected disabled>-- Select a Payment Reason --</option>
                         </select>
                    </div>

                    <div class="form-group col-md-12"><i class="fas fa-coins"></i>&nbsp;
                        <label for="inputEmail4">Amount</label>
                        <input type="number" min="1" 
                        class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['payment_amount'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_amount'])){echo ' is-valid';} ?>"
                             placeholder="Amount" name="payment_amount">
                    </div>

                    <div class="input-group mb-3 col-md-12">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="payment_method"><i class="fas fa-university"></i>&nbsp;Payment Method</label>
                        </div>
                        <select class="custom-select <?php  if(isset($_POST['Add']) && empty($_POST['payment_method'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_method'])){echo ' is-valid';} ?>" id="payment_method" name="payment_method">
                            <option value="" selected disabled>-- Select Method --</option>
                            <option value="SLGTI">SLGTI</option>
                            <option value="BANK">Bank</option>
                        </select>
                    </div>

                    <div class="form-group col-md-12"><i class="fas fa-th"></i>&nbsp;
                        <label for="text">Qty</label>
                        <input type="number"  min="1" max="50"class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['payment_qty'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_qty'])){echo ' is-valid';} ?>"
                            placeholder="Qty" name="payment_qty">
                    </div>
                    <div class="form-group col-md-12 "><i class="fas fa-sticky-note"></i>&nbsp;
                        <label for="inputEmail4">Note</label>
                        <input type="text" 
                        class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['payment_note'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_note'])){echo ' is-valid';} ?>" 
                             placeholder="Note" name="payment_note">
                    </div>
                </div>
                <h2>
                <button type="submit" name="Add" value="Add" class="btn btn-primary btn-block">
                    <h1><i class="fab fa-amazon-pay"></i>&nbsp;
                </button> </h1>
                <button type="submit" value="reset" class="btn btn-secondary btn-sm" ><i class="fas
        fa-redo-alt"></i>&nbsp;Reset</button>&nbsp;
                <a href="index.php" button type="button" class="btn btn-danger btn-sm"><i class="fas
        fa-times"></i>&nbsp;Close</a> <div class="row">



        </div>
        <div class="col-sm-0">
        
           
            
        <div class="col-sm-12"> 
        

      
    </div>


    <div class="row">
        <div class="col-sm-4">

        </div>
        

    <div class="row">
        <div class="col-sm-4">




        </div>
        <!--
-------------------------------------------------------------------------------------------------------------------------------
--------------------------------------- -->
        <div class="col-sm-4">


        </div>
        <div class="col-sm-4">


        </div>
        <!-- colom3........start -->
        <div class="col-sm-4">

        </div>
        <!-- colom3........ end -->
    </div>
    <br>
    </div>
    <!-- dont change -->
    

</div>
</div>

<script>
function showpaymentreason(val) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("payment_reason").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getPaymentReason", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("payment_type=" + val);
}
</script>
<!-- dont change -->
<?php
    include_once("../footer.php");
    ?>
    <?php } ?>
</body>

</html>


