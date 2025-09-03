<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->  
<?php
$title = "onpeak&offpeak | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

 if($_SESSION['user_type']!='STU'){
 ?>
<!--END DON'T CHANGE THE ORDER--> 

<!--BLOCK#2 START YOUR CODE HERE -->
   <!-- Content here -->

   <?php
   if (isset($_POST['approved'])) {
     $id = mysqli_real_escape_string($con, $_POST['approved']);
     $sql = "UPDATE `onpeak_request` SET `onpeak_request_status`='Approved' WHERE `id`='$id'";
     if (mysqli_query($con, $sql)) {
       echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Request approved.</strong><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
     } else {
       echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error: '.htmlspecialchars(mysqli_error($con)).'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
     }
   }

   ?>


   <?php
   if (isset($_POST['NotApproved'])) {
     $id = mysqli_real_escape_string($con, $_POST['NotApproved']);
     $sql = "UPDATE `onpeak_request` SET `onpeak_request_status`='Not Approved' WHERE `id`='$id'";
     if (mysqli_query($con, $sql)) {
       echo '<div class="alert alert-warning alert-dismissible fade show" role="alert"><strong>Request not approved.</strong><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
     } else {
       echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error: '.htmlspecialchars(mysqli_error($con)).'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';
     }
   }

   ?>


   <br>     
        <div class="row border border-light shadow p-3 mb-5 bg-white rounded">
          <div class="col">
          <br>
            <blockquote class="blockquote text-center">
                <h1 class="display-4">On peak</h1> 
                <p class="mb-0">Department of Information and Communication Technology</p>
                <footer class="blockquote-footer">Head of the Department<cite title="Source Title"></cite></footer>
            </blockquote>
          </div>
        </div>



<br>

    <div class="border border-light shadow p-3 mb-5 bg-white rounded" > 
      <div class="col">
        <div class=row>
            <div class="col">
                <br>
                <br>
                <nav class="navbar navbar-light bg-light">
                        <form class="form-inline">
                        <div class="pr-5 pl-2 ml-auto text-info"> <h6> <strong> Pending Requests </strong> </h6> </div>
                       </form>
                </nav>
                <br>
            </div>
        </div>
        
        

      <div class="table-responsive d-none d-md-block">
        <table class="table table-hover">
            <thead class="thead-dark">
                  <tr>
                    <th scope="col">REG NO</th>
                    <th scope="col">CONTACT</th>
                    <th scope="col">EXIT DATE</th>
                    <th scope="col">EXIT TIME</th>
                    <th scope="col">RETURN DATE</th>
                    <th scope="col">RETURN TIME</th>
                    <th scope="col">COMMENT</th>
                    <th scope="col" class="text-nowrap">ACTION</th>
                    <th scope="col"></th>
                  </tr>
            </thead>
            <tbody>
           
             <?php
                // Replaced stored procedure call with direct SELECT to avoid DEFINER errors
                $sql = "SELECT id, student_id, contact_no, exit_date, exit_time, return_date, return_time, comment, request_date_time, onpeak_request_status FROM onpeak_request WHERE onpeak_request_status='Pending' ORDER BY id DESC";
                $result = mysqli_query($con, $sql);
                if ($result && mysqli_num_rows($result) > 0) {
                 while($row = mysqli_fetch_assoc($result)) {

                // Mobile card view (visible only on mobile)
                echo '
                <div class="card mb-3 d-md-none">
                  <div class="card-body">
                    <div class="row mb-2">
                      <div class="col-5 font-weight-bold">Reg No:</div>
                      <div class="col-7">'.$row['student_id'].'</div>
                    </div>
                    <div class="row mb-2">
                      <div class="col-5 font-weight-bold">Contact:</div>
                      <div class="col-7">'.$row['contact_no'].'</div>
                    </div>
                    <div class="row mb-2">
                      <div class="col-5 font-weight-bold">Exit:</div>
                      <div class="col-7">'.$row['exit_date'].' '.$row['exit_time'].'</div>
                    </div>
                    <div class="row mb-2">
                      <div class="col-5 font-weight-bold">Return:</div>
                      <div class="col-7">'.$row['return_date'].' '.$row['return_time'].'</div>
                    </div>
                    <div class="row mb-3">
                      <div class="col-5 font-weight-bold">Comment:</div>
                      <div class="col-7">'.($row['comment'] ?: '-').'</div>
                    </div>
                    <div class="d-flex justify-content-between">
                      <form method="post" class="d-inline">
                        <button type="submit" class="btn btn-success btn-sm mb-1" name="approved" value="'.$row['id'].'" style="min-width: 80px;">
                          <i class="fas fa-check"></i> Approve
                        </button>
                        <button type="submit" class="btn btn-danger btn-sm mb-1" name="NotApproved" value="'.$row['id'].'" style="min-width: 80px;">
                          <i class="fas fa-times"></i> Reject
                        </button>
                      </form>
                      <a href="OnPeakinfo.php?stid='.$row['student_id'].'&id='.$row['id'].'" class="btn btn-info btn-sm mb-1">
                        <i class="fas fa-info-circle"></i> Details
                      </a>
                    </div>
                  </div>
                </div>';

                // Desktop table row (hidden on mobile)
                echo '
                <tr class="d-none d-md-table-row">
                  <td>'.$row['student_id'].'</td>
                  <td>'.$row['contact_no'].'</td>
                  <td>'.$row['exit_date'].'</td>
                  <td>'.$row['exit_time'].'</td>
                  <td>'.$row['return_date'].'</td>
                  <td>'.$row['return_time'].'</td>
                  <td>'.($row['comment'] ?: '-').'</td>
                  <td class="text-nowrap">
                    <form method="post" class="d-inline">
                      <button type="submit" class="btn btn-success btn-sm" name="approved" value="'.$row['id'].'" title="Approve">
                        <i class="fas fa-check"></i>
                      </button>
                      <button type="submit" class="btn btn-danger btn-sm" name="NotApproved" value="'.$row['id'].'" title="Reject">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </td>
                  <td class="text-nowrap">
                    <a href="OnPeakinfo.php?stid='.$row['student_id'].'&id='.$row['id'].'" class="btn btn-info btn-sm" title="View Details">
                      <i class="fas fa-info-circle"></i>
                    </a>
                  </td>
                </tr>';
                  }
                 } else {
                     echo "No more Requests";
                }
            ?>
           
        </table> 
      </div>
    </div>
  </div>


 
  <div class="border border-light shadow p-3 mb-4 bg-white rounded">
    <div class="row">
      <div class="col-12">
        <h5 class="text-info mb-4"><strong>Request History</strong></h5>
      </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs nav-fill mb-3" id="historyTabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link active" id="approved-tab" data-toggle="tab" href="#approved" role="tab" aria-controls="approved" aria-selected="true">
          <i class="fas fa-thumbs-up d-none d-md-inline"></i> Approved
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="not-approved-tab" data-toggle="tab" href="#not-approved" role="tab" aria-controls="not-approved" aria-selected="false">
          <i class="fas fa-thumbs-down d-none d-md-inline"></i> Not Approved
        </a>
      </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="historyTabsContent">
      <!-- Approved Requests Tab -->
      <div class="tab-pane fade show active" id="approved" role="tabpanel" aria-labelledby="approved-tab">
        <?php
        $sql = "SELECT student_id, reason, contact_no, exit_date, exit_time, return_date, return_time, onpeak_request_status 
                FROM onpeak_request 
                WHERE onpeak_request_status='Approved' 
                ORDER BY id DESC";
        $result = mysqli_query($con, $sql);
        
        if($result && mysqli_num_rows($result) > 0): 
          // Mobile Card View
          echo '<div class="d-md-none">';
          while($row = mysqli_fetch_assoc($result)) {
            echo '<div class="card mb-3">
                    <div class="card-body">
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Reg No:</div>
                        <div class="col-7">'.$row["student_id"].'</div>
                      </div>
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Reason:</div>
                        <div class="col-7">'.($row["reason"] ?: '-').'</div>
                      </div>
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Exit:</div>
                        <div class="col-7">'.$row["exit_date"].' '.$row["exit_time"].'</div>
                      </div>
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Return:</div>
                        <div class="col-7">'.$row["return_date"].' '.$row["return_time"].'</div>
                      </div>
                      <div class="row">
                        <div class="col-12">
                          <span class="badge badge-success">'.$row["onpeak_request_status"].'</span>
                        </div>
                      </div>
                    </div>
                  </div>';
          }
          echo '</div>';
          
          // Reset pointer to start for desktop view
          mysqli_data_seek($result, 0);
          
          // Desktop Table View
          echo '<div class="table-responsive d-none d-md-block">
                  <table class="table table-hover">
                    <thead class="thead-light">
                      <tr>
                        <th>Reg No</th>
                        <th>Reason</th>
                        <th>Contact</th>
                        <th>Exit</th>
                        <th>Return</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>';
          
          while($row = mysqli_fetch_assoc($result)) {
            echo '<tr>
                    <td>'.$row["student_id"].'</td>
                    <td>'.($row["reason"] ?: '-').'</td>
                    <td>'.$row["contact_no"].'</td>
                    <td>'.$row["exit_date"].'<br><small class="text-muted">'.$row["exit_time"].'</small></td>
                    <td>'.$row["return_date"].'<br><small class="text-muted">'.$row["return_time"].'</small></td>
                    <td><span class="badge badge-success">'.$row["onpeak_request_status"].'</span></td>
                  </tr>';
          }
          
          echo '      </tbody>
                  </table>
                </div>';
                
        else:
          echo '<div class="alert alert-info">No approved requests found.</div>';
        endif;
        ?>
      </div>

      <!-- Not Approved Requests Tab -->
      <div class="tab-pane fade" id="not-approved" role="tabpanel" aria-labelledby="not-approved-tab">
        <?php
        $sql = "SELECT student_id, reason, contact_no, exit_date, exit_time, return_date, return_time, onpeak_request_status 
                FROM onpeak_request 
                WHERE onpeak_request_status='Not Approved' 
                ORDER BY id DESC";
        $result = mysqli_query($con, $sql);
        
        if($result && mysqli_num_rows($result) > 0):
          // Mobile Card View
          echo '<div class="d-md-none">';
          while($row = mysqli_fetch_assoc($result)) {
            echo '<div class="card mb-3">
                    <div class="card-body">
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Reg No:</div>
                        <div class="col-7">'.$row["student_id"].'</div>
                      </div>
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Reason:</div>
                        <div class="col-7">'.($row["reason"] ?: '-').'</div>
                      </div>
                      <div class="row mb-2">
                        <div class="col-5 font-weight-bold">Exit:</div>
                        <div class="col-7">'.$row["exit_date"].' '.$row["exit_time"].'</div>
                      </div>
                      <div class="row">
                        <div class="col-12">
                          <span class="badge badge-danger">'.$row["onpeak_request_status"].'</span>
                        </div>
                      </div>
                    </div>
                  </div>';
          }
          echo '</div>';
          
          // Reset pointer to start for desktop view
          mysqli_data_seek($result, 0);
          
          // Desktop Table View
          echo '<div class="table-responsive d-none d-md-block">
                  <table class="table table-hover">
                    <thead class="thead-light">
                      <tr>
                        <th>Reg No</th>
                        <th>Reason</th>
                        <th>Contact</th>
                        <th>Exit</th>
                        <th>Return</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>';
          
          while($row = mysqli_fetch_assoc($result)) {
            echo '<tr>
                    <td>'.$row["student_id"].'</td>
                    <td>'.($row["reason"] ?: '-').'</td>
                    <td>'.$row["contact_no"].'</td>
                    <td>'.$row["exit_date"].'<br><small class="text-muted">'.$row["exit_time"].'</small></td>
                    <td>'.$row["return_date"].'<br><small class="text-muted">'.$row["return_time"].'</small></td>
                    <td><span class="badge badge-danger">'.$row["onpeak_request_status"].'</span></td>
                  </tr>';
          }
          
          echo '      </tbody>
                  </table>
                </div>';
                
        else:
          echo '<div class="alert alert-info">No rejected requests found.</div>';
        endif;
        ?>
      </div>
    </div>
  </div>
    

<script>
  $(document).ready(function() {
    // Initialize tabs
    $('#historyTabs a').on('click', function (e) {
      e.preventDefault();
      $(this).tab('show');
    });
    
    // Store the selected tab in localStorage
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
      localStorage.setItem('selectedTab', $(e.target).attr('href'));
    });
    
    // Get the last selected tab from localStorage
    var selectedTab = localStorage.getItem('selectedTab');
    if (selectedTab) {
      $('a[href="' + selectedTab + '"]').tab('show');
    }
  });
</script>


  
        

      <!-- <div class=row >
        <table class="table table-hover">
            <thead>
                  <tr>
                    <th scope="col">REGISTRATION NO </th>
                    <th scope="col">REASON FOR EXIT</th>
                    <th scope="col">CONTACT NO </th>
                    <th scope="col">EXIT DATE</th>
                    <th scope="col">EXIT TIME</th>
                    <th scope="col">RETURN DATE</th>
                    <th scope="col">RETURN TIME</th>
                    <th scope="col">REFERENCE</th>
                    
                  </tr>
            </thead> -->
            <?php
            // if(isset($_GET['sea'])){
            //    $id= $_GET['sear'];
               
            //   $sql = "SELECT * FROM `onpeak_request` WHERE `student_id`='$id' ";
            //   $result = mysqli_query($con, $sql);
            //   if (mysqli_num_rows($result) > 0) {
            //   while($row = mysqli_fetch_assoc($result)) {

            //   echo '
            //     <tbody> 
            //       <tr>
            //         <th scope="row">'. $row["student_id"].'</th>
            //         <td>'. $row["reason"]. '</td>
            //         <td>'. $row["contact_no"]. '</td>
            //         <td>'. $row["exit_date"]. '</td>
            //         <td>'. $row["exit_time"]. '</td>
            //         <td>'. $row["return_date"].'</td>
            //         <td>'. $row["return_time"]. '</td>
            //         <td>'. $row["onpeak_request_status"]. '</td>
            //        </tr> 
            //   </tbody>
            //   ';
            //   }
            //       } else {
            //           echo "No more Requests";
            //       }

              
              
            // }


            ?> 
            
                <?php
                    // $sql = "SELECT * FROM `onpeak_request` WHERE `onpeak_request_status`= 'Approved' OR `onpeak_request_status`= 'Not Approved'  ";
                    // $result = mysqli_query($con, $sql);
                    // if (mysqli_num_rows($result) > 0) {
                    // while($row = mysqli_fetch_assoc($result)) {

                    // echo '
                    //   <tbody> 
                    //     <tr>
                    //       <th scope="row">'. $row["student_id"].'</th>
                    //       <td>'. $row["reason"]. '</td>
                    //       <td>'. $row["contact_no"]. '</td>
                    //       <td>'. $row["exit_date"]. '</td>
                    //       <td>'. $row["exit_time"]. '</td>
                    //       <td>'. $row["return_date"].'</td>
                    //       <td>'. $row["return_time"]. '</td>
                    //       <td>'. $row["onpeak_request_status"]. '</td>
                    //      </tr> 
                    // </tbody>
                    // ';
                    // }
                    //     } else {
                    //         echo "No more Requests";
                    //     }
            ?>
           
        <!-- </table> 
      </div>
    </div>
  </div>
  -->



<br>
<br>


  <div class="border border-light shadow p-3 mb-5 bg-white rounded" > 
      <div class="col">
        <div class=row>
            <div class="col">
                <br>
                <br>
                 <nav class="navbar navbar-light bg-light">
                        <form class="form-inline">
                        
                        <form method="GET">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <input class="form-control mr-sm-2" type="search" placeholder="Registration No" aria-label="Search" name="sear">
                        <button class="btn btn-outline-success my-2 my-sm-0" type="submit" name="sea"><i class="fas fa-search"></i> </button>
                       </form>
                        </form>
                </nav>
                <br>
                <br>
            </div>
        </div>
        
        

      <div class=row >
        <table class="table table-hover ">
            <thead class="thead-dark">
                  <tr>
                    <th scope="col">REGISTRATION NO </th>
                    <th scope="col">REASON FOR EXIT</th>
                    <th scope="col">CONTACT NO </th>
                    <th scope="col">EXIT DATE</th>
                    <th scope="col">EXIT TIME</th>
                    <th scope="col">RETURN DATE</th>
                    <th scope="col">RETURN TIME</th>
                    <th scope="col">REFERENCE</th>
                    
                  </tr>
            </thead>
            <?php
            if(isset($_GET['sea'])){
               $id= $_GET['sear'];
               
              $sql = "SELECT * FROM `onpeak_request` WHERE `student_id`='".mysqli_real_escape_string($con, $id)."'";
              $result = mysqli_query($con, $sql);
              if (mysqli_num_rows($result) > 0) {
              while($row = mysqli_fetch_assoc($result)) {

              echo '
                <tbody> 
                  <tr>
                    <th scope="row">'. $row["student_id"].'</th>
                    <td>'. $row["reason"]. '</td>
                    <td>'. $row["contact_no"]. '</td>
                    <td>'. $row["exit_date"]. '</td>
                    <td>'. $row["exit_time"]. '</td>
                    <td>'. $row["return_date"].'</td>
                    <td>'. $row["return_time"]. '</td>
                    <td>'. $row["onpeak_request_status"]. '</td>
                   </tr> 
              </tbody>
              ';
              }
                  } else {
                      echo "No more Requests";
                  }

              
              
            }


            ?> 



        <?php
            if(isset($_GET['search_d'])){
               $exit_date= $_GET['seard'];
               
              $sql = "SELECT * FROM `onpeak_request` WHERE `exit_date`='$id' ";
              $result = mysqli_query($con, $sql);
              if (mysqli_num_rows($result) > 0) {
              while($row = mysqli_fetch_assoc($result)) {

              echo '
                <tbody> 
                  <tr>
                    <th scope="row">'. $row["student_id"].'</th>
                    <td>'. $row["reason"]. '</td>
                    <td>'. $row["contact_no"]. '</td>
                    <td>'. $row["exit_date"]. '</td>
                    <td>'. $row["exit_time"]. '</td>
                    <td>'. $row["return_date"].'</td>
                    <td>'. $row["return_time"]. '</td>
                    <td>'. $row["onpeak_request_status"]. '</td>
                   </tr> 
              </tbody>
              ';
              }
                  } else {
                      echo "No more Requests";
                  }

              
              
            }


            ?> 
            
                
           
        </table> 
      </div>
    </div>
  </div>




<br>
<br>



       <div class="row ">
          <div class="col-3 ">
          <div class="card shadow-sm p-3 mb-5 bg-white rounded" style="width: 18rem;">
              <div class="card-body">
              <h5 class="card-title">Leave of Absence </h5>
              <p class="card-text">A LOA is an extended period of time off from their studies. 
                    there may be a formal process you need to follow to get approved for a leave.</p>
              </div>
              </div>
          </div>

          <div class="col-3 ">
              <div class="card shadow-sm p-3 mb-5 bg-white rounded" style="width: 18rem;">
              <div class="card-body">
              <h5 class="card-title">Time Schedule</h5>
              <p class="card-text">This form must be submitted to the guards, when students wants to exit SLGTI during scgool hours/ on peak (8.15 am- 4.15 pm)</p>
              </div>
              </div>
          </div>

          <div class="col-3">
              <div class="card shadow-sm p-3 mb-5 bg-white rounded" style="width: 18rem;">
              <div class="card-body">
              <h5 class="card-title">Jurisdiction of the Code</h5>
              <p class="card-text">Please note that students fail within the jurisdiction of the code of conduct and honor for off-campus conduct.</p>
              </div>
              </div>
          </div>

          <div class="col-3">
              <div class="card shadow-sm p-3 mb-5 bg-white rounded" style="width: 18rem;">
              <div class="card-body">
              <h5 class="card-title">Approvel</h5>
              <p class="card-text">Please supervise the reason for students temporary exit in the box below, state the date , inform the warden. </p>
              </div>
              </div>
          </div>
      </div>

 
 

<!--END OF YOUR COD-->
<?php } ?> 
<!--BLOCK#3 START DON'T CHANGE THE ORDER-->   
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->  
