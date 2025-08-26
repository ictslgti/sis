<?php
$title="payment |SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
if($_SESSION['user_type']=='ACC'||'ADM'){
?>
<!-- dont change -->

    <div class="shadow p-3 mb-s bg-white rounded">
        <h1 class="text-center display-3">SLGTI Student Payment Modify Portal</h1> 
        
    </div>
      <br>
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
    

      $student_id=$_POST['student_id'];
      $pays_department=$_POST['pays_department'];
      $pays_reason=$_POST['payment_reason'];
      $pays_qty=$_POST['payment_qty'];
      $pays_note=$_POST['payment_note'];
     $pays_amount=$_POST['payment_amount'];
      $payment_type=$_POST['payment_type'];
     
     
      
      $sql="INSERT INTO `pays` (`student_id`,`payment_type`,`payment_reason`,`pays_note`,`pays_amount`,`pays_qty`,`pays_department`) 
      VALUES ('$student_id','$payment_type','$pays_reason','$pays_note','$pays_amount','$pays_qty','$pays_department')";
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

?>
<?php
$student_id=$student_name=$student_profile_img =$payment_id=$pays_reason=$payment_note=$pays_amount=$payment_id=$pays_date=$department=null;

if(isset($_GET['upt'])){
$id=$_GET['upt'];
$sql="SELECT * FROM `pays` WHERE `pays_id`='$id'";
$result = mysqli_query($con ,$sql);
         if(mysqli_num_rows($result)== 1){
              $row = mysqli_fetch_assoc($result);
               $student_id=$row['student_id'];
               $department=$row['pays_department'];
             $pays_reason=$row['payment_reason'];
               $pays_qty=$row['pays_qty'];
               $pays_note=$row['pays_note'];
               $pays_amount=$row['pays_amount'];
               $payment_type=$row['payment_type'];
               $payment_method= isset($row['payment_method']) ? $row['payment_method'] : '';
              
              
          }
      }

?>
    <?php

if(isset($_POST['edit'])){
      $id=$_GET['upt'];
      $payment_type=$_POST['payment_type'];
      $pays_reason=$_POST['payment_reason'];
      $payment_method= isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
      $pays_note=$_POST['payment_note'];
      $pays_amount=$_POST['payment_amount'];
      $pays_qty=$_POST['payment_qty'];

      $sql="UPDATE `pays` SET `payment_type`='$payment_type',
      `payment_reason`='$pays_reason',`payment_method`='$payment_method',`pays_note`=' $pays_note',`pays_amount`='$pays_amount',`pays_qty`='$pays_qty' WHERE `pays_id`='$id'";
      

     if(mysqli_query($con,$sql)){

          echo
            '<div class="alert alert-success">
            <strong>Success!</strong> <h4 class="text-center display-3">Modified</h4></a>
          </div>';
          
      }
      else{
        echo "Error".$sql."<br>".mysqli_error($con);
      }
  }

?>
    <!-- Search ID -->


    <div class="container">

    <div class="row shadow p-3 mb-s bg-white rounded">
       
        
    <form method="POST" action="#">
  
        <div class="row shadow p-3 mb-s bg-white rounded ">
        <div class="col-sm-4"><?php if($student_profile_img!=null) { ?> <img src="<?php echo $student_profile_img; ?>"
                alt="..." width="100px" height="100px"> <?php }?><br>

            
                <div class="form-row">
                    <div class="form-group col-md-12"><i class="fas fa-id-card-alt"></i>&nbsp;
                        <label for="inputEmail4">ID</label>
                        <input type="text" name="student_id" value="<?php echo  $student_id;?>"
                            class="form-control<?php  if(isset($_POST['Add']) && empty($_POST['student_id'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['student_id'])){echo ' is-valid';} ?>"
                            id="inputEmail4" placeholder="ID" disabled>
                    </div>
                    
                    <div class="form-group col-md-12"><i class="fas fa-building"></i>&nbsp;
                        <label for="inputEmail4">Department</label>
                        <input type="Department" 
                            class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['pays_department'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['pays_department'])){echo ' is-valid';} ?>"" id="
                            inputEmail4" placeholder="Department" name="pays_department" value="<?php echo  $department;?>" disabled>
                    </div>
                    <div class="input-group mb-3 col-md-12">

                        <div class="input-group-prepend">

                            <label class="input-group-text" for="inputGroupSelect01"><i class="fas
                        fa-swatchbook"></i>&nbsp;Payment Type&nbsp;&nbsp;&nbsp;&nbsp;</label>
                        </div>
                        <select class="custom-select <?php  if(isset($_POST['Add']) && empty($_POST['payment_type'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_type'])){echo ' is-valid';} ?> "
                            id="payment_type" name="payment_type" onchange="showpaymentreason(this.value)"  value="<?php echo $payment_type;?>">
                            <option value="null" selected disabled>-- Select a Payment Reason --</option>
                    
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
                        <input type="text" value="<?php echo $pays_amount;?>"
                        class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['payment_amount'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_amount'])){echo ' is-valid';} ?>"
                             placeholder="Amount" name="payment_amount" >
                    </div>

                    <div class="input-group mb-3 col-md-12">
                        <div class="input-group-prepend">
                            <label class="input-group-text" for="payment_method"><i class="fas fa-university"></i>&nbsp;Payment Method</label>
                        </div>
                        <select class="custom-select <?php  if(isset($_POST['edit']) && empty($_POST['payment_method'])){echo ' is-invalid';}if(isset($_POST['edit']) && !empty($_POST['payment_method'])){echo ' is-valid';} ?>" id="payment_method" name="payment_method">
                            <option value="" disabled <?php echo (empty($payment_method))? 'selected' : '';?> >-- Select Method --</option>
                            <option value="SLGTI" <?php echo ($payment_method==='SLGTI')?'selected':''; ?>>SLGTI</option>
                            <option value="BANK" <?php echo ($payment_method==='BANK')?'selected':''; ?>>Bank</option>
                        </select>
                    </div>

                    <div class="form-group col-md-12"><i class="fas fa-th"></i>&nbsp;
                        <label for="text">Qty</label>
                        <input type="text" class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['payment_qty'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_qty'])){echo ' is-valid';} ?>"
                            placeholder="Qty" name="payment_qty" value="<?php echo $pays_qty;?>">
                    </div>
                    <div class="form-group col-md-12 "><i class="fas fa-sticky-note"></i>&nbsp;
                        <label for="inputEmail4">Note</label>
                        <input type="text"  value="<?php echo $pays_note;?>"
                        class="form-control <?php  if(isset($_POST['Add']) && empty($_POST['payment_note'])){echo ' is-invalid';}if(isset($_POST['Add']) && !empty($_POST['payment_note'])){echo ' is-valid';} ?>" 
                             placeholder="Note" name="payment_note"> 
                    </div>

                    <button type="submit" name="edit" value="Add" class="btn btn-primary btn-block">
                    <h1>UPDATE&nbsp;
                </button> </h1>
               <br>
                
                </div>
            



        </div>
                </div>

           
        </div>
        <div class="col-sm-4">


            

                <div class="form-row">

                    
        <div class="col-sm-4">
        
           
        <h2>
                 <div class="row">
        <div class="col-sm-12"> 
        

        







        </div>
        </div>
        </div>
        <br>
    </form>
    </div>
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
<?php }?> </div>
</body>

</html>
