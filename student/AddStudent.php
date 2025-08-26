<!------START DON'T CHANGE ORDER HEAD,MANU,FOOTER----->
<!---BLOCK 01--->
<?php 
   
include_once("../config.php");
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$title ="STUDENTS' REGISTRATION FORM | SLGTI";
include_once("../head.php");
include_once("../menu.php");

?>
<!----END DON'T CHANGE THE ORDER---->


<!---BLOCK 02--->
<!---START YOUR CODER HERE----->
<!-----END YOUR CODE----->
<!-- form start---->
<?php
//echo $_SESSION['user_name'];  
// Ensure new columns exist for nationality and WhatsApp
$__col = mysqli_query($con, "SHOW COLUMNS FROM `student` LIKE 'student_nationality'");
if ($__col && mysqli_num_rows($__col) === 0) { @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_nationality` VARCHAR(100) NULL"); }
$__col = mysqli_query($con, "SHOW COLUMNS FROM `student` LIKE 'student_whatsapp'");
if ($__col && mysqli_num_rows($__col) === 0) { @mysqli_query($con, "ALTER TABLE `student` ADD COLUMN `student_whatsapp` VARCHAR(25) NULL"); }

$stid = $title = $fname = $ininame = $gender = $civil = $email = $nic = $dob = $phone = $address = $zip = $district = $division = $province = $blood = $mode =
$ename = $eaddress = $ephone = $erelation = $enstatus = $coid = $year = $enroll = $exit = $qutype = $index = $yoe = $subject = $results = null;
$nationality = $whatsapp = null;
//$stid = $_SESSION['user_name'];
// edit
if(isset($_GET['edit']))
{
  $stid =$_GET['edit'];
  $sql = "SELECT s.`student_id`,`student_title`,`student_fullname`,`student_ininame`,`student_gender`,`student_civil`,`student_email`,`student_nic`,
  `student_dob`,`student_phone`,`student_address`,`student_zip`,`student_district`,`student_divisions`,`student_provice`,`student_blood`,
  s.`student_nationality`,s.`student_whatsapp`,`student_em_name`,`student_em_address`,`student_em_phone`,`student_em_relation`,
  e.`course_id`,e.`course_mode`,e.`academic_year`,e.`student_enroll_date`,e.`student_enroll_exit_date`, e.`student_enroll_status`
  FROM `student` s LEFT JOIN `student_enroll` e ON s.`student_id` = e.`student_id` WHERE s.`student_id`='$stid' ORDER BY e.`student_enroll_date` DESC LIMIT 1";
  $result = mysqli_query($con,$sql);

  //echo 'academic_year'.$row['academic_year'];
  if(mysqli_num_rows($result)==1)
  {
   // echo"success";
    $row =mysqli_fetch_assoc($result);
    //$stid = $row['student_id'];
    $title = $row['student_title'];
    $fname = $row['student_fullname'];
    $ininame = $row['student_ininame'];
    $gender = $row['student_gender'];
    $civil = $row['student_civil'];
    $email = $row['student_email'];
    $nic = $row['student_nic'];
    $dob = $row['student_dob'];
    $phone = $row['student_phone'];
    $address = $row['student_address'];
    $zip = $row['student_zip'];
    $district = $row['student_district'];
    $division = $row['student_divisions'];
    $province = $row['student_provice'];
    $blood = $row['student_blood'];
    $ename = $row['student_em_name'];
    $eaddress = $row['student_em_address'];
    $ephone = $row['student_em_phone'];
    $erelation = $row['student_em_relation'];
    if (isset($row['student_nationality'])) { $nationality = $row['student_nationality']; }
    if (isset($row['student_whatsapp'])) { $whatsapp = $row['student_whatsapp']; }
    $coid = $row['course_id'];
    $mode = $row['course_mode'];
    $year = $row['academic_year'];
    $enstatus =$row['student_enroll_status'];
    $enroll = $row['student_enroll_date'];
    $exit = $row['student_enroll_exit_date'];
  }
} 
   //delete coding
  

  // if(isset($_GET['edit']))
  // {
  //     $stid = $_GET['edit'];
  //     $sql = "DELETE FROM `student` WHERE `student_id`='$stid'";
  //     if(mysqli_query($con,$sql))
  //     {
  //         echo "Student Personal Recorde Delete Successfully";
  //     }
  //     else
  //     {
  //     echo "Error Deleteing Record: ". mysqli_error($con);
  //     }
  // }


  //Insert coding
  
  if(isset($_POST['Submit']))
  {
      // echo "welcome Edit"; echo 'ini_name'.$_POST['ini_name'];  echo 'gender'.$_POST['gender'];echo 'civil'.$_POST['civil'];echo 'email'.$_POST['email'];
      // echo 'sid'.$_POST['sid']; echo 'nic'.$_POST['nic'];echo 'dob'.$_POST['dob']; echo 'phone'.$_POST['phone'];echo 'address'.$_POST['address'];echo 'relation'.$_POST['relation'];
      // echo 'title'.$_POST['title'];echo 'zip'.$_POST['zip'];echo 'ds'.$_POST['ds'];echo 'province'.$_POST['province'];echo 'blood'.$_POST['blood'];
      // echo 'fullname'.$_POST['fullname'];echo 'district'.$_POST['district']; echo 'Ename'.$_POST['Ename']; echo 'addressE'.$_POST['addressE']; echo 'Ephone'.$_POST['Ephone'];
  
    if(!empty($_POST['sid']) && !empty($_POST['title']) && !empty($_POST['fullname']) && !empty($_POST['ini_name']) && !empty($_POST['gender']) && !empty($_POST['civil']) 
    &&!empty($_POST['email']) && !empty($_POST['nic']) && !empty($_POST['dob']) && !empty($_POST['phone']) && !empty($_POST['address']) && !empty($_POST['zip']) && !empty($_POST['district']) 
    &&!empty($_POST['ds']) && !empty($_POST['province']) && !empty($_POST['blood']) && !empty($_POST['Ename']) && !empty($_POST['addressE']) && !empty($_POST['Ephone']) 
    &&!empty($_POST['relation']))
    
     {
      echo "SUCCESS";
       $stid=$_POST['sid'];
       $title=$_POST['title'];
       $fname=$_POST['fullname'];
       $ininame=$_POST['ini_name'];
       $gender=$_POST['gender'];
       $civil=$_POST['civil'];
       $email=$_POST['email'];
       $email =($_POST["email"]);
          // check if e-mail address is well-formed
       if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
       {
           $emailErr = "Invalid email format";
       }
       $nic=$_POST['nic'];
       $dob=$_POST['dob'];
       $phone=$_POST['phone'];
        $whatsapp = isset($_POST['whatsapp']) ? $_POST['whatsapp'] : null;
        $address=$_POST['address'];
        $zip=$_POST['zip'];
        $district=$_POST['district'];
        $division=$_POST['ds'];
        $province=$_POST['province'];
        $nationality = isset($_POST['nationality']) ? $_POST['nationality'] : null;
        $blood=$_POST['blood'];
        $ename=$_POST['Ename'];
        $eaddress=$_POST['addressE'];
        $ephone=$_POST['Ephone'];
        $erelation=$_POST['relation'];
        
        $sqlstudent = "INSERT INTO `student`(`student_id`, `student_title`, `student_fullname`, `student_ininame`, `student_gender`, `student_civil`, 
        `student_email`, `student_nic`, `student_dob`, `student_phone`, `student_whatsapp`, `student_address`, `student_zip`, `student_district`, `student_divisions`, 
        `student_provice`, `student_blood`, `student_nationality`, `student_em_name`, `student_em_address`, `student_em_phone`, `student_em_relation`) VALUES 
        ('$stid','$title','$fname','$ininame','$gender','$civil','$email','$nic','$dob','$phone',".($whatsapp?"'".mysqli_real_escape_string($con,$whatsapp)."'":"NULL").",'$address','$zip','$district','$division','$province',
        ".($nationality?"'".mysqli_real_escape_string($con,$nationality)."'":"NULL").",'$blood','$ename','$eaddress','$ephone','$erelation')";

              if(mysqli_query($con,$sqlstudent))
              {
                echo "Record Insert Successfully";
              }
              else
              {
                echo "Error: ".$sqlstudent . "<br>" . mysqli_error($con);
                echo "Fill the required field";
              }
            }
    }


if(isset($_POST['Submit']))
  {
    if(!empty($_POST['sid']) && !empty($_POST['cid']) && !empty($_POST['mode']) && !empty($_POST['ayear']) && !empty($_POST['status']) && !empty($_POST['enrolldate']) && !empty($_POST['exitdate']))
    {
        echo "SUCCESS";
        $stid=$_POST['sid'];
        $coid=$_POST['cid'];
        $mode=$_POST['mode'];
        $year=$_POST['ayear'];
        $enstatus=$_POST['status'];
        $enroll=$_POST['enrolldate'];
        $exit=$_POST['exitdate'];

          $sqlenroll = "INSERT INTO `student_enroll`(`student_id`, `course_id`, course_mode,`academic_year`, `student_enroll_date`, `student_enroll_exit_date`, 
          `student_enroll_status`) VALUES ('$stid','$coid','$mode','$year','$enroll','$exit','$enstatus')";

            if(mysqli_query($con,$sqlenroll))
            {
              echo "Record Insert Successfully";
            }
            else
            {
            // echo "Error: ".$sqlstudent . "<br>" . mysqli_error($con);
              echo "Error: ".$sqlenroll . "<br>" . mysqli_error($con);
              //echo "Error: ".$sqlqualification . "<br>" . mysqli_error($con);
              echo "Fill the required field";
            }
    }
  }
  


  //update coding FOR STUDENT
if(isset($_POST['Edit']))
     {
      // echo "welcome Edit"; echo 'sid'.$_POST['sid']; echo 'cid'.$_POST['cid']; echo 'ayear'.$_POST['ayear']; echo 'enrolldate'.$_POST['enrolldate'];
      // echo 'exitdate'.$_POST['exitdate']; echo 'title'.$_POST['title'];echo 'fullname'.$_POST['fullname'];echo 'ini_name'.$_POST['ini_name']; echo 'gender'.$_POST['gender'];
      // echo 'civil'.$_POST['civil'];echo 'exitdate'.$_POST['exitdate'];echo 'email'.$_POST['email'];echo 'nic'.$_POST['nic'];echo 'dob'.$_POST['dob'];
      // echo 'phone'.$_POST['phone'];echo 'address'.$_POST['address'];echo 'zip'.$_POST['zip'];echo 'ds'.$_POST['ds'];echo 'province'.$_POST['province'];
      // echo 'blood'.$_POST['blood'];echo 'qualification'.$_POST['qualification'];echo 'indexno'.$_POST['indexno'];echo 'indexno'.$_POST['indexno'];
      // echo 'indexno'.$_POST['indexno'];echo 'yoe'.$_POST['yoe'];echo 'subject'.$_POST['subject'];echo 'subject'.$_POST['subject']; echo 'district'.$_POST['district'];
      // echo 'result'.$_POST['result'];echo 'Ename'.$_POST['Ename'];echo 'addressE'.$_POST['addressE']; echo 'Ephone'.$_POST['Ephone']; echo 'relation'.$_POST['relation'];
       if (isset($_GET['edit']) && $_GET['edit'] !== '')
        {
         //echo "SUCCESS";
         $stid=$_GET['edit'];
         $title=$_POST['title'];
         $fname=$_POST['fullname'];
         $ininame=$_POST['ini_name'];
         $gender=$_POST['gender'];
         $civil=$_POST['civil'];
         $email=$_POST['email'];
         $nic=$_POST['nic'];
         $dob=$_POST['dob'];
         $phone=$_POST['phone'];
         $whatsapp = isset($_POST['whatsapp']) ? $_POST['whatsapp'] : null;
         $address=$_POST['address'];
         $zip=$_POST['zip'];
         $district=$_POST['district'];
         $division=$_POST['ds'];
         $province=$_POST['province'];
         $nationality = isset($_POST['nationality']) ? $_POST['nationality'] : null;
         $blood=$_POST['blood'];
         $ename=$_POST['Ename'];
         $eaddress=$_POST['addressE'];
         $ephone=$_POST['Ephone'];
         $erelation=$_POST['relation'];

         $sql1 = "UPDATE `student` SET `student_title`='$title',`student_fullname`='$fname',`student_ininame`='$ininame',`student_gender`='$gender',
         `student_civil`='$civil',`student_email`='$email',`student_nic`='$nic',`student_dob`='$dob',`student_phone`='$phone',`student_whatsapp`=".(
            $whatsapp?"'".mysqli_real_escape_string($con,$whatsapp)."'":"NULL").",`student_address`='$address',
         `student_zip`='$zip',`student_district`='$district',`student_divisions`='$division',`student_provice`='$province',`student_blood`='$blood',
         `student_nationality`=".( $nationality?"'".mysqli_real_escape_string($con,$nationality)."'":"NULL").",`student_em_name`='$ename',`student_em_address`='$eaddress',`student_em_phone`='$ephone',`student_em_relation`='$erelation' WHERE student_id = '$stid'";
        
    
            if(mysqli_query($con,$sql1))
            {
              echo "Record Updated Successfully";
            }
            else
            {
              echo "Error: ".$sql1 . "<br>" . mysqli_error($con);
              echo "Fill the required field";
            }
          }
        }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////       
?>
<div class="ROW">
     <div class="col text-center shadow p-5 mb-5 bg-white rounded ">
         <h1 style="text-align:center"> STUDENTS' REGISTRATION FORM </h1>
         <h5 style="text-align:center"> SRI LANKA GERMAN TRAINING INSTITUTE</h5>
     </div>
</div><br>

<div class="container">
<form class="needs-validation" action="" method="POST">

<div class="form-row">
          <div class class="col-md-1"></div>
          <div class="col">
          <h2 style="font-size: 20px; font-weight: 700; border-bottom: 2px solid #aaa;"> Enroll Information </h2>
          </div>  
</div><br>

    <div class="form-row">
        <div class="col-md-6 mb-3">
          <label for="cid"> Course Name: </label>
          <select name="cid" id="cid" class="custom-select" value="<?php echo $coid; ?>">
          <option selected disabled> ........select the Course .......</option>
              <?php 
                $sql="SELECT * from course";
                $result = mysqli_query($con,$sql);
                if(mysqli_num_rows($result)>0)
                while($row = mysqli_fetch_assoc($result)) 
                {
                echo '<option value="'.$row['course_id'].'"';
                if ($row["course_id"]==$coid)
                {
                  echo 'selected'; 
                }
                echo '>'.$row['course_name'].'</option>';
                }
              ?> 
          </select>
        </div>

        <div class="col-md-3 mb-3">
          <label for="ayear"> Academic Year: </label>
          <select name="ayear" id="ayear" class="selectpicker show-tick" data-live-search="true" data-width="100%" value="<?php echo $year; ?>" required>
          <option selected disabled>--Academic Year--</option>
          <?php
            $sql = "SELECT * FROM `academic` ORDER BY `academic_year` DESC ";
            $result = mysqli_query($con, $sql);
            if (mysqli_num_rows($result) > 0) 
            while($row = mysqli_fetch_assoc($result)){
            echo '<option  value="'.$row ['academic_year'].'" data-subtext="'.$row ['academic_year_status'].'"';
            if($row ["academic_year"] == $year)
            {
              echo 'selected';
            }
            echo '>'.$row ['academic_year'].'</option>';
            }
            
            ?> 
          </select>
        </div>

        <div class="col-md-2 mb-3">
          <label for="mode"> Course Mode: </label>
          <select name="mode" id="mode" class="custom-select" value="<?php echo $mode; ?>" required>
            <option selected disabled> Course Mode </option>
              <option value="Full" <?php if($mode=="Full") echo 'selected';?>>Full Time</option> 
              <option value="Part" <?php if($mode == "Part") echo 'selected';?>>Part Time</option>
         </select>
         </div>
    </div>

    <div class="form-row">

        <div class="col-md-3 mb-3">
          <label for="sid">Student ID:</label>
          <input type="text" class="form-control" name="sid" 
          value="<?php echo $stid; 
          // if(isset($_GET['edit']))
          // {
          //   echo $stid; 
          // }
          // echo $year =$_GET['ayear'];
          //   echo $coid =$_GET['coid'];
          // else ((isset($_GET['ayear'])) && (isset($_GET['coid'])))
          // {
          //   echo $Sql="SELECT MAX(student_id) from student_enroll where `course_id`='$coid' and`academic_year`='$year' ORDER by student_id ASC";
          //   $result = mysqli_query($con,$sql);

          //   if(mysqli_num_rows($result)==1)
          //   {
          //     $row =mysqli_fetch_assoc($result);
          //     $stid = $row['student_id'];
          //   }
          //   else
          //   {
          //     $stid = $row['student_id'];
          //   }
          // }
          ?>" id="sid" required>
        </div>

        <div class="col-md-3 mb-3">
          <label for="status">Status:</label>
          <select name="status" id="status" class="custom-select" value="<?php echo $enstatus; ?>" required>
            <option selected disabled>Choose Status</option>
              <option value="Following" <?php if($enstatus=="Following")  echo 'selected';?>>Following</option> 
              <option value="Completed" <?php if($enstatus == "Completed") echo ' selected';?>>Completed</option>
              <option value="Dropout"<?php if($enstatus=="Dropout") echo 'selected';?>>Dropout</option>
              <option value="Long Absent"<?php if($enstatus=="Long Absent") echo 'selected';?>>Long Absent</option>
          </select>
        </div>

        <div class="col-md-3 mb-3">
          <label for="enrolldate">Enroll Date:</label>
          <input type="date" class="form-control" value="<?php echo $enroll; ?>" id="enrolldate" name="enrolldate" placeholder="" aria-describedby="enrolldatePrepend" required>
        </div>

        <div class="col-md-3 mb-3">
          <label for="exitdate">Exit Date:</label>
          <input type="date" class="form-control" value="<?php echo $exit; ?>" id="exitdate" name="exitdate" placeholder="" aria-describedby="exitdatePrepend" required>
        </div>
    </div>

    <div class="form-row">
          <div class class="col-md-1"></div>
          <div class="col">
          <h2 style="font-size: 20px; font-weight: 700; border-bottom: 2px solid #aaa;"> Personal Information </h2>
          </div>  
    </div><br>
                      
    <div class="form-row">
          <div class="col-md-2 mb-3">
          <label for="title"> Title: </label>
          <select name="title" id="title" class="custom-select" value="<?php echo $title; ?>" required>
            <option selected disabled>Choose Title</option>
              <option value="Mr" <?php if($title=="Mr") echo 'selected';?>>Mr</option> 
              <option value="Miss" <?php if($title == "Miss") echo 'selected';?>>Miss</option>
              <option value="Mrs"<?php if($title=="Mrs") echo 'selected';?>>Mrs</option>
         </select>
         </div>
              
        <div class="col-md-10 mb-3">
          <label for="fullname"> Full Name: </label>
          <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo $fname; ?>" placeholder="" aria-describedby="fullnamePrepend" required>
        </div>

    </div>

    <div class="form-row">
        <div class="col-md-7 mb-3">
          <label for="ini_name"> Name with Initials: </label>
          <input type="text" class="form-control" id="ini_name" name="ini_name" value="<?php echo $ininame; ?>" placeholder="" required>
        </div>

        <div class="col-md-2 mb-3">
            <label for="custom-select"> Gender: </label>
            <select name="gender" id="gender" class="form-control" value="<?php echo $gender; ?>" required>
              <option selected disabled>Choose Gender</option>
              <option value="Male"<?php if($gender=="Male")  echo 'selected';?>>Male</option>
              <option value="Female"<?php if($gender=='Female') echo ' selected';?>>Female</option>
            </select>
        </div>

        <div class="col-md-3 mb-3">
            <label for="civil"> Civil Status: </label>
            <select name="civil" id="civilstatus" class="custom-select" value="<?php echo $civil; ?>" required>
            <option selected disabled>Choose Status</option>
              <option value="Single"<?php if($civil=="Single")  echo 'selected';?>>Single</option> 
            <option value="Married"<?php if($civil=="Married") echo ' selected';?> >Married</option>
          </select>
      </div>
    </div>
  
    <div class="form-row">
      <div class="col-md-4 mb-3">
        <label for="nationality"> Nationality: </label>
        <input type="text" class="form-control" id="nationality" name="nationality" value="<?php echo $nationality; ?>" placeholder="e.g. Sri Lankan">
      </div>
    </div>
  
    <div class="form-row">
          
          <div class="col-md-4 mb-3">
            <label for="email"> Email: </label>
            <div class="input-group-prepend">
            <div class="input-group-text">@</div>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
              {
               $emailErr = "Invalid email format";
              } ?>" placeholder="nimal89@gmail.com"  required>
            </div> 
          </div>

          <div class="col-md-3 mb-3">
            <label for="nic"> NIC: </label>
            <input type="text" class="form-control" id="nic" name="nic" max="12" min="10" value="<?php echo $nic; ?>" placeholder=""  required>
          </div>
          
          <div class="col-md-2 mb-3">
            <label for="dob"> Date of Birth: </label>
            <input type="date" class="form-control" id="dob" name="dob" value="<?php echo $dob; ?>" placeholder=""  required>
          </div>

          <div class="col-md-3 mb-3">
            <label for="phone"> Phone No: </label>
            <input type="number" class="form-control" id="phone" name="phone" maxlength="10" minlength="10" value="<?php echo $phone; ?>" placeholder=""  required>
          </div>
    </div>    
    
    <div class="form-row">
      <div class="col-md-3 mb-3">
        <label for="whatsapp"> WhatsApp No: </label>
        <input type="text" class="form-control" id="whatsapp" name="whatsapp" value="<?php echo $whatsapp; ?>" placeholder="e.g. +94XXXXXXXXX">
      </div>
    </div>
    
    <div class="form-row"> 
          <div class="col-md-12 mb-3">
            <label for="address"> Address: </label>
            <input type="textarea" class="form-control" id="address" name="address" value="<?php echo $address; ?>" placeholder="No, Street, Hometown."  required>
          </div>
    </div>
 
    <div class="form-row">
          <div class="col-md-2 mb-3">
            <label for="zip"> ZIP-Code:</label>
            <input type="number" class="form-control" id="zip" name="zip" value="<?php echo $zip; ?>" placeholder=""  required>
          </div>
          
          <div class="col-md-2 mb-3">
            <label for="district"> District: </label>
            <select name="district" id="district" class="show-tick selectpicker" data-live-search="true" data-width="100%" value="<?php echo $district; ?>" required>
                <option value="">Select</option>
                <option value="Ampara"<?php if($district=="Ampara")  echo 'selected';?>> Ampara </option>
                <option value="Batticalo"<?php if($district=="Batticalo")  echo 'selected';?>> Batticalo </option>
                <option value="Trincomalee"<?php if($district=="Trincomalee")  echo 'selected';?>> Trincomalee </option>
                <option value="Jaffna"<?php if($district=="Jaffna")  echo 'selected';?>> Jaffna </option>
                <option value="Vavuniya"<?php if($district=="Vavuniya")  echo 'selected';?>> Vavuniya </option>
                <option value="Killinochchi"<?php if($district=="Killinochchi")  echo 'selected';?>> Killinochchi  </option>
                <option value="Mullaitivu"<?php if($district=="Mullaitivu")  echo 'selected';?>> Mullaitivu </option>
                <option value="Mannar"<?php if($district=="Mannar")  echo 'selected';?>> Mannar </option>
                <option value="Puttalam"<?php if($district=="Puttalam")  echo 'selected';?>> Puttalam </option>
                <option value="Kurunegala"<?php if($district=="Kurunegala")  echo 'selected';?>> Kurunegala </option>
                <option value="Gampaha"<?php if($district=="Gampaha")  echo 'selected';?>> Gampaha </option>
                <option value="Colombo"<?php if($district=="Colombo")  echo 'selected';?>> Colombo </option>
                <option value="Kalutara"<?php if($district=="Kalutara")  echo 'selected';?>> Kalutara </option>
                <option value="Anuradhapura"<?php if($district=="Anuradhapura")  echo 'selected';?>> Anuradhapura </option>
                <option value="Polonnaruwa"<?php if($district=="Polonnaruwa")  echo 'selected';?>> Polonnaruwa </option>
                <option value="Matale"<?php if($district=="Matale")  echo 'selected';?>> Matale	 </option>
                <option value="Kandy"<?php if($district=="Kandy")  echo 'selected';?>> Kandy </option>
                <option value="Nuwara Eliya"<?php if($district=="Nuwara Eliya")  echo 'selected';?>> Nuwara Eliya </option>
                <option value="Kegalle"<?php if($district=="Kegalle")  echo 'selected';?>> Kegalle </option>
                <option value="Ratnapura"<?php if($district=="Ratnapura")  echo 'selected';?>> Ratnapura </option>
                <option value="Badulla"<?php if($district=="Badulla")  echo 'selected';?>> Badulla </option>
                <option value="Monaragala"<?php if($district=="Monaragala")  echo 'selected';?>> Monaragala </option>
                <option value="Hambantota"<?php if($district=="Hambantota")  echo 'selected';?>> Hambantota </option>
                <option value="Matara"<?php if($district=="Matara")  echo 'selected';?>> Matara </option>
                <option value="Galle"<?php if($district=="Galle")  echo 'selected';?>> Galle </option>
            </select>
          </div>

          <div class="col-md-2 mb-3">
            <label for="ds"> Divisional Secretariat: </label>
            <input type="text" name="ds" class="form-control" id="ds" value="<?php echo $division; ?>" placeholder=""  required>
          </div>
          
          <div class="col-md-2 mb-3">
            <label for="province"> Province: </label>
            <select name="province" id="province" class="custom-select" value="<?php echo $province; ?>" required>
                <option value="">Select</option>
                <option value="1"<?php if($province=="1")  echo 'selected';?>> Northen </option>
                <option value="2"<?php if($province=="2")  echo 'selected';?>> Eastern </option>
                <option value="3"<?php if($province=="3")  echo 'selected';?>> Western </option>
                <option value="4"<?php if($province=="4")  echo 'selected';?>> Southern </option>
                <option value="5"<?php if($province=="5")  echo 'selected';?>> Central </option>
                <option value="6"<?php if($province=="6")  echo 'selected';?>> North Western </option>
                <option value="7"<?php if($province=="7")  echo 'selected';?>> Uva </option>
                <option value="8"<?php if($province=="8")  echo 'selected';?>> North Central </option>
                <option value="9"<?php if($province=="9")  echo 'selected';?>> Sabaragamuwa </option>
            </select>
          </div>

          <div class="col-md-2 mb-3">
            <label for="blood"> Blood Group: </label>
            <select name="blood" id="blood" class="custom-select" value="<?php echo $blood; ?>" required>
              <option selected disabled> Blood Group </option>
              <option value="A+"<?php if($blood=="A+")  echo 'selected';?>> A+ </option>
              <option value="A-"<?php if($blood=="A-")  echo 'selected';?>> A- </option>
              <option value="B+"<?php if($blood=="B+")  echo 'selected';?>> B+ </option>
              <option value="B-"<?php if($blood=="B-")  echo 'selected';?>> B- </option>
              <option value="C+"<?php if($blood=="C+")  echo 'selected';?>> O+ </option>
              <option value="C-"<?php if($blood=="C-")  echo 'selected';?>> O- </option>
              <option value="AB+"<?php if($blood=="AB+")  echo 'selected';?>> AB+ </option>
              <option value="AB-"<?php if($blood=="AB-")  echo 'selected';?>> AB- </option> 
            </select>
        </div>

    </div>
  
    <div class="form-row">
          <div class class="col-md-1">
          </div>
          <div class="col">
          <h2 style="font-size: 20px; font-weight: 700; border-bottom: 2px solid #aaa;"> Educational Qualification </h2>
          </div>  
    </div><br>

  <div class="form-row">

      <div class="col-md-2 mb-3 ">
            <label for="qualification"> Qualification Type: </label>
            <!-- <input name="qualification" id="qualification" class="form-control" type="text" value="" > -->

            <select name="qualification" id="qualification" class="custom-select" value="<?php echo $qutype; ?>" required>
              <option value="null">-Qualification-</option>
              <option value="G.C.EO/L">G.C.EO/L</option>
              <option value="G.C.EA/L">G.C.EA/L</option>
              <option value="NVQ3">NVQ3</option>
              <option value="NVQ4">NVQ4</option>
              <option value="NVQ5">NVQ5</option>
            </select>
        </div>

        <div class="col-md-3 mb-3 ">
            <label for="indexno"> Index No: </label>
            <input type="text" name="indexno" id="indexno" class="form-control"  value="<?php echo $index; ?>" required>
        </div>
                            
        <div class="col-md-2 mb-3">
            <label  for="examy"> Year of Exam: </label>
            <input  type="text" name="examy" id="examy" class="form-control" value="<?php echo $yoe; ?>" required>
        </div>

        <div class="col-md-5 mb-3">
            <label  for="subject"> Subject/Name of the Course: </label>
            <input type="text" name="subject" id="subject" class="form-control"  value="<?php echo $subject; ?>" required>
        </div>

        <div class="col-md-2 mb-3">
            <label  for="result"> Result: </label>
            <input type="text" class="form-control" id="result" name="result" placeholder="" value="<?php echo $results; ?>"  required>
        </div>

        <div class="col-md-6 mb-3"></div>
        
        <div class="btn-group-horizontal col-md-3 mb-3"> 
           <button type="submit" style="margin-top:35px" value="UPDATEQ" name="UPDATEQ" class="btn btn-primary mr-2"><i class="fas fa-user-edit"></i>EDIT</button>  
           <button type="submit" value="qualAdd" style="margin-top:35px" name="qualAdd"  class="btn btn-primary mr-2" style="float:right"><i class="fas fa-user-plus"></i>ADD</button>
        </div>
  
    </div>
        
        <div class="form-row">
        <div id="results-student_education" class="form-group table-responsive">               
            <table class="table table-hover" width="100%" id="table">
              <thead>
              <tr>
              <th width="15%"> Qualification Type </th>
              <th width="20%"> Index No </th>
              <th width="15%"> Year of Exam </th>
              <th width="30%"> Subject </th>
              <th width="10%"> Result </th>
              <th width="10%"> Action </th>
              </tr>
              </head>
              <tbody>
              <?php
               if(isset($_GET['edit']))
               {
                  //$stid =$_GET['edit'];WHERE `qualification_student_id`= '$stid'"
                  //include_once("mysqli_connect.php");
                  $stid =$_GET['edit'];
                  $sql ="SELECT `qualification_type`,`qualification_index_no`,`qualification_year`,`qualification_description`,`qualification_results` 
                  FROM `student_qualification` where `qualification_student_id`= '$stid' ";
                  $result = mysqli_query ($con, $sql);
                  if (mysqli_num_rows($result)>0)
                  {
                    while($row = mysqli_fetch_assoc($result))
                    {
                      echo '
                      <tr style="text-align:left";>
                          <td>'. $row["qualification_type"].'</td>
                          <td>'. $row["qualification_index_no"].'</td>
                          <td>'. $row["qualification_year"].'</td>
                          <td>'. $row["qualification_description"].'</td>
                          <td>'. $row["qualification_results"].'</td>
                          <td>
                          <a href="?DELETE='.$stid.'&quali='.$row["qualification_description"].'&deinde='.$row["qualification_index_no"].'"class="btn btn-outline-danger btn-icon-split"""> <i class="far fa-trash-alt"></i> &nbsp;&nbsp;</a>
                          </td>
                      </tr> ';
                    }
                  }
                  else
                  {
                    echo "0 results";
                  }
               }
               ?>
              </tbody>
            </table>  
      </div>
      </div>
        
        <script>
                var table = document.getElementById('table');
                
                for(var i = 1; i < table.rows.length; i++)
                {
                    table.rows[i].onclick = function()
                    {
                         document.getElementById("qualification").value = this.cells[0].innerText;
                         document.getElementById("indexno").value = this.cells[1].innerText;
                         document.getElementById("examy").value = this.cells[2].innerText;
                         document.getElementById("subject").value = this.cells[3].innerText;
                         document.getElementById("result").value = this.cells[4].innerText;
                    };
                }
    
         </script>
         <?php
         $qutype = $stid = $index = $yoe = $subject = $result = null;
         // QUALIFICATION INSERT AND UPDATE
         if(isset($_POST['qualAdd']))
         {
         // echo "welcome";
         // echo 'sid'.$_POST['sid']; echo 'examy'.$_POST['examy']; echo 'subject'.$_POST['subject']; echo 'result'.$_POST['result'];
         // echo 'qualification'.$_POST['qualification']; echo 'indexno'.$_POST['indexno'];

           if(!empty($_POST['sid']) &&!empty($_POST['examy']) &&!empty($_POST['subject']) &&!empty($_POST['result'])
           &&!empty($_POST['qualification'])  &&!empty($_POST['indexno']))
           {
             echo "SUCCESS";
               $stid=$_POST['sid'];
               $qutype=$_POST['qualification'];
               $index=$_POST['indexno']; 
               $yoe=$_POST['examy'];
               $subject=$_POST['subject'];
               $result=$_POST['result'];
           
                 $sqlqualification = "INSERT INTO `student_qualification`(`qualification_student_id`, `qualification_type`, `qualification_index_no`, `qualification_year`, 
                 `qualification_description`, `qualification_results`) VALUES  ('$stid','$qutype','$index','$yoe','$subject','$result')";

                     if(mysqli_query($con,$sqlqualification))
                     {
                       echo "Record Insert Successfully";
                     }
                     else
                     {
                       echo "Error: ".$sqlqualification . "<br>" . mysqli_error($con);
                     // echo "Fill the required field";
                     }
                   }
           }

           if(isset($_POST['UPDATEQ']))
             {
               // ECHO 'qualification'.$_POST['qualification']; ECHO 'examy'.$_POST['examy']; ECHO 'result'.$_POST['result'];
               // ECHO 'indexno'.$_POST['indexno']; ECHO 'subject'.$_POST['subject']; 

               if(!empty($_POST['qualification']) && !empty($_POST['examy']) && !empty($_POST['result'])
               && !empty($_POST['indexno']) && !empty($_POST['subject']))
               {
               "SUCCESS";
               //$stid=$_GET['stid'];
               $index=$_POST['indexno'];
               $subject=$_POST['subject'];
               $yoe=$_POST['examy'];
               $qutype=$_POST['qualification'];
               $result=$_POST['result'];

               $sql3 = "UPDATE `student_qualification` SET `qualification_type`='$qutype',`qualification_year`='$yoe',`qualification_results`='$result'
                WHERE`qualification_index_no`='$index' AND `qualification_description`='$subject'";

                   if(mysqli_query($con,$sql3))
                   {
                     echo "Upadate Successfully";
                   }
                   else
                   {
                     echo "Error: ".$sql . "<br>" . mysqli_error($con);
                   }
                 }
           }

          if((isset($_GET['quali'])) && (isset($_GET['deinde'])))
          {
            $index =$_GET['deinde'];
            $subject=$_GET['quali'];
            $sql = "DELETE FROM `student_qualification` WHERE `qualification_description`='$subject' and `qualification_index_no`='$index'";
             if(mysqli_query($con,$sql))
             {
                 echo "Student Qualification Recorde Delete Successfully";
             }
             else
             {
             echo "Error Deleteing Record: ". mysqli_error($con);
             }
           }
         ?>


    <div class="form-row">
          <div class class="col-md-1">
          </div>
          <div class="col">
          <h2 style="font-size: 20px; font-weight: 700; border-bottom: 2px solid #aaa;">Emergency Contact Information</h2>
          </div>  
    </div><br>

    <div class="form-row">
        <div class="col-md-12 mb-3">
          <label for="Ename">Name :</label>
          <input type="text" class="form-control" id="Ename" name="Ename" value="<?php echo $ename; ?>" placeholder=""  required>
        </div>
    </div>
    
    <div class="form-row">
        <div class="col-md-12 mb-3">
          <label for="addressE">Address :</label>
          <input type="text" class="form-control" id="addressE" name="addressE" value="<?php echo $eaddress; ?>" placeholder="" required>
        </div>
    </div>
    
    <div class="form-row">
        <div class="col-md-3 mb-3">
          <label for="Ephone">Phone No :</label>
          <input type="number" class="form-control" id="Ephon" maxlength="10" minlength="10" name="Ephone" value="<?php echo $ephone; ?>" placeholder=""  required>
        </div>
    
        <div class="col-md-3 mb-3">
          <label for="relation">Relationship :</label>
          <select name="relation" id="relation" value="<?php echo $erelation; ?>" class="custom-select" >
              <option value="">Select</option>
              <option value="mother" <?php if($erelation=="mother") echo 'selected' ?>> Mother </option>
              <option value="father" <?php if($erelation=="father") echo 'selected' ?>> Father </option>
              <option value="guardian" <?php if($erelation=="guardian") echo 'selected' ?>> Guardian </option>
        </select>
        </div>
    </div>
<div class="form-row pt-3">
<?php
echo '<div class="btn-group-horizontal">';

if(isset($_GET['edit']))
{
  echo '<button type="submit" value="Edit" name="Edit" class="btn btn-primary mr-2"><i class="fas fa-user-edit"></i>UPDATE</button>';
  echo '<button class="btn btn-sm btn-danger" value="delete" name="delete" data-href="?delete='.$row["student_id"].'" data-toggle="modal" data-target="#confirm-delete"> <i class="fas fa-trash"></i> </button>';
}
else
{
  echo '<button type="submit" value="Submit" name="Submit"  class="btn btn-primary mr-2"><i class="fas fa-user-plus"></i>ADD</button>';
  echo'<button type="reset" value="Reset" class="btn btn-primary mr-2"><i class="fas fa-redo"></i>REFRESH</button>';
}
echo '</div>';
?>
</div>
</form>
</div>




<!---BLOCK 03--->
<!----DON'T CHANGE THE ORDER--->
<?php 
if (isset($con)) {
    mysqli_close($con);
}
include_once("../footer.php"); 
?>