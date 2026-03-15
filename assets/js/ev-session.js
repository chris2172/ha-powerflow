/**
 * HA Powerflow – EV Session Summary  (ev-session.js)
 */
(function ($) {
    'use strict';

    var charts = {};

    // ── Elapsed timer for active session ───────────────────────────────────
    function updateElapsed() {
        var $el = $('#evcs-live-elapsed');
        if (!$el.length) return;
        var start = parseInt($el.data('start'), 10);
        if (!start) return;
        var secs = Math.floor(Date.now() / 1000) - start;
        var h = Math.floor(secs / 3600);
        var m = Math.floor((secs % 3600) / 60);
        var s = secs % 60;
        var str = h > 0
            ? h + 'h ' + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's'
            : m > 0
            ? m + 'm ' + String(s).padStart(2,'0') + 's'
            : s + 's';
        $el.text(str);
    }
    setInterval(updateElapsed, 1000);
    updateElapsed();

    // ── Collapse / expand history cards ────────────────────────────────────
    $(document).on('click keypress', '.evcs-card--history .evcs-card-header', function (e) {
        if (e.type === 'keypress' && e.which !== 13) return;
        var $card = $(this).closest('.evcs-card--history');
        var collapsed = $card.attr('data-collapsed') === 'true';
        $card.attr('data-collapsed', collapsed ? 'false' : 'true');
        // Initialise chart when first expanded
        if (collapsed) {
            var $canvas = $card.find('.evcs-chart');
            if ($canvas.length && !$canvas.data('chart-init')) {
                initChart($canvas[0]);
                $canvas.data('chart-init', true);
            }
        }
    });

    // ── Build Chart.js chart from canvas data attribute ────────────────────
    function initChart(canvas) {
        var $c      = $(canvas);
        var raw     = $c.attr('data-points');
        var currency= $c.attr('data-currency') || '£';
        var rate    = parseFloat($c.attr('data-rate') || 0);
        var milesPerKwh = parseFloat($c.attr('data-miles-per-kwh') || 3.5);
        var isActive= $c.attr('data-active') === 'true';
        var id      = canvas.id;

        if (!raw) return;
        var pts;
        try { pts = JSON.parse(raw); } catch(e) { return; }
        if (!pts || !pts.length) return;

        var labels  = pts.map(function(p) {
            var d = new Date(p.ts * 1000);
            return d.getHours().toString().padStart(2,'0') + ':' +
                   d.getMinutes().toString().padStart(2,'0');
        });

        var kwhData   = pts.map(function(p) { return p.kwh; });
        var powerData = pts.map(function(p) { return p.power > 0 ? (p.power / 1000) : null; });

        var accentColor = isActive ? '#22c55e' : '#349bef';
        var accentFade  = isActive ? 'rgba(34,197,94,' : 'rgba(52,155,239,';

        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }

        charts[id] = new Chart(canvas, {
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Energy Added (kWh)',
                        data: kwhData,
                        borderColor: accentColor,
                        backgroundColor: function(ctx) {
                            var chart = ctx.chart;
                            var width = chart.chartArea ? chart.chartArea.width : 0;
                            var height = chart.chartArea ? chart.chartArea.height : 0;
                            if (!width || !height) return accentFade + '0)';
                            var gradient = chart.ctx.createLinearGradient(0, 0, 0, height);
                            gradient.addColorStop(0,   accentFade + '0.25)');
                            gradient.addColorStop(1,   accentFade + '0.0)');
                            return gradient;
                        },
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: pts.length > 60 ? 0 : 3,
                        pointHoverRadius: 5,
                        yAxisID: 'y',
                        order: 1,
                    },
                    {
                        type: 'bar',
                        label: 'Charge Power (kW)',
                        data: powerData,
                        backgroundColor: 'rgba(240,165,0,0.18)',
                        borderColor: 'rgba(240,165,0,0.55)',
                        borderWidth: 1,
                        borderRadius: 2,
                        yAxisID: 'y2',
                        order: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: isActive ? 0 : 500 },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: '#8899bb',
                            font: { family: "'Exo 2', sans-serif", size: 11 },
                            boxWidth: 14,
                            padding: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1a2233',
                        borderColor: 'rgba(52,155,239,0.3)',
                        borderWidth: 1,
                        titleColor: '#e2e8f0',
                        bodyColor: '#8899bb',
                        titleFont: { family: "'Exo 2', sans-serif", weight: '700', size: 12 },
                        bodyFont:  { family: "'Exo 2', sans-serif", size: 11 },
                        padding: 12,
                        callbacks: {
                            afterBody: function(items) {
                                var kwhItem = items.find(function(i) { return i.datasetIndex === 0; });
                                if (!kwhItem) return '';
                                var kwh = parseFloat(kwhItem.raw) || 0;
                                var lines = [];
                                if (rate > 0) lines.push('Cost so far: ' + currency + (kwh * rate).toFixed(2));
                                if (milesPerKwh > 0 && kwh > 0) lines.push('Range added: ~' + Math.round(kwh * milesPerKwh) + ' miles');
                                return lines;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Exo 2', sans-serif", size: 10 },
                            maxTicksLimit: 10,
                            maxRotation: 0,
                        },
                        grid: { color: 'rgba(255,255,255,0.04)' }
                    },
                    y: {
                        position: 'left',
                        title: {
                            display: true,
                            text: 'kWh',
                            color: '#64748b',
                            font: { family: "'Exo 2', sans-serif", size: 10 }
                        },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Exo 2', sans-serif", size: 10 },
                            callback: function(v) { return v.toFixed(1); }
                        },
                        grid: { color: 'rgba(255,255,255,0.04)' }
                    },
                    y2: {
                        position: 'right',
                        title: {
                            display: true,
                            text: 'kW',
                            color: '#64748b',
                            font: { family: "'Exo 2', sans-serif", size: 10 }
                        },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Exo 2', sans-serif", size: 10 },
                            callback: function(v) { return v.toFixed(1); }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // ── Initialise all visible charts on page load ──────────────────────────
    $(document).ready(function () {
        // Featured card chart always visible
        $('#evcs-wrap .evcs-card--featured .evcs-chart').each(function () {
            initChart(this);
        });
        // History cards only init when expanded (see collapse handler above)
    });

})(jQuery);
