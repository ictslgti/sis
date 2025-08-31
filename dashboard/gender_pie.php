<!-- BLOCK#1 START DON'T CHANGE THE ORDER-->
<?php
$title = "Gender Pie | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!--END DON'T CHANGE THE ORDER-->

<!--BLOCK#2 START YOUR CODE HERE -->
<div class="row mt-3">
    <div class="col-md-6 col-sm-12 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Overall Gender (All Departments)</h5>
                <div class="small text-muted">Male vs Female totals</div>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:320px;">
                    <canvas id="genderPieChart"></canvas>
                    <div id="genderPieEmpty" class="text-center text-muted" style="position:absolute;top:0;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:center;">
                        <div>
                            <i class="fas fa-info-circle"></i> No data found
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted">
                <small>Data source: Following students with Conduct accepted</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-sm-12 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Department-wise charts</h5>
                <div class="small text-muted">All departments overview</div>
            </div>
            <div class="card-body">
                <div id="deptCharts" class="row"></div>
            </div>
        </div>
    </div>
</div>

<script>
    let genderPieChart;
    let genderDeptData = [];
    function isAdminDepartment(name){
        var n = String(name||'').trim().toLowerCase();
        return n === 'admin' || n === 'administration' || /(^|\s)admin(istration)?(\s|$)/.test(n);
    }

    function fetchGenderData() {
        // Absolute app-root path avoids base href issues
        return fetch("<?php echo (defined('APP_BASE') ? APP_BASE : ''); ?>/dashboard/gender_distribution_api.php?get_gender_data=1&status=Following&conduct=accepted")
            .then(r => r.json())
            .then(j => j.status === 'success' ? j.data : [])
            .catch(() => []);
    }

    function computeCounts(selection) {
        let male = 0,
            female = 0;
        if (selection === 'ALL') {
            genderDeptData.forEach(it => {
                if (isAdminDepartment(it.department)) return; // exclude admin dept
                male += (it.male || 0);
                female += (it.female || 0);
            });
        } else {
            const sel = String(selection || '').trim().toLowerCase();
            const row = genderDeptData.find(it => String(it.department || '').trim().toLowerCase() === sel);
            if (row) {
                male = row.male || 0;
                female = row.female || 0;
            }
        }
        return {
            male,
            female
        };
    }

    function renderPie(selection) {
        const ctx = document.getElementById('genderPieChart').getContext('2d');
        const {
            male,
            female
        } = computeCounts(selection);
        const emptyEl = document.getElementById('genderPieEmpty');

        if (genderPieChart) genderPieChart.destroy();

        const total = (Number(male) || 0) + (Number(female) || 0);
        if (!total) {
            emptyEl.style.display = 'flex';
            return;
        } else {
            emptyEl.style.display = 'none';
        }
        genderPieChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [male, female],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'top'
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var ds = data.datasets[tooltipItem.datasetIndex];
                            var label = data.labels[tooltipItem.index] || '';
                            var val = ds.data[tooltipItem.index] || 0;
                            return label + ': ' + val;
                        },
                        footer: function(tooltipItems, data) {
                            try {
                                var ds = data.datasets[tooltipItems[0].datasetIndex];
                                var sum = ds.data.reduce(function(a, b) {
                                    return Number(a || 0) + Number(b || 0);
                                }, 0);
                                return 'Total: ' + sum;
                            } catch (e) {
                                return '';
                            }
                        }
                    }
                },
                title: {
                    display: false
                }
            }
        });
    }

    function initGenderPie() {
        fetchGenderData().then(data => {
            genderDeptData = Array.isArray(data) ? data : [];
            // Debug: log loaded data size
            try {
                console.debug('GenderPie: loaded rows', genderDeptData.length);
            } catch (e) {}
            // Render department mini-charts (no table)
            renderDeptCharts();
            // Ensure Chart library is available before rendering
            (function ensureChartReady() {
                if (window.Chart) {
                    renderPie('ALL');
                } else {
                    setTimeout(ensureChartReady, 50);
                }
            })();
        }).catch(() => {
            genderDeptData = [];
            try {
                console.error('GenderPie: failed to load data');
            } catch (e) {}
            renderPie('ALL');
        });

        // No dropdown; always showing ALL in left chart.
    }

    // Table removed per requirement; only rendering department mini-charts.

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                '\'': '&#39;'
            } [c]);
        });
    }

    // Render department-wise mini doughnut charts
    function renderDeptCharts(){
        var container = document.getElementById('deptCharts');
        if(!container) return;
        container.innerHTML = '';
        genderDeptData.forEach(function(it){
            if (isAdminDepartment(it.department)) return; // skip admin dept card
            var col = document.createElement('div');
            col.className = 'col-sm-6 col-md-4 col-lg-3 mb-3';
            var card = document.createElement('div');
            card.className = 'border rounded p-2 h-100';
            var title = document.createElement('div');
            title.className = 'small text-truncate font-weight-bold mb-2';
            title.title = it.department||'';
            title.textContent = it.department||'';
            var canvas = document.createElement('canvas');
            canvas.height = 140;
            canvas.style.maxHeight = '140px';
            var footer = document.createElement('div');
            footer.className = 'small text-muted mt-2 text-right';
            var m = Number(it.male||0), f = Number(it.female||0), t = Number(it.total||(m+f));
            footer.textContent = 'M:'+m+' F:'+f+' T:'+t;
            card.appendChild(title);
            card.appendChild(canvas);
            card.appendChild(footer);
            col.appendChild(card);
            container.appendChild(col);
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Male','Female'],
                    datasets: [{
                        data: [m, f],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 99, 132, 0.8)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: { enabled: true },
                    cutoutPercentage: 60
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initGenderPie);
</script>

<!--BLOCK#3 START DON'T CHANGE THE ORDER-->
<?php include_once("../footer.php"); ?>
<!--END DON'T CHANGE THE ORDER-->