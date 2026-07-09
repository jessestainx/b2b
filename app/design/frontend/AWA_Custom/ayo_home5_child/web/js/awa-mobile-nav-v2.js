/**
 * awa-mobile-nav — Mobile bottom nav drawer (toggle-nav-footer). Versão v3.
 *
 * Bugs corrigidos:
 * - Não usa 'nav-open' no body (evita overlay do RokanThemes sobrepor o drawer)
 * - Não aplica transform no outer (transform no outer cria containing-block para
 *   position:fixed do panel, posicionando-o relativo ao outer off-screen)
 * - Overlay usa classe única 'awa-custom-drawer-overlay' (evita conflito com tema)
 * - Botão .awa-nav-close recebe z-index 10002 para ficar acima do overlay
 */
define(['jquery'], function ($) {
    'use strict';

    if (window.__AWA_MENU_V2) {
        return {};
    }

    if (window.__awaMobileNavInit) { return {}; }
    window.__awaMobileNavInit = true;

    let PANEL_SEL     = '.section-items.nav-sections.category-dropdown-items.awa-header-primary-nav';
    let OUTER_SEL     = '.sections.nav-sections.category-dropdown';
    let CONTENT_SEL   = '.section-item-content.nav-sections';
    let OPEN_CLS      = 'is-awa-mobile-open';
    let BODY_OPEN_CLS = 'awa-mobile-drawer-open';
    let isDrawerOpen  = false;
    let overlay       = null;

    function isMobile() { return window.innerWidth < 992; }

    function getOverlay() {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'awa-custom-drawer-overlay';
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
        let menuPanel;
        let trigger;
        if (!panel) { return; }
        menuPanel = panel.querySelector('[data-role="awa-vertical-menu-panel"]');
        trigger = panel.querySelector('[data-role="awa-vertical-menu-trigger"]');

        /*
         * IMPORTANTE: Não aplicar transform no outer.
         * transform em um ancestral cria um novo containing-block para
         * position:fixed, fazendo o panel se posicionar relativo ao outer
         * (que está off-screen) em vez do viewport.
         */
        if (outer) {
            outer.style.setProperty('display',    'block',   'important');
            outer.style.setProperty('visibility', 'visible', 'important');
            outer.style.setProperty('opacity',    '1',       'important');
        }

        /* Posicionar o panel como drawer fixo na lateral esquerda */
        panel.style.setProperty('display',      'block',              'important');
        panel.style.setProperty('visibility',   'visible',            'important');
        panel.style.setProperty('opacity',      '1',                  'important');
        panel.style.setProperty('transform',    'none',               'important');
        panel.style.setProperty('position',     'fixed',              'important');
        panel.style.setProperty('top',          '0',                  'important');
        panel.style.setProperty('left',         '0',                  'important');
        panel.style.setProperty('bottom',       '0',                  'important');
        panel.style.setProperty('width',        'min(86vw, 360px)',   'important');
        panel.style.setProperty('max-width',    '360px',              'important');
        panel.style.setProperty('height',       '100vh',              'important');
        panel.style.setProperty('overflow-y',   'auto',               'important');
        panel.style.setProperty('overflow-x',   'hidden',             'important');
        panel.style.setProperty('background',   '#ffffff',            'important');
        panel.style.setProperty('z-index',      '10001',              'important');
        panel.style.setProperty('box-shadow',   '4px 0 20px rgba(15,23,42,.25)', 'important');

        if (content) {
            content.style.setProperty('display',    'block',   'important');
            content.style.setProperty('visibility', 'visible', 'important');
            content.style.setProperty('opacity',    '1',       'important');
            content.style.setProperty('position',   'static',  'important');
            content.style.setProperty('width',      '100%',    'important');
            content.style.setProperty('height',     'auto',    'important');
            content.style.setProperty('transform',  'none',    'important');
        }

        if (menuPanel) {
            menuPanel.setAttribute('aria-hidden', 'false');
            menuPanel.setAttribute('data-awa-menu-state', 'open');
            menuPanel.classList.add('menu-open', 'vmm-open');
            menuPanel.style.setProperty('display',    'block',   'important');
            menuPanel.style.setProperty('visibility', 'visible', 'important');
            menuPanel.style.setProperty('opacity',    '1',       'important');
            menuPanel.style.setProperty('position',   'static',  'important');
            menuPanel.style.setProperty('width',      '100%',    'important');
            menuPanel.style.setProperty('height',     'auto',    'important');
            menuPanel.style.setProperty('max-height', 'none',    'important');
            menuPanel.style.setProperty('overflow',   'visible', 'important');
        }

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }

        panel.classList.add(OPEN_CLS);
        document.body.classList.add(BODY_OPEN_CLS);
        /* Não adicionamos 'nav-open' — evita o overlay nativo do RokanThemes */

        /* Overlay semitransparente atrás do drawer */
        let ov = getOverlay();
        ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:10000;display:block;';
        ov.addEventListener('click', closeDrawer, { once: true });

        /* Elevar botão fechar acima do overlay (z-index CSS padrão: 120) */
        let closeEl = document.querySelector('.awa-nav-close');
        if (closeEl) { closeEl.style.setProperty('z-index', '10002', 'important'); }

        let btn = document.querySelector('.toggle-nav-footer');
        if (btn) { btn.setAttribute('aria-expanded', 'true'); }

        isDrawerOpen = true;
    }

    function closeDrawer() {
        let panel   = document.querySelector(PANEL_SEL);
        let outer   = document.querySelector(OUTER_SEL);
        let content = document.querySelector(CONTENT_SEL);
        let menuPanel = panel ? panel.querySelector('[data-role="awa-vertical-menu-panel"]') : null;
        let trigger = panel ? panel.querySelector('[data-role="awa-vertical-menu-trigger"]') : null;

        [outer, panel, content].forEach(function (el) {
            if (el) { el.removeAttribute('style'); }
        });

        if (menuPanel) {
            menuPanel.setAttribute('aria-hidden', 'true');
            menuPanel.setAttribute('data-awa-menu-state', 'closed');
            menuPanel.classList.remove('menu-open', 'vmm-open');
            menuPanel.removeAttribute('style');
        }

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }

        if (panel) { panel.classList.remove(OPEN_CLS); }
        document.body.classList.remove(BODY_OPEN_CLS);

        if (overlay) { overlay.style.display = 'none'; }

        /* Restaurar z-index do botão fechar */
        let closeEl = document.querySelector('.awa-nav-close');
        if (closeEl) { closeEl.style.removeProperty('z-index'); }

        let btn = document.querySelector('.toggle-nav-footer');
        if (btn) { btn.setAttribute('aria-expanded', 'false'); }

        isDrawerOpen = false;
    }

    function init() {
        $(document).on('click', '.toggle-nav-footer', function (e) {
            e.preventDefault();
            if (!isMobile()) { return; }
            if (isDrawerOpen) { closeDrawer(); } else { openDrawer(); }
        });

        $(document).on('click', '.awa-nav-close, .vmm-mobile-close', function () {
            if (isDrawerOpen) { closeDrawer(); }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && isDrawerOpen) { closeDrawer(); }
        });

        $(window).on('resize', function () {
            if (!isMobile() && isDrawerOpen) { closeDrawer(); }
        });
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    }

    return { open: openDrawer, close: closeDrawer };
});
