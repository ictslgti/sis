<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->  
<?php
 $title = "payment report | SLGTI";
 include_once("../config.php"); 
 include_once("../head.php"); 
 include_once("../menu.php"); 
 if(isset($_SESSION['user_type']) && $_SESSION['user_type']=='FIN'){ 
 ?>
<!--END DON'T CHANGE THE ORDER--> 

<!--BLOCK#2 START YOUR CODE HERE -->
<!--to count number of rows in the database table and calculate how many buttons want to print -->
<?php
     $noOfRows=null;
     $sql1="SELECT COUNT(pays_id) AS NumberOfPays FROM pays";
     $result=mysqli_query($con,$sql1);
     if(mysqli_num_rows($result) == 1)
     {
          $row=mysqli_fetch_assoc($result);
          $noOfRows=$row["NumberOfPays"];

          //in a single view table only show 10 entry so devid total number of rows by 10. this says how many buttons want to print
          $noOFButtions= round($noOfRows/10)+1;
     }
 ?>

<!-- navbar and search bar -->
<div class="shadow p-3 mb-s bg-white rounded">
<h1 class="text-center display-3">SLGTI Student Payment Report Portal</h1></div><br>
 <div class=" rounded-top bg-light pt-2 mt-2 pb-2 shadow p-3 mb-s bg-white rounded">
          <a class="navbar-brand font-weight-bold ml-2 "> Payment Details</a>
          
               <input class=" float-right btn btn-outline-success my-2 my-sm-0 mr-2" id="searchBtn" value="Search" type="button" onclick="search();">
               <input  style="width: 15%" class="float-right form-control mr-sm-2 mb-2" id="searchFld" type="search" placeholder="Search" aria-label="Search">
            
</div>

<!-- print header of the thable -->
     <div class="table-responsive shadow p-3 mb-s bg-white rounded">  
          <table id="books" class="table shadow p-3 mb-s bg-white rounded">  
               <thead> 
               <br> 
                    <tr>
                        <th>PAYMENT ID</th>
                        <th>STUDENT ID</th>
                        <th>PAYMENT CATEGORY</th>
                        <th>PAYMENT REASON</th>
                        <th>PAYMENT NOTE</th>
                        <th>PAYMENT AMOUNT</th>
                        <th>PAYMENTQTY</th>
                        <th>PAYMENT DATE</th>
                        <th>PAYMENT DEPARTMENT</th>
                    </tr>
               </thead>
               <tbody id="BookData">
               <?php
               include_once("getPaymentData.php");
               ?>
               <tbody>             
          </table>

          <div class="clearfix float-right mr-2">
                <ul class="pagination">
                <li class="page-item"  id="0" value="0" onclick="showName(this.id);"><a class="text-primary page-link">First</a></li>
                <?php
                    $count=1;
                    while($noOFButtions>$count){
               ?>
               <li class="page-item"  id="<?php echo $count;?>" value="<?php echo $count+1;?>" onclick="showName(this.id);"><a class="text-primary page-link"><?php echo $count+1;?></a></li>
               <?php            
               $count=$count+1;
               }
               ?>
                    <li class="page-item"  id="<?php echo $count;?>" value="<?php echo $count+1;?>" onclick="showName(this.id);"><a class="text-primary page-link">Last</a></li>
                </ul>
                <ul class="pagination">
                <li class="page-item rounded"  id="all" value="all" onclick="showName('0');"><a class="text-primary page-link">All Entries</a></li>
                </ul>
            </div>

     </div>


<!-- java script & ajax to get button values and set to a PHP variable -->
<script>
     function showName(idOfButton){
         //document.getElementById(idOfButton).style.background='red';

        //call ajax
        var ajax = new XMLHttpRequest();
        ajax.open("POST", "getPaymentData", true);

        //sending ajax request
        ajax.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        ajax.send("id="+idOfButton);

        //reciving responce from data.php
        ajax.onreadystatechange = function(){
          if (this.readyState == 4 && this.status == 200){
               document.getElementById("BookData").innerHTML = this.responseText;
          }
        }

        var butCount = <?php echo $noOFButtions ?>;
        while (butCount>=0){
          var x = document.getElementById(butCount);
          x.style.display = "none";
          butCount=butCount-1;
     }

     butCount = <?php echo $noOFButtions ?>;
     if(idOfButton==butCount){
        var x = document.getElementById(idOfButton);
        var afterId = parseInt(idOfButton)-2;
        var beforId = parseInt(idOfButton)-1;
        var y = document.getElementById(afterId);
        var w = document.getElementById(beforId);
        var first = document.getElementById('0');
        var last = document.getElementById(butCount);

        //document.write(beforId);
        x.style.display = "inline";
        y.style.display = "inline";
        w.style.display = "inline";
        first.style.display = "inline";
        last.style.display = "inline";


     }else if (idOfButton==0){
     var x = document.getElementById(idOfButton);
        var afterId = parseInt(idOfButton)+1;
        var beforId = parseInt(idOfButton)+2;
        var w = document.getElementById(beforId);
        var y = document.getElementById(afterId);
        var first = document.getElementById('0');
        var last = document.getElementById(butCount);
        //document.write(beforId);
        x.style.display = "inline";
        y.style.display = "inline";
        w.style.display = "inline";
        first.style.display = "inline";
        last.style.display = "inline";

     }else
     {
        var x = document.getElementById(idOfButton);
        var afterId = parseInt(idOfButton)+1;
        var beforId = parseInt(idOfButton)-1;
        var w = document.getElementById(beforId);
        var y = document.getElementById(afterId);
        var first = document.getElementById('0');
        var last = document.getElementById(butCount);
        //document.write(beforId);
        x.style.display = "inline";
        w.style.display = "inline";
        y.style.display = "inline";
        first.style.display = "inline";
        last.style.display = "inline";

        var a = document.getElementById(idOfButton);
        var b = parseInt(idOfButton)+1;
        var beforId = parseInt(idOfButton)-1;
        var w = document.getElementById(beforId);
        var y = document.getElementById(afterId);
        var first = document.getElementById('0');
        var last = document.getElementById(butCount);      
     }
        var all = document.getElementById("all");
        all.style.display = "none";
     }

     var butCount = <?php echo $noOFButtions ?>;
        while (butCount>=0){
          var x = document.getElementById(butCount);
          x.style.cursor = "pointer";
          x.style.display = "none";
          butCount=butCount-1;    
     }
     var all = document.getElementById("all");
     all.style.display = "none";

     var butCount = <?php echo $noOFButtions ?>;
     var one = document.getElementById('0');
     var two = document.getElementById('1');
     var three = document.getElementById('2');
     var first = document.getElementById('0');
     var last = document.getElementById(butCount);

     one.style.display = "inline";
     two.style.display = "inline";
     three.style.display = "inline";
     first.style.display = "inline";
     last.style.display = "inline";


     function search(){
        var cheackText = document.getElementById('searchFld').value;
        if  (cheackText!=""){

          //call ajax
          var ajax = new XMLHttpRequest();
          ajax.open("POST", "getPaymentDataSearch", true);

          //sending ajax request
          ajax.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
          var text =  document.getElementById('searchFld').value;
          //document.write(text);
          ajax.send("text="+text);

          //reciving responce from data.php
          ajax.onreadystatechange = function(){
               if (this.readyState == 4 && this.status == 200){
                    document.getElementById("BookData").innerHTML = this.responseText;
               }
          }

          var butCount = <?php echo $noOFButtions ?>;
          while (butCount>=0){
               var x = document.getElementById(butCount);
               x.style.cursor = "pointer";
               x.style.display = "none";
               butCount=butCount-1;
          
               }
        var all = document.getElementById("all");
        all.style.display = "inline";
          }
     }
</script>
<!--END OF YOUR COD-->
<?php } ?>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->   
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->  
