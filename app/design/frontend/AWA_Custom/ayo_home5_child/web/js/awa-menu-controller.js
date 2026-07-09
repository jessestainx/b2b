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
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function isDesktop() {
        return window.matchMedia
            ? window.matchMedia('(min-width: ' + DESKTOP_MIN + 'px)').matches
            : window.innerWidth >= DESKTOP_MIN;
    }

    function isMobile() {
        return !isDesktop();
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

    /* ── FlyoutPortal ─────────────────────────────────────────────────── */
    function FlyoutPortal(root) {
        var self = this;
        this.root = root;
        this.portals = [];
        this._onEnter = function (e) {
            var li = e.target.closest('li.level0.parent, li.level0.navigation__item--parent');
            if (li && self.root.contains(li)) {
                self.attach(li);
            }
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
            return;
        }
        this.root.dataset.awaFlyoutMounted = '1';
        this.root.addEventListener('mouseenter', this._onEnter, true);
        this.root.addEventListener('mouseleave', this._onLeave, true);
        window.addEventListener('scroll', this._reposition, { passive: true });
        window.addEventListener('resize', this._reposition, { passive: true });
    };

    FlyoutPortal.prototype.findSubmenu = function (li) {
        return li.querySelector(':scope > .submenu, :scope > .level0.submenu, :scope > .navigation__submenu');
    };

    FlyoutPortal.prototype.attach = function (li) {
        if (!isDesktop()) {
            return;
        }
        var submenu = this.findSubmenu(li);
        if (!submenu || submenu.dataset.awVmfPortaled === '1') {
            return;
        }
        var portal = document.createElement('div');
        portal.className = PORTAL_CLASS;
        portal.dataset.awVmfLiMenu = li.getAttribute('data-menu') || '';
        portal.appendChild(submenu);
        document.body.appendChild(portal);
        submenu.dataset.awVmfPortaled = '1';
        li.classList.add(ACTIVE_CLASS);
        li.setAttribute('data-awa-submenu-open', 'true');
        this.position(li, portal);
        this.portals.push(portal);

        var active = document.activeElement;
        if (active && li.contains(active)) {
            var firstSubLink = portal.querySelector('a[href]');
            if (firstSubLink) {
                window.requestAnimationFrame(function () {
                    firstSubLink.focus();
                });
            }
        }

        this.bindPortalKeyboard(portal, li);
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
            return;
        }
        FloatingUIDOM.computePosition(li, portal, {
            placement: 'right-start',
            middleware: [
                FloatingUIDOM.offset({ mainAxis: 0, crossAxis: 0 }),
                FloatingUIDOM.flip(),
                FloatingUIDOM.shift({ padding: 8 })
            ]
        }).then(function (data) {
            Object.assign(portal.style, {
                position: 'fixed',
                left: data.x + 'px',
                top: data.y + 'px',
                zIndex: '99990'
            });
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
        document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (p) { p.remove(); });
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

        void panelEl.offsetHeight;

        var maxPx = Math.min(window.innerHeight * 0.7, 560);
        var contentPx = Math.max(this.measurePanelContentHeight(), panelEl.scrollHeight);
        var target = Math.min(Math.max(contentPx, 160), maxPx);

        panelEl.style.setProperty('max-height', 'min(70vh, 560px)', 'important');
        panelEl.style.setProperty('height', target + 'px', 'important');
        panelEl.style.setProperty('min-height', '120px', 'important');
        applyMenuLinkTypography(panelEl, true);
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
                this.syncSearchRow(true);
                applyMenuLinkTypography(this.panel, true);
                applyMenuLabelNormalization(this.panel);
                applyTruncationTitles(this.panel);
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
        if (!this.trigger || !this.panel) {
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
            return;
        }

        var nav = element && element.nodeType === 1
            ? element
            : document.querySelector('[data-role="awa-vertical-menu"]');
        if (!nav) {
            return;
        }
        var dept = new DeptMenu(nav, config || {});
        var flyout = new FlyoutPortal(nav.querySelector('[data-role="awa-vertical-menu-panel"]') || nav);
        try {
            dept.bind(flyout);
            flyout.mount();
        } catch (bindErr) {
        }

        if (!DOC_BOOTED) {
            DOC_BOOTED = true;
            mobileDrawer.bind();
            (new HorizontalNav()).bind();
            document.body.classList.add('awa-menu-v2-ready');
        }

        nav.dataset.awaMenuControllerReady = '1';
    };
});
