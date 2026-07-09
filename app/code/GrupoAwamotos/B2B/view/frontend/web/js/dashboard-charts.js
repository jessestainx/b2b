define([
    'jquery',
    'awaChartjs',
], function ($, Chart) {
    'use strict';

    return function (config, element) {
        let chartId = config.chartId || 'b2b-purchase-chart';
        var $canvas = $('#' + chartId);

        if (!$canvas.length) {
            return;
        }

        let rawData = $canvas.data('chart-data');
        if (!rawData || !Array.isArray(rawData)) {
            return;
        }

        let labels = rawData.map(function (item) {
            return item.label; });
        let values = rawData.map(function (item) {
            return item.value; });

        let ctx = $canvas[0].getContext('2d');

        // Gradient for a premium look
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(183, 51, 55, 0.4)');
        gradient.addColorStop(1, 'rgba(183, 51, 55, 0.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Compras (R$)',
                    data: values,
                    borderColor: '#A33B3B',
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#A33B3B'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#334155',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                family: 'Inter, sans-serif',
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(226, 232, 240, 0.5)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                family: 'Inter, sans-serif',
                                size: 11
                            },
                            callback: function (value) {
                                if (value >= 1000) {
                                    return 'R$ ' + (value / 1000) + 'k';
                                }
                                return 'R$ ' + value;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                }
            }
        });
    };
});
