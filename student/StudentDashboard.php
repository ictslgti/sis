<?php
// Student Dashboard with monthly attendance calendar and cards
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Only students can access
if (!isset($_SESSION['user_table']) || $_SESSION['user_table'] !== 'student') {
  header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/index.php');
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';
$studentId = $_SESSION['user_name'] ?? '';
if ($studentId === '') { header('Location: ' . $base . '/index.php'); exit; }

// Load student basic and enroll info
$student = null; $enroll = null; $course = null; $department = null; $profileImg = null;
if ($r = mysqli_query($con, "SELECT s.*, se.course_id, c.course_name, d.department_name
                             FROM student s
                             LEFT JOIN student_enroll se ON se.student_id=s.student_id AND se.student_enroll_status IN ('Following','Active')
                             LEFT JOIN course c ON c.course_id = se.course_id
                             LEFT JOIN department d ON d.department_id = c.department_id
                             WHERE s.student_id='".mysqli_real_escape_string($con,$studentId)."' LIMIT 1")) {
  $student = mysqli_fetch_assoc($r) ?: null; mysqli_free_result($r);
}
if ($student) {
  $profileImg = trim((string)($student['student_profile_img'] ?? ''));
  if ($profileImg !== '') {
    // Ensure path form: if blob existed earlier, skip; assume path for dashboard
    $abs = realpath(__DIR__ . '/../' . $profileImg);
    if (!$abs || !file_exists($abs)) { $profileImg = null; }
  }
}
$title = 'My Dashboard | SLGTI';
require_once __DIR__ . '/../head.php';
// Use compact student top nav if available
$topNav = __DIR__ . '/top_nav.php'; if (file_exists($topNav)) { include $topNav; }
?>
<div class="container-fluid px-2 px-md-4 mt-2">
  <div class="row">
    <div class="col-12 col-lg-4 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex">
          <div class="mr-3">
            <img src="<?php echo $profileImg? ($base . '/' . htmlspecialchars($profileImg)): ($base . '/img/profile/user.png'); ?>" alt="Photo" class="img-thumbnail" style="width:96px;height:128px;object-fit:cover;">
          </div>
          <div class="flex-fill">
            <h5 class="mb-1"><?php echo htmlspecialchars($student['student_fullname'] ?? $studentId); ?></h5>
            <div class="small text-muted mb-1">ID: <?php echo htmlspecialchars($studentId); ?></div>
            <?php if (!empty($student['course_name'])): ?>
            <div class="badge badge-info">Course: <?php echo htmlspecialchars($student['course_name']); ?></div>
            <?php endif; ?>
            <?php if (!empty($student['student_whatsapp'])): ?>
            <div class="small mt-2"><i class="fab fa-whatsapp text-success"></i> <?php echo htmlspecialchars($student['student_whatsapp']); ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-footer p-2 text-right">
          <a href="<?php echo $base; ?>/student/Student_profile.php?Sid=<?php echo urlencode($studentId); ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8 mb-3">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div>
            <i class="fas fa-calendar-alt text-primary mr-1"></i>
            <strong>Attendance</strong>
            <small class="text-muted">(Month)</small>
          </div>
          <div class="d-flex align-items-center">
            <input type="month" id="attMonth" class="form-control form-control-sm" style="max-width: 160px;" />
          </div>
        </div>
        <div class="card-body">
          <div id="calendarLegend" class="small mb-2">
            <span class="badge" style="background:#28a745">&nbsp;</span> Present
            <span class="badge ml-2" style="background:#dc3545">&nbsp;</span> Absent
            <span class="badge ml-2" style="background:#e9ecef;color:#6c757d;">&nbsp;</span> Not Marked / Weekend / Holiday
          </div>
          <div id="calendar" class="border rounded small"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12 col-lg-4 mb-3">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Personal Information</strong>
          <button class="btn btn-sm btn-light" data-toggle="collapse" data-target="#cardPersonal"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div id="cardPersonal" class="collapse show">
          <div class="card-body">
            <div><strong>Name with Initials:</strong> <?php echo htmlspecialchars($student['student_ininame'] ?? '—'); ?></div>
            <div><strong>Gender:</strong> <?php echo htmlspecialchars($student['student_gender'] ?? '—'); ?></div>
            <div><strong>NIC:</strong> <?php echo htmlspecialchars($student['student_nic'] ?? '—'); ?></div>
            <div><strong>Phone:</strong> <?php echo htmlspecialchars($student['student_phone'] ?? '—'); ?></div>
            <div><strong>Address:</strong> <?php echo htmlspecialchars($student['student_address'] ?? '—'); ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4 mb-3">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Enrollment</strong>
          <button class="btn btn-sm btn-light" data-toggle="collapse" data-target="#cardEnroll"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div id="cardEnroll" class="collapse show">
          <div class="card-body">
            <div><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name'] ?? '—'); ?></div>
            <div><strong>Course:</strong> <?php echo htmlspecialchars($student['course_name'] ?? '—'); ?></div>
            <div><strong>Status:</strong> <?php echo htmlspecialchars($student['student_status'] ?? '—'); ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4 mb-3">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Quick Links</strong>
          <button class="btn btn-sm btn-light" data-toggle="collapse" data-target="#cardLinks"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div id="cardLinks" class="collapse show">
          <div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action" href="<?php echo $base; ?>/timetable/Timetable.php"><i class="fas fa-calendar mr-2"></i>Timetable</a>
            <a class="list-group-item list-group-item-action" href="<?php echo $base; ?>/student/Student_profile.php?Sid=<?php echo urlencode($studentId); ?>"><i class="fas fa-id-card mr-2"></i>My Profile</a>
            <a class="list-group-item list-group-item-action" href="<?php echo $base; ?>/onpeak/OnPeak.php"><i class="far fa-calendar-check mr-2"></i>OnPeak Calendar</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  #calendar { overflow: hidden; }
  .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); border-top:1px solid #dee2e6; border-left:1px solid #dee2e6; }
  .cal-cell { min-height: 74px; border-right:1px solid #dee2e6; border-bottom:1px solid #dee2e6; padding: .25rem; position: relative; }
  .cal-cell .date { position: absolute; top: 2px; right: 4px; font-size: .8rem; color: #6c757d; }
  .cal-head { background:#f8f9fa; font-weight:600; text-align:center; padding:.4rem 0; border-right:1px solid #dee2e6; border-bottom:1px solid #dee2e6; }
  .mark-present { background: #e8f6ed; }
  .mark-absent { background: #fdecea; }
  .mark-none { background: #fff; }
</style>
<script>
(function(){
  const base = <?php echo json_encode($base); ?>;
  const calendarEl = document.getElementById('calendar');
  const monthInput = document.getElementById('attMonth');
  const today = new Date();
  const y = today.getFullYear(); const m = String(today.getMonth()+1).padStart(2,'0');
  monthInput.value = y + '-' + m;

  async function fetchAttendance(monthStr){
    const url = base + '/student/attendance_api.php?month=' + encodeURIComponent(monthStr);
    const res = await fetch(url, { credentials:'same-origin' });
    if (!res.ok) throw new Error('Failed to load attendance');
    return res.json();
  }

  function buildCalendar(monthStr, data){
    const parts = monthStr.split('-');
    const year = parseInt(parts[0],10), mon = parseInt(parts[1],10) - 1;
    const first = new Date(year, mon, 1);
    const last = new Date(year, mon+1, 0);
    const startDow = first.getDay(); // 0=Sun
    const days = last.getDate();

    const weekdayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const fragment = document.createDocumentFragment();

    const head = document.createElement('div');
    head.className = 'cal-grid';
    for (let i=0;i<7;i++){ const h = document.createElement('div'); h.className='cal-head'; h.textContent = weekdayNames[i]; head.appendChild(h); }
    fragment.appendChild(head);

    const grid = document.createElement('div');
    grid.className = 'cal-grid';

    // leading empty cells
    for (let i=0;i<startDow;i++){ const c = document.createElement('div'); c.className='cal-cell mark-none'; grid.appendChild(c); }

    // day cells
    for (let d=1; d<=days; d++){
      const dateStr = year + '-' + String(mon+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
      const mark = data.days && data.days[dateStr];
      const cls = mark === 1 ? 'mark-present' : (mark === 0 ? 'mark-absent' : 'mark-none');
      const cell = document.createElement('div');
      cell.className = 'cal-cell ' + cls;
      const num = document.createElement('div'); num.className='date'; num.textContent = d; cell.appendChild(num);
      grid.appendChild(cell);
    }

    // trailing fill to complete weeks
    const totalCells = startDow + days;
    const remain = (7 - (totalCells % 7)) % 7;
    for (let i=0;i<remain;i++){ const c = document.createElement('div'); c.className='cal-cell mark-none'; grid.appendChild(c); }

    calendarEl.innerHTML = '';
    calendarEl.appendChild(fragment);
    calendarEl.appendChild(grid);
  }

  async function refresh(){
    const monthStr = monthInput.value;
    try {
      const data = await fetchAttendance(monthStr);
      buildCalendar(monthStr, data);
    } catch(e){ console.error(e); calendarEl.textContent = 'Failed to load'; }
  }

  monthInput.addEventListener('change', refresh);
  refresh();
})();
</script>
<?php include_once __DIR__ . '/../footer.php'; ?>
