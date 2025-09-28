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



<div class="container-fluid py-3">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
          <div>
            <h5 class="mb-0">
              <i class="fas fa-bullhorn"></i>
              <?php echo isset($_GET['edit']) ? 'Edit Event' : 'Add Event'; ?>
            </h5>
            <small class="text-light">Sri Lanka German Training Institute</small>
          </div>
          <a href="../notices/NoticeTable.php" class="btn btn-success btn-sm"><i class="fas fa-eye"></i>&nbsp;View All</a>
        </div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
              <div class="form-group col-12 mb-3">
                <label class="font-weight-bold" for="event_name"><i class="fas fa-award text-primary"></i> Event Name</label>
                <input type="text" class="form-control" id="event_name" name="event_name" placeholder="Event Name" value="<?php echo $e_name; ?>">
              </div>

              <div class="form-group col-12 col-md-6 mb-3">
                <label class="font-weight-bold" for="event_venue"><i class="fas fa-map-marker-alt text-primary"></i> Venue</label>
                <input type="text" class="form-control" id="event_venue" name="event_venue" placeholder="Venue" value="<?php echo $e_venue; ?>">
              </div>

              <div class="form-group col-12 col-md-3 mb-3">
                <label class="font-weight-bold" for="event_date"><i class="far fa-calendar-alt text-primary"></i> Date</label>
                <input type="date" class="form-control" id="event_date" name="event_date" value="<?php echo $e_date; ?>">
              </div>

              <div class="form-group col-12 col-md-3 mb-3">
                <label class="font-weight-bold" for="event_time"><i class="far fa-clock text-primary"></i> Time</label>
                <input type="time" class="form-control" id="event_time" name="event_time" value="<?php echo $e_time; ?>">
              </div>

              <div class="form-group col-12 col-md-6 mb-3">
                <label class="font-weight-bold" for="event_chief_guest"><i class="fas fa-user text-primary"></i> Chief Guest</label>
                <input type="text" class="form-control" id="event_chief_guest" name="event_chief_guest" placeholder="Chief Guest" value="<?php echo $e_chief_guest; ?>">
              </div>

              <div class="form-group col-12 mb-3">
                <label class="font-weight-bold" for="event_comment"><i class="fab fa-audible text-primary"></i> Comment</label>
                <textarea class="form-control" id="event_comment" rows="3" name="event_comment" placeholder="Describe the event..."><?php echo $e_comment; ?></textarea>
              </div>

              <div class="form-group col-12 col-md-6 mb-3">
                <label class="font-weight-bold" for="Departmentx"><i class="fas fa-university text-primary"></i> Event Type</label>
                <select class="custom-select" name="status" id="Departmentx" required>
                  <option value="null" disabled <?php echo ($e_sta===null)?'selected':''; ?>>-- Select the Event --</option>
                  <?php
                  $sql="select * from `notice_event_stutas`";
                  $result = mysqli_query($con,$sql);
                  if (mysqli_num_rows($result) > 0 ) {
                    while($row=mysqli_fetch_assoc($result)){
                      echo '<option value="'.$row["id"].'"';
                      if($row["id"]== $e_sta) echo ' selected';
                      echo '>'.htmlspecialchars($row["status"]).'</option>';
                    }
                  }
                  ?>
                </select>
              </div>

              <div class="form-group col-12 col-md-6 mb-3">
                <label class="font-weight-bold" for="customFile"><i class="fas fa-plus text-primary"></i> Add Image/File</label>
                <input type="file" name="ima" class="form-control" id="customFile">
                <?php if (!empty($e_d_url)) { ?>
                  <small class="form-text text-muted">Current file:</small>
                  <div class="mt-2">
                    <img src="docs/events/<?php echo htmlspecialchars($e_d_url); ?>" alt="Current" class="img-fluid img-thumbnail" style="max-height: 200px;">
                  </div>
                <?php } ?>
              </div>
            </div>

            <div class="d-flex justify-content-end pt-2">
              <?php
                if(isset($_GET['edit'])){
                  echo '<button type="submit" name="update" class="btn btn-primary"><i class="far fa-edit"></i>&nbsp;Update Event</button>';
                } else {
                  echo '<button type="submit" name="add" class="btn btn-primary"><i class="fas fa-plus"></i>&nbsp;Add Event</button>';
                }
              ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>


<?php

if(isset($_POST['add'])){
    if(!empty($_POST['event_name'])&&
      !empty($_POST['event_date'])&& 
      !empty($_POST['event_comment'])&&
      !empty($_POST['event_time'])
      ){
       // Sanitize inputs (allow optional fields to be empty without notices)
       $event_name = isset($_POST['event_name']) ? mysqli_real_escape_string($con, $_POST['event_name']) : '';
       $event_venue = isset($_POST['event_venue']) ? mysqli_real_escape_string($con, $_POST['event_venue']) : '';
       $event_date = isset($_POST['event_date']) ? mysqli_real_escape_string($con, $_POST['event_date']) : '';
       $event_chief_guest = isset($_POST['event_chief_guest']) ? mysqli_real_escape_string($con, $_POST['event_chief_guest']) : '';
       $event_comment = isset($_POST['event_comment']) ? mysqli_real_escape_string($con, $_POST['event_comment']) : '';
       $event_time = isset($_POST['event_time']) ? mysqli_real_escape_string($con, $_POST['event_time']) : '';
       $status = isset($_POST['status']) ? mysqli_real_escape_string($con, $_POST['status']) : '';
       if ($status === '') {
         // Default to first available status if not provided
         $rs = mysqli_query($con, "SELECT `id` FROM `notice_event_stutas` ORDER BY `id` ASC LIMIT 1");
         if ($rs && mysqli_num_rows($rs) > 0) { $statusRow = mysqli_fetch_assoc($rs); $status = (string)$statusRow['id']; }
         if (!is_string($status) || $status === '') { $status = '0'; }
       }

       // File upload (optional) — if absent, store empty string to satisfy NOT NULL columns
       $name = '';
       $hasUpload = isset($_FILES['ima']) && isset($_FILES['ima']['tmp_name']) && is_uploaded_file($_FILES['ima']['tmp_name']) && (int)$_FILES['ima']['error'] === UPLOAD_ERR_OK;
       if ($hasUpload) {
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
         $mime = function_exists('mime_content_type') ? @mime_content_type($t_name) : '';
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
       }
       
       $fileSqlVal = "'".mysqli_real_escape_string($con, (string)$name)."'";
       $sql="INSERT INTO `notice_event` (`event_name`,`event_venue`,`event_date`,`event_chief_guest`,`event_comment`,`event_time`,`event_docs_url`,`status`) values('$event_name','$event_venue','$event_date','$event_chief_guest','$event_comment','$event_time',$fileSqlVal,'$status')";
       if(mysqli_query($con,$sql)){
           $message ="<div class='alert alert-success'>New record created successfully</div>";
           echo $message;
       }else{
           echo "<div class='alert alert-danger'>Error :- ".htmlspecialchars(mysqli_error($con))."</div>";
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



<!-- Removed erroneous local footer include to avoid duplicate footers -->
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
