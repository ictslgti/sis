<?php
// DepartmentTimetable.php — HOD/Admin can select a course and group from own department and manage timetable
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$APP_BASE = defined('APP_BASE') ? APP_BASE : '';
$user_type = $_SESSION['user_type'] ?? '';
$department_id = $_SESSION['department_id'] ?? ($_SESSION['department_code'] ?? '');

// Permissions: HOD + admin roles
if (!in_array($user_type, ['HOD','ADM','ADMIN','IN3'], true)) {
  $_SESSION['error'] = 'Access denied';
  header('Location: ' . $APP_BASE . '/home/home.php');
  exit;
}

// Params
$course_id = isset($_GET['course_id']) ? trim((string)$_GET['course_id']) : '';
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$academic_year = isset($_GET['academic_year']) ? trim((string)$_GET['academic_year']) : '';

// Compute sensible default AY (Aug-May)
if ($academic_year === '') {
  $cy = (int)date('Y');
  $cm = (int)date('n');
  $baseY = $cm >= 8 ? $cy : $cy - 1;
  $academic_year = $baseY . '-' . ($baseY + 1);
}

// Fetch courses for this HOD's department
$courses = [];
if ($department_id !== '') {
  $sql = "SELECT course_id, course_name FROM course WHERE department_id = ? ORDER BY course_name";
  if ($st = $con->prepare($sql)) {
    $st->bind_param('s', $department_id);
    if ($st->execute()) {
      $rs = $st->get_result();
      while ($row = $rs->fetch_assoc()) { $courses[] = $row; }
    }
    $st->close();
  }
}

// Fetch groups for selected course (scoped to dept via join)
$groups = [];
if ($course_id !== '') {
  $sqlG = "SELECT g.id, COALESCE(NULLIF(TRIM(g.group_name),''), COALESCE(NULLIF(TRIM(g.name),''), CONCAT('Group #', g.id))) AS label
           FROM `groups` g JOIN course c ON c.course_id = g.course_id
           WHERE g.course_id = ?";
  if ($department_id !== '') { $sqlG .= " AND c.department_id = ?"; }
  $sqlG .= " ORDER BY label";
  if ($st = $con->prepare($sqlG)) {
    if ($department_id !== '') { $st->bind_param('ss', $course_id, $department_id); } else { $st->bind_param('s', $course_id); }
    if ($st->execute()) { $rs = $st->get_result(); while ($row = $rs->fetch_assoc()) { $groups[] = $row; } }
    $st->close();
  }
}

// Fetch selected group info for header
$group = null;
if ($group_id > 0) {
  $sql = "SELECT g.id AS group_id, COALESCE(NULLIF(TRIM(g.group_name),''), COALESCE(NULLIF(TRIM(g.name),''), CONCAT('Group #', g.id))) AS group_name,
                 c.course_id, c.course_name, c.department_id, d.department_name
          FROM `groups` g
          JOIN course c ON c.course_id = g.course_id
          LEFT JOIN department d ON d.department_id = c.department_id
          WHERE g.id = ? LIMIT 1";
  if ($st = $con->prepare($sql)) {
    $st->bind_param('i', $group_id);
    if ($st->execute()) { $group = $st->get_result()->fetch_assoc(); }
    $st->close();
  }
}

$title = 'Department Timetable Management';
require_once __DIR__ . '/../head.php';
include __DIR__ . '/../menu.php';
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>Department Timetable</h4>
    <div class="d-flex align-items-center">
      <form class="form-inline" id="filters">
        <div class="form-group mr-2">
          <label for="courseSel" class="mr-2 mb-0">Course</label>
          <select id="courseSel" name="course_id" class="form-control selectpicker" data-live-search="true" title="Select course">
            <?php foreach ($courses as $c): $sel = ($course_id !== '' && $course_id === $c['course_id']) ? 'selected' : ''; ?>
              <option value="<?php echo htmlspecialchars($c['course_id']); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars(($c['course_id'] ? ($c['course_id'].' - ') : '').$c['course_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mr-2">
          <label for="groupSel" class="mr-2 mb-0">Group</label>
          <select id="groupSel" name="group_id" class="form-control selectpicker" data-live-search="true" title="Select group" <?php echo ($course_id==='')?'disabled':''; ?>>
            <?php if ($course_id===''): ?>
              <option value="" disabled selected>Select course first</option>
            <?php else: foreach ($groups as $g): $sel = ($group_id === (int)$g['id']) ? 'selected' : ''; ?>
              <option value="<?php echo (int)$g['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($g['label']); ?></option>
            <?php endforeach; endif; ?>
          </select>
        </div>
        <div class="form-group mr-2">
          <label for="academic_year" class="mr-2 mb-0">Academic Year</label>
          <select id="academic_year" name="academic_year" class="form-control">
            <?php $cy=(int)date('Y'); for($i=$cy-2;$i<=$cy+2;$i++){ $ay=$i.'-'.($i+1); $sel=$ay===$academic_year?'selected':''; echo '<option '.$sel.'>'.htmlspecialchars($ay).'</option>'; } ?>
          </select>
        </div>
        <button type="button" class="btn btn-primary" id="applyFilters">Apply</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <?php if ($group): ?>
          <strong><?php echo htmlspecialchars($group['group_name']); ?></strong>
          <span class="text-muted ml-2">Course: <?php echo htmlspecialchars($group['course_name'] ?? ''); ?></span>
          <span class="text-muted ml-2">Dept: <?php echo htmlspecialchars($group['department_name'] ?? ''); ?></span>
        <?php else: ?>
          <span class="text-muted">Select course and group to manage timetable</span>
        <?php endif; ?>
      </div>
      <div>
        <button class="btn btn-sm btn-outline-secondary" id="printTimetable">Print</button>
        <button class="btn btn-sm btn-success" id="downloadPDF">Download PDF</button>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="timetableTable">
          <thead class="thead-light">
            <tr>
              <th style="width:120px">Days/Session</th>
              <th class="text-center">Session 1<br><small>08:30 - 10:00</small></th>
              <th class="text-center">Session 2<br><small>10:30 - 12:00</small></th>
              <th class="text-center">Session 3<br><small>13:00 - 14:30</small></th>
              <th class="text-center">Session 4<br><small>14:45 - 16:15</small></th>
            </tr>
          </thead>
          <tbody>
            <?php $days=[1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday']; foreach($days as $dno=>$dname): ?>
              <tr>
                <th class="align-middle"><?php echo $dname; ?></th>
                <?php foreach(['P1','P2','P3','P4'] as $p): ?>
                  <td class="timetable-slot" data-day="<?php echo $dno; ?>" data-period="<?php echo $p; ?>">
                    <div class="timetable-content text-muted text-center py-2">Add</div>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="timetableModal" tabindex="-1" role="dialog" aria-labelledby="timetableModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="timetableForm">
        <input type="hidden" id="timetable_id" name="timetable_id" value="0">
        <input type="hidden" id="group_id" name="group_id" value="<?php echo (int)$group_id; ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="timetableModalLabel">Add Timetable Entry</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">
          <div id="formError" class="alert alert-danger d-none"></div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Module <span class="text-danger">*</span></label>
              <select class="form-control selectpicker" id="module_id" name="module_id" data-live-search="true" title="Select module"></select>
            </div>
            <div class="form-group col-md-6">
              <label>Staff <span class="text-danger">*</span></label>
              <select class="form-control selectpicker" id="staff_id" name="staff_id" data-live-search="true" title="Select staff"></select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-3">
              <label>Weekday <span class="text-danger">*</span></label>
              <select class="form-control" id="weekday" name="weekday">
                <?php foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'] as $dn=>$dl): ?>
                  <option value="<?php echo $dn; ?>"><?php echo $dl; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>Session <span class="text-danger">*</span></label>
              <select class="form-control" id="period" name="period">
                <option value="P1">Session 1 (08:30 - 10:00)</option>
                <option value="P2">Session 2 (10:30 - 12:00)</option>
                <option value="P3">Session 3 (13:00 - 14:30)</option>
                <option value="P4">Session 4 (14:45 - 16:15)</option>
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>Classroom <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="classroom" name="classroom" placeholder="e.g., Practical LAP-01" required>
            </div>
            <div class="form-group col-md-3">
              <label>Start Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="start_date" name="start_date" required>
            </div>
            <div class="form-group col-md-3">
              <label>End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="end_date" name="end_date" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
#timetableTable { table-layout: fixed; }
#timetableTable th, #timetableTable td { vertical-align: top; }
.timetable-slot { min-height: 90px; cursor: pointer; padding: 8px; }
.timetable-slot .timetable-content { min-height: 74px; display: flex; align-items: center; justify-content: center; }
.timetable-entry { border-radius: 4px; padding: 8px 10px; margin: 0 auto; color: #fff; font-size: 0.85rem; position: relative; width: 88%; max-width: 280px; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
.timetable-entry .module-code { font-weight: 700; display:block; }
.timetable-entry .staff-name { font-size: .78rem; opacity: .95; display:block; }
.timetable-entry .classroom { position:absolute; right:6px; bottom:4px; font-size: .7rem; background: rgba(0,0,0,.2); padding: 0 4px; border-radius: 3px; }
/* Hide original native selects after enhancement and enforce width */
.selectpicker.bs-select-hidden { display: none !important; }
.select.selectpicker { display: none !important; }
.bootstrap-select { width: 100% !important; }
@media (max-width: 767.98px) {
  .timetable-entry { width: 96%; }
}
</style>

<script>
(function(){
  const APP_BASE = <?php echo json_encode($APP_BASE); ?>;
  const deptCourseId = <?php echo json_encode($course_id); ?>;
  let groupId = <?php echo (int)$group_id; ?>;
  const academicYear = <?php echo json_encode($academic_year); ?>;
  const groupCourseId = <?php echo json_encode($group['course_id'] ?? $course_id); ?>;

  function loadScriptOnce(url, testFn){ return new Promise(res=>{ try{ if(testFn && testFn()) return res(); }catch(_){ } var s=document.createElement('script'); s.src=url; s.async=true; s.onload=function(){ try{ if(!testFn || testFn()) res(); else res(); }catch(_){ res(); } }; s.onerror=function(){ res(); }; document.head.appendChild(s); }); }
  function ensureUiDeps(){
    const boot = APP_BASE + '/js/bootstrap.bundle.min.js';
    const sel  = APP_BASE + '/js/bootstrap-select.min.js';
    return loadScriptOnce(boot, function(){ return !!(window.jQuery && jQuery.fn && jQuery.fn.modal); })
      .then(function(){ return loadScriptOnce(sel, function(){ return !!(window.jQuery && jQuery.fn && jQuery.fn.selectpicker); }); });
  }
  (function ensureJQ(init){ if(window.jQuery) return init(window.jQuery); var s=document.createElement('script'); s.src=APP_BASE + '/js/jquery.min.js'; s.async=true; s.onload=function(){ init(window.jQuery); }; s.onerror=function(){ var c=document.createElement('script'); c.src='https://code.jquery.com/jquery-3.6.0.min.js'; c.async=true; c.onload=function(){ init(window.jQuery); }; document.head.appendChild(c); }; document.head.appendChild(s); })(function($){
    $(document).ready(async function(){
      try { await ensureUiDeps(); } catch(_){ }

      // Apply filters
      $('#applyFilters').on('click', function(){
        const cid = $('#courseSel').val() || '';
        const gid = $('#groupSel').val() || '';
        const ay  = $('#academic_year').val() || '';
        const loc = new URL(window.location.href);
        if (cid) loc.searchParams.set('course_id', cid); else loc.searchParams.delete('course_id');
        if (gid) loc.searchParams.set('group_id', gid); else loc.searchParams.delete('group_id');
        if (ay)  loc.searchParams.set('academic_year', ay); else loc.searchParams.delete('academic_year');
        window.location.href = loc.toString();
      });

      // Init pickers
      try { if ($.fn.selectpicker) { $('.selectpicker').each(function(){ var $s=$(this); if ($s.data('selectpicker')) $s.selectpicker('refresh'); else $s.selectpicker({style:'btn-light',size:8}); if ($s.next('.bootstrap-select').length) { $s.addClass('bs-select-hidden').hide(); } }); } } catch(_){ }

      // Load groups when course changes
      function bindCourseChange(){
        function fetchGroups(cid, $g){
          // helper to set loading UI
          function setLoading(){ $g.empty().append($('<option>',{value:'',text:'Loading groups...',disabled:true,selected:true})); try{$g.selectpicker('refresh');}catch(_){ } }
          function setError(msg){ $g.empty().append($('<option>',{value:'',text:msg||'Failed to load groups',disabled:true,selected:true})); try{$g.selectpicker('refresh');}catch(_){ } }
          const urls = [
            (APP_BASE + '/controller/ajax/get_course_groups.php'),
            ('/controller/ajax/get_course_groups.php'),
            ('../controller/ajax/get_course_groups.php')
          ];
          const methods = ['POST','GET'];
          let attempt = 0;
          function tryNext(){
            if (attempt >= urls.length * methods.length) { setError('Failed to load groups'); return; }
            const url = urls[attempt % urls.length];
            const method = methods[Math.floor(attempt / urls.length) % methods.length];
            attempt++;
            $.ajax({ url: url, type: method, data: { course_id: cid }, dataType: 'html' })
              .done(function(html){ $g.empty().append(html); $g.prop('disabled', false); try{ $g.selectpicker('refresh'); if($g.next('.bootstrap-select').length){ $g.addClass('bs-select-hidden').hide(); $g.next('.bootstrap-select').removeClass('disabled'); } }catch(_){ } })
              .fail(function(xhr, status, err){ try{ console.warn('Group load failed', method, url, status, err); }catch(_){ } tryNext(); });
          }
          setLoading();
          tryNext();
        }
        function onCourseChange(){
          const cid = $('#courseSel').val();
          const $g = $('#groupSel');
          $g.prop('disabled', !cid);
          if (!cid) {
            $g.empty().append($('<option>',{value:'',text:'Select course first',disabled:true,selected:true}));
            try{$g.selectpicker('refresh');}catch(_){ }
            return;
          }
          fetchGroups(cid, $g);
        }
        // Bind both bootstrap-select synthetic event and native change for robustness
        $('#courseSel').on('changed.bs.select', onCourseChange);
        $('#courseSel').on('change', onCourseChange);
        // Trigger once on load if a course is already selected
        if ($('#courseSel').val()) { onCourseChange(); }
      }
      bindCourseChange();

      function colorForKey(key){ var colors=['#3498db','#2ecc71','#e74c3c','#f39c12','#9b59b6','#1abc9c','#d35400','#34495e','#7f8c8d','#27ae60']; var h=0,s=String(key||''); for(var i=0;i<s.length;i++){ h=((h<<5)-h)+s.charCodeAt(i); h|=0; } return colors[Math.abs(h)%colors.length]; }

      function renderTimetable(data){
        const map={}; (data||[]).forEach(function(e){ map[e.weekday]=map[e.weekday]||{}; map[e.weekday][e.period]=map[e.weekday][e.period]||[]; map[e.weekday][e.period].push(e); });
        $('#timetableTable tbody td').each(function(){
          var day=$(this).data('day'), period=$(this).data('period');
          var list=(map[day]&&map[day][period])?map[day][period]:[];
          var cont=$(this).find('.timetable-content');
          cont.empty();
          if(list.length===0){ cont.append('<div class="text-muted text-center py-2">Add</div>'); return; }
          // Deduplicate: keep only the most recent timetable row for this slot
          var chosen=list.reduce(function(best, cur){ var bid=(best&&best.timetable_id)||0; var cid=(cur&&cur.timetable_id)||0; return cid>bid?cur:best; }, null);
          var items = chosen? [chosen] : [list[0]];
          items.forEach(function(e){
            var $div=$('<div class="timetable-entry"></div>').css('background-color', colorForKey(e.module_id)).attr('data-id', e.timetable_id);
            $div.append('<span class="module-code">'+(e.module_id||'')+'</span>');
            if(e.staff_name) $div.append('<span class="staff-name">'+e.staff_name+'</span>');
            if(e.classroom) $div.append('<span class="classroom">'+e.classroom+'</span>');
            var $btns=$('<div class="timetable-actions"></div>');
            $btns.append('<button type="button" class="btn btn-sm btn-light btn-edit">Edit</button> ');
            $btns.append('<button type="button" class="btn btn-sm btn-danger btn-delete">Del</button>');
            $div.append($btns);
            cont.append($div);
          });
        });
      }

      function safeParse(txt){ try{ if(typeof txt!=='string') return txt; let t=txt.trim(); const f=t.indexOf('{'), l=t.lastIndexOf('}'); if(f!==-1&&l!==-1&&l>f) return JSON.parse(t.substring(f,l+1)); }catch(e){} return null; }

      function loadTimetable(){ if(!groupId){ $('#timetableTable .timetable-content').html('<div class="text-muted text-center py-2">Select course and group</div>'); return; } $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'GET', data: { action:'list', group_id: groupId, academic_year: academicYear } }).done(function(resp){ var j = safeParse(resp) || resp; if(j && j.success){ renderTimetable(j.data); } }); }

      function loadModules(){ var $sel=$('#module_id'); var cid=$('#courseSel').val()||groupCourseId; var gid=$('#groupSel').val()||groupId; return $.get(APP_BASE + '/controller/ajax/get_course_modules.php', { course_id: cid, group_id: gid }, null, 'json').then(function(mods){ try{ if(typeof mods==='string') mods=JSON.parse(mods); }catch(e){} $sel.empty(); if(Array.isArray(mods)&&mods.length){ mods.forEach(function(m){ var txt=(m.module_id?(m.module_id+' - '):'')+(m.module_name||''); $sel.append($('<option>',{value:m.module_id, text: txt})); }); } else { $sel.append($('<option>',{ value:'', text:'No modules', disabled:true, selected:true })); } try{ $sel.selectpicker('refresh'); if($sel.next('.bootstrap-select').length){ $sel.addClass('bs-select-hidden').hide(); } }catch(_){ } }); }
      function loadStaff(){ var $sel=$('#staff_id'); return $.get(APP_BASE + '/controller/ajax/get_staff.php', null, null, 'json').then(function(st){ try{ if(typeof st==='string') st=JSON.parse(st); }catch(e){} $sel.empty(); if(Array.isArray(st)&&st.length){ st.forEach(function(s){ $sel.append($('<option>',{value:s.staff_id, text: s.staff_name})); }); } else { $sel.append($('<option>',{ value:'', text:'No staff', disabled:true, selected:true })); } try{ $sel.selectpicker('refresh'); if($sel.next('.bootstrap-select').length){ $sel.addClass('bs-select-hidden').hide(); } }catch(_){ } }); }

      function resetForm(){ $('#timetable_id').val(0); $('#module_id').val(''); $('#staff_id').val(''); $('#classroom').val(''); $('#start_date').val(''); $('#end_date').val(''); try{ $('.selectpicker').selectpicker('refresh'); }catch(_){ } $('#formError').addClass('d-none').empty(); }

      // Click on empty slot => Add
      $(document).on('click', '.timetable-slot', function(e){ if(!groupId){ alert('Select course and group first'); return; } if($(e.target).closest('.timetable-entry').length) return; var day=$(this).data('day'); var period=$(this).data('period'); resetForm(); $('#weekday').val(day); $('#period').val(period); $('#timetableModalLabel').text('Add Timetable Entry'); $('#timetableModal').modal('show'); });

      // Edit existing entry
      $(document).on('click', '.btn-edit', function(e){ e.stopPropagation(); var id=$(this).closest('.timetable-entry').data('id'); $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'GET', dataType:'json', data:{ action:'get', timetable_id:id } }).done(function(r){ if(r && r.success && r.data){ var d=r.data; resetForm(); $('#timetable_id').val(d.timetable_id); $('#weekday').val(d.weekday); $('#period').val(d.period); $('#classroom').val(d.classroom||''); $('#start_date').val(d.start_date||''); $('#end_date').val(d.end_date||''); $('#module_id').val(d.module_id); $('#staff_id').val(d.staff_id); try{ $('.selectpicker').selectpicker('refresh'); }catch(_){ } $('#timetableModalLabel').text('Edit Timetable Entry'); $('#timetableModal').modal('show'); } }); });

      // Delete
      $(document).on('click', '.btn-delete', function(e){ e.stopPropagation(); if(!confirm('Delete this timetable entry?')) return; var id=$(this).closest('.timetable-entry').data('id'); $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'POST', dataType:'json', data:{ action:'delete', timetable_id:id, hard_delete:false } }).done(function(r){ loadTimetable(); }); });

      // Save
      $('#timetableForm').on('submit', function(e){ e.preventDefault(); var data=$(this).serialize()+'&action=save'; if(!groupId){ $('#formError').removeClass('d-none').text('Select group first'); return; } $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'POST', dataType:'json', data:data }).done(function(r){ if(r && r.success){ $('#timetableModal').modal('hide'); loadTimetable(); } else { $('#formError').removeClass('d-none').text((r&&r.message)||'Save failed'); } }); });

      // Initial loads
      Promise.all([loadModules(), loadStaff()]).then(loadTimetable);

      // If user changes group selection in UI without applying filters, reflect in state (so cell add works)
      $('#groupSel').on('changed.bs.select', function(){ groupId = parseInt($(this).val()||'0',10)||0; $('#group_id').val(groupId); loadModules(); loadTimetable(); });

      // Print
      $('#printTimetable').on('click', function(){ var w=window.open('', '_blank'); w.document.write('<!doctype html><html><head><title>Timetable</title><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"></head><body>'+document.querySelector('.table-responsive').outerHTML+'</body></html>'); w.document.close(); w.focus(); setTimeout(function(){ w.print(); }, 300); });

      // PDF
      $('#downloadPDF').on('click', async function(){ function add(src){ return new Promise(res=>{ var s=document.createElement('script'); s.src=src; s.async=true; s.onload=res; s.onerror=res; document.head.appendChild(s); }); } if(!window.html2canvas) await add('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'); if(!(window.jspdf && window.jspdf.jsPDF)) await add('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'); var node=document.querySelector('.table-responsive'); var canvas=await html2canvas(node,{scale:2,backgroundColor:'#ffffff'}); var pdf=new (window.jspdf.jsPDF)({orientation:'landscape',unit:'pt',format:'a4'}); var pw=pdf.internal.pageSize.getWidth(), ph=pdf.internal.pageSize.getHeight(); var iw=pw-40, ih=(canvas.height*iw)/canvas.width; pdf.addImage(canvas.toDataURL('image/png'),'PNG',20,20,iw,Math.min(ih,ph-40)); pdf.save('DepartmentTimetable_'+(groupId||'')+'_'+academicYear+'.pdf'); });
    });
  });
})();
</script>
<?php include __DIR__ . '/../footer.php'; ?>
