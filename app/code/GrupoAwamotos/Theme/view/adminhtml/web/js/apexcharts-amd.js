/**
 * Carrega ApexCharts sem conflito com RequireJS (UMD interno usa define() anônimo).
 * Binário: GrupoAwamotos_Theme/js/vendor/apexcharts.min.js
 */
define([], function () {
    'use strict';

    var scriptUrl = require.toUrl('GrupoAwamotos_Theme/js/vendor/apexcharts.min.js');
    var loadPromise = null;

    /**
     * @returns {Promise<function(new:Object, Object): Object>}
     */
    return function loadApexCharts()
    {
        if (window.ApexCharts) {
            return Promise.resolve(window.ApexCharts);
        }

        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = fetch(scriptUrl, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load ApexCharts from ' + scriptUrl);
                }

                return response.text();
            })
            .then(function (code) {
                var amdBackup = window.define;

                try {
                    window.define = undefined;
                    // eslint-disable-next-line no-eval
                    (0, eval)(code);
                } finally {
                    if (amdBackup) {
                        window.define = amdBackup;
                    }
                }

                if (!window.ApexCharts) {
                    throw new Error('ApexCharts is not available on window after load');
                }

                return window.ApexCharts;
            });

        return loadPromise;
    }();
});
