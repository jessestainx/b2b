/**
 * Fallback seguro para ambientes onde apenas *.min.js é publicado.
 * Reexecuta módulos com sufixo .min após scripterror/timeout.
 */
define([], function () {
    'use strict';

    var w = window;
    var rjs = w.requirejs;

    if (!rjs || typeof rjs.onError !== 'function') {
        return {};
    }

    if (rjs.__awaMinFallbackInstalled) {
        return {};
    }
    rjs.__awaMinFallbackInstalled = true;

    var retried = Object.create(null);
    var originalOnError = rjs.onError;

    function canRetry(moduleName) {
        if (!moduleName || typeof moduleName !== 'string') {
            return false;
        }
        if (moduleName.indexOf('!') !== -1) {
            return false;
        }
        if (/\.min$/.test(moduleName)) {
            return false;
        }
        if (/^js\/awa-/.test(moduleName)) {
            return false;
        }

        return true;
    }

    function toMinModule(moduleName) {
        return moduleName + '.min';
    }

    rjs.onError = function (error) {
        var requireType = error && error.requireType ? String(error.requireType) : '';
        var modules = error && Array.isArray(error.requireModules) ? error.requireModules : [];
        var recoverable = requireType === 'scripterror' || requireType === 'timeout';

        if (recoverable && modules.length) {
            var retryList = [];
            var patchPaths = {};

            modules.forEach(function (moduleName) {
                if (!canRetry(moduleName) || retried[moduleName]) {
                    return;
                }

                retried[moduleName] = true;
                patchPaths[moduleName] = toMinModule(moduleName);
                retryList.push(moduleName);
            });

            if (retryList.length) {
                rjs.config({
                    paths: patchPaths
                });

                try {
                    w.require(retryList, function () {}, function () {});
                    return;
                } catch (retryError) {
                    // Se o fallback também falhar, deixa o fluxo padrão tratar.
                }
            }
        }

        if (typeof originalOnError === 'function') {
            return originalOnError.apply(this, arguments);
        }
    };

    return {};
});
