<?php
// hostel/AllocatedRoomWise.php
// One form to view allocated hostel students, block/room-wise with student and department names

require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$title = 'Hostel Allocations - Room-wise List | SLGTI';
include_once __DIR__ . '/../head.php';
include_once __DIR__ . '/../menu.php';

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Selected filters
$hostelId = isset($_GET['hostel_id']) ? (int)$_GET['hostel_id'] : 0;
$blockId  = isset($_GET['block_id'])  ? (int)$_GET['block_id']  : 0;

// Load hostels for dropdown
$hostels = [];
$hres = mysqli_query($con, "SELECT id, name FROM hostels WHERE active=1 ORDER BY name");
while ($hres && $row = mysqli_fetch_assoc($hres)) { $hostels[] = $row; }

// When a block is selected, fetch room-wise allocations
$rooms = [];
if ($blockId > 0) {
    // Build latest enrollment subquery to obtain department for each student
    $sql = "
    SELECT 
      r.id AS room_id,
      r.room_no,
      r.capacity,
      s.student_id,
      s.student_ininame,
      s.student_fullname,
      d.department_name
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
    ORDER BY CAST(r.room_no AS UNSIGNED), r.room_no, s.student_ininame
    ";
    if ($st = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($st, 'i', $blockId);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        while ($rs && $row = mysqli_fetch_assoc($rs)) {
            $rooms[] = $row;
        }
        mysqli_stmt_close($st);
    }
}

?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h4 class="mb-0">Allocated Students - Room-wise</h4>
      <small class="text-muted">Select hostel and block to view allocated students per room with department</small>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="get" id="filtersForm" class="form-inline">
        <div class="form-group mr-2 mb-2">
          <label for="hostel_id" class="mr-2 small">Hostel</label>
          <select name="hostel_id" id="hostel_id" class="form-control">
            <option value="0">-- Select Hostel --</option>
            <?php foreach ($hostels as $h): ?>
              <option value="<?php echo (int)$h['id']; ?>" <?php echo $hostelId===(int)$h['id']?'selected':''; ?>><?php echo esc($h['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mr-2 mb-2">
          <label for="block_id" class="mr-2 small">Block</label>
          <select name="block_id" id="block_id" class="form-control" <?php echo $hostelId>0?'':'disabled'; ?>>
            <option value="0">-- Select Block --</option>
          </select>
        </div>
        <button type="submit" id="viewBtn" class="btn btn-primary mb-2" <?php echo ($hostelId>0 && $blockId>0)?'':'disabled'; ?>>View</button>
      </form>
    </div>
  </div>

  <?php if ($blockId > 0): ?>
  <div class="card shadow-sm">
    <div class="card-body">
      <?php if (empty($rooms)): ?>
        <div class="alert alert-info mb-0">No allocations found for the selected block.</div>
      <?php else: ?>
        <?php
          // Group by room
          $byRoom = [];
          foreach ($rooms as $r) { $byRoom[$r['room_id']][] = $r; }
        ?>
        <div class="row">
          <?php foreach ($byRoom as $roomId => $rows): 
              $meta = $rows[0];
              $occupied = 0; foreach ($rows as $rr) { if (!empty($rr['student_id'])) $occupied++; }
          ?>
            <div class="col-lg-4 col-md-6 mb-3">
              <div class="border rounded h-100">
                <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                  <div>
                    <strong>Room:</strong> <?php echo esc($meta['room_no']); ?>
                  </div>
                  <div class="small text-muted">
                    <?php echo (int)$occupied; ?>/<?php echo (int)$meta['capacity']; ?> occupied
                  </div>
                </div>
                <div class="p-2">
                  <?php if ($occupied === 0): ?>
                    <div class="text-muted small">No students allocated</div>
                  <?php else: ?>
                    <ul class="list-unstyled mb-0">
                      <?php foreach ($rows as $rr): if (empty($rr['student_id'])) continue; ?>
                        <li class="mb-1">
                          <div class="d-flex justify-content-between">
                            <span>
                              <?php echo esc($rr['student_ininame'] ?: $rr['student_fullname']); ?>
                              <span class="text-muted small">(<?php echo esc($rr['student_id']); ?>)</span>
                            </span>
                            <span class="small text-muted text-right"><?php echo esc($rr['department_name']); ?></span>
                          </div>
                        </li>
                      <?php endforeach; ?>
                    </ul>
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

<script>
(function(){
  function qs(id){ return document.getElementById(id); }
  var hostel = qs('hostel_id');
  var block  = qs('block_id');
  var viewBtn = qs('viewBtn');
  var form = qs('filtersForm');

  function toggleView(){
    if (!viewBtn) return;
    var ok = hostel && block && hostel.value !== '0' && block.value !== '0';
    viewBtn.disabled = !ok;
  }

  function loadBlocks(){
    if (!hostel || !block) return;
    var hid = hostel.value || '0';
    if (hid === '0') { block.innerHTML = '<option value="0">-- Select Block --</option>'; block.disabled = true; toggleView(); return; }
    fetch('<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/hostel/blocks_api.php?hostel_id='+encodeURIComponent(hid))
      .then(r => r.json())
      .then(list => {
        var opts = '<option value="0">-- Select Block --</option>';
        (list||[]).forEach(function(b){ opts += '<option value="'+b.id+'"'+ (<?php echo json_encode($blockId); ?>==b.id?' selected':'') +'>'+ b.name +'</option>'; });
        block.innerHTML = opts;
        block.disabled = false;
        toggleView();
      })
      .catch(() => {
        block.innerHTML = '<option value="0">-- Select Block --</option>';
        block.disabled = true;
        toggleView();
      });
  }

  if (hostel) {
    hostel.addEventListener('change', function(){ block.value='0'; loadBlocks(); });
    block && block.addEventListener('change', function(){
      toggleView();
      if (form && hostel.value !== '0' && block.value !== '0') { form.submit(); }
    });
    // initial
    loadBlocks();
    toggleView();
  }
})();
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
