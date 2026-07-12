/**
 * AWA Menu Controller v2 — vanilla runtime (Departamentos, flyout, mobile drawer, horizontal nav).
 *
 * @module awa-menu-controller
 */
define([
    'domReady!',
    'js/vendor/floating-ui.amd'
], function (domReady, FloatingUIDOM) {
    'use strict';

    var DESKTOP_MIN = 992;
    var PORTAL_CLASS = 'awa-vmf-portal';
    var ACTIVE_CLASS = 'awa-vmf-active';
    var DOC_BOOTED = false;
    var RUNTIME_STYLE_FIX_ID = 'awa-vmenu-runtime-fixes';
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    var AWA_DEBUG_ENDPOINT = 'http://localhost:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3';
    var AWA_DEBUG_ENDPOINT_IPV4 = 'http://127.0.0.1:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3';
    var AWA_DEBUG_SAME_ORIGIN_INGEST = '/b2b/account/login/';
    var AWA_DEBUG_SESSION = 'ca59a1';

    function emitServerBeacon(payload) {
        try {
            var dataText = '';
            try {
                dataText = encodeURIComponent(
                    JSON.stringify(payload.data || {}).slice(0, 240)
                );
            } catch (e) {}
            var qs = '?awa_dbg=1'
                + '&sid=' + encodeURIComponent(AWA_DEBUG_SESSION)
                + '&run=' + encodeURIComponent(payload.runId || '')
                + '&hyp=' + encodeURIComponent(payload.hypothesisId || '')
                + '&loc=' + encodeURIComponent(payload.location || '')
                + '&msg=' + encodeURIComponent(payload.message || '')
                + '&ts=' + encodeURIComponent(String(payload.timestamp || Date.now()))
                + '&data=' + dataText;
            var src = AWA_DEBUG_SAME_ORIGIN_INGEST + qs + '&transport=img';
            var img = new Image();
            img.src = src;
            if (navigator && typeof navigator.sendBeacon === 'function') {
                try {
                    navigator.sendBeacon(
                        AWA_DEBUG_SAME_ORIGIN_INGEST + qs + '&transport=sendbeacon',
                        JSON.stringify({
                            sessionId: payload.sessionId || '',
                            runId: payload.runId || '',
                            hypothesisId: payload.hypothesisId || '',
                            location: payload.location || '',
                            message: payload.message || '',
                            timestamp: payload.timestamp || Date.now()
                        })
                    );
                } catch (err) {}
            }
        } catch (e) {}
    }

    function sendDebugLog(runId, hypothesisId, location, message, data) {
        var payload = {
            sessionId: AWA_DEBUG_SESSION,
            runId: runId,
            hypothesisId: hypothesisId,
            location: location,
            message: message,
            data: data || {},
            timestamp: Date.now()
        };
        emitServerBeacon(payload);
        fetch(AWA_DEBUG_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': AWA_DEBUG_SESSION
            },
            body: JSON.stringify(payload)
        }).catch(function () {});
        fetch(AWA_DEBUG_ENDPOINT_IPV4, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': AWA_DEBUG_SESSION
            },
            body: JSON.stringify(payload)
        }).catch(function () {});
    }
    // #region agent log
    sendDebugLog(
        'pre-fix',
        'H0',
        'awa-menu-controller.js:init',
        'Module initialized',
        {
            href: window.location.href,
            userAgent: navigator.userAgent,
            fetchLooksNative: String(window.fetch).indexOf('[native code]') !== -1
        }
    );
    // #endregion
    // #region agent log
    sendDebugLog(
        'pre-fix',
        'H14',
        'awa-menu-controller.js:init:selectors',
        'Menu selector inventory on current route',
        {
            href: window.location.href,
            triggerCount: document.querySelectorAll('[data-role="awa-vertical-menu-trigger"]').length,
            panelCount: document.querySelectorAll('[data-role="awa-vertical-menu-panel"]').length,
            navCount: document.querySelectorAll('[data-role="awa-vertical-menu"], .navigation.verticalmenu.side-verticalmenu').length
        }
    );
    // #endregion
    // #region agent log
    (function installHomeMenuDeliveryProbe() {
        var panel = document.querySelector('[data-role="awa-vertical-menu-panel"]');
        var fold = document.querySelector('.content-top-home > .top-home-content--above-fold');
        var benefitsInner = document.querySelector('.awa-hero-b2b-cta__inner.container');
        var scriptResource = performance.getEntriesByType('resource').filter(function (entry) {
            return entry.name.indexOf('/js/awa-menu-controller.js') !== -1;
        }).pop();
        sendDebugLog(
            'home-menu-client-delivery',
            'H112',
            'awa-menu-controller.js:init:delivery',
            'Menu controller delivery and initial viewport identity',
            {
                controllerRevision: '20260711-home-column-v1',
                viewportWidth: window.innerWidth,
                clientWidth: document.documentElement.clientWidth,
                devicePixelRatio: window.devicePixelRatio,
                desktopMatch: isDesktop(),
                scriptUrl: scriptResource ? scriptResource.name : '',
                panelState: panel ? panel.getAttribute('data-awa-menu-state') || '' : '',
                panelDisplay: panel ? window.getComputedStyle(panel).display : '',
                panelClass: panel ? panel.className : ''
            }
        );

        if (!window.MutationObserver || !panel || !fold || !benefitsInner) {
            return;
        }
        var mutationCount = 0;
        var observer = new MutationObserver(function (records) {
            if (mutationCount >= 6) {
                observer.disconnect();
                return;
            }
            mutationCount += 1;
            window.requestAnimationFrame(function () {
                var panelRect = panel.getBoundingClientRect();
                var hero = document.querySelector('.awa-hero-swiper');
                var heroRect = hero ? hero.getBoundingClientRect() : null;
                sendDebugLog(
                    'home-menu-late-mutation',
                    'H113-H115',
                    'awa-menu-controller.js:mutation:home-composition',
                    'Late mutation affecting menu composition',
                    {
                        count: mutationCount,
                        changed: records.map(function (record) {
                            return {
                                target: record.target === panel
                                    ? 'panel'
                                    : (record.target === fold ? 'fold' : 'benefits'),
                                attribute: record.attributeName || ''
                            };
                        }).slice(0, 6),
                        viewportWidth: window.innerWidth,
                        desktopMatch: isDesktop(),
                        bodyActive: document.body.classList.contains('awa-home-menu-column-active'),
                        panelState: panel.getAttribute('data-awa-menu-state') || '',
                        panelDisplay: window.getComputedStyle(panel).display,
                        foldInline: fold.getAttribute('style') || '',
                        benefitsInline: benefitsInner.getAttribute('style') || '',
                        panelRight: Math.round(panelRect.right),
                        heroLeft: heroRect ? Math.round(heroRect.left) : null,
                        overlap: heroRect ? Math.max(0, Math.round(panelRect.right - heroRect.left)) : null
                    }
                );
            });
        });
        observer.observe(panel, {
            attributes: true,
            attributeFilter: ['class', 'style', 'aria-hidden', 'data-awa-menu-state']
        });
        observer.observe(fold, {
            attributes: true,
            attributeFilter: ['class', 'style']
        });
        observer.observe(benefitsInner, {
            attributes: true,
            attributeFilter: ['class', 'style']
        });
    }());
    // #endregion
    if (!window.__AWA_MENU_DEBUG_ERROR_HOOKED) {
        window.__AWA_MENU_DEBUG_ERROR_HOOKED = true;
        window.addEventListener('error', function (event) {
            if (window.__AWA_MENU_DEBUG_ERROR_SENT) {
                return;
            }
            window.__AWA_MENU_DEBUG_ERROR_SENT = true;
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H20',
                'awa-menu-controller.js:window:error',
                'First uncaught window error observed',
                {
                    href: window.location.href,
                    message: event && event.message ? String(event.message) : '',
                    source: event && event.filename ? String(event.filename) : '',
                    line: event && event.lineno ? Number(event.lineno) : 0,
                    col: event && event.colno ? Number(event.colno) : 0
                }
            );
            // #endregion
        }, true);
        window.addEventListener('unhandledrejection', function (event) {
            if (window.__AWA_MENU_DEBUG_REJECTION_SENT) {
                return;
            }
            window.__AWA_MENU_DEBUG_REJECTION_SENT = true;
            var reasonText = '';
            try {
                reasonText = event && event.reason ? String(event.reason) : '';
            } catch (err) {}
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H21',
                'awa-menu-controller.js:window:unhandledrejection',
                'First unhandled promise rejection observed',
                {
                    href: window.location.href,
                    reason: reasonText.slice(0, 180)
                }
            );
            // #endregion
        }, true);
    }

    function isDesktop() {
        return window.matchMedia
            ? window.matchMedia('(min-width: ' + DESKTOP_MIN + 'px)').matches
            : window.innerWidth >= DESKTOP_MIN;
    }

    function isMobile() {
        return !isDesktop();
    }

    function applyHomeMenuStyle(element, declarations) {
        if (!element) {
            return;
        }
        if (!element._awaHomeMenuPreviousStyles) {
            element._awaHomeMenuPreviousStyles = {};
        }
        Object.keys(declarations).forEach(function (property) {
            if (!Object.prototype.hasOwnProperty.call(element._awaHomeMenuPreviousStyles, property)) {
                element._awaHomeMenuPreviousStyles[property] = {
                    value: element.style.getPropertyValue(property),
                    priority: element.style.getPropertyPriority(property)
                };
            }
            element.style.setProperty(property, declarations[property], 'important');
        });
    }

    function restoreHomeMenuStyles(element) {
        if (!element || !element._awaHomeMenuPreviousStyles) {
            return;
        }
        Object.keys(element._awaHomeMenuPreviousStyles).forEach(function (property) {
            var previous = element._awaHomeMenuPreviousStyles[property];
            if (previous.value) {
                element.style.setProperty(property, previous.value, previous.priority);
            } else {
                element.style.removeProperty(property);
            }
        });
        element._awaHomeMenuPreviousStyles = null;
    }

    function syncHomeMenuComposition(open) {
        if (!document.body.matches('.cms-index-index, .cms-home, .cms-homepage_ayo_home5')) {
            return;
        }

        var fold = document.querySelector('.content-top-home > .top-home-content--above-fold');
        var banner = fold
            ? fold.querySelector(':scope > .banner-slider.banner-slider2')
            : null;
        var benefitsInner = document.querySelector('.awa-hero-b2b-cta__inner.container');
        var categorySection = document.querySelector('.top-home-content--category-carousel');
        var categoryRoot = document.querySelector('.awa-header-categories.menu_left_home1');
        var categoryNodes = categoryRoot
            ? [categoryRoot].concat(Array.prototype.slice.call(categoryRoot.querySelectorAll(
                '.awa-nav-categories, '
                + '.sections.nav-sections.category-dropdown, '
                + '.section-items.nav-sections.category-dropdown-items, '
                + '.section-item-content.nav-sections.category-dropdown-item-content, '
                + '.navigation.verticalmenu.side-verticalmenu, '
                + '[data-role="awa-vertical-menu-trigger"]'
            )))
            : [];
        var shouldReserveMenuColumn = open && isDesktop();

        if (!shouldReserveMenuColumn) {
            [fold, banner, benefitsInner, categorySection]
                .concat(categoryNodes)
                .forEach(restoreHomeMenuStyles);
            document.body.classList.remove('awa-home-menu-column-active');
            return;
        }

        applyHomeMenuStyle(fold, {
            'padding-inline-start': '336px',
            'padding-inline-end': '16px'
        });
        applyHomeMenuStyle(banner, {
            'width': '100%',
            'max-width': '100%',
            'margin-inline': '0'
        });
        applyHomeMenuStyle(benefitsInner, {
            'padding-inline-start': '320px',
            'padding-inline-end': '0'
        });
        applyHomeMenuStyle(categorySection, {
            'padding-block-start': '48px'
        });
        if (window.innerWidth >= 1024) {
            categoryNodes.forEach(function (element) {
                applyHomeMenuStyle(element, {
                    'width': '304px',
                    'min-width': '304px',
                    'max-width': '304px'
                });
            });
            applyHomeMenuStyle(categoryRoot, {
                'flex': '0 0 304px'
            });
        } else {
            categoryNodes.forEach(restoreHomeMenuStyles);
        }
        document.body.classList.add('awa-home-menu-column-active');
    }


    function rafThrottle(fn) {
        var scheduled = 0;
        return function () {
            var ctx = this;
            var args = arguments;
            if (scheduled) {
                return;
            }
            scheduled = window.requestAnimationFrame(function () {
                scheduled = 0;
                fn.apply(ctx, args);
            });
        };
    }

    function getFocusables(root) {
        if (!root) {
            return [];
        }
        return Array.prototype.slice.call(root.querySelectorAll(FOCUSABLE)).filter(function (el) {
            return el.offsetParent !== null || el === document.activeElement;
        });
    }

    function restorePortaledFocusState(root) {
        if (!root) {
            return 0;
        }

        var restored = 0;
        root.querySelectorAll('[data-awa-hidden-focus-sync="1"]').forEach(function (el) {
            var previousTabindex = el.getAttribute('data-awa-prev-tabindex');
            var previousAriaHidden = el.getAttribute('data-awa-prev-aria-hidden');

            if (previousTabindex === '') {
                el.removeAttribute('tabindex');
            } else if (previousTabindex !== null) {
                el.setAttribute('tabindex', previousTabindex);
            }

            if (previousAriaHidden === '') {
                el.removeAttribute('aria-hidden');
            } else if (previousAriaHidden !== null) {
                el.setAttribute('aria-hidden', previousAriaHidden);
            }

            if ('inert' in el) {
                el.inert = false;
            }
            el.removeAttribute('inert');
            el.removeAttribute('data-awa-hidden-focus-sync');
            el.removeAttribute('data-awa-prev-tabindex');
            el.removeAttribute('data-awa-prev-aria-hidden');
            restored += 1;
        });

        return restored;
    }

    function countDrawerLinks(root) {
        if (!root) {
            return 0;
        }
        return root.querySelectorAll(
            'a[href]:not([href="#"]), .ui-menu-item > a, .navigation__link, .level-top'
        ).length;
    }

    function resolveDrawerShell() {
        var candidates = [
            document.querySelector('[data-awa-nav-shell="true"]'),
            document.getElementById('awa-primary-navigation'),
            document.getElementById('awa-category-navigation'),
            document.querySelector('.section-items.nav-sections.category-dropdown-items.awa-header-primary-nav'),
            document.querySelector('.sections.nav-sections')
        ].filter(Boolean);

        var seen = [];
        candidates = candidates.filter(function (el) {
            if (seen.indexOf(el) !== -1) {
                return false;
            }
            seen.push(el);
            return true;
        });

        var best = null;
        var bestCount = -1;
        candidates.forEach(function (el) {
            var count = countDrawerLinks(el);
            if (count > bestCount) {
                bestCount = count;
                best = el;
            }
        });

        return best || candidates[0] || null;
    }

    function getDrawerTargets(shell) {
        shell = shell || resolveDrawerShell();
        if (!shell) {
            return [];
        }
        var targets = [shell];
        var primary = document.getElementById('awa-primary-navigation');
        var category = document.getElementById('awa-category-navigation');
        [primary, category].forEach(function (el) {
            if (!el || targets.indexOf(el) !== -1) {
                return;
            }
            if (countDrawerLinks(el) >= 3) {
                targets.push(el);
            }
        });
        return targets;
    }

    function drawerMotionEnabled() {
        return !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function setDrawerTransition(target) {
        if (!target) {
            return;
        }
        if (!drawerMotionEnabled()) {
            target.style.removeProperty('transition');
            return;
        }
        target.style.setProperty(
            'transition',
            'transform 280ms cubic-bezier(0.22, 1, 0.36, 1)',
            'important'
        );
    }

    function slideDrawerIn(target) {
        if (!target) {
            return;
        }
        setDrawerTransition(target);
        if (!drawerMotionEnabled()) {
            target.style.setProperty('transform', 'translateX(0)', 'important');
            return;
        }
        target.style.setProperty('transform', 'translateX(-105%)', 'important');
        void target.offsetWidth;
        target.style.setProperty('transform', 'translateX(0)', 'important');
    }

    function slideDrawerOut(target) {
        if (!target) {
            return;
        }
        setDrawerTransition(target);
        target.style.setProperty('transform', 'translateX(-105%)', 'important');
    }

    function unlockDrawerHosts(root) {
        if (!root) {
            return;
        }
        var hostSel = '.header-control.header-nav.awa-nav-bar, .header-control.awa-nav-bar, '
            + '.awa-header-categories, .awa-nav-categories, .sections.nav-sections, '
            + '.section-items.nav-sections';
        var node = root.parentElement;
        while (node && node !== document.body) {
            if (node.matches && node.matches(hostSel)) {
                node.style.setProperty('display', 'block', 'important');
                node.style.setProperty('visibility', 'visible', 'important');
                node.style.setProperty('opacity', '1', 'important');
                node.style.setProperty('overflow', 'visible', 'important');
                node.style.setProperty('height', 'auto', 'important');
                node.style.setProperty('max-height', 'none', 'important');
            }
            node = node.parentElement;
        }
    }

    function syncDrawerToggleAria(isOpen) {
        document.querySelectorAll(
            '[data-awa-nav-toggle="true"], .toggle-nav-footer, [data-action="toggle-nav"]'
        ).forEach(function (btn) {
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    function needsTypographyFix(node) {
        if (!node || !window.getComputedStyle) {
            return true;
        }

        return parseFloat(window.getComputedStyle(node).fontSize) < 12;
    }

    function applyMenuLinkTypography(root, enable) {
        if (!root) {
            return;
        }

        var linkProps = ['font-size', 'font-weight', 'line-height', 'color'];
        root.querySelectorAll('a.level-top, .navigation.custommenu li.level0 > a, .top-menu a.level-top').forEach(function (anchor) {
            if (enable) {
                if (!needsTypographyFix(anchor)) {
                    return;
                }
                anchor.style.setProperty('font-size', '13px', 'important');
                anchor.style.setProperty('font-weight', '500', 'important');
                anchor.style.setProperty('line-height', '1.35', 'important');
                anchor.style.setProperty('color', 'var(--awa-text-primary, #333333)', 'important');
            } else {
                linkProps.forEach(function (prop) {
                    anchor.style.removeProperty(prop);
                });
            }
        });

        root.querySelectorAll('.navigation__label').forEach(function (label) {
            if (enable) {
                if (!needsTypographyFix(label)) {
                    return;
                }
                label.style.setProperty('font-size', '13px', 'important');
                label.style.setProperty('font-weight', '500', 'important');
            } else {
                linkProps.forEach(function (prop) {
                    label.style.removeProperty(prop);
                });
            }
        });
    }

    function ensureRuntimeMenuStyles() {
        var existing = document.getElementById(RUNTIME_STYLE_FIX_ID);
        if (existing) {
            document.head.appendChild(existing);
            return;
        }

        var styleEl = document.createElement('style');
        styleEl.id = RUNTIME_STYLE_FIX_ID;
        styleEl.textContent = [
            '#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu > ul.togge-menu.list-category-dropdown > li.ui-menu-item.level0 {',
            '  box-sizing: border-box !important;',
            '  width: 100% !important;',
            '  height: var(--awa-vmenu-item-h, 48px) !important;',
            '  min-height: var(--awa-vmenu-item-h, 48px) !important;',
            '  margin: 0 !important;',
            '  padding: 0 !important;',
            '}',
            '#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu > ul.togge-menu.list-category-dropdown > li.ui-menu-item.level0 > a.level-top.navigation__link,',
            'body .page-wrapper .navigation.verticalmenu .togge-menu > li.ui-menu-item.level0 > a.level-top.navigation__link {',
            '  box-sizing: border-box !important;',
            '  width: 100% !important;',
            '  height: 100% !important;',
            '  min-height: var(--awa-vmenu-item-h, 48px) !important;',
            '  line-height: 1.3 !important;',
            '  display: flex !important;',
            '  align-items: center !important;',
            '}',
            '#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu > ul.togge-menu.list-category-dropdown > li.awa-vem-extra-li {',
            '  box-sizing: border-box !important;',
            '  width: 100% !important;',
            '  max-width: 100% !important;',
            '  overflow: hidden !important;',
            '}',
            '#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu > ul.togge-menu.list-category-dropdown > li.ui-menu-item.level0 > a.level-top.navigation__link::after,',
            'body .page-wrapper .navigation.verticalmenu .togge-menu > li.level0 > a.level-top::after,',
            'html body#html-body .page-wrapper .verticalmenu.navigation.side-verticalmenu li.level0.parent > a::after,',
            'html body#html-body .page-wrapper .verticalmenu.navigation.side-verticalmenu li.level0.navigation__item--parent > a::after,',
            'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header [data-role="awa-vertical-menu-panel"][data-awa-menu-state="open"] > li.ui-menu-item.level0:is(.parent, .navigation__item--parent) > a.level-top::after,',
            'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header [data-role="awa-vertical-menu-panel"][data-awa-menu-state="open"] > li.ui-menu-item.level0:is(.parent, .navigation__item--parent) > a.navigation__link::after,',
            'a.level-top.navigation__link.awa-vmenu-link-runtime::after {',
            '  content: none !important;',
            '  display: none !important;',
            '  width: 0 !important;',
            '  height: 0 !important;',
            '  float: none !important;',
            '  margin: 0 !important;',
            '  position: static !important;',
            '  font-size: 0 !important;',
            '  line-height: 0 !important;',
            '  color: transparent !important;',
            '  opacity: 0 !important;',
            '  overflow: hidden !important;',
            '}',
            '#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu > ul.togge-menu.list-category-dropdown > li.ui-menu-item.level0 > a.level-top.navigation__link:focus {',
            '  outline: none !important;',
            '}',
            '#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu > ul.togge-menu.list-category-dropdown > li.ui-menu-item.level0 > a.level-top.navigation__link:focus-visible,',
            'body .awa-vmf-portal .navigation__inner-item--level1.subcategory-second-level > a:focus-visible {',
            '  outline: 2px solid var(--awa-primary, #d9232e) !important;',
            '  outline-offset: 1px !important;',
            '  border-radius: 6px !important;',
            '}',
            'body .awa-vmf-portal .navigation__inner-item--level1.subcategory-second-level > a {',
            '  color: var(--awa-text-primary, #333333) !important;',
            '}',
            '@media (min-width: 992px) {',
            '  #html-body [data-role="awa-vertical-menu-panel"] > li.ui-menu-item.level0 > .submenu:not([data-awa-vmf-portaled="1"]),',
            '  #html-body [data-role="awa-vertical-menu-panel"] > li.ui-menu-item.level0 > .navigation__submenu:not([data-awa-vmf-portaled="1"]) {',
            '    display: none !important;',
            '  }',
            '  #html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu .open-children-toggle.navigation__toggle,',
            '  body .page-wrapper .navigation.verticalmenu .open-children-toggle.navigation__toggle {',
            '    display: none !important;',
            '    opacity: 0 !important;',
            '    pointer-events: none !important;',
            '    width: 0 !important;',
            '    height: 0 !important;',
            '    margin: 0 !important;',
            '    padding: 0 !important;',
            '    border: 0 !important;',
            '    background: transparent !important;',
            '    box-shadow: none !important;',
            '    outline: none !important;',
            '  }',
            '}'
        ].join('\n');
        document.head.appendChild(styleEl);
    }

    function applyTopLinkRuntimeFixes(root) {
        if (!root) {
            return;
        }
        root.style.setProperty('--awa-vmenu-item-h', '48px');
        root.querySelectorAll(':scope > li.ui-menu-item.level0').forEach(function (item) {
            item.style.setProperty('box-sizing', 'border-box', 'important');
            item.style.setProperty('width', '100%', 'important');
            item.style.setProperty('height', 'var(--awa-vmenu-item-h)', 'important');
            item.style.setProperty('min-height', 'var(--awa-vmenu-item-h)', 'important');
            item.style.setProperty('margin', '0', 'important');
            item.style.setProperty('padding', '0', 'important');
        });
        root.querySelectorAll(':scope > li.ui-menu-item.level0 > a.level-top.navigation__link').forEach(function (link) {
            link.classList.add('awa-vmenu-link-runtime');
            link.style.setProperty('box-sizing', 'border-box', 'important');
            link.style.setProperty('width', '100%', 'important');
            link.style.setProperty('height', '100%', 'important');
            link.style.setProperty('min-height', 'var(--awa-vmenu-item-h, 48px)', 'important');
            link.style.setProperty('line-height', '1.3', 'important');
        });
        root.querySelectorAll(':scope > li.awa-vem-extra-li').forEach(function (item) {
            item.style.setProperty('box-sizing', 'border-box', 'important');
            item.style.setProperty('width', '100%', 'important');
            item.style.setProperty('max-width', '100%', 'important');
            item.style.setProperty('overflow', 'hidden', 'important');
        });
        if (isDesktop()) {
            root.querySelectorAll(':scope > li.ui-menu-item.level0 > .open-children-toggle.navigation__toggle').forEach(function (btn) {
                btn.style.setProperty('display', 'none', 'important');
                btn.style.setProperty('opacity', '0', 'important');
                btn.style.setProperty('pointer-events', 'none', 'important');
                btn.style.setProperty('width', '0', 'important');
                btn.style.setProperty('height', '0', 'important');
                btn.style.setProperty('margin', '0', 'important');
                btn.style.setProperty('padding', '0', 'important');
                btn.style.setProperty('border', '0', 'important');
                btn.style.setProperty('background', 'transparent', 'important');
                btn.style.setProperty('outline', 'none', 'important');
            });
        }
        window.requestAnimationFrame(function () {
            var firstItem = root.querySelector(':scope > li.ui-menu-item.level0:not(.orther-link)');
            var firstLink = firstItem
                ? firstItem.querySelector(':scope > a.level-top.navigation__link')
                : null;
            var itemRect = firstItem ? firstItem.getBoundingClientRect() : null;
            var linkRect = firstLink ? firstLink.getBoundingClientRect() : null;
            // #region agent log
            sendDebugLog(
                'post-level0-box-fix',
                'H68-H71',
                'awa-menu-controller.js:applyTopLinkRuntimeFixes:box-model',
                'Level-zero box model and panel overflow after normalization',
                {
                    panelClientWidth: root.clientWidth,
                    panelScrollWidth: root.scrollWidth,
                    panelClientHeight: root.clientHeight,
                    panelScrollHeight: root.scrollHeight,
                    itemHeight: itemRect ? Math.round(itemRect.height) : null,
                    itemWidth: itemRect ? Math.round(itemRect.width) : null,
                    itemMarginRight: firstItem ? window.getComputedStyle(firstItem).marginRight : '',
                    itemPadding: firstItem ? window.getComputedStyle(firstItem).padding : '',
                    linkHeight: linkRect ? Math.round(linkRect.height) : null
                }
            );
            // #endregion
        });
    }

    /* ── FlyoutPortal ─────────────────────────────────────────────────── */
    function FlyoutPortal(root) {
        var self = this;
        this.root = root;
        this.portals = [];
        this._debugEnterCount = 0;
        this._debugAttachSkipCount = 0;
        this._onEnter = function (e) {
            var li = e.target.closest('li.level0.parent, li.level0.navigation__item--parent');
            if (li && self.root.contains(li)) {
                if (self._debugEnterCount < 3) {
                    self._debugEnterCount += 1;
                    // #region agent log
                    sendDebugLog(
                        'pre-fix',
                        'H23',
                        'awa-menu-controller.js:FlyoutPortal.onEnter',
                        'Flyout hover enter matched a parent level0 item',
                        {
                            href: window.location.href,
                            menuId: li.getAttribute('data-menu') || '',
                            liClassName: li.className,
                            submenuFound: !!self.findSubmenu(li),
                            rootClassName: self.root ? self.root.className : ''
                        }
                    );
                    // #endregion
                }
                self.attach(li);
            }
        };
        this._onOver = function (e) {
            var li = e.target && e.target.closest
                ? e.target.closest('li.level0.parent, li.level0.navigation__item--parent')
                : null;
            if (!li || !self.root.contains(li)) {
                return;
            }
            if (self._debugEnterCount < 6) {
                self._debugEnterCount += 1;
                var submenu = self.findSubmenu(li);
                var submenuStyle = submenu ? window.getComputedStyle(submenu) : null;
                // #region agent log
                sendDebugLog(
                    'pre-fix',
                    'H34',
                    'awa-menu-controller.js:FlyoutPortal.onOver',
                    'Mouseover fallback reached parent level0 candidate',
                    {
                        href: window.location.href,
                        menuId: li.getAttribute('data-menu') || '',
                        liClassName: li.className,
                        submenuFound: !!submenu,
                        submenuDisplay: submenuStyle ? submenuStyle.display : '',
                        submenuPosition: submenuStyle ? submenuStyle.position : '',
                        activePortalCount: document.querySelectorAll('.' + PORTAL_CLASS).length
                    }
                );
                // #endregion
            }
            self.attach(li);
        };
        this._onLeave = function (e) {
            var li = e.target.closest('li.level0');
            if (!li || !self.root.contains(li)) {
                return;
            }
            if (e.target !== li) {
                return;
            }
            var to = e.relatedTarget;
            if (to && li.contains(to)) {
                return;
            }
            if (to && to.closest && to.closest('.' + PORTAL_CLASS)) {
                return;
            }
            self.detach(li, 'li-leave');
        };
        this._reposition = rafThrottle(function () {
            if (!isDesktop()) {
                return;
            }
            document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (portal) {
                var id = portal.dataset.awVmfLiMenu;
                var li = id && self.root.querySelector('li.level0[data-menu="' + id + '"]');
                if (li) {
                    self.position(li, portal);
                }
            });
        });
    }

    FlyoutPortal.prototype.mount = function () {
        if (!this.root || this.root.dataset.awaFlyoutMounted === '1') {
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H24',
                'awa-menu-controller.js:FlyoutPortal.mount:skip',
                'Flyout mount skipped due missing/already mounted root',
                {
                    href: window.location.href,
                    hasRoot: !!this.root,
                    mountedFlag: this.root ? (this.root.dataset.awaFlyoutMounted || '') : ''
                }
            );
            // #endregion
            return;
        }
        this.root.dataset.awaFlyoutMounted = '1';
        this.root.addEventListener('mouseenter', this._onEnter, true);
        this.root.addEventListener('mouseover', this._onOver, true);
        this.root.addEventListener('mouseleave', this._onLeave, true);
        window.addEventListener('scroll', this._reposition, { passive: true });
        window.addEventListener('resize', this._reposition, { passive: true });
        // #region agent log
        sendDebugLog(
            'pre-fix',
            'H24',
            'awa-menu-controller.js:FlyoutPortal.mount:ready',
            'Flyout mount completed and listeners attached',
            {
                href: window.location.href,
                rootClassName: this.root.className || '',
                rootTagName: this.root.tagName || '',
                parentCount: this.root.querySelectorAll('li.level0.parent, li.level0.navigation__item--parent').length
            }
        );
        // #endregion
    };

    FlyoutPortal.prototype.findSubmenu = function (li) {
        return li.querySelector(':scope > .submenu, :scope > .level0.submenu, :scope > .navigation__submenu');
    };

    FlyoutPortal.prototype.applyLayoutFixes = function (portal) {
        if (!portal) {
            return;
        }

        ensureRuntimeMenuStyles();
        portal.style.setProperty('width', 'min(560px, calc(100vw - 32px))', 'important');
        portal.style.setProperty('min-width', 'min(520px, calc(100vw - 32px))', 'important');
        portal.style.setProperty('max-width', '560px', 'important');
        portal.style.setProperty('overflow-x', 'hidden', 'important');

        var row = portal.querySelector('.row');
        if (row) {
            row.style.setProperty('display', 'block', 'important');
            row.style.setProperty('width', '100%', 'important');
            row.style.setProperty('max-width', '100%', 'important');
            row.style.setProperty('margin', '0', 'important');
        }

        var lists = portal.querySelectorAll('.navigation__inner-list--level1, .subchildmenu.mega-columns');
        lists.forEach(function (list) {
            list.style.setProperty('display', 'grid', 'important');
            list.style.setProperty('grid-template-columns', 'minmax(96px, 1fr) minmax(124px, 140px)', 'important');
            list.style.setProperty('column-gap', '16px', 'important');
            list.style.setProperty('row-gap', '8px', 'important');
            list.style.setProperty('padding', '16px 12px 14px 16px', 'important');
            list.style.setProperty('width', '100%', 'important');
            list.style.setProperty('max-width', '100%', 'important');
            list.style.setProperty('margin', '0', 'important');
        });

        var textItems = portal.querySelectorAll(
            '.navigation__inner-item--level1.subcategory-title, .navigation__inner-item--level1.subcategory-second-level, .navigation__inner-item--all'
        );
        textItems.forEach(function (item) {
            item.style.setProperty('grid-column', '1', 'important');
            item.style.setProperty('display', 'block', 'important');
            item.style.setProperty('width', '100%', 'important');
            item.style.setProperty('max-width', '100%', 'important');
            item.style.setProperty('min-width', '0', 'important');
        });

        var imageItems = portal.querySelectorAll('.navigation__inner-item--level1.imagem.img-subcategory, .navigation__inner-item--level1.imagem');
        imageItems.forEach(function (item) {
            item.style.setProperty('grid-column', '2', 'important');
            item.style.setProperty('grid-row', '1 / span 10', 'important');
            item.style.setProperty('width', '140px', 'important');
            item.style.setProperty('min-width', '124px', 'important');
            item.style.setProperty('max-width', '140px', 'important');
            item.style.setProperty('margin', '0', 'important');
            item.style.setProperty('padding', '0', 'important');
            item.style.setProperty('align-self', 'start', 'important');
            item.style.setProperty('max-height', '280px', 'important');
            item.style.setProperty('overflow', 'hidden', 'important');
            var image = item.querySelector('img');
            if (image) {
                image.style.setProperty('display', 'block', 'important');
                image.style.setProperty('width', '100%', 'important');
                image.style.setProperty('height', 'auto', 'important');
                image.style.setProperty('max-height', '280px', 'important');
                image.style.setProperty('object-fit', 'contain', 'important');
                image.style.setProperty('object-position', 'top center', 'important');
            }
        });
    };

    FlyoutPortal.prototype.attach = function (li) {
        if (!isDesktop()) {
            if (this._debugAttachSkipCount < 2) {
                this._debugAttachSkipCount += 1;
                // #region agent log
                sendDebugLog(
                    'pre-fix',
                    'H25',
                    'awa-menu-controller.js:attach:skip-mobile',
                    'Flyout attach aborted because viewport is not desktop',
                    {
                        href: window.location.href,
                        viewportW: window.innerWidth,
                        viewportH: window.innerHeight
                    }
                );
                // #endregion
            }
            return;
        }
        var submenu = this.findSubmenu(li);
        if (!submenu || submenu.dataset.awVmfPortaled === '1') {
            if (this._debugAttachSkipCount < 4) {
                this._debugAttachSkipCount += 1;
                // #region agent log
                sendDebugLog(
                    'pre-fix',
                    'H26',
                    'awa-menu-controller.js:attach:skip-no-submenu',
                    'Flyout attach aborted due submenu missing or already portaled',
                    {
                        href: window.location.href,
                        menuId: li ? (li.getAttribute('data-menu') || '') : '',
                        submenuFound: !!submenu,
                        submenuPortaled: submenu ? (submenu.dataset.awVmfPortaled || '') : ''
                    }
                );
                // #endregion
            }
            return;
        }
        var portal = document.createElement('div');
        portal.className = PORTAL_CLASS;
        portal.dataset.awVmfLiMenu = li.getAttribute('data-menu') || '';
        portal.appendChild(submenu);
        document.body.appendChild(portal);
        var restoredFocusNodes = restorePortaledFocusState(portal);
        this.applyLayoutFixes(portal);
        submenu.dataset.awVmfPortaled = '1';
        li.classList.add(ACTIVE_CLASS);
        li.setAttribute('data-awa-submenu-open', 'true');
        var self = this;
        var autoUpdateCount = 0;
        if (FloatingUIDOM && typeof FloatingUIDOM.autoUpdate === 'function') {
            portal._awaCleanupAutoUpdate = FloatingUIDOM.autoUpdate(
                li,
                portal,
                function () {
                    if (autoUpdateCount < 4) {
                        autoUpdateCount += 1;
                        var panelRect = self.root.getBoundingClientRect();
                        var portalRect = portal.getBoundingClientRect();
                        // #region agent log
                        sendDebugLog(
                            'post-layout-shift-fix',
                            'H67',
                            'awa-menu-controller.js:attach:auto-update',
                            'Floating UI autoUpdate requested flyout reposition',
                            {
                                updateCount: autoUpdateCount,
                                panelTop: Math.round(panelRect.top),
                                portalTop: Math.round(portalRect.top),
                                deltaBeforeUpdate: Math.round(panelRect.top - portalRect.top),
                                viewportW: window.innerWidth,
                                viewportH: window.innerHeight
                            }
                        );
                        // #endregion
                    }
                    self.position(li, portal);
                },
                {
                    ancestorScroll: true,
                    ancestorResize: true,
                    elementResize: true,
                    layoutShift: true
                }
            );
        } else {
            this.position(li, portal);
        }
        this.portals.push(portal);

        // #region agent log
        sendDebugLog(
            'pre-fix',
            'H1',
            'awa-menu-controller.js:attach',
            'Flyout attach classes and structural context',
            {
                liMenu: li.getAttribute('data-menu') || '',
                portalClassName: portal.className,
                submenuClassName: submenu.className,
                submenuPortaled: submenu.dataset.awVmfPortaled || '',
                panelState: this.root && this.root.getAttribute('data-awa-menu-state'),
                panelAriaHidden: this.root && this.root.getAttribute('aria-hidden')
            }
        );
        // #endregion

        var active = document.activeElement;
        if (active && li.contains(active)) {
            var firstSubLink = portal.querySelector('a[href]');
            if (firstSubLink) {
                window.requestAnimationFrame(function () {
                    firstSubLink.focus();
                });
            }
        }
        var firstRestoredLink = portal.querySelector('a[href]');
        // #region agent log
        sendDebugLog(
            'post-fix',
            'H52',
            'awa-menu-controller.js:attach:focus-restore',
            'Portaled flyout hidden-focus state restored',
            {
                menuId: li.getAttribute('data-menu') || '',
                restored: restoredFocusNodes,
                inert: firstRestoredLink ? firstRestoredLink.hasAttribute('inert') : null,
                tabindex: firstRestoredLink ? firstRestoredLink.getAttribute('tabindex') : null,
                ariaHidden: firstRestoredLink ? firstRestoredLink.getAttribute('aria-hidden') : null,
                owned: firstRestoredLink ? firstRestoredLink.getAttribute('data-awa-hidden-focus-sync') : null
            }
        );
        // #endregion

        this.bindPortalKeyboard(portal, li);

        window.requestAnimationFrame(function () {
            this.applyLayoutFixes(portal);
            var firstSubchild = portal.querySelector('.subchildmenu, .navigation__inner-list--level1');
            var firstLink = portal.querySelector('.subchildmenu a, .navigation__inner-list--level1 a');
            var imageNode = portal.querySelector('.imagem.img-subcategory, .navigation__inner-item--level1.imagem');
            var rowNode = portal.querySelector('.row');
            var firstLinkRect = firstLink ? firstLink.getBoundingClientRect() : null;
            var imageRect = imageNode ? imageNode.getBoundingClientRect() : null;
            var overlap = false;
            if (firstLinkRect && imageRect) {
                overlap = !(firstLinkRect.right <= imageRect.left
                    || firstLinkRect.left >= imageRect.right
                    || firstLinkRect.bottom <= imageRect.top
                    || firstLinkRect.top >= imageRect.bottom);
            }
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H2-H3',
                'awa-menu-controller.js:attach:raf',
                'Flyout computed layout snapshot',
                {
                    portalWidth: Math.round(portal.getBoundingClientRect().width),
                    portalHeight: Math.round(portal.getBoundingClientRect().height),
                    portalMinWidth: window.getComputedStyle(portal).minWidth,
                    portalMaxWidth: window.getComputedStyle(portal).maxWidth,
                    portalOverflowY: window.getComputedStyle(portal).overflowY,
                    submenuDisplay: submenu ? window.getComputedStyle(submenu).display : '',
                    submenuPosition: submenu ? window.getComputedStyle(submenu).position : '',
                    subchildDisplay: firstSubchild ? window.getComputedStyle(firstSubchild).display : '',
                    subchildFloat: firstSubchild ? window.getComputedStyle(firstSubchild).float : '',
                    subchildGrid: firstSubchild ? window.getComputedStyle(firstSubchild).gridTemplateColumns : '',
                    rowWidth: rowNode ? Math.round(rowNode.getBoundingClientRect().width) : 0,
                    listWidth: firstSubchild ? Math.round(firstSubchild.getBoundingClientRect().width) : 0,
                    imagePosition: imageNode ? window.getComputedStyle(imageNode).position : '',
                    imageFloat: imageNode ? window.getComputedStyle(imageNode).float : '',
                    imageWidth: imageNode ? window.getComputedStyle(imageNode).width : '',
                    textImageOverlap: overlap
                }
            );
            // #endregion
            var portalRect = portal.getBoundingClientRect();
            var submenuRect = submenu ? submenu.getBoundingClientRect() : null;
            var textNode = portal.querySelector(
                '.navigation__inner-item--level1.subcategory-title, '
                + '.navigation__inner-item--level1.subcategory-second-level, '
                + '.navigation__inner-item--all'
            );
            var textRect = textNode ? textNode.getBoundingClientRect() : null;
            var textLink = textNode ? textNode.querySelector('a') : firstLink;
            var textStyle = textLink ? window.getComputedStyle(textLink) : null;
            var imageStyle = imageNode ? window.getComputedStyle(imageNode) : null;
            var imageElement = imageNode ? imageNode.querySelector('img') : null;
            var imageElementStyle = imageElement ? window.getComputedStyle(imageElement) : null;
            var level0Links = this.root
                ? this.root.querySelectorAll(':scope > li.ui-menu-item.level0 > a.level-top.navigation__link')
                : [];
            var minLevel0Height = 0;
            var maxLevel0Height = 0;
            Array.prototype.forEach.call(level0Links, function (link) {
                var height = Math.round(link.getBoundingClientRect().height);
                if (!height) {
                    return;
                }
                minLevel0Height = minLevel0Height ? Math.min(minLevel0Height, height) : height;
                maxLevel0Height = Math.max(maxLevel0Height, height);
            });
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H44',
                'awa-menu-controller.js:attach:geometry',
                'Rendered flyout geometry after layout fixes',
                {
                    pw: Math.round(portalRect.width),
                    ph: Math.round(portalRect.height),
                    sw: submenuRect ? Math.round(submenuRect.width) : 0,
                    tw: textRect ? Math.round(textRect.width) : 0,
                    tx2: textRect ? Math.round(textRect.right) : 0,
                    ix1: imageRect ? Math.round(imageRect.left) : 0,
                    iw: imageRect ? Math.round(imageRect.width) : 0,
                    ov: overlap
                }
            );
            // #endregion
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H45',
                'awa-menu-controller.js:attach:typography',
                'Rendered flyout text readability properties',
                {
                    fs: textStyle ? textStyle.fontSize : '',
                    lh: textStyle ? textStyle.lineHeight : '',
                    c: textStyle ? textStyle.color : '',
                    bg: textStyle ? textStyle.backgroundColor : '',
                    op: textStyle ? textStyle.opacity : '',
                    vis: textStyle ? textStyle.visibility : '',
                    td: textStyle ? textStyle.textDecorationLine : ''
                }
            );
            // #endregion
            var titleSpan = portal.querySelector(
                '.navigation__inner-item--level1.subcategory-title span'
            );
            var secondLevelLink = portal.querySelector(
                '.navigation__inner-item--level1.subcategory-second-level > a'
            );
            var titleStyle = titleSpan ? window.getComputedStyle(titleSpan) : null;
            var secondLevelStyle = secondLevelLink ? window.getComputedStyle(secondLevelLink) : null;
            var portalStyle = window.getComputedStyle(portal);
            var submenuStyle = submenu ? window.getComputedStyle(submenu) : null;
            var beforeStyle = secondLevelLink
                ? window.getComputedStyle(secondLevelLink, '::before')
                : null;
            var afterStyle = secondLevelLink
                ? window.getComputedStyle(secondLevelLink, '::after')
                : null;
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H48',
                'awa-menu-controller.js:attach:title-style',
                'Actual flyout title span computed typography',
                {
                    found: !!titleSpan,
                    txt: titleSpan ? String(titleSpan.textContent || '').trim().slice(0, 40) : '',
                    fs: titleStyle ? titleStyle.fontSize : '',
                    fw: titleStyle ? titleStyle.fontWeight : '',
                    lh: titleStyle ? titleStyle.lineHeight : '',
                    c: titleStyle ? titleStyle.color : '',
                    op: titleStyle ? titleStyle.opacity : '',
                    vis: titleStyle ? titleStyle.visibility : ''
                }
            );
            // #endregion
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H49',
                'awa-menu-controller.js:attach:link-style',
                'Actual flyout second-level link computed typography',
                {
                    found: !!secondLevelLink,
                    txt: secondLevelLink ? String(secondLevelLink.textContent || '').trim().slice(0, 40) : '',
                    fs: secondLevelStyle ? secondLevelStyle.fontSize : '',
                    fw: secondLevelStyle ? secondLevelStyle.fontWeight : '',
                    lh: secondLevelStyle ? secondLevelStyle.lineHeight : '',
                    c: secondLevelStyle ? secondLevelStyle.color : '',
                    bg: secondLevelStyle ? secondLevelStyle.backgroundColor : '',
                    op: secondLevelStyle ? secondLevelStyle.opacity : ''
                }
            );
            // #endregion
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H50',
                'awa-menu-controller.js:attach:surface-style',
                'Flyout parent surface and compositing properties',
                {
                    pbg: portalStyle.backgroundColor,
                    pop: portalStyle.opacity,
                    pfl: portalStyle.filter,
                    sbg: submenuStyle ? submenuStyle.backgroundColor : '',
                    sop: submenuStyle ? submenuStyle.opacity : '',
                    svis: submenuStyle ? submenuStyle.visibility : '',
                    mix: submenuStyle ? submenuStyle.mixBlendMode : ''
                }
            );
            // #endregion
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H51',
                'awa-menu-controller.js:attach:link-pseudo',
                'Flyout second-level pseudo-element traces',
                {
                    bc: beforeStyle ? beforeStyle.content : '',
                    bd: beforeStyle ? beforeStyle.display : '',
                    bw: beforeStyle ? beforeStyle.width : '',
                    bco: beforeStyle ? beforeStyle.color : '',
                    ac: afterStyle ? afterStyle.content : '',
                    ad: afterStyle ? afterStyle.display : '',
                    aw: afterStyle ? afterStyle.width : ''
                }
            );
            // #endregion
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H46',
                'awa-menu-controller.js:attach:overflow',
                'Rendered flyout overflow and scrollbar properties',
                {
                    cw: portal.clientWidth,
                    sw: portal.scrollWidth,
                    ch: portal.clientHeight,
                    sh: portal.scrollHeight,
                    ox: window.getComputedStyle(portal).overflowX,
                    oy: window.getComputedStyle(portal).overflowY,
                    sg: window.getComputedStyle(portal).scrollbarGutter
                }
            );
            // #endregion
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H47',
                'awa-menu-controller.js:attach:image-items',
                'Rendered image and menu item consistency properties',
                {
                    ip: imageStyle ? imageStyle.position : '',
                    id: imageStyle ? imageStyle.display : '',
                    iw: imageRect ? Math.round(imageRect.width) : 0,
                    ih: imageRect ? Math.round(imageRect.height) : 0,
                    ew: imageElement ? Math.round(imageElement.getBoundingClientRect().width) : 0,
                    eh: imageElement ? Math.round(imageElement.getBoundingClientRect().height) : 0,
                    fit: imageElementStyle ? imageElementStyle.objectFit : '',
                    lmin: minLevel0Height,
                    lmax: maxLevel0Height
                }
            );
            // #endregion
            var nestedList = portal.querySelector('.navigation__inner-list--level2, .subchildmenu[data-level="2"]');
            var nestedListStyle = nestedList ? window.getComputedStyle(nestedList) : null;
            var activeLink = getItemLink(li);
            var activeLinkStyle = activeLink ? window.getComputedStyle(activeLink) : null;
            var activeLinkBefore = activeLink ? window.getComputedStyle(activeLink, '::before') : null;
            var visualAuditData = {
                menuId: li.getAttribute('data-menu') || '',
                portalTop: Math.round(portalRect.top),
                portalHeight: Math.round(portalRect.height),
                portalClientHeight: portal.clientHeight,
                portalScrollHeight: portal.scrollHeight,
                portalOverflowY: portalStyle.overflowY,
                nestedFound: !!nestedList,
                nestedDisplay: nestedListStyle ? nestedListStyle.display : '',
                nestedHeight: nestedList ? Math.round(nestedList.getBoundingClientRect().height) : 0,
                imageFound: !!imageElement,
                imageComplete: imageElement ? imageElement.complete : null,
                imageNaturalWidth: imageElement ? imageElement.naturalWidth : 0,
                imageNaturalHeight: imageElement ? imageElement.naturalHeight : 0,
                level1Color: secondLevelStyle ? secondLevelStyle.color : '',
                level1Class: secondLevelLink && secondLevelLink.parentElement
                    ? secondLevelLink.parentElement.className
                    : '',
                activeBorderTop: activeLinkStyle ? activeLinkStyle.borderTop : '',
                activeOutline: activeLinkStyle ? activeLinkStyle.outline : '',
                activeBeforeDisplay: activeLinkBefore ? activeLinkBefore.display : '',
                activeBeforeColor: activeLinkBefore ? activeLinkBefore.backgroundColor : ''
            };
            // #region agent log
            fetch('http://localhost:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'ca59a1'},body:JSON.stringify({sessionId:'ca59a1',runId:'visual-audit',hypothesisId:'H55-H59',location:'awa-menu-controller.js:attach:visual-audit',message:'Flyout visual defect evidence',data:visualAuditData,timestamp:Date.now()})}).catch(function(){});
            sendDebugLog(
                'visual-audit',
                'H55-H59',
                'awa-menu-controller.js:attach:visual-audit',
                'Flyout visual defect evidence',
                visualAuditData
            );
            // #endregion
        }.bind(this));
    };

    FlyoutPortal.prototype.bindPortalKeyboard = function (portal, li) {
        var self = this;
        var handler = function (e) {
            var links = Array.prototype.slice.call(portal.querySelectorAll('a[href]')).filter(function (anchor) {
                var href = anchor.getAttribute('href');
                return href && href !== '#';
            });
            var idx = links.indexOf(document.activeElement);

            if (e.key === 'ArrowDown' && idx >= 0 && links[idx + 1]) {
                e.preventDefault();
                links[idx + 1].focus();
                return;
            }

            if (e.key === 'ArrowUp' && idx > 0 && links[idx - 1]) {
                e.preventDefault();
                links[idx - 1].focus();
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                self.detach(li, 'portal-escape');
                var parentLink = getItemLink(li);
                if (parentLink) {
                    parentLink.focus();
                }
            }
        };

        portal._awaKeyHandler = handler;
        portal.addEventListener('keydown', handler);
    };

    FlyoutPortal.prototype.unbindPortalKeyboard = function (portal) {
        if (portal && portal._awaKeyHandler) {
            portal.removeEventListener('keydown', portal._awaKeyHandler);
            portal._awaKeyHandler = null;
        }
    };

    FlyoutPortal.prototype.position = function (li, portal) {
        if (!FloatingUIDOM || typeof FloatingUIDOM.computePosition !== 'function') {
            portal.style.position = 'fixed';
            portal.style.zIndex = '99990';
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H4',
                'awa-menu-controller.js:position:fallback',
                'Floating UI unavailable fallback positioning',
                {
                    floatingUiAvailable: false
                }
            );
            // #endregion
            return;
        }
        var panelElement = this.root;
        FloatingUIDOM.computePosition(li, portal, {
            placement: 'right-start',
            strategy: 'fixed',
            middleware: [
                FloatingUIDOM.offset({ mainAxis: 0, crossAxis: 0 }),
                FloatingUIDOM.flip(),
                FloatingUIDOM.shift({ padding: 8 })
            ]
        }).then(function (data) {
            var panelRect = panelElement ? panelElement.getBoundingClientRect() : null;
            var liRect = li.getBoundingClientRect();
            var panelTop = panelRect ? Math.max(8, panelRect.top) : Math.max(8, data.y);
            var availableHeight = Math.max(160, window.innerHeight - panelTop - 8);
            portal.style.setProperty(
                'max-height',
                Math.min(640, Math.floor(availableHeight)) + 'px',
                'important'
            );
            var maxTop = Math.max(8, window.innerHeight - portal.offsetHeight - 8);
            var anchoredTop = panelRect
                ? Math.min(Math.max(8, panelRect.top), maxTop)
                : Math.min(Math.max(8, data.y), maxTop);
            var maxLeft = Math.max(8, window.innerWidth - portal.offsetWidth - 8);
            var anchoredLeft = Math.min(
                Math.max(8, panelRect ? panelRect.right : data.x),
                maxLeft
            );
            Object.assign(portal.style, {
                position: data.strategy || 'fixed',
                left: anchoredLeft + 'px',
                top: anchoredTop + 'px',
                zIndex: '99990'
            });
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H4',
                'awa-menu-controller.js:position:computed',
                'Floating UI computed flyout coordinates',
                {
                    x: Math.round(data.x),
                    anchoredX: Math.round(anchoredLeft),
                    panelRight: panelRect ? Math.round(panelRect.right) : null,
                    y: Math.round(data.y),
                    placement: data.placement || '',
                    strategy: data.strategy || '',
                    viewportW: window.innerWidth,
                    viewportH: window.innerHeight
                }
            );
            // #endregion
            var anchorData = {
                menuId: li.getAttribute('data-menu') || '',
                strategy: data.strategy || '',
                computedY: Math.round(data.y),
                panelTop: panelRect ? Math.round(panelRect.top) : null,
                panelRight: panelRect ? Math.round(panelRect.right) : null,
                itemTop: Math.round(liRect.top),
                itemRight: Math.round(liRect.right),
                anchoredLeft: Math.round(anchoredLeft),
                overlapAfterPosition: panelRect
                    ? Math.max(0, Math.round(panelRect.right - anchoredLeft))
                    : null,
                anchoredTop: Math.round(anchoredTop),
                portalHeight: Math.round(portal.getBoundingClientRect().height),
                portalMaxHeight: window.getComputedStyle(portal).maxHeight,
                availableHeight: Math.round(availableHeight),
                clientHeight: portal.clientHeight,
                scrollHeight: portal.scrollHeight,
                viewportHeight: window.innerHeight
            };
            // #region agent log
            fetch('http://localhost:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'ca59a1'},body:JSON.stringify({sessionId:'ca59a1',runId:'post-anchor-fix',hypothesisId:'H61-H62',location:'awa-menu-controller.js:position:panel-anchor',message:'Flyout uses fixed strategy and panel vertical anchor',data:anchorData,timestamp:Date.now()})}).catch(function(){});
            sendDebugLog(
                'post-anchor-fix',
                'H61-H62',
                'awa-menu-controller.js:position:panel-anchor',
                'Flyout uses fixed strategy and panel vertical anchor',
                anchorData
            );
            // #endregion
        });
    };

    FlyoutPortal.prototype.detach = function (li, reason) {
        var menuId = li.getAttribute('data-menu') || '';
        var portal = document.querySelector(
            '.' + PORTAL_CLASS + '[data-aw-vmf-li-menu="' + menuId + '"]'
        );
        var submenu = portal
            ? portal.querySelector('.submenu, .level0.submenu, .navigation__submenu')
            : this.findSubmenu(li);

        if (portal && submenu) {
            if (typeof portal._awaCleanupAutoUpdate === 'function') {
                portal._awaCleanupAutoUpdate();
                portal._awaCleanupAutoUpdate = null;
            }
            this.unbindPortalKeyboard(portal);
            li.appendChild(submenu);
            portal.remove();
        }
        if (submenu) {
            submenu.dataset.awVmfPortaled = '';
        }
        li.classList.remove(ACTIVE_CLASS);
        li.removeAttribute('data-awa-submenu-open');
    };

    FlyoutPortal.prototype.teardown = function () {
        document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (p) {
            if (typeof p._awaCleanupAutoUpdate === 'function') {
                p._awaCleanupAutoUpdate();
                p._awaCleanupAutoUpdate = null;
            }
            p.remove();
        });
    };

    FlyoutPortal.prototype.closeAll = function () {
        var self = this;
        Array.prototype.slice.call(
            this.root.querySelectorAll('li.level0[data-awa-submenu-open="true"]')
        ).forEach(function (li) {
            self.detach(li, 'close-all-open-li');
        });
        document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (portal) {
            var menuId = portal.dataset.awVmfLiMenu || '';
            var li = menuId && self.root.querySelector('li.level0[data-menu="' + menuId + '"]');
            if (li) {
                self.detach(li, 'close-all-portal');
            } else {
                if (typeof portal._awaCleanupAutoUpdate === 'function') {
                    portal._awaCleanupAutoUpdate();
                    portal._awaCleanupAutoUpdate = null;
                }
                portal.remove();
            }
        });
    };

    function getLevel0Items(panel) {
        return Array.prototype.filter.call(
            panel.querySelectorAll(':scope > li.ui-menu-item.level0'),
            function (li) {
                return !li.classList.contains('expand-category-link')
                    && !li.classList.contains('awa-vmenu-empty')
                    && !li.classList.contains('vertical-bg-img')
                    && !li.classList.contains('awa-vem-extra-li')
                    && !li.classList.contains('awa-vmenu-search-li')
                    && !li.classList.contains('awa-vmenu-search-empty-li');
            }
        );
    }

    function getItemLink(li) {
        return li ? li.querySelector(':scope > a.level-top, :scope > a.navigation__link') : null;
    }

    function normalizePtTitleCase(text) {
        if (!text) {
            return text;
        }

        var lowerWords = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para', 'com', 'por', 'a', 'o', 'as', 'os'];
        return text.split(/\s+/).map(function (word, index) {
            var lower = word.toLowerCase();
            if (index > 0 && lowerWords.indexOf(lower) !== -1) {
                return lower;
            }
            return lower.charAt(0).toUpperCase() + lower.slice(1);
        }).join(' ');
    }

    function applyMenuLabelNormalization(panel) {
        if (!panel) {
            return;
        }

        panel.querySelectorAll('.navigation__label').forEach(function (label) {
            var raw = (label.textContent || '').trim();
            if (!raw) {
                return;
            }

            var normalized = normalizePtTitleCase(raw);
            if (normalized !== raw) {
                label.textContent = normalized;
            }
        });
    }

    function applyTruncationTitles(panel) {
        if (!panel) {
            return;
        }
        panel.querySelectorAll('a.level-top, a.navigation__link').forEach(function (link) {
            var label = link.querySelector('.navigation__label');
            var text = label ? (label.textContent || '').trim()
                : link.getAttribute('data-awa-clean-label') || '';

            if (link.hasAttribute('title')) {
                var cur = link.getAttribute('title');
                var hasBadge = !!link.querySelector('.cat-label');
                if (hasBadge || (text && cur !== text)) {
                    link.removeAttribute('title');
                }
            }

            if (!text || !label) {
                return;
            }

            if (label.scrollWidth > label.clientWidth + 1) {
                link.setAttribute('title', text);
            } else {
                link.removeAttribute('title');
            }
        });
    }

    /* ── DeptMenu ─────────────────────────────────────────────────────── */
    function DeptMenu(nav, config) {
        this.nav = nav;
        this.config = config || {};
        this.trigger = nav.querySelector('[data-role="awa-vertical-menu-trigger"]');
        this.panel = nav.querySelector('[data-role="awa-vertical-menu-panel"]');
        this.status = nav.querySelector('[data-role="awa-vertical-menu-status"]');
        this.isOpen = false;
        this.hoverTimer = null;
        this.closeTimer = null;
    }

    DeptMenu.prototype.unlockParentChain = function (open) {
        if (!this.nav) {
            return;
        }

        var props = ['height', 'max-height', 'min-height', 'overflow'];
        var node = this.nav;

        while (node) {
            if (open) {
                node.style.setProperty('height', 'auto', 'important');
                node.style.setProperty('max-height', 'none', 'important');
                node.style.setProperty('min-height', '0', 'important');
                node.style.setProperty('overflow', 'visible', 'important');
            } else {
                props.forEach(function (prop) {
                    node.style.removeProperty(prop);
                });
            }

            if (node.classList && (
                node.classList.contains('awa-nav-bar')
                || node.classList.contains('header-nav-global')
                || (node.classList.contains('header-control') && node.classList.contains('header-nav'))
            )) {
                break;
            }
            node = node.parentElement;
        }
    };

    DeptMenu.prototype.measurePanelContentHeight = function () {
        var panelEl = this.panel;
        if (!panelEl) {
            return 160;
        }

        var total = 0;
        Array.prototype.forEach.call(panelEl.children, function (child) {
            if (window.getComputedStyle(child).display === 'none') {
                return;
            }
            total += child.getBoundingClientRect().height;
        });

        var panelStyles = window.getComputedStyle(panelEl);
        total += parseFloat(panelStyles.paddingTop) || 0;
        total += parseFloat(panelStyles.paddingBottom) || 0;
        total += parseFloat(panelStyles.borderTopWidth) || 0;
        total += parseFloat(panelStyles.borderBottomWidth) || 0;

        return Math.ceil(total);
    };

    DeptMenu.prototype.syncPanelHeight = function () {
        var panelEl = this.panel;
        if (!panelEl || !this.isOpen || !isDesktop()) {
            return;
        }

        this.unlockParentChain(true);
        panelEl.style.setProperty('display', 'flex', 'important');
        panelEl.style.setProperty('flex-direction', 'column', 'important');
        panelEl.style.setProperty('overflow-x', 'hidden', 'important');
        panelEl.style.setProperty('overflow-y', 'auto', 'important');
        panelEl.style.setProperty('z-index', '100100', 'important');
        panelEl.style.setProperty('height', 'auto', 'important');
        panelEl.style.setProperty('max-height', 'none', 'important');
        panelEl.style.setProperty('min-height', '0', 'important');
        panelEl.style.setProperty('padding-right', '12px', 'important');
        panelEl.style.setProperty('scrollbar-gutter', 'stable', 'important');

        void panelEl.offsetHeight;

        var maxPx = Math.min(window.innerHeight * 0.7, 560);
        var contentPx = Math.max(this.measurePanelContentHeight(), panelEl.scrollHeight);
        var target = Math.min(Math.max(contentPx, 160), maxPx);
        var toggleButtons = panelEl.querySelectorAll(':scope > li.ui-menu-item.level0 > .open-children-toggle.navigation__toggle');
        var visibleToggles = 0;
        toggleButtons.forEach(function (btn) {
            if (window.getComputedStyle(btn).display !== 'none') {
                visibleToggles += 1;
            }
        });

        panelEl.style.setProperty('max-height', 'min(70vh, 560px)', 'important');
        panelEl.style.setProperty('height', target + 'px', 'important');
        panelEl.style.setProperty('min-height', '120px', 'important');
        applyMenuLinkTypography(panelEl, true);
        applyTopLinkRuntimeFixes(panelEl);
        if (panelEl.dataset.awaHeightAuditLogged !== '1') {
            panelEl.dataset.awaHeightAuditLogged = '1';
            window.requestAnimationFrame(function () {
                var panelRect = panelEl.getBoundingClientRect();
                var children = Array.prototype.map.call(panelEl.children, function (child) {
                    var rect = child.getBoundingClientRect();
                    var style = window.getComputedStyle(child);
                    return {
                        cls: child.className || child.tagName,
                        menu: child.getAttribute('data-menu') || '',
                        display: style.display,
                        position: style.position,
                        height: Math.round(rect.height),
                        scrollHeight: child.scrollHeight,
                        top: Math.round(rect.top - panelRect.top),
                        bottom: Math.round(rect.bottom - panelRect.top)
                    };
                });
                var visibleChildren = children.filter(function (child) {
                    return child.display !== 'none' && child.height > 0;
                });
                var hiddenWithSize = children.filter(function (child) {
                    return child.display === 'none' && (child.height > 0 || child.scrollHeight > 0);
                });
                var visibleSubmenus = Array.prototype.map.call(
                    panelEl.querySelectorAll(':scope > li.level0 > .submenu, :scope > li.level0 > .navigation__submenu'),
                    function (submenu) {
                        var rect = submenu.getBoundingClientRect();
                        var style = window.getComputedStyle(submenu);
                        return {
                            parent: submenu.parentElement ? (submenu.parentElement.getAttribute('data-menu') || '') : '',
                            display: style.display,
                            position: style.position,
                            height: Math.round(rect.height),
                            scrollHeight: submenu.scrollHeight
                        };
                    }
                ).filter(function (submenu) {
                    return submenu.display !== 'none' || submenu.height > 0;
                });
                // #region agent log
                sendDebugLog(
                    'panel-height-audit',
                    'H72-H75',
                    'awa-menu-controller.js:syncPanelHeight:contributors',
                    'Direct children contributing to vertical panel scroll height',
                    {
                        clientHeight: panelEl.clientHeight,
                        scrollHeight: panelEl.scrollHeight,
                        visibleHeightSum: visibleChildren.reduce(function (sum, child) {
                            return sum + child.height;
                        }, 0),
                        visibleChildren: visibleChildren,
                        hiddenWithSize: hiddenWithSize,
                        visibleSubmenus: visibleSubmenus
                    }
                );
                // #endregion
            });
        }
        // #region agent log
        sendDebugLog(
            'pre-fix',
            'H5',
            'awa-menu-controller.js:syncPanelHeight',
            'Vertical panel scroll area sizing',
            {
                targetHeight: target,
                panelClientWidth: panelEl.clientWidth,
                panelScrollWidth: panelEl.scrollWidth,
                panelClientHeight: panelEl.clientHeight,
                panelScrollHeight: panelEl.scrollHeight,
                paddingRight: window.getComputedStyle(panelEl).paddingRight,
                overflowY: window.getComputedStyle(panelEl).overflowY,
                toggleCount: toggleButtons.length,
                visibleToggles: visibleToggles
            }
        );
        // #endregion
    };

    DeptMenu.prototype.syncSearchRow = function (open) {
        if (!this.panel) {
            return;
        }

        var searchRow = this.panel.querySelector('[data-role="awa-vmenu-search-row"]');
        if (!searchRow) {
            return;
        }

        if (open && isDesktop()) {
            searchRow.style.setProperty('display', 'block', 'important');
        } else if (!open) {
            searchRow.style.removeProperty('display');
        }
    };

    DeptMenu.prototype.schedulePanelHeight = function () {
        var self = this;
        var attempt = 0;
        function run() {
            if (!self.isOpen || !self.panel) {
                return;
            }
            self.syncPanelHeight();
            attempt += 1;
            if (attempt < 5) {
                window.setTimeout(run, attempt === 1 ? 16 : 48);
            }
        }
        window.requestAnimationFrame(run);
    };

    DeptMenu.prototype.syncAria = function (open) {
        if (this.trigger) {
            this.trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            this.trigger.classList.toggle('active', open);
        }
        if (this.nav) {
            this.nav.classList.toggle(ACTIVE_CLASS, open);
            this.nav.setAttribute('data-awa-menu-open', open ? 'true' : 'false');
        }
        if (this.panel) {
            var nextPanelAriaHidden = open ? 'false' : 'true';
            if (this.panel.getAttribute('aria-hidden') !== nextPanelAriaHidden) {
                this.panel.setAttribute('aria-hidden', nextPanelAriaHidden);
            }
            this.panel.setAttribute('data-awa-menu-state', open ? 'open' : 'closed');
            this.panel.classList.toggle('vmm-open', open);
            this.panel.classList.toggle('menu-open', open);
            if (open) {
                this.panel.style.setProperty('display', 'flex', 'important');
                this.panel.style.setProperty('flex-direction', 'column', 'important');
                this.panel.style.setProperty('visibility', 'visible', 'important');
                this.panel.style.setProperty('opacity', '1', 'important');
                this.panel.style.setProperty('padding-right', '12px', 'important');
                this.panel.style.setProperty('scrollbar-gutter', 'stable', 'important');
                ensureRuntimeMenuStyles();
                applyTopLinkRuntimeFixes(this.panel);
                this.syncSearchRow(true);
                applyMenuLinkTypography(this.panel, true);
                applyMenuLabelNormalization(this.panel);
                // #region agent log
                (function logSearchGeometry(stage, panel) {
                    var searchRow = panel.querySelector(':scope > [data-role="awa-vmenu-search-row"]');
                    var searchWrap = searchRow
                        ? searchRow.querySelector('.awa-vmenu-search-wrap')
                        : null;
                    var searchInput = searchWrap
                        ? searchWrap.querySelector('.awa-vmenu-search-input')
                        : null;
                    var snapshot = function (node) {
                        var rect;
                        var style;

                        if (!node) {
                            return null;
                        }
                        rect = node.getBoundingClientRect();
                        style = window.getComputedStyle(node);

                        return {
                            rect: {
                                x: Math.round(rect.x),
                                y: Math.round(rect.y),
                                w: Math.round(rect.width),
                                h: Math.round(rect.height)
                            },
                            position: style.position,
                            display: style.display,
                            float: style.float,
                            left: style.left,
                            insetInlineStart: style.insetInlineStart,
                            margin: style.margin,
                            padding: style.padding,
                            transform: style.transform,
                            width: style.width,
                            boxSizing: style.boxSizing,
                            offsetLeft: node.offsetLeft,
                            offsetParent: node.offsetParent
                                ? String(node.offsetParent.className || node.offsetParent.id || node.offsetParent.tagName).slice(0, 120)
                                : null
                        };
                    };

                    fetch('http://localhost:7935/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Debug-Session-Id': 'ca59a1'
                        },
                        body: JSON.stringify({
                            sessionId: 'ca59a1',
                            runId: 'vmenu-search-offset-pre-fix',
                            hypothesisId: 'H201-H205',
                            location: 'awa-menu-controller.js:syncAria:open:search-geometry',
                            message: 'Vertical menu search coordinate chain',
                            data: {
                                stage: stage,
                                viewportWidth: window.innerWidth,
                                panelCount: document.querySelectorAll('[data-role="awa-vertical-menu-panel"]').length,
                                inputCount: document.querySelectorAll('.awa-vmenu-search-input').length,
                                inputBelongsToPanel: !!(searchInput && searchInput.closest('[data-role="awa-vertical-menu-panel"]') === panel),
                                panel: snapshot(panel),
                                searchRow: snapshot(searchRow),
                                searchWrap: snapshot(searchWrap),
                                searchInput: snapshot(searchInput)
                            },
                            timestamp: Date.now()
                        })
                    }).catch(function () {});
                    sendDebugLog(
                        'vmenu-search-offset-pre-fix',
                        'H201-H205',
                        'awa-menu-controller.js:syncAria:open:search-geometry-fallback',
                        'Compact vertical menu search coordinate chain',
                        {
                            s: stage,
                            v: window.innerWidth,
                            c: [
                                document.querySelectorAll('[data-role="awa-vertical-menu-panel"]').length,
                                document.querySelectorAll('.awa-vmenu-search-input').length,
                                searchInput && searchInput.closest('[data-role="awa-vertical-menu-panel"]') === panel ? 1 : 0
                            ],
                            p: compactGeometry(panel),
                            r: compactGeometry(searchRow),
                            w: compactGeometry(searchWrap),
                            i: compactGeometry(searchInput)
                        }
                    );

                    function compactGeometry(node) {
                        var rect = node ? node.getBoundingClientRect() : null;

                        return rect ? [
                            Math.round(rect.x),
                            Math.round(rect.y),
                            Math.round(rect.width),
                            Math.round(rect.height),
                            node.offsetLeft
                        ] : null;
                    }
                    sendDebugLog(
                        'vmenu-search-offset-pre-fix',
                        'H201-H204',
                        'awa-menu-controller.js:syncAria:open:search-style-fallback',
                        'Compact vertical menu search positioning styles',
                        {
                            r: compactStyle(searchRow),
                            w: compactStyle(searchWrap),
                            i: compactStyle(searchInput)
                        }
                    );

                    function compactStyle(node) {
                        var style = node ? window.getComputedStyle(node) : null;

                        return style ? [
                            style.position,
                            style.float,
                            style.left,
                            style.marginLeft,
                            style.transform,
                            node.offsetParent
                                ? String(node.offsetParent.className || node.offsetParent.id || node.offsetParent.tagName).slice(0, 45)
                                : ''
                        ] : null;
                    }
                }('immediate', this.panel));
                // #endregion
                // #region agent log
                var visibleInactiveSubmenus = Array.prototype.filter.call(
                    this.panel.querySelectorAll(':scope > li.ui-menu-item.level0 > .submenu, :scope > li.ui-menu-item.level0 > .navigation__submenu'),
                    function (submenu) {
                        return !submenu.closest('.' + PORTAL_CLASS)
                            && window.getComputedStyle(submenu).display !== 'none';
                    }
                );
                var levelZeroHeights = Array.prototype.map.call(
                    this.panel.querySelectorAll(':scope > li.ui-menu-item.level0'),
                    function (item) {
                        return Math.round(item.getBoundingClientRect().height);
                    }
                );
                fetch('http://localhost:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'ca59a1'},body:JSON.stringify({sessionId:'ca59a1',runId:'post-race-fix',hypothesisId:'H53-H54',location:'awa-menu-controller.js:open:layout-guard',message:'Inactive submenu race guard and scrollbar geometry',data:{visibleInactiveSubmenus:visibleInactiveSubmenus.length,maxLevelZeroHeight:levelZeroHeights.length?Math.max.apply(Math,levelZeroHeights):0,clientWidth:this.panel.clientWidth,scrollWidth:this.panel.scrollWidth,clientHeight:this.panel.clientHeight,scrollHeight:this.panel.scrollHeight,scrollbarGutter:window.getComputedStyle(this.panel).scrollbarGutter},timestamp:Date.now()})}).catch(function(){});
                // #endregion
                applyTruncationTitles(this.panel);
                var firstParentItem = this.panel.querySelector(':scope > li.ui-menu-item.level0.navigation__item--parent, :scope > li.ui-menu-item.level0.parent');
                var firstParentLink = this.panel.querySelector(':scope > li.ui-menu-item.level0.navigation__item--parent > a.level-top.navigation__link, :scope > li.ui-menu-item.level0.parent > a.level-top.navigation__link');
                var firstParentAfter = firstParentLink ? window.getComputedStyle(firstParentLink, '::after') : null;
                var probeRect = firstParentItem ? firstParentItem.getBoundingClientRect() : null;
                var probeX = probeRect ? Math.round(probeRect.left + (probeRect.width / 2)) : 0;
                var probeY = probeRect ? Math.round(probeRect.top + (probeRect.height / 2)) : 0;
                var topAtProbe = (probeRect && probeRect.width > 0 && probeRect.height > 0)
                    ? document.elementFromPoint(probeX, probeY)
                    : null;
                // #region agent log
                sendDebugLog(
                    'pre-fix',
                    'H9',
                    'awa-menu-controller.js:syncAria:open',
                    'Immediate open-state parent ::after snapshot',
                    {
                        href: window.location.href,
                        panelState: this.panel.getAttribute('data-awa-menu-state') || '',
                        runtimeStylePresent: !!document.getElementById(RUNTIME_STYLE_FIX_ID),
                        panelPointerEvents: window.getComputedStyle(this.panel).pointerEvents,
                        firstParentPointerEvents: firstParentItem ? window.getComputedStyle(firstParentItem).pointerEvents : '',
                        firstParentRectW: probeRect ? Math.round(probeRect.width) : 0,
                        firstParentRectH: probeRect ? Math.round(probeRect.height) : 0,
                        probeInsideFirstParent: !!(topAtProbe && firstParentItem && firstParentItem.contains(topAtProbe)),
                        probeTopTag: topAtProbe ? topAtProbe.tagName : '',
                        probeTopClass: topAtProbe ? String(topAtProbe.className || '').slice(0, 120) : '',
                        probeTopSrc: (topAtProbe && topAtProbe.tagName === 'IMG')
                            ? String(topAtProbe.currentSrc || topAtProbe.src || '').slice(0, 180)
                            : '',
                        probeTopPointerEvents: topAtProbe ? window.getComputedStyle(topAtProbe).pointerEvents : '',
                        probeTopPosition: topAtProbe ? window.getComputedStyle(topAtProbe).position : '',
                        probeTopZIndex: topAtProbe ? window.getComputedStyle(topAtProbe).zIndex : '',
                        probeTopClosestLevel0Class: (topAtProbe && topAtProbe.closest)
                            ? (function () {
                                var probeLi = topAtProbe.closest('li.ui-menu-item.level0');
                                return probeLi ? String(probeLi.className || '').slice(0, 120) : '';
                            }())
                            : '',
                        firstParentClass: firstParentLink && firstParentLink.parentElement ? firstParentLink.parentElement.className : '',
                        afterContent: firstParentAfter ? firstParentAfter.content : '',
                        afterDisplay: firstParentAfter ? firstParentAfter.display : '',
                        afterOpacity: firstParentAfter ? firstParentAfter.opacity : ''
                    }
                );
                // #endregion
                // #region agent log
                sendDebugLog(
                    'pre-fix',
                    'H38',
                    'awa-menu-controller.js:syncAria:open:probe-compact',
                    'Compact probe for top element intercepting first level0 item',
                    {
                        href: window.location.href,
                        panelState: this.panel.getAttribute('data-awa-menu-state') || '',
                        probeInsideFirstParent: !!(topAtProbe && firstParentItem && firstParentItem.contains(topAtProbe)),
                        probeTopTag: topAtProbe ? topAtProbe.tagName : '',
                        probeTopClass: topAtProbe ? String(topAtProbe.className || '').slice(0, 60) : '',
                        probeTopSrc: (topAtProbe && topAtProbe.tagName === 'IMG')
                            ? String(topAtProbe.currentSrc || topAtProbe.src || '').slice(0, 120)
                            : '',
                        probeTopPointerEvents: topAtProbe ? window.getComputedStyle(topAtProbe).pointerEvents : '',
                        probeTopZIndex: topAtProbe ? window.getComputedStyle(topAtProbe).zIndex : ''
                    }
                );
                // #endregion
                if (this.panel.dataset.awaDocPointerProbeBound !== '1') {
                    this.panel.dataset.awaDocPointerProbeBound = '1';
                    this.panel._awaDocPointerProbeCount = 0;
                    document.addEventListener('pointermove', function (evt) {
                        if (!this.panel || !this.isOpen || this.panel._awaDocPointerProbeCount >= 6) {
                            return;
                        }
                        this.panel._awaDocPointerProbeCount += 1;
                        var x = evt ? Math.round(evt.clientX || 0) : 0;
                        var y = evt ? Math.round(evt.clientY || 0) : 0;
                        var topNode = document.elementFromPoint(x, y);
                        // #region agent log
                        sendDebugLog(
                            'pre-fix',
                            'H39',
                            'awa-menu-controller.js:syncAria:doc-pointermove-probe',
                            'Document pointermove probe while panel open',
                            {
                                href: window.location.href,
                                count: this.panel._awaDocPointerProbeCount,
                                pointerX: x,
                                pointerY: y,
                                targetTag: evt && evt.target ? evt.target.tagName : '',
                                targetClass: evt && evt.target ? String(evt.target.className || '').slice(0, 80) : '',
                                topTag: topNode ? topNode.tagName : '',
                                topClass: topNode ? String(topNode.className || '').slice(0, 80) : '',
                                topPointerEvents: topNode ? window.getComputedStyle(topNode).pointerEvents : '',
                                inPanel: !!(topNode && this.panel.contains(topNode)),
                                inNav: !!(topNode && this.nav && this.nav.contains(topNode))
                            }
                        );
                        // #endregion
                    }.bind(this), true);
                }
                if (this.nav && this.nav.dataset.awaNavPointerProbeBound !== '1') {
                    this.nav.dataset.awaNavPointerProbeBound = '1';
                    this.nav._awaNavPointerProbeCount = 0;
                    this.nav.addEventListener('pointerover', function (evt) {
                        if (!this.nav || this.nav._awaNavPointerProbeCount >= 5) {
                            return;
                        }
                        this.nav._awaNavPointerProbeCount += 1;
                        var target = evt && evt.target ? evt.target : null;
                        var level0Li = target && target.closest
                            ? target.closest('li.ui-menu-item.level0')
                            : null;
                        // #region agent log
                        sendDebugLog(
                            'pre-fix',
                            'H37',
                            'awa-menu-controller.js:syncAria:nav-pointerover-probe',
                            'Pointerover captured in nav scope while menu is open',
                            {
                                href: window.location.href,
                                count: this.nav._awaNavPointerProbeCount,
                                isOpen: !!this.isOpen,
                                targetTag: target ? target.tagName : '',
                                targetClass: target ? String(target.className || '').slice(0, 120) : '',
                                targetPointerEvents: target ? window.getComputedStyle(target).pointerEvents : '',
                                level0Class: level0Li ? String(level0Li.className || '').slice(0, 120) : ''
                            }
                        );
                        // #endregion
                    }.bind(this), true);
                }
                if (this.panel.dataset.awaHoverProbeBound !== '1') {
                    this.panel.dataset.awaHoverProbeBound = '1';
                    this.panel._awaHoverProbeCount = 0;
                    this.panel.addEventListener('mouseover', function (evt) {
                        if (!this.panel || this.panel._awaHoverProbeCount >= 4) {
                            return;
                        }
                        this.panel._awaHoverProbeCount += 1;
                        var target = evt && evt.target ? evt.target : null;
                        var targetParent = target && target.closest
                            ? target.closest('li.level0.parent, li.level0.navigation__item--parent')
                            : null;
                        // #region agent log
                        sendDebugLog(
                            'pre-fix',
                            'H36',
                            'awa-menu-controller.js:syncAria:hover-probe',
                            'Panel mouseover probe event reached menu panel',
                            {
                                href: window.location.href,
                                count: this.panel._awaHoverProbeCount,
                                targetTag: target ? target.tagName : '',
                                targetClass: target ? String(target.className || '').slice(0, 120) : '',
                                hasClosestParentLevel0: !!targetParent,
                                closestParentClass: targetParent ? String(targetParent.className || '').slice(0, 120) : ''
                            }
                        );
                        // #endregion
                    }.bind(this), true);
                }
                window.requestAnimationFrame(function () {
                    var rafParentLink = this.panel ? this.panel.querySelector(':scope > li.ui-menu-item.level0.navigation__item--parent > a.level-top.navigation__link, :scope > li.ui-menu-item.level0.parent > a.level-top.navigation__link') : null;
                    var rafAfter = rafParentLink ? window.getComputedStyle(rafParentLink, '::after') : null;
                    var hero = document.querySelector('.awa-hero-swiper');
                    var benefits = document.querySelector('.awa-hero-benefits');
                    var categoryTitle = Array.prototype.find.call(
                        document.querySelectorAll('.awa-section-header__title'),
                        function (title) {
                            return (title.textContent || '').indexOf('Compre por categoria') !== -1;
                        }
                    );
                    var panelRect = this.panel ? this.panel.getBoundingClientRect() : null;
                    var heroRect = hero ? hero.getBoundingClientRect() : null;
                    var benefitsRect = benefits ? benefits.getBoundingClientRect() : null;
                    var categoryRect = categoryTitle ? categoryTitle.getBoundingClientRect() : null;
                    // #region agent log
                    (function logSearchGeometryAfterFrame(panel) {
                        var searchRow = panel
                            ? panel.querySelector(':scope > [data-role="awa-vmenu-search-row"]')
                            : null;
                        var searchWrap = searchRow
                            ? searchRow.querySelector('.awa-vmenu-search-wrap')
                            : null;
                        var searchInput = searchWrap
                            ? searchWrap.querySelector('.awa-vmenu-search-input')
                            : null;
                        var compactRect = function (node) {
                            var rect = node ? node.getBoundingClientRect() : null;

                            return rect ? {
                                x: Math.round(rect.x),
                                y: Math.round(rect.y),
                                w: Math.round(rect.width),
                                h: Math.round(rect.height)
                            } : null;
                        };

                        fetch('http://localhost:7935/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Debug-Session-Id': 'ca59a1'
                            },
                            body: JSON.stringify({
                                sessionId: 'ca59a1',
                                runId: 'vmenu-search-offset-pre-fix',
                                hypothesisId: 'H202-H205',
                                location: 'awa-menu-controller.js:syncAria:open:search-geometry-raf',
                                message: 'Vertical menu search geometry after layout frame',
                                data: {
                                    panel: compactRect(panel),
                                    searchRow: compactRect(searchRow),
                                    searchWrap: compactRect(searchWrap),
                                    searchInput: compactRect(searchInput),
                                    rowInlineStyle: searchRow ? searchRow.getAttribute('style') || '' : '',
                                    wrapInlineStyle: searchWrap ? searchWrap.getAttribute('style') || '' : '',
                                    inputInlineStyle: searchInput ? searchInput.getAttribute('style') || '' : ''
                                },
                                timestamp: Date.now()
                            })
                        }).catch(function () {});
                        sendDebugLog(
                            'vmenu-search-offset-pre-fix',
                            'H202-H205',
                            'awa-menu-controller.js:syncAria:open:search-geometry-raf-fallback',
                            'Compact vertical menu search geometry after layout frame',
                            {
                                p: compactRect(panel),
                                r: compactRect(searchRow),
                                w: compactRect(searchWrap),
                                i: compactRect(searchInput)
                            }
                        );
                    }(this.panel));
                    // #endregion
                    // #region agent log
                    sendDebugLog(
                        'pre-fix',
                        'H10',
                        'awa-menu-controller.js:syncAria:open:raf',
                        'Post-frame parent ::after snapshot',
                        {
                            href: window.location.href,
                            panelState: this.panel ? this.panel.getAttribute('data-awa-menu-state') || '' : '',
                            runtimeStylePresent: !!document.getElementById(RUNTIME_STYLE_FIX_ID),
                            afterContent: rafAfter ? rafAfter.content : '',
                            afterDisplay: rafAfter ? rafAfter.display : '',
                            afterOpacity: rafAfter ? rafAfter.opacity : ''
                        }
                    );
                    // #endregion
                    // #region agent log
                    fetch('http://localhost:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'ca59a1'},body:JSON.stringify({sessionId:'ca59a1',runId:'home-menu-composition-pre-fix',hypothesisId:'H104-H107',location:'awa-menu-controller.js:syncAria:open:composition',message:'Open menu composition against hero and next section',data:{panelPosition:this.panel?window.getComputedStyle(this.panel).position:null,panelRect:panelRect?{x:Math.round(panelRect.x),y:Math.round(panelRect.y),right:Math.round(panelRect.right),bottom:Math.round(panelRect.bottom),w:Math.round(panelRect.width),h:Math.round(panelRect.height)}:null,heroRect:heroRect?{x:Math.round(heroRect.x),y:Math.round(heroRect.y),right:Math.round(heroRect.right),bottom:Math.round(heroRect.bottom),w:Math.round(heroRect.width),h:Math.round(heroRect.height)}:null,benefitsRect:benefitsRect?{x:Math.round(benefitsRect.x),y:Math.round(benefitsRect.y),right:Math.round(benefitsRect.right),bottom:Math.round(benefitsRect.bottom),w:Math.round(benefitsRect.width),h:Math.round(benefitsRect.height)}:null,horizontalOverlap:panelRect&&heroRect?Math.max(0,Math.round(panelRect.right-heroRect.left)):null,categoryGap:panelRect&&categoryRect?Math.round(categoryRect.top-panelRect.bottom):null,bodyClass:document.body.className,navClass:this.nav?this.nav.className:'',heroParentClass:hero&&hero.parentElement?hero.parentElement.className:''},timestamp:Date.now()})}).catch(function(){});
                    sendDebugLog(
                        'home-menu-composition-pre-fix',
                        'H104-H107',
                        'awa-menu-controller.js:syncAria:open:composition',
                        'Open menu composition against hero and next section',
                        {
                            panelPosition: this.panel ? window.getComputedStyle(this.panel).position : null,
                            panelRect: panelRect ? {
                                x: Math.round(panelRect.x),
                                y: Math.round(panelRect.y),
                                right: Math.round(panelRect.right),
                                bottom: Math.round(panelRect.bottom),
                                w: Math.round(panelRect.width),
                                h: Math.round(panelRect.height)
                            } : null,
                            heroRect: heroRect ? {
                                x: Math.round(heroRect.x),
                                y: Math.round(heroRect.y),
                                right: Math.round(heroRect.right),
                                bottom: Math.round(heroRect.bottom),
                                w: Math.round(heroRect.width),
                                h: Math.round(heroRect.height)
                            } : null,
                            benefitsRect: benefitsRect ? {
                                x: Math.round(benefitsRect.x),
                                y: Math.round(benefitsRect.y),
                                right: Math.round(benefitsRect.right),
                                bottom: Math.round(benefitsRect.bottom),
                                w: Math.round(benefitsRect.width),
                                h: Math.round(benefitsRect.height)
                            } : null,
                            horizontalOverlap: panelRect && heroRect
                                ? Math.max(0, Math.round(panelRect.right - heroRect.left))
                                : null,
                            categoryGap: panelRect && categoryRect
                                ? Math.round(categoryRect.top - panelRect.bottom)
                                : null,
                            bodyClass: document.body.className,
                            navClass: this.nav ? this.nav.className : '',
                            heroParentClass: hero && hero.parentElement ? hero.parentElement.className : ''
                        }
                    );
                    // #endregion
                }.bind(this));
                if (isDesktop()) {
                    this.schedulePanelHeight();
                }
            } else {
                this.syncSearchRow(false);
                applyMenuLinkTypography(this.panel, false);
                ['display', 'flex-direction', 'visibility', 'opacity', 'height', 'min-height', 'max-height', 'overflow', 'overflow-x', 'overflow-y'].forEach(function (prop) {
                    this.panel.style.removeProperty(prop);
                }, this);
            }
        }
        if (this.nav && isDesktop()) {
            this.unlockParentChain(open);
        }
        if (this.status) {
            this.status.textContent = open
                ? 'Menu de departamentos aberto.'
                : 'Menu de departamentos fechado. Pressione Enter para abrir.';
        }
        syncHomeMenuComposition(open);
        document.body.classList.toggle('awa-menu-dept-open', open);
    };

    DeptMenu.prototype.open = function () {
        if (this.isOpen) {
            return;
        }
        clearTimeout(this.closeTimer);
        this.isOpen = true;
        this.syncAria(true);
    };

    DeptMenu.prototype.close = function () {
        if (!this.isOpen) {
            return;
        }
        clearTimeout(this.hoverTimer);
        clearTimeout(this.closeTimer);
        this.isOpen = false;
        this.syncAria(false);
    };

    DeptMenu.prototype.closeSoon = function (delay) {
        var self = this;
        clearTimeout(this.closeTimer);
        this.closeTimer = window.setTimeout(function () {
            if (document.querySelector('.' + PORTAL_CLASS + ':hover')) {
                self.closeSoon(delay);
                return;
            }
            self.close();
        }, typeof delay === 'number' ? delay : 180);
    };

    DeptMenu.prototype.toggle = function () {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    };

    DeptMenu.prototype.bind = function (flyout) {
        var self = this;
        // #region agent log
        sendDebugLog(
            'pre-fix',
            'H15',
            'awa-menu-controller.js:DeptMenu.bind:entry',
            'DeptMenu bind entry snapshot',
            {
                href: window.location.href,
                hasTrigger: !!this.trigger,
                hasPanel: !!this.panel,
                hasNav: !!this.nav,
                navReadyFlag: this.nav ? (this.nav.dataset.awaMenuControllerReady || '') : ''
            }
        );
        // #endregion
        if (!this.trigger || !this.panel) {
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H15',
                'awa-menu-controller.js:DeptMenu.bind:abort',
                'DeptMenu bind aborted due missing trigger/panel',
                {
                    href: window.location.href,
                    hasTrigger: !!this.trigger,
                    hasPanel: !!this.panel
                }
            );
            // #endregion
            return;
        }

        if (
            document.body.classList.contains('awa-menu-dept-open')
            || this.panel.getAttribute('data-awa-menu-state') === 'open'
            || this.trigger.getAttribute('aria-expanded') === 'true'
        ) {
            this.isOpen = true;
            this.syncAria(true);
        }

        this.trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (isMobile()) {
                mobileDrawer.openDrawer();
                return;
            }
            self.toggle();
        });

        this.trigger.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'ArrowDown') {
                return;
            }
            e.preventDefault();
            if (isMobile()) {
                mobileDrawer.openDrawer();
                return;
            }
            self.open();
            window.requestAnimationFrame(function () {
                var first = self.panel.querySelector('[data-role="awa-vmenu-search"], a.level-top, a.navigation__link');
                if (first) {
                    first.focus();
                }
            });
        });

        this.nav.addEventListener('mouseenter', function () {
            if (!isDesktop()) {
                return;
            }
            clearTimeout(self.closeTimer);
            clearTimeout(self.hoverTimer);
            self.hoverTimer = window.setTimeout(function () { self.open(); }, self.config.hoverDelay || 100);
        });
        this.nav.addEventListener('mouseleave', function (e) {
            if (!isDesktop()) {
                return;
            }
            var to = e.relatedTarget;
            if (to && (self.nav.contains(to) || (to.closest && to.closest('.' + PORTAL_CLASS)))) {
                return;
            }
            clearTimeout(self.hoverTimer);
            self.closeSoon(self.config.closeDelay || 180);
        });
        document.addEventListener('mouseleave', function (e) {
            if (!isDesktop() || !self.isOpen) {
                return;
            }
            var from = e.target;
            var to = e.relatedTarget;
            if (to || !from || !from.closest || (!from.closest('.' + PORTAL_CLASS) && !from.closest('[data-role="awa-vertical-menu"]'))) {
                return;
            }
            self.closeSoon(self.config.closeDelay || 180);
        }, true);

        document.addEventListener('click', function (e) {
            if (!self.isOpen || self.nav.contains(e.target)) {
                return;
            }
            self.close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !self.isOpen) {
                return;
            }
            if (flyout && document.querySelector('.' + PORTAL_CLASS)) {
                flyout.closeAll();
                return;
            }
            self.close();
            self.trigger.focus();
        });

        this.bindPanelKeyboard(flyout);
        this.bindPanelSearch();
        this.bindLimitShow();
        this.syncSearchRow(this.isOpen);
        if (this.isOpen) {
            this.schedulePanelHeight();
        }
        applyMenuLabelNormalization(this.panel);
        applyTruncationTitles(this.panel);
        if (this.panel && window.ResizeObserver) {
            var ro = new ResizeObserver(function () {
                applyTruncationTitles(self.panel);
            });
            ro.observe(this.panel);
        }
        window.addEventListener('resize', function () {
            applyTruncationTitles(self.panel);
            syncHomeMenuComposition(self.isOpen);
        }, { passive: true });

        this.panel.querySelectorAll('.open-children-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var li = btn.closest('li');
                if (!li) {
                    return;
                }
                var sub = li.querySelector(':scope > .subchildmenu, :scope > .submenu');
                if (!sub) {
                    return;
                }
                var opened = sub.classList.toggle('opened');
                btn.setAttribute('aria-expanded', opened ? 'true' : 'false');
                sub.style.display = opened ? '' : 'none';
            });
        });
        // #region agent log
        sendDebugLog(
            'pre-fix',
            'H16',
            'awa-menu-controller.js:DeptMenu.bind:ready',
            'DeptMenu bind completed and listeners attached',
            {
                href: window.location.href,
                isOpen: !!this.isOpen,
                panelState: this.panel.getAttribute('data-awa-menu-state') || '',
                ariaExpanded: this.trigger.getAttribute('aria-expanded') || '',
                parentCount: this.panel.querySelectorAll(':scope > li.ui-menu-item.level0.parent, :scope > li.ui-menu-item.level0.navigation__item--parent').length
            }
        );
        // #endregion
    };

    DeptMenu.prototype.bindLimitShow = function () {
        var self = this;
        if (!this.panel) {
            return;
        }

        var limit = parseInt(this.panel.getAttribute('data-limit-show') || '0', 10);
        var expandLi = this.panel.querySelector(':scope > li.expand-category-link');
        var expandBtn = expandLi ? expandLi.querySelector('.vm-toggle-categories') : null;
        var items = getLevel0Items(this.panel);

        if (!expandBtn || limit <= 0 || items.length <= limit) {
            if (expandLi) {
                expandLi.style.display = 'none';
            }
            return;
        }

        expandLi.style.display = '';
        items.forEach(function (li, index) {
            if (index >= limit) {
                li.classList.add('orther-link');
                li.classList.remove('is-expanded');
                li.style.setProperty('display', 'none', 'important');
            } else {
                li.style.removeProperty('display');
            }
        });

        var showText = expandBtn.getAttribute('data-show-text') || expandBtn.textContent.trim();
        var hideText = expandBtn.getAttribute('data-hide-text') || showText;
        var showAria = expandBtn.getAttribute('data-show-aria') || showText;
        var hideAria = expandBtn.getAttribute('data-hide-aria') || hideText;
        var labelSpan = expandBtn.querySelector('span');

        if (labelSpan && showText) {
            labelSpan.textContent = showText;
        }

        if (self.isOpen) {
            self.schedulePanelHeight();
        }

        expandBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var expanding = !expandBtn.classList.contains('expanding');
            expandBtn.classList.toggle('expanding', expanding);
            expandLi.classList.toggle('expanding', expanding);
            expandBtn.setAttribute('aria-expanded', expanding ? 'true' : 'false');

            if (labelSpan) {
                labelSpan.textContent = expanding ? hideText : showText;
            }
            expandBtn.setAttribute('aria-label', expanding ? hideAria : showAria);

            items.forEach(function (li, index) {
                if (index < limit) {
                    return;
                }
                li.classList.toggle('is-expanded', expanding);
                if (expanding) {
                    li.style.removeProperty('display');
                } else {
                    li.style.setProperty('display', 'none', 'important');
                }
            });
            self.schedulePanelHeight();
        });
    };

    DeptMenu.prototype.bindPanelKeyboard = function (flyout) {
        var self = this;
        if (!this.panel) {
            return;
        }

        this.panel.addEventListener('keydown', function (e) {
            if (!isDesktop() || !self.isOpen) {
                return;
            }

            var items = getLevel0Items(self.panel);
            if (!items.length) {
                return;
            }

            var currentLi = e.target.closest('li.ui-menu-item.level0');
            var idx = currentLi ? items.indexOf(currentLi) : -1;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var nextIdx = idx < 0 ? 0 : Math.min(idx + 1, items.length - 1);
                var nextLink = getItemLink(items[nextIdx]);
                if (nextLink) {
                    nextLink.focus();
                }
                return;
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                var prevIdx = idx <= 0 ? 0 : idx - 1;
                var prevLink = getItemLink(items[prevIdx]);
                if (prevLink) {
                    prevLink.focus();
                }
                return;
            }

            if ((e.key === 'ArrowRight' || e.key === 'Enter' || e.key === ' ') && currentLi) {
                if (currentLi.classList.contains('parent') || currentLi.classList.contains('navigation__item--parent')) {
                    e.preventDefault();
                    currentLi.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true, cancelable: true }));
                    if (flyout) {
                        flyout.attach(currentLi);
                    }
                }
                return;
            }

            if ((e.key === 'ArrowLeft' || e.key === 'Escape') && flyout && document.querySelector('.' + PORTAL_CLASS)) {
                e.preventDefault();
                e.stopPropagation();
                flyout.closeAll();
                if (currentLi) {
                    var link = getItemLink(currentLi);
                    if (link) {
                        link.focus();
                    }
                }
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || !isDesktop() || !document.querySelector('.' + PORTAL_CLASS)) {
                return;
            }
            if (self.panel && self.panel.contains(document.activeElement)) {
                return;
            }
            if (flyout) {
                flyout.closeAll();
            }
        }, true);
    };

    DeptMenu.prototype.bindPanelSearch = function () {
        var self = this;
        var input = this.panel && this.panel.querySelector('[data-role="awa-vmenu-search"]');
        if (!input) {
            return;
        }

        var searchRow = this.panel.querySelector('[data-role="awa-vmenu-search-row"]');
        var searchTimer = null;

        function normalize(text) {
            return (text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        function getFilterableItems() {
            return Array.prototype.filter.call(
                self.panel.querySelectorAll(':scope > li.ui-menu-item.level0'),
                function (li) {
                    return !li.classList.contains('awa-vmenu-search-li')
                        && !li.classList.contains('awa-vmenu-search-empty-li');
                }
            );
        }

        function ensureSearchEmptyRow() {
            var existing = self.panel.querySelector('[data-role="awa-vmenu-search-empty"]');
            if (existing) {
                return existing.closest('li');
            }
            if (!searchRow) {
                return null;
            }
            var li = document.createElement('li');
            li.className = 'awa-vmenu-search-empty-li';
            li.setAttribute('data-role', 'awa-vmenu-search-empty');
            li.setAttribute('role', 'none');
            li.innerHTML = '<div class="awa-vmenu-search-empty" aria-live="polite">' +
                '<span class="awa-vmenu-search-empty-icon" aria-hidden="true">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" focusable="false" aria-hidden="true">' +
                '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></span>' +
                '<p class="awa-vmenu-search-empty-text"></p></div>';
            searchRow.insertAdjacentElement('afterend', li);
            return li;
        }

        function filterItems(query) {
            var rawQuery = (query || '').trim();
            var q = normalize(rawQuery);
            var visibleCount = 0;

            getFilterableItems().forEach(function (li) {
                var labelEl = li.querySelector('.navigation__label')
                    || li.querySelector('a.level-top, a.navigation__link');
                var label = normalize(labelEl ? labelEl.textContent : '');
                var match = !q || label.indexOf(q) !== -1;
                li.style.display = match ? '' : 'none';
                if (match) {
                    visibleCount += 1;
                }
            });

            self.panel.querySelectorAll(':scope > .awa-vmenu__divider, :scope > .awa-vmenu__section-label').forEach(function (el) {
                if (!q) {
                    el.style.display = '';
                    return;
                }
                var nextVisible = Array.prototype.find.call(
                    getFilterableItems(),
                    function (item) { return item.style.display !== 'none'; }
                );
                el.style.display = nextVisible ? '' : 'none';
            });

            var expandLink = self.panel.querySelector(':scope > li.expand-category-link');
            if (expandLink) {
                expandLink.style.display = q ? 'none' : '';
            }

            var emptyLi = self.panel.querySelector('[data-role="awa-vmenu-search-empty"]');
            emptyLi = emptyLi ? emptyLi.closest('li') : null;
            if (q && visibleCount === 0) {
                emptyLi = ensureSearchEmptyRow();
                if (emptyLi) {
                    var textEl = emptyLi.querySelector('.awa-vmenu-search-empty-text');
                    if (textEl) {
                        textEl.innerHTML = 'Nenhuma categoria para <span class="awa-vmenu-search-empty-query"></span>';
                        var queryEl = textEl.querySelector('.awa-vmenu-search-empty-query');
                        if (queryEl) {
                            queryEl.textContent = rawQuery;
                        }
                    }
                    emptyLi.classList.add('is-visible');
                    emptyLi.style.display = '';
                }
            } else if (emptyLi) {
                emptyLi.classList.remove('is-visible');
                emptyLi.style.display = 'none';
            }

            if (self.status) {
                if (q) {
                    self.status.textContent = visibleCount > 0
                        ? visibleCount + ' categoria' + (visibleCount !== 1 ? 's' : '') + ' encontrada' + (visibleCount !== 1 ? 's' : '')
                        : 'Nenhuma categoria para "' + rawQuery + '"';
                } else if (self.isOpen) {
                    self.status.textContent = 'Menu de departamentos aberto.';
                }
            }

            if (self.isOpen) {
                self.schedulePanelHeight();
            }
        }

        input.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var value = input.value;
            searchTimer = window.setTimeout(function () {
                filterItems(value);
            }, 120);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && input.value) {
                input.value = '';
                filterItems('');
                e.stopPropagation();
            }
        });
    };

    /* ── MobileDrawer ───────────────────────────────────────────────── */
    var mobileDrawer = {
        toggle: null,
        shell: null,
        overlay: null,
        lastFocus: null,
        trapHandler: null,
        open: false,
        closing: false,

        ensureOverlay: function () {
            if (this.overlay) {
                return this.overlay;
            }
            this.overlay = document.querySelector('.awa-mobile-drawer-overlay');
            if (this.overlay) {
                return this.overlay;
            }
            this.overlay = document.createElement('button');
            this.overlay.type = 'button';
            this.overlay.className = 'awa-mobile-drawer-overlay';
            this.overlay.setAttribute('aria-label', 'Fechar menu');
            this.overlay.setAttribute('aria-hidden', 'true');
            this.overlay.setAttribute('tabindex', '-1');
            document.body.appendChild(this.overlay);
            return this.overlay;
        },

        ensureDrawerHeader: function () {
            var shell = this.shell || resolveDrawerShell();
            if (!shell) {
                return null;
            }
            var header = shell.querySelector('.awa-menu-drawer-header');
            if (header) {
                return header;
            }
            header = document.createElement('div');
            header.className = 'awa-menu-drawer-header';
            header.setAttribute('data-awa-menu-drawer-header', 'true');

            var title = document.createElement('p');
            title.className = 'awa-menu-drawer-header__title';
            title.textContent = 'Departamentos';

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'awa-nav-close awa-mobile-drawer-close';
            closeBtn.setAttribute('aria-label', 'Fechar menu');
            closeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';

            header.appendChild(title);
            header.appendChild(closeBtn);
            shell.insertBefore(header, shell.firstChild);

            var self = this;
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                self.closeDrawer();
            });

            return header;
        },

        syncShell: function (isOpen) {
            var shell = this.shell || resolveDrawerShell();
            if (!shell && isOpen) {
                this.shell = resolveDrawerShell();
                shell = this.shell;
            }
            if (!shell) {
                return;
            }

            var targets = getDrawerTargets(shell);
            targets.forEach(function (target) {
                target.classList.toggle('is-awa-mobile-open', isOpen);
                if (isOpen) {
                    unlockDrawerHosts(target);
                    target.style.setProperty('position', 'fixed', 'important');
                    target.style.setProperty('top', '0', 'important');
                    target.style.setProperty('left', '0', 'important');
                    target.style.setProperty('width', 'min(86vw, 360px)', 'important');
                    target.style.setProperty('height', '100dvh', 'important');
                    target.style.setProperty('max-height', '100dvh', 'important');
                    target.style.setProperty('min-height', '0', 'important');
                    target.style.setProperty('z-index', '1300', 'important');
                    target.style.setProperty('display', 'block', 'important');
                    target.style.setProperty('visibility', 'visible', 'important');
                    target.style.setProperty('opacity', '1', 'important');
                    target.style.setProperty('pointer-events', 'auto', 'important');
                    target.style.setProperty('overflow-y', 'auto', 'important');
                    target.style.setProperty('overflow-x', 'hidden', 'important');
                    target.style.setProperty('padding', '16px 12px calc(24px + env(safe-area-inset-bottom))', 'important');
                    target.style.setProperty('box-sizing', 'border-box', 'important');
                    target.style.setProperty('background', 'var(--awa-surface, #fff)', 'important');
                    target.style.setProperty('box-shadow', '8px 0 28px rgb(15 23 42 / 15%)', 'important');
                    slideDrawerIn(target);
                } else {
                    applyMenuLinkTypography(target, false);
                    ['display', 'visibility', 'opacity', 'pointer-events', 'position', 'width', 'height', 'max-height', 'min-height', 'z-index', 'transform', 'top', 'left', 'overflow-y', 'overflow-x', 'padding', 'box-sizing', 'background', 'box-shadow', 'transition'].forEach(function (p) {
                        target.style.removeProperty(p);
                    });
                }
            });

            syncDrawerToggleAria(isOpen);

            if (isOpen && isMobile()) {
                this.shell = shell;
                this.ensureDrawerHeader();
                targets.forEach(function (target) {
                    applyMenuLinkTypography(target, true);
                });
                var searchRow = shell.querySelector('[data-role="awa-vmenu-search-row"]');
                if (searchRow) {
                    searchRow.style.setProperty('display', 'block', 'important');
                }
                var panel = shell.querySelector('[data-role="awa-vertical-menu-panel"]');
                if (panel) {
                    if (panel.getAttribute('aria-hidden') !== 'false') {
                        panel.setAttribute('aria-hidden', 'false');
                    }
                    panel.setAttribute('data-awa-menu-state', 'open');
                    panel.classList.add('menu-open', 'vmm-open');
                    panel.style.setProperty('display', 'block', 'important');
                    panel.style.setProperty('visibility', 'visible', 'important');
                    panel.style.setProperty('opacity', '1', 'important');
                    panel.style.setProperty('position', 'static', 'important');
                    panel.style.setProperty('width', '100%', 'important');
                    panel.style.setProperty('height', 'auto', 'important');
                    panel.style.setProperty('max-height', 'none', 'important');
                    panel.style.setProperty('overflow', 'visible', 'important');
                }
                applyMenuLabelNormalization(panel || shell);
                applyTruncationTitles(panel || shell);
            } else {
                var closedPanel = shell.querySelector('[data-role="awa-vertical-menu-panel"]');
                if (closedPanel) {
                    if (closedPanel.getAttribute('aria-hidden') !== 'true') {
                        closedPanel.setAttribute('aria-hidden', 'true');
                    }
                    closedPanel.setAttribute('data-awa-menu-state', 'closed');
                    closedPanel.classList.remove('menu-open', 'vmm-open');
                    ['display', 'visibility', 'opacity', 'position', 'width', 'height', 'max-height', 'overflow'].forEach(function (p) {
                        closedPanel.style.removeProperty(p);
                    });
                }
            }
        },

        openDrawer: function () {
            if (!isMobile() || this.open) {
                return;
            }
            var self = this;
            this.lastFocus = document.activeElement;
            this.open = true;
            document.body.classList.add('nav-open', 'awa-menu-drawer-open');
            document.body.classList.remove('awa-nav-preflight');
            this.syncShell(true);
            syncDrawerToggleAria(true);
            if (this.toggle) {
                this.toggle.setAttribute('aria-expanded', 'true');
            }
            var ov = this.ensureOverlay();
            ov.classList.add('is-active');
            ov.setAttribute('aria-hidden', 'false');
            var focusDelay = drawerMotionEnabled() ? 300 : 0;
            window.setTimeout(function () {
                var shell = self.shell || resolveDrawerShell();
                var closeBtn = shell && shell.querySelector('.awa-mobile-drawer-close');
                if (closeBtn) {
                    closeBtn.focus();
                    return;
                }
                var focusables = getFocusables(shell);
                if (focusables.length) {
                    focusables[0].focus();
                }
            }, focusDelay);
            if (this.trapHandler) {
                document.addEventListener('keydown', this.trapHandler);
            }
        },

        closeDrawer: function () {
            if (!this.open || this.closing) {
                return;
            }
            var self = this;
            if (this.trapHandler) {
                document.removeEventListener('keydown', this.trapHandler);
            }
            this.closing = true;
            var targets = getDrawerTargets(this.shell);
            var duration = drawerMotionEnabled() ? 280 : 0;

            if (this.overlay) {
                this.overlay.classList.remove('is-active');
                this.overlay.setAttribute('aria-hidden', 'true');
            }
            targets.forEach(slideDrawerOut);

            window.setTimeout(function () {
                self.closing = false;
                self.open = false;
                document.body.classList.remove('nav-open', 'awa-menu-drawer-open', 'awa-mobile-drawer-open', 'nav-before-open');
                self.syncShell(false);
                syncDrawerToggleAria(false);
                if (self.toggle) {
                    self.toggle.setAttribute('aria-expanded', 'false');
                }
                if (self.lastFocus && typeof self.lastFocus.focus === 'function') {
                    self.lastFocus.focus();
                } else if (self.toggle) {
                    self.toggle.focus();
                }
                self.lastFocus = null;
            }, duration);
        },

        bind: function () {
            var self = this;
            this.toggle = document.querySelector('[data-awa-nav-toggle="true"]');
            this.shell = resolveDrawerShell();
            if (!this.toggle) {
                return;
            }

            this.trapHandler = function (e) {
                if (e.key !== 'Tab' || !self.open) {
                    return;
                }
                var focusables = getFocusables(self.shell);
                if (!focusables.length) {
                    return;
                }
                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            };

            if (
                isMobile()
                && (
                    document.body.classList.contains('nav-open')
                    || document.body.classList.contains('awa-nav-preflight')
                )
                && !this.open
            ) {
                this.open = true;
                document.body.classList.add('awa-menu-drawer-open');
                document.body.classList.remove('awa-nav-preflight');
                this.syncShell(true);
                if (this.toggle) {
                    this.toggle.setAttribute('aria-expanded', 'true');
                }
            }

            this.toggle.addEventListener('click', function (e) {
                if (!isMobile()) {
                    return;
                }
                e.preventDefault();
                if (self.open) {
                    self.closeDrawer();
                } else {
                    self.openDrawer();
                }
            });

            document.querySelectorAll('.toggle-nav-footer, [data-action="toggle-nav"]').forEach(function (btn) {
                if (btn === self.toggle || btn.getAttribute('data-awa-nav-toggle') === 'true') {
                    return;
                }
                btn.addEventListener('click', function (e) {
                    if (!isMobile()) {
                        return;
                    }
                    e.preventDefault();
                    if (self.open) {
                        self.closeDrawer();
                    } else {
                        self.openDrawer();
                    }
                });
            });

            document.querySelectorAll('.awa-nav-close, .vmm-mobile-close').forEach(function (btn) {
                if (btn.dataset.awaDrawerCloseBound === '1') {
                    return;
                }
                btn.dataset.awaDrawerCloseBound = '1';
                btn.addEventListener('click', function (e) {
                    if (!isMobile() || !self.open) {
                        return;
                    }
                    e.preventDefault();
                    self.closeDrawer();
                });
            });

            this.ensureOverlay().addEventListener('click', function () {
                self.closeDrawer();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && self.open) {
                    self.closeDrawer();
                }
            });
        }
    };

    /* ── HorizontalNav ──────────────────────────────────────────────── */
    function HorizontalNav() {
        this.root = document.querySelector('.navigation.custommenu.main-nav');
    }

    HorizontalNav.prototype.bind = function () {
        if (!this.root) {
            return;
        }
        this.root.setAttribute('role', 'navigation');
        this.root.querySelectorAll('a.level-top').forEach(function (link) {
            if (!link.getAttribute('tabindex')) {
                link.setAttribute('tabindex', '0');
            }
        });
        this.root.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
                return;
            }
            var links = Array.prototype.slice.call(this.querySelectorAll('a.level-top, .top-menu > li > a'));
            var idx = links.indexOf(document.activeElement);
            if (idx < 0) {
                return;
            }
            e.preventDefault();
            var next = e.key === 'ArrowRight' ? idx + 1 : idx - 1;
            if (links[next]) {
                links[next].focus();
            }
        }.bind(this.root));
    };

    /* ── Widget entry (per vertical menu nav) ───────────────────────── */
    return function (config, element) {
        if (!window.__AWA_MENU_V2) {
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H17',
                'awa-menu-controller.js:widget:guard',
                'Widget exited because __AWA_MENU_V2 is falsy',
                {
                    href: window.location.href,
                    markerType: typeof window.__AWA_MENU_V2,
                    markerValue: String(window.__AWA_MENU_V2)
                }
            );
            // #endregion
            return;
        }

        var nav = element && element.nodeType === 1
            ? element
            : document.querySelector('[data-role="awa-vertical-menu"]');
        if (!nav) {
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H18',
                'awa-menu-controller.js:widget:no-nav',
                'Widget could not resolve vertical menu root',
                {
                    href: window.location.href,
                    elementProvided: !!(element && element.nodeType === 1),
                    selectorCount: document.querySelectorAll('[data-role="awa-vertical-menu"]').length
                }
            );
            // #endregion
            return;
        }
        // #region agent log
        sendDebugLog(
            'pre-fix',
            'H19',
            'awa-menu-controller.js:widget:nav-found',
            'Widget resolved vertical menu root',
            {
                href: window.location.href,
                navClassName: nav.className,
                triggerCountInNav: nav.querySelectorAll('[data-role="awa-vertical-menu-trigger"]').length,
                panelCountInNav: nav.querySelectorAll('[data-role="awa-vertical-menu-panel"]').length,
                navInSticky: !!nav.closest('.header-wrapper-sticky'),
                navRectW: Math.round(nav.getBoundingClientRect().width),
                navRectH: Math.round(nav.getBoundingClientRect().height)
            }
        );
        // #endregion
        var dept = new DeptMenu(nav, config || {});
        var flyout = new FlyoutPortal(nav.querySelector('[data-role="awa-vertical-menu-panel"]') || nav);
        try {
            dept.bind(flyout);
            flyout.mount();
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H19',
                'awa-menu-controller.js:widget:bind-success',
                'Widget bind and flyout mount completed',
                {
                    href: window.location.href,
                    navReadyFlagBeforeSet: nav.dataset.awaMenuControllerReady || '',
                    portalRootClass: (flyout.root && flyout.root.className) || ''
                }
            );
            // #endregion
        } catch (bindErr) {
            // #region agent log
            sendDebugLog(
                'pre-fix',
                'H22',
                'awa-menu-controller.js:widget:bind-error',
                'Widget bind threw runtime error',
                {
                    href: window.location.href,
                    message: bindErr && bindErr.message ? String(bindErr.message) : '',
                    stack: bindErr && bindErr.stack ? String(bindErr.stack).slice(0, 180) : ''
                }
            );
            // #endregion
        }

        if (!DOC_BOOTED) {
            DOC_BOOTED = true;
            ensureRuntimeMenuStyles();
            mobileDrawer.bind();
            (new HorizontalNav()).bind();
            document.body.classList.add('awa-menu-v2-ready');
        }

        nav.dataset.awaMenuControllerReady = '1';
    };
});
