/**
 * Mobile accordion for vertical menu only.
 * Footer accordion: awaFooterInteractions (GrupoAwamotos_Theme).
 */
(function () {
    'use strict';

    if (window.__awaMobileAccordionInit) {
        return;
    }
    window.__awaMobileAccordionInit = true;

    function isMobile() {
        return window.matchMedia('(max-width: 767px)').matches;
    }

    function initVmenuAccordion() {
        var vmenu = document.querySelector('.block-vertical-nav, .block.block-vmenu');
        if (!vmenu || vmenu.dataset.awaVmenuAccordionBound === '1') {
            return;
        }

        vmenu.dataset.awaVmenuAccordionBound = '1';
        vmenu.addEventListener('click', function (e) {
            if (!isMobile()) {
                return;
            }

            var link = e.target.closest('.vela-vertical-menu > li > a');
            if (!link) {
                return;
            }

            var li = link.parentElement;
            var submenu = li.querySelector('.submenu, .sub-menu');
            if (!submenu) {
                return;
            }

            e.preventDefault();
            li.classList.toggle('is-open');
        });
    }

    function init() {
        initVmenuAccordion();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
