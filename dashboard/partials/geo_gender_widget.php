<?php
// Province- and District-wise Student Count Charts Widget (Bar Charts)
// Expects $ggw_academic_year to be provided by parent if filtering needed
?>
<div class="row mt-4">
  <div class="col-md-6 col-sm-12 mb-3">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Province-wise Students</h6>
        <div class="small text-muted">Total current active students</div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="position: relative; height:320px;">
          <canvas id="geoProvinceBar"></canvas>
          <div id="geoProvinceEmpty" class="text-center text-muted" style="position:absolute;top:0;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:center;">
            <div><i class="fas fa-info-circle"></i> No data found</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-sm-12 mb-3">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">District-wise Students (Top 20)</h6>
        <div class="small text-muted">Total current active students</div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="position: relative; height:320px;">
          <canvas id="geoDistrictBar"></canvas>
          <div id="geoDistrictEmpty" class="text-center text-muted" style="position:absolute;top:0;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:center;">
            <div><i class="fas fa-info-circle"></i> No data found</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var provChart, distChart;
  function fetchGeo(){
    var base = "<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/dashboard/geo_distribution_api.php?get_geo_data=1&status=Following&conduct=accepted";
    var yr = "<?php echo isset($ggw_academic_year) && $ggw_academic_year !== '' ? ('&academic_year=' . rawurlencode($ggw_academic_year)) : ''; ?>";
    return fetch(base + yr).then(r=>r.json()).then(j=> j.status==='success'? j.data: {province:[],district:[]}).catch(()=>({province:[],district:[]}));
  }
  function renderBar(canvasId, emptyId, labels, totals){
    var ctx = document.getElementById(canvasId);
    var emptyEl = document.getElementById(emptyId);
    if (!ctx) return null; ctx = ctx.getContext('2d');
    var grand = totals.reduce((a,b)=>a+Number(b||0),0);
    if (!grand){ if (emptyEl) emptyEl.style.display='flex'; return null; } else if (emptyEl) emptyEl.style.display='none';
    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Students',
          data: totals,
          backgroundColor: 'rgba(54, 162, 235, 0.7)',
          borderColor: 'rgba(54, 162, 235, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        legend: { position: 'top' },
        scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] }
      }
    });
  }
  function init(){
    fetchGeo().then(function(data){
      var prov = Array.isArray(data.province)? data.province: [];
      var dist = Array.isArray(data.district)? data.district: [];
      // Sort by total desc
      prov.sort(function(a,b){ return (Number(b.total||0) - Number(a.total||0)); });
      dist.sort(function(a,b){ return (Number(b.total||0) - Number(a.total||0)); });
      var pLabels = prov.map(x=> x.province||'Unknown');
      var pTotals = prov.map(x=> Number(x.total||0));
      var dLabels = dist.map(x=> x.district||'Unknown');
      var dTotals = dist.map(x=> Number(x.total||0));
      if (provChart) provChart.destroy();
      if (distChart) distChart.destroy();
      function ensure(){ if (window.Chart){
        provChart = renderBar('geoProvinceBar','geoProvinceEmpty',pLabels,pTotals);
        distChart = renderBar('geoDistrictBar','geoDistrictEmpty',dLabels,dTotals);
      } else { setTimeout(ensure, 50); } }
      ensure();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
