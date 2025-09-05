<?php
// Province- and District-wise Student Count Charts Widget (Bar Charts)
// Expects $ggw_academic_year to be provided by parent if filtering needed
?>
<style>
  /* Geo widget responsive styling */
  .ggw .chart-container{ height: clamp(260px, 42vw, 380px); overflow-x: hidden; }
  @media (max-width: 576px){ .ggw .chart-container{ height: 320px; } }
  .ggw .card{ height: 100%; border-radius: 12px; }
  .ggw .card-body{ padding: 12px; }
  .ggw canvas{ min-width: 0; }
  
  /* Remove side space on mobile inside the geo widget */
  @media (max-width: 576px){
    .ggw{ margin-left: -10px; margin-right: -10px; }
    .ggw [class^="col-"], .ggw [class*=" col-"]{ padding-left: 0; padding-right: 0; }
    .ggw .card-header{ padding: 10px 12px; }
    .ggw .card-body{ padding: 8px 10px; }
  }
</style>
<div class="row mt-4 ggw">
  <div class="col-lg-6 col-md-12 mb-3 d-flex">
    <div class="card w-100 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Province-wise Students</h6>
        <div class="small text-muted">Total current active students</div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="position: relative;">
          <canvas id="geoProvinceBar"></canvas>
          <div id="geoProvinceEmpty" class="text-center text-muted" style="position:absolute;top:0;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:center;">
            <div><i class="fas fa-info-circle"></i> No data found</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6 col-md-12 mb-3 d-flex">
    <div class="card w-100 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">District-wise Students </h6>
        <div class="small text-muted">Total current active students</div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="position: relative;">
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
  function buildPalette(n){
    // Modern pastel-ish palette with sufficient contrast
    var base = [
      '#4F46E5','#06B6D4','#10B981','#F59E0B','#EF4444',
      '#8B5CF6','#14B8A6','#22C55E','#EAB308','#FB7185',
      '#0EA5E9','#6366F1','#84CC16','#F97316','#EC4899'
    ];
    var colors = []; var borders = [];
    for (var i=0;i<n;i++){
      var c = base[i % base.length];
      colors.push(hexToRgba(c, 0.7));
      borders.push(c);
    }
    return {bg: colors, bd: borders};
  }
  function hexToRgba(hex, a){
    var h = hex.replace('#','');
    var r = parseInt(h.substring(0,2),16);
    var g = parseInt(h.substring(2,4),16);
    var b = parseInt(h.substring(4,6),16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
  }
  function renderBar(canvasId, emptyId, labels, totals){
    var ctx = document.getElementById(canvasId);
    var emptyEl = document.getElementById(emptyId);
    if (!ctx) return null; ctx = ctx.getContext('2d');
    var grand = totals.reduce((a,b)=>a+Number(b||0),0);
    if (!grand){ if (emptyEl) emptyEl.style.display='flex'; return null; } else if (emptyEl) emptyEl.style.display='none';
    var isMobile = window.matchMedia && window.matchMedia('(max-width: 576px)').matches;
    var chartType = isMobile ? 'horizontalBar' : 'bar';
    var pal = buildPalette(labels.length);
    return new Chart(ctx, {
      type: chartType,
      data: {
        labels: labels,
        datasets: [{
          label: 'Students',
          data: totals,
          backgroundColor: pal.bg,
          borderColor: pal.bd,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        legend: { display: false },
        layout: { padding: { left: 0, right: 0, top: 6, bottom: 2 } },
        scales: (function(){
          if (chartType === 'horizontalBar') {
            return {
              xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.05)' } }],
              yAxes: [{ ticks: { autoSkip: false, callback: function(v){ var s=String(v||''); return s.length>14? s.slice(0,12)+'…': s; } }, gridLines: { display:false } }]
            };
          } else {
            return {
              yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.05)' } }],
              xAxes: [{ ticks: { autoSkip: true, maxRotation: 30, minRotation: 0, callback: function(v){ var s=String(v||''); return s.length>10? s.slice(0,9)+'…': s; } }, barPercentage: 0.8, categoryPercentage: 0.9, gridLines: { display:false } }]
            };
          }
        })(),
        tooltips: { mode: 'index', intersect: false }
      }
    });
  }
  function init(){
    fetchGeo().then(function(data){
      var prov = Array.isArray(data.province)? data.province: [];
      var dist = Array.isArray(data.district)? data.district: [];
      // Client-side filter to remove any Unknown labels if any slipped through
      prov = prov.filter(function(x){ return (x && x.province && String(x.province).toLowerCase() !== 'unknown'); });
      dist = dist.filter(function(x){ return (x && x.district && String(x.district).toLowerCase() !== 'unknown'); });
      // Sort by total desc
      prov.sort(function(a,b){ return (Number(b.total||0) - Number(a.total||0)); });
      dist.sort(function(a,b){ return (Number(b.total||0) - Number(a.total||0)); });
      var pLabels = prov.map(x=> x.province||'Unknown');
      var pTotals = prov.map(x=> Number(x.total||0));
      var dLabels = dist.map(x=> x.district||'Unknown');
      var dTotals = dist.map(x=> Number(x.total||0));
      
      // Adjust canvas/container height on mobile for better readability
      var isMobile = window.matchMedia && window.matchMedia('(max-width: 576px)').matches;
      if (isMobile) {
        var pCont = document.getElementById('geoProvinceBar')?.parentElement;
        var dCont = document.getElementById('geoDistrictBar')?.parentElement;
        var base = 80; var per = 26; // px per label
        if (pCont) { pCont.style.height = Math.max(260, base + per * pLabels.length) + 'px'; }
        if (dCont) { dCont.style.height = Math.max(260, base + per * dLabels.length) + 'px'; }
      }
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
