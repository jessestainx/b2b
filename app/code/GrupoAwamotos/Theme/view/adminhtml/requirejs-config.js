/**
 * SSOT admin — Chart.js e ApexCharts (loader AMD + vendor em view/base).
 */
var config = {
    paths: {
        awaChartjs: 'GrupoAwamotos_Theme/js/vendor/chart.umd.min',
        apexcharts: 'GrupoAwamotos_Theme/js/apexcharts-amd'
    },
    shim: {
        'GrupoAwamotos_Theme/js/vendor/chart.umd.min': {
            exports: 'Chart'
        }
    }
};
