<?php
// hostel/ManualAllocate.php - Warden/Admin manual allocation without prior request
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'])) {
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
      <div class="form-group col-md-3">
        <label>Department</label>
        <select id="dept_filter" class="form-control">
          <option value="">-- Any --</option>
          <?php
          $dres = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
          while ($dres && ($d = mysqli_fetch_assoc($dres))) {
            echo '<option value="'.h($d['department_id']).'">'.h($d['department_name'])."</option>";
          }
          ?>
        </select>
      </div>
      <div class="form-group col-md-3">
        <label>Course</label>
        <select id="course_filter" class="form-control" disabled>
          <option value="">-- Any --</option>
          <?php
          $cres = mysqli_query($con, "SELECT course_id, course_name, department_id FROM course ORDER BY course_name");
          while ($cres && ($c = mysqli_fetch_assoc($cres))) {
            echo '<option value="'.h($c['course_id']).'" data-dept="'.h($c['department_id']).'">'.h($c['course_name'])."</option>";
          }
          ?>
        </select>
      </div>
      <div class="form-group col-md-2">
        <label>Gender</label>
        <select id="gender_filter" class="form-control">
          <option value="">-- Any --</option>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Mixed">Mixed</option>
        </select>
      </div>
      <div class="form-group col-md-4">
        <label>Student</label>
        <select name="student_id" id="student_id" class="form-control" required>
          <option value="">Select student</option>
        </select>
        <small class="form-text text-muted">List excludes students already allocated to a hostel.</small>
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
        <select name="hostel_id" id="hostel_id" class="form-control" disabled>
          <option value="">Select...</option>
          <?php
          if ($_SESSION['user_type'] === 'WAR' && $wardenGender) {
            // WAR: show only hostels matching warden gender (with synonyms)
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
            $wg = (strcasecmp($wardenGender,'male')===0?'Male':(strcasecmp($wardenGender,'female')===0?'Female':$wardenGender));
            $allowed = array_merge(['Mixed'], $expand($wg));
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
          } else {
            // ADM/SAO: show all active hostels
            $resH = mysqli_query($con, "SELECT id, name FROM hostels WHERE active=1 ORDER BY name");
            while ($resH && ($h = mysqli_fetch_assoc($resH))) {
              echo '<option value="'.(int)$h['id'].'">'.h($h['name']).'</option>';
            }
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
const deptSel = document.getElementById('dept_filter');
const courseSel = document.getElementById('course_filter');
const studentSel = document.getElementById('student_id');
const genderSel = document.getElementById('gender_filter');

function repopulateCoursesByDept(){
  if (!deptSel || !courseSel) return;
  const d = deptSel.value;
  const all = Array.from(courseSel.querySelectorAll('option'));
  const keep = new Set(['']);
  all.forEach(o => { if (o.value) { o.hidden = !!(d && o.getAttribute('data-dept') !== d); } });
  // reset selection if hidden
  if (courseSel.value && courseSel.selectedOptions[0].hidden) courseSel.value = '';
  // enable/disable course select
  courseSel.disabled = !d;
}

async function loadEligibleStudents(){
  if (!studentSel) return;
  const dept = deptSel ? deptSel.value : '';
  const course = courseSel ? courseSel.value : '';
  const hid = hostelSel ? hostelSel.value : '';
  const gen = genderSel ? genderSel.value : '';
  const qs = new URLSearchParams();
  if (dept) qs.append('department_id', dept);
  if (course) qs.append('course_id', course);
  if (hid) qs.append('hostel_id', hid);
  if (gen && gen !== 'Mixed') qs.append('gender', gen);
  studentSel.innerHTML = '<option value="">Loading...</option>';
  try {
    const r = await fetch(base + '/controller/StudentListForHostel.php' + (qs.toString() ? ('?'+qs.toString()) : ''));
    const json = await r.json();
    if (!json || json.ok !== true) { studentSel.innerHTML = '<option value="">Failed to load</option>'; return; }
    const arr = Array.isArray(json.students) ? json.students : [];
    const opts = ['<option value="">Select student</option>'].concat(arr.map(s => {
      const name = (s.student_fullname || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      const id = String(s.student_id||'');
      return `<option value="${id}">${name} (${id})</option>`;
    }));
    studentSel.innerHTML = opts.join('');
  } catch(e){
    studentSel.innerHTML = '<option value="">Failed to load</option>';
  }
}

async function loadHostelsByGender(){
  if (!hostelSel) return;
  const gen = genderSel ? genderSel.value : '';
  const qs = gen ? ('?gender='+encodeURIComponent(gen)) : '';
  if (!gen) {
    hostelSel.innerHTML = '<option value="">Select...</option>';
    hostelSel.disabled = true;
    blockSel.innerHTML = '<option value="">Select hostel first</option>';
    roomSel.innerHTML = '<option value="">Select block first</option>';
    return;
  }
  hostelSel.innerHTML = '<option value="">Loading...</option>';
  try {
    const r = await fetch(base + '/controller/HostelsList.php' + qs);
    const json = await r.json();
    if (!json || json.ok !== true) { hostelSel.innerHTML = '<option value="">Select...</option>'; return; }
    const arr = Array.isArray(json.hostels) ? json.hostels : [];
    const opts = ['<option value="">Select...</option>'].concat(arr.map(h => `<option value="${h.id}">${h.name}</option>`));
    hostelSel.innerHTML = opts.join('');
    hostelSel.disabled = false;
  } catch(e){
    hostelSel.innerHTML = '<option value="">Select...</option>';
  }
  // reset dependent selections
  blockSel.innerHTML = '<option value="">Select hostel first</option>';
  roomSel.innerHTML = '<option value="">Select block first</option>';
}

hostelSel && hostelSel.addEventListener('change', async (e) => {
  blockSel.innerHTML = '<option value="">Loading...</option>';
  roomSel.innerHTML = '<option value="">Select block first</option>';
  const hid = e.target.value;
  if (!hid) { blockSel.innerHTML = '<option value="">Select...</option>'; return; }
  const url = base + '/hostel/blocks_api.php?hostel_id=' + encodeURIComponent(hid);
  const r = await fetch(url);
  const data = await r.json();
  blockSel.innerHTML = '<option value="">Select...</option>' + data.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
  // reload students to match hostel gender
  loadEligibleStudents();
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

// filters
deptSel && deptSel.addEventListener('change', function(){ repopulateCoursesByDept(); loadEligibleStudents(); });
courseSel && courseSel.addEventListener('change', loadEligibleStudents);
genderSel && genderSel.addEventListener('change', function(){ loadHostelsByGender(); loadEligibleStudents(); });

// Initial population
repopulateCoursesByDept();
// Keep hostel disabled until a gender is selected
hostelSel && (hostelSel.disabled = true);
loadHostelsByGender();
loadEligibleStudents();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
