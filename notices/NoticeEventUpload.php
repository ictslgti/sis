<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "notices | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

$isSTU = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU';
if ($isSTU) {
    header('Location: ../dashboard/index.php');
    exit;
}

$eid = $e_name = $e_venue=$e_date=$e_chief_guest=$e_comment = $e_time = $e_d_url = $e_sta =null;
if(isset($_GET['edit'])){
    $eid = $_GET['edit'];
    $sql = "SELECT * FROM `notice_event` WHERE `event_id` = $eid";
    $result = mysqli_query($con, $sql);
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $e_name = $row['event_name'];
        $e_venue = $row['event_venue'];
        $e_date = $row['event_date'];
        $e_chief_guest = $row['event_chief_guest'];
        $e_comment = $row['event_comment'];
        $e_time = $row['event_time'];
        $e_d_url = $row['event_docs_url'];
        $e_sta = $row['status'];
    }
}


?>

<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START YOUR CODE HERE -->



<form method="POST" enctype="multipart/form-data">

        <div class="row border border-light shadow p-3 mb-5 bg-white rounded">
                <div class="col">
                <br>
                <br>
                <blockquote class="blockquote text-center">
                    <h1 class="display-4 text-primary">Event Description Part</h1> 
                    <h4 class="mb-0 ">Srilanka German Training Institute</h4>
                    <h6 class="">This section to Add Details and Edit Details.<cite title="Source Title"></cite></h6>
                </blockquote>
                </div>
        </div>

        <!-- <a href="NoticeTable.php">View</a> -->
        

        <div class="input-group mb-3 ">
            <div class="input-group-prepend">
            <a href="NoticeTable.php"  name="add" class="btn btn-success" role="button" ><i class="fas fa-eye"></i>&nbsp;&nbsp;View</a> 
            </div>
        </div>
 
        <div class="input-group mb-3 ">
            <div class="input-group-prepend">
                <label class="input-group-text" for="inputGroupSelect01"> 
                <i class="fas fa-award text-primary"></i> </i>&nbsp;&nbsp;Event Name&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </div>
            <input type="text" class="form-control"  name="event_name" placeholder="Event Name" value="<?php echo $e_name; ?>" >
        </div>

        <div class="input-group mb-3 ">
            <div class="input-group-prepend">
                <label class="input-group-text" for="inputGroupSelect01"> 
                <i class="fas fa-map-marker-alt text-primary" ></i>  </i>&nbsp;&nbsp;Venue&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </div>
            <input type="text" class="form-control"  name="event_venue" placeholder="Venue" value="<?php echo $e_venue; ?>" >
        </div>

        <div class="input-group mb-3 ">
            <div class="input-group-prepend">
                <label class="input-group-text" for="inputGroupSelect01"> 
                <i class="far fa-calendar-alt text-primary"></i></i>&nbsp;&nbsp;Date&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </div>
            <input type="date" class="form-control" name="event_date"  value="<?php echo $e_date; ?>">
        </div>

        <div class="input-group mb-3 ">
            <div class="input-group-prepend">  
                <label class="input-group-text" for="inputGroupSelect01"> 
                    <i class="far fa-clock"> </i>&nbsp;&nbsp;Event Time&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </div>
                    <input class="form-control" type="time" name="event_time"  value="<?php echo $e_time; ?>">
        </div>
        
        <div class="input-group mb-3 ">
            <div class="input-group-prepend">
                <label class="input-group-text" for="inputGroupSelect01"> 
                    <i class="fas fa-user text-primary"> </i>&nbsp;&nbsp;chief Guest&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </div>
            <input type="text" class="form-control"  name="event_chief_guest" placeholder="chief Guest" value="<?php echo $e_chief_guest; ?>">
        </div>


        <div class="input-group mb-3 ">
            <div class="input-group-prepend">
                <label class="input-group-text" for="inputGroupSelect01"> 
                <i class="fab fa-audible text-primary" ></i> </i>&nbsp;&nbsp;Comment&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
            </div>
            <!-- <input type="text" class="form-control"  name="event_comment" placeholder="Comment" value="<?php //echo $e_comment; ?>"> -->
            
            <textarea class="form-control" rows="3" id="comment" name="event_comment"><?php echo $e_comment; ?></textarea>
            <!-- <textarea class="form-control"   placeholder="event_comment" name="event_comment" value=""></textarea> -->
        </div>

        <div class="col-12">
                <div class="form-group">
                <label> <i class="fas fa-university text-primary"> </i> Event</label>
                <select class="browser-default custom-select" name="status" id="Departmentx"  onchange="showCouese(this.value)" required>
                <option value="null" selected disabled >---- Select the Event---- </option>
                <?php
                $sql="select * from `notice_event_stutas`";
                $result = mysqli_query($con,$sql);
                if (mysqli_num_rows($result) > 0 ) {
                while($row=mysqli_fetch_assoc($result)){
                    echo '<option  value="'.$row["id"].'" required';
                    if($row["id"]== $e_sta) echo ' selected';
                    echo '>'.$row["status"].'</option>';
                }}   
                ?>
                </select>
                </div>

        </div>
    <div class="input-group mb-3 ">
        <div class="input-group-prepend">
            <label class="input-group-text" for="inputGroupSelect01"> 
            <i class="fas fa-plus text-primary"></i>  </i>&nbsp;&nbsp;Add File&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label>
        </div>
         <input type="file" name='ima' class="form-control" id="customFile">

    </div>

    <div class="input-group mb-3 ">
            <div class="input-group-prepend">
                <!-- <input type="submit"  value="ADD" name="add" class="btn btn-outline-primary"> -->
               
                <?php
                if(isset($_GET['edit'])){
                    echo ' <button type="submit" value="Add" name="update" class="btn btn-primary mr-2"><i class="fas fa-minus"></i>&nbsp;&nbsp;Edit New Event</button>';
                }else{
                    echo '<button type="submit" value="Add" name="add" class="btn btn-primary mr-2"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add New Event</button>';
                }
                ?>
            </div>
            <div class="input-group-prepend"> 
              
            </div>
            
        </div>
    
</form>


<?php

if(isset($_POST['add'])){
    if(!empty($_POST['event_name'])&&
     !empty($_POST['event_venue'])&&
      !empty($_POST['event_date'])&& 
      !empty($_POST['event_chief_guest'])&& 
      !empty($_POST['event_comment'])&&
      !empty($_POST['event_time'])&&
      !empty($_POST['status'])){
       $event_name=$_POST['event_name'];
       $event_venue=$_POST['event_venue'];
       $event_date=$_POST['event_date'];
       $event_chief_guest=$_POST['event_chief_guest'];
       $event_comment=$_POST['event_comment'];
       $event_time=$_POST['event_time'];
       $status=$_POST['status'];

       
       $t_name = $_FILES["ima"]["tmp_name"];
       $origName = basename($_FILES["ima"]["name"]);
       $test_dir = './docs/events';
       // Ensure destination exists
       if (!is_dir($test_dir)) { @mkdir($test_dir, 0775, true); }
       // Helper to compress to JPEG
       if (!function_exists('ne_compress_to_jpeg_file')) {
         function ne_compress_to_jpeg_file(string $srcPath, string $destPath, int $maxDim = 1600, int $quality = 82): bool {
           $info = @getimagesize($srcPath);
           if ($info === false) return false;
           $mime = strtolower($info['mime'] ?? '');
           switch ($mime) {
             case 'image/jpeg': $src = @imagecreatefromjpeg($srcPath); break;
             case 'image/png':  $src = @imagecreatefrompng($srcPath); break;
             case 'image/gif':  $src = @imagecreatefromgif($srcPath); break;
             case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false; break;
             default: $src = false; break;
           }
           if (!$src) return false;
           $sw = imagesx($src); $sh = imagesy($src);
           $scale = 1.0; $maxSide = max($sw, $sh);
           if ($maxSide > $maxDim) { $scale = $maxDim / $maxSide; }
           $dw = (int)max(1, round($sw * $scale));
           $dh = (int)max(1, round($sh * $scale));
           $dst = imagecreatetruecolor($dw, $dh);
           // Fill white for transparency
           if (in_array($mime, ['image/png','image/gif'], true)) {
             $white = imagecolorallocate($dst, 255,255,255);
             imagefilledrectangle($dst, 0,0, $dw,$dh, $white);
           }
           if (!imagecopyresampled($dst, $src, 0,0,0,0, $dw,$dh, $sw,$sh)) { imagedestroy($src); imagedestroy($dst); return false; }
           imagedestroy($src);
           $ok = imagejpeg($dst, $destPath, max(0, min(100, $quality)));
           imagedestroy($dst);
           return (bool)$ok;
         }
       }
       // Determine mime
       $mime = function_exists('mime_content_type') ? mime_content_type($t_name) : '';
       $isImage = is_string($mime) && preg_match('/^image\/(jpeg|png|gif|webp)$/i', $mime);
       if ($isImage) {
         $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
         if ($safeBase === '') { $safeBase = 'event_' . date('Ymd_His'); }
         $name = $safeBase . '.jpg';
         $destPath = rtrim($test_dir,'/\\') . '/' . $name;
         if (!ne_compress_to_jpeg_file($t_name, $destPath, 1600, 82)) {
           // Fallback to move original
           if (!@move_uploaded_file($t_name, $destPath)) {
             // Last fallback: write contents
             $data = @file_get_contents($t_name);
             if ($data !== false) { @file_put_contents($destPath, $data); }
           }
         }
       } else {
         // Non-image: keep original name
         $name = $origName;
         @move_uploaded_file($t_name, rtrim($test_dir,'/\\') . '/' . $name);
       }
       
       $sql="INSERT INTO `notice_event` (`event_name`,`event_venue`,`event_date`,`event_chief_guest`,`event_comment`,`event_time`,`event_docs_url`,`status`) values('$event_name','$event_venue','$event_date','$event_chief_guest','$event_comment','$event_time','$name','$status')";
       if(mysqli_query($con,$sql)){
           $message ="<h4 class='text-success' >New record created successfully</h4>";
           echo "'$message';";
       }else{
           echo "Error :-".$sql.
         "<br>"  .mysqli_error($con);
       }
   }
}

if(isset($_POST['update'])){
    if(!empty($_POST['event_name'])&&
    !empty($_POST['event_venue'])&&
    !empty($_POST['event_date'])&&
    !empty($_POST['event_chief_guest'])&&
    !empty($_POST['event_comment'])&&
    !empty($_POST['event_time'])&&
    !empty($_POST['status'])
    ){
          $event_id = $_GET['edit'];
          $event_name = $_POST['event_name'];
          $event_venue = $_POST['event_venue'];
          $event_date = $_POST['event_date'];
          $event_chief_guest = $_POST['event_chief_guest'];
          $event_comment = $_POST['event_comment'];
          $event_time = $_POST['event_time'];
          $status = $_POST['status'];
           
       $t_name = $_FILES["ima"]["tmp_name"];
       $origName = basename($_FILES["ima"]["name"]);
       $test_dir = './docs/events';
       if (!is_dir($test_dir)) { @mkdir($test_dir, 0775, true); }
       // Reuse helper if available; otherwise define
       if (!function_exists('ne_compress_to_jpeg_file')) {
         function ne_compress_to_jpeg_file(string $srcPath, string $destPath, int $maxDim = 1600, int $quality = 82): bool {
           $info = @getimagesize($srcPath);
           if ($info === false) return false;
           $mime = strtolower($info['mime'] ?? '');
           switch ($mime) {
             case 'image/jpeg': $src = @imagecreatefromjpeg($srcPath); break;
             case 'image/png':  $src = @imagecreatefrompng($srcPath); break;
             case 'image/gif':  $src = @imagecreatefromgif($srcPath); break;
             case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false; break;
             default: $src = false; break;
           }
           if (!$src) return false;
           $sw = imagesx($src); $sh = imagesy($src);
           $scale = 1.0; $maxSide = max($sw, $sh);
           if ($maxSide > $maxDim) { $scale = $maxDim / $maxSide; }
           $dw = (int)max(1, round($sw * $scale));
           $dh = (int)max(1, round($sh * $scale));
           $dst = imagecreatetruecolor($dw, $dh);
           if (in_array($mime, ['image/png','image/gif'], true)) {
             $white = imagecolorallocate($dst, 255,255,255);
             imagefilledrectangle($dst, 0,0, $dw,$dh, $white);
           }
           if (!imagecopyresampled($dst, $src, 0,0,0,0, $dw,$dh, $sw,$sh)) { imagedestroy($src); imagedestroy($dst); return false; }
           imagedestroy($src);
           $ok = imagejpeg($dst, $destPath, max(0, min(100, $quality)));
           imagedestroy($dst);
           return (bool)$ok;
         }
       }
       $mime = function_exists('mime_content_type') ? mime_content_type($t_name) : '';
       $isImage = is_string($mime) && preg_match('/^image\/(jpeg|png|gif|webp)$/i', $mime);
       if ($isImage) {
         $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
         if ($safeBase === '') { $safeBase = 'event_' . date('Ymd_His'); }
         $name = $safeBase . '.jpg';
         $destPath = rtrim($test_dir,'/\\') . '/' . $name;
         if (!ne_compress_to_jpeg_file($t_name, $destPath, 1600, 82)) {
           if (!@move_uploaded_file($t_name, $destPath)) {
             $data = @file_get_contents($t_name);
             if ($data !== false) { @file_put_contents($destPath, $data); }
           }
         }
       } else {
         $name = $origName;
         @move_uploaded_file($t_name, rtrim($test_dir,'/\\') . '/' . $name);
       }

         $sql =" UPDATE `notice_event` SET
         `event_name`='$event_name',
         `event_venue`='$event_venue',
         `event_date`='$event_date',
         `event_chief_guest`='$event_chief_guest',
         `event_comment`='$event_comment',
         `event_time`='$event_time',
         `event_docs_url`='$name',
         `status`='$status'

         WHERE `notice_event`.`event_id` = $event_id";
            if(mysqli_query($con,$sql)){
                $message ="<h4 class='text-success'>Old record Edited successfully</h4>" ;
                echo "$message";
            }else{
                echo "Error :-".$sql.
            "<br>"  .mysqli_error($con);
            }
            }
    }

?>



<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
<script>
function showCouese(val) {
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Course").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getCourse", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("department=" + val);
}

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

function showTeacher() {
    var did = document.getElementById("Departmentx").value;
    var cid = document.getElementById("Course").value;
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            document.getElementById("Teacher").innerHTML = this.responseText;
        }
    };
    xmlhttp.open("POST", "controller/getTeacher", true);
    xmlhttp.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xmlhttp.send("Department=" + did + "&Course="+ cid );
}
</script>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
