<?php
// Student Dashboard with monthly attendance calendar and cards
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/../config.php';

// Only students can access
if (!isset($_SESSION['user_table']) || $_SESSION['user_table'] !== 'student') {
  header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/index.php');
  exit;
}

$base = defined('APP_BASE') ? APP_BASE : '';
$studentId = $_SESSION['user_name'] ?? '';
if ($studentId === '') {
  header('Location: ' . $base . '/index.php');
  exit;
}

// Load student basic and enroll info
$student = null;
$enroll = null;
$course = null;
$department = null;
$profileImg = null;
if ($r = mysqli_query($con, "SELECT s.*, se.course_id, c.course_name, d.department_name
                             FROM student s
                             LEFT JOIN student_enroll se ON se.student_id=s.student_id AND se.student_enroll_status IN ('Following','Active')
                             LEFT JOIN course c ON c.course_id = se.course_id
                             LEFT JOIN department d ON d.department_id = c.department_id
                             WHERE s.student_id='" . mysqli_real_escape_string($con, $studentId) . "' LIMIT 1")) {
  $student = mysqli_fetch_assoc($r) ?: null;
  mysqli_free_result($r);
}
if ($student) {
  $profileImg = trim((string)($student['student_profile_img'] ?? ''));
  if ($profileImg !== '') {
    // Ensure path form: if blob existed earlier, skip; assume path for dashboard
    $abs = realpath(__DIR__ . '/../' . $profileImg);
    if (!$abs || !file_exists($abs)) {
      $profileImg = null;
    }
  }
}
// Fetch hostel allocation and roommates (define vars before view)
$hostelAlloc = null;
$roommates = [];
// Active allocation for this student
if ($stH = mysqli_prepare($con, 'SELECT a.id, a.allocated_at, a.leaving_at, a.room_id, r.room_no, r.capacity, b.name AS block_name, h.name AS hostel_name
                                  FROM hostel_allocations a
                                  JOIN hostel_rooms r ON r.id = a.room_id
                                  JOIN hostel_blocks b ON b.id = r.block_id
                                  JOIN hostels h ON h.id = b.hostel_id
                                  WHERE a.student_id = ? AND a.status = "active" LIMIT 1')) {
  mysqli_stmt_bind_param($stH, 's', $studentId);
  if (mysqli_stmt_execute($stH)) {
    $rsH = mysqli_stmt_get_result($stH);
    if ($rsH) {
      $hostelAlloc = mysqli_fetch_assoc($rsH) ?: null;
    }
  }
  mysqli_stmt_close($stH);
}
// Roommates in same room (exclude self)
if ($hostelAlloc && !empty($hostelAlloc['room_id'])) {
  if ($stR = mysqli_prepare($con, 'SELECT s.student_id, COALESCE(NULLIF(TRIM(s.student_ininame),""), s.student_fullname, s.student_id) AS name
                                   FROM hostel_allocations a
                                   JOIN student s ON s.student_id = a.student_id
                                   WHERE a.room_id = ? AND a.status = "active" AND a.student_id <> ?
                                   ORDER BY name')) {
    mysqli_stmt_bind_param($stR, 'is', $hostelAlloc['room_id'], $studentId);
    if (mysqli_stmt_execute($stR)) {
      $rsR = mysqli_stmt_get_result($stR);
      while ($rsR && ($row = mysqli_fetch_assoc($rsR))) {
        $roommates[] = $row;
      }
    }
    mysqli_stmt_close($stR);
  }
}
$title = 'My Dashboard | SLGTI';
require_once __DIR__ . '/../head.php';
// Use compact student top nav if available
$topNav = __DIR__ . '/top_nav.php';
if (file_exists($topNav)) {
  include $topNav;
}
?>
<div class="container-fluid px-2 px-md-4 mt-2 dashboard-container">
  <div class="row">
    <div class="col-12 col-lg-12 mb-3">
      <div class="card shadow-sm card-elevated h-100 profile-card">
        <div class="card-body p-3">
          <div class="d-flex align-items-center header-stack flex-wrap">
            <div class="avatar-frame mr-3">
              <img src="<?php echo $profileImg ? ($base . '/' . htmlspecialchars($profileImg)) : ($base . '/img/profile/user.png'); ?>" alt="Photo" class="avatar-img">
            </div>
            <div class="flex-fill">
              <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-1 font-weight-600 student-name" style="line-height:1.2;">
                  <?php echo htmlspecialchars($student['student_fullname'] ?? $studentId); ?>
                </h5>
              </div>
              <div class="small text-muted mb-2">ID: <?php echo htmlspecialchars($studentId); ?></div>
              <?php if (!empty($student['course_name'])): ?>
                <span class="badge badge-pill badge-primary mb-1 course-badge">
                  <?php echo htmlspecialchars($student['course_name']); ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($student['student_whatsapp'])): ?>
                <div class="small mt-2"><i class="fab fa-whatsapp text-success"></i> <?php echo htmlspecialchars($student['student_whatsapp']); ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="card-footer py-2 d-flex justify-content-right">
          <a href="<?php echo $base; ?>/student/Student_profile.php?Sid=<?php echo urlencode($studentId); ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-id-card mr-1"></i> View Profile
          </a>
        </div>
      </div>
    </div>

    <!-- Roommate Contact Modal -->
    <div class="modal fade" id="roommateModal" tabindex="-1" role="dialog" aria-labelledby="roommateLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title" id="roommateLabel"><i class="fas fa-user-friends mr-1 text-secondary"></i> Roommate Contact</h6>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="rmcLoading" class="text-muted small">Loading...</div>
            <div id="rmcContent" style="display:none;">
              <div class="mb-2"><strong id="rmcName">-</strong> <small class="text-muted" id="rmcId"></small></div>
              <div class="border rounded p-2 mb-2">
                <div class="small text-muted mb-1"><strong>Emergency Contact</strong></div>
                <div><strong>Name:</strong> <span id="rmcEmName">-</span></div>
                <div><strong>Relation:</strong> <span id="rmcEmRel">-</span></div>
                <div><strong>Phone:</strong> <span id="rmcEmPhone">-</span></div>
                <div><strong>Address:</strong> <span id="rmcEmAddr">-</span></div>
              </div>
              <div class="border rounded p-2">
                <div class="small text-muted mb-1"><strong>Contact</strong></div>
                <div><strong>Phone:</strong> <span id="rmcPhone">-</span></div>
                <div><strong>Email:</strong> <span id="rmcEmail">-</span></div>
                <div><strong>WhatsApp:</strong> <span id="rmcWA">-</span></div>
              </div>
            </div>
          </div>
          <div class="modal-footer py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
      <!-- Personal Information on the right (desktop) -->
      <div class="col-12 col-lg-8 mb-3">
        <div class="card shadow-sm card-elevated h-100">
          <div class="card-header card-header-light d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-user mr-1"></i> Personal Information</strong>
            <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#cardPersonalTop"><i class="fas fa-eye-slash"></i></button>
          </div>
          <div id="cardPersonalTop" class="collapse show">
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
    </div>


    <!-- Month Summary Modal -->
    <div class="modal fade" id="attSummaryModal" tabindex="-1" role="dialog" aria-labelledby="attSummaryLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title" id="attSummaryLabel"><i class="fas fa-chart-pie mr-1 text-primary"></i> Month Summary</h6>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="d-flex justify-content-between mb-1"><span>Present</span><strong id="sumPresent">0</strong></div>
            <div class="d-flex justify-content-between mb-1"><span>Absent</span><strong id="sumAbsent">0</strong></div>
            <div class="d-flex justify-content-between mb-1"><span>Not Marked / Weekend / Holiday</span><strong id="sumNotMarked">0</strong></div>
            <hr class="my-2">
            <div class="d-flex justify-content-between"><span>Percentage</span><strong id="sumPercent">0%</strong></div>
            <small class="text-muted d-block mt-2">Percentage is calculated over weekdays (Mon–Fri) in the month.</small>
          </div>
          <div class="modal-footer py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Personal Information (moved up, full width) -->
  </div>



  <div class="row">
    <div class="col-12 col-lg-8 mb-3">
      <div class="card shadow-sm card-elevated h-100">
        <div class="card-header card-header-light d-flex align-items-center justify-content-between calendar-toolbar flex-wrap">
          <div>
            <i class="fas fa-calendar-alt mr-1"></i>
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
        <div class="card-footer py-2">
          <div class="d-flex flex-wrap align-items-center">
            <span class="badge badge-success mr-2 mb-1">Present: <span id="footPresent">0</span></span>
            <span class="badge badge-danger mr-2 mb-1">Absent: <span id="footAbsent">0</span></span>
            <span class="badge badge-secondary mr-2 mb-1">Not Marked: <span id="footNotMarked">0</span></span>
            <span class="badge badge-primary mb-1">Percentage: <span id="footPercent">0%</span></span>
          </div>
        </div>
      </div>
    </div>
    <!-- Right sidebar: stacked cards next to attendance -->
    <div class="col-12 col-lg-4 mb-3">
      <div class="card shadow-sm card-elevated mb-3">
        <div class="card-header card-header-light d-flex justify-content-between align-items-center">
          <strong><i class="fas fa-graduation-cap mr-1"></i> Enrollment</strong>
          <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#cardEnroll"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div id="cardEnroll" class="collapse show">
          <div class="card-body">
            <div><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name'] ?? '—'); ?></div>
            <div><strong>Course:</strong> <?php echo htmlspecialchars($student['course_name'] ?? '—'); ?></div>
            <div><strong>Status:</strong> <?php echo htmlspecialchars($student['student_status'] ?? '—'); ?></div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm card-elevated mb-3">
        <div class="card-header card-header-light d-flex justify-content-between align-items-center">
          <strong><i class="far fa-building mr-1"></i> Hostel</strong>
          <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#cardHostel"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div id="cardHostel" class="collapse show">
          <div class="card-body">
            <?php if ($hostelAlloc): ?>
              <div><strong>Hostel:</strong> <?php echo htmlspecialchars($hostelAlloc['hostel_name']); ?></div>
              <div><strong>Block:</strong> <?php echo htmlspecialchars($hostelAlloc['block_name']); ?></div>
              <div><strong>Room No:</strong> <?php echo htmlspecialchars($hostelAlloc['room_no']); ?></div>
              <div><strong>Allocated on:</strong> <?php echo htmlspecialchars($hostelAlloc['allocated_at']); ?></div>
              <?php if (!empty($roommates)): ?>
                <hr class="my-2">
                <div class="small text-muted mb-1"><strong>Roommates</strong></div>
                <ul class="list-unstyled mb-0">
                  <?php foreach ($roommates as $rm): ?>
                    <li>
                      <button type="button" class="btn btn-link p-0 roommate-link" data-stu="<?php echo htmlspecialchars($rm['student_id']); ?>" title="View emergency contact">
                        <i class="fas fa-user text-secondary mr-1"></i><?php echo htmlspecialchars($rm['name']); ?>
                        <small class="text-muted">(<?php echo htmlspecialchars($rm['student_id']); ?>)</small>
                      </button>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-muted">No active hostel allocation.</div>
              <a class="btn btn-sm btn-outline-primary mt-2" href="<?php echo $base; ?>/student/request_hostel.php">
                <i class="fas fa-bed mr-1"></i> Request Hostel
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card shadow-sm card-elevated">
        <div class="card-header card-header-light d-flex justify-content-between align-items-center">
          <strong><i class="fas fa-link mr-1"></i> Quick Links</strong>
          <button class="btn btn-sm btn-outline-secondary" data-toggle="collapse" data-target="#cardLinks"><i class="fas fa-eye-slash"></i></button>
        </div>
        <div id="cardLinks" class="collapse show">
          <div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action" href="<?php echo $base; ?>"><i class="fas fa-calendar mr-2"></i>Timetable</a>
            <a class="list-group-item list-group-item-action" href="<?php echo $base; ?>/student/Student_profile.php?Sid=<?php echo urlencode($studentId); ?>"><i class="fas fa-id-card mr-2"></i>My Profile</a>
            <a class="list-group-item list-group-item-action" href="<?php echo $base; ?>onpeak/RequestOnPeak.php"><i class="far fa-calendar-check mr-2"></i>OnPeak Calendar</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  /* Dashboard responsive frame */
  .dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
  }

  /* Unified elevated card and accent header */
  .card-elevated {
    box-shadow: 0 8px 24px rgba(0, 0, 0, .06), 0 2px 8px rgba(0, 0, 0, .04) !important;
    border: 1px solid rgba(0, 0, 0, .06);
  }

  .card-header-accent {
    background: linear-gradient(90deg, #0ea5e9, #0284c7);
    color: #fff;
  }

  .card-header-accent .btn.btn-light {
    color: #0b5;
  }

  /* Avoid horizontal scroll on mobile */
  html,
  body {
    max-width: 100%;
    overflow-x: hidden;
  }

  /* Avatar (fixed aspect portrait) */
  .avatar-frame {
    width: 96px;
    height: 128px;
    border-radius: .5rem;
    overflow: hidden;
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, .08);
    background: #f1f3f5;
    flex: 0 0 auto;
  }

  .avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  #calendar {
    overflow: hidden;
  }

  .cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-top: 1px solid #dee2e6;
    border-left: 1px solid #dee2e6;
  }

  .cal-cell {
    min-height: 74px;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    padding: .25rem;
    position: relative;
  }

  .cal-cell .date {
    position: absolute;
    top: 2px;
    right: 4px;
    font-size: .8rem;
    color: #6c757d;
  }

  .cal-head {
    background: #f8f9fa;
    font-weight: 600;
    text-align: center;
    padding: .4rem 0;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
  }

  .mark-present {
    background: #e8f6ed;
  }

  .mark-absent {
    background: #fdecea;
  }

  .mark-none {
    background: #fff;
  }

  /* Mobile tweaks */
  @media (max-width: 575.98px) {
    .dashboard-container {
      padding-left: .5rem !important;
      padding-right: .5rem !important;
    }

    .header-stack {
      flex-direction: row;
      align-items: flex-start;
    }

    .avatar-frame {
      width: 80px;
      height: 106px;
      margin-bottom: .5rem;
    }

    .student-name {
      font-size: 1rem;
    }

    .course-badge {
      display: inline-block;
      max-width: 100%;
      overflow-wrap: anywhere;
      white-space: normal;
      line-height: 1.2;
    }

    .calendar-toolbar {
      flex-direction: row;
    }

    #calendar {
      overflow-x: hidden;
    }

    .cal-cell {
      min-height: 60px;
    }
  }

  /* OnPeak card alignment tweaks */
  .onpeak-card {
    width: 100%;
  }

  .onpeak-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .onpeak-card .card-header strong {
    display: inline-flex;
    align-items: center;
  }

  .onpeak-card .card-body {
    padding-top: .75rem;
    padding-bottom: .75rem;
  }

  @media (max-width: 575.98px) {
    .onpeak-card .card-header {
      padding: .5rem .75rem;
    }

    .onpeak-card .btn {
      white-space: nowrap;
    }

    .onpeak-card table {
      font-size: .92rem;
    }
  }
</style>
<script>
  (function() {
    const base = <?php echo json_encode($base); ?>;
    const calendarEl = document.getElementById('calendar');
    const monthInput = document.getElementById('attMonth');
    const btnSummary = document.getElementById('attSummaryBtn');
    const modalEl = document.getElementById('attSummaryModal');
    const sumPresent = document.getElementById('sumPresent');
    const sumAbsent = document.getElementById('sumAbsent');
    const sumNotMarked = document.getElementById('sumNotMarked');
    const sumPercent = document.getElementById('sumPercent');
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    monthInput.value = y + '-' + m;
    let lastData = null;

    async function fetchAttendance(monthStr) {
      const url = base + '/student/attendance_api.php?month=' + encodeURIComponent(monthStr);
      const res = await fetch(url, {
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error('Failed to load attendance');
      return res.json();
    }

    function buildCalendar(monthStr, data) {
      const parts = monthStr.split('-');
      const year = parseInt(parts[0], 10),
        mon = parseInt(parts[1], 10) - 1;
      const first = new Date(year, mon, 1);
      const last = new Date(year, mon + 1, 0);
      const startDow = first.getDay(); // 0=Sun
      const days = last.getDate();

      const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      const fragment = document.createDocumentFragment();

      const head = document.createElement('div');
      head.className = 'cal-grid';
      for (let i = 0; i < 7; i++) {
        const h = document.createElement('div');
        h.className = 'cal-head';
        h.textContent = weekdayNames[i];
        head.appendChild(h);
      }
      fragment.appendChild(head);

      const grid = document.createElement('div');
      grid.className = 'cal-grid';

      // leading empty cells
      for (let i = 0; i < startDow; i++) {
        const c = document.createElement('div');
        c.className = 'cal-cell mark-none';
        grid.appendChild(c);
      }

      // day cells
      for (let d = 1; d <= days; d++) {
        const dateStr = year + '-' + String(mon + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        const mark = data.days && data.days[dateStr];
        const cls = mark === 1 ? 'mark-present' : (mark === 0 ? 'mark-absent' : 'mark-none');
        const cell = document.createElement('div');
        cell.className = 'cal-cell ' + cls;
        const num = document.createElement('div');
        num.className = 'date';
        num.textContent = d;
        cell.appendChild(num);
        grid.appendChild(cell);
      }

      // trailing fill to complete weeks
      const totalCells = startDow + days;
      const remain = (7 - (totalCells % 7)) % 7;
      for (let i = 0; i < remain; i++) {
        const c = document.createElement('div');
        c.className = 'cal-cell mark-none';
        grid.appendChild(c);
      }

      calendarEl.innerHTML = '';
      calendarEl.appendChild(fragment);
      calendarEl.appendChild(grid);
    }

    function renderFooterSummary(monthStr, data) {
      try {
        const [yy, mm] = monthStr.split('-').map(n => parseInt(n, 10));
        const first = new Date(yy, mm - 1, 1);
        const last = new Date(yy, mm, 0);
        let workdays = 0;
        for (let d = new Date(first); d <= last; d.setDate(d.getDate() + 1)) {
          const w = d.getDay();
          if (w === 0 || w === 6) continue;
          workdays++;
        }
        const days = data && data.days ? data.days : {};
        let present = 0,
          absent = 0;
        Object.keys(days).forEach(k => {
          if (days[k] === 1) present++;
          else if (days[k] === 0) absent++;
        });
        const notMarked = Math.max(0, workdays - (present + absent));
        const pct = workdays > 0 ? Math.round((present / workdays) * 100) : 0;
        const fp = document.getElementById('footPresent');
        if (fp) fp.textContent = String(present);
        const fa = document.getElementById('footAbsent');
        if (fa) fa.textContent = String(absent);
        const fn = document.getElementById('footNotMarked');
        if (fn) fn.textContent = String(notMarked);
        const fr = document.getElementById('footPercent');
        if (fr) fr.textContent = pct + '%';
      } catch (e) {
        /* no-op */ }
    }

    async function refresh() {
      const monthStr = monthInput.value;
      try {
        const data = await fetchAttendance(monthStr);
        lastData = data;
        buildCalendar(monthStr, data);
        renderFooterSummary(monthStr, data);
      } catch (e) {
        console.error(e);
        calendarEl.textContent = 'Failed to load';
      }
    }

    monthInput.addEventListener('change', refresh);
    btnSummary && btnSummary.addEventListener('click', function() {
      if (!lastData) return;
      try {
        const month = monthInput.value;
        // compute weekdays in month
        const [yy, mm] = month.split('-').map(n => parseInt(n, 10));
        const first = new Date(yy, mm - 1, 1);
        const last = new Date(yy, mm, 0);
        let workdays = 0;
        for (let d = new Date(first); d <= last; d.setDate(d.getDate() + 1)) {
          const w = d.getDay();
          if (w === 0 || w === 6) continue;
          workdays++;
        }
        const days = lastData && lastData.days ? lastData.days : {};
        let present = 0,
          absent = 0;
        Object.keys(days).forEach(k => {
          if (days[k] === 1) present++;
          else if (days[k] === 0) absent++;
        });
        const notMarked = Math.max(0, workdays - (present + absent));
        const pct = workdays > 0 ? Math.round((present / workdays) * 100) : 0;
        if (sumPresent) sumPresent.textContent = String(present);
        if (sumAbsent) sumAbsent.textContent = String(absent);
        if (sumNotMarked) sumNotMarked.textContent = String(notMarked);
        if (sumPercent) sumPercent.textContent = pct + '%';
        if (window.jQuery) {
          jQuery('#attSummaryModal').modal('show');
        } else {
          modalEl && (modalEl.style.display = 'block');
        }
      } catch (e) {
        console.error(e);
      }
    });
    // Roommate modal wiring
    function bindRoommateLinks() {
      document.querySelectorAll('.roommate-link').forEach(function(btn) {
        btn.addEventListener('click', async function() {
          var sid = this.getAttribute('data-stu');
          if (!sid) return;
          try {
            if (window.jQuery) jQuery('#roommateModal').modal('show');
            document.getElementById('rmcLoading').style.display = 'block';
            document.getElementById('rmcContent').style.display = 'none';
            const url = base + '/student/roommate_contact.php?student_id=' + encodeURIComponent(sid);
            const res = await fetch(url, {
              credentials: 'same-origin'
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.message || 'Failed');
            var c = data.contact || {};
            document.getElementById('rmcName').textContent = c.student_fullname || c.student_ininame || c.student_id || '-';
            document.getElementById('rmcId').textContent = c.student_id ? '(' + c.student_id + ')' : '';
            document.getElementById('rmcEmName').textContent = c.student_em_name || '-';
            document.getElementById('rmcEmRel').textContent = c.student_em_relation || '-';
            document.getElementById('rmcEmPhone').textContent = c.student_em_phone || '-';
            document.getElementById('rmcEmAddr').textContent = c.student_em_address || '-';
            document.getElementById('rmcPhone').textContent = c.student_phone || '-';
            document.getElementById('rmcEmail').textContent = c.student_email || '-';
            document.getElementById('rmcWA').textContent = c.student_whatsapp || '-';
            document.getElementById('rmcLoading').style.display = 'none';
            document.getElementById('rmcContent').style.display = 'block';
          } catch (e) {
            document.getElementById('rmcLoading').textContent = 'Failed to load contact';
          }
        });
      });
    }
    // Initial bind on load
    bindRoommateLinks();
    // OnPeak request modal button
    (function() {
      var btn = document.getElementById('onpeakRequestBtn');
      var frame = document.getElementById('onpeakFrame');
      if (btn && frame) {
        btn.addEventListener('click', function() {
          var url = base + '/onpeak/RequestOnPeak.php';
          frame.src = url;
          if (window.jQuery) jQuery('#onpeakModal').modal('show');
        });
        // Clear iframe on modal hide to free memory
        if (window.jQuery) {
          jQuery('#onpeakModal').on('hidden.bs.modal', function() {
            frame.src = 'about:blank';
          });
        }
      }
    })();
    refresh();
  })();
</script>
<?php include_once __DIR__ . '/../footer.php'; ?>