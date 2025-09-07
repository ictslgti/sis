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
  <div class="card">
    <div class="card-header bg-info text-white">
      <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between">
        <div class="mb-2 mb-sm-0 d-flex align-items-center">
          <i class="fas fa-user-graduate mr-2"></i>
          <span class="h6 mb-0">Student Accommodation</span>
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
      .hostel-actions .btn { min-width: 140px; }
      @media (max-width: 575.98px){
        /* Buttons stack full-width on mobile */
        .hostel-actions { flex-direction: column !important; align-items: stretch !important; }
        .hostel-actions .btn { width: 100%; margin-left: 0 !important; margin-right: 0 !important; }
        .hostel-actions .btn + .btn { margin-top: .5rem; }
        /* Form controls comfortable width */
        #roomWiseForm .form-group { width: 100%; }
        #roomWiseForm label { margin-bottom: .25rem; }
        #roomWiseForm .form-control-sm { width: 100%; min-width: 0; }
      }
    </style>
    <?php if ($isAdmin || $isSAO || $isWarden): ?>
      <div class="mb-3 d-flex hostel-actions justify-content-center justify-content-md-end align-items-center" style="gap:.5rem">
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
        <form method="get" id="roomWiseForm">
          <div class="form-row">
            <div class="form-group col-12 col-sm-6 col-md-4">
              <label for="hostel_id" class="small text-muted">Hostel</label>
              <select name="hostel_id" id="hostel_id" class="form-control form-control-sm">
                <option value="0">-- Select Hostel --</option>
                <?php foreach ($hostels as $h): ?>
                  <option value="<?php echo (int)$h['id']; ?>" <?php echo $filterHostel===(int)$h['id']?'selected':''; ?>><?php echo htmlspecialchars($h['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-12 col-sm-6 col-md-4">
              <label for="block_id" class="small text-muted">Block</label>
              <select name="block_id" id="block_id" class="form-control form-control-sm" <?php echo $filterHostel>0?'':'disabled'; ?>>
                <option value="0">-- Select Block --</option>
              </select>
            </div>
            <div class="form-group col-12 col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-sm btn-outline-primary w-100" <?php echo ($filterHostel>0 && $filterBlock>0)?'':'disabled'; ?>>View Room-wise</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <?php if ($filterHostel > 0 && $filterBlock > 0): ?>
    <!-- Rooms list + Room info panel -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-12 col-md-4 mb-3 mb-md-0">
            <h6 class="text-muted">Rooms</h6>
            <div id="roomList" class="list-group" style="max-height: 55vh; overflow:auto">
              <div class="text-muted small">Loading rooms...</div>
            </div>
          </div>
          <div class="col-12 col-md-8">
            <h6 class="text-muted">Room Information</h6>
            <div id="roomInfo" class="border rounded p-3">
              <div class="text-muted">Select a room to see allocations.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

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
                            <div class="btn-group" role="group">
                              <button type="button" class="btn btn-xs btn-outline-info js-see-info" title="See details" data-student="<?php echo htmlspecialchars($rr['student_id']); ?>">
                                <i class="fa fa-eye"></i>
                              </button>
                              <button type="button" class="btn btn-xs btn-outline-danger js-leave-card" title="Mark as left"
                                      data-student="<?php echo htmlspecialchars($rr['student_id']); ?>"
                                      data-room="<?php echo (int)$meta['room_id']; ?>">
                                <i class="far fa-trash-alt"></i>
                              </button>
                            </div>
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
  var roomListEl = document.getElementById('roomList');
  var roomInfoEl = document.getElementById('roomInfo');

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

  function renderRooms(list){
    if (!roomListEl) return;
    if (!Array.isArray(list) || list.length === 0){
      roomListEl.innerHTML = '<div class="text-muted small">No rooms found.</div>';
      return;
    }
    var html = list.map(function(r){
      var occ = parseInt(r.occupied||0,10); var cap = parseInt(r.capacity||0,10);
      var badge = '<span class="badge badge-light border ml-2">'+occ+'/'+cap+'</span>';
      var label = (r.room_no ? ('Room '+r.room_no) : ('#'+r.id));
      return (
        '<div class="room-list-item">'+
          '<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-room="'+r.id+'">'+
            '<span>'+ label +'</span>'+ badge +
          '</a>'+
          '<div class="px-3 py-2 border-left border-right border-bottom d-none small" data-room-preview="'+r.id+'">'
            + '<a href="#" class="js-load-preview" data-room="'+r.id+'"><i class="fa fa-eye"></i> Preview students</a>' +
          '</div>'+
        '</div>'
      );
    }).join('');
    roomListEl.innerHTML = html;
    // bind clicks
    Array.prototype.forEach.call(roomListEl.querySelectorAll('a.list-group-item[data-room]'), function(a){
      a.addEventListener('click', function(e){ e.preventDefault(); var rid = this.getAttribute('data-room'); showRoom(rid); roomListEl.querySelectorAll('.active').forEach(function(x){ x.classList.remove('active'); }); this.classList.add('active'); });
    });
    // preview toggles
    Array.prototype.forEach.call(roomListEl.querySelectorAll('.js-load-preview[data-room]'), function(btn){
      btn.addEventListener('click', function(e){ e.preventDefault(); var rid = this.getAttribute('data-room'); togglePreview(rid, this); });
    });
    // auto-select first
    var first = roomListEl.querySelector('a.list-group-item[data-room]');
    if (first){ first.classList.add('active'); showRoom(first.getAttribute('data-room')); }
  }

  function togglePreview(roomId, triggerEl){
    var cont = roomListEl && roomListEl.querySelector('[data-room-preview="'+roomId+'"]');
    if (!cont) return;
    var isHidden = cont.classList.contains('d-none');
    if (isHidden) {
      // load preview once
      if (!cont.getAttribute('data-loaded')){
        cont.innerHTML = '<div class="text-muted small">Loading...</div>';
        fetch(base + '/controller/HostelRoomInfo.php?room_id='+encodeURIComponent(roomId))
          .then(function(r){ return r.json(); })
          .then(function(data){
            if (!data || !data.ok){ cont.innerHTML = '<div class="text-muted small">Failed to load.</div>'; return; }
            var studs = Array.isArray(data.students) ? data.students : [];
            if (studs.length === 0){ cont.innerHTML = '<div class="text-muted small">No students in this room.</div>'; }
            else {
              var rows = studs.slice(0,5).map(function(s){
                var name = s.student_ininame || s.student_fullname || '';
                return '<div class="d-flex justify-content-between">'
                  + '<span>'+ (s.student_id||'') +'</span>'
                  + '<span class="text-truncate ml-2" style="max-width: 140px;">'+ name +'</span>'
                  + '</div>';
              }).join('');
              var more = studs.length > 5 ? '<div class="text-muted">+'+(studs.length-5)+' more</div>' : '';
              cont.innerHTML = rows + more;
            }
            cont.setAttribute('data-loaded', '1');
          })
          .catch(function(){ cont.innerHTML = '<div class="text-muted small">Failed to load.</div>'; });
      }
      cont.classList.remove('d-none');
      if (triggerEl) triggerEl.innerHTML = '<i class="fa fa-eye-slash"></i> Hide preview';
    } else {
      cont.classList.add('d-none');
      if (triggerEl) triggerEl.innerHTML = '<i class="fa fa-eye"></i> Preview students';
    }
  }

  function loadRooms(){
    if (!roomListEl || !block) return;
    var bid = block.value || '0';
    if (bid === '0'){ roomListEl.innerHTML = '<div class="text-muted small">Select a block.</div>'; return; }
    fetch(base + '/hostel/rooms_api.php?block_id='+encodeURIComponent(bid))
      .then(function(r){ return r.json(); })
      .then(function(list){ renderRooms(list||[]); })
      .catch(function(){ if(roomListEl){ roomListEl.innerHTML = '<div class="text-muted small">Failed to load rooms.</div>'; } });
  }

  function showRoom(roomId){
    if (!roomInfoEl) return;
    roomInfoEl.innerHTML = '<div class="text-muted">Loading...</div>';
    fetch(base + '/controller/HostelRoomInfo.php?room_id='+encodeURIComponent(roomId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data || !data.ok){ roomInfoEl.innerHTML = '<div class="alert alert-warning">Unable to load room info.</div>'; return; }
        var r = data.room || {}; var studs = Array.isArray(data.students)? data.students : [];
        var header = '<div class="d-flex justify-content-between align-items-center mb-2">'+
                       '<div><b>Room '+ (r.room_no || r.id) +'</b> <span class="text-muted small">('+ (r.block_name||'') +' · '+ (r.hostel_name||'') +')</span></div>'+
                       '<span class="badge badge-light border">'+ (r.occupied||0) +'/'+ (r.capacity||0) +'</span>'+
                     '</div>';
        if (studs.length === 0){ roomInfoEl.innerHTML = header + '<div class="text-muted small">No active allocations in this room.</div>'; return; }
        var rows = studs.map(function(s){
          var name = s.student_ininame || s.student_fullname || '';
          var meta = [s.student_id, s.department_name||'', s.course_name||''].filter(Boolean).join(' · ');
          return '<tr>'+
                   '<td>'+ (s.student_id||'') +'</td>'+
                   '<td>'+ (name) +'</td>'+
                   '<td>'+ (s.student_gender||'') +'</td>'+
                   '<td class="text-nowrap">'+
                      '<div class="btn-group btn-group-sm" role="group">'+
                        '<button type="button" class="btn btn-outline-primary js-move" data-sid="'+(s.student_id||'')+'" data-from="'+(r.id)+'"><i class="fa fa-exchange-alt"></i> Move</button>'+
                        '<button type="button" class="btn btn-outline-danger js-leave" data-sid="'+(s.student_id||'')+'" data-from="'+(r.id)+'"><i class="fa fa-sign-out-alt"></i> Leave</button>'+
                      '</div>'+
                   '</td>'+
                 '</tr>';
        }).join('');
        roomInfoEl.innerHTML = header +
          '<div class="table-responsive">'+
            '<table class="table table-sm table-striped mb-0">'+
              '<thead><tr><th>ID</th><th>Name</th><th>Gender</th><th>Actions</th></tr></thead>'+
              '<tbody>'+rows+'</tbody>'+
            '</table>'+
          '</div>';
        // Bind action buttons
        Array.prototype.forEach.call(roomInfoEl.querySelectorAll('.js-leave'), function(btn){
          btn.addEventListener('click', function(){
            var sid = this.getAttribute('data-sid'); var from = this.getAttribute('data-from');
            if (!confirm('Mark student '+sid+' as left from this room?')) return;
            var fd = new FormData(); fd.append('action','leave'); fd.append('student_id', sid); fd.append('room_id', from);
            fetch(base + '/controller/HostelAllocationActions.php', { method:'POST', body: fd })
              .then(function(r){ return r.json(); })
              .then(function(resp){
                if (!resp || !resp.ok){ alert((resp && resp.message) ? resp.message : 'Failed'); return; }
                showRoom(r.id); if (roomListEl) loadRooms();
              })
              .catch(function(){ alert('Request failed'); });
          });
        });
        Array.prototype.forEach.call(roomInfoEl.querySelectorAll('.js-move'), function(btn){
          btn.addEventListener('click', function(){
            var sid = this.getAttribute('data-sid'); var from = this.getAttribute('data-from');
            openMoveModal(sid, from);
          });
        });
      })
      .catch(function(){ roomInfoEl.innerHTML = '<div class="alert alert-danger">Failed to load room info.</div>'; });
  }

  if (hostel) {
    hostel.addEventListener('change', function(){ preBlock = 0; block.value='0'; loadBlocks(); });
    block && block.addEventListener('change', function(){ toggleBtn(); if (form && hostel.value !== '0' && block.value !== '0'){ form.submit(); } });
    loadBlocks();
    toggleBtn();
    // If rooms panel is present on page load, populate it
    if (roomListEl) { loadRooms(); }
  }
})();
</script>

<!-- Move Student Modal -->
<div class="modal fade" id="moveModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Move Student</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="moveForm">
          <input type="hidden" id="mvStudentId">
          <input type="hidden" id="mvFromRoom">
          <div class="form-group">
            <label for="mvToRoom">Target Room</label>
            <select id="mvToRoom" class="form-control"></select>
            <small class="form-text text-muted">Only rooms in the selected block are listed.</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="mvSubmit">Move</button>
      </div>
    </div>
  </div>
</div>

<script>
// Move modal logic (uses jQuery from footer.php)
(function(){
  var $ = window.jQuery; if (!$) return;
  var $modal = $('#moveModal');
  var $to = $('#mvToRoom'); var $sid = $('#mvStudentId'); var $from = $('#mvFromRoom');

  window.openMoveModal = function(studentId, fromRoomId){
    try {
      $sid.val(studentId); $from.val(fromRoomId);
      $to.empty().append($('<option>').text('Loading...').attr('value',''));
      var base = '<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>';
      var block = document.getElementById('block_id'); var bid = block ? (block.value||'0') : '0';
      if (bid === '0') { $to.empty().append($('<option>').text('Select a block first').attr('value','')); $modal.modal('show'); return; }
      fetch(base + '/hostel/rooms_api.php?block_id='+encodeURIComponent(bid))
        .then(function(r){ return r.json(); })
        .then(function(list){
          $to.empty().append($('<option>').text('-- Select Room --').attr('value',''));
          (list||[]).forEach(function(r){
            var txt = (r.room_no ? ('Room '+r.room_no) : ('#'+r.id)) + ' ('+ (r.occupied||0)+'/'+(r.capacity||0)+')';
            $to.append($('<option>').attr('value', r.id).text(txt));
          });
          $modal.modal('show');
        })
        .catch(function(){ $to.empty().append($('<option>').text('Failed to load rooms').attr('value','')); $modal.modal('show'); });
    } catch(e) { $modal.modal('show'); }
  };

  $('#mvSubmit').on('click', function(){
    var toRoom = $to.val(); var sid = $sid.val(); var fromRoom = $from.val();
    if (!toRoom) { alert('Please select a target room'); return; }
    var fd = new FormData(); fd.append('action','move'); fd.append('student_id', sid); fd.append('from_room_id', fromRoom); fd.append('to_room_id', toRoom);
    var base = '<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>';
    fetch(base + '/controller/HostelAllocationActions.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(resp){
        if (!resp || !resp.ok){ alert((resp && resp.message) ? resp.message : 'Move failed'); return; }
        $modal.modal('hide');
        // Refresh UI
        var active = document.querySelector('#roomList a.list-group-item.active');
        var currentRoom = active ? active.getAttribute('data-room') : null;
        if (currentRoom) { showRoom(currentRoom); }
        if (typeof loadRooms === 'function') loadRooms();
      })
      .catch(function(){ alert('Request failed'); });
  });
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
  // Eye icon: show student info modal
  $(document).on('click', '.js-see-info', function(){
    var sid = $(this).data('student');
    if (!sid) return;
    $('#studentInfoBody').html('<div class="text-center text-muted">Loading...</div>');
    modal.modal('show');
    $.get('../controller/StudentInfo.php', { student_id: sid }, function(resp){
      try { if (typeof resp === 'string') resp = JSON.parse(resp); } catch(e) { resp = { ok:false, message:'Unexpected response' }; }
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
  // Replace eye action with leave action on room-wise cards
  $(document).on('click', '.js-leave-card', function(){
    var sid = $(this).data('student');
    var rid = $(this).data('room');
    if (!sid || !rid) return;
    if (!confirm('Mark '+sid+' as left from this room?')) return;
    var fd = new FormData();
    fd.append('action','leave');
    fd.append('student_id', sid);
    fd.append('room_id', rid);
    fetch('<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/controller/HostelAllocationActions.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(resp){
        if (!resp || !resp.ok) { alert((resp && resp.message) ? resp.message : 'Failed'); return; }
        // Refresh page state: reload same block
        try { window.location.reload(); } catch(e) {}
      })
      .catch(function(){ alert('Request failed'); });
  });
});
</script>

<!--END OF YOUR COD-->

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
