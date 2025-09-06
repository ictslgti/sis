<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "hostel | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START YOUR CODE HERE -->
<?php 
// Session is needed for role and warden gender
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Detect WAR user and fetch warden gender
$isWarden = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'WAR';
$isAdmin  = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADM';
$isSAO    = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'SAO';
$wardenGender = null;
if ($isWarden && !empty($_SESSION['user_name'])) {
  if ($st = mysqli_prepare($con, "SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($st, 's', $_SESSION['user_name']);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    if ($rs) {
      $r = mysqli_fetch_assoc($rs);
      if ($r && isset($r['staff_gender'])) { $wardenGender = $r['staff_gender']; }
    }
    mysqli_stmt_close($st);
  }
}

// Delete an allocation by its ID (Admins only)
if (isset($_GET['delete'])) {
  if (!$isAdmin) {
    echo '<div class="alert alert-warning">You are not allowed to delete allocations.</div>';
  } else {
  $alloc_id = (int)$_GET['delete'];
  if ($alloc_id > 0) {
    if ($stDel = mysqli_prepare($con, "DELETE FROM hostel_allocations WHERE id = ?")) {
      mysqli_stmt_bind_param($stDel, 'i', $alloc_id);
      $ok = mysqli_stmt_execute($stDel);
      mysqli_stmt_close($stDel);
      if ($ok) {
        echo '<div class="alert alert-danger">
          <strong>Deleted!</strong> Allocation removed.
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>';
      } else {
        echo '<div class="alert alert-warning">Error deleting allocation: ' . htmlspecialchars(mysqli_error($con)) . '</div>';
      }
    } else {
      echo '<div class="alert alert-warning">Failed to prepare delete: ' . htmlspecialchars(mysqli_error($con)) . '</div>';
    }
  }
  }
}
?>










<div style="margin-top:30px ">
  <div class="card ">
   <div class="card-header bg-info">
      <div class="row">
        <div class="col-md-9" >
       
                <label style="font-family: 'Luckiest Guy', cursive; font-size: 20px; "> <i class="fas fa-user-graduate"></i> &nbsp; Student Accomadation</label>
                <!-- <footer class="blockquote-footer" style=" padding-left: 650px">Hostel Allocation <cite title="Source Title"></cite></footer> -->
            
        </div>
        
      </div>
    </div>

    <div class="card-body">
    <style>
      /* Scoped styles for Hostel page */
      .font-weight-600 { font-weight: 600; }
      .btn-xs { padding: .15rem .4rem; font-size: .75rem; line-height: 1.2; border-radius: .2rem; }
      .room-card { border-color: var(--border-color); }
      .room-card .card-header { background-color: var(--bg-card); border-bottom: 1px solid var(--border-color); }
      .stud-item { transition: background-color .15s ease, box-shadow .15s ease; }
      .stud-item:hover { background-color: rgba(0,0,0,.03); box-shadow: 0 1px 0 rgba(0,0,0,.03) inset; }
      @media (max-width: 575.98px){
        #roomWiseForm .form-control-sm { min-width: 180px; }
      }
    </style>
    <?php if ($isAdmin || $isSAO || $isWarden): ?>
      <div class="mb-3 d-flex justify-content-end align-items-center" style="gap:.5rem">
        <a href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/hostel/BulkRoomAssign.php" class="btn btn-sm btn-primary">
          <i class="fa fa-users"></i> Bulk Assign
        </a>
        <a href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/hostel/ManualAllocate.php" class="btn btn-sm btn-outline-secondary">
          <i class="fa fa-user-plus"></i> Manual Allocate
        </a>
      </div>
    <?php endif; ?>

    <?php
      // Filters for room-wise cards
      $filterHostel = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;
      $filterBlock  = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;
      // Load hostels list
      $hostels = [];
      $hres = mysqli_query($con, "SELECT id, name FROM hostels WHERE active=1 ORDER BY name");
      while ($hres && ($row = mysqli_fetch_assoc($hres))) { $hostels[] = $row; }
    ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body py-3">
        <form method="get" id="roomWiseForm" class="form-inline align-items-center" style="gap:.5rem .75rem">
          <div class="form-group mb-2">
            <label for="hostel_id" class="mr-2 small text-muted">Hostel</label>
            <select name="hostel_id" id="hostel_id" class="form-control form-control-sm">
              <option value="0">-- Select Hostel --</option>
              <?php foreach ($hostels as $h): ?>
                <option value="<?php echo (int)$h['id']; ?>" <?php echo $filterHostel===(int)$h['id']?'selected':''; ?>><?php echo htmlspecialchars($h['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2">
            <label for="block_id" class="mr-2 small text-muted">Block</label>
            <select name="block_id" id="block_id" class="form-control form-control-sm" <?php echo $filterHostel>0?'':'disabled'; ?>>
              <option value="0">-- Select Block --</option>
            </select>
          </div>
          <button type="submit" class="btn btn-sm btn-outline-primary mb-2" <?php echo ($filterHostel>0 && $filterBlock>0)?'':'disabled'; ?>>View Room-wise</button>
        </form>
      </div>
    </div>

    <?php if ($filterBlock > 0): ?>
      <?php
        // Query room-wise students for selected block (similar to AllocatedRoomWise)
        $sql = "
          SELECT 
            r.id AS room_id,
            r.room_no,
            r.capacity,
            s.student_id,
            s.student_ininame,
            s.student_fullname,
            d.department_name,
            (SELECT COUNT(*) FROM hostel_allocations a2 WHERE a2.room_id=r.id AND a2.status='active') AS occupied
          FROM hostel_rooms r
          LEFT JOIN hostel_allocations a 
            ON a.room_id = r.id AND a.status = 'active'
          LEFT JOIN student s 
            ON s.student_id = a.student_id
          LEFT JOIN (
            SELECT se.student_id, MAX(se.student_enroll_date) AS max_enroll_date
            FROM student_enroll se
            GROUP BY se.student_id
          ) le ON le.student_id = s.student_id
          LEFT JOIN student_enroll e
            ON e.student_id = le.student_id AND e.student_enroll_date = le.max_enroll_date
          LEFT JOIN course c ON c.course_id = e.course_id
          LEFT JOIN department d ON d.department_id = c.department_id
          WHERE r.block_id = ?
          ORDER BY CAST(r.room_no AS UNSIGNED), r.room_no, s.student_ininame";
        $roomsData = [];
        if ($st = mysqli_prepare($con, $sql)) {
          mysqli_stmt_bind_param($st, 'i', $filterBlock);
          mysqli_stmt_execute($st);
          $rs = mysqli_stmt_get_result($st);
          while ($rs && $row = mysqli_fetch_assoc($rs)) { $roomsData[] = $row; }
          mysqli_stmt_close($st);
        }
        // Group by room
        $byRoom = [];
        foreach ($roomsData as $r) { $byRoom[$r['room_id']][] = $r; }
      ?>
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h5 class="mb-3">Room-wise Students</h5>
          <?php if (empty($byRoom)): ?>
            <div class="alert alert-info mb-0">No rooms or allocations found for the selected block.</div>
          <?php else: ?>
            <div class="row">
              <?php foreach ($byRoom as $roomId => $rows): $meta = $rows[0]; $occ = (int)($meta['occupied'] ?? 0); ?>
                <div class="col-12 col-lg-6 col-xl-4 mb-3">
                  <div class="room-card card h-100 shadow-sm">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                      <div class="font-weight-600">Room <?php echo htmlspecialchars($meta['room_no']); ?></div>
                      <span class="badge badge-light border"><span class="text-muted">Occupied</span> <?php echo (int)$occ; ?>/<?php echo (int)$meta['capacity']; ?></span>
                    </div>
                    <div class="card-body p-2 flex-grow-1">
                      <?php
                        $hadStudent = false;
                        foreach ($rows as $rr) {
                          if (empty($rr['student_id'])) continue;
                          $hadStudent = true;
                      ?>
                        <div class="stud-item border rounded p-2 mb-2">
                          <div class="d-flex justify-content-between align-items-center">
                            <div>
                              <div class="font-weight-600 mb-0"><?php echo htmlspecialchars($rr['student_ininame'] ?: $rr['student_fullname']); ?></div>
                              <div class="text-muted small"><?php echo htmlspecialchars($rr['student_id']); ?> · <?php echo htmlspecialchars($rr['department_name'] ?: ''); ?></div>
                            </div>
                            <button type="button" class="btn btn-xs btn-outline-info js-see-info" title="See details" data-student="<?php echo htmlspecialchars($rr['student_id']); ?>">
                              <i class="fa fa-eye"></i>
                            </button>
                          </div>
                        </div>
                      <?php } if (!$hadStudent): ?>
                        <div class="text-muted small">No students allocated</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
    <!-- Removed legacy allocations table as per requirements -->
   </div>
  </div>
</div>
</div>

<script>
(function(){
  // JS for room-wise filters: load blocks by selected hostel and toggle submit
  function qs(id){ return document.getElementById(id); }
  var hostel = qs('hostel_id');
  var block  = qs('block_id');
  var form   = qs('roomWiseForm');
  var base   = '<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>';
  var preBlock = <?php echo json_encode(isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0); ?>;

  function toggleBtn(){
    var btns = form ? form.querySelectorAll('button[type="submit"]') : [];
    var ok = hostel && block && hostel.value !== '0' && block.value !== '0';
    btns.forEach(function(b){ b.disabled = !ok; });
  }

  function loadBlocks(){
    if (!hostel || !block) return;
    var hid = hostel.value || '0';
    if (hid === '0') { block.innerHTML = '<option value="0">-- Select Block --</option>'; block.disabled = true; toggleBtn(); return; }
    fetch(base + '/hostel/blocks_api.php?hostel_id='+encodeURIComponent(hid))
      .then(function(r){ return r.json(); })
      .then(function(list){
        var opts = '<option value="0">-- Select Block --</option>';
        (list||[]).forEach(function(b){ opts += '<option value="'+b.id+'"'+ (preBlock==b.id?' selected':'') +'>'+ b.name +'</option>'; });
        block.innerHTML = opts;
        block.disabled = false;
        toggleBtn();
      })
      .catch(function(){ block.innerHTML = '<option value="0">-- Select Block --</option>'; block.disabled = true; toggleBtn(); });
  }

  if (hostel) {
    hostel.addEventListener('change', function(){ preBlock = 0; block.value='0'; loadBlocks(); });
    block && block.addEventListener('change', function(){ toggleBtn(); if (form && hostel.value !== '0' && block.value !== '0'){ form.submit(); } });
    loadBlocks();
    toggleBtn();
  }
})();
</script>

<!-- Student Info Modal -->
<div class="modal fade" id="studentInfoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Student Information</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="studentInfoBody">
          <div class="text-center text-muted">Loading...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
 </div>

<script>
// Ensure jQuery is loaded by footer.php before using it
window.addEventListener('load', function(){
  var $ = window.jQuery;
  if (!$) { return; }
  function esc(s){ return (s==null? '' : String(s)); }
  var modal = $('#studentInfoModal');
  $(document).on('click', '.js-see-info', function(){
    var sid = $(this).data('student');
    $('#studentInfoBody').html('<div class="text-center text-muted">Loading...</div>');
    modal.modal('show');
    $.get('../controller/StudentInfo.php', { student_id: sid }, function(resp){
      try {
        if (typeof resp === 'string') resp = JSON.parse(resp);
      } catch(e) { resp = { ok:false, message:'Unexpected response' }; }
      if (resp && resp.ok) {
        var h = ''+
          '<div class="row">'+
            '<div class="col-md-6 mb-3">'+
              '<div class="card h-100">'+
                '<div class="card-header bg-primary text-white">Personal</div>'+
                '<div class="card-body">'+
                  '<div><small class="text-muted d-block">Student ID</small><b>'+esc(resp.data.student_id)+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Full Name</small><b>'+esc(resp.data.student_fullname)+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Gender</small><b>'+esc(resp.data.student_gender)+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Email</small><b>'+esc(resp.data.student_email)+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Phone</small><b>'+esc(resp.data.student_phone)+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Address</small><b>'+esc(resp.data.student_address)+'</b></div>'+
                '</div>'+
              '</div>'+
            '</div>'+
            '<div class="col-md-6 mb-3">'+
              '<div class="card h-100">'+
                '<div class="card-header text-white" style="background-color: rgba(208, 3, 3, 0.98);">Emergency Contact</div>'+
                '<div class="card-body">'+
                  '<div><small class="text-muted d-block">Name</small><b>'+esc(resp.data.student_em_name || '—')+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Relation</small><b>'+esc(resp.data.student_em_relation || '—')+'</b></div>'+
                  '<div class="mt-2"><small class="text-muted d-block">Phone</small><b>'+esc(resp.data.student_em_phone || '—')+'</b></div>'+
                '</div>'+
              '</div>'+
            '</div>'+
          '</div>';
        $('#studentInfoBody').html(h);
      } else {
        var m = (resp && resp.message) ? resp.message : 'Failed to load info';
        $('#studentInfoBody').html('<div class="alert alert-warning">'+m+'</div>');
      }
    }).fail(function(){
      $('#studentInfoBody').html('<div class="alert alert-danger">Request failed. Please try again.</div>');
    });
  });
});
</script>

<!--END OF YOUR COD-->

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
