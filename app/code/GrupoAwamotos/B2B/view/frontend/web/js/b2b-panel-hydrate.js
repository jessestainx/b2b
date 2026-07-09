/**
 * B2B Panel Hydration — FPC pages (home, category, PDP).
 *
 * On account pages the panel is server-rendered; this script handles all other pages.
 *
 * FAST PATH: reads `mage-cache-storage` localStorage directly (same source as
 * awa-header-account-prompt.js / Magento customer-data). This runs synchronously
 * at DOMContentLoaded, injecting the panel before the user can click the generic prompt.
 *
 * SLOW PATH: subscribes to Magento customer-data section updates via RequireJS so
 * the panel stays in sync after login / section invalidation.
 *
 * Loaded exclusively via <script defer> — NOT an AMD module.
 * Using define() here causes RequireJS "Mismatched anonymous define()" because
 * the file is never loaded through require(), so no module ID is registered.
 */
(function () {
    'use strict';

    var w = window;
    var d = document;

    /* ─── SVG icon map ─────────────────────────────────────────────────────── */
    var ICONS = {
        user: '<svg class="awa-b2b-panel-icon awa-b2b-panel-icon--user" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        chevron: '<svg class="awa-b2b-panel-icon awa-b2b-panel-icon--chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>',
        building: '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01"/></svg>',
        crown: '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 18h18"/><path d="M5 18l1.5-9 4.5 4 3-6 3 6 4.5-4L19 18z"/></svg>',
        'clock-o': '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        store: '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 9l2-5h14l2 5"/><path d="M5 9v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/><path d="M9 21V12h6v9"/></svg>',
        percent: '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/><path d="M19 5L5 19"/></svg>',
        'credit-card': '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
        tachometer: '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
        'file-text-o': '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>',
        'list-ul': '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
        calculator: '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h2M12 11h2M16 11h0M8 15h2M12 15h2M16 15h0"/></svg>',
        'user-circle-o': '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="10" r="3"/><path d="M6.5 18.5c1.2-2.2 3.3-3.5 5.5-3.5s4.3 1.3 5.5 3.5"/></svg>',
        'sign-out': '<svg class="awa-b2b-panel-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    };

    function icon(name)
    {
        return ICONS[name] || ICONS['store'];
    }

    function esc(val)
    {
        var s = String(val == null ? '' : val);
        return s
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Mirrors B2BHelper::formatDisplayName() (PHP) — title-cases names that
     * come 100% uppercase from the ERP import (e.g. "FERNANDO"), leaving
     * already well-cased names (self-registered customers) untouched.
     */
    function formatDisplayName(val)
    {
        var s = String(val == null ? '' : val).trim();
        if (!s || s !== s.toUpperCase()) {
            return s;
        }
        return s.toLowerCase().replace(/(^|[\s\-'&\/])([a-zà-ÿ])/g, function (match, sep, letter) {
            return sep + letter.toUpperCase();
        });
    }

    /* ─── HTML builder — mirrors status-panel.phtml ─────────────────────────── */
    function buildPanelHtml(data)
    {
        var firstName = esc(formatDisplayName(data.first_name));
        var fullName = esc(formatDisplayName(data.full_name));
        var company = esc(formatDisplayName(data.company));
        var groupName = esc(data.group_name);
        var badgeColor = esc(data.badge_color);
        var badgeIcon = String(data.badge_icon || 'store');
        var discount = parseInt(data.discount, 10) || 0;
        var creditLimit = parseFloat(data.credit_limit) || 0;
        var creditAvailable = parseFloat(data.credit_available) || 0;
        var creditFormatted = esc(data.credit_available_formatted);
        var accountUrl = esc(data.account_url);
        var logoutUrl = esc(data.logout_url);

        var companyDisplay = formatDisplayName(data.company);
        var line2Raw = companyDisplay || data.group_name || '';
        var line2Trigger = line2Raw;
        if (companyDisplay && companyDisplay.length > 24) {
            line2Trigger = data.group_name || companyDisplay.substring(0, 22) + '\u2026';
        }
        var line2Label = esc(line2Trigger || data.group_name || '');
        var line2Title = esc(line2Raw || data.group_name || '');
        var firstLetter = (data.first_name || '').charAt(0).toUpperCase();

        var actionsHtml = '';
        var actions = Array.isArray(data.quick_actions) ? data.quick_actions : [];
        for (var i = 0; i < actions.length; i++) {
            var a = actions[i];
            actionsHtml +=
                '<a href="' + esc(a.url) + '" class="quick-action-link">' +
                '<span class="quick-action-icon">' + icon(String(a.icon)) + '</span>' +
                '<span class="quick-action-label">' + esc(a.label) + '</span>' +
                '</a>';
        }

        var statsHtml = '';
        if (discount > 0 || creditLimit > 0) {
            statsHtml += '<div class="dropdown-section stats-section">';
            if (discount > 0) {
                statsHtml +=
                    '<div class="stat-item discount-stat">' +
                    '<span class="stat-icon">' + icon('percent') + '</span>' +
                    '<span class="stat-content">' +
                    '<span class="stat-value">\u2212' + discount + '%</span>' +
                    '<span class="stat-label">Desconto exclusivo</span>' +
                    '</span></div>';
            }
            if (creditLimit > 0 && creditFormatted) {
                var creditPct = Math.min(100, Math.round((creditAvailable / creditLimit) * 100));
                statsHtml +=
                    '<div class="stat-item credit-stat">' +
                    '<span class="stat-icon">' + icon('credit-card') + '</span>' +
                    '<span class="stat-content">' +
                    '<span class="stat-value">' + creditFormatted + '</span>' +
                    '<span class="stat-label">Cr\u00e9dito dispon\u00edvel</span>' +
                    '</span>' +
                    '<div class="credit-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + creditPct + '">' +
                    '<div class="credit-bar-fill" style="--b2b-credit-width:' + creditPct + '%"></div>' +
                    '</div></div>';
            }
            statsHtml += '</div>';
        }

        var logoutPost = esc(JSON.stringify({ action: data.logout_url, data: { uenc: '' } }));

        return (
            '<div class="b2b-status-panel b2b-status-panel--hydrated" role="region" aria-label="Painel B2B">' +
            '<button type="button" class="b2b-status-trigger" aria-expanded="false" aria-controls="b2b-status-dropdown" aria-haspopup="true">' +
            '<span class="b2b-status-trigger__icon" aria-hidden="true">' + icon('user') + '</span>' +
            '<span class="b2b-status-trigger__text">' +
            '<span class="b2b-status-trigger__line1">Ol\u00e1, ' + firstName + '</span>' +
            '<span class="b2b-status-trigger__line2" title="' + line2Title + '">' + line2Label +
            (discount > 0 ? '<span class="b2b-status-trigger__discount">\u2212' + discount + '%</span>' : '') +
            '</span></span>' +
            '<span class="b2b-status-trigger__chevron" aria-hidden="true">' + icon('chevron') + '</span>' +
            '</button>' +
            '<div id="b2b-status-dropdown" class="b2b-status-dropdown" aria-hidden="true">' +
            '<div class="dropdown-inner">' +
            '<div class="dropdown-section user-section">' +
            '<div class="user-avatar" style="--avatar-color:' + badgeColor + '">' + firstLetter + '</div>' +
            '<div class="user-details">' +
            '<span class="user-name">' + fullName + '</span>' +
            (company ? '<span class="user-company">' + icon('building') + company + '</span>' : '') +
            '<span class="user-tier" style="--tier-color:' + badgeColor + '">' + icon(badgeIcon) + groupName + '</span>' +
            '</div></div>' +
            statsHtml +
            '<div class="dropdown-section actions-section">' +
            '<span class="section-title">Acesso r\u00e1pido</span>' +
            '<nav class="quick-actions" aria-label="A\u00e7\u00f5es r\u00e1pidas B2B">' + actionsHtml + '</nav>' +
            '</div>' +
            '<div class="dropdown-footer">' +
            '<a href="' + accountUrl + '" class="footer-link account-link">' + icon('user-circle-o') + 'Minha conta</a>' +
            '<a href="' + logoutUrl + '" class="footer-link logout-link" data-post="' + logoutPost + '">' + icon('sign-out') + 'Sair</a>' +
            '</div>' +
            '</div></div></div>'
        );
    }

    /* ─── Panel injection ───────────────────────────────────────────────────── */
    var injected = false;

    function injectPanel(data)
    {
        if (injected || d.querySelector('.b2b-status-panel')) {
            injected = true;
            return;
        }

        var rightCol = d.querySelector('.awa-header-right-col, [data-awa-header-right]');
        if (!rightCol) {
            return;
        }

        var wrapper = d.createElement('div');
        wrapper.innerHTML = buildPanelHtml(data);
        var panel = wrapper.firstElementChild;
        if (!panel) {
            return;
        }

        var prompt = rightCol.querySelector('.awa-header-account-prompt');
        if (prompt) {
            rightCol.insertBefore(panel, prompt);
            prompt.style.setProperty('display', 'none', 'important');
            prompt.setAttribute('aria-hidden', 'true');
        } else {
            rightCol.insertBefore(panel, rightCol.firstChild);
        }

        injected = true;

        var trigger = panel.querySelector('.b2b-status-trigger');
        var dropdown = panel.querySelector('.b2b-status-dropdown');
        var shadowOverlayState = null;

        function resolveDesktopTop(triggerRect, margin)
        {
            var top = Math.round(triggerRect.bottom - 1);
            var anchor = trigger
                ? trigger.closest('.awa-main-header__inner, .header.awa-main-header, .header-wrapper-sticky')
                : null;
            var isB2bDashboard = d.body && (
                d.body.classList.contains('b2b-account-dashboard') ||
                d.body.classList.contains('b2b-account-index')
            );

            if (!anchor) {
                anchor = d.querySelector(
                    '.awa-site-header .awa-main-header__inner, ' +
                    '.awa-site-header .header.awa-main-header, ' +
                    '.awa-site-header .header-wrapper-sticky'
                );
            }

            if (anchor) {
                var anchorRect = anchor.getBoundingClientRect();

                if (anchorRect && anchorRect.width > 0 && anchorRect.height > 0 && anchorRect.bottom > 0) {
                    var anchorBottom = Math.round(anchorRect.bottom - 1);

                    if (isB2bDashboard || top - anchorBottom > 12) {
                        top = anchorBottom;
                    }
                }
            }

            return Math.max(margin, top);
        }

        function positionDropdown()
        {
            if (!trigger || !dropdown) {
                return;
            }

            var viewportWidth = d.documentElement.clientWidth || w.innerWidth || 0;
            var viewportHeight = w.innerHeight || d.documentElement.clientHeight || 0;
            var margin = 12;

            dropdown.setAttribute('data-awa-fixed-layer', 'true');
            dropdown.style.setProperty('position', 'fixed', 'important');
            dropdown.style.setProperty('z-index', '100320', 'important');
            dropdown.style.setProperty('max-width', 'calc(100vw - 24px)', 'important');

            if (viewportWidth < 768) {
                dropdown.style.setProperty('top', 'auto', 'important');
                dropdown.style.setProperty('right', '0', 'important');
                dropdown.style.setProperty('bottom', '0', 'important');
                dropdown.style.setProperty('left', '0', 'important');
                dropdown.style.setProperty('width', '100%', 'important');
                dropdown.style.setProperty('max-height', '82vh', 'important');
                return;
            }

            var rect = trigger.getBoundingClientRect();
            var configuredWidth = viewportWidth < 992 ? 360 : 420;
            var width = Math.max(280, Math.min(configuredWidth, viewportWidth - (margin * 2)));
            var left = Math.min(Math.max(margin, rect.right - width), viewportWidth - width - margin);
            var top = resolveDesktopTop(rect, margin);
            var maxHeight = Math.max(240, Math.min(520, viewportHeight - top - margin));

            dropdown.style.setProperty('top', top + 'px', 'important');
            dropdown.style.setProperty('right', 'auto', 'important');
            dropdown.style.setProperty('bottom', 'auto', 'important');
            dropdown.style.setProperty('left', Math.round(left) + 'px', 'important');
            dropdown.style.setProperty('width', Math.round(width) + 'px', 'important');
            dropdown.style.setProperty('max-height', Math.round(maxHeight) + 'px', 'important');
        }

        function resetDropdownPosition()
        {
            if (!dropdown) {
                return;
            }

            dropdown.removeAttribute('data-awa-fixed-layer');
            [
                'position',
                'z-index',
                'inset',
                'top',
                'right',
                'bottom',
                'left',
                'width',
                'max-width',
                'max-height'
            ].forEach(function (property) {
                dropdown.style.removeProperty(property);
            });
        }

        function suppressLegacyShadowOverlay()
        {
            var body = d.body;
            var html = d.documentElement;
            var shadow = d.querySelector('.shadow_bkg_show');
            var properties = ['display', 'opacity', 'visibility', 'pointer-events', 'background-color'];

            if (!shadow) {
                return;
            }

            shadowOverlayState = {
                styles: {},
                ariaHidden: shadow.getAttribute('aria-hidden')
            };

            properties.forEach(function (property) {
                shadowOverlayState.styles[property] = {
                    value: shadow.style.getPropertyValue(property),
                    priority: shadow.style.getPropertyPriority(property)
                };
            });

            if (body) {
                body.classList.remove('nav-open', 'background_shadow_show');
            }
            if (html) {
                html.classList.remove('nav-open', 'background_shadow_show');
            }

            shadow.style.setProperty('display', 'none', 'important');
            shadow.style.setProperty('opacity', '0', 'important');
            shadow.style.setProperty('visibility', 'hidden', 'important');
            shadow.style.setProperty('pointer-events', 'none', 'important');
            shadow.style.setProperty('background-color', 'transparent', 'important');
            shadow.setAttribute('aria-hidden', 'true');
        }

        function restoreLegacyShadowOverlay()
        {
            var shadow = d.querySelector('.shadow_bkg_show');

            if (!shadow || !shadowOverlayState || !shadowOverlayState.styles) {
                return;
            }

            Object.keys(shadowOverlayState.styles).forEach(function (property) {
                var styleState = shadowOverlayState.styles[property];
                if (!styleState || !styleState.value) {
                    shadow.style.removeProperty(property);
                    return;
                }

                shadow.style.setProperty(property, styleState.value, styleState.priority || '');
            });

            if (shadowOverlayState.ariaHidden === null || typeof shadowOverlayState.ariaHidden === 'undefined') {
                shadow.removeAttribute('aria-hidden');
            } else {
                shadow.setAttribute('aria-hidden', shadowOverlayState.ariaHidden);
            }

            shadowOverlayState = null;
        }

        function closeNativeFallback()
        {
            if (!trigger || trigger.getAttribute('aria-expanded') !== 'true') {
                return;
            }

            trigger.setAttribute('aria-expanded', 'false');
            panel.classList.remove('is-open');
            if (dropdown) {
                dropdown.setAttribute('aria-hidden', 'true');
            }
            resetDropdownPosition();
            restoreLegacyShadowOverlay();
        }

        function nativeFallbackToggle(e)
        {
            e.stopPropagation();
            var isOpen = trigger.getAttribute('aria-expanded') === 'true';
            trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            panel.classList.toggle('is-open', !isOpen);
            if (dropdown) {
                dropdown.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
            }

            if (isOpen) {
                resetDropdownPosition();
                restoreLegacyShadowOverlay();
            } else {
                suppressLegacyShadowOverlay();
                positionDropdown();
            }
        }

        function nativeFallbackOutside(e)
        {
            if (trigger && trigger.getAttribute('aria-expanded') === 'true' && !panel.contains(e.target)) {
                closeNativeFallback();
            }
        }

        function nativeFallbackResize()
        {
            if (trigger && trigger.getAttribute('aria-expanded') === 'true') {
                positionDropdown();
            }
        }

        function nativeFallbackScroll()
        {
            closeNativeFallback();
        }

        if (trigger) {
            trigger.addEventListener('click', nativeFallbackToggle);
            d.addEventListener('click', nativeFallbackOutside);
            w.addEventListener('resize', nativeFallbackResize, { passive: true });
            w.addEventListener('scroll', nativeFallbackScroll, { passive: true });
        }

        function runWidgetInit()
        {
            if (typeof w.require !== 'function' || w.require._awaStub) {
                if (typeof w.awaRunWhenRequire === 'function') {
                    w.awaRunWhenRequire(runWidgetInit, { key: 'b2b-panel-widget' });
                } else {
                    w.setTimeout(runWidgetInit, 150);
                }
                return;
            }

            w.require(
                ['jquery', 'mage/translate', 'jquery-ui-modules/widget', 'GrupoAwamotos_B2B/js/header-status-panel'],
                function ($) {
                    if (trigger) {
                        trigger.removeEventListener('click', nativeFallbackToggle);
                        d.removeEventListener('click', nativeFallbackOutside);
                        w.removeEventListener('resize', nativeFallbackResize);
                        w.removeEventListener('scroll', nativeFallbackScroll);
                    }
                    resetDropdownPosition();
                    restoreLegacyShadowOverlay();
                    $(panel).headerStatusPanel();
                }
            );
        }

        runWidgetInit();
    }

    /* ─── Fast-path: read b2b_panel directly from Magento localStorage cache ──
     * Magento stores customer-data sections in `mage-cache-storage`.
     * Reading it here is synchronous and ~0ms — no AJAX, no RequireJS needed.
     * This matches the exact data that customer-data.js would return.
     */
    function readCachedSection(key)
    {
        try {
            var raw = localStorage.getItem('mage-cache-storage');
            if (!raw) {
                return null;
            }
            var storage = JSON.parse(raw);
            return (storage && storage[key]) ? storage[key] : null;
        } catch (e) {
            return null;
        }
    }

    function bootFast()
    {
        if (injected || d.querySelector('.b2b-status-panel')) {
            injected = true;
            return;
        }
        /* Only run for customers with an active session */
        if (d.cookie.indexOf('private_content_version') === -1) {
            return;
        }
        var data = readCachedSection('b2b_panel');
        if (data && data.is_b2b) {
            injectPanel(data);
        }
    }

    /* ─── Slow-path: RequireJS + Magento customer-data subscription ─────────── */
    function bootSlow()
    {
        if (injected) {
            return;
        }
        if (d.querySelector('.b2b-status-panel')) {
            injected = true;
            return;
        }
        if (d.cookie.indexOf('private_content_version') === -1) {
            return;
        }

        if (typeof w.require !== 'function' || w.require._awaStub) {
            w.setTimeout(bootSlow, 150);
            return;
        }

        w.require(['Magento_Customer/js/customer-data'], function (customerData) {
            var b2bSection = customerData.get('b2b_panel');
            var customerSection = customerData.get('customer');

            function onB2bUpdate(sectionData)
            {
                if (sectionData && sectionData.is_b2b) {
                    injectPanel(sectionData);
                }
            }

            b2bSection.subscribe(onB2bUpdate);
            onB2bUpdate(b2bSection());

            /* When customer section shows a logged-in user, eagerly request b2b_panel
             * if the section is not yet in the observable (may differ from localStorage). */
            function onCustomerUpdate(cData)
            {
                if (injected) {
                    return;
                }
                var isLoggedIn = cData && (cData.firstname || cData.id || cData.entity_id);
                if (isLoggedIn) {
                    var current = b2bSection();
                    if (!current || Object.keys(current).length === 0) {
                        customerData.reload(['b2b_panel'], false);
                    }
                }
            }

            customerSection.subscribe(onCustomerUpdate);
            onCustomerUpdate(customerSection());

            customerData.getInitCustomerData().done(function () {
                var current = b2bSection();
                if (current && current.is_b2b) {
                    injectPanel(current);
                } else if (!current || Object.keys(current).length === 0) {
                    w.setTimeout(function () {
                        if (!injected) {
                            customerData.reload(['b2b_panel'], false);
                        }
                    }, 400);
                }
            });
        });
    }

    /* ─── Entry point ───────────────────────────────────────────────────────── */
    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', function () {
            bootFast();   /* synchronous — runs before any click is possible */
            bootSlow();   /* async subscription for updates */
        }, { once: true });
    } else {
        bootFast();
        w.setTimeout(bootSlow, 0);
    }

    d.addEventListener('awa:customer-data-ready', bootSlow, { once: true });
}());
