/**
 * AWA — CSS Interaction Gate
 *
 * Aplica links CSS com data-awa-gate="1" (media="print" → "all")
 * e injeta fila #awa-css-gate-queue de forma progressiva.
 *
 * Home: cosmético somente por intenção real para preservar TBT/LCP.
 * Interação acelera a fila; clique em área vazia não dispara o gate.
 * Outras rotas: fallback pós-load curto.
 */
(function () {
    'use strict';

    var CSS_GATE_ATTR = 'data-awa-gate';
    var applied = false;
    var homePostGateFixPending = false;
    var hasMeaningfulGateInteraction = false;
    var GATE_EVENTS = ['pointerdown', 'keydown', 'touchstart'];
    var QUEUE_WATCHDOG_MS = 30000;
    var MOBILE_FALLBACK_DELAY_MS = 1200;
    var DESKTOP_FALLBACK_DELAY_MS = 1200;
    var HOME_INTERACTION_FALLBACK_MS = 8000; /* auto-fallback cobre visitantes passivos; interação ainda acelera */
    var HOME_STYLES_M_DELAY_MS = 3200; /* styles-m pesado — após LCP, antes do gate completo */
    var HOME_LCP_GATE_FALLBACK_MS = 2800; /* alinhado ao idle do styles-l; evita espera de 5s+ sem interação */
    var QUEUE_STAGGER_MS = 50;
    var QUEUE_HEAVY_GAP_MS = 120; /* opt17: gap entre bundles LOAD_LAST (evita parse paralelo) */
    var QUEUE_STYLES_M_FRAGMENT = 'styles-m.css';
    var HOME_AUTO_FALLBACK_ENABLED = true;
    var STANDARDIZE_TERMINAL_FRAGMENT = 'awa-home-standardize-terminal-wins';
    var ALIGN_GRID_TERMINAL_FRAGMENT = 'awa-align-grid-terminal-2026-06-11';
    var QUEUE_HEADER_FIRST_FRAGMENTS = [
        'awa-third-party-bundle',
        /* awa-carousel-bundle + awa-shelf-carousel: async imediato na home — vitrine é conteúdo primário */
        /* header-stack + header-refine-terminal: migrados para styles-l (_extend.less 43.01/43.02) */
        /* impeccable-refine: home body-end sync; non-home head async */
    ];
    /* Home: bundles pesados retirados do idle gate — body-end via plugin (2026-06-13) */
    var QUEUE_HOME_LOAD_LAST_FRAGMENTS = [];

    function injectHomeFooterOverflowGuard() {
        var style;

        style = document.getElementById('awa-post-audit-footer-overflow-fix');
        if (style) {
            return;
        }

        style = document.createElement('style');
        style.id = 'awa-post-audit-footer-overflow-fix';
        style.textContent = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is(.page_footer,.page-footer) .footer-bottom{width:auto!important;max-width:100%!important;box-sizing:border-box!important}';
        (document.head || document.documentElement).appendChild(style);
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: 767px)').matches;
    }

    function isMeaningfulIntent(event) {
        var target;

        if (!event) {
            return false;
        }

        if (event.type === 'keydown') {
            return event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar';
        }

        target = event.target;

        return !!(target && target.closest && target.closest(
            'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], ' +
            '.minicart-wrapper, .awa-header-account-prompt, #search_mini_form, .awa-hero-swiper__nav, ' +
            '.swiper-pagination-bullet, .awa-category-carousel__item, .product-item, .item-product'
        ));
    }

    function isHomePage() {
        return !!(document.body && (
            document.body.classList.contains('cms-index-index') ||
            document.body.classList.contains('cms-home') ||
            document.body.classList.contains('cms-homepage_ayo_home5')
        ));
    }

    function onGateInteraction(event) {
        if (isHomePage() && !isMeaningfulIntent(event)) {
            return;
        }

        hasMeaningfulGateInteraction = true;

        if (applied) {
            if (homePostGateFixPending) {
                injectPostGateHeaderFix();
                injectHeaderVtexFinalLock();
                window.setTimeout(function () {
                    injectHeaderVtexFinalLock();
                }, 800);
                window.setTimeout(function () {
                    injectHeaderVtexFinalLock();
                }, 1800);
                injectHomeCarouselCardTerminal();
                injectHeaderMobileGridTerminal();
                homePostGateFixPending = false;
            }
            return;
        }

        applyGatedCSS('interaction');
    }

    function isHeavyQueueFragment(url) {
        var j;
        var frag;

        if (!url) {
            return false;
        }

        for (j = 0; j < QUEUE_HOME_LOAD_LAST_FRAGMENTS.length; j += 1) {
            frag = QUEUE_HOME_LOAD_LAST_FRAGMENTS[j];
            if (url.indexOf(frag) !== -1) {
                return true;
            }
        }

        return url.indexOf(QUEUE_STYLES_M_FRAGMENT) !== -1;
    }

    function injectQueuedStylesheets() {
        return new Promise(function (resolve) {
            var node = document.getElementById('awa-css-gate-queue');
            var urls;
            var i;
            var link;
            var pending = 0;
            var urlsToLoad = [];
            var resolved = false;
            var watchdogId;

            function finishQueue() {
                if (resolved) {
                    return;
                }
                resolved = true;
                if (watchdogId) {
                    window.clearTimeout(watchdogId);
                }
                resolve();
            }

            if (!node || !node.textContent) {
                finishQueue();
                return;
            }

            try {
                urls = JSON.parse(node.textContent);
            } catch (e) {
                finishQueue();
                return;
            }

            if (!Array.isArray(urls)) {
                finishQueue();
                return;
            }

            urls.sort(function (a, b) {
                var aPri = 0;
                var bPri = 0;
                var aLast = 0;
                var bLast = 0;
                var j;
                var frag;

                for (j = 0; j < QUEUE_HEADER_FIRST_FRAGMENTS.length; j += 1) {
                    frag = QUEUE_HEADER_FIRST_FRAGMENTS[j];
                    if (a.indexOf(frag) !== -1) {
                        aPri = j + 1;
                    }
                    if (b.indexOf(frag) !== -1) {
                        bPri = j + 1;
                    }
                }

                if (aPri && !bPri) {
                    return -1;
                }
                if (!aPri && bPri) {
                    return 1;
                }
                if (aPri && bPri) {
                    return aPri - bPri;
                }

                for (j = 0; j < QUEUE_HOME_LOAD_LAST_FRAGMENTS.length; j += 1) {
                    frag = QUEUE_HOME_LOAD_LAST_FRAGMENTS[j];
                    if (a.indexOf(frag) !== -1) {
                        aLast = j + 1;
                    }
                    if (b.indexOf(frag) !== -1) {
                        bLast = j + 1;
                    }
                }

                if (aLast && !bLast) {
                    return 1;
                }
                if (!aLast && bLast) {
                    return -1;
                }
                if (aLast && bLast) {
                    return aLast - bLast;
                }

                return 0;
            });

            for (i = 0; i < urls.length; i += 1) {
                if (!urls[i] || document.querySelector('link[href="' + urls[i] + '"]')) {
                    continue;
                }
                urlsToLoad.push(urls[i]);
            }

            /* Home: styles-m (~5MB) por último — reduz TBT/Speed Index no lab sem atrasar LCP */
            if (document.body && document.body.classList.contains('cms-index-index')) {
                var stylesMUrls = [];
                var otherUrls = [];
                for (i = 0; i < urlsToLoad.length; i += 1) {
                    if (urlsToLoad[i].indexOf(QUEUE_STYLES_M_FRAGMENT) !== -1) {
                        stylesMUrls.push(urlsToLoad[i]);
                    } else {
                        otherUrls.push(urlsToLoad[i]);
                    }
                }
                urlsToLoad = otherUrls.concat(stylesMUrls);
            }

            if (urlsToLoad.length === 0) {
                finishQueue();
                return;
            }

            pending = urlsToLoad.length;
            watchdogId = window.setTimeout(finishQueue, QUEUE_WATCHDOG_MS);

            function onQueueSheetDone() {
                pending -= 1;
                if (pending <= 0) {
                    finishQueue();
                }
            }

            function appendQueuedSheet(url, done) {
                link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = url;
                link.fetchPriority = 'low';
                link.onload = done;
                link.onerror = done;
                document.head.appendChild(link);
            }

            var isHomeGate = document.body && document.body.classList.contains('cms-index-index');
            var lightUrls = [];
            var heavyUrls = [];

            for (i = 0; i < urlsToLoad.length; i += 1) {
                if (isHeavyQueueFragment(urlsToLoad[i])) {
                    heavyUrls.push(urlsToLoad[i]);
                } else {
                    lightUrls.push(urlsToLoad[i]);
                }
            }

            for (i = 0; i < lightUrls.length; i += 1) {
                window.setTimeout(
                    appendQueuedSheet.bind(null, lightUrls[i], onQueueSheetDone),
                    i * QUEUE_STAGGER_MS
                );
            }

            /* opt17: bundles pesados em série — evita travamento do main thread */
            (function loadHeavySequential(index) {
                var url;
                var stylesMDelay;
                var run;

                if (index >= heavyUrls.length) {
                    return;
                }

                url = heavyUrls[index];
                stylesMDelay = (isHomeGate && url.indexOf(QUEUE_STYLES_M_FRAGMENT) !== -1)
                    ? HOME_STYLES_M_DELAY_MS
                    : 0;

                run = function () {
                    appendQueuedSheet(url, function () {
                        window.setTimeout(function () {
                            loadHeavySequential(index + 1);
                        }, QUEUE_HEAVY_GAP_MS);
                        onQueueSheetDone();
                    });
                };

                if (stylesMDelay > 0) {
                    window.setTimeout(run, stylesMDelay);
                } else {
                    run();
                }
            }(0));
        });
    }

    function normalizeTerminalHref(href) {
        return href || '';
    }

    /** Header refine terminal — migrado para styles-l via _extend.less (import 43.02). */
    function injectHeaderRefineTerminal() {
        return Promise.resolve();
    }

    /** Impeccable refine — reinjeta após fila gate (vence polish-type / layout-bundle). */
    function injectImpeccableRefineTerminal() {
        return new Promise(function (resolve) {
            var probe = document.querySelector('link[data-awa-impeccable-terminal="refine"]');
            var href;
            var link;

            if (document.querySelector('link[data-awa-impeccable-refine-terminal="1"]')) {
                resolve();
                return;
            }

            href = '';
            if (probe && probe.href) {
                href = probe.href;
            } else {
                probe = document.querySelector('link[href*="awa-commerce-impeccable-refine"]');
                if (probe && probe.href) {
                    href = probe.href;
                }
            }

            if (!href) {
                resolve();
                return;
            }

            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'all';
            link.setAttribute('data-awa-impeccable-refine-terminal', '1');
            link.onload = resolve;
            link.onerror = resolve;
            document.body.appendChild(link);
        });
    }

    /** Home standardize terminal — última camada CSS (vence gate-polish featured/eyebrow). */
    function resolveHomeStandardizeTerminalHref() {
        var probe = document.querySelector('link[href*="' + STANDARDIZE_TERMINAL_FRAGMENT + '"]');
        var node;
        var urls;
        var i;

        if (probe && probe.href) {
            return probe.href;
        }

        node = document.getElementById('awa-css-gate-queue');
        if (node && node.textContent) {
            try {
                urls = JSON.parse(node.textContent);
                for (i = 0; i < urls.length; i += 1) {
                    if (urls[i].indexOf(STANDARDIZE_TERMINAL_FRAGMENT) !== -1) {
                        return urls[i];
                    }
                }
            } catch (e) { /* noop */ }
        }

        probe = document.querySelector('link[href*="/static/version"][href*="/css/"]');
        if (probe && probe.href) {
            return probe.href.replace(/[^/]+$/, 'awa-home-standardize-terminal-wins-2026-06-09.min.css?v=20260702-bottomnav-specificity-fix2');
        }

        return '';
    }

    function injectHomeStandardizeTerminal() {
        return new Promise(function (resolve) {
            var href;
            var link;

            if (!isHomePage()) {
                resolve();
                return;
            }

        if (document.querySelector(
            'link[data-awa-home-standardize-terminal="1"],' +
            'link[data-awa-home-standardize-body="1"],' +
            'link[href*="awa-home-standardize-terminal-wins"]'
        )) {
            resolve();
            return;
        }

            href = resolveHomeStandardizeTerminalHref();
            if (!href) {
                resolve();
                return;
            }

            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'all';
            link.setAttribute('data-awa-home-standardize-terminal', '1');
            link.onload = resolve;
            link.onerror = resolve;
            document.body.appendChild(link);
        });
    }

    function resolveAlignGridTerminalHref() {
        var probe = document.querySelector('link[href*="' + ALIGN_GRID_TERMINAL_FRAGMENT + '"]');
        var node;
        var urls;
        var i;

        if (probe && probe.href) {
            return probe.href;
        }

        node = document.getElementById('awa-css-gate-queue');
        if (node && node.textContent) {
            try {
                urls = JSON.parse(node.textContent);
                for (i = 0; i < urls.length; i += 1) {
                    if (urls[i].indexOf(ALIGN_GRID_TERMINAL_FRAGMENT) !== -1) {
                        return urls[i];
                    }
                }
            } catch (e) { /* noop */ }
        }

        probe = document.querySelector('link[href*="' + STANDARDIZE_TERMINAL_FRAGMENT + '"]');
        if (probe && probe.href) {
            return probe.href.replace(/[^/]+$/, ALIGN_GRID_TERMINAL_FRAGMENT + '.min.css?v=20260618-postaudit2');
        }

        probe = document.querySelector('link[href*="/static/"][href*="/css/"]');
        if (probe && probe.href) {
            return probe.href.replace(/\/css\/[^/?]+(\?[^/?]*)?$/, '/css/' + ALIGN_GRID_TERMINAL_FRAGMENT + '.min.css?v=20260618-postaudit2');
        }

        return '';
    }

    function injectAlignGridTerminal() {
        return new Promise(function (resolve) {
            var href;
            var link;

            if (document.querySelector(
                'link[data-awa-align-grid-terminal="1"],' +
                'link[href*="' + ALIGN_GRID_TERMINAL_FRAGMENT + '"]'
            )) {
                resolve();
                return;
            }

            href = resolveAlignGridTerminalHref();
            if (!href) {
                resolve();
                return;
            }

            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'all';
            link.setAttribute('data-awa-align-grid-terminal', '1');
            link.onload = resolve;
            link.onerror = resolve;
            document.body.appendChild(link);
        });
    }

    /** Impeccable audit — no fim do body (vence fila reinjetada no head). */
    function injectImpeccableAuditTerminal() {
        return new Promise(function (resolve) {
            var href;
            var link;
            var probe = document.querySelector(
                'link[data-awa-impeccable-terminal="1"],' +
                'link[data-awa-impeccable-terminal="final"]'
            );

            if (probe) {
                resolve();
                return;
            }

            href = '';
            probe = document.querySelector('link[href*="awa-impeccable-audit-2026-05-28"]');
            if (probe && probe.href) {
                href = probe.href;
            } else {
                probe = document.querySelector('link[href*="awa-header-refine-terminal"]');
                if (probe && probe.href) {
                    href = probe.href.replace(/[^/]+$/, 'awa-impeccable-audit-2026-05-28.css');
                }
            }

            if (!href) {
                resolve();
                return;
            }

            link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.media = 'all';
            link.setAttribute('data-awa-impeccable-terminal', '1');
            link.onload = resolve;
            link.onerror = resolve;
            document.body.appendChild(link);
        });
    }

    /** Post-gate final-wins: CSS injetado no fim do body vence links do gate no head. */
    function getHomeStabilityCss() {
        return 'body#html-body.cms-index-index:not(.nav-open) .navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:not(.vmm-open):not(.menu-open),body#html-body.cms-home:not(.nav-open) .navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:not(.vmm-open):not(.menu-open),body#html-body.cms-homepage_ayo_home5:not(.nav-open) .navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:not(.vmm-open):not(.menu-open){display:none!important;visibility:hidden!important;height:0!important;max-height:0!important;min-height:0!important;overflow:visible!important;margin:0!important;padding:0!important;border:0!important;pointer-events:none!important}body#html-body.cms-index-index .content-top-home a.awa-section-header__link,body#html-body.cms-index-index .content-top-home a.awa-shelf__view-all,body#html-body.cms-index-index .content-top-home a.awa-category-carousel__cta-link,body#html-body.cms-home .content-top-home a.awa-section-header__link,body#html-body.cms-home .content-top-home a.awa-shelf__view-all,body#html-body.cms-home .content-top-home a.awa-category-carousel__cta-link,body#html-body.cms-homepage_ayo_home5 .content-top-home a.awa-section-header__link,body#html-body.cms-homepage_ayo_home5 .content-top-home a.awa-shelf__view-all,body#html-body.cms-homepage_ayo_home5 .content-top-home a.awa-category-carousel__cta-link{display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:6px!important;min-height:44px!important;padding:8px 12px!important;border-radius:999px!important;box-sizing:border-box!important;text-decoration:none!important;white-space:nowrap!important;color:var(--awa-primary,#b73337)!important}body#html-body.cms-index-index .content-top-home .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]),body#html-body.cms-home .content-top-home .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]),body#html-body.cms-homepage_ayo_home5 .content-top-home .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]){opacity:1!important;visibility:visible!important}';
    }

    /** opt21: shell leve anti-CLS — aplica antes da fila CSS pesada; post-gate cosmético continua adiável. */
    function getHomeClsShellCss() {
        var home = 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper';

        return home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded){display:flex!important;flex-flow:row nowrap!important;overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important;width:100%!important;min-height:300px!important;max-height:300px!important;margin:0!important;padding:0!important;list-style:none!important}'
            + home + ' .awa-shelf--carousel .awa-carousel__viewport>:is(ul.owl.awa-carousel__track,.awa-carousel__track){max-block-size:none!important;overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 50%!important;max-width:50%!important;box-sizing:border-box!important;list-style:none!important}'
            + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+3){display:none!important}'
            + home + ' .content-top-home .product-thumb,' + home + ' .content-top-home .product-image-container{aspect-ratio:1/1!important;min-height:120px!important;display:block!important;width:100%!important;contain:layout!important}'
            + home + ' .content-top-home .product-thumb img,' + home + ' .item-product .product-image-photo{min-height:120px!important;width:100%!important;height:auto!important;aspect-ratio:1/1!important;object-fit:contain!important;opacity:1!important;visibility:visible!important}'
            + home + ' .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]){opacity:1!important;visibility:visible!important}'
            + home + ' .awa-category-carousel__icon svg{display:block!important;width:64px!important;height:64px!important;max-width:64px!important;max-height:64px!important}'
            + 'html body#html-body .awa-whatsapp-float{width:56px!important;height:56px!important;min-width:56px!important;min-height:56px!important}'
            + 'html body#html-body nav.fixed-bottom.hidden-sm{min-height:56px!important}'
            + '.awa-footer-pay-logos{display:flex!important;flex-wrap:wrap!important;gap:8px!important;align-items:center!important;min-height:34px!important}'
            + '.awa-pay-logo{min-width:40px!important;min-height:28px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;padding:3px 6px!important}'
            + '.awa-pay-logo img{display:block!important;object-fit:contain!important}'
            + '.awa-pay-logo img[width=\"46\"][height=\"28\"]{width:46px!important;height:28px!important;max-width:46px!important;max-height:28px!important}'
            + '.awa-pay-logo img[width=\"36\"][height=\"24\"]{width:36px!important;height:24px!important;max-width:36px!important;max-height:24px!important}'
            + 'html body#html-body .page-wrapper :not(.awa-site-header) .logo img{display:block!important;width:161px!important;height:auto!important;aspect-ratio:161/92!important;max-width:min(161px,36vw)!important;max-height:56px!important;object-fit:contain!important}'
            + '@media(min-width:992px){html body#html-body .awa-site-header .logo img{display:block!important;width:104px!important;height:44px!important;max-width:104px!important;max-height:44px!important;min-width:0!important;aspect-ratio:auto!important;object-fit:contain!important}}'
            + '@media(max-width:991px){html body#html-body .awa-site-header .logo img{display:block!important;width:auto!important;height:44px!important;max-width:min(120px,36vw)!important;max-height:44px!important;min-width:0!important;aspect-ratio:auto!important;object-fit:contain!important}}'
            + home + ' .awa-hero-b2b-cta ul.awa-hero-benefits{display:grid!important;gap:12px!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;list-style:none!important;margin:0!important;padding:0!important}'
            + home + ' .awa-hero-benefits__item{display:flex!important;align-items:center!important;gap:8px!important;padding:12px!important;box-sizing:border-box!important}'
            + home + ' .awa-hero-benefits__icon{display:inline-grid!important;flex:0 0 32px!important;width:32px!important;height:32px!important;min-width:32px!important;min-height:32px!important;place-items:center!important}'
            + home + ' .awa-hero-benefits__icon svg{display:block!important;width:24px!important;height:24px!important;max-width:24px!important;max-height:24px!important}'
            + 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom{min-height:120px!important;contain:layout style!important}'
            + 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-pay-sec{min-height:56px!important}'
            + '@media(min-width:768px){' + home + ' .awa-hero-b2b-cta ul.awa-hero-benefits{gap:16px!important;grid-template-columns:repeat(4,minmax(0,1fr))!important}' + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 33.333%!important;max-width:33.333%!important}' + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+3){display:flex!important}' + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+4){display:none!important}}'
            + '@media(max-width:479px){' + home + ' .awa-hero-b2b-cta ul.awa-hero-benefits{grid-template-columns:1fr!important;gap:8px!important}}'
            + '@media(min-width:1024px){' + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 25%!important;max-width:25%!important}' + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+4){display:flex!important}' + home + ' .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+5){display:none!important}}'
            + '@media(max-width:991px){' + home + ' .awa-category-carousel__icon svg{width:52px!important;height:52px!important;max-width:52px!important;max-height:52px!important}}';
    }

    function injectHomeClsShell() {
        var style;

        if (!isHomePage() || document.getElementById('awa-home-cls-shell')) {
            return;
        }

        style = document.createElement('style');
        style.id = 'awa-home-cls-shell';
        style.textContent = getHomeClsShellCss();
        document.body.appendChild(style);
    }

    function injectHomeStabilityFix(id) {
        var style;
        var isHome;

        isHome = document.body.classList.contains('cms-index-index') ||
            document.body.classList.contains('cms-home') ||
            document.body.classList.contains('cms-homepage_ayo_home5');

        if (!isHome) {
            return;
        }

        style = document.getElementById(id);
        if (!style) {
            style = document.createElement('style');
            style.id = id;
            document.body.appendChild(style);
        }

        style.textContent = getHomeStabilityCss();
    }

    function hasHeaderCascadeLock() {
        return !!(
            document.getElementById('awa-header-impeccable-cascade-lock-v18')
            || document.getElementById('awa-header-impeccable-cascade-lock-v17')
            || document.getElementById('awa-header-impeccable-cascade-lock-v16')
            || document.getElementById('awa-header-impeccable-cascade-lock-v15')
            || document.getElementById('awa-header-impeccable-cascade-lock-v14')
        );
    }

    function injectPostGateHeaderFix() {
        var style;
        var isHome;
        var css;

        if (hasHeaderCascadeLock()) {
            return;
        }

        isHome = isHomePage();

        css = '@media (min-width:992px){html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .awa-nav-categories,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .sections.nav-sections.category-dropdown,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .section-items.nav-sections.category-dropdown-items,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .section-item-content.nav-sections.category-dropdown-item-content,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories .navigation.verticalmenu.side-verticalmenu{background:transparent!important;height:48px!important;max-height:48px!important;min-height:0!important;overflow:visible!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0!important}html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-header-categories button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"],html body#html-body .page-wrapper .header-control.awa-nav-bar .menu_left_home1 button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"]{border-radius:0!important;border:0!important;box-shadow:none!important;background:var(--awa-primary,#b73337)!important;color:#fff!important}html body#html-body .page-wrapper .header-control.awa-nav-bar .title-category-dropdown .vm-icon,html body#html-body .page-wrapper .header-control.awa-nav-bar .title-category-dropdown .icon-menu{background:transparent!important;border-radius:0!important;box-shadow:none!important}html body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt .awa-header-account-prompt__line2{display:inline-flex!important;flex-wrap:nowrap!important;align-items:center!important;gap:4px!important;white-space:nowrap!important;min-height:0!important;height:auto!important}html body#html-body .page-wrapper .awa-site-header a.awa-header-account-prompt__link.awa-header-account-prompt__link--register{color:#fff!important;background:#b73337!important;background-color:#b73337!important;border:none!important;border-radius:9999px!important;padding:3px 10px!important;font-size:max(12px,0.75rem)!important;font-weight:700!important;white-space:nowrap!important;display:inline-flex!important;align-items:center!important;line-height:1.2!important;min-height:0!important;height:auto!important}html body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt .awa-header-account-prompt__line1{font-size:max(12px,0.75rem)!important}html body#html-body .page-wrapper .awa-site-header form#search_mini_form.minisearch{height:44px!important;min-height:44px!important;max-height:44px!important;border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;box-shadow:none!important;overflow:hidden!important}html body#html-body .page-wrapper .awa-site-header a.action.showcart.header-mini-cart,html body#html-body .page-wrapper .awa-site-header .minicart-wrapper .action.showcart{overflow:visible!important;position:relative!important}}';

        css += '@media (prefers-reduced-motion:no-preference){#html-body .page-wrapper .header-wrapper-sticky,#html-body .page-wrapper .header-wrapper-sticky.is-sticky,#html-body .page-wrapper .awa-site-header .header-wrapper-sticky{transition:box-shadow .2s ease,opacity .2s ease,transform .2s ease!important}#html-body .page-wrapper .logo,#html-body .page-wrapper .awa-site-header .logo,#html-body .page-wrapper .logo img{transition:opacity .2s ease,transform .2s ease!important}}@media (prefers-reduced-motion:reduce){#html-body .page-wrapper .header-wrapper-sticky,#html-body .page-wrapper .logo,#html-body .page-wrapper .logo img{transition:none!important}}#html-body .page-wrapper .awa-header-account-prompt__guest .awa-header-account-prompt__line1,#html-body .page-wrapper .awa-benefit-desc{font-size:max(12px,.8125rem)!important;line-height:1.45!important}#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy strong{color:#333!important}#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy span{color:#666!important}#html-body .page-wrapper .awa-footer-business-contact__action,#html-body .page-wrapper .awa-footer-business-contact__action--primary{background:#fff!important;border:1px solid #e5e7eb!important;color:#111827!important}#html-body .page-wrapper .awa-footer-business-contact__action-copy strong,#html-body .page-wrapper .awa-footer-business-contact__action-copy small{color:#111827!important}html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label,html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label--social,html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label,html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label--social{color:oklch(45% .02 20)!important}html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store{background:oklch(99% .002 20)!important;border:1px solid oklch(92% .01 20)!important;padding:12px!important;border-radius:8px!important}html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store p.awa-footer-atendimento__store-name,html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store p.awa-footer-atendimento__store-address{color:oklch(22% .01 20)!important}#html-body .page-wrapper .page_footer h3.awa-newsletter-title{color:#fff!important}html body#html-body .page-wrapper :is(.navigation.verticalmenu div[id^="submenu-menu-"],.navigation.verticalmenu .submenu.navigation__submenu){border:0!important;box-shadow:0 4px 12px rgb(15 23 42/10%)!important}html body#html-body .page-wrapper :is(#search_autocomplete,.mst-searchautocomplete__autocomplete){border:0!important;box-shadow:0 4px 16px rgb(15 23 42/12%)!important;overflow:visible!important}html body#html-body .page-wrapper :is(#awa-b2b-promo-bar,.awa-b2b-promo-bar) .awa-b2b-promo-close{color:#fff!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-section-header__eyebrow{display:none!important;visibility:hidden!important;height:0!important;width:0!important;overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}html body#html-body :is(nav.fixed-bottom.hidden-sm,nav.fixed-bottom,.fixed-bottom,.awa-mobile-bottom-nav){border:0!important;border-top:0!important;box-shadow:0 -2px 8px rgb(15 23 42/8%)!important}html body#html-body .page-wrapper :is(#header .header-content,.header-container .header-content,.header-content){border:0!important;box-shadow:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-category-carousel__item .awa-category-carousel__icon{transition:transform .24s cubic-bezier(.22,1,.36,1)!important}html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-item .awa-footer-trust-copy strong{color:#333!important}html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-item .awa-footer-trust-copy span{color:#666!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell{background:oklch(98.5% .004 20)!important;border:0!important;box-shadow:none!important;padding:clamp(20px,4vw,34px)!important;border-radius:16px!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell :is(.b2b-register-container,.b2b-register-page){border:0!important;box-shadow:none!important;background:transparent!important;padding:0!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell .b2b-register-progress{border:0!important;background:transparent!important;box-shadow:none!important}html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell .form-section{border:0!important;background:transparent!important;box-shadow:none!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper{overflow-x:clip!important;overflow-y:visible!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper .awa-skip-link:not(:focus):not(:focus-visible){left:0!important;width:1px!important;height:1px!important;clip-path:inset(50%)!important;overflow:hidden!important;margin:-1px!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper :is(#awa-search-label,#awa-search-panel-a11y,.mst-searchautocomplete__autocomplete){overflow:visible!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper li.ui-menu-item.navigation__item--parent{overflow:visible!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper :is(.level0.submenu.navigation__submenu,.subchildmenu.navigation__inner-list){overflow:visible!important;padding-right:12px!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper .navigation.custommenu.main-nav>li.ui-menu-item.navigation__item>a{padding-block:12px!important}html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper form#search_mini_form.minisearch .actions{padding-inline-start:8px!important}html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{display:grid!important;grid-template-columns:1fr!important;gap:8px!important;align-items:start!important}@media (min-width:768px){html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{grid-template-columns:200px minmax(0,1fr)!important;gap:16px!important}html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns>.column.main{grid-column:2!important}}@media (min-width:1024px){html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{grid-template-columns:240px minmax(0,1fr)!important;gap:24px!important}}html body#html-body.b2b-account-dashboard .page-wrapper .summary-card{box-shadow:none!important}html body#html-body.b2b-account-dashboard .page-wrapper .b2b-dashboard-lazy-panel[data-lazy-loaded="error"],html body#html-body.b2b-account-dashboard .page-wrapper .b2b-dashboard-lazy-panel.b2b-dashboard-lazy-panel--error{border:0!important;background:transparent!important;padding:8px 0!important}';

        if (isHome) {
            css += 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded){display:flex!important;flex-flow:row nowrap!important;overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important;width:100%!important;min-height:300px!important;max-height:300px!important;margin:0!important;padding:0!important;list-style:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-carousel__viewport>:is(ul.owl.awa-carousel__track,.awa-carousel__track){max-block-size:none!important;overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 50%!important;max-width:50%!important;box-sizing:border-box!important;list-style:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+3){display:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-nav-bar .velaFooterLinks,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .velaFooterLinks:not(.page-footer .velaFooterLinks),html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .header-control .velaFooterLinks{display:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-account-dropdown__trigger:not([aria-expanded="true"])+.awa-account-dropdown__menu{display:none!important;opacity:0!important;visibility:hidden!important;pointer-events:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .item-product .product-image-photo,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .item-product .product-thumb img{min-height:120px!important;object-fit:contain!important;opacity:1!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel :is(img.product-image-photo[loading="lazy"],.product-thumb img[loading="lazy"]){opacity:1!important;visibility:visible!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel.awa-carousel-pending .awa-carousel__viewport{animation-iteration-count:3!important}@media(min-width:768px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 33.333%!important;max-width:33.333%!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+3){display:flex!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+4){display:none!important}}@media(min-width:1024px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item{flex:0 0 25%!important;max-width:25%!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+4){display:flex!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-carousel-section ul.owl.awa-carousel__track:not(.owl-carousel):not(.owl-loaded)>li.item:nth-child(n+5){display:none!important}}';
            css += getHomeStabilityCss();
            css += '@media(max-width:767px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel>.awa-owl-nav,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-carousel>.awa-owl-nav{display:flex!important;position:relative!important;transform:none!important;min-height:44px!important;height:auto!important;justify-content:flex-end!important;margin-block-start:8px!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-owl-nav__btn,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-owl-nav__btn.swiper-button-prev,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-shelf--carousel .awa-owl-nav__btn.swiper-button-next{position:relative!important;inset:auto!important;top:auto!important;left:auto!important;right:auto!important;inline-size:44px!important;block-size:44px!important;min-inline-size:44px!important;min-block-size:44px!important;transform:none!important;margin:0!important}}';
            css += '@media(max-width:767px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is(.awa-owl-nav__btn,.awa-carousel__arrow,.awa-carousel__toggle){inline-size:44px!important;block-size:44px!important;min-inline-size:44px!important;min-block-size:44px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important}}';
            css += '@media (min-width:992px){html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .menu_left_home1 .verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open,[aria-hidden="false"],[data-awa-menu-state="open"]){overflow:visible!important;overflow-x:visible!important;contain:layout style!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .menu_left_home1 .verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open)>li.ui-menu-item.level0{overflow:visible!important}}';
        }

        /* Cascade-lock v12 no body: nav/mobile only — promo + impeccable surfaces live in PHP cascade-lock */
        if (!hasHeaderCascadeLock()) {
            css += '@media(max-width:767px){html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky,html body#html-body .page-wrapper #header .header-wrapper-sticky{height:96px!important;min-height:96px!important;max-height:96px!important;overflow:hidden!important;padding-block:0!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){display:grid!important;grid-template-areas:"toggle brand cart" "search search search"!important;grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:44px 44px!important;gap:8px!important;padding:0 16px!important;max-height:96px!important;height:96px!important;overflow:hidden!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){grid-area:brand!important;align-self:center!important;max-height:44px!important}}@media (min-width:768px) and (max-width:991px){html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){display:grid!important;grid-template-areas:\"brand search actions\"!important;grid-template-columns:clamp(112px,14vw,148px) minmax(0,1fr) minmax(220px,max-content)!important;grid-template-rows:56px!important;gap:0 12px!important;padding:0 16px!important;max-height:56px!important;height:56px!important;min-height:56px!important;max-width:min(100%,1280px)!important;margin-inline:auto!important;overflow:visible!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-header-mobile-toggle,.action.nav-toggle,[data-action="toggle-nav"]){display:none!important;visibility:hidden!important;pointer-events:none!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-right-col{display:inline-flex!important;grid-area:actions!important;gap:8px!important;align-items:center!important;justify-content:flex-end!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-right-col>:not(.awa-header-minicart):not(.awa-header-account-prompt){display:none!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;grid-column:auto!important;min-width:0!important}}@media (min-width:992px){html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row]{display:grid!important;grid-template-columns:clamp(128px,12vw,168px) minmax(0,1fr) minmax(280px,max-content)!important;grid-template-areas:\"brand search actions\"!important;align-items:center!important;gap:0 12px!important;overflow:visible!important}html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row],html body#html-body .page-wrapper .awa-site-header .header.awa-main-header{min-height:64px!important;height:64px!important;max-height:64px!important;padding-block:0!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row]{display:grid!important;grid-template-columns:clamp(128px,12vw,168px) minmax(0,1fr) minmax(280px,max-content)!important;grid-template-areas:\"brand search actions\"!important;column-gap:12px!important}html body#html-body .page-wrapper .awa-site-header .awa-header-primary-row{display:contents!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-header-brand-cell{grid-area:brand!important;grid-column:auto!important;max-width:168px!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-header-search-col{grid-area:search!important;grid-column:auto!important;min-width:0!important;max-width:none!important}html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-site-header .awa-header-right-col{grid-area:actions!important;grid-column:auto!important;width:auto!important;max-width:none!important;justify-self:end!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){align-self:center!important;height:auto!important;min-height:0!important;max-height:56px!important}html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky,html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky.is-sticky,html body#html-body .page-wrapper #header .header-wrapper-sticky{height:auto!important;min-height:64px!important;max-height:64px!important;overflow:visible!important}html body#html-body .page-wrapper .header-control.header-nav.awa-nav-bar,html body#html-body .page-wrapper .header-control.awa-nav-bar,html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-bar__inner,html body#html-body .page-wrapper .header-control.awa-nav-bar > .container{min-height:40px!important;max-height:40px!important;height:40px!important;box-sizing:border-box!important}html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar,html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar > .container,html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar .awa-nav-bar__inner{min-height:40px!important;max-height:40px!important;height:40px!important;box-sizing:border-box!important;padding-block:0!important;overflow:visible!important}html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-bar__inner,html body#html-body .page-wrapper .header-control.awa-nav-bar > .container > .row{display:flex!important;align-items:center!important;min-height:40px!important;height:40px!important}}';
        }

        style = document.getElementById('awa-header-post-gate-fix');
        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-header-post-gate-fix';
            document.body.appendChild(style);
        }

        css += '@media (max-width:767px){html body#html-body .page-wrapper :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){height:auto!important;min-height:96px!important;max-height:none!important;overflow:visible!important;contain:none!important;padding-block:0!important}html body#html-body .page-wrapper .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){overflow:visible!important}html body#html-body .page-wrapper .awa-site-header :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.awa-header-search-col,.awa-header-minicart,.minicart-wrapper){overflow:visible!important}}@media (min-width:768px) and (max-width:991px){html body#html-body .page-wrapper :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){height:auto!important;min-height:56px!important;max-height:none!important;overflow:visible!important;contain:none!important;padding-block:0!important}}@media (min-width:992px){html body#html-body .page-wrapper :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){height:auto!important;min-height:64px!important;max-height:64px!important;overflow:visible!important;contain:none!important;padding-block:0!important}}';

        /* §SEARCH-SSOT post-gate — última camada inline (vence density/S11/polish-type legado) */
        css += '@media (min-width:768px){html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(form#search_mini_form,form.minisearch,form.search-content){display:flex!important;align-items:stretch!important;height:44px!important;min-height:44px!important;max-height:44px!important;border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;overflow:hidden!important;box-shadow:none!important}html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(input#search,.input-text){height:44px!important;min-height:44px!important;max-height:44px!important;line-height:44px!important;font-size:14px!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:transparent!important}html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(button.action.search,button.awa-search-btn,form#search_mini_form button.action.search,.actions .action.search,.block-search .action.search){width:44px!important;min-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;background-color:transparent!important;color:var(--awa-primary,#b73337)!important;box-shadow:none!important}html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(button.action.search,.actions .action.search) svg{stroke:var(--awa-primary,#b73337)!important;fill:none!important}}@media (max-width:767px){html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(form#search_mini_form,form.minisearch,form.search-content){display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;grid-template-areas:"field submit"!important;height:44px!important;min-height:44px!important;max-height:44px!important;border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;overflow:hidden!important}html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(input#search,.input-text){height:44px!important;min-height:44px!important;max-height:44px!important;line-height:44px!important;font-size:16px!important}html body#html-body:not(#__awa-no-match):not(.onepagecheckout-index-index):not(.checkout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]:not(.awa-header-condensed) .awa-header-search-col :is(button.action.search,button.awa-search-btn,form#search_mini_form button.action.search,.actions .action.search,.block-search .action.search){width:44px!important;min-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important}}';

        /* §H41b — carousel card: uma caixa mensurável (vence density/impeccable pós-align-grid) */
        css += 'html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is(.awa-carousel-section,.content-top-home .awa-shelf--carousel) .item-product.awa-carousel-card-slot>.content-item-product.awa-product-card{display:contents!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0!important;min-height:0!important}';

        /* §IMPECCABLE v10.47 — hero contrast/inset + footer labels (terminal pós-gate) */
        css += 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer):not(.awa-footer--dark) .footer-container :is(p.awa-footer-atendimento__label,p.awa-footer-atendimento__label--social){color:#666!important}html body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5){--awa-home-shell-gutter:16px;--awa-home-pad-compact:8px;--awa-home-pad-standard:12px;--awa-home-pad-featured:16px;--awa-home-section-gap:12px}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .awa-shelf--carousel{padding-inline:0!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home :is(.awa-carousel-section,.top-home-content,.awa-home-recent-orders,.awa-grid-section,.awa-home-niche-shelves,.awa-hero-b2b-cta) > .container,html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .ayo-home5-wrapper--template-driven .container{padding-inline:0!important;margin-inline:0!important;width:100%!important;max-width:100%!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is(.page_footer,.page-footer) .awa-footer-newsletter{padding-inline:0!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .ayo-home5-wrapper--template-driven>.top-home-content.awa-home-section{padding-inline:0!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .top-home-content--category-carousel .awa-category-carousel__viewport{padding:0!important;margin-inline:0!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .top-home-content--category-carousel :is(.awa-category-carousel__track,#awa-cat-carousel.awa-category-carousel__track){padding:0!important;margin-inline:0!important;scroll-padding-inline:0!important;gap:8px!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home .ayo-home5-wrapper--template-driven>.top-home-content.awa-home-section{padding-block:8px!important;margin-block:0!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .top-home-content--above-fold .banner-slider.banner-slider2{padding:clamp(8px,1.2vw,14px)!important;box-sizing:border-box!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .banner_item .text-banner:not(:empty){padding:clamp(16px,3vw,32px) clamp(16px,4vw,48px)!important;background:linear-gradient(to top,rgb(15 23 42 / 72%) 0%,rgb(15 23 42 / 28%) 55%,transparent 100%)!important;z-index:2!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .banner_item .text-banner :is(h2,.slide-title){color:#fff!important}html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .banner_item .text-banner :is(p,.slide-desc){color:rgb(248 250 252 / 92%)!important;background-color:transparent!important}';

        style.textContent = css;
    }

    /** §H41b — uma caixa por card; roda sempre (cascade-lock v14+ bloqueia post-gate header). */
    function injectHomeCarouselCardTerminal() {
        var style;
        var css;

        if (!isHomePage()) {
            return;
        }

        css = 'html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.page-wrapper :is(.awa-carousel-section,.content-top-home .awa-shelf--carousel) '
            + '.item-product.awa-carousel-card-slot>.content-item-product.awa-product-card{'
            + 'display:contents!important;background:transparent!important;border:0!important;'
            + 'box-shadow:none!important;padding:0!important;margin:0!important;min-height:0!important}';

        style = document.getElementById('awa-home-carousel-card-terminal');
        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-home-carousel-card-terminal';
            document.body.appendChild(style);
        }

        style.textContent = css;
    }

    /** §HPOL5-MOBILE — grid mobile do header; roda sempre (cascade-lock v14+ bloqueia post-gate parcial). */
    function injectHeaderMobileGridTerminal() {
        var style;
        var css;

        css = '@layer awa-visual-priority{@media(max-width:767px){'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            + ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            + 'display:grid!important;grid-template-columns:minmax(0,1fr)!important;'
            + 'grid-template-rows:44px 44px auto!important;grid-template-areas:"primary" "search" "actions"!important;'
            + 'gap:4px 8px!important;height:auto!important;min-height:0!important;max-height:none!important;'
            + 'padding:4px 12px 0!important;overflow:visible!important;box-sizing:border-box!important}'
            /* Autopilot 2026-07-03: a camada PHP awa-fixes transforma o header mobile em primary/search/actions;
               este lock mantém o primary-row como grid interno para evitar logo/menu/carrinho deslocados. */
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-primary-row[data-awa-header-primary-row="true"]{display:grid!important;grid-area:primary!important;'
            + 'grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-areas:"toggle brand cart"!important;'
            + 'align-items:center!important;column-gap:8px!important;width:100%!important;min-width:0!important;'
            + 'min-height:44px!important;max-height:44px!important}'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-primary-row .awa-header-mobile-toggle{grid-area:toggle!important;justify-self:start!important}'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-primary-row > .col-md-2,'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-brand-cell[data-awa-header-brand="true"]{grid-area:brand!important;justify-self:center!important}'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-primary-row .awa-header-cart-link{grid-area:cart!important;justify-self:end!important}'
            /* BUGFIX-B2B-PANEL-MOBILE-2026-07-08: esta regra (layer alta prioridade,
               vence !important normal via reversao de ordem de camadas) zerava o
               right-col inteiro no mobile — incluindo o painel B2B injetado por JS
               dentro dele (b2b-panel-hydrate.js/header-status-panel.js). Resultado:
               trigger com display:none e o "modal" do painel B2B nunca renderizava
               no mobile (confirmado via CDP getMatchedStylesForNode). Exceção via
               :has() para manter o comportamento legado quando o painel B2B não
               está presente. */
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-right-col[data-awa-header-right="true"]:not(:has(.b2b-status-panel)){display:none!important;visibility:hidden!important;'
            + 'width:0!important;min-width:0!important;max-width:0!important;height:0!important;min-height:0!important;'
            + 'max-height:0!important;overflow:hidden!important;pointer-events:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            + '.page-wrapper header.awa-site-header[data-awa-header-mode="default"] '
            + '.awa-header-right-col[data-awa-header-right="true"]:has(.b2b-status-panel){display:flex!important;visibility:visible!important;'
            + 'width:auto!important;min-width:0!important;max-width:min(220px,54vw)!important;height:44px!important;min-height:44px!important;'
            + 'max-height:44px!important;overflow:visible!important;pointer-events:auto!important;grid-area:actions!important;'
            + 'justify-self:end!important;align-self:center!important}'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            + ':is(.header-wrapper-sticky,.header.awa-main-header){height:auto!important;min-height:0!important;'
            + 'max-height:none!important;padding-block:0!important;overflow:visible!important}'
            + 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode="default"]{'
            + '--awa-header-main-row-h:auto!important}}}';

        style = document.getElementById('awa-header-mobile-grid-terminal');
        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-header-mobile-grid-terminal';
            document.body.appendChild(style);
        }

        style.textContent = css;
    }

    function injectPostAuditVisualTerminal() {
        var style;
        var css;

        if (!isHomePage()) {
            return;
        }

        css = ''
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar{background:#ffffff!important;background-color:#ffffff!important;color:var(--awa-text-primary,#111827)!important;border-bottom-color:var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__cta strong){color:var(--awa-text-primary,#111827)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-close{background:transparent!important;color:var(--awa-text-secondary,var(--awa-text,#111827))!important}'
            /* FIX-HOME-PROMO-GHOST-MOBILE-2026-07-05: colapso equivalente ao bloco
               desktop abaixo, porém FORA do @media(min-width:992px) — confirmado via
               CDP getMatchedStylesForNode em viewport 360px que o computed height da
               promo bar ficava travado em 36/44px (várias regras !important de altura
               fixa no bundle legado vencem a regra mobile de baixa especificidade em
               awa-header-mobile-grid-critical.css). Mesma especificidade de 7 IDs usada
               no fix desktop, sem media query, cobre todos os breakpoints. */
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar.awa-promo-bar--scrolled-away,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5).awa-header-is-sticky .awa-site-header #awa-b2b-promo-bar,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header.awa-header-condensed #awa-b2b-promo-bar{'
            + 'height:0!important;min-height:0!important;max-height:0!important;padding:0!important;margin:0!important;border:0!important;overflow:hidden!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar.awa-promo-bar--scrolled-away :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout,.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__cta),'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5).awa-header-is-sticky .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout,.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__cta),'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header.awa-header-condensed #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout,.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__cta){'
            + 'height:0!important;min-height:0!important;max-height:0!important;padding:0!important;margin:0!important;border:0!important;overflow:hidden!important}'
            + '@media(min-width:992px){'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header{background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border-bottom:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #header.header-container{height:32px!important;min-height:32px!important;max-height:32px!important;overflow:visible!important;background:transparent!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #header.header-container .header-content{height:32px!important;min-height:32px!important;max-height:32px!important;padding:0!important;align-items:center!important;overflow:visible!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar{position:relative!important;height:32px!important;min-height:32px!important;max-height:32px!important;padding:0 40px 0 8px!important;border:0!important;border-bottom:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;background:#ffffff!important;background-color:#ffffff!important;color:var(--awa-text-primary,#111827)!important;line-height:32px!important;box-sizing:border-box!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__inner{position:static!important;width:100%!important;max-width:100%!important;margin:0!important;padding:0!important;height:31px!important;min-height:31px!important;max-height:31px!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__cta strong){color:var(--awa-text-primary,#111827)!important;line-height:32px!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta{font-weight:700!important;color:var(--awa-primary,var(--awa-red,currentColor))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-close{position:absolute!important;top:0!important;right:0!important;inset-block-start:0!important;inset-inline-end:0!important;width:40px!important;min-width:40px!important;max-width:40px!important;height:31px!important;min-height:31px!important;max-height:31px!important;border:0!important;border-radius:0!important;background:transparent!important;color:var(--awa-text-secondary,var(--awa-text,#111827))!important;font-size:16px!important;font-weight:600!important;line-height:31px!important;transform:none!important}'
            /* FIX-HOME-PROMO-GHOST-2026-07-05: o lock de 32px acima vence as regras de
               colapso do estado sticky (.awa-promo-bar--scrolled-away / .awa-header-condensed),
               deixando uma faixa invisível de 32px no fluxo do header (confirmado via
               H3 hiddenInFlow nos logs de runtime + CDP getMatchedStyles). Este bloco,
               com a MESMA especificidade de 7 IDs + classe de estado e declarado DEPOIS
               no mesmo stylesheet, colapsa promo bar e seu contêiner quando rolado. */
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #awa-b2b-promo-bar.awa-promo-bar--scrolled-away,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5).awa-header-is-sticky .awa-site-header #awa-b2b-promo-bar,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header.awa-header-condensed #awa-b2b-promo-bar{'
            + 'height:0!important;min-height:0!important;max-height:0!important;padding:0!important;margin:0!important;border:0!important;overflow:hidden!important;visibility:hidden!important;opacity:0!important;pointer-events:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5).awa-header-is-sticky .awa-site-header #header.header-container,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5).awa-header-is-sticky .awa-site-header #header.header-container .header-content,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header.awa-header-condensed #header.header-container,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header.awa-header-condensed #header.header-container .header-content{'
            + 'height:0!important;min-height:0!important;max-height:0!important;padding:0!important;margin:0!important;border:0!important;overflow:hidden!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky:not(.is-sticky){display:block!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;background:transparent!important;box-shadow:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky.is-sticky{box-sizing:border-box!important;padding-block-start:4px!important;padding-inline:max(16px,calc((100% - min(100%,1280px))/2))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header.awa-main-header{height:74px!important;min-height:74px!important;max-height:74px!important;padding:0!important;padding-block:0!important;padding-inline:0!important;margin:0!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border-bottom:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){height:74px!important;min-height:74px!important;max-height:74px!important;padding-block:0!important;box-sizing:border-box!important;overflow:visible!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header_main.awa-main-header-inner-wrap{border-block:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-main>.container{display:block!important;max-width:1280px!important;margin-inline:auto!important;padding-inline:16px!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-main-header__inner.wp-header{display:grid!important;grid-template-columns:minmax(132px,176px) minmax(420px,1fr) minmax(236px,300px)!important;grid-template-areas:\"brand search actions\"!important;align-items:center!important;column-gap:24px!important;padding-block:9px!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-primary-row{display:contents!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-brand-cell{grid-area:brand!important;align-self:center!important;height:56px!important;min-height:0!important;max-height:56px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-brand-cell .logo,html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-brand-cell .logo a{display:flex!important;align-items:center!important;height:56px!important;min-height:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-brand-cell .logo img{width:104px!important;height:44px!important;max-width:104px!important;max-height:44px!important;object-fit:contain!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-search-col{grid-area:search!important;align-self:center!important;height:56px!important;display:flex!important;align-items:center!important;min-width:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-search-col :is(.block-search,#search_mini_form,form.minisearch){width:100%!important;max-width:760px!important;margin-inline:auto!important;height:44px!important;min-height:44px!important;max-height:44px!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header form#search_mini_form.minisearch{background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;border-radius:var(--awa-radius-md,8px)!important;box-shadow:none!important;overflow:hidden!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header form#search_mini_form.minisearch :is(input,#search){background:transparent!important;box-shadow:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-right-col{grid-area:actions!important;justify-self:end!important;align-self:center!important;height:56px!important;min-height:0!important;max-height:56px!important;display:flex!important;align-items:center!important;gap:10px!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt{height:44px!important;min-height:44px!important;max-height:44px!important;padding:0 10px!important;align-items:center!important;grid-template-columns:20px minmax(0,1fr)!important;gap:8px!important;border-radius:8px!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;box-shadow:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__icon{width:20px!important;min-width:20px!important;padding:0!important;color:var(--awa-text-muted,var(--awa-text-secondary,CanvasText))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__guest{gap:2px!important;line-height:1.1!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__line1{font-size:11px!important;line-height:1.05!important;font-weight:600!important;color:var(--awa-text-muted,var(--awa-text-secondary,CanvasText))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__line2{font-size:13px!important;line-height:1.1!important;font-weight:600!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__link--login{font-size:13px!important;font-weight:700!important;padding:2px 4px!important;color:var(--awa-text,CanvasText)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__separator{font-size:10px!important;font-weight:500!important;padding-inline:1px!important;color:var(--awa-text-muted,var(--awa-text-secondary,CanvasText))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-account-prompt__link--register{font-size:13px!important;font-weight:700!important;padding:0!important;background:transparent!important;color:var(--awa-primary,var(--awa-red,currentColor))!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header :is(.minicart-wrapper,.awa-header-minicart,.action.showcart){height:44px!important;min-height:44px!important;max-height:44px!important;width:44px!important;min-width:44px!important;max-width:44px!important;align-self:center!important;display:inline-flex!important;align-items:center!important;justify-content:center!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header :is(.awa-header-minicart .action.showcart,.awa-minicart-trigger){border-radius:8px!important;box-shadow:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-control.header-nav.awa-nav-bar{height:48px!important;min-height:48px!important;max-height:48px!important;padding:0!important;margin:0!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border-block:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;border-inline:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-control.awa-nav-bar>.container{height:46px!important;min-height:46px!important;max-height:46px!important;max-width:1280px!important;margin-inline:auto!important;padding:0!important;box-sizing:border-box!important}html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-control.awa-nav-bar .awa-nav-bar__inner{height:46px!important;min-height:46px!important;max-height:46px!important;max-width:1280px!important;margin-inline:auto!important;padding:0 var(--awa-home-shell-gutter,16px)!important;box-sizing:border-box!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-control.awa-nav-bar .awa-nav-bar__inner{display:grid!important;grid-template-columns:206px minmax(0,1fr) auto!important;align-items:center!important;gap:24px!important;padding:0 var(--awa-home-shell-gutter,16px)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-header-categories.menu_left_home1{grid-column:1!important;width:206px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important;align-self:center!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-control.awa-nav-bar button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"],html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-control.awa-nav-bar .our_categories.title-category-dropdown{height:44px!important;min-height:44px!important;max-height:44px!important;border-radius:8px!important;box-shadow:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-nav-quick-links{grid-column:3!important;margin:0!important;justify-self:end!important;height:44px!important;min-height:44px!important;max-height:44px!important;align-items:center!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-nav-quick-links__list{height:44px!important;min-height:44px!important;max-height:44px!important;align-items:center!important;gap:24px!important;padding:0 var(--awa-home-shell-gutter,16px)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .awa-nav-quick-links__link{font-size:13px!important;font-weight:600!important;color:var(--awa-text,CanvasText)!important}'
            + '}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.page-wrapper .top-home-content--above-fold .wrapper_slider.visible-xs .banner_item_bg :is(picture,img){'
            + 'display:block!important;width:100%!important;height:auto!important;min-height:0!important;'
            + 'max-height:min(58vh,430px)!important;aspect-ratio:auto!important;object-fit:contain!important;'
            + 'object-position:center center!important;background:var(--awa-bg,Canvas)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer){'
            + 'background:var(--awa-bg-soft,var(--awa-bg,Canvas))!important;color:var(--awa-text,CanvasText)!important;min-height:0!important;height:auto!important;padding-block:0!important;overflow-x:clip!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            + ':is(#footer,.footer-container,.footer.content,.footer-top,.footer-middle,.footer-content,.row,.rowFlexMargin,.velaBlock,.velaContent,.awa-footer-newsletter){'
            + 'background:transparent!important;background-color:transparent!important;color:var(--awa-text,CanvasText)!important;min-height:0!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            + ':is(input,textarea,select,.control input){background:var(--awa-bg,Canvas)!important;color:var(--awa-text,CanvasText)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            + ':is(h2,h3,h4,.footer-title,.awa-footer-title){color:var(--awa-text,CanvasText)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            + ':is(a,p,li,span,.footer.links a,.footer-content a){color:var(--awa-text,CanvasText)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom{'
            + 'background:var(--awa-bg,Canvas)!important;color:var(--awa-text,CanvasText)!important;border-radius:12px!important;'
            + 'box-sizing:border-box!important;margin-inline:auto!important;width:min(100%,1248px)!important;max-width:calc(100% - 32px)!important;padding:clamp(16px,2.4vw,28px)!important;'
            + 'border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 14%,Canvas))!important;box-shadow:none!important;overflow:hidden!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.page-wrapper .top-home-content--category-carousel :is(.awa-section-header,.awa-category-carousel__header){'
            + 'display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;align-items:center!important;gap:12px!important}'
            + '@media(max-width:767px){'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] .awa-b2b-promo-bar__text{display:flex!important;align-items:center!important;justify-content:center!important;height:36px!important;margin:0 auto!important;max-width:calc(100% - 88px)!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;line-height:1.2!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] .awa-b2b-promo-bar__cta{display:inline-flex!important;align-items:center!important;justify-content:center!important;position:static!important;min-width:0!important;max-width:100%!important;height:36px!important;min-height:0!important;margin:0!important;padding:0!important;line-height:1.2!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] :is(.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__lead-short,.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__cta-long){display:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] .awa-b2b-promo-bar__cta-short{display:inline!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.awa-site-header :is(.awa-header-minicart,.minicart-wrapper,.minicart-wrapper .action.showcart){'
            + 'width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;'
            + 'min-height:44px!important;max-height:44px!important;display:inline-flex!important;align-items:center!important;'
            + 'justify-content:center!important;box-sizing:border-box!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.page-wrapper :is(.awa-owl-nav__btn,.awa-carousel__arrow,.awa-carousel__toggle){'
            + 'inline-size:44px!important;block-size:44px!important;min-inline-size:44px!important;min-block-size:44px!important;'
            + 'width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;'
            + 'display:inline-flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.page-wrapper .top-home-content--category-carousel :is(.awa-section-header,.awa-category-carousel__header){grid-template-columns:1fr!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            + '.page-wrapper .top-home-content--category-carousel :is(.awa-section-header__link,.awa-category-carousel__cta-link,.awa-shelf__view-all){'
            + 'justify-self:stretch!important;width:100%!important}}';

        style = document.getElementById('awa-post-audit-visual-terminal');
        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-post-audit-visual-terminal';
            document.body.appendChild(style);
        }

        style.textContent = css;
    }

    function getHeaderVtexFinalLockCss() {
        return ".js-mobile-menu-compact, #nav .owl-item, .header-content, .nav-sections, .awa-site-header .vm-icon{will-change:auto!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .header-wrapper-sticky{position:sticky!important;top:0!important;z-index:1000!important;contain:layout!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .header-wrapper-sticky,html body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] #header{height:156px!important;min-height:156px!important;max-height:156px!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;backdrop-filter:blur(2px)!important;border-bottom:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-main-header__inner.wp-header,.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-main-header__inner[data-awa-header-row]{display:grid!important;grid-template-columns:minmax(120px,176px) minmax(0,1fr) minmax(260px,max-content)!important;grid-template-areas:\"brand search actions\"!important;align-items:center!important;gap:12px!important;max-width:1280px!important;margin:0 auto!important;padding:0 16px!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-brand-cell{grid-area:brand!important;height:56px!important;max-height:56px!important;display:flex!important;align-items:center!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-search-col{grid-area:search!important;height:64px!important;min-height:64px!important;max-height:64px!important;display:flex!important;align-items:center!important;min-width:0!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-right-col{grid-area:actions!important;height:56px!important;max-height:56px!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:10px!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-brand-cell .logo img{width:104px!important;height:44px!important;max-width:104px!important;max-height:44px!important;object-fit:contain!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-search-col :is(form#search_mini_form,form.minisearch,form.search-content){display:flex!important;align-items:stretch!important;height:44px!important;min-height:44px!important;max-height:44px!important;border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;overflow:hidden!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;box-shadow:none!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-search-col :is(input#search,.input-text){height:44px!important;min-height:44px!important;max-height:44px!important;line-height:44px!important;border:0!important;border-radius:0!important;background:transparent!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-search-col :is(button.action.search,button.awa-search-btn,form#search_mini_form button.action.search,.actions .action.search,.block-search .action.search){width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important;border:0!important;border-radius:0!important;color:var(--awa-primary,#b73337)!important;background:transparent!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-right-col .action.showcart,.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-right-col .minicart-wrapper{width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important;border-radius:8px!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-account-prompt{height:44px!important;min-height:44px!important;max-height:44px!important;display:flex!important;align-items:center!important;padding:0 10px!important;gap:8px!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .header-control.awa-nav-bar{height:48px!important;min-height:48px!important;max-height:48px!important;padding:0 16px!important;margin:0!important;display:grid!important;grid-template-columns:206px minmax(0,1fr) auto!important;gap:24px!important;align-items:center!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border-block:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .header-control.awa-nav-bar .awa-nav-bar__inner{height:46px!important;min-height:46px!important;max-height:46px!important;max-width:1280px!important;margin:0 auto!important;display:grid!important;grid-template-columns:206px minmax(0,1fr) auto!important;align-items:center!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-categories.menu_left_home1{grid-column:1!important;height:44px!important;min-height:44px!important;max-height:44px!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-header-categories{width:206px!important;height:44px!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .awa-nav-bar__logo{display:none!important}.page-wrapper .awa-site-header[data-awa-header-mode=\"default\"] .header-control.awa-nav-bar button.our_categories.title-category-dropdown{height:44px!important;min-height:44px!important;max-height:44px!important;border-radius:8px!important;padding:0 12px!important;box-shadow:none!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border-color:var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important}";
    }

    function injectHeaderVtexFinalLock() {
        var style;
        style = document.getElementById("awa-header-impeccable-cascade-lock-v18");

        if (!style) {
            style = document.createElement("style");
            style.id = "awa-header-impeccable-cascade-lock-v18";
            document.body.appendChild(style);
        }

        style.textContent = getHeaderVtexFinalLockCss();
        if (document.body.classList) {
            document.body.classList.add("awa-header-lock-armed");
        }
    }

    function applyGatedCSS(triggerType) {
        var links;
        var i;
        var isHomeFallbackRun;

        if (applied) {
            return;
        }

        if (!triggerType) {
            triggerType = 'immediate';
        }

        isHomeFallbackRun = isHomePage()
            && triggerType !== 'interaction'
            && triggerType !== 'immediate';

        if (triggerType === 'interaction' || triggerType === 'immediate') {
            hasMeaningfulGateInteraction = true;
        }

        applied = true;

        injectHomeStabilityFix('awa-home-stability-gate-fix');
        injectHomeClsShell();

        if (document.documentElement) {
            document.documentElement.classList.add('awa-css-gate-applied');
            document.documentElement.classList.remove('awa-css-gate-pending');
        }

        links = document.querySelectorAll('link[' + CSS_GATE_ATTR + ']');

        for (i = 0; i < links.length; i += 1) {
            links[i].media = 'all';
        }

        injectQueuedStylesheets().then(function () {
            return injectHeaderRefineTerminal();
        }).then(function () {
            return injectImpeccableAuditTerminal();
        }).then(function () {
            return injectImpeccableRefineTerminal();
        }).then(function () {
            return injectHomeStandardizeTerminal();
        }).then(function () {
            return injectAlignGridTerminal();
        }).then(function () {
            if (isHomeFallbackRun && !hasMeaningfulGateInteraction) {
                homePostGateFixPending = true;
            } else {
                injectPostGateHeaderFix();
                injectHeaderVtexFinalLock();
                window.setTimeout(function () {
                    injectHeaderVtexFinalLock();
                }, 800);
                window.setTimeout(function () {
                    injectHeaderVtexFinalLock();
                }, 1800);
                homePostGateFixPending = false;
            }
            injectHomeCarouselCardTerminal();
            injectHeaderMobileGridTerminal();
            injectPostAuditVisualTerminal();
            try {
                document.dispatchEvent(new CustomEvent('awa:css-gate-applied', { bubbles: true }));
            } catch (e) { /* noop */ }
        });

        for (i = 0; i < GATE_EVENTS.length; i += 1) {
            window.removeEventListener(GATE_EVENTS[i], onGateInteraction, true);
        }
    }

    var i;

    window.__awaApplyGatedCSS = applyGatedCSS;

    if (document.documentElement && isHomePage()) {
        document.documentElement.classList.add('awa-css-gate-pending');
    }

    injectHomeFooterOverflowGuard();

    for (i = 0; i < GATE_EVENTS.length; i += 1) {
        window.addEventListener(GATE_EVENTS[i], onGateInteraction, {
            capture: true,
            passive: true
        });
    }

    function scheduleFallbackGate() {
        var run = function () {
            if (!applied) {
                applyGatedCSS('fallback');
            }
        };

        if (isHomePage()) {
            if (!HOME_AUTO_FALLBACK_ENABLED) {
                return;
            }

            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(run, { timeout: HOME_LCP_GATE_FALLBACK_MS });
            } else {
                window.setTimeout(run, HOME_LCP_GATE_FALLBACK_MS);
            }
            return;
        }

        if (isMobileViewport()) {
            window.setTimeout(run, MOBILE_FALLBACK_DELAY_MS);
            return;
        }

        window.setTimeout(run, DESKTOP_FALLBACK_DELAY_MS);
    }

    window.addEventListener('load', function () {
        scheduleFallbackGate();
    }, { once: true });

    if (window.__awaCssGateApplyImmediately) {
        window.setTimeout(function () {
            applyGatedCSS('immediate');
        }, 0);
    }
}());

/**
 * Busca — botão limpar (sync; awa-ux-enhancements deferido na home via AMD bootstrap).
 */
(function () {
    'use strict';

    function setSearchClearVisible(clearBtn, visible) {
        clearBtn.hidden = !visible;
        clearBtn.style.display = visible ? 'inline-flex' : 'none';
        clearBtn.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    function initSearchClear() {
        var searchInput = document.querySelector(
            '#search, .header-search input[type="text"], .block-search input.input-text'
        );
        if (!searchInput || document.getElementById('awa-search-clear')) {
            return;
        }

        var clearBtn = document.createElement('button');
        clearBtn.id = 'awa-search-clear';
        clearBtn.type = 'button';
        clearBtn.className = 'awa-search-clear-btn';
        clearBtn.setAttribute('aria-label', 'Limpar busca');
        clearBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';

        var control = searchInput.closest(
            '.control[data-awa-search-control], .field.search .control, .control'
        ) || searchInput.parentElement;

        if (control) {
            if (window.getComputedStyle(control).position === 'static') {
                control.style.position = 'relative';
            }
            control.appendChild(clearBtn);
        }

        setSearchClearVisible(clearBtn, false);
        searchInput.classList.add('awa-search-input--clearable');

        searchInput.addEventListener('input', function () {
            setSearchClearVisible(clearBtn, !!this.value);
        });
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            setSearchClearVisible(clearBtn, false);
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            searchInput.focus();
        });
    }

    function bootSearchClear() {
        initSearchClear();
    }

    if (document.readyState !== 'loading') {
        bootSearchClear();
    } else {
        document.addEventListener('DOMContentLoaded', bootSearchClear, { once: true });
    }

    window.setTimeout(bootSearchClear, 500);
    window.setTimeout(bootSearchClear, 2000);
}());

/**
 * Header mobile — faixa B2B compacta terminal para páginas fora da home.
 */
(function () {
    'use strict';

    function injectHeaderMobilePromoClean() {
        var style;
        var css;

        if (!document.querySelector('.awa-site-header .awa-b2b-promo-bar__text')) {
            return;
        }

        style = document.getElementById('awa-header-mobile-promo-clean-terminal');
        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-header-mobile-promo-clean-terminal';
            document.head.appendChild(style);
        }

        css = '@media(max-width:767px){'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] .awa-b2b-promo-bar__text{display:flex!important;align-items:center!important;justify-content:center!important;height:36px!important;margin:0 auto!important;max-width:calc(100% - 88px)!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;line-height:1.2!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] .awa-b2b-promo-bar__cta{display:inline-flex!important;align-items:center!important;justify-content:center!important;position:static!important;min-width:0!important;max-width:100%!important;height:36px!important;min-height:0!important;margin:0!important;padding:0!important;line-height:1.2!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] :is(.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__lead-short,.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__cta-long){display:none!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header[data-awa-header-mode=default] .awa-b2b-promo-bar__cta-short{display:inline!important}'
            + '}';

        style.textContent = css;
    }

    if (document.readyState !== 'loading') {
        injectHeaderMobilePromoClean();
    } else {
        document.addEventListener('DOMContentLoaded', injectHeaderMobilePromoClean, { once: true });
    }

    window.setTimeout(injectHeaderMobilePromoClean, 600);
    window.setTimeout(injectHeaderMobilePromoClean, 2500);
}());

/**
 * Patch 2026-07-02 — Contraste do footer vermelho (links/textos ilegíveis).
 *
 * Causa raiz: o footer (.page_footer/.page-footer) tem fundo vermelho fixo
 * (var(--awa-primary)), mas várias folhas de estilo legadas (assumindo um
 * footer claro/branco) e injeções anteriores deste mesmo gate competem entre
 * si definindo `color` para .velaFooterLinks a / .awa-newsletter-desc /
 * .awa-footer-atendimento p — algumas com tom escuro (#666/#475569), outras
 * já tentando um tom claro, mas perdendo o empate de especificidade por
 * posição no DOM. Como esta função é a última <style> anexada por este
 * arquivo (executa por último, na mesma ordem de carregamento), ela vence
 * qualquer empate de especificidade (#html-body x7) contra as demais.
 * Não removemos as regras antigas — apenas garantimos a cor final correta.
 */
(function () {
    'use strict';

    function injectFooterContrastTerminal() {
        var style = document.getElementById('awa-footer-contrast-terminal');
        var css;

        function isFooterDark() {
            var footer = document.querySelector('.page_footer, .page-footer');
            var color;
            var match;
            var lightness;
            var r;
            var g;
            var b;

            if (!footer) {
                return false;
            }

            color = window.getComputedStyle(footer).backgroundColor || '';
            match = color.match(/oklch\(\s*([0-9.]+)(%)?/i);
            if (match) {
                lightness = parseFloat(match[1]);
                if (match[2]) {
                    lightness /= 100;
                }

                return lightness < 0.62;
            }

            match = color.match(/rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)/i);
            if (match) {
                r = parseFloat(match[1]) / 255;
                g = parseFloat(match[2]) / 255;
                b = parseFloat(match[3]) / 255;

                return (0.2126 * r + 0.7152 * g + 0.0722 * b) < 0.5;
            }

            return footer.classList.contains('awa-footer--dark');
        }

        if (!isFooterDark()) {
            if (style) {
                style.textContent = '';
            }

            return;
        }

        if (!style) {
            style = document.createElement('style');
            style.id = 'awa-footer-contrast-terminal';
            document.head.appendChild(style);
        }

        /* A11Y-001 (2026-07-04, revisão Lighthouse desktop): rgba(255,255,255,.82)
         * sobre o vermelho efetivo do footer (~rgb(178,60,52)) mede ~4.46:1 —
         * abaixo do minimo AA de 4.5:1 para texto normal (falha por margem
         * mínima). .9 de opacidade eleva para ~5.0:1, com folga de segurança. */
        css = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) :is(.velaFooterLinks a,.awa-newsletter-desc,.awa-footer-atendimento p,.awa-footer-atendimento a[href^="tel"],.awa-footer-atendimento a[href^="mailto"]):not(.awa-footer-atendimento__store *){'
            + 'color:rgba(255,255,255,.9)!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) .awa-footer-section__toggle{'
            + 'color:#fff!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) :is(.awa-footer-atendimento a[href^="tel"],.awa-footer-atendimento a[href^="mailto"]):hover{'
            + 'color:#fff!important;text-decoration:underline!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) .velaFooterLinks a:hover,'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) .velaFooterLinks a:focus-visible{'
            + 'color:#fff!important;text-decoration:underline!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) :is(.awa-footer-atendimento__store,.awa-footer-atendimento__store p){'
            + 'color:#1a1a1a!important}'
            /* A11Y-001 (2026-07-04): .awa-footer-pay-sec__label/.awa-footer-muted-label e
             * .awa-footer-copyright__legal/__disclaimer tem a cor pretendida (#333/#666,
             * escura, sobre fundo claro da faixa de pagamento/copyright) sobrescrita por
             * tokens legados de _awa-consolidated.less (--awa-cons-c76/--awa-vfix-c1)
             * que resolvem para um tom quase branco — texto praticamente invisivel
             * (contraste ~1.05:1, WCAG exige 4.5:1). Mesmo mecanismo de reforco final
             * ja usado acima para o restante do footer. */
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) .footer-bottom :is(.awa-footer-muted-label,.awa-footer-pay-sec__label){'
            + 'color:#333333!important}'
            + 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            + ':is(.page_footer,.page-footer) .footer-bottom :is(.awa-footer-copyright__legal,.awa-footer-copyright__disclaimer){'
            + 'color:#666666!important}';

        style.textContent = css;

        /*
         * Reforço via estilo inline (!important): o footer deste tema acumula
         * dezenas de folhas concorrentes com cadeias #html-body de especificidade
         * variável e ordem de carga imprevisível (algumas assíncronas via
         * document.body.appendChild em Promises). Confirmado empiricamente (via
         * bisseção de document.styleSheets) que mesmo #html-body x8 nem sempre
         * vence em todos os elementos do footer (ex.: botões-acordeão mobile).
         * Estilo inline com prioridade "important" tem precedência garantida
         * sobre QUALQUER regra de folha de estilo externa, eliminando de vez
         * a disputa de cascade para este conjunto pontual de elementos.
         */
        function forceInlineContrast() {
            var footer = document.querySelector('.page_footer, .page-footer');
            if (!footer) {
                return;
            }
            /* A11Y-001: .awa-footer-section__toggle NAO entra nesta lista "muted"
             * (rgba(255,255,255,.82) ~3.9:1 de contraste, falha WCAG). Esse elemento
             * ja tem sua propria regra dedicada (#fff, ~5.4:1) na <style> acima; incluir
             * aqui sobrescrevia essa regra com um inline style de opacidade reduzida. */
            var lightEls = footer.querySelectorAll(
                '.velaFooterLinks a, .awa-newsletter-desc, ' +
                '.awa-footer-atendimento a[href^="tel"], .awa-footer-atendimento a[href^="mailto"]'
            );
            for (var i = 0; i < lightEls.length; i++) {
                if (lightEls[i].closest('.awa-footer-atendimento__store')) {
                    continue;
                }
                /* A11Y-001: .9 (nao .82) — ver justificativa de contraste na
                 * <style> injetada acima (mesmo par de cores, mesma regra). */
                lightEls[i].style.setProperty('color', 'rgba(255,255,255,.9)', 'important');
            }
            var darkEls = footer.querySelectorAll('.awa-footer-atendimento__store, .awa-footer-atendimento__store p');
            for (var j = 0; j < darkEls.length; j++) {
                darkEls[j].style.setProperty('color', '#1a1a1a', 'important');
            }
            /* A11Y-001 (2026-07-04, revisão pós-Lighthouse): a regra dedicada
             * ".awa-footer-section__toggle{color:#fff!important}" da <style>
             * acima nao vence no DOM real (auditado: cor efetiva permanece
             * #393130 sobre fundo efetivo #b23c34 — contraste 2.16, falha
             * WCAG). Reforço via inline style, que tem precedência garantida. */
            var toggleEls = footer.querySelectorAll('.awa-footer-section__toggle');
            for (var k = 0; k < toggleEls.length; k++) {
                toggleEls[k].style.setProperty('color', '#ffffff', 'important');
            }
            /* A11Y-001 (2026-07-04): .awa-footer-devby__label acumula a classe
             * .awa-footer-muted-label mas vive fora de .footer-bottom (esta em
             * .awa-footer-devby, secao irma), entao a regra CSS acima que exige
             * ".footer-bottom .awa-footer-muted-label" nao o alcança — cor
             * efetiva ficava quase branca sobre fundo quase branco (contraste
             * 1.02:1). Fix direto e independente de hierarquia via inline. */
            var devbyLabel = footer.querySelector('.awa-footer-devby__label');
            if (devbyLabel) {
                devbyLabel.style.setProperty('color', '#333333', 'important');
            }
        }

        forceInlineContrast();
        window.setTimeout(forceInlineContrast, 800);
        window.setTimeout(forceInlineContrast, 3200);
    }

    if (document.readyState !== 'loading') {
        injectFooterContrastTerminal();
    } else {
        document.addEventListener('DOMContentLoaded', injectFooterContrastTerminal, { once: true });
    }

    window.setTimeout(injectFooterContrastTerminal, 700);
    window.setTimeout(injectFooterContrastTerminal, 3000);
}());

/*
 * Fix terminal — ícone do carrinho (showcart) invisível (2026-07-02, corrigido 2026-07-07).
 *
 * Diagnóstico original (2026-07-02) estava incorreto: assumia que o botão
 * showcart tinha fundo vermelho sólido (background: var(--awa-primary)) e
 * que o ícone precisava ser branco para contrastar com ele. Confirmado por
 * runtime (getComputedStyle + getBoundingClientRect via CDP em 2026-07-07)
 * que o `.showcart`/`.awa-header-cart-fallback` real tem
 * `background-color: transparent` — NÃO existe fundo vermelho. O ícone SVG
 * usa `stroke="currentColor"` e herda corretamente `color: var(--awa-primary)`
 * (vermelho) da cascata CSS, o que já o torna visível sobre o fundo branco
 * da página. O reforço inline anterior forçava `color/stroke: #fff`, criando
 * branco sobre branco — o ícone ficava 100% invisível para o usuário final
 * (regressão introduzida pelo próprio "fix").
 *
 * Correção: reforçar a cor CORRETA (var(--awa-primary), vermelho AWA) via
 * inline important, em vez de branco — mantém o mesmo mecanismo de reforço
 * (inline important vence qualquer regra de stylesheet) mas com o valor
 * certo, garantindo visibilidade mesmo se algum agente externo ao CSSOM
 * documentado quebrar a herança de `color` nesta camada.
 */
(function () {
    'use strict';

    var ICON_SELECTOR = '.awa-site-header .awa-header-minicart .minicart-wrapper .action.showcart .awa-minicart-icon, ' +
        '.awa-site-header .awa-header-minicart .minicart-wrapper .action.showcart .awa-minicart-icon *, ' +
        '.awa-site-header .awa-header-cart-fallback__icon, ' +
        '.awa-site-header .awa-header-cart-fallback__icon *';

    function forceCartIconContrast() {
        var els = document.querySelectorAll(ICON_SELECTOR);
        for (var i = 0; i < els.length; i++) {
            els[i].style.setProperty('color', 'var(--awa-primary, #b73337)', 'important');
            els[i].style.setProperty('stroke', 'var(--awa-primary, #b73337)', 'important');
            if (els[i].tagName === 'circle') {
                els[i].style.setProperty('fill', 'var(--awa-primary, #b73337)', 'important');
            }
        }
    }

    if (document.readyState !== 'loading') {
        forceCartIconContrast();
    } else {
        document.addEventListener('DOMContentLoaded', forceCartIconContrast, { once: true });
    }

    window.setTimeout(forceCartIconContrast, 700);
    window.setTimeout(forceCartIconContrast, 1500);
    window.setTimeout(forceCartIconContrast, 3000);
    window.setTimeout(forceCartIconContrast, 6000);

    /* Minicart é re-renderizado por Knockout ao atualizar contagem/itens — reaplica. */
    if (window.jQuery) {
        window.jQuery(document).on('ajaxComplete cartUpdate', forceCartIconContrast);
    }
}());

/**
 * A11Y-001 (2026-07-04) — Contraste: aba ativa das prateleiras da home e botão
 * "Deptos." da nav inferior mobile.
 *
 * Causa raiz #1: `.awa-home-niche-shelves__tab.is-active` usa
 * `color: var(--awa-text-inverse, CanvasText)`, mas `--awa-text-inverse` está
 * "envenenado" em várias folhas legadas (_awa-consolidated.less e outras)
 * por tokens como --awa-cons-c78/--awa-sg-c61 que resolvem para preto/quase-
 * preto em vez de branco — texto preto sobre fundo vermelho (--awa-primary),
 * contraste ~1.4:1. Mesmo padrão já documentado acima no fix do ícone do
 * carrinho.
 *
 * Causa raiz #2: `button.toggle-nav-footer` é o único item da nav inferior
 * mobile com fundo próprio colorido (vermelho, quando ativo/padrão), mas
 * herda `color: var(--awa-text-secondary, #666666)` da regra genérica dos
 * irmãos `<a>` (que têm fundo transparente) — resultando em slate-600
 * (rgb(71,85,105)) sobre vermelho, contraste ~2.1:1.
 *
 * Reforço inline "important": mesmo mecanismo comprovado acima, pois ambos
 * os seletores perdem a disputa de cascade contra folhas assíncronas/tokens
 * quebrados fora do nosso controle direto nesta função.
 */
(function () {
    'use strict';

    /* A11Y-001 (2026-07-04, revisão pós-Lighthouse): regra <style> síncrona,
     * injetada assim que este script executa (sem esperar DOMContentLoaded).
     * O reforço 100% via JS abaixo (setProperty inline) só alcança o botão
     * depois de DOMContentLoaded + até 3s de timeouts — nesse intervalo (ou
     * caso ".is-active" seja atribuída por outro script assíncrono, ex.
     * awa-home-shelf-bootstrap, antes do primeiro forceHomeTabAndFooterToggleContrast
     * rodar) o Lighthouse/axe pode auditar o botão ainda com a cor "envenenada"
     * herdada de --awa-text-inverse. Uma regra CSS de verdade não tem essa
     * corrida: aplica no primeiro paint e continua valendo para qualquer
     * elemento que ganhe a classe depois, sem depender de nenhum timer.
     */
    (function injectHomeTabContrastStyle() {
        var style = document.getElementById('awa-home-tab-contrast-style');
        if (style) {
            return;
        }
        style = document.createElement('style');
        style.id = 'awa-home-tab-contrast-style';
        /* Causa raiz #3 (a pior): ".is-active" NAO vem no HTML server-side — é
         * adicionada por JS depois que a prateleira entra em viewport (gating
         * do PERF-002). O botão tem `transition: color .18s` (entre outras),
         * então no instante em que a classe chega, o texto ANIMA da cor
         * "inativa" até o branco — por ~180ms existe um frame com texto
         * escuro sobre fundo já vermelho. Auditores como axe-core/Lighthouse
         * podem amostrar exatamente esse frame de transição. `transition:
         * none` neutraliza a animação só para o estado ativo, aplicando a
         * cor final instantaneamente assim que a classe é adicionada. */
        style.textContent = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            + '.awa-home-niche-shelves__tab.is-active{color:#ffffff!important;transition:none!important}';
        (document.head || document.documentElement).appendChild(style);
    }());

    function forceHomeTabAndFooterToggleContrast() {
        document.querySelectorAll('.awa-home-niche-shelves__tab.is-active').forEach(function (tab) {
            tab.style.setProperty('color', '#ffffff', 'important');
        });

        document.querySelectorAll('nav.fixed-bottom button.toggle-nav-footer').forEach(function (btn) {
            var isExpanded = btn.getAttribute('aria-expanded') === 'true';
            btn.style.setProperty('color', isExpanded ? '#b73337' : '#ffffff', 'important');
            var icon = btn.querySelector('.icon');
            if (icon) {
                icon.style.setProperty('color', isExpanded ? '#b73337' : '#ffffff', 'important');
            }
        });
    }

    if (document.readyState !== 'loading') {
        forceHomeTabAndFooterToggleContrast();
    } else {
        document.addEventListener('DOMContentLoaded', forceHomeTabAndFooterToggleContrast, { once: true });
    }

    window.setTimeout(forceHomeTabAndFooterToggleContrast, 700);
    window.setTimeout(forceHomeTabAndFooterToggleContrast, 1500);
    window.setTimeout(forceHomeTabAndFooterToggleContrast, 3000);

    document.addEventListener('click', function (evt) {
        if (evt.target && evt.target.closest &&
            evt.target.closest('.awa-home-niche-shelves__tab, .toggle-nav-footer')) {
            window.setTimeout(forceHomeTabAndFooterToggleContrast, 30);
        }
    }, { capture: true, passive: true });
}());

/*
 * VISUAL-20260707: footer home/PLP e quickview PLP.
 *
 * O footer recebe regras concorrentes em inline head, bundles assíncronos e
 * este próprio gate. Algumas rotas ainda chegavam no fim do carregamento com o
 * container externo vermelho, embora os filhos já estivessem claros. Esta
 * camada aplica a decisão visual diretamente nos containers-alvo, depois das
 * folhas tardias, sem tocar no conteúdo nem nas rotas.
 */
(function () {
    'use strict';

    function isTargetPage() {
        return document.body && document.body.matches(
            '.catalog-category-view,.catalogsearch-result-index,.cms-index-index,.cms-home,.cms-homepage_ayo_home5'
        );
    }

    function setImportant(el, prop, value) {
        if (el) {
            el.style.setProperty(prop, value, 'important');
        }
    }

    function applyFooterShellFix() {
        if (!isTargetPage()) {
            return;
        }

        document.querySelectorAll('.page_footer, .page-footer').forEach(function (footer) {
            setImportant(footer, 'background', 'var(--awa-bg-soft,var(--awa-bg,Canvas))');
            setImportant(footer, 'background-color', 'var(--awa-bg-soft,var(--awa-bg,Canvas))');
            setImportant(footer, 'color', 'var(--awa-text,CanvasText)');
            setImportant(footer, 'height', 'auto');
            setImportant(footer, 'min-height', '0');
            setImportant(footer, 'padding-block', '0');
            setImportant(footer, 'overflow-x', 'clip');
            setImportant(footer, 'max-width', '100%');
        });

        document.querySelectorAll(
            '.page_footer #footer, .page-footer #footer, ' +
            '.page_footer .footer-container, .page-footer .footer-container'
        ).forEach(function (el) {
            setImportant(el, 'background', 'transparent');
            setImportant(el, 'background-color', 'transparent');
            setImportant(el, 'color', 'var(--awa-text,CanvasText)');
            setImportant(el, 'min-height', '0');
        });

        document.querySelectorAll('.page_footer .footer-bottom, .page-footer .footer-bottom').forEach(function (el) {
            setImportant(el, 'box-sizing', 'border-box');
            setImportant(el, 'background', 'var(--awa-bg,Canvas)');
            setImportant(el, 'background-color', 'var(--awa-bg,Canvas)');
            setImportant(el, 'color', 'var(--awa-text,CanvasText)');
            setImportant(el, 'margin-inline', 'auto');
            setImportant(el, 'max-width', 'calc(100% - 32px)');
            setImportant(el, 'width', 'min(100%,1248px)');
            setImportant(el, 'overflow', 'hidden');
            setImportant(el, 'box-shadow', 'none');
        });
    }

    function applyCatalogQuickviewFix() {
        if (!document.body || !document.body.matches('.catalog-category-view,.catalogsearch-result-index')) {
            return;
        }

        document.querySelectorAll('.products-grid .product-thumb').forEach(function (el) {
            setImportant(el, 'position', 'relative');
            setImportant(el, 'overflow', 'hidden');
        });

        document.querySelectorAll('.products-grid .quickview-link').forEach(function (el) {
            setImportant(el, 'box-sizing', 'border-box');
            setImportant(el, 'inline-size', '40px');
            setImportant(el, 'block-size', '40px');
            setImportant(el, 'max-width', '40px');
            setImportant(el, 'min-width', '0');
            setImportant(el, 'right', '0');
            setImportant(el, 'inset-inline-end', '0');
        });
    }

    function applyVisualBugfixTerminal() {
        applyFooterShellFix();
        applyCatalogQuickviewFix();
    }

    if (document.readyState !== 'loading') {
        applyVisualBugfixTerminal();
    } else {
        document.addEventListener('DOMContentLoaded', applyVisualBugfixTerminal, { once: true });
    }

    document.addEventListener('awa:css-gate-applied', applyVisualBugfixTerminal, { passive: true });
    window.addEventListener('load', applyVisualBugfixTerminal, { once: true, passive: true });
    window.setTimeout(applyVisualBugfixTerminal, 800);
    window.setTimeout(applyVisualBugfixTerminal, 2400);
    window.setTimeout(applyVisualBugfixTerminal, 5000);
}());
