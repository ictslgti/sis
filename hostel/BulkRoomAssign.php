<?php
// hostel/BulkRoomAssign.php - Bulk room-wise assignment for SAO/ADM/WAR
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../head.php';
require_once __DIR__ . '/../menu.php';

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM','SAO','WAR'])) {
  echo '<div class="container mt-4"><div class="alert alert-danger">Forbidden</div></div>';
  require_once __DIR__ . '/../footer.php';
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Hostels list
$hostels = [];
$hres = mysqli_query($con, "SELECT id, name FROM hostels WHERE active=1 ORDER BY name");
while ($hres && ($row = mysqli_fetch_assoc($hres))) { $hostels[] = $row; }

// Optional flash from controller
$okCount = isset($_GET['ok']) ? (int)$_GET['ok'] : null;
$failCount = isset($_GET['fail']) ? (int)$_GET['fail'] : null;
$msg = isset($_GET['msg']) ? trim($_GET['msg']) : '';
?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <h4 class="mb-0">Bulk Room-wise Assignment</h4>
        <small class="text-muted">Select a hostel, block, and room, then pick multiple students to allocate. Capacity and gender rules are enforced.</small>
      </div>
      <div>
        <a class="btn btn-secondary" href="<?php echo $base; ?>/hostel/Hostel.php">Back</a>
      </div>
    </div>
  </div>

  <?php if ($okCount !== null || $failCount !== null || $msg !== ''): ?>
    <div class="alert <?php echo ($failCount && $failCount>0)?'alert-warning':'alert-success'; ?>">
      <?php if ($okCount !== null): ?>
        <span><strong><?php echo (int)$okCount; ?></strong> student(s) allocated.</span>
      <?php endif; ?>
      <?php if ($failCount !== null): ?>
        <span class="ml-3"><strong><?php echo (int)$failCount; ?></strong> failed.</span>
      <?php endif; ?>
      <?php if ($msg !== ''): ?>
        <div class="mt-1 small"><?php echo h($msg); ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="<?php echo $base; ?>/controller/HostelBulkAssign.php">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Hostel</label>
            <select name="hostel_id" id="hostel_id" class="form-control" required>
              <option value="">-- Select Hostel --</option>
              <?php foreach ($hostels as $h): ?>
                <option value="<?php echo (int)$h['id']; ?>"><?php echo h($h['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Block</label>
            <select name="block_id" id="block_id" class="form-control" required>
              <option value="">-- Select hostel first --</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Room</label>
            <select name="room_id" id="room_id" class="form-control" required>
              <option value="">-- Select block first --</option>
            </select>
            <small class="form-text text-muted"><span id="capInfo">Capacity: —, Occupied: —, Available: —</span></small>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-3">
            <label>Allocated Date</label>
            <input type="date" name="allocated_at" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group col-md-3">
            <label>Leaving Date</label>
            <input type="date" name="leaving_at" class="form-control">
          </div>
        </div>

        <hr>
        <h6>Select Students</h6>
        <div class="alert alert-info py-2">Only students whose gender is compatible with the selected hostel can be allocated. Existing active allocations will be ended before assigning.</div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Filter by Department (optional)</label>
            <select id="dept_filter" class="form-control">
              <option value="">All Departments</option>
              <?php
              $dres = mysqli_query($con, "SELECT department_id, department_name FROM department ORDER BY department_name");
              while ($dres && ($d = mysqli_fetch_assoc($dres))) {
                echo '<option value="'.h($d['department_id']).'">'.h($d['department_name']).'</option>';
              }
              ?>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>&nbsp;</label>
            <input type="text" id="search_student" class="form-control" placeholder="Search by name or ID...">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <div id="students_container" class="border rounded p-2" style="max-height: 380px; overflow: auto">
              <div class="text-muted">Select a hostel and block to load rooms; then select a room to load students.</div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Bulk Assign</button>
          <button type="reset" class="btn btn-secondary ml-2">Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const base = '<?php echo $base; ?>';
  const hostelSel = document.getElementById('hostel_id');
  const blockSel  = document.getElementById('block_id');
  const roomSel   = document.getElementById('room_id');
  const capInfo   = document.getElementById('capInfo');
  const studentsBox = document.getElementById('students_container');
  const deptFilter = document.getElementById('dept_filter');
  const searchBox  = document.getElementById('search_student');

  let roomsCache = [];
  let hostelGender = null; // will be determined server-side on load students
  let studentsAll = [];

  function setCapInfo(cap, occ){
    const available = (cap != null && occ != null) ? (cap - occ) : null;
    capInfo.textContent = `Capacity: ${cap!=null?cap:'—'}, Occupied: ${occ!=null?occ:'—'}, Available: ${available!=null?available:'—'}`;
  }

  hostelSel && hostelSel.addEventListener('change', async (e) => {
    blockSel.innerHTML = '<option value="">Loading...</option>';
    roomSel.innerHTML = '<option value="">-- Select block first --</option>';
    roomsCache = [];
    setCapInfo(null,null);
    studentsBox.innerHTML = '<div class="text-muted">Select a block and room to load students.</div>';
    const hid = e.target.value;
    if (!hid) { blockSel.innerHTML = '<option value="">-- Select hostel first --</option>'; return; }
    const r = await fetch(base + '/hostel/blocks_api.php?hostel_id=' + encodeURIComponent(hid));
    const data = await r.json();
    blockSel.innerHTML = '<option value="">-- Select Block --</option>' + data.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
  });

  blockSel && blockSel.addEventListener('change', async (e) => {
    roomSel.innerHTML = '<option value="">Loading...</option>';
    roomsCache = [];
    setCapInfo(null,null);
    studentsBox.innerHTML = '<div class="text-muted">Select a room to load students.</div>';
    const bid = e.target.value;
    if (!bid) { roomSel.innerHTML = '<option value="">-- Select block first --</option>'; return; }
    const r = await fetch(base + '/hostel/rooms_api.php?block_id=' + encodeURIComponent(bid));
    const data = await r.json();
    roomsCache = data || [];
    roomSel.innerHTML = '<option value="">-- Select Room --</option>' + roomsCache.map(rm => `<option value="${rm.id}" data-capacity="${rm.capacity}" data-occupied="${rm.occupied}">${rm.room_no} (cap ${rm.capacity}, occ ${rm.occupied})</option>`).join('');
  });

  roomSel && roomSel.addEventListener('change', async (e) => {
    const opt = e.target.selectedOptions && e.target.selectedOptions[0];
    const cap = opt ? parseInt(opt.getAttribute('data-capacity')||'0',10) : null;
    const occ = opt ? parseInt(opt.getAttribute('data-occupied')||'0',10) : null;
    setCapInfo(cap, occ);
    await loadStudents();
  });

  deptFilter && deptFilter.addEventListener('change', renderStudents);
  searchBox && searchBox.addEventListener('input', renderStudents);

  async function loadStudents(){
    const hid = hostelSel.value; const bid = blockSel.value; const rid = roomSel.value;
    if (!hid || !bid || !rid){ studentsBox.innerHTML='<div class="text-muted">Select a hostel, block, and room.</div>'; return; }
    // Fetch compatible students list from server. We'll reuse a generic endpoint here by building a small API inline.
    try {
      const r = await fetch(base + '/hostel/rooms_api.php?block_id=' + encodeURIComponent(bid));
      const rooms = await r.json();
      const room = (rooms||[]).find(x => String(x.id) === String(rid));
      // Capacity info already set; we now fetch students from server-side simple list
      const resp = await fetch(base + '/controller/StudentListApi.php');
      // Fallback if API doesn't exist: we will render a message
      if (resp.status !== 200){
        studentsBox.innerHTML = '<div class="alert alert-warning">Student list API not found. Please contact admin.</div>';
        return;
      }
      const json = await resp.json();
      studentsAll = Array.isArray(json)? json : (json.students || []);
      renderStudents();
    } catch (e) {
      studentsBox.innerHTML = '<div class="alert alert-danger">Failed to load students.</div>';
    }
  }

  function renderStudents(){
    if (!studentsAll || studentsAll.length === 0){
      studentsBox.innerHTML = '<div class="text-muted">No students to show. Ensure the student list API is available.</div>';
      return;
    }
    const dept = (deptFilter && deptFilter.value) ? String(deptFilter.value) : '';
    const q = (searchBox && searchBox.value ? searchBox.value.toLowerCase() : '');
    const filtered = studentsAll.filter(s => {
      const okDept = !dept || String(s.department_id||'') === dept;
      const inText = (String(s.student_id||'').toLowerCase().includes(q) || String(s.student_fullname||'').toLowerCase().includes(q));
      return okDept && inText;
    });
    const html = '<div class="small text-muted mb-2">' + filtered.length + ' student(s) listed</div>' +
      '<div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr>'+
      '<th><input type="checkbox" id="chk_all"></th>'+
      '<th>ID</th><th>Name</th><th>Gender</th><th>Department</th>'+
      '</tr></thead><tbody>'+
      filtered.map(s => `<tr>`+
        `<td><input type="checkbox" name="student_ids[]" value="${h(String(s.student_id))}"></td>`+
        `<td>${h(String(s.student_id))}</td>`+
        `<td>${h(String(s.student_fullname||''))}</td>`+
        `<td>${h(String(s.student_gender||''))}</td>`+
        `<td>${h(String(s.department_name||''))}</td>`+
      `</tr>`).join('')+
      '</tbody></table></div>';
    studentsBox.innerHTML = html;
    const chkAll = document.getElementById('chk_all');
    if (chkAll){
      chkAll.addEventListener('change', function(){
        document.querySelectorAll('#students_container input[type="checkbox"][name="student_ids[]"]').forEach(cb => { cb.checked = chkAll.checked; });
      });
    }
  }

  function h(s){
    if (s==null) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
  }
})();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
