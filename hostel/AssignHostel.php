<?php
// hostel/AssignHostel.php - Assign a paid request to a room
$title = "Assign Hostel | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'])) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
  include_once("../footer.php");
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';
$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';

// Determine warden gender if WAR
$wardenGender = null;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'WAR' && !empty($_SESSION['user_name'])) {
  if ($st = mysqli_prepare($con, "SELECT staff_gender FROM staff WHERE staff_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($st, 's', $_SESSION['user_name']);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    if ($rs) { $r = mysqli_fetch_assoc($rs); if ($r && isset($r['staff_gender'])) { $wardenGender = $r['staff_gender']; } }
    mysqli_stmt_close($st);
  }
}

// Load request ensure it's paid
$req = null;
if ($student_id !== '') {
  $st = mysqli_prepare($con, "SELECT student_id, distance_km, status FROM hostel_requests WHERE student_id=?");
  mysqli_stmt_bind_param($st, 's', $student_id);
  mysqli_stmt_execute($st);
  $res = mysqli_stmt_get_result($st);
  $req = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($st);
}

// Load student basic details (name, gender, dept/phone optional)
$stu = null; $stuGender = null;
if ($student_id !== '') {
  if ($st2 = mysqli_prepare($con, "SELECT student_fullname, student_gender, student_phone, student_department FROM student WHERE student_id=? LIMIT 1")) {
    mysqli_stmt_bind_param($st2, 's', $student_id);
    mysqli_stmt_execute($st2);
    $rs2 = mysqli_stmt_get_result($st2);
    $stu = $rs2 ? mysqli_fetch_assoc($rs2) : null;
    mysqli_stmt_close($st2);
    if ($stu && isset($stu['student_gender'])) {
      $g = trim((string)$stu['student_gender']);
      if (strcasecmp($g, 'male')===0) { $stuGender = 'Male'; }
      elseif (strcasecmp($g, 'female')===0) { $stuGender = 'Female'; }
      elseif (strcasecmp($g, 'mixed')===0) { $stuGender = 'Mixed'; } // unlikely but normalize
      else { $stuGender = $g !== '' ? $g : null; }
    }
  }
}
?>
<div class="container mt-4">
  <h3>Assign Hostel</h3>
  <?php if (!$req): ?>
    <div class="alert alert-warning">No request found for this student.</div>
  <?php elseif ($req['status'] !== 'paid'): ?>
    <div class="alert alert-info">Request status is <?php echo htmlspecialchars($req['status']); ?>. Only 'paid' requests can be assigned.</div>
  <?php endif; ?>

  <?php if ($stu): ?>
    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center">
          <div class="mr-3"><strong>ID:</strong> <?php echo htmlspecialchars($student_id); ?></div>
          <div class="mr-3"><strong>Name:</strong> <?php echo htmlspecialchars($stu['student_fullname'] ?? ''); ?></div>
          <div class="mr-3"><strong>Gender:</strong> 
            <?php if ($stuGender): ?>
              <span class="badge badge-<?php echo ($stuGender==='Male'?'primary':($stuGender==='Female'?'danger':'secondary')); ?>"><?php echo htmlspecialchars($stuGender); ?></span>
            <?php else: ?>
              <span class="text-muted">Not set</span>
            <?php endif; ?>
          </div>
          <?php if (isset($req['distance_km'])): ?>
            <div class="mr-3"><strong>Distance:</strong> <?php echo (float)$req['distance_km']; ?> km</div>
          <?php endif; ?>
          <?php if (isset($req['status'])): ?>
            <div class="mr-3"><strong>Request:</strong> <?php echo htmlspecialchars($req['status']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?php echo $base; ?>/controller/HostelAllocate.php">
    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" />

    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Hostel</label>
        <select name="hostel_id" id="hostel_id" class="form-control" required>
          <option value="">Select...</option>
          <?php
          $hostelCount = 0;
          // Allowed genders: union of Mixed + student gender (if set) + warden gender (if WAR), including synonyms
          $expand = function($g) {
            if (!$g) return [];
            $g = trim($g);
            if (strcasecmp($g,'Male')===0) return ['Male','Boys','Boy'];
            if (strcasecmp($g,'Female')===0) return ['Female','Girls','Girl','Ladies'];
            if (strcasecmp($g,'Mixed')===0) return ['Mixed'];
            // Also map common synonyms back to canonical sets
            if (strcasecmp($g,'Boys')===0 || strcasecmp($g,'Boy')===0) return ['Male','Boys','Boy'];
            if (strcasecmp($g,'Girls')===0 || strcasecmp($g,'Girl')===0 || strcasecmp($g,'Ladies')===0) return ['Female','Girls','Girl','Ladies'];
            return [$g];
          };

          $allowedSet = ['Mixed' => true];
          foreach ($expand($stuGender) as $v) { $allowedSet[$v] = true; }
          $wg = $wardenGender;
          if ($wg) { $wg = (strcasecmp($wg,'male')===0?'Male':(strcasecmp($wg,'female')===0?'Female':$wg)); }
          foreach ($expand($wg) as $v) { $allowedSet[$v] = true; }
          $allowed = array_keys($allowedSet);

           // Prepare query with dynamic IN list
           $placeholders = implode(',', array_fill(0, count($allowed), '?'));
           $sqlH = "SELECT id, name FROM hostels WHERE active=1 AND gender IN ($placeholders) ORDER BY name";
           if ($stH = mysqli_prepare($con, $sqlH)) {
             $types = str_repeat('s', count($allowed));
             mysqli_stmt_bind_param($stH, $types, ...$allowed);
             mysqli_stmt_execute($stH);
             $resH = mysqli_stmt_get_result($stH);
             while ($resH && $h = mysqli_fetch_assoc($resH)) {
               $hostelCount++;
               echo '<option value="'.(int)$h['id'].'">'.htmlspecialchars($h['name']).'</option>';
             }
             mysqli_stmt_close($stH);
           }
          ?>
        </select>
        <?php if ($hostelCount === 0): ?>
          <small class="form-text text-muted">No compatible hostels found. Ensure hostels exist for the student's/warden's gender or Mixed.</small>
        <?php endif; ?>
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

    <div class="form-row">
      <div class="form-group col-md-4">
        <label>Allocated Date</label>
        <input type="date" class="form-control" name="allocated_at" value="<?php echo date('Y-m-d'); ?>" required />
      </div>
      <div class="form-group col-md-4">
        <label>Leaving Date</label>
        <input type="date" class="form-control" name="leaving_at" />
      </div>
    </div>

    <button type="submit" class="btn btn-primary" <?php echo (!$req || $req['status']!=='paid')?'disabled':''; ?>>Assign</button>
    <a href="<?php echo $base; ?>/hostel/Requests.php" class="btn btn-secondary ml-2">Back</a>
  </form>

  <hr />
  <small class="text-muted">Tip: Capacity check is enforced on submit.</small>
</div>

<script>
// Simple chained selects using basic fetch endpoints
const base = '<?php echo $base; ?>';
const hostelSel = document.getElementById('hostel_id');
const blockSel = document.getElementById('block_id');
const roomSel = document.getElementById('room_id');
const studentGender = '<?php echo isset($stuGender) && $stuGender ? htmlspecialchars($stuGender, ENT_QUOTES, 'UTF-8') : ''; ?>';

hostelSel && hostelSel.addEventListener('change', async (e) => {
  blockSel.innerHTML = '<option value="">Loading...</option>';
  roomSel.innerHTML = '<option value=\"\">Select block first</option>';
  const hid = e.target.value;
  if (!hid) { blockSel.innerHTML = '<option value="">Select...</option>'; return; }
  const url = base + '/hostel/blocks_api.php?hostel_id=' + encodeURIComponent(hid) + (studentGender ? ('&student_gender=' + encodeURIComponent(studentGender)) : '');
  const r = await fetch(url);
  const data = await r.json();
  blockSel.innerHTML = '<option value="">Select...</option>' + data.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
});

blockSel && blockSel.addEventListener('change', async (e) => {
  roomSel.innerHTML = '<option value="">Loading...</option>';
  const bid = e.target.value;
  if (!bid) { roomSel.innerHTML = '<option value="">Select...</option>'; return; }
  const url = base + '/hostel/rooms_api.php?block_id=' + encodeURIComponent(bid) + (studentGender ? ('&student_gender=' + encodeURIComponent(studentGender)) : '');
  const r = await fetch(url);
  const data = await r.json();
  roomSel.innerHTML = '<option value="">Select...</option>' + data.map(rm => `<option value="${rm.id}">${rm.room_no} (cap ${rm.capacity}, occ ${rm.occupied})</option>`).join('');
});
</script>

<?php include_once("../footer.php"); ?>
