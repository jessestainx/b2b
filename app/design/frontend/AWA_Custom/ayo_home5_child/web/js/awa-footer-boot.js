(function (w, d) {
    'use strict';

    var INLINE_PROPS = [
        'display', 'grid-template-columns', 'align-items', 'height', 'min-height',
        'grid-column', 'grid-row', 'max-height', 'visibility', 'pointer-events',
        'box-sizing', 'justify-content', 'width', 'max-width', 'gap', 'min-width'
    ];

    function isFooterMobile() {
        return typeof w.matchMedia === 'function' && w.matchMedia('(max-width: 767px)').matches;
    }

    function clearFooterDesktopInlineState() {
        d.querySelectorAll(
            '.awa-footer-categories-expand__inner, [data-awa-categories-toggle], '
            + '.awa-footer-categories-expand__toggle, .awa-footer-categories-expand__heading, '
            + '.awa-footer-categories-expand__panel, #awa-footer-categories-panel, '
            + 'ul.awa-footer-categories-list, ul.awa-footer-categories-list > li, '
            + 'ul.awa-footer-categories-list > li > a'
        ).forEach(function (el) {
            INLINE_PROPS.forEach(function (prop) {
                el.style.removeProperty(prop);
            });
        });
    }

    function applyFooterCategoriesDesktopState() {
        if (typeof w.matchMedia === 'function' && w.matchMedia('(max-width: 767px)').matches) {
            return;
        }

        d.querySelectorAll('.awa-footer-categories-expand__inner').forEach(function (inner) {
            inner.style.setProperty('display', 'grid', 'important');
            inner.style.setProperty('grid-template-columns', 'minmax(96px,max-content) minmax(0,1fr)', 'important');
            inner.style.setProperty('align-items', 'center', 'important');
            inner.style.setProperty('height', 'auto', 'important');
            inner.style.setProperty('min-height', '0', 'important');
        });

        d.querySelectorAll('[data-awa-categories-toggle], .awa-footer-categories-expand__toggle').forEach(function (toggle) {
            toggle.style.setProperty('display', 'none', 'important');
            toggle.style.setProperty('visibility', 'hidden', 'important');
            toggle.style.setProperty('pointer-events', 'none', 'important');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.classList.add('is-expanded');
        });

        d.querySelectorAll('.awa-footer-categories-expand__heading').forEach(function (heading) {
            heading.style.setProperty('grid-column', '1', 'important');
            heading.style.setProperty('grid-row', '1', 'important');
        });

        d.querySelectorAll('.awa-footer-categories-expand__panel, #awa-footer-categories-panel').forEach(function (panel) {
            panel.style.setProperty('grid-column', '2', 'important');
            panel.style.setProperty('grid-row', '1', 'important');
            panel.style.setProperty('display', 'block', 'important');
            panel.style.setProperty('max-height', 'none', 'important');
            panel.style.setProperty('height', 'auto', 'important');
            panel.style.setProperty('visibility', 'visible', 'important');
            panel.hidden = false;
            panel.removeAttribute('hidden');
            panel.removeAttribute('inert');
            panel.setAttribute('aria-hidden', 'false');
        });

        d.querySelectorAll('ul.awa-footer-categories-list').forEach(function (list) {
            /* r13: compact flex pills — avoid 4-col stretch cards (duplicate-border look) */
            list.style.setProperty('display', 'flex', 'important');
            list.style.setProperty('flex-wrap', 'wrap', 'important');
            list.style.setProperty('grid-template-columns', 'none', 'important');
            list.style.setProperty('gap', '6px 8px', 'important');
            list.style.setProperty('width', 'auto', 'important');
            list.style.setProperty('height', 'auto', 'important');
        });

        d.querySelectorAll('ul.awa-footer-categories-list > li').forEach(function (item) {
            item.style.setProperty('display', 'block', 'important');
            item.style.setProperty('width', 'auto', 'important');
            item.style.setProperty('min-width', '0', 'important');
            item.style.setProperty('flex', '0 0 auto', 'important');
        });

        d.querySelectorAll('ul.awa-footer-categories-list > li > a').forEach(function (link) {
            link.style.setProperty('box-sizing', 'border-box', 'important');
            link.style.setProperty('display', 'inline-flex', 'important');
            link.style.setProperty('justify-content', 'center', 'important');
            link.style.setProperty('align-items', 'center', 'important');
            link.style.setProperty('width', 'auto', 'important');
            link.style.setProperty('max-width', 'none', 'important');
            link.style.setProperty('flex', '0 0 auto', 'important');
            link.style.setProperty('background', '#fff', 'important');
            link.style.setProperty('border', '1px solid #e5e5e5', 'important');
            link.style.setProperty('border-radius', '6px', 'important');
            /* r13: compact pill */
            link.style.setProperty('min-height', '28px', 'important');
            link.style.setProperty('height', 'auto', 'important');
            link.style.setProperty('padding', '4px 10px', 'important');
        });
    }

    var DENSE_PROPS = [
        'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'margin-inline',
        'padding', 'padding-top', 'padding-bottom', 'padding-block',
        'padding-left', 'padding-right', 'padding-inline',
        'min-height', 'height', 'max-height', 'line-height', 'font-size', 'overflow', 'display',
        'white-space', 'text-overflow', 'gap', 'width', 'max-width'
    ];

    var DENSE_SEL = [
        '.page_footer',
        '.page_footer .velaFooterLinks a',
        '.page_footer .awa-footer-atendimento__actions a',
        '.page_footer .awa-footer-atendimento__phone a',
        '.page_footer .awa-footer-atendimento__email a',
        '.page_footer .awa-footer-copyright__disclaimer',
        '.page_footer .awa-footer-atendimento__store-badge',
        '.page_footer .awa-footer-atendimento__store-address',
        '.page_footer .velaFooterTitle',
        '.page_footer .awa-footer-section__toggle',
        '.page_footer .vela-content.velaFooterMenu',
        '.page_footer ul.awa-footer-categories-list',
        '.page_footer ul.awa-footer-categories-list > li > a',
        '.page_footer .awa-newsletter-icon',
        '.page_footer #newsletter-validate-detail input[type=email]',
        '.page_footer #newsletter-validate-detail button.action.subscribe',
        '.page_footer #newsletter-validate-detail .field.newsletter',
        '.page_footer .awa-footer-pro__social-link',
        '.page_footer .awa-footer-cnpj-badge',
        '.page_footer .footer-bottom',
        '.page_footer .awa-footer-devby',
        '.page_footer .awa-footer-devby__inner',
        '.page_footer section.awa-footer-categories-expand',
        '.page_footer .footer-container > .container',
        '.page_footer .awa-footer-newsletter > .container',
        '.page_footer .footer-bottom > .container',
        '.page_footer .awa-footer-trust-bar > .container',
        '.page_footer .awa-newsletter-info',
        '.page_footer .awa-newsletter-desc',
        '.page_footer .awa-newsletter-title',
        '.page_footer .velaNewsletterTitle',
        '.page_footer .awa-footer-newsletter',
        '.page_footer .awa-footer-trust-bar',
        '.page_footer .awa-footer-trust-grid',
        '.page_footer .awa-footer-bottom__copyright',
        '.page_footer .awa-footer-devby__title'
    ].join(', ');

    function clearFooterDesktopDenseInline() {
        d.querySelectorAll(DENSE_SEL).forEach(function (el) {
            DENSE_PROPS.forEach(function (prop) {
                el.style.removeProperty(prop);
            });
        });
    }

    /** r72 densifica ≥992; audit 4.2 touch ≥44 desde 768. */
    function applyFooterTouchTargetsInline() {
        if (!w.matchMedia || !w.matchMedia('(min-width: 768px)').matches) {
            return;
        }
        d.querySelectorAll(
            '.page_footer .velaFooterLinks a, .page_footer .awa-footer-atendimento__actions a, ' +
            '.page_footer .awa-footer-atendimento__phone a, .page_footer .awa-footer-atendimento__email a, ' +
            '.page_footer .awa-footer-pro__social-link, .page_footer ul.awa-footer-categories-list > li > a'
        ).forEach(function (link) {
            link.style.setProperty('display', 'inline-flex', 'important');
            link.style.setProperty('align-items', 'center', 'important');
            link.style.setProperty('padding-top', '10px', 'important');
            link.style.setProperty('padding-bottom', '10px', 'important');
            link.style.setProperty('padding-block', '10px', 'important');
            link.style.setProperty('min-height', '44px', 'important');
            link.style.setProperty('height', 'auto', 'important');
            link.style.setProperty('line-height', '1.25', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-pro__social-link').forEach(function (el) {
            el.style.setProperty('width', '44px', 'important');
            el.style.setProperty('min-width', '44px', 'important');
            el.style.setProperty('justify-content', 'center', 'important');
            el.style.setProperty('box-sizing', 'border-box', 'important');
        });
    }

    /** r72: container 24px + news/cols/trust — densifica no desktop. */
    function applyFooterDesktopDenseInline() {
        if (!w.matchMedia || !w.matchMedia('(min-width: 992px)').matches) {
            clearFooterDesktopDenseInline();
            applyFooterTouchTargetsInline();
            return;
        }
        /* Shell real no DOM: footer.page-footer (nem sempre .page_footer). */
        d.querySelectorAll('footer.page-footer, .page-footer, .page_footer').forEach(function (footer) {
            [
                'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'margin-inline',
                'padding-block', 'padding-inline', 'padding-left', 'padding-right', 'padding',
                'overflow', 'overflow-x', 'overflow-y',
                'max-width', 'width'
            ].forEach(function (prop) { footer.style.removeProperty(prop); });
            /* Full-bleed: trust bar edge-to-edge; eixo 1280 fica nos filhos. */
            footer.style.setProperty('max-width', 'none', 'important');
            footer.style.setProperty('width', '100%', 'important');
            footer.style.setProperty('margin-left', '0', 'important');
            footer.style.setProperty('margin-right', '0', 'important');
            footer.style.setProperty('margin-inline', '0', 'important');
            footer.style.setProperty('padding-left', '0', 'important');
            footer.style.setProperty('padding-right', '0', 'important');
            footer.style.setProperty('padding-inline', '0', 'important');
            footer.style.setProperty('box-sizing', 'border-box', 'important');
        });
        /* Eixo 1280 alinhado ao header: shell full-bleed, miolo max-width + gutter 24. */
        (function applyFooterAxis1280() {
            var desktop = w.matchMedia && w.matchMedia('(min-width: 768px)').matches;
            var padInline = desktop ? '24px' : '16px';
            d.querySelectorAll(
                '.page_footer .footer-container > .container, ' +
                '.page_footer .awa-footer-newsletter > .container, ' +
                '.page_footer .footer-bottom > .container, ' +
                '.page_footer .awa-footer-trust-bar > .container'
            ).forEach(function (el) {
                el.style.setProperty('max-width', 'min(100%, 1280px)', 'important');
                el.style.setProperty('width', '100%', 'important');
                el.style.setProperty('margin-left', 'auto', 'important');
                el.style.setProperty('margin-right', 'auto', 'important');
                el.style.setProperty('margin-inline', 'auto', 'important');
                el.style.setProperty('padding-left', padInline, 'important');
                el.style.setProperty('padding-right', padInline, 'important');
                el.style.setProperty('padding-inline', padInline, 'important');
                el.style.setProperty('box-sizing', 'border-box', 'important');
            });
            d.querySelectorAll('.page_footer .footer-container > .container').forEach(function (el) {
                el.style.setProperty('padding-top', '16px', 'important');
                el.style.setProperty('padding-bottom', '16px', 'important');
                el.style.setProperty('padding-block', '16px', 'important');
            });
            /* Newsletter: fundo full-bleed; eixo no .container filho. */
            d.querySelectorAll('.page_footer .awa-footer-newsletter').forEach(function (el) {
                el.style.setProperty('padding-top', '14px', 'important');
                el.style.setProperty('padding-bottom', '14px', 'important');
                el.style.setProperty('padding-block', '14px', 'important');
                el.style.setProperty('padding-left', '0', 'important');
                el.style.setProperty('padding-right', '0', 'important');
                el.style.setProperty('padding-inline', '0', 'important');
                el.style.setProperty('margin-bottom', '0', 'important');
            });
            d.querySelectorAll('.page_footer .awa-footer-newsletter > .container').forEach(function (el) {
                el.style.setProperty('padding-top', '0', 'important');
                el.style.setProperty('padding-bottom', '0', 'important');
                el.style.setProperty('padding-block', '0', 'important');
            });
        }());
        d.querySelectorAll('.page_footer .awa-newsletter-info').forEach(function (el) {
            el.style.setProperty('max-height', 'none', 'important');
            el.style.setProperty('overflow', 'visible', 'important');
            el.style.setProperty('gap', '8px', 'important');
        });
        d.querySelectorAll('.page_footer .awa-newsletter-title, .page_footer .velaNewsletterTitle').forEach(function (el) {
            el.style.setProperty('font-size', '15px', 'important');
            el.style.setProperty('line-height', '1.25', 'important');
            el.style.setProperty('margin', '0', 'important');
        });
        d.querySelectorAll('.page_footer .awa-newsletter-desc').forEach(function (el) {
            el.style.setProperty('font-size', '13px', 'important');
            el.style.setProperty('line-height', '1.35', 'important');
            el.style.setProperty('margin', '0', 'important');
            el.style.setProperty('max-height', 'none', 'important');
            el.style.setProperty('overflow', 'visible', 'important');
            el.style.setProperty('white-space', 'normal', 'important');
            el.style.setProperty('text-overflow', 'clip', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-trust-bar').forEach(function (el) {
            ['padding-block', 'margin-bottom', 'background', 'background-color', 'background-image', 'color', 'display']
                .forEach(function (prop) { el.style.removeProperty(prop); });
        });
        d.querySelectorAll('.page_footer .awa-footer-trust-grid').forEach(function (el) {
            el.style.setProperty('padding-block', '4px', 'important');
            el.style.setProperty('gap', '8px 16px', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-bottom__copyright').forEach(function (el) {
            el.style.setProperty('padding', '0', 'important');
            el.style.setProperty('margin', '0', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-devby__title').forEach(function (el) {
            el.style.setProperty('margin', '0', 'important');
            el.style.setProperty('font-size', '11px', 'important');
        });
        d.querySelectorAll(
            '.page_footer .velaFooterLinks a, .page_footer .awa-footer-atendimento__actions a, ' +
            '.page_footer .awa-footer-atendimento__phone a, .page_footer .awa-footer-atendimento__email a'
        ).forEach(function (link) {
            link.style.setProperty('display', 'inline-flex', 'important');
            link.style.setProperty('align-items', 'center', 'important');
            link.style.setProperty('padding-top', '10px', 'important');
            link.style.setProperty('padding-bottom', '10px', 'important');
            link.style.setProperty('padding-block', '10px', 'important');
            link.style.setProperty('min-height', '44px', 'important');
            link.style.setProperty('height', 'auto', 'important');
            link.style.setProperty('line-height', '1.25', 'important');
        });
        d.querySelectorAll('.page_footer .velaFooterTitle, .page_footer .awa-footer-section__toggle').forEach(function (el) {
            el.style.setProperty('margin-bottom', '2px', 'important');
            el.style.setProperty('margin-top', '0', 'important');
            el.style.setProperty('padding-bottom', '0', 'important');
            el.style.setProperty('min-height', '0', 'important');
        });
        d.querySelectorAll('.page_footer .vela-content.velaFooterMenu').forEach(function (el) {
            el.style.setProperty('padding', '2px 4px', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-copyright__disclaimer').forEach(function (el) {
            el.style.setProperty('font-size', '10px', 'important');
            el.style.setProperty('line-height', '1.4', 'important');
            el.style.setProperty('max-height', 'none', 'important');
            el.style.setProperty('overflow', 'visible', 'important');
            el.style.setProperty('white-space', 'normal', 'important');
            el.style.setProperty('max-width', 'min(72ch, 100%)', 'important');
            el.style.removeProperty('text-overflow');
        });
        d.querySelectorAll('.page_footer .awa-footer-cnpj-badge').forEach(function (el) {
            el.style.setProperty('font-size', '10px', 'important');
            el.style.setProperty('line-height', '1.2', 'important');
            el.style.setProperty('padding', '0 5px', 'important');
            el.style.setProperty('min-height', '0', 'important');
            el.style.setProperty('height', 'auto', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-atendimento__store-badge').forEach(function (el) {
            el.style.setProperty('display', 'none', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-atendimento__store-address').forEach(function (el) {
            el.style.setProperty('white-space', 'nowrap', 'important');
            el.style.setProperty('overflow', 'hidden', 'important');
            el.style.setProperty('text-overflow', 'ellipsis', 'important');
        });
        d.querySelectorAll('.page_footer ul.awa-footer-categories-list').forEach(function (el) {
            el.style.setProperty('display', 'flex', 'important');
            el.style.setProperty('flex-wrap', 'wrap', 'important');
            el.style.setProperty('grid-template-columns', 'none', 'important');
            el.style.setProperty('gap', '6px 8px', 'important');
            el.style.setProperty('width', 'auto', 'important');
        });
        d.querySelectorAll('.page_footer ul.awa-footer-categories-list > li > a').forEach(function (link) {
            link.style.setProperty('width', 'auto', 'important');
            link.style.setProperty('max-width', 'none', 'important');
            link.style.setProperty('min-height', '28px', 'important');
            link.style.setProperty('height', 'auto', 'important');
            link.style.setProperty('padding', '4px 10px', 'important');
            link.style.setProperty('font-size', '12px', 'important');
            link.style.setProperty('line-height', '1.25', 'important');
            link.style.setProperty('border', '1px solid #e5e5e5', 'important');
            link.style.setProperty('border-radius', '6px', 'important');
            link.style.setProperty('background', '#fff', 'important');
        });
        d.querySelectorAll('.page_footer section.awa-footer-categories-expand').forEach(function (el) {
            el.style.setProperty('padding', '2px 0', 'important');
            el.style.setProperty('margin-top', '0', 'important');
        });
        d.querySelectorAll('.page_footer .awa-newsletter-icon').forEach(function (el) {
            el.style.setProperty('width', '24px', 'important');
            el.style.setProperty('height', '24px', 'important');
            el.style.setProperty('min-height', '24px', 'important');
            el.style.setProperty('max-height', '24px', 'important');
        });
        // PIXEL-QA D 2026-07-24: touch target ≥44 (antes 32 via dense desktop)
        d.querySelectorAll(
            '.page_footer #newsletter-validate-detail input[type=email], ' +
            '.page_footer #newsletter-validate-detail button.action.subscribe, ' +
            '.page_footer #newsletter-validate-detail .field.newsletter'
        ).forEach(function (el) {
            el.style.setProperty('height', '44px', 'important');
            el.style.setProperty('min-height', '44px', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-pro__social-link').forEach(function (el) {
            el.style.setProperty('width', '44px', 'important');
            el.style.setProperty('height', '44px', 'important');
            el.style.setProperty('min-width', '44px', 'important');
            el.style.setProperty('min-height', '44px', 'important');
            el.style.setProperty('padding', '10px', 'important');
            el.style.setProperty('box-sizing', 'border-box', 'important');
            el.style.setProperty('display', 'inline-flex', 'important');
            el.style.setProperty('align-items', 'center', 'important');
            el.style.setProperty('justify-content', 'center', 'important');
        });
        d.querySelectorAll('.page_footer .footer-bottom').forEach(function (el) {
            /* PIXEL-QA: pad horizontal só no shell footer.page-footer. */
            el.style.setProperty('padding', '2px 0', 'important');
            /* cat-white-r8: mata card soft/borda do visual-bugfix runtime */
            el.style.setProperty('background-color', '#fff', 'important');
            el.style.setProperty('background-image', 'none', 'important');
            el.style.setProperty('border', '0', 'important');
            el.style.setProperty('border-radius', '0', 'important');
});
        d.querySelectorAll('.page_footer .awa-footer-devby').forEach(function (el) {
            el.style.setProperty('padding-block', '0', 'important');
            el.style.setProperty('max-height', '32px', 'important');
        });
        d.querySelectorAll('.page_footer .awa-footer-devby__inner').forEach(function (el) {
            el.style.setProperty('min-height', '28px', 'important');
            el.style.setProperty('height', '28px', 'important');
            el.style.setProperty('gap', '6px', 'important');
        });
        applyFooterTouchTargetsInline();
    }

    function applyFooterCategoriesLayoutState() {
        if (isFooterMobile()) {
            clearFooterDesktopInlineState();
            syncFooterCategoriesShell();
            return;
        }

        applyFooterCategoriesDesktopState();
    }

    function injectFooterCategoriesDesktopLayoutLock() {
        if (isFooterMobile()) {
            clearFooterDesktopInlineState();
            syncFooterCategoriesShell();
            return;
        }

        var catLockCss = '@media (min-width:768px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) section.awa-footer-categories-expand .awa-footer-categories-expand__inner{display:grid!important;grid-template-columns:minmax(96px,max-content) minmax(0,1fr)!important;align-items:center!important;column-gap:clamp(14px,2vw,28px)!important;row-gap:4px!important;height:auto!important;min-height:0!important}html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) section.awa-footer-categories-expand .awa-footer-categories-expand__toggle{display:none!important;visibility:hidden!important;pointer-events:none!important}html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) section.awa-footer-categories-expand .awa-footer-categories-expand__heading{grid-column:1!important;grid-row:1!important}html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) section.awa-footer-categories-expand .awa-footer-categories-expand__panel{grid-column:2!important;grid-row:1!important;display:block!important;max-height:none!important;height:auto!important;visibility:visible!important}html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) ul.awa-footer-categories-list{display:flex!important;flex-wrap:wrap!important;grid-template-columns:none!important;gap:6px 8px!important;width:auto!important;height:auto!important}html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) ul.awa-footer-categories-list>li>a{width:auto!important;max-width:none!important;min-height:28px!important;height:auto!important;padding:4px 10px!important;border:1px solid #e5e5e5!important;border-radius:6px!important;background:#fff!important}}';
        var style = d.getElementById('awa-footer-categories-desktop-lock');
        if (!style) {
            style = d.createElement('style');
            style.id = 'awa-footer-categories-desktop-lock';
            (d.head || d.documentElement).appendChild(style);
        }
        style.textContent = catLockCss;

        applyFooterCategoriesDesktopState();
    }

    function syncFooterToggleAria() {
        var mobile = w.matchMedia('(max-width: 767px)').matches;
        d.querySelectorAll('button.awa-footer-section__toggle').forEach(function (btn) {
            btn.setAttribute('aria-expanded', mobile ? 'false' : 'true');
        });
    }

    function syncFooterCategoriesShell() {
        var mobile = w.matchMedia('(max-width: 767px)').matches;

        d.querySelectorAll('[data-awa-categories-toggle]').forEach(function (btn) {
            var panelId = btn.getAttribute('aria-controls');
            var panel = panelId ? d.getElementById(panelId) : null;

            if (!panel) {
                return;
            }

            if (mobile) {
                if (btn.getAttribute('aria-expanded') !== 'true') {
                    btn.setAttribute('aria-expanded', 'false');
                    btn.classList.remove('is-expanded');
                    panel.hidden = true;
                    panel.setAttribute('aria-hidden', 'true');
                    panel.setAttribute('inert', '');
                }
                return;
            }

            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('is-expanded');
            panel.hidden = false;
            panel.removeAttribute('inert');
            panel.setAttribute('aria-hidden', 'false');
        });
    }

    syncFooterToggleAria();
    syncFooterCategoriesShell();
    injectFooterCategoriesDesktopLayoutLock();
    applyFooterDesktopDenseInline();
        applyFooterTouchTargetsInline();
    if (typeof w.matchMedia === 'function') {
        var mq = w.matchMedia('(max-width: 767px)');
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', function () {
                syncFooterToggleAria();
                syncFooterCategoriesShell();
                injectFooterCategoriesDesktopLayoutLock();
                applyFooterDesktopDenseInline();
        applyFooterTouchTargetsInline();
            });
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(function () {
                syncFooterToggleAria();
                syncFooterCategoriesShell();
                injectFooterCategoriesDesktopLayoutLock();
                applyFooterDesktopDenseInline();
        applyFooterTouchTargetsInline();
            });
        }
    }

    var bootStarted = false;

    function bootFooterInteractions() {
        if (bootStarted || w.__awaFooterInteractionsHomeInit) {
            return;
        }

        var footer = d.querySelector('.page_footer, .page-footer');
        var cfgNode = d.getElementById('awa-footer-interactions-config-json');
        if (!footer || !cfgNode || !cfgNode.textContent) {
            return;
        }

        bootStarted = true;

        var run = function () {
            if (footer.getAttribute('data-awa-footer-interactions-boot') === '1') {
                return;
            }

            w.require(['awaFooterInteractions'], function (footerInteractions) {
                var config;
                try {
                    config = JSON.parse(cfgNode.textContent);
                } catch (e) {
                    return;
                }

                footer.setAttribute('data-awa-footer-interactions-boot', '1');
                footerInteractions(config, footer);
            });
        };

        if (typeof w.awaRunWhenRequire === 'function') {
            w.awaRunWhenRequire(run, { key: 'footer-interactions-critical' });
        } else if (typeof w.require === 'function' && !w.require._awaStub) {
            run();
        } else {
            var pollAttempts = 0;
            var pollTimer = w.setInterval(function () {
                pollAttempts += 1;
                if (typeof w.require === 'function' && !w.require._awaStub) {
                    w.clearInterval(pollTimer);
                    run();
                    return;
                }
                if (pollAttempts >= 80) {
                    w.clearInterval(pollTimer);
                }
            }, 250);
        }
    }

    function scheduleFooterBoot() {
        var footer = d.querySelector('.page_footer, .page-footer');
        if (!footer) {
            return;
        }

        footer.addEventListener('click', function (evt) {
            if (!evt.target || !evt.target.closest) {
                return;
            }
            if (evt.target.closest('.awa-footer-section__toggle, .awa-footer-categories-expand__toggle')) {
                bootFooterInteractions();
            }
        }, { capture: true, passive: true });

        if ('IntersectionObserver' in w) {
            var observer = new w.IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    observer.disconnect();
                    bootFooterInteractions();
                });
            }, { rootMargin: '320px 0px 0px 0px' });
            observer.observe(footer);
            return;
        }

        bootFooterInteractions();
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', scheduleFooterBoot, { once: true });
    } else {
        scheduleFooterBoot();
    }
}(window, document));
/*§boot-r16d*/
