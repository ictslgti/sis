<?php
$title = "assessment | SLGTI";
 include_once("../config.php"); 
 include_once("../head.php"); 
 include_once("../menu.php"); 
 ?>
<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START MY CODER HERE -->
<html>

    <body style="background-color: rgb(255,255,255);">



        <div class="shadow p-3 mb-5 bg-white rounded">

            <div class="highlight-blue">
                <div class="container">
                    <div class="intro">
                        <h1 class="display-4 text-center">Asignments Portal</h1>

                        <p class="text-center">Welcome <?php echo $_SESSION["user_name"];?> to examinations portal </p>

                    </div>
                </div>
            </div>
        </div>

        <!--  -->

        <?php
         echo $assessment_id=$student_id=$module_id=$assessments_marks=$assessment_attempt=null;

        if (isset($_POST['save'])) {
$sql = null;
//start
if (isset($_GET['StudentMarks'])) {
# code...
$id_x=$_GET['StudentMarks'];
// build student list: same academic year + repeat candidates (prior failed in this module)
$sql_x = "SELECT a.assessment_id, a.course_id, a.academic_year, a.module_id, se.student_id
          FROM assessments a
          JOIN student_enroll se ON se.course_id = a.course_id AND se.academic_year = a.academic_year
          WHERE a.assessment_id = '".$id_x."'
          UNION
          SELECT a.assessment_id, a.course_id, a.academic_year, a.module_id, m.student_id
          FROM assessments a
          JOIN assessments_marks m ON m.module_id = a.module_id
          WHERE a.assessment_id = '".$id_x."' AND m.assessment_marks < 40";

$result_x = mysqli_query($con, $sql_x);
if ($result_x === false) {
    echo '<div class="alert alert-danger">DB error (save list): '.htmlspecialchars(mysqli_error($con)).'</div>';
} elseif (mysqli_num_rows($result_x)>0) {
# code...
while ($row_x = mysqli_fetch_assoc($result_x)) {
     $postnamem = "M".$row_x['student_id'];
     $postnamea = "A".$row_x['student_id'];
     $postnames = "S".$row_x['student_id'];
     $postnamemo = "MO".$row_x['module_id'];
     echo $assessments_marks = $_POST[$postnamem];
     echo $assessment_attempt = $_POST[$postnamea];
     echo $student_id=$_POST[$postnames];
     echo $module_id=$_POST[$postnamemo];
     //insert 
     $sql .= "INSERT INTO `assessments_marks` 
     (`assessment_id`,`module_id`, `student_id`, `assessment_attempt`, `assessment_marks`) 
     VALUES ('".$id_x."','".$module_id."','".$student_id."','".$assessment_attempt."','".$assessments_marks."');";
     //end insert

}
}
}
echo $sql;

//end

if(!empty($sql) && mysqli_multi_query($con,$sql))
{
  echo '
    <div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>'.$assessment_id.'</strong> Assessment Type details inserted!
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
    </button>
    </div>   
  ';
}
else{
  
  echo '
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
  <strong>'.$assessment_id.'</strong> echo "Error".$sql."<br>".mysqli_error($con);
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
  <span aria-hidden="true">&times;</span>
  </button>
  </div>
  
  ';
}

}










        ?>






        <!-- main area start-->
        <div class="container">
            <div class="card">
                <br>
                <div class="intro">
                    <h3 class="display-5 text-center">Enter Students Asignments Marks</h3>
                </div>
                <br>
            </div>
            <br>
            <form  method="POST">
                <!-- table -->
                <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Assessment ID</th>
                            <th scope="col">Student Roll Number</th>
                            <th scope="col">Module</th>

                            <th scope="col">Marks</th>
                            <th scope="col">Attempt</th>
                            
                        </tr>
                    </thead>
                    <tbody>


                        <?php

                        if (isset($_GET['StudentMarks'])) {
                            # code...
                             $id=$_GET['StudentMarks'];
                          $sql = "SELECT a.assessment_id, a.course_id, a.academic_year, a.module_id, se.student_id
                                  FROM assessments a
                                  JOIN student_enroll se ON se.course_id = a.course_id AND se.academic_year = a.academic_year
                                  WHERE a.assessment_id = '".$id."'
                                  UNION
                                  SELECT a.assessment_id, a.course_id, a.academic_year, a.module_id, m.student_id
                                  FROM assessments a
                                  JOIN assessments_marks m ON m.module_id = a.module_id
                                  WHERE a.assessment_id = '".$id."' AND m.assessment_marks < 40";

                            $result = mysqli_query($con, $sql);
                            if ($result === false) {
                                echo '<tr><td colspan="6" class="text-danger">DB error: '.htmlspecialchars(mysqli_error($con)).'</td></tr>';
                            } elseif (mysqli_num_rows($result)>0) {
                                # code...
                                while ($row = mysqli_fetch_assoc($result)) {
                                    # code...
                                    echo '
                                    <tr>
                                    <th scope="row" id="assessment_id" name="assessment_id">' . $row ["assessment_id"].'</th>
                        <th scope="row" id="student_id" name="student_id">' . $row ["student_id"].'
                        <input type="hidden" name="S' . $row ["student_id"].'" value="' . $row ["student_id"].'" />
                        </th>
                        <th scope="row" id="module_id" name="module_id" >' . $row ["module_id"].'
                        <input type="hidden" name="MO' . $row ["module_id"].'" value="' . $row ["module_id"].'" />
                        </th>
                        
                        <td>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control form-control-sm" placeholder="Enter the Marks" 
                                    aria-label="Username" aria-describedby="basic-addon1" id="assessments_marks" name="M' . $row ["student_id"].'" required>
                            </div>
                        </td>
                        <td>
                            <div class="input-group mb-3">

                                <select class="custom-select custom-select-sm" id="assessment_attempt" name="A' . $row ["student_id"].'">
                                    <option selected>----Choose Attempt--- </option>
                                    <option value="1">Attempt 1</option>
                                    <option value="2">Attempt 2</option>
                                    <option value="3">Attempt 3</option>
                                </select>
                            </div>


                        </td>
                       

                                    
                                    
                                    
                                    </tr>
                                   
                                   ';
                                }
                            }
                            else {
                                # code...
                                echo "0 results";
                            }

                        }




                        ?>








                    </tbody>
                </table>
                </div>
                <div class="row">
                    <div class="col">

                    </div>
                    <div class="col-md-auto">

                    </div>
                    <div class="col col-lg-2">
                        <button type="submit" class="btn btn-sm btn-outline-primary" name="save" id="save">&nbsp;&nbsp;&nbsp;<i
                                class="fas fa-save"></i>&nbsp;&nbsp;Save&nbsp;&nbsp;&nbsp;</button>
                    </div>
                </div>
            </form>
            <!-- end of add table -->
            <br>
            <!-- save button start -->

            <!-- save button end -->
            <br>
            <br>
            <br>
            <br>
            <!-- card start -->
            <div class="card">
                <br>
                <div class="container">
                    <div class="intro">
                        <h3 class="display-5 text-center">Overall Module Marks</h3>
                    </div>
                </div>
                <br>
            </div>
            <!-- end card -->
            <br>
            <!-- <div class="row">
                <div class="col">
                    1 of 2
                </div>
                <div class="col">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Student's Index Number"
                            aria-label="Recipient's username" aria-describedby="button-addon2">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button"
                                id="button-addon2">&nbsp;&nbsp;&nbsp;<i
                                    class="fas fa-search"></i>&nbsp;&nbsp;&nbsp;Search&nbsp;&nbsp;&nbsp;</button>
                        </div>
                    </div>
                </div>
            </div> -->
            <?php

if(isset($_GET['delete'])){
    $assessment_marks_id = $_GET['delete'];
     $sql = "DELETE FROM `assessments_marks` WHERE `assessment_marks_id` = '$assessment_marks_id'";
    
    if (mysqli_query($con, $sql)){

        echo '<div class="alert alert-danger">
        <strong>Success!</strong> Assessment Marks was Deleted.</a>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span></button>
      </div>';

        
        
        // echo '<button class = "btn btn-danger"><div class="fa-1.5x"><i class="fas fa-trash fa-pulse "></i>&nbsp;&nbsp;Delete Success</div></button>';

    }else{
        echo "Error deleting record:" . mysqli_error($con);
    }
}

?>

<?php
     $noOfRows=null;
     $sql1="SELECT COUNT(assessment_marks_id) AS NumberOfAssessments FROM assessments_marks";
     $result=mysqli_query($con,$sql1);
     if(mysqli_num_rows($result) == 1)
     {
          $row=mysqli_fetch_assoc($result);
          $noOfRows=$row["NumberOfAssessments"];

          //in a single view table only show 10 entry so devid total number of rows by 10. this says how many buttons want to print
          $noOFButtions= round($noOfRows/10)+1;
     }
 ?>







            <!-- small view table start  -->
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th scope="col">Marks ID</th>
                     <th scope="col">Module ID</th>
                        <th scope="col">Assessment ID</th>
                        <th scope="col">Student ID</th>
                        <th scope="col">Assessment Attempt</th>
                        <th scope="col">Assessment Marks</th>
                        
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php
               include_once("getAssessmentResults.php");
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
        ajax.open("POST", "getAssessmentResults", true);

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
          ajax.open("POST", "getAssessmentResultsSearch", true);

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









    </body>

</html>
<!-- END OF MY CODE -->





<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
