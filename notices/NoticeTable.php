<!-- BLOCK#1 START DON'T CHANGE THE ORDER -->
<?php 
$title = "notices | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
$isSTU = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU';
?>
<!-- BLOCK#2 START YOUR CODER HERE -->

<div class="container-fluid py-3">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-11">
      <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
          <h5 class="mb-0"><i class="fas fa-list"></i> Event Info List</h5>
          <?php if (!$isSTU) { ?>
            <a href="../notices/NoticeEventUpload.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i>&nbsp;Add Event</a>
          <?php } ?>
        </div>
        <div class="card-body">
          <?php
          // Handle delete action with role check and user feedback
          if (isset($_GET['delete_id'])) {
              if ($isSTU) {
                  echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                          <strong>Access denied.</strong> Students cannot delete events.
                          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                          </button>
                        </div>';
              } else {
                  $e_id = (int)$_GET['delete_id'];
                  $sql = "DELETE FROM `notice_event` WHERE `event_id`='$e_id'";
                  if (mysqli_query($con, $sql)) {
                      echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                              <strong>ID '.htmlspecialchars($e_id).'</strong> deleted successfully.
                              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>';
                  } else {
                      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                              <strong>Error:</strong> Cannot delete or update a parent row (foreign key constraint may fail)
                              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>';
                  }
              }
          }
          ?>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="thead-light">
                <tr>
                  <th scope="col">No.</th>
                  <th scope="col">Event Name</th>
                  <th scope="col">Venue</th>
                  <th scope="col">Date</th>
                  <th scope="col">Time</th>
                  <th scope="col">Chief Guest</th>
                  <th scope="col">Comment</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                  $sql="SELECT e.`event_id` AS `event_id`,
                                e.`event_name` AS `event_name`,
                                e.`event_venue` AS `event_venue`,
                                e.`event_date` AS `event_date`,
                                e.`event_time` AS `event_time`,
                                e.`event_chief_guest` AS `event_chief_guest`,
                                e.`event_comment` AS `event_comment`,
                                s.`status` AS `status`
                         FROM `notice_event` e
                         LEFT JOIN `notice_event_stutas` s ON e.`status` = s.`id`";
                  $result = mysqli_query($con, $sql);
                  if ($result && mysqli_num_rows($result) > 0) {
                    while($row = mysqli_fetch_assoc($result)) {
                      echo '<tr>'
                          . '<td>'. $row["event_id"].'</td>'
                          . '<td>'. htmlspecialchars($row["event_name"]).'</td>'
                          . '<td>'. htmlspecialchars($row["event_venue"]).'</td>'
                          . '<td>'. htmlspecialchars($row["event_date"]).'</td>'
                          . '<td>'. htmlspecialchars($row["event_time"]).'</td>'
                          . '<td>'. htmlspecialchars($row["event_chief_guest"]).'</td>'
                          . '<td>'. htmlspecialchars($row["event_comment"]).'</td>'
                          . '<td>';
                          $st = trim((string)($row['status'] ?? ''));
                          $badge = 'secondary';
                          if (strcasecmp($st, 'AwardingCeremony') === 0 || strcasecmp($st, 'Upcoming') === 0) { $badge = 'success'; }
                          if (strcasecmp($st, 'Celebration') === 0 || strcasecmp($st, 'Ongoing') === 0) { $badge = 'info'; }
                          if (strcasecmp($st, 'Closed') === 0) { $badge = 'dark'; }
                          echo '<span class="badge badge-'. $badge .'">'. htmlspecialchars($st ?: 'N/A') .'</span></td>'
                              . '<td class="text-right">'
                              . '<a href="../notices/NoticeEventView.php?id='. $row["event_id"].'" class="btn btn-primary btn-sm mr-1"> <i class="fas fa-eye"></i> View</a>';
                          if(!$isSTU){ echo '<a href="../notices/NoticeEventUpload.php?edit='. $row["event_id"].'" class="btn btn-warning btn-sm mr-1"><i class="far fa-edit"></i></a>'
                            . '<button class="btn btn-danger btn-sm" data-href="?delete_id='.$row["event_id"].'" data-toggle="modal" data-target="#confirm-delete"><i class="fas fa-trash"></i></button>'; }
                          echo '</td></tr>';
                    }
                  } else {
                    echo '<tr><td colspan="9" class="text-center text-muted">0 results</td></tr>';
                  }
                ?>
              </tbody>
            </table>
          </div>
          <div class="pt-2">
            <a href="NoticeEventUpload.php" class="btn btn-link"><i class="fas fa-arrow-left"></i> Back</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- END YOUR CODER HERE -->

    <!-- BLOCK#3 START DON'T CHANGE THE ORDER -->
    <?php 
    include_once("../footer.php");
    ?>
    <!-- END DON'T CHANGE THE ORDER -->

