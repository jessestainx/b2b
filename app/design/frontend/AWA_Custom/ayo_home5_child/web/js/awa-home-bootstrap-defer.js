/**
 * Home PSI — adia require config + merged bundle até interação real do usuário.
 */
(function (w, d) {
    'use strict';

    var done = false;
    var userIntent = false;
    var delayedBootScheduled = false;
    var events = ['pointerdown', 'keydown', 'touchstart'];
    var MIN_PASSIVE_BOOT_MS = 5000;
    var disableAutoBoot = true;

    function cleanup() {
        events.forEach(function (eventName) {
            w.removeEventListener(eventName, onInteract, true);
        });
    }

    function isMeaningfulIntent(evt) {
        if (!evt) {
            return false;
        }

        if (evt.type === 'keydown') {
            return evt.key === 'Enter' || evt.key === ' ' || evt.key === 'Spacebar';
        }

        return !!(evt.target && evt.target.closest && evt.target.closest(
            'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], .minicart-wrapper, .awa-header-account-prompt, #search_mini_form, .awa-hero-swiper__nav, .swiper-pagination-bullet, .awa-category-carousel__item, .product-item, .item-product'
        ));
    }

    function bootNow() {
        userIntent = true;
        boot(true);
    }

    function runInline() {
        var inline = d.getElementById('awa-home-bootstrap-inline');

        if (!inline || !inline.textContent) {
            return;
        }

        if (d.getElementById('awa-home-bootstrap-inline-ran')) {
            return;
        }

        try {
            var script = d.createElement('script');

            script.id = 'awa-home-bootstrap-inline-ran';
            script.text = inline.textContent;
            (d.head || d.body || d.documentElement).appendChild(script);
        } catch (e) {
            return;
        }
    }

    function waitForRealRequire(callback, attempts) {
        var left = attempts || 80;

        if (typeof w.awaFlushRequireQueue === 'function' && w.awaFlushRequireQueue()) {
            callback();
            return;
        }

        if (left <= 0) {
            callback();
            return;
        }

        w.setTimeout(function () {
            waitForRealRequire(callback, left - 1);
        }, 50);
    }

    function prepareForMergedBundle() {
        try {
            delete w.define;
        } catch (e) {
            w.define = undefined;
        }
    }

    function dispatchCarouselRuntimeReady(source) {
        if (!w.__awaCarouselRuntimeReady) {
            w.__awaCarouselRuntimeReady = true;
            d.dispatchEvent(new CustomEvent('awa:carousel-runtime-ready'));
        }
        loadShelfCarouselScript('dispatchCarouselRuntimeReady:' + (source || 'unknown'));
        return true;
    }

    function shelfJsUrl() {
        var link = d.querySelector('link[data-awa-shelf-js]');

        if (link) {
            return link.getAttribute('data-awa-shelf-js') || '';
        }

        link = d.querySelector('link[href*="awa-scroll-carousel"]');

        if (link && link.href) {
            return link.href;
        }

        link = d.querySelector('link[href*="awa-shelf-carousel"]');

        if (!link || !link.href) {
            return '';
        }

        return link.href
            .replace(/awa-shelf-carousel\.min\.css/, 'awa-scroll-carousel.js')
            .replace(/awa-shelf-carousel\.css/, 'awa-scroll-carousel.js')
            .replace('/css/', '/js/');
    }

    function loadShelfCarouselScript(source) {
        if (d.querySelector('script[data-awa-shelf-carousel-js="1"]')) {
            return true;
        }

        var src = shelfJsUrl();

        if (!src) {
            return false;
        }

        var script = d.createElement('script');

        script.src = src;
        script.defer = true;
        script.setAttribute('data-awa-shelf-carousel-js', '1');
        (d.body || d.documentElement).appendChild(script);

        return true;
    }

    function ensureCarouselRuntime(source, onReady) {
        dispatchCarouselRuntimeReady(source);
        if (typeof onReady === 'function') {
            onReady();
        }
    }

    function appendMerged(onReady) {
        var store = d.getElementById('awa-home-bootstrap-merged');

        if (!store) {
            ensureCarouselRuntime('no-merged-store', onReady);
            return;
        }

        var src = store.getAttribute('data-src');

        if (!src || d.querySelector('script[data-awa-merged-bundle="1"]')) {
            ensureCarouselRuntime('merged-already-loaded', onReady);
            return;
        }

        var script = d.createElement('script');

        script.src = src;
        script.type = 'text/javascript';
        script.defer = true;
        script.setAttribute('data-awa-merged-bundle', '1');
        script.onload = function () {
            waitForRealRequire(function () {
                ensureCarouselRuntime('merged-onload', onReady);
            });
        };
        script.onerror = function () {
            if (typeof onReady === 'function') {
                onReady();
            }
        };
        prepareForMergedBundle();
        (d.body || d.documentElement).appendChild(script);
    }

    function syncHeroA11y() {
        var mobile = w.innerWidth < 768;
        d.querySelectorAll('[data-awa-hero-variant]').forEach(function (el) {
            var active = el.getAttribute('data-awa-hero-variant') === (mobile ? 'mobile' : 'desktop');
            el.setAttribute('aria-hidden', active ? 'false' : 'true');
            if (active) {
                el.removeAttribute('inert');
            } else {
                el.setAttribute('inert', '');
            }
        });
    }

    function syncHeroLcp() {
        var mobile = w.innerWidth < 768;
        var variant = mobile ? 'mobile' : 'desktop';
        var root = d.querySelector('.wrapper_slider[data-awa-hero-variant="' + variant + '"]');
        if (!root) {
            return;
        }
        var img = root.querySelector('.swiper-slide:first-child picture img, .swiper-slide:first-child img');
        if (!img) {
            return;
        }
        img.loading = 'eager';
        img.setAttribute('fetchpriority', 'high');
        img.decoding = 'async';
    }

    function syncHeroA11yAndLcp() {
        syncHeroA11y();
        syncHeroLcp();
    }

    function installHeroViewportSync() {
        var scheduled = false;

        if (d.__awaHeroA11yViewportSyncInstalled) {
            syncHeroA11yAndLcp();
            return;
        }

        d.__awaHeroA11yViewportSyncInstalled = true;
        d.addEventListener('resize', function () {
            if (scheduled) {
                return;
            }
            scheduled = true;
            w.setTimeout(function () {
                scheduled = false;
                syncHeroA11yAndLcp();
            }, 50);
        }, { passive: true });
    }

    function initHeroSliders() {
        function runHeroInit() {
            if (typeof w.require !== 'function' || w.require._awaStub) {
                return false;
            }

            var configs = d.querySelectorAll('script[type="application/json"][id^="awa-hero-slider-config-"]');

            if (!configs.length) {
                return false;
            }

            w.require(['awaHeroSlider'], function (initHero) {
                configs.forEach(function (node) {
                    try {
                        var payload = JSON.parse(node.textContent || '');

                        if (payload && payload.sliderId !== undefined) {
                            initHero(payload);
                        }
                    } catch (e) {
                        /* ignore malformed config */
                    }
                });
                installHeroViewportSync();
                syncHeroA11yAndLcp();
            }, function () {
                initHeroSliders._failed = true;
            });

            return true;
        }

        if (runHeroInit()) {
            return;
        }

        // Sem require-stub na home, waitForRealRequire degrada para timeout cego de 4s;
        // se o require.js real demorar mais, o hero ficava sem init permanente.
        // Retry com polling até o require real aparecer (cap 30s).
        var heroAttempts = 120;
        var heroTimer = w.setInterval(function () {
            heroAttempts -= 1;
            if (d.querySelector('.awa-hero-swiper-ready') || runHeroInit() || heroAttempts <= 0) {
                w.clearInterval(heroTimer);
            }
        }, 250);
    }

    d.addEventListener('awa:carousel-runtime-ready', function () {
        loadShelfCarouselScript('awa:carousel-runtime-ready');
        if (!d.querySelector('.awa-hero-swiper-ready')) {
            initHeroSliders();
        }
    });

    function initRokanTheme(onReady) {
        if (typeof w.require !== 'function' || w.require._awaStub) {
            if (typeof onReady === 'function') {
                onReady();
            }
            return;
        }

        w.require(['rokanthemes/theme'], function () {
            if (typeof onReady === 'function') {
                onReady();
            }
        }, function () {
            if (typeof onReady === 'function') {
                onReady();
            }
        });
    }

    function boot(force) {
        if (done) {
            return;
        }

        if (!force && !userIntent && window.performance && window.performance.now && window.performance.now() < MIN_PASSIVE_BOOT_MS) {
            if (!delayedBootScheduled) {
                delayedBootScheduled = true;
                window.setTimeout(function () {
                    delayedBootScheduled = false;
                    boot(false);
                }, MIN_PASSIVE_BOOT_MS - window.performance.now());
            }
            return;
        }

        done = true;
        w.__awaBootstrapReady = true;
        d.dispatchEvent(new CustomEvent('awa-bootstrap-ready'));
        cleanup();
        syncHeroA11yAndLcp();
        installHeroViewportSync();
        runInline();
        appendMerged(function () {
            initRokanTheme(initHeroSliders);
        });
    }

    w.__awaHomeBootstrapBoot = function (force) {
        boot(!!force);
    };

    function onInteract(evt) {
        if (!isMeaningfulIntent(evt)) {
            return;
        }

        bootNow();
    }

    events.forEach(function (eventName) {
        w.addEventListener(eventName, onInteract, { passive: true, capture: true });
    });

    /*
     * Header UX: the vertical menu requires RequireJS modules. Waiting for the
     * first click makes "Departamentos" feel broken because the click only starts
     * the AMD bootstrap. Prewarm as soon as the user approaches the header, and
     * also after idle so the first real click opens the menu immediately.
     */
    ['pointerover', 'focusin'].forEach(function (eventName) {
        d.addEventListener(eventName, function (evt) {
            if (evt.target && evt.target.closest && evt.target.closest('.awa-site-header, [data-role="awa-vertical-menu"]')) {
                bootNow();
            }
        }, { passive: true, capture: true, once: true });
    });

    if (!disableAutoBoot) {
        if (w.requestIdleCallback) {
            w.requestIdleCallback(function () {
                bootNow();
            }, { timeout: 3500 });
        } else {
            w.setTimeout(bootNow, 3500);
        }
    }

    /*
     * Mobile nav preflight: when the hamburger is tapped before RequireJS modules
     * have registered their click handler, the first tap is "swallowed" and
     * the nav doesn't open. This preflight handler fires ONCE on first click,
     * immediately applies body.nav-open (CSS shows the panel) and starts the
     * AMD bootstrap. The real toggleList handler registers on subsequent clicks.
     */
    function installNavPreflight() {
        var navBtn = d.querySelector('[data-action="toggle-nav"], .action.nav-toggle');
        if (!navBtn) return;

        navBtn.addEventListener('click', function onNavPreflightClick() {
            navBtn.removeEventListener('click', onNavPreflightClick);
            bootNow();
            if (!d.body.classList.contains('nav-open')) {
                d.body.classList.add('nav-open', 'awa-nav-preflight');
                navBtn.setAttribute('aria-expanded', 'true');
            }
        }, { capture: false, once: true });
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', installNavPreflight, { once: true });
    } else {
        installNavPreflight();
    }

    function hasCarouselSections() {
        return !!d.querySelector('.awa-carousel-section, .awa-shelf--carousel, .wrapper_slider');
    }

    function hasHeroSlider() {
        return !!d.querySelector('.wrapper_slider, script[id^="awa-hero-slider-config-"]');
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', function () {
            if (hasHeroSlider()) {
                syncHeroA11yAndLcp();
                installHeroViewportSync();
            }
        }, { once: true });
    } else if (hasHeroSlider()) {
        syncHeroA11yAndLcp();
        installHeroViewportSync();
    }

    /**
     * Hero: não inicializa sozinho após load.
     * O CSS crítico já mantém apenas o primeiro slide visível antes do Swiper;
     * boot automático reativava scripts/estilos e criava percepção de layout antigo.
     */
    function scheduleHeroBootAfterLoad() {
        return;
    }

    scheduleHeroBootAfterLoad();

    /* safe6: header prewarm + idle fallback preserva LCP e evita menu parecer travado. */
})(window, document);
