<?php
// HODTimetable.php — HOD can select a group (own dept) and manage its timetable (CRUD)
// nginx/1.18 (Ubuntu) friendly, JSON-safe
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../library/access_control.php';

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
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';

// Compute sensible default AY (Aug-May)
if ($academic_year === '') {
  $cy = (int)date('Y');
  $cm = (int)date('n');
  $baseY = $cm >= 8 ? $cy : $cy - 1;
  $academic_year = $baseY . '-' . ($baseY + 1);
}

// Fetch groups for this HOD's department
$groups = [];
if ($department_id !== '') {
  $sqlG = "SELECT g.id, COALESCE(NULLIF(TRIM(g.group_name),''), CONCAT('Group #', g.id)) AS label
           FROM `groups` g
           JOIN course c ON c.course_id = g.course_id
           WHERE c.department_id = ?
           ORDER BY label";
  if ($st = $con->prepare($sqlG)) {
    $st->bind_param('s', $department_id);
    if ($st->execute()) {
      $rs = $st->get_result();
      while ($row = $rs->fetch_assoc()) { $groups[] = $row; }
    }
    $st->close();
  }
}

// Fetch selected group details (course_id, labels)
$group = null;
if ($group_id > 0) {
  $sql = "SELECT g.id AS group_id, g.group_name, g.group_code, c.course_id, c.course_name, c.department_id, d.department_name
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

$title = 'HOD Timetable Management';
require_once __DIR__ . '/../head.php';
include __DIR__ . '/../menu.php';
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="fas fa-calendar-alt mr-2"></i>HOD Timetable</h4>
    <div class="d-flex align-items-center">
      <form class="form-inline" id="filters">
        <div class="form-group mr-2">
          <label for="groupSel" class="mr-2 mb-0">Group</label>
          <select id="groupSel" name="group_id" class="form-control selectpicker" data-live-search="true" title="Select group">
            <?php foreach ($groups as $g): $sel = ($group_id === (int)$g['id']) ? 'selected' : ''; ?>
              <option value="<?php echo (int)$g['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($g['label']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mr-2">
          <label for="moduleSel" class="mr-2 mb-0">Module</label>
          <select id="moduleSel" class="form-control selectpicker" data-live-search="true" title="Select module"></select>
        </div>
        <div class="form-group mr-2">
          <label for="staffSel" class="mr-2 mb-0">Staff</label>
          <select id="staffSel" class="form-control selectpicker" data-live-search="true" title="Select staff"></select>
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
          <strong><?php echo htmlspecialchars($group['group_name'] ?: ($group['group_code'] ?: ('Group #'.$group_id))); ?></strong>
          <span class="text-muted ml-2">Course: <?php echo htmlspecialchars($group['course_name'] ?? ''); ?></span>
          <span class="text-muted ml-2">Dept: <?php echo htmlspecialchars($group['department_name'] ?? ''); ?></span>
        <?php else: ?>
          <span class="text-muted">Select a group to manage timetable</span>
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
              <tr class="<?php echo ($dno>=6?'weekend':''); ?>">
                <th class="align-middle"><?php echo $dname; ?></th>
                <?php foreach(['P1','P2','P3','P4'] as $p): ?>
                  <td class="timetable-slot" data-day="<?php echo $dno; ?>" data-period="<?php echo $p; ?>">
                    <div class="timetable-content"></div>
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
.timetable-slot { min-height: 90px; cursor: pointer; }
.timetable-entry { border-radius: 4px; padding: 6px; margin: 2px 0; color: #fff; font-size: 0.85rem; position: relative; }
.timetable-entry .module-code { font-weight: 700; display:block; }
.timetable-entry .staff-name { font-size: .78rem; opacity: .95; display:block; }
.timetable-entry .classroom { position:absolute; right:6px; bottom:4px; font-size: .7rem; background: rgba(0,0,0,.2); padding: 0 4px; border-radius: 3px; }
/* Hide original native selects after enhancement and enforce width */
.selectpicker.bs-select-hidden { display: none !important; }
.select.selectpicker { display: none !important; }
.bootstrap-select { width: 100% !important; }
</style>

<script>
(function(){
  const APP_BASE = <?php echo json_encode($APP_BASE); ?>;
  const groupId = <?php echo (int)$group_id; ?>;
  const academicYear = <?php echo json_encode($academic_year); ?>;
  const groupCourseId = <?php echo json_encode($group['course_id'] ?? ''); ?>;

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
      // Apply filter
      $('#applyFilters').on('click', function(){
        const gid = $('#groupSel').val() || '';
        const ay  = $('#academic_year').val() || '';
        const loc = new URL(window.location.href);
        if (gid) loc.searchParams.set('group_id', gid); else loc.searchParams.delete('group_id');
        if (ay)  loc.searchParams.set('academic_year', ay); else loc.searchParams.delete('academic_year');
        window.location.href = loc.toString();
      });

      // Init pickers
      try { if ($.fn.selectpicker) { $('.selectpicker').each(function(){ var $s=$(this); if ($s.data('selectpicker')) $s.selectpicker('refresh'); else $s.selectpicker({style:'btn-light',size:8}); if ($s.next('.bootstrap-select').length) { $s.addClass('bs-select-hidden').hide(); } }); } } catch(_){ }

      // Load top Module options based on currently selected group (via group_id derivative in API)
      function loadTopModules(){ var gid=$('#groupSel').val()||groupId; var $sel=$('#moduleSel'); $sel.empty(); if(!gid){ $sel.append($('<option>',{value:'',text:'Select group first',disabled:true,selected:true})); try{$sel.selectpicker('refresh');}catch(_){} return Promise.resolve(); } return $.get(APP_BASE + '/controller/ajax/get_course_modules.php', { group_id: gid }, null, 'json').then(function(mods){ try{ if(typeof mods==='string') mods=JSON.parse(mods);}catch(e){} $sel.empty(); if(Array.isArray(mods)&&mods.length){ mods.forEach(function(m){ var txt=(m.module_id?(m.module_id+' - '):'')+(m.module_name||''); $sel.append($('<option>',{value:m.module_id, text: txt})); }); } else { $sel.append($('<option>',{value:'',text:'No modules',disabled:true,selected:true})); } try{$sel.selectpicker('refresh'); if($sel.next('.bootstrap-select').length){ $sel.addClass('bs-select-hidden').hide(); }}catch(_){} }); }

      // Load top Staff options (department-scoped server-side)
      function loadTopStaff(){ var $sel=$('#staffSel'); return $.get(APP_BASE + '/controller/ajax/get_staff.php', null, null, 'json').then(function(st){ try{ if(typeof st==='string') st=JSON.parse(st);}catch(e){} $sel.empty(); if(Array.isArray(st)&&st.length){ st.forEach(function(s){ $sel.append($('<option>',{value:s.staff_id, text: s.staff_name})); }); } else { $sel.append($('<option>',{value:'',text:'No staff',disabled:true,selected:true})); } try{$sel.selectpicker('refresh'); if($sel.next('.bootstrap-select').length){ $sel.addClass('bs-select-hidden').hide(); }}catch(_){} }); }

      // Reload Module list when group changes (without navigating)
      $('#groupSel').on('changed.bs.select', function(){ loadTopModules(); });

      // Prefill the Add/Edit modal with the top selections, if chosen
      function applyTopSelectionsToModal(){ var mod=$('#moduleSel').val(); var stf=$('#staffSel').val(); if(mod){ $('#module_id').val(mod); try{$('#module_id').selectpicker('refresh');}catch(_){}} if(stf){ $('#staff_id').val(stf); try{$('#staff_id').selectpicker('refresh');}catch(_){}} }

      if (!groupId) { loadTopStaff(); return; }

      function colorForKey(key){ var colors=['#3498db','#2ecc71','#e74c3c','#f39c12','#9b59b6','#1abc9c','#d35400','#34495e','#7f8c8d','#27ae60']; var h=0,s=String(key||''); for(var i=0;i<s.length;i++){ h=((h<<5)-h)+s.charCodeAt(i); h|=0; } return colors[Math.abs(h)%colors.length]; }

      function renderTimetable(data){
        const map={}; (data||[]).forEach(function(e){ map[e.weekday]=map[e.weekday]||{}; map[e.weekday][e.period]=map[e.weekday][e.period]||[]; map[e.weekday][e.period].push(e); });
        $('#timetableTable tbody td').each(function(){ var day=$(this).data('day'), period=$(this).data('period'); var list=(map[day]&&map[day][period])?map[day][period]:[]; var cont=$(this).find('.timetable-content'); cont.empty(); if(list.length===0){ cont.append('<div class="text-muted text-center py-2">Add</div>'); return; } list.forEach(function(e){ var $div=$('<div class="timetable-entry"></div>').css('background-color', colorForKey(e.module_id)).attr('data-id', e.timetable_id); $div.append('<span class="module-code">'+(e.module_id||'')+'</span>'); if(e.staff_name) $div.append('<span class="staff-name">'+e.staff_name+'</span>'); if(e.classroom) $div.append('<span class="classroom">'+e.classroom+'</span>'); var $btns=$('<div class="timetable-actions"></div>'); $btns.append('<button type="button" class="btn btn-sm btn-light btn-edit">Edit</button> '); $btns.append('<button type="button" class="btn btn-sm btn-danger btn-delete">Del</button>'); $div.append($btns); cont.append($div); }); });
      }

      function safeParse(txt){ try{ if(typeof txt!=='string') return txt; let t=txt.trim(); const f=t.indexOf('{'), l=t.lastIndexOf('}'); if(f!==-1&&l!==-1&&l>f) return JSON.parse(t.substring(f,l+1)); }catch(e){} return null; }

      function loadTimetable(){ $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'GET', data: { action:'list', group_id: groupId, academic_year: academicYear } }).done(function(resp){ var j = safeParse(resp) || resp; if(j && j.success){ renderTimetable(j.data); } }); }

      function loadModules(){ var $sel=$('#module_id'); return $.get(APP_BASE + '/controller/ajax/get_course_modules.php', { course_id: groupCourseId, group_id: groupId }, null, 'json').then(function(mods){ try{ if(typeof mods==='string') mods=JSON.parse(mods); }catch(e){} $sel.empty(); if(Array.isArray(mods)&&mods.length){ mods.forEach(function(m){ var txt=(m.module_id?(m.module_id+' - '):'')+(m.module_name||''); $sel.append($('<option>',{value:m.module_id, text: txt})); }); } else { $sel.append($('<option>',{ value:'', text:'No modules', disabled:true, selected:true })); } try{ $sel.selectpicker('refresh'); if($sel.next('.bootstrap-select').length){ $sel.addClass('bs-select-hidden').hide(); } }catch(_){ } }); }
      function loadStaff(){ var $sel=$('#staff_id'); return $.get(APP_BASE + '/controller/ajax/get_staff.php', null, null, 'json').then(function(st){ try{ if(typeof st==='string') st=JSON.parse(st); }catch(e){} $sel.empty(); if(Array.isArray(st)&&st.length){ st.forEach(function(s){ $sel.append($('<option>',{value:s.staff_id, text: s.staff_name})); }); } else { $sel.append($('<option>',{ value:'', text:'No staff', disabled:true, selected:true })); } try{ $sel.selectpicker('refresh'); if($sel.next('.bootstrap-select').length){ $sel.addClass('bs-select-hidden').hide(); } }catch(_){ } }); }

      function resetForm(){ $('#timetable_id').val(0); $('#module_id').val(''); $('#staff_id').val(''); $('#classroom').val(''); $('#start_date').val(''); $('#end_date').val(''); try{ $('.selectpicker').selectpicker('refresh'); }catch(_){ } $('#formError').addClass('d-none').empty(); }

      // Click on empty slot => Add
      $(document).on('click', '.timetable-slot', function(e){ if($(e.target).closest('.timetable-entry').length) return; var day=$(this).data('day'); var period=$(this).data('period'); resetForm(); $('#weekday').val(day); $('#period').val(period); applyTopSelectionsToModal(); $('#timetableModalLabel').text('Add Timetable Entry'); $('#timetableModal').modal('show'); });

      // Edit existing entry
      $(document).on('click', '.btn-edit', function(e){ e.stopPropagation(); var id=$(this).closest('.timetable-entry').data('id'); $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'GET', dataType:'json', data:{ action:'get', timetable_id:id } }).done(function(r){ if(r && r.success && r.data){ var d=r.data; resetForm(); $('#timetable_id').val(d.timetable_id); $('#weekday').val(d.weekday); $('#period').val(d.period); $('#classroom').val(d.classroom||''); $('#start_date').val(d.start_date||''); $('#end_date').val(d.end_date||''); $('#module_id').val(d.module_id); $('#staff_id').val(d.staff_id); try{ $('.selectpicker').selectpicker('refresh'); }catch(_){ } $('#timetableModalLabel').text('Edit Timetable Entry'); $('#timetableModal').modal('show'); } }); });

      // Delete
      $(document).on('click', '.btn-delete', function(e){ e.stopPropagation(); if(!confirm('Delete this timetable entry?')) return; var id=$(this).closest('.timetable-entry').data('id'); $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'POST', dataType:'json', data:{ action:'delete', timetable_id:id, hard_delete:false } }).done(function(r){ loadTimetable(); }); });

      // Save
      $('#timetableForm').on('submit', function(e){ e.preventDefault(); var data=$(this).serialize()+'&action=save'; $.ajax({ url: APP_BASE + '/controller/GroupTimetableController.php', type:'POST', dataType:'json', data:data }).done(function(r){ if(r && r.success){ $('#timetableModal').modal('hide'); loadTimetable(); } else { $('#formError').removeClass('d-none').text((r&&r.message)||'Save failed'); } }); });

      // Load data
      Promise.all([loadModules(), loadStaff(), loadTopModules(), loadTopStaff()]).then(loadTimetable);

      // Print
      $('#printTimetable').on('click', function(){ var w=window.open('', '_blank'); w.document.write('<!doctype html><html><head><title>Timetable</title><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"></head><body>'+document.querySelector('.table-responsive').outerHTML+'</body></html>'); w.document.close(); w.focus(); setTimeout(function(){ w.print(); }, 300); });

      // PDF
      $('#downloadPDF').on('click', async function(){ function add(src){ return new Promise(res=>{ var s=document.createElement('script'); s.src=src; s.async=true; s.onload=res; s.onerror=res; document.head.appendChild(s); }); } if(!window.html2canvas) await add('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'); if(!(window.jspdf && window.jspdf.jsPDF)) await add('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'); var node=document.querySelector('.table-responsive'); var canvas=await html2canvas(node,{scale:2,backgroundColor:'#ffffff'}); var pdf=new (window.jspdf.jsPDF)({orientation:'landscape',unit:'pt',format:'a4'}); var pw=pdf.internal.pageSize.getWidth(), ph=pdf.internal.pageSize.getHeight(); var iw=pw-40, ih=(canvas.height*iw)/canvas.width; pdf.addImage(canvas.toDataURL('image/png'),'PNG',20,20,iw,Math.min(ih,ph-40)); pdf.save('Timetable_'+groupId+'_'+academicYear+'.pdf'); });
    });
  });
})();
</script>
<?php include __DIR__ . '/../footer.php'; ?>
