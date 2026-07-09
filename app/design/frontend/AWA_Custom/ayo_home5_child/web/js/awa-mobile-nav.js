/**
 * awa-mobile-nav — Mobile bottom nav drawer (toggle-nav-footer).
 * Abre/fecha o drawer de categorias (.section-items.nav-sections) ao clicar
 * no botão "Menu" da barra inferior mobile. Versão v2.
 */
define(['jquery'], function ($) {
    'use strict';

    if (window.__AWA_MENU_V2) {
        return {};
    }

    if (window.__awaMobileNavInit) return {};
    window.__awaMobileNavInit = true;

    let PANEL_SEL     = '.section-items.nav-sections.category-dropdown-items.awa-header-primary-nav';
    let OUTER_SEL     = '.sections.nav-sections.category-dropdown';
    let CONTENT_SEL   = '.section-item-content.nav-sections';
    let OPEN_CLS      = 'is-awa-mobile-open';
    let BODY_OPEN_CLS = 'awa-mobile-drawer-open';
    let NAV_OPEN_CLS  = 'nav-open';
    let isDrawerOpen  = false;
    let overlay       = null;

    /* -------- helpers -------- */
    function isMobile() { return window.innerWidth < 992; }

    function getOverlay() {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'awa-mobile-overlay';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    function openDrawer() {
        if (!isMobile()) { return; }
        let panel   = document.querySelector(PANEL_SEL);
        let outer   = document.querySelector(OUTER_SEL);
        let content = document.querySelector(CONTENT_SEL);
        if (!panel) { return; }

        /* inline overrides — batem qualquer display:none !important do CSS */
        [outer, panel, content].forEach(function (el) {
            if (!el) { return; }
            el.style.setProperty('display',          'block',              'important');
            el.style.setProperty('visibility',       'visible',            'important');
            el.style.setProperty('opacity',          '1',                  'important');
            el.style.setProperty('transform',        'translateX(0)',       'important');
        });

        /* Posiciona o panel como drawer fixo */
        panel.style.setProperty('position',   'fixed',              'important');
        panel.style.setProperty('top',        '0',                  'important');
        panel.style.setProperty('left',       '0',                  'important');
        panel.style.setProperty('bottom',     '0',                  'important');
        panel.style.setProperty('width',      'min(86vw, 360px)',    'important');
        panel.style.setProperty('max-width',  '360px',              'important');
        panel.style.setProperty('height',     '100vh',              'important');
        panel.style.setProperty('overflow-y', 'auto',               'important');
        panel.style.setProperty('overflow-x', 'hidden',             'important');
        panel.style.setProperty('background', '#ffffff',            'important');
        panel.style.setProperty('z-index',    '10001',              'important');
        panel.style.setProperty('box-shadow', '4px 0 20px rgba(15,23,42,.25)', 'important');

        if (content) {
            content.style.setProperty('position', 'static', 'important');
            content.style.setProperty('width',    '100%',   'important');
            content.style.setProperty('height',   'auto',   'important');
        }

        panel.classList.add(OPEN_CLS);
        document.body.classList.add(BODY_OPEN_CLS);
        document.body.classList.add(NAV_OPEN_CLS);
        document.documentElement.classList.add(NAV_OPEN_CLS);

        let ov = getOverlay();
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;display:block;';
        ov.addEventListener('click', closeDrawer, { once: true });

        /* aria */
        let btn = document.querySelector('.toggle-nav-footer');
        if (btn) { btn.setAttribute('aria-expanded', 'true'); }

        isDrawerOpen = true;
    }

    function closeDrawer() {
        let panel   = document.querySelector(PANEL_SEL);
        let outer   = document.querySelector(OUTER_SEL);
        let content = document.querySelector(CONTENT_SEL);

        [outer, panel, content].forEach(function (el) {
            if (el) { el.removeAttribute('style'); }
        });

        if (panel) { panel.classList.remove(OPEN_CLS); }
        document.body.classList.remove(BODY_OPEN_CLS);
        document.body.classList.remove(NAV_OPEN_CLS);
        document.documentElement.classList.remove(NAV_OPEN_CLS);

        if (overlay) { overlay.style.display = 'none'; }

        let btn = document.querySelector('.toggle-nav-footer');
        if (btn) { btn.setAttribute('aria-expanded', 'false'); }

        isDrawerOpen = false;
    }

    function init() {
        /* Botão Menu da barra inferior */
        $(document).on('click', '.toggle-nav-footer', function (e) {
            e.preventDefault();
            if (!isMobile()) { return; }
            if (isDrawerOpen) { closeDrawer(); } else { openDrawer(); }
        });

        /* Botões fechar dentro do drawer */
        $(document).on('click', '.awa-nav-close, .vmm-mobile-close', function () {
            if (isDrawerOpen) { closeDrawer(); }
        });

        /* ESC */
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && isDrawerOpen) { closeDrawer(); }
        });

        /* Resize — fechar drawer se voltou ao desktop */
        $(window).on('resize', function () {
            if (!isMobile() && isDrawerOpen) { closeDrawer(); }
        });

        /* Fecha o drawer ao clicar fora (fallback legacy) */
        $(document).on('click', '.page-wrapper', function (e) {
            if ($(document.body).hasClass(NAV_OPEN_CLS) &&
                !isDrawerOpen &&
                !$(e.target).closest('.nav-sections, .nav-toggle').length) {
                var $closeBtn = $('.nav-toggle');
                if ($closeBtn.length) { $closeBtn.trigger('click'); }
            }
        });
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    }

    return { open: openDrawer, close: closeDrawer };
});
