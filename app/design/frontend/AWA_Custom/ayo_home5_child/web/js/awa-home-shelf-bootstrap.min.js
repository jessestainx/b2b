/**
 * Home shelf bootstrap r47c:
 * - NÃO carrega carrossel no first paint (IO falso-positivo em .top-home-content)
 * - Carrega em: scroll do usuário, intent, ou fallback 8s após load
 * - Mantém LCP do hero livre do longtask ~2.3s do awa-scroll-carousel
 */
(function (window, document) {
    'use strict';

    var configNode = document.getElementById('awa-home-shelf-bootstrap-config');
    var parsedConfig = null;
    var booted = false;
    var scheduled = false;
    var intentEvents = ['pointerdown', 'keydown', 'touchstart'];

    if (configNode && configNode.textContent) {
        try {
            parsedConfig = JSON.parse(configNode.textContent);
        } catch (error) {
            parsedConfig = null;
        }
    }

    function dbg(msg, data) {
}

    function cleanupLegacyHeaderNavState() {
        document.querySelectorAll('.awa-owl-nav--header-slot').forEach(function (slot) {
            slot.remove();
        });
        document.querySelectorAll('.awa-carousel-nav-host').forEach(function (header) {
            header.classList.remove('awa-carousel-nav-host', 'has-carousel-autoplay-toggle', 'is-awa-not-scrollable');
        });
    }

    function dispatchBootstrapReady() {
        if (window.__awaBootstrapReady) {
            return;
        }
        window.__awaBootstrapReady = true;
        document.dispatchEvent(new CustomEvent('awa-bootstrap-ready'));
    }

    function decodeAttrUrl(value) {
        if (!value) {
            return '';
        }
        if (value.indexOf('&') === -1) {
            return value;
        }
        var textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value || value;
    }

    function getScriptSrc() {
        if (parsedConfig && typeof parsedConfig.jsSrc === 'string' && parsedConfig.jsSrc) {
            return parsedConfig.jsSrc;
        }
        var bootScript = document.currentScript
            || document.querySelector('script[data-awa-shelf-loader="1"][data-awa-shelf-js]')
            || document.querySelector('script[data-awa-shelf-js]');
        if (bootScript) {
            var bootSrc = decodeAttrUrl(bootScript.getAttribute('data-awa-shelf-js') || '');
            if (bootSrc) {
                return bootSrc;
            }
        }
        var link = document.querySelector('link[data-awa-shelf-js]');
        return link ? decodeAttrUrl(link.getAttribute('data-awa-shelf-js') || '') : '';
    }

    function appendShelfScript() {
        if (document.querySelector('script[data-awa-shelf-carousel-js="1"]')) {
            dispatchBootstrapReady();
            return true;
        }
        var src = getScriptSrc();
        if (!src) {
            return false;
        }
        var script = document.createElement('script');
        script.src = src;
        script.defer = true;
        script.setAttribute('data-awa-shelf-carousel-js', '1');
        script.onload = function () {
            dispatchBootstrapReady();
            var tries = 0;
            (function pollNavMount() {
                if (typeof window.__awaMountShelfNavInHeaders === 'function') {
                    window.__awaMountShelfNavInHeaders();
                    return;
                }
                tries += 1;
                if (tries < 40) {
                    window.setTimeout(pollNavMount, 100);
                }
            }());
        };
        script.onerror = dispatchBootstrapReady;
        (document.body || document.documentElement).appendChild(script);
        dispatchBootstrapReady();
        return true;
    }

    function runHomeBootstrapDefer() {
        if (typeof window.__awaHomeBootstrapBoot === 'function') {
            window.__awaHomeBootstrapBoot(true);
        }
    }

    function isMeaningfulIntent(event) {
        if (!event) {
            return false;
        }
        if (event.type === 'keydown') {
            return event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar';
        }
        return !!(event.target && event.target.closest && event.target.closest(
            'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], .minicart-wrapper, .awa-header-account-prompt, #search_mini_form, .awa-hero-swiper__nav, .swiper-pagination-bullet, .awa-category-carousel__item, .awa-owl-nav__btn, .awa-carousel__viewport, .awa-shelf--carousel, .product-item, .item-product'
        ));
    }

    function boot(reason) {
        if (booted) {
            return;
        }
        var loaded = appendShelfScript();
        if (!loaded) {
            if (reason === 'intent') {
                runHomeBootstrapDefer();
            }
            dbg('shelf-boot-miss', { reason: reason });
            return;
        }
        booted = true;
        dbg('shelf-boot', { reason: reason, y: Math.round(window.pageYOffset || 0) });
        if (reason === 'intent') {
            runHomeBootstrapDefer();
        }
    }

    function scheduleScrollBoot() {
        if (booted || scheduled) {
            return;
        }
        scheduled = true;
        dbg('shelf-schedule-scroll', {});

        function onScroll() {
            var y = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (y < 140) {
                return;
            }
            window.removeEventListener('scroll', onScroll, true);
            boot('scroll');
        }

        window.addEventListener('scroll', onScroll, { passive: true, capture: true });

        function onLoad() {
            window.setTimeout(function () {
                if (!booted) {
                    boot('load-fallback');
                }
            }, 8000);
        }

        if (document.readyState === 'complete') {
            onLoad();
        } else {
            window.addEventListener('load', onLoad, { once: true });
        }
    }

    function onReady() {
        cleanupLegacyHeaderNavState();
        scheduleScrollBoot();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady, { once: true });
    } else {
        onReady();
    }

    intentEvents.forEach(function (eventName) {
        window.addEventListener(eventName, function (event) {
            if (!isMeaningfulIntent(event)) {
                return;
            }
            boot('intent');
        }, { passive: eventName !== 'keydown', capture: true, once: true });
    });
})(window, document);
