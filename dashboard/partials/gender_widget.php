<?php
// Lightweight gender charts widget for embedding in dashboard/index.php
// - No head/menu/footer includes
// - Uses Chart.js already loaded globally (footer.php)
?>
<div class="row mt-3">
  <div class="col-md-6 col-sm-12 mb-3">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Overall Gender (All Departments)</h6>
        <div class="small text-muted">Male vs Female totals</div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="position: relative; height:300px;">
          <canvas id="genderPieChart_embed"></canvas>
          <div id="genderPieEmpty_embed" class="text-center text-muted" style="position:absolute;top:0;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:center;">
            <div><i class="fas fa-info-circle"></i> No data found</div>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center small text-muted">
        <span id="gw_dept_count_embed">Departments: —</span>
        <span id="gw_gender_totals_embed">M: — F: — T: —</span>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-sm-12 mb-3">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Department-wise</h6>
        
      </div>
      <div class="card-body">
        <div id="deptCharts_embed" class="row"></div>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  var genderPieChart;
  var genderDeptData = [];
  function isAdminDepartment(name){
    var n = String(name||'').trim().toLowerCase();
    return n === 'admin' || n === 'administration' || /(^|\s)admin(istration)?(\s|$)/.test(n);
  }
  function computeDeptCountAll(){
    var cnt = 0;
    genderDeptData.forEach(function(it){
      if (isAdminDepartment(it.department)) return;
      var m = Number(it.male||0), f = Number(it.female||0);
      if ((m+f) > 0) cnt++;
    });
    return cnt;
  }
  function fetchGenderData(){
    return fetch("<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/dashboard/gender_distribution_api.php?get_gender_data=1&status=Following&conduct=accepted")
      .then(r=>r.json()).then(j=> j.status==='success'? j.data: []).catch(()=>[]);
  }
  function computeCountsAll(){
    var male=0,female=0;
    genderDeptData.forEach(function(it){
      if (isAdminDepartment(it.department)) return;
      male += Number(it.male||0);
      female += Number(it.female||0);
    });
    return {male: male, female: female};
  }
  function renderPie(){
    var ctx = document.getElementById('genderPieChart_embed');
    if (!ctx) return;
    ctx = ctx.getContext('2d');
    var counts = computeCountsAll();
    var total = (Number(counts.male)||0) + (Number(counts.female)||0);
    var emptyEl = document.getElementById('genderPieEmpty_embed');
    // Update footer info
    var deptEl = document.getElementById('gw_dept_count_embed');
    var totalsEl = document.getElementById('gw_gender_totals_embed');
    if (deptEl) deptEl.textContent = 'Departments: ' + computeDeptCountAll();
    if (totalsEl) totalsEl.textContent = 'M:' + (counts.male||0) + ' F:' + (counts.female||0) + ' T:' + (total||0);
    if (genderPieChart) genderPieChart.destroy();
    if (!total){
      if (emptyEl) emptyEl.style.display = 'flex';
      return;
    } else if (emptyEl){
      emptyEl.style.display = 'none';
    }
    genderPieChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Male','Female'],
        datasets: [{
          data: [counts.male, counts.female],
          backgroundColor: ['rgba(54, 162, 235, 0.8)','rgba(255, 99, 132, 0.8)'],
          borderColor: ['rgba(54, 162, 235, 1)','rgba(255, 99, 132, 1)'],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        legend: { position: 'top' },
        cutoutPercentage: 60
      }
    });
  }
  function renderDeptCharts(){
    var container = document.getElementById('deptCharts_embed');
    if(!container) return;
    container.innerHTML = '';
    genderDeptData.forEach(function(it){
      if (isAdminDepartment(it.department)) return;
      var col = document.createElement('div');
      col.className = 'col-sm-6 col-md-4 col-lg-3 mb-3';
      var card = document.createElement('div');
      card.className = 'border rounded p-2 h-100';
      var title = document.createElement('div');
      title.className = 'small text-truncate font-weight-bold mb-2';
      title.title = it.department||'';
      title.textContent = it.department||'';
      var canvas = document.createElement('canvas');
      canvas.height = 120; canvas.style.maxHeight = '120px';
      var footer = document.createElement('div');
      footer.className = 'small text-muted mt-2 text-right';
      var m = Number(it.male||0), f = Number(it.female||0), t = Number(it.total||(m+f));
      footer.textContent = 'M:'+m+' F:'+f+' T:'+t;
      card.appendChild(title); card.appendChild(canvas); card.appendChild(footer);
      col.appendChild(card); container.appendChild(col);
      var ctx = canvas.getContext('2d');
      new Chart(ctx, {
        type: 'doughnut',
        data: { labels: ['Male','Female'], datasets: [{
          data: [m,f],
          backgroundColor: ['rgba(54, 162, 235, 0.8)','rgba(255, 99, 132, 0.8)'],
          borderColor: ['rgba(54, 162, 235, 1)','rgba(255, 99, 132, 1)'],
          borderWidth: 1
        }]},
        options: { responsive: true, maintainAspectRatio: false, legend: {display:false}, cutoutPercentage: 60 }
      });
    });
  }
  function init(){
    fetchGenderData().then(function(data){
      genderDeptData = Array.isArray(data)? data: [];
      function ensure(){ if (window.Chart){ renderPie(); renderDeptCharts(); } else { setTimeout(ensure, 50); } }
      ensure();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
