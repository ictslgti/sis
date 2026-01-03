<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "Hostel | SLGTI";
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
$deleteMessage = '';
if (isset($_GET['delete'])) {
  if (!$isAdmin) {
    $deleteMessage = '<div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
      <strong>Access Denied!</strong> You are not allowed to delete allocations.
      <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>';
  } else {
    $alloc_id = (int)$_GET['delete'];
    if ($alloc_id > 0) {
      if ($stDel = mysqli_prepare($con, "DELETE FROM hostel_allocations WHERE id = ?")) {
        mysqli_stmt_bind_param($stDel, 'i', $alloc_id);
        $ok = mysqli_stmt_execute($stDel);
        mysqli_stmt_close($stDel);
        if ($ok) {
          $deleteMessage = '<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <strong>Success!</strong> Allocation removed successfully.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>';
        } else {
          $deleteMessage = '<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <strong>Error!</strong> Failed to delete allocation: ' . htmlspecialchars(mysqli_error($con)) . '
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>';
        }
      } else {
        $deleteMessage = '<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
          <strong>Error!</strong> Failed to prepare delete statement.
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>';
      }
    }
  }
}

// Filters for room-wise cards
$filterHostel = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;
$filterBlock  = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;

// Load hostels list
$hostels = [];
$hres = mysqli_query($con, "SELECT id, name FROM hostels WHERE active=1 ORDER BY name");
while ($hres && ($row = mysqli_fetch_assoc($hres))) { $hostels[] = $row; }

// Load blocks for selected hostel
$blocks = [];
if ($filterHostel > 0) {
  $bres = mysqli_query($con, "SELECT id, name FROM hostel_blocks WHERE hostel_id = " . (int)$filterHostel . " AND active=1 ORDER BY name");
  while ($bres && ($row = mysqli_fetch_assoc($bres))) { $blocks[] = $row; }
}
?>

<div class="page-content">
  <div class="container-fluid" style="max-width: 100% !important; width: 100% !important; margin-left: 0 !important; margin-right: 0 !important; padding-left: 15px; padding-right: 15px;">
    <div class="row align-items-center mt-3 mb-3">
      <div class="col">
        <h4 class="d-flex align-items-center page-title mb-0" style="color: #1e293b; font-weight: 600;">
          <i class="far fa-building mr-2" style="color: #6366f1;"></i>
          Student Accommodation
        </h4>
      </div>
    </div>

    <?php echo $deleteMessage; ?>

    <style>
      /* Full width container */
      .page-content .container-fluid {
        max-width: 100% !important;
        width: 100% !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      
      @media (min-width: 576px) {
        .page-content .container-fluid {
          padding-left: 20px;
          padding-right: 20px;
        }
      }
      
      @media (min-width: 992px) {
        .page-content .container-fluid {
          padding-left: 30px;
          padding-right: 30px;
        }
      }
      
      /* Card header white text */
      .card-header {
        color: #ffffff !important;
      }
      .card-header * {
        color: #ffffff !important;
      }
      
      /* Form Label Styling */
      .form-label {
        display: block;
        font-weight: 600;
        font-size: 0.875rem;
        color: #374151;
        margin-bottom: 0.5rem;
        line-height: 1.5;
      }
      
      .form-label i {
        color: #6366f1;
        margin-right: 0.25rem;
      }
      
      /* Proper sizing for select dropdowns */
      .form-control.custom-select,
      select.form-control {
        display: block;
        width: 100%;
        height: calc(2.5rem + 2px);
        padding: 0.625rem 2.5rem 0.625rem 0.875rem;
        font-size: 0.9375rem;
        font-weight: 400;
        line-height: 1.5;
        color: #374151;
        background-color: #ffffff;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%236366f1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.875rem center;
        background-size: 16px 12px;
        border: 1.5px solid #d1d5db;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        cursor: pointer;
      }
      
      .form-control.custom-select:hover,
      select.form-control:hover {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
      }
      
      .form-control.custom-select:focus,
      select.form-control:focus {
        border-color: #6366f1;
        outline: 0;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        background-color: #ffffff;
      }
      
      .form-control.custom-select:disabled,
      select.form-control:disabled {
        background-color: #f3f4f6;
        color: #6b7280;
        cursor: not-allowed;
        opacity: 0.7;
      }
      
      /* Disable table hover effects */
      .table tbody tr:hover {
        background-color: transparent !important;
      }
      .table-striped tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.05) !important;
      }
      .table tbody tr:hover td {
        background-color: transparent !important;
      }
      
      /* Room card styling */
      .room-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        transition: box-shadow 0.2s ease;
      }
      
      .room-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      }
      
      .stud-item {
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background-color: #ffffff;
      }
      
      /* Button styling with proper colors */
      .btn-primary {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%);
        border: none;
        color: #ffffff !important;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        transition: all 0.3s ease;
      }
      
      .btn-primary:hover {
        background: linear-gradient(135deg, #818cf8 0%, #6366f1 50%, #22d3ee 100%);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        transform: translateY(-2px);
        color: #ffffff !important;
      }
      
      .btn-primary:disabled {
        background: #9ca3af;
        color: #ffffff !important;
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
      }
      
      .btn-secondary {
        background: #6b7280;
        border: none;
        color: #ffffff !important;
        font-weight: 500;
      }
      
      .btn-secondary:hover {
        background: #4b5563;
        color: #ffffff !important;
      }
      
      .btn-light {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #ffffff !important;
        font-weight: 500;
        backdrop-filter: blur(10px);
      }
      
      .btn-light:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        color: #ffffff !important;
      }
      
      .btn-outline-primary {
        border: 1.5px solid #6366f1;
        color: #6366f1 !important;
        background: transparent;
        font-weight: 500;
      }
      
      .btn-outline-primary:hover {
        background: #6366f1;
        color: #ffffff !important;
        border-color: #6366f1;
      }
      
      .btn-outline-info {
        border: 1.5px solid #06b6d4;
        color: #06b6d4 !important;
        background: transparent;
        font-weight: 500;
      }
      
      .btn-outline-info:hover {
        background: #06b6d4;
        color: #ffffff !important;
        border-color: #06b6d4;
      }
      
      .btn-outline-danger {
        border: 1.5px solid #ef4444;
        color: #ef4444 !important;
        background: transparent;
        font-weight: 500;
      }
      
      .btn-outline-danger:hover {
        background: #ef4444;
        color: #ffffff !important;
        border-color: #ef4444;
      }
      
      .btn-xs {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        line-height: 1.2;
        border-radius: 0.25rem;
      }
      
      /* Text colors for better readability */
      .text-muted {
        color: #6b7280 !important;
      }
      
      .text-primary {
        color: #6366f1 !important;
      }
      
      h4, h5, h6 {
        color: #1e293b;
        font-weight: 600;
      }
      
      .font-weight-600 {
        color: #1e293b;
        font-weight: 600;
      }
      
      /* Table text colors */
      .table td {
        color: #374151 !important;
      }
      
      .table th {
        color: #1e293b !important;
        font-weight: 600;
      }
      
      /* Badge colors */
      .badge-light {
        background-color: rgba(255, 255, 255, 0.9) !important;
        color: #374151 !important;
        border: 1px solid #e5e7eb;
      }
      
      .badge-primary {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #ffffff !important;
      }
      
      /* Responsive adjustments */
      @media (max-width: 575.98px) {
        .page-content .container-fluid {
          padding-left: 15px;
          padding-right: 15px;
        }
        
        .form-control.custom-select,
        select.form-control {
          height: calc(2.25rem + 2px);
          padding: 0.5rem 2rem 0.5rem 0.75rem;
          font-size: 0.875rem;
        }
        
        .hostel-actions {
          flex-direction: column !important;
          align-items: stretch !important;
        }
        
        .hostel-actions .btn {
          width: 100%;
          margin-left: 0 !important;
          margin-right: 0 !important;
        }
        
        .hostel-actions .btn + .btn {
          margin-top: 0.5rem;
        }
      }
    </style>

    <!-- Main Card -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff; padding: 1rem 1.25rem;">
        <div class="font-weight-semibold mb-2 mb-md-0" style="color: #ffffff !important;">
          <i class="far fa-building mr-1"></i> Student Accommodation Management
        </div>
        <?php if ($isAdmin || $isSAO || $isWarden): ?>
          <div class="d-flex hostel-actions" style="gap: 0.5rem;">
            <a href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/hostel/BulkRoomAssign.php" class="btn btn-sm btn-light">
              <i class="fa fa-users mr-1"></i> Bulk Assign
            </a>
            <a href="<?php echo (defined('APP_BASE')?APP_BASE:''); ?>/hostel/ManualAllocate.php" class="btn btn-sm btn-light">
              <i class="fa fa-user-plus mr-1"></i> Manual Allocate
            </a>
          </div>
        <?php endif; ?>
      </div>

      <div class="card-body" style="padding: 1.25rem;">
        <!-- Filters Card -->
        <div class="card shadow-sm border-0 mb-4">
          <div class="card-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff; padding: 0.875rem 1.25rem;">
            <div class="font-weight-semibold" style="color: #ffffff !important;">
              <i class="fas fa-filter mr-1"></i> Filter Options
            </div>
          </div>
          <div class="card-body" style="padding: 1.25rem;">
            <form method="get" id="roomWiseForm">
              <div class="form-row">
                <div class="form-group col-12 col-md-4 mb-3">
                  <label for="hostel_id" class="form-label">
                    <i class="fas fa-building mr-1"></i>Hostel
                  </label>
                  <select name="hostel_id" id="hostel_id" class="form-control custom-select">
                    <option value="0">-- Select Hostel --</option>
                    <?php foreach ($hostels as $h): ?>
                      <option value="<?php echo (int)$h['id']; ?>" <?php echo $filterHostel===(int)$h['id']?'selected':''; ?>><?php echo htmlspecialchars($h['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-4 mb-3">
                  <label for="block_id" class="form-label">
                    <i class="fas fa-cube mr-1"></i>Block
                  </label>
                  <select name="block_id" id="block_id" class="form-control custom-select" <?php echo $filterHostel>0?'':'disabled'; ?>>
                    <option value="0">-- Select Block --</option>
                    <?php foreach ($blocks as $b): ?>
                      <option value="<?php echo (int)$b['id']; ?>" <?php echo $filterBlock===(int)$b['id']?'selected':''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-12 col-md-4 mb-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary btn-block" <?php echo ($filterHostel>0 && $filterBlock>0)?'':'disabled'; ?>>
                    <i class="fas fa-search mr-2"></i>View Room-wise
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <?php if ($filterHostel > 0 && $filterBlock > 0): ?>
        <!-- Rooms list + Room info panel -->
        <div class="card shadow-sm border-0 mb-4">
          <div class="card-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff; padding: 0.875rem 1.25rem;">
            <div class="font-weight-semibold" style="color: #ffffff !important;">
              <i class="fas fa-door-open mr-1"></i> Room Management
            </div>
          </div>
          <div class="card-body" style="padding: 1.25rem;">
            <div class="row">
              <div class="col-12 col-md-4 mb-3 mb-md-0">
                <h6 class="mb-3" style="color: #374151; font-weight: 600;">
                  <i class="fas fa-list mr-1" style="color: #6366f1;"></i>Rooms
                </h6>
                <div id="roomList" class="list-group" style="max-height: 55vh; overflow-y: auto; border-radius: 0.5rem;">
                  <div class="small p-3 text-center" style="color: #9ca3af;">Loading rooms...</div>
                </div>
              </div>
              <div class="col-12 col-md-8">
                <h6 class="mb-3" style="color: #374151; font-weight: 600;">
                  <i class="fas fa-info-circle mr-1" style="color: #6366f1;"></i>Room Information
                </h6>
                <div id="roomInfo" class="border rounded p-3" style="min-height: 200px; background-color: #f9fafb;">
                  <div class="text-center" style="color: #9ca3af;">Select a room to see allocations.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($filterBlock > 0): ?>
          <?php
            // Query room-wise students for selected block
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
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff; padding: 0.875rem 1.25rem;">
              <div class="font-weight-semibold" style="color: #ffffff !important;">
                <i class="fas fa-users mr-1"></i> Room-wise Students
              </div>
            </div>
            <div class="card-body" style="padding: 1.25rem;">
              <?php if (empty($byRoom)): ?>
                <div class="alert alert-info mb-0" style="background-color: #dbeafe; border-color: #93c5fd; color: #1e40af;">
                  <i class="fas fa-info-circle mr-2"></i>No rooms or allocations found for the selected block.
                </div>
              <?php else: ?>
                <div class="row">
                  <?php foreach ($byRoom as $roomId => $rows): $meta = $rows[0]; $occ = (int)($meta['occupied'] ?? 0); ?>
                    <div class="col-12 col-lg-6 col-xl-4 mb-3">
                      <div class="room-card card h-100 shadow-sm">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); border-bottom: 1px solid #e5e7eb;">
                          <div class="font-weight-600" style="color: #1e293b;">Room <?php echo htmlspecialchars($meta['room_no']); ?></div>
                          <span class="badge badge-light border">
                            <span style="color: #6b7280;">Occupied</span> <strong style="color: #374151;"><?php echo (int)$occ; ?>/<?php echo (int)$meta['capacity']; ?></strong>
                          </span>
                        </div>
                        <div class="card-body p-3">
                          <?php
                            $hadStudent = false;
                            foreach ($rows as $rr) {
                              if (empty($rr['student_id'])) continue;
                              $hadStudent = true;
                          ?>
                            <div class="stud-item">
                              <div class="d-flex justify-content-between align-items-center">
                                <div class="flex-grow-1">
                                  <div class="font-weight-600 mb-1" style="color: #1e293b;"><?php echo htmlspecialchars($rr['student_ininame'] ?: $rr['student_fullname']); ?></div>
                                  <div class="small" style="color: #6b7280;"><?php echo htmlspecialchars($rr['student_id']); ?> · <?php echo htmlspecialchars($rr['department_name'] ?: ''); ?></div>
                                </div>
                                <div class="btn-group ml-2" role="group">
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
                            <div class="small text-center py-2" style="color: #9ca3af;">No students allocated</div>
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
    if (hid === '0') { 
      block.innerHTML = '<option value="0">-- Select Block --</option>'; 
      block.disabled = true; 
      toggleBtn(); 
      return; 
    }
    fetch(base + '/hostel/blocks_api.php?hostel_id='+encodeURIComponent(hid))
      .then(function(r){ return r.json(); })
      .then(function(list){
        var opts = '<option value="0">-- Select Block --</option>';
        (list||[]).forEach(function(b){ 
          opts += '<option value="'+b.id+'"'+ (preBlock==b.id?' selected':'') +'>'+ b.name +'</option>'; 
        });
        block.innerHTML = opts;
        block.disabled = false;
        toggleBtn();
      })
      .catch(function(){ 
        block.innerHTML = '<option value="0">-- Select Block --</option>'; 
        block.disabled = true; 
        toggleBtn(); 
      });
  }

  function renderRooms(list){
    if (!roomListEl) return;
    if (!Array.isArray(list) || list.length === 0){
      roomListEl.innerHTML = '<div class="small p-3 text-center" style="color: #9ca3af;">No rooms found.</div>';
      return;
    }
    var html = list.map(function(r){
      var occ = parseInt(r.occupied||0,10); 
      var cap = parseInt(r.capacity||0,10);
      var badge = '<span class="badge badge-light border ml-2">'+occ+'/'+cap+'</span>';
      var label = (r.room_no ? ('Room '+r.room_no) : ('#'+r.id));
      return (
        '<div class="room-list-item">'+
          '<a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-room="'+r.id+'">'+
            '<span>'+ label +'</span>'+ badge +
          '</a>'+
          '<div class="px-3 py-2 border-left border-right border-bottom d-none small" data-room-preview="'+r.id+'">'+
            '<a href="#" class="js-load-preview" data-room="'+r.id+'"><i class="fa fa-eye"></i> Preview students</a>' +
          '</div>'+
        '</div>'
      );
    }).join('');
    roomListEl.innerHTML = html;
    // bind clicks
    Array.prototype.forEach.call(roomListEl.querySelectorAll('a.list-group-item[data-room]'), function(a){
      a.addEventListener('click', function(e){ 
        e.preventDefault(); 
        var rid = this.getAttribute('data-room'); 
        showRoom(rid); 
        roomListEl.querySelectorAll('.active').forEach(function(x){ x.classList.remove('active'); }); 
        this.classList.add('active'); 
      });
    });
    // preview toggles
    Array.prototype.forEach.call(roomListEl.querySelectorAll('.js-load-preview[data-room]'), function(btn){
      btn.addEventListener('click', function(e){ 
        e.preventDefault(); 
        var rid = this.getAttribute('data-room'); 
        togglePreview(rid, this); 
      });
    });
    // auto-select first
    var first = roomListEl.querySelector('a.list-group-item[data-room]');
    if (first){ 
      first.classList.add('active'); 
      showRoom(first.getAttribute('data-room')); 
    }
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
            if (!data || !data.ok){ 
              cont.innerHTML = '<div class="text-muted small">Failed to load.</div>'; 
              return; 
            }
            var studs = Array.isArray(data.students) ? data.students : [];
            if (studs.length === 0){ 
              cont.innerHTML = '<div class="small" style="color: #9ca3af;">No students in this room.</div>'; 
            } else {
              var rows = studs.slice(0,5).map(function(s){
                var name = s.student_ininame || s.student_fullname || '';
                return '<div class="d-flex justify-content-between" style="color: #374151;">'+
                  '<span>'+ (s.student_id||'') +'</span>'+
                  '<span class="text-truncate ml-2" style="max-width: 140px;">'+ name +'</span>'+
                  '</div>';
              }).join('');
              var more = studs.length > 5 ? '<div style="color: #6b7280;">+'+(studs.length-5)+' more</div>' : '';
              cont.innerHTML = rows + more;
            }
            cont.setAttribute('data-loaded', '1');
          })
          .catch(function(){ 
            cont.innerHTML = '<div class="small" style="color: #9ca3af;">Failed to load.</div>'; 
          });
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
    if (bid === '0'){ 
      roomListEl.innerHTML = '<div class="small p-3 text-center" style="color: #9ca3af;">Select a block.</div>'; 
      return; 
    }
    fetch(base + '/hostel/rooms_api.php?block_id='+encodeURIComponent(bid))
      .then(function(r){ return r.json(); })
      .then(function(list){ renderRooms(list||[]); })
      .catch(function(){ 
        if(roomListEl){ 
          roomListEl.innerHTML = '<div class="small p-3 text-center" style="color: #9ca3af;">Failed to load rooms.</div>'; 
        } 
      });
  }

  function showRoom(roomId){
    if (!roomInfoEl) return;
    roomInfoEl.innerHTML = '<div class="text-center" style="color: #9ca3af;">Loading...</div>';
    fetch(base + '/controller/HostelRoomInfo.php?room_id='+encodeURIComponent(roomId))
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data || !data.ok){ 
          roomInfoEl.innerHTML = '<div class="alert alert-warning">Unable to load room info.</div>'; 
          return; 
        }
        var r = data.room || {}; 
        var studs = Array.isArray(data.students)? data.students : [];
        var header = '<div class="d-flex justify-content-between align-items-center mb-3">'+
                       '<div><b>Room '+ (r.room_no || r.id) +'</b> <span class="text-muted small">('+ (r.block_name||'') +' · '+ (r.hostel_name||'') +')</span></div>'+
                       '<span class="badge badge-light border">'+ (r.occupied||0) +'/'+ (r.capacity||0) +'</span>'+
                     '</div>';
        if (studs.length === 0){ 
          roomInfoEl.innerHTML = header + '<div class="small text-center" style="color: #9ca3af;">No active allocations in this room.</div>'; 
          return; 
        }
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
            var sid = this.getAttribute('data-sid'); 
            var from = this.getAttribute('data-from');
            if (!confirm('Mark student '+sid+' as left from this room?')) return;
            var fd = new FormData(); 
            fd.append('action','leave'); 
            fd.append('student_id', sid); 
            fd.append('room_id', from);
            fetch(base + '/controller/HostelAllocationActions.php', { method:'POST', body: fd })
              .then(function(r){ return r.json(); })
              .then(function(resp){
                if (!resp || !resp.ok){ 
                  alert((resp && resp.message) ? resp.message : 'Failed'); 
                  return; 
                }
                showRoom(r.id); 
                if (roomListEl) loadRooms();
              })
              .catch(function(){ alert('Request failed'); });
          });
        });
        Array.prototype.forEach.call(roomInfoEl.querySelectorAll('.js-move'), function(btn){
          btn.addEventListener('click', function(){
            var sid = this.getAttribute('data-sid'); 
            var from = this.getAttribute('data-from');
            openMoveModal(sid, from);
          });
        });
      })
      .catch(function(){ 
        roomInfoEl.innerHTML = '<div class="alert alert-danger">Failed to load room info.</div>'; 
      });
  }

  if (hostel) {
    hostel.addEventListener('change', function(){ 
      preBlock = 0; 
      block.value='0'; 
      loadBlocks(); 
    });
    block && block.addEventListener('change', function(){ 
      toggleBtn(); 
      if (form && hostel.value !== '0' && block.value !== '0'){ 
        form.submit(); 
      } 
    });
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
      <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff;">
        <h5 class="modal-title" style="color: #ffffff !important;">Move Student</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #ffffff;">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="moveForm">
          <input type="hidden" id="mvStudentId">
          <input type="hidden" id="mvFromRoom">
          <div class="form-group">
            <label for="mvToRoom" class="form-label">Target Room</label>
            <select id="mvToRoom" class="form-control custom-select"></select>
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
      if (bid === '0') { 
        $to.empty().append($('<option>').text('Select a block first').attr('value','')); 
        $modal.modal('show'); 
        return; 
      }
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
        .catch(function(){ 
          $to.empty().append($('<option>').text('Failed to load rooms').attr('value','')); 
          $modal.modal('show'); 
        });
    } catch(e) { $modal.modal('show'); }
  };

  $('#mvSubmit').on('click', function(){
    var toRoom = $to.val(); var sid = $sid.val(); var fromRoom = $from.val();
    if (!toRoom) { alert('Please select a target room'); return; }
    var fd = new FormData(); 
    fd.append('action','move'); 
    fd.append('student_id', sid); 
    fd.append('from_room_id', fromRoom); 
    fd.append('to_room_id', toRoom);
    var base = '<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>';
    fetch(base + '/controller/HostelAllocationActions.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(resp){
        if (!resp || !resp.ok){ 
          alert((resp && resp.message) ? resp.message : 'Move failed'); 
          return; 
        }
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
      <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #06b6d4 100%); color: #ffffff;">
        <h5 class="modal-title" style="color: #ffffff !important;">Student Information</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #ffffff;">
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

<!--END OF YOUR CODE-->

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->
