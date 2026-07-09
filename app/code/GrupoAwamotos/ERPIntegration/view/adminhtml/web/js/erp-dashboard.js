/**
 * ERP Dashboard — RequireJS Module
 *
 * Handles: sync actions, chart rendering, notifications,
 * countdown timers, guide panel toggle, and auto-refresh.
 */
define([
    'jquery',
    'apexcharts'
], function ($, ApexCharts) {
    'use strict';

    /**
     * @param {Object} config
     * @param {string} config.syncUrls       — JSON-decoded sync URL map
     * @param {Array}  config.dailySalesData  — daily sales chart data
     * @param {Array}  config.rfmData         — RFM segment chart data
     * @param {number} config.autoRefreshMs   — auto-refresh interval (0 = disabled)
     * @param {HTMLElement} element            — dashboard root element
     */
    return function (config, element) {
        var $dashboard = $(element);
        let syncUrls  = config.syncUrls || {};
        let dailySalesData = config.dailySalesData || [];
        let rfmData = config.rfmData || [];
        let AUTO_REFRESH_DEFAULT_MS = 300000;
        let RELOAD_DELAY_MS = 1500;
        let SLIDE_TOGGLE_SPEED = 300;
        let COUNTDOWN_RELOAD_DELAY_MS = 1000;
        let COUNTDOWN_SHOW_THRESHOLD_SEC = 60;

        let autoRefreshMs = config.autoRefreshMs || AUTO_REFRESH_DEFAULT_MS;

        // ──────────────────────────────────────────────
        // Quick Guide Panel
        // ──────────────────────────────────────────────
        $dashboard.on('click', '#erp-guide-toggle', function () {
            var $panel = $dashboard.find('#erp-guide-panel');
            $panel.slideToggle(SLIDE_TOGGLE_SPEED);
            $(this).toggleClass('active');
        });
        $dashboard.on('click', '#erp-guide-close', function () {
            $dashboard.find('#erp-guide-panel').slideUp(SLIDE_TOGGLE_SPEED);
            $dashboard.find('#erp-guide-toggle').removeClass('active');
        });

        // ──────────────────────────────────────────────
        // Integration Actions (test, reset, refresh)
        // ──────────────────────────────────────────────
        $dashboard.on('click', '#erp-refresh-status', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).addClass('loading');

            $.ajax({
                url: syncUrls.status,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    }
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        });

        $dashboard.on('click', '.erp-action-btn', function () {
            var $btn = $(this);
            let action = $btn.data('action');
            let url = syncUrls[action];
            if (!url) {
                return; }

            $btn.prop('disabled', true).addClass('loading');
            showNotification('info', 'Executando...');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showNotification('success', response.message || 'Operação concluída');
                        setTimeout(function () {
                            location.reload(); }, RELOAD_DELAY_MS);
                    } else {
                        showNotification('error', response.message || 'Erro na operação');
                    }
                },
                error: function () {
                    showNotification('error', 'Erro de comunicação com o servidor');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('loading');
                }
            });
        });

        // ──────────────────────────────────────────────
        // Sync Buttons
        // ──────────────────────────────────────────────
        $dashboard.on('click', '.erp-sync-btn', function () {
            var $btn = $(this);
            let syncType = $btn.data('sync-type');
            let url = syncUrls[syncType];

            if (!url) {
                showNotification('error', 'URL de sincronização não configurada');
                return;
            }

            $btn.prop('disabled', true).addClass('loading').text('Sincronizando...');
            showNotification('info', 'Iniciando sincronização de ' + syncType + '...');

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        let msg = 'Sincronização concluída';
                        if (response.processed) {
                            msg += ': ' + response.processed + ' registros'; }
                        if (response.created) {
                            msg += ' (' + response.created + ' novos)'; }
                        if (response.updated) {
                            msg += ' (' + response.updated + ' atualizados)'; }
                        showNotification('success', msg);
                        setTimeout(function () {
                            location.reload(); }, 2000);
                    } else {
                        showNotification('error', response.message || 'Erro na sincronização');
                    }
                },
                error: function (xhr) {
                    let msg = 'Erro de comunicação';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    showNotification('error', msg);
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('loading').text('Sincronizar Agora');
                }
            });
        });

        // ──────────────────────────────────────────────
        // Circuit Breaker Countdown
        // ──────────────────────────────────────────────
        $dashboard.find('.erp-countdown').each(function () {
            var $el = $(this);
            let seconds = parseInt($el.data('seconds'), 10);
            var $value = $el.find('.countdown-value');

            let interval = setInterval(function () {
                seconds--;
                if (seconds <= 0) {
                    clearInterval(interval);
                    $value.text('Pronto');
                    setTimeout(function () {
                        location.reload(); }, COUNTDOWN_RELOAD_DELAY_MS);
                } else {
                    $value.text(seconds + 's');
                }
            }, 1000);
        });

        // ──────────────────────────────────────────────
        // Auto-Refresh
        // ──────────────────────────────────────────────
        if (autoRefreshMs > 0) {
            var $refreshTimer = $('<span class="erp-auto-refresh-timer"></span>');
            $dashboard.find('.erp-header-actions').append($refreshTimer);

            let remainingSec = Math.floor(autoRefreshMs / 1000);

            setInterval(function () {
                remainingSec--;
                if (remainingSec <= 0) {
                    location.reload();
                } else if (remainingSec <= COUNTDOWN_SHOW_THRESHOLD_SEC) {
                    let min = Math.floor(remainingSec / 60);
                    let sec = remainingSec % 60;
                    $refreshTimer.text(
                        'Atualiza em ' + (min > 0 ? min + 'm ' : '') + sec + 's'
                    );
                    $refreshTimer.show();
                } else {
                    $refreshTimer.hide();
                }
            }, 1000);
        }

        // ──────────────────────────────────────────────
        // Notification System
        // ──────────────────────────────────────────────
        function showNotification(type, message)
        {
            var $container = $dashboard.find('.erp-notifications');
            if (!$container.length) {
                $container = $('<div class="erp-notifications"></div>');
                $dashboard.prepend($container);
            }

            let iconMap = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            var $n = $(
                '<div class="erp-notification erp-notification-' + type + '">' +
                    '<span class="erp-notification-icon">' + (iconMap[type] || '') + '</span>' +
                    '<span class="erp-notification-text">' + $('<span>').text(message).html() + '</span>' +
                    '<button type="button" class="erp-notification-close">&times;</button>' +
                '</div>'
            );

            $container.append($n);

            $n.find('.erp-notification-close').on('click', function () {
                $n.fadeOut(300, function () {
                    $(this).remove(); });
            });

            if (type !== 'error') {
                setTimeout(function () {
                    $n.fadeOut(300, function () {
                        $(this).remove(); });
                }, 5000);
            }
        }

        // ──────────────────────────────────────────────
        // Sales Chart (ApexCharts)
        // ──────────────────────────────────────────────
        if (dailySalesData.length > 0) {
            let actualData = [];
            let projectionData = [];
            let categories = [];

            dailySalesData.forEach(function (item) {
                categories.push(item.date);
                if (item.type === 'actual') {
                    actualData.push(item.value);
                    projectionData.push(null);
                } else {
                    actualData.push(null);
                    projectionData.push(item.value);
                }
            });

            let salesChart = new ApexCharts(
                document.querySelector('#sales-chart'),
                {
                    chart: {
                        type: 'area',
                        height: 350,
                        animations: { enabled: true, easing: 'easeinout', speed: 800 },
                        toolbar: { show: true, tools: { download: true, zoom: true, pan: true } },
                        zoom: { enabled: true }
                    },
                    series: [
                        { name: 'Vendas Realizadas', data: actualData },
                        { name: 'Projeção', data: projectionData }
                    ],
                    xaxis: {
                        categories: categories,
                        type: 'datetime',
                        labels: { format: 'dd/MM' }
                    },
                    yaxis: {
                        labels: {
                            formatter: function (val) {
                                if (val >= 1000000) {
                                    return 'R$ ' + (val / 1000000).toFixed(1) + 'M'; }
                                if (val >= 1000) {
                                    return 'R$ ' + (val / 1000).toFixed(0) + 'K'; }
                                return 'R$ ' + val.toFixed(0);
                            }
                        }
                    },
                    stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] },
                    fill: {
                        type: 'gradient',
                        gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 }
                    },
                    colors: ['#00E396', '#FEB019'],
                    tooltip: {
                        y: {
                            formatter: function (val) {
                                return val
                                    ? 'R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits : 2 })
                                    : '-';
                            }
                        }
                    },
                    annotations: {
                        xaxis: [{
                            x: new Date().getTime(),
                            borderColor: '#775DD0',
                            label: {
                                text: 'Hoje',
                                style: { color: '#fff', background: '#775DD0' }
                            }
                        }]
                    }
                }
            );
            salesChart.render();
        }

        // ──────────────────────────────────────────────
        // RFM Donut Chart (ApexCharts)
        // ──────────────────────────────────────────────
        if (rfmData.length > 0) {
            let rfmChart = new ApexCharts(
                document.querySelector('#rfm-chart'),
                {
                    chart: { type: 'donut', height: 350 },
                    series: rfmData.map(function (s) {
                        return s.count; }),
                labels: rfmData.map(function (s) {
                    return s.segment; }),
                colors: rfmData.map(function (s) {
                    return s.color; }),
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '60%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total Clientes',
                                    formatter: function (w) {
                                        return w.globals.seriesTotals.reduce(function (a, b) {
                                            return a + b; }, 0);
                                    }
                                }
                            }
                        }
                    }
                    },
                    tooltip: {
                        y: {
                            formatter: function (val, opts) {
                                let revenue = rfmData[opts.seriesIndex].revenue;
                                return val + ' clientes (R$ ' + revenue.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ')';
                            }
                        }
                    },
                    responsive: [{
                        breakpoint: 480,
                        options: {
                            chart: { width: 300 },
                            legend: { position: 'bottom' }
                        }
                    }]
                }
            );
            rfmChart.render();
        }
    };
});
