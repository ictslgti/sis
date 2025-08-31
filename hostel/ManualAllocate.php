<?php
// hostel/ManualAllocate.php - Warden/Admin manual allocation without prior request
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','WAR'])) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';

// Determine warden gender if WAR (to filter hostels dropdown)
$wardenGender = null;
if ($_SESSION['user_type'] === 'WAR' && !empty($_SESSION['user_name'])) {
  if ($st = mysqli_prepare($con, 'SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1')) {
    mysqli_stmt_bind_param($st, 's', $_SESSION['user_name']);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    if ($rs && ($row = mysqli_fetch_assoc($rs))) { $wardenGender = $row['staff_gender']; }
    mysqli_stmt_close($st);
  }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<div class="container mt-4">
  <h3>Manual Hostel Allocation</h3>
  <div class="alert alert-info">Assign a student to a room without a prior hostel request. Capacity and gender rules apply.</div>

  <form method="POST" action="<?php echo $base; ?>/controller/HostelManualAllocate.php">
    <div class="form-row">
      <div class="form-group col-md-5">
        <label>Student</label>
        <select name="student_id" class="form-control" required>
          <option value="">Select student</option>
          <?php
          // If WAR, limit students by warden gender; ADM sees all
          if ($_SESSION['user_type'] === 'WAR' && $wardenGender) {
            if ($st = mysqli_prepare($con, "SELECT s.student_id, s.student_fullname FROM student s WHERE s.student_gender = ? ORDER BY s.student_fullname, s.student_id")) {
              mysqli_stmt_bind_param($st, 's', $wardenGender);
              mysqli_stmt_execute($st);
              $rs = mysqli_stmt_get_result($st);
              while ($rs && ($s = mysqli_fetch_assoc($rs))) {
                echo '<option value="'.h($s['student_id']).'">'.h($s['student_fullname']).' ('.h($s['student_id']).')</option>';
              }
              mysqli_stmt_close($st);
            }
          } else {
            $stq = mysqli_query($con, "SELECT s.student_id, s.student_fullname FROM student s ORDER BY s.student_fullname, s.student_id");
            while ($stq && ($s = mysqli_fetch_assoc($stq))) {
              echo '<option value="'.h($s['student_id']).'">'.h($s['student_fullname']).' ('.h($s['student_id']).')</option>';
            }
          }
          ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Allocated Date</label>
        <input type="date" class="form-control" name="allocated_at" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
      <div class="form-group col-md-3">
        <label>Leaving Date</label>
        <input type="date" class="form-control" name="leaving_at">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Hostel</label>
        <select name="hostel_id" id="hostel_id" class="form-control" required>
          <option value="">Select...</option>
          <?php
          // Build allowed genders set: Mixed + warden gender (if WAR)
          $expand = function($g) {
            if (!$g) return [];
            $g = trim($g);
            if (strcasecmp($g,'Male')===0) return ['Male','Boys','Boy'];
            if (strcasecmp($g,'Female')===0) return ['Female','Girls','Girl','Ladies'];
            if (strcasecmp($g,'Mixed')===0) return ['Mixed'];
            if (strcasecmp($g,'Boys')===0 || strcasecmp($g,'Boy')===0) return ['Male','Boys','Boy'];
            if (strcasecmp($g,'Girls')===0 || strcasecmp($g,'Girl')===0 || strcasecmp($g,'Ladies')===0) return ['Female','Girls','Girl','Ladies'];
            return [$g];
          };
          $allowedSet = ['Mixed' => true];
          $wg = $wardenGender;
          if ($wg) { $wg = (strcasecmp($wg,'male')===0?'Male':(strcasecmp($wg,'female')===0?'Female':$wg)); }
          foreach ($expand($wg) as $v) { $allowedSet[$v] = true; }
          $allowed = array_keys($allowedSet);
          $ph = implode(',', array_fill(0, count($allowed), '?'));
          $sqlH = "SELECT id, name FROM hostels WHERE active=1 AND gender IN ($ph) ORDER BY name";
          if ($stH = mysqli_prepare($con, $sqlH)) {
            $types = str_repeat('s', count($allowed));
            mysqli_stmt_bind_param($stH, $types, ...$allowed);
            mysqli_stmt_execute($stH);
            $resH = mysqli_stmt_get_result($stH);
            while ($resH && ($h = mysqli_fetch_assoc($resH))) {
              echo '<option value="'.(int)$h['id'].'">'.h($h['name']).'</option>';
            }
            mysqli_stmt_close($stH);
          }
          ?>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label>Block</label>
        <select name="block_id" id="block_id" class="form-control" required>
          <option value="">Select hostel first</option>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label>Room</label>
        <select name="room_id" id="room_id" class="form-control" required>
          <option value="">Select block first</option>
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Allocate</button>
    <a href="<?php echo $base; ?>/hostel/Hostel.php" class="btn btn-secondary ml-2">Back</a>
  </form>
</div>

<script>
const base = '<?php echo $base; ?>';
const hostelSel = document.getElementById('hostel_id');
const blockSel = document.getElementById('block_id');
const roomSel = document.getElementById('room_id');

hostelSel && hostelSel.addEventListener('change', async (e) => {
  blockSel.innerHTML = '<option value="">Loading...</option>';
  roomSel.innerHTML = '<option value="">Select block first</option>';
  const hid = e.target.value;
  if (!hid) { blockSel.innerHTML = '<option value="">Select...</option>'; return; }
  const url = base + '/hostel/blocks_api.php?hostel_id=' + encodeURIComponent(hid);
  const r = await fetch(url);
  const data = await r.json();
  blockSel.innerHTML = '<option value="">Select...</option>' + data.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
});

blockSel && blockSel.addEventListener('change', async (e) => {
  roomSel.innerHTML = '<option value="">Loading...</option>';
  const bid = e.target.value;
  if (!bid) { roomSel.innerHTML = '<option value="">Select...</option>'; return; }
  const url = base + '/hostel/rooms_api.php?block_id=' + encodeURIComponent(bid);
  const r = await fetch(url);
  const data = await r.json();
  roomSel.innerHTML = '<option value="">Select...</option>' + data.map(rm => `<option value="${rm.id}">${rm.room_no} (cap ${rm.capacity}, occ ${rm.occupied})</option>`).join('');
});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
