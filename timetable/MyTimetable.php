<?php
// MyTimetable.php — Student-friendly, nginx-safe timetable view (read-only)
// Designed to work on nginx/1.18 (Ubuntu) with strict JSON handling
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Access control: allow Students to view their own timetable; optionally allow staff/HOD/ADM to view by group_id
$base = defined('APP_BASE') ? APP_BASE : '';
$userType = $_SESSION['user_type'] ?? '';
$userTable = $_SESSION['user_table'] ?? '';
$userId = $_SESSION['user_name'] ?? ($_SESSION['user_id'] ?? '');

if (!$userId) {
  header('Location: ' . $base . '/index.php');
  exit;
}

// Resolve group_id to view
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if ($group_id <= 0 && $userTable === 'student') {
  // Try group_students table first
  if ($st = @mysqli_prepare($con, "SELECT group_id FROM group_students WHERE student_id = ? AND (status = 'active' OR status IS NULL OR status = '') ORDER BY id DESC LIMIT 1")) {
    @mysqli_stmt_bind_param($st, 's', $userId);
    if (@mysqli_stmt_execute($st)) {
      $rs = @mysqli_stmt_get_result($st);
      if ($rs && ($row = @mysqli_fetch_assoc($rs))) { $group_id = (int)$row['group_id']; }
    }
    @mysqli_stmt_close($st);
  }
  // Fallback: group_student
  if ($group_id <= 0) {
    if ($st = @mysqli_prepare($con, "SELECT group_id FROM group_student WHERE student_id = ? AND (status = 'active' OR status IS NULL OR status = '') ORDER BY id DESC LIMIT 1")) {
      @mysqli_stmt_bind_param($st, 's', $userId);
      if (@mysqli_stmt_execute($st)) {
        $rs = @mysqli_stmt_get_result($st);
        if ($rs && ($row = @mysqli_fetch_assoc($rs))) { $group_id = (int)$row['group_id']; }
      }
      @mysqli_stmt_close($st);
    }
  }
}

// Academic year default: Aug -> May window
$current_year = (int)date('Y');
$current_month = (int)date('n');
$base_year = ($current_month >= 8) ? $current_year : ($current_year - 1);
$academic_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== ''
  ? trim($_GET['academic_year'])
  : ($base_year . '-' . ($base_year + 1));

// Fetch group label for header
$group_label = '';
if ($group_id > 0) {
  if ($stg = @mysqli_prepare($con, "SELECT g.group_name, g.group_code FROM `groups` g WHERE g.id = ? LIMIT 1")) {
    @mysqli_stmt_bind_param($stg, 'i', $group_id);
    if (@mysqli_stmt_execute($stg)) {
      $rg = @mysqli_stmt_get_result($stg);
      if ($rg && ($gr = @mysqli_fetch_assoc($rg))) {
        $nm = trim((string)($gr['group_name'] ?? ''));
        $cd = trim((string)($gr['group_code'] ?? ''));
        $group_label = $nm !== '' ? $nm : ($cd !== '' ? $cd : ('Group #' . $group_id));
      }
    }
    @mysqli_stmt_close($stg);
  }
  if ($group_label === '') { $group_label = 'Group #' . $group_id; }
}

$title = 'My Timetable | SLGTI';
require_once __DIR__ . '/../head.php';
// Students might have a compact top nav
$topNav = __DIR__ . '/../student/top_nav.php';
if (file_exists($topNav)) { include $topNav; }
?>
<div class="container-fluid px-2 px-md-4 mt-2">
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm card-elevated">
        <div class="card-header card-header-light d-flex align-items-center justify-content-between flex-wrap">
          <div>
            <i class="fas fa-calendar-alt mr-1"></i>
            <strong>My Timetable</strong>
            <?php if ($group_label !== ''): ?>
              <span class="badge badge-info ml-2"><?php echo htmlspecialchars($group_label); ?></span>
            <?php endif; ?>
          </div>
          <div class="d-flex align-items-center">
            <div class="mr-2 small text-muted">Academic Year</div>
            <form id="ayForm" class="form-inline">
              <input type="hidden" name="group_id" value="<?php echo (int)$group_id; ?>">
              <select name="academic_year" id="academic_year" class="form-control form-control-sm">
                <?php
                  $cy = (int)date('Y');
                  for ($i = $cy - 2; $i <= $cy + 2; $i++) {
                    $ay = $i . '-' . ($i + 1);
                    $sel = ($ay === $academic_year) ? 'selected' : '';
                    echo '<option value="'.htmlspecialchars($ay).'" '.$sel.'>'.htmlspecialchars($ay).'</option>';
                  }
                ?>
              </select>
            </form>
          </div>
        </div>
        <div class="card-body p-2 p-md-3">
          <?php if ($group_id <= 0): ?>
            <div class="text-muted">No group is assigned to your account yet.</div>
          <?php else: ?>
            <div id="stdTTWrap">
              <table id="stdTT" class="table table-bordered table-sm mb-0 timetable-student">
                <thead class="thead-light">
                  <tr>
                    <th style="width:110px">Day</th>
                    <th class="text-center">08:30<br class="d-none d-md-block">- 10:00</th>
                    <th class="text-center">10:30<br class="d-none d-md-block">- 12:00</th>
                    <th class="text-center">13:00<br class="d-none d-md-block">- 14:30</th>
                    <th class="text-center">14:45<br class="d-none d-md-block">- 16:15</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $weekdays = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'];
                  foreach ($weekdays as $dno=>$dname): ?>
                    <tr>
                      <th class="align-middle"><?php echo $dname; ?></th>
                      <?php foreach (['P1','P2','P3','P4'] as $p): ?>
                        <td data-day="<?php echo $dno; ?>" data-period="<?php echo $p; ?>">
                          <div class="std-ttslot text-muted text-center py-2">—</div>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div id="stdTTLegend" class="mt-2"></div>
          <?php endif; ?>
        </div>
        <div class="card-footer py-2">
          <button class="btn btn-sm btn-outline-secondary" id="printBtn"><i class="fas fa-print mr-1"></i> Print</button>
          <button class="btn btn-sm btn-outline-success" id="pdfBtn"><i class="fas fa-file-pdf mr-1"></i> Download PDF</button>
        </div>
      </div>
    </div>
  </div>
</div>
<style>
#stdTTWrap { overflow-x: hidden; }
#stdTT { width: 100%; table-layout: fixed; }
#stdTT th.align-middle { width: 100px; }
.table.timetable-student th, .table.timetable-student td { vertical-align: top; padding: .4rem .45rem; }
.table.timetable-student .std-ttslot { position: relative; display: flex; align-items: stretch; justify-content: flex-start; min-height: 56px; }
.table.timetable-student .std-entry { border-radius: 4px; color: #fff; padding: 6px 8px; font-size: 0.82rem; line-height: 1.2; display: block; width: 100%; }
.table.timetable-student .std-entry .code { font-weight: 700; display:block; }
.table.timetable-student .std-entry .name { font-size: .76rem; opacity: .95; display:block; }
.table.timetable-student .std-entry .staff { font-size: .7rem; opacity: .95; display:block; }
.table.timetable-student .std-entry .room { position:absolute; bottom:4px; right:6px; font-size:.64rem; background: rgba(255,255,255,.25); padding:0 6px; border-radius:3px; }
#stdTTLegend { border-top: 1px solid #e9ecef; padding-top: 8px; }
#stdTTLegend .legend-title { font-weight: 600; color:#6c757d; margin: 4px 0; }
#stdTTLegend ul { list-style: disc; padding-left: 18px; margin: 4px 0 8px 0; }
#stdTTLegend ul.modules, #stdTTLegend ul.lecturers { columns: 1; -webkit-columns: 1; -moz-columns: 1; }
@media (min-width: 768px) { #stdTTLegend ul.modules, #stdTTLegend ul.lecturers { columns: 2; -webkit-columns: 2; -moz-columns: 2; } }
#stdTTLegend .tt-item { break-inside: avoid; -webkit-column-break-inside: avoid; display: block; line-height: 1.25; }
#stdTTLegend .tt-code { font-weight:700; }
#stdTTLegend .badge { font-size: .7rem; padding: .15rem .4rem; }
</style>
<script>
(function(){
  const APP_BASE = <?php echo json_encode($base); ?>;
  const groupId = <?php echo (int)$group_id; ?>;
  const academicYear = <?php echo json_encode($academic_year); ?>;
  if (!groupId) return;

  function parseJsonSafe(txt){
    try {
      if (typeof txt !== 'string') return txt;
      let t = txt.trim();
      if (/^\{[\s\S]*\}$/.test(t)) return JSON.parse(t);
      const first = t.indexOf('{'); const last = t.lastIndexOf('}');
      if (first !== -1 && last !== -1 && last > first) return JSON.parse(t.substring(first, last+1));
    } catch(e) {}
    return null;
  }

  function colorForKey(key){
    const colors = ['#3498db','#2ecc71','#e74c3c','#f39c12','#9b59b6','#1abc9c','#d35400','#34495e','#7f8c8d','#27ae60'];
    let h=0; const s=String(key);
    for (let i=0;i<s.length;i++){ h=((h<<5)-h)+s.charCodeAt(i); h|=0; }
    return colors[Math.abs(h)%colors.length];
  }

  function fillTable(data){
    const map = {}; // day -> period -> [entries]
    (data||[]).forEach(e=>{
      if(!map[e.weekday]) map[e.weekday]={};
      if(!map[e.weekday][e.period]) map[e.weekday][e.period]=[];
      map[e.weekday][e.period].push(e);
    });
    document.querySelectorAll('#stdTT tbody td').forEach(td=>{
      const day = parseInt(td.getAttribute('data-day'),10);
      const period = td.getAttribute('data-period');
      const slot = (map[day] && map[day][period]) ? map[day][period] : [];
      const box = td.querySelector('.std-ttslot');
      if (!slot.length){ box.textContent='—'; return; }
      const e = slot[0]; // first entry visible
      const div = document.createElement('div'); div.className='std-entry'; div.style.backgroundColor = colorForKey(e.module_id);
      const code = document.createElement('span'); code.className='code'; code.textContent = e.module_id || 'N/A';
      const name = document.createElement('span'); name.className='name'; name.textContent = e.module_name || '';
      const staff = document.createElement('span'); staff.className='staff'; staff.textContent = e.staff_name || '';
      const room = document.createElement('span'); room.className='room'; room.textContent = e.classroom || '';
      div.appendChild(code); if(e.module_name) div.appendChild(name); if(e.staff_name) div.appendChild(staff); if(e.classroom) div.appendChild(room);
      box.innerHTML=''; box.appendChild(div);
    });
  }

  function loadTimetable(){
    const url = APP_BASE + '/controller/GroupTimetableController.php?action=list&group_id='+encodeURIComponent(groupId)+'&academic_year='+encodeURIComponent(academicYear);
    fetch(url, { credentials:'same-origin' })
      .then(r=>r.text())
      .then(txt=>{ const j = parseJsonSafe(txt); if (j && j.success) fillTable(j.data); })
      .catch(()=>{});
  }

  // AY change redirect
  const ay = document.getElementById('academic_year');
  if (ay){ ay.addEventListener('change', function(){
    const val = this.value; const loc = new URL(window.location.href);
    loc.searchParams.set('academic_year', val);
    if (!loc.searchParams.get('group_id') && groupId) loc.searchParams.set('group_id', String(groupId));
    window.location.href = loc.toString();
  }); }

  // Print and PDF
  document.getElementById('printBtn').addEventListener('click', function(){
    const w = window.open('', '_blank');
    w.document.write('<!doctype html><html><head><title>Timetable</title><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"></head><body>'+document.getElementById('stdTTWrap').outerHTML+'</body></html>');
    w.document.close(); w.focus(); setTimeout(()=>{ w.print(); }, 300);
  });
  document.getElementById('pdfBtn').addEventListener('click', async function(){
    const { jsPDF } = window.jspdf || {};
    if (!window.html2canvas || !jsPDF) return;
    const node = document.getElementById('stdTTWrap');
    const canvas = await html2canvas(node, { scale: 2, backgroundColor: '#ffffff' });
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const imgWidth = pageWidth - 40; const imgHeight = (canvas.height * imgWidth) / canvas.width;
    pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 20, 20, imgWidth, Math.min(imgHeight, pageHeight-40));
    pdf.save('MyTimetable_'+groupId+'_'+academicYear+'.pdf');
  });

  // Load external libs for PDF if needed
  (function ensureDeps(){
    function add(src){ return new Promise(res=>{ var s=document.createElement('script'); s.src=src; s.async=true; s.onload=res; s.onerror=res; document.head.appendChild(s); }); }
    const p1 = window.html2canvas ? Promise.resolve() : add('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js');
    const p2 = (window.jspdf && window.jspdf.jsPDF) ? Promise.resolve() : add('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
    Promise.all([p1,p2]).then(loadTimetable);
  })();
})();
</script>
<?php include __DIR__ . '/../footer.php'; ?>
