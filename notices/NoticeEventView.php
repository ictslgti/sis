<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "notices | SLGTI";
include_once("../config.php");
include_once("../head.php");
// Determine if this is an embedded view (e.g., inside a student iframe)
$embed = isset($_GET['embed']) && $_GET['embed'] == '1';
if (!$embed) {
  include_once("../menu.php");
  // Students do not have access to full page Notices
  $isSTU = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU';
  if ($isSTU) {
      echo '<script>window.location.href = "../dashboard/index.php";</script>';
      exit;
  }
}
?>
<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START YOUR CODE HERE -->
<?php if ($embed) { ?>
<style>
  body { background: #fff; }
  .container-fluid { padding: 12px; }
  .card { box-shadow: none !important; border: 0 !important; }
  .card-header { display: none !important; }
  img.img-thumbnail { max-height: 200px; width: 100%; height: auto; border: 0; border-radius: .5rem; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
  h3.mb-4.text-danger { font-size: 1.2rem; color: #e03131 !important; }
  h5.border-bottom { border: 0 !important; margin-bottom: .5rem !important; }
  /* Embedded layout polish */
  .ev-embed { padding: 6px 4px; }
  .ev-embed i { display: none !important; } /* hide symbols/icons */
  .ev-embed .file-caption { display: none; }
  .ev-embed .col { padding-top: .25rem; padding-bottom: .25rem; }
  .ev-embed .col h5 { font-size: .95rem; color: #495057; }
  .ev-embed .col + .col h5 { font-weight: 600; color: #212529; }
  @media (max-width: 575.98px) {
    .ev-embed .col { flex: 0 0 100%; max-width: 100%; }
    h3.mb-4.text-danger { font-size: 1.05rem; }
    img.img-thumbnail { max-height: 160px; }
  }
</style>
<?php } ?>

<?php if(!$embed): ?>
<div class="container-fluid py-3">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
          <div>
            <h5 class="mb-0"><i class="fas fa-bullhorn"></i> View Events</h5>
            <small class="text-light">Sri Lanka German Training Institute</small>
          </div>
          <a href="NoticeEventUpload.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i>&nbsp;Add Event</a>
        </div>
        <div class="card-body">
          <form method ="post"  action="NoticeEventUpload">
            <?php  
            if(isset($_POST["evName"])){
                $eid = trim($_POST["evName"]);
                $eid_esc = mysqli_real_escape_string($con, $eid);
                $sql = "SELECT * from `notice_event` e,`notice_event_stutas` s  WHERE e.status=s.id and LOWER(s.`status`)=LOWER('$eid_esc') order by event_date > curdate() desc";
                $result = mysqli_query($con,$sql);
               if (mysqli_num_rows($result)>0) {
                    while($row=mysqli_fetch_assoc($result)){
                    $e_name=$row['event_name'];
                    $e_venue=$row['event_venue'];
                    $e_date=$row['event_date'];
                    $e_time=$row['event_time'];
                    $e_cguest=$row['event_chief_guest'];
                    $e_comm=$row['event_comment'];
                    $file_name=$row['event_docs_url'];
                    $C_date=date('Y-m-d');
                    if($e_date > $C_date)$mess='<h4  class="text-success">soon<h4>';else $mess='<h4  class="text-danger">closed<h4>';
                    // Build image HTML safely
                    $imgHtml = '<div class="text-muted small">No image</div>';
                    $imgCaption = '<div class="text-muted small">File: (none)</div>';
                    if (!empty($file_name)) {
                      $fs = __DIR__ . '/docs/events/' . $file_name;
                      if (file_exists($fs)) {
                        $imgHtml = '<img src="/notices/docs/events/'.htmlspecialchars($file_name).'" class="img-fluid img-thumbnail" alt="Event Image">';
                        $imgCaption = '<div class="mt-2 small">File: <code>'.htmlspecialchars($file_name).'</code> | <a href="/notices/docs/events/'.htmlspecialchars($file_name).'" target="_blank">Open</a></div>';
                      } else {
                        $imgCaption = '<div class="mt-2 small text-danger">File not found: <code>'.htmlspecialchars($fs).'</code></div>';
                      }
                    }
                    echo '<div class="row align-items-stretch mb-4">
                      <div class="col-md-5 col-sm-12 mb-3 mb-md-0">
                        <div class="card h-100 shadow-sm">
                          <div class="card-body d-flex flex-column justify-content-center text-center">
                            <div class="mb-2">'.$mess.'</div>
                            '.$imgHtml.'
                            '.$imgCaption.'
                          </div>
                        </div>
                      </div>
                      <div class="col-md-7 col-sm-12">
                        <div class="card h-100 shadow-sm">
                          <div class="card-body">
                            <h3 class="mb-4 text-danger">Event For:&nbsp;'.$e_name.'</h3>
                            <div class="row">
                              <div class="col-6"><h5 class="border-bottom mb-3"><i class="fas fa-map-marker-alt text-primary"></i> Venue</h5></div>
                              <div class="col-6"><h5 class="border-bottom mb-3">' .  $e_venue . '</h5></div>
                              <div class="w-100"></div>
                              <div class="col-6"><h5 class="border-bottom mb-3"><i class="far fa-calendar-alt text-primary"></i> Date / Time</h5></div>
                              <div class="col-6"><h5 class="border-bottom mb-3">' .  $e_date .' / ' .$e_time. '</h5></div>
                              <div class="w-100"></div>
                              <div class="col-6"><h5 class="border-bottom mb-3"><i class="fas fa-user text-primary"></i> Chief Guest</h5></div>
                              <div class="col-6"><h5 class="border-bottom mb-3">' .  $e_cguest . '</h5></div>
                            </div>
                            <div class="mt-3">
                              <h5><i class="fab fa-audible text-primary"></i> Comment</h5>
                              <p class="mb-0">' .  $e_comm . '</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>';


                    }
                }}
?>

          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php
$eid =$e_name = $e_venue = $e_date = $e_time =$e_cguest = $e_comm= $file_name = $e_status = null;
if(isset($_GET['id'])){
    $eid=$_GET['id'];
    $sql="SELECT `event_id`,`event_name`,`event_venue`,`event_date`,`event_time`,`event_chief_guest`,`event_comment`,`event_docs_url`,`status` FROM `notice_event` WHERE  `event_id`= $eid";
    $result = mysqli_query($con,$sql);
   if (mysqli_num_rows($result) == 1) {
        $row=mysqli_fetch_assoc($result);
        $e_name=$row['event_name'];
        $e_venue=$row['event_venue'];
        $e_date=$row['event_date'];
        $e_time=$row['event_time'];
        $e_cguest=$row['event_chief_guest'];
        $e_comm=$row['event_comment'];
        $file_name=$row['event_docs_url'];
        $e_status=$row['status'];
    }
}

if(isset($_GET['id'])){
    // Single view image handling
    $singleImgHtml = '<div class="text-muted small">No image</div>';
    $singleCaption = '<div class="text-muted small">File: (none)</div>';
    if (!empty($file_name)) {
        $fs = __DIR__ . '/docs/events/' . $file_name;
        if (file_exists($fs)) {
            $singleImgHtml = '<img src="/notices/docs/events/'.htmlspecialchars($file_name).'"  class="img-fluid img-thumbnail" alt="Event Image">';
            $singleCaption = '<div class="mt-2 small">File: <code>'.htmlspecialchars($file_name).'</code> | <a href="/notices/docs/events/'.htmlspecialchars($file_name).'" target="_blank">Open</a></div>';
        } else {
            $singleCaption = '<div class="mt-2 small text-danger">File not found: <code>'.htmlspecialchars($fs).'</code></div>';
        }
    }
    echo '
    <div class="row ev-embed">

        <div class="col-md-5 col-sm-12">
            <div>
                '.$singleImgHtml.'
                '.$singleCaption.'
            </div>
        </div>
    
        <div class="col-md-7 col-sm-12">
    
            <div class="row">
                <div class="col-12"> <h3 class="mb-4 text-danger">Event For:&nbsp;'.$e_name.'</h3></div>
                
                <div class="w-100"></div>
                <div class="col"><h5 class="border-bottom mb-4">  <i class="fas fa-map-marker-alt text-primary"></i>  </i>&nbsp;&nbsp;Venue&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5></div>
                <div class="col"><h5 class="border-bottom mb-4">' .  $e_venue . '</h5></div>
                <div class="w-100"></div>
                <div class="col"><h5 class="border-bottom mb-4"> <i class="far fa-calendar-alt text-primary"></i></i>&nbsp;&nbsp;Date / Time&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5></div>
                <div class="col"><h5 class="border-bottom mb-4">' .  $e_date .'&nbsp;&nbsp;/&nbsp;&nbsp;' .$e_time. '</h5></div>
                <div class="w-100"></div>
                <div class="col"><h5 class="border-bottom mb-4"> <i class="fas fa-user text-primary"> </i>&nbsp;&nbsp;Chief Guest&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5></div>
                <div class="col"><h5 class="border-bottom mb-4">' .  $e_cguest . '</h5></div>    
                <div class="w-100"></div>
                <div class="col-12"> <h5 class=""><i class="fab fa-audible text-primary"></i> </i>&nbsp;Comment&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5><br> 
                <h5 >' .  $e_comm . '</h5></div>
            </div>
    
           
        </div>
    </div>
    
    ';
}

?>

   

<!-- Removed duplicate local footer include to avoid double footers -->
<?php if(!$embed): ?>
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
<?php endif; ?>
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php if(!$embed) { include_once("../footer.php"); } ?>
<!--END DON'T CHANGE THE ORDER-->
