<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "payment | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

 if($_SESSION['user_type']=='ACC'||'ADM'){ 
?>
<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START YOUR CODE HERE -->



<div class="shadow p-3 mb-s bg-white rounded">
  <h1 class="text-center display-3">SLGTI Student Payment Information</h1><br>
  </div>
  </body>

 <?php
	 
 $payment_type=$payment_reason=null;
 if(isset($_POST['Add'])){ 
	 if
    (!empty($_POST['payment_type'])
    &&!empty($_POST['payment_reason'])){
		 
    $payment_type=$_POST['payment_reason'];
     $payment_reason=$_POST['payment_type'];
    $sql="INSERT INTO `payment`(`payment_reason`, `payment_type`) VALUES ('$payment_reason', '$payment_type')";
    if(mysqli_query($con,$sql)){
        echo '
          <div class="alert alert-success alert-dismissible fade show" role="alert">
          <strong>'.$payment_type.'</strong> <h6 class="text-center display-3">Add</h6> 
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
          </button>
          </div>    
        ';
      }
      else{
        
        echo '
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>'.$payment_type.'</strong> echo "Error".$sql."<br>".mysqli_error($con);
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
        </button>
        </div>
        
        ';
      }

      }
    
}
?><br>
  <form method="POST"  action="#">
  <div class="form-group col-md-4 shadow p-3 mb-s bg-white rounded"><h4><i class="fas fa-folder-plus"></i>&nbsp;
                        <label for="inputEmail4">Add Payment Category</label></h4>
                        <input type="Department" 
                            class="form-control" id="
                            inputEmail4" placeholder="Typing Very Carefully" name="payment_reason">
                    </div>

                    <div class="form-group col-md-4 shadow p-3 mb-s bg-white rounded"><h4><i class="fas fa-folder-plus"></i>&nbsp;
                        <label for="inputEmail4">Add Payment Reason</label></h4>
                        <input type="Department" 
                            class="form-control" id="
                            inputEmail4" placeholder="Typing Very Carefully" name="payment_type">
                            <br>
                            
                    </div>
                    <button type="submit"name="Add" value="Add" class="btn btn-outline-success ">ADD+</button>
  </form>
  <br>
	

<div class="row shadow p-3 mb-s bg-white rounded">
    <div class="col-sm-12">
    <table class="table">

    <tr>
        <th>PAYMENT ID</th>
        <th>STUDENT ID</th>
        <th>PAYMENT CATEGORY</th>
        <th>PAYMENT REASON</th>
        <th>PAYMENT METHOD</th>
        <th>PAYMENT NOTE</th>
        <th>PAYMENT AMOUNT</th>
        <th>PAYMENTQTY</th>
        <th>PAYMENT DATE</th>
        <th>PAYMENT DEPARTMENT</th>
      

</tr>



<?php 

$sql="CALL `getin`()";
$result=mysqli_query($con,$sql);
if(mysqli_num_rows($result)>0){
    while($row=mysqli_fetch_assoc($result)){
        echo'
        <tr>
        <td>'.$row['pays_id'].'</td>
        <td>'.$row['student_id'].'</td>
        <td>'.$row['payment_type'].'</td>
        <td>'.$row['payment_reason'].'</td>
        <td>'.(isset($row['payment_method']) && $row['payment_method']!=='' ? $row['payment_method'] : '-').'</td>
        <td>'.$row['pays_note'].'</td>
        <td>'.$row['pays_amount'].'</td>
        <td>'.$row['pays_qty'].'</td>
        <td>'.$row['pays_date'].'</td>
        <td>'.$row['pays_department'].'</td>
        
        
        </tr>';

        
    }
}else{
    $result=0;
}

?>

</table>
    </div>
    <div class="col-sm-6">

    <!-- <table class="table">

    <tr>
        <th>payment_reason</th>
        <th>payment_type</th>
    </tr>

<?php 

$sql="SELECT * FROM `payment`";
$result=mysqli_query($con,$sql);
if(mysqli_num_rows($result)>0){
    while($row=mysqli_fetch_assoc($result)){
        echo'
        <tr>
        <td>'.$row['payment_reason'].'</td>
        <td>'.$row['payment_type'].'</td>
         </tr>';

    }
}else{
    $result=0;
}

?>
</table> -->
    </div>
  </div>

<!--END OF YOUR COD-->
<?php } ?>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
