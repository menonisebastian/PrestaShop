{*
* SYSPROVIDER Newsletter — Admin Subscribers Stats
* @author SYSPROVIDER S.L.
*}

<div class="syspnl-admin-wrap">

    {* ── Cabecera ── *}
    <div class="syspnl-admin-header">
        <div class="syspnl-admin-header-left">
            <span class="syspnl-admin-icon">✉</span>
            <div>
                <h2 class="syspnl-admin-title">Suscriptores Newsletter</h2>
                <p class="syspnl-admin-subtitle">Gestión y estadísticas de suscriptores al popup de newsletter</p>
            </div>
        </div>
        <a href="{$syspnl_module_url}" class="syspnl-btn-config">
            ⚙ Configurar módulo
        </a>
    </div>

    {* ── KPI Cards ── *}
    <div class="syspnl-kpi-grid">

        <div class="syspnl-kpi-card syspnl-kpi-total">
            <div class="syspnl-kpi-icon">👥</div>
            <div class="syspnl-kpi-body">
                <span class="syspnl-kpi-number">{$syspnl_total}</span>
                <span class="syspnl-kpi-label">Total suscriptores</span>
            </div>
        </div>

        <div class="syspnl-kpi-card syspnl-kpi-today">
            <div class="syspnl-kpi-icon">📅</div>
            <div class="syspnl-kpi-body">
                <span class="syspnl-kpi-number">{$syspnl_today}</span>
                <span class="syspnl-kpi-label">Hoy</span>
            </div>
        </div>

        <div class="syspnl-kpi-card syspnl-kpi-month">
            <div class="syspnl-kpi-icon">📈</div>
            <div class="syspnl-kpi-body">
                <span class="syspnl-kpi-number">{$syspnl_month}</span>
                <span class="syspnl-kpi-label">Este mes</span>
            </div>
        </div>

        <div class="syspnl-kpi-card syspnl-kpi-coupon">
            <div class="syspnl-kpi-icon">🎁</div>
            <div class="syspnl-kpi-body">
                <span class="syspnl-kpi-number">{$syspnl_with_coupon}</span>
                <span class="syspnl-kpi-label">Con cupón generado</span>
            </div>
        </div>

    </div>

    {* ── Gráfico últimos 30 días ── *}
    {if $syspnl_chart_labels != '[]'}
        <div class="syspnl-chart-panel">
            <div class="syspnl-chart-header">
                <h3 class="syspnl-chart-title">📊 Suscripciones — últimos 30 días</h3>
            </div>
            <div class="syspnl-chart-body">
                <canvas id="syspnlChart" height="90"></canvas>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var labels = {$syspnl_chart_labels};
                var data   = {$syspnl_chart_data};

                // Formato legible de fechas
                var formattedLabels = labels.map(function(d) {
                    var parts = d.split('-');
                    return parts[2] + '/' + parts[1];
                });

                var ctx = document.getElementById('syspnlChart').getContext('2d');

                var gradient = ctx.createLinearGradient(0, 0, 0, 200);
                gradient.addColorStop(0, 'rgba(232, 146, 124, 0.4)');
                gradient.addColorStop(1, 'rgba(232, 146, 124, 0.02)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: formattedLabels,
                        datasets: [{
                            label: 'Suscriptores',
                            data: data,
                            borderColor: '#e8927c',
                            backgroundColor: gradient,
                            borderWidth: 2.5,
                            pointBackgroundColor: '#e8927c',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true,
                            tension: 0.4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#3d2c27',
                                titleColor: '#f0b429',
                                bodyColor: '#d4a898',
                                padding: 10,
                                cornerRadius: 6,
                                displayColors: false,
                                callbacks: {
                                    title: function(items) { return items[0].label; },
                                    label: function(item) {
                                        return item.raw + (item.raw === 1 ? ' suscriptor' :
                                            ' suscriptores');
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    color: '#9c8278',
                                    font: { size: 11 },
                                    maxTicksLimit: 10,
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.06)' },
                                ticks: {
                                    color: '#9c8278',
                                    font: { size: 11 },
                                    stepSize: 1,
                                    precision: 0,
                                }
                            }
                        }
                    }
                });
            });
        </script>
    {/if}

</div>

{* Chart.js desde CDN (solo si no está ya cargado) *}
<script>
    if (typeof Chart === 'undefined') {
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
        document.head.appendChild(s);
    }
</script>