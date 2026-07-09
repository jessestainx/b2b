<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Model;

/**
 * CSS terminal único — vence super-global (promo vermelho), visual-audit (72–88px / grid 4 col mobile)
 * e folhas injetadas pelo CSS gate após </body> (pin script mantém este bloco por último).
 */
final class HeaderImpeccableCascadeLockCss
{
    public const STYLE_ID = 'awa-header-impeccable-cascade-lock-v18';
    public const CATALOG_PARITY_STYLE_ID = 'awa-header-catalog-parity-v1';

    /** Lock leve (~4KB) — footer em PLP/PDP/checkout onde o cascade-lock do header é omitido. */
    public const FOOTER_STYLE_ID = 'awa-footer-terminal-lock-v1';

    /** Arquivo CSS externo gerado de footerTerminalRules() — carregado async no body. */
    public const FOOTER_CSS_FILE = 'awa-footer-terminal-lock-v1.min.css';

    public const FOOTER_CSS_QUERY = '?v=20260702-mobile-newsletter-width';

    public const HOME_IMPECCABLE_TERMINAL_STYLE_ID = 'awa-home-impeccable-terminal-v1';

    /** Home — subset terminal do header (~11KB) quando o cascade-lock completo é omitido. */
    public const HOME_LIGHT_STYLE_ID = 'awa-header-home-light-lock-v1';

    public const HOME_LIGHT_CSS_FILE = 'awa-header-home-light-lock-v1.min.css';

    public const HOME_LIGHT_QUERY = '?v=20260616-adapt';

    public const HOME_GUARD_SCRIPT_ID = 'awa-header-mobile-grid-guard';

    public const DISTILL_MOBILE_GRID_SCRIPT_ID = 'awa-header-distill-mobile-grid-20260616c';

    public const HEADER_TERMINAL_VERSION = '24-b2b-header-normalize';

    /** Subset leve (~6KB) — home/PLP/carrinho onde o cascade-lock completo é omitido. */
    public const HEADER_ESSENTIAL_STYLE_ID = 'awa-header-essential-terminal-v1';

    public const GATE_SCRIPT_QUERY = '20260708-b2b-panel-mobile-fix-v2';

    /**
     * Cache-busting para awa-visual-fixes-2026-06-29-final.min.css, aplicado
     * via GrupoAwamotos\Theme\Plugin\Response\OptimizeHeadStylesPlugin
     * (pós-processamento do HTML já renderizado pelo Renderer::renderHeadAssets()).
     *
     * NUNCA colocar esta query diretamente no atributo `src` de <css> no
     * layout XML: o core resolve o content-type do asset via
     * `pathinfo($path, PATHINFO_EXTENSION)` (Asset\Source::getContentType),
     * e "arquivo.min.css?v=xxx" quebra essa extração (retorna "css?v=xxx"),
     * fazendo Renderer::addDefaultAttributes() omitir `rel="stylesheet"` do
     * <link> gerado — o navegador então ignora o arquivo inteiro. Ver
     * comentário completo em default_head_blocks.xml.
     *
     * 2026-07-03: bump obrigatório após fix de flex-basis/gap dos carrosséis
     * (5º card cortado em "Linhas em destaque" e demais awa-shelf--carousel).
     * O asset é servido com Cache-Control: public, max-age=31536000, immutable
     * — sem trocar a query, visitantes com o CSS antigo em cache do navegador
     * NUNCA revalidariam e continuariam vendo o bug por até 1 ano.
     */
    public const VISUAL_FIXES_CSS_QUERY = '20260706-footer-toggle-selector-fix';

    /**
     * Cache-busting para awa-master-fix.js após tornar o init não bloqueante
     * para DOMContentLoaded em PLP/PDP.
     */
    public const MASTER_FIX_JS_QUERY = '20260702-masterfix-dcl-nonblocking';

    public const HARDEN_TERMINAL_ID = 'awa-header-harden-terminal-20260616';

    public const LAYOUT_TERMINAL_ID = 'awa-header-layout-terminal-20260616';

    public const POLISH_TERMINAL_ID = 'awa-header-polish-terminal-20260616';

    public const DISTILL_TERMINAL_ID = 'awa-header-distill-terminal-20260616e';

    /** Blocos header legados substituídos pelo distill terminal (2026-06-16). */
    public const LEGACY_HEADER_TERMINAL_IDS = [
        'awa-header-mobile-grid-body-terminal',
        'awa-header-vis-fix-20260615',
        'awa-header-layout-sync-terminal-20260616',
        'awa-header-adapt-terminal-20260616',
        'awa-header-harden-terminal-20260616',
        'awa-header-layout-terminal-20260616',
        'awa-header-polish-terminal-20260616',
        'awa-header-distill-terminal-20260616',
        'awa-header-distill-terminal-20260616b',
    ];

    public const LEGACY_HEADER_TERMINAL_SCRIPT_IDS = [
        'awa-header-harden-script-20260616',
        'awa-header-a11y-polish-20260616',
    ];

    /** Blocos duplicados cobertos pelo distill — remover do HTML (head PHTML + home-light stale). */
    public const LEGACY_HEADER_DUPLICATE_IDS = [
        'awa-header-layout-sync-20260616',
        'awa-header-account-hierarchy-terminal-inline',
        'awa-align-grid-header-container-terminal',
        self::HEADER_ESSENTIAL_STYLE_ID,
        self::HOME_LIGHT_STYLE_ID,
    ];

    /** Critical global inline — duplica distill + mobile-grid-critical em PLP/busca. */
    public const HEADER_CRITICAL_GLOBAL_ID = 'awa-header-impeccable-critical-global';

    public const REFINE_CSS_FILE = 'awa-commerce-impeccable-refine.min.css';

    public const REFINE_QUERY = '?v=20260704-carousel-nav-transform-fix';

    /** PDP terminal — ui-simplify + distill-lock (round 6, 2026-06-10). */
    public const PDP_DISTILL_LOCK_QUERY = '?v=20260610-pdp';

    public const PDP_UI_SIMPLIFY_QUERY = '?v=20260618-vqa-pdp-title-v2';

    /** Grid/alinhamento terminal — última camada SSOT (2026-06-11). */
    public const ALIGN_GRID_CSS_FILE = 'awa-align-grid-terminal-2026-06-11.min.css';

    public const ALIGN_GRID_QUERY = '?v=20260708-plp-b2b-gate-align-r7';

    public const BODY_END_QUERY = '?v=20260622-carousel-pro-controls-v4';

    public const SUPER_GLOBAL_CSS_FILE = 'awa-super-global-20260611m.min.css';

    public const SUPER_GLOBAL_QUERY = '';

    public const HOME_DISTILL_LOCK_QUERY = '?v=20260610-pdp';

    /** Rotas de auth/light-shell que devem omitir o cascade lock global. */
    public const AUTH_FOCUS_ACTIONS = [
        'b2b_account_login',
        'b2b_register_index',
        'b2b_register_success',
        'b2b_account_forgotpassword',
        'b2b_account_claim',
        'customer_account_login',
        'customer_account_create',
    ];

    /** Rotas operacionais B2B logadas (dashboard/conta/pedidos/cotações). */
    public const B2B_ACCOUNT_FOCUS_ACTIONS = [
        'b2b_account_index',
        'b2b_account_dashboard',
        'b2b_account_orders',
        'b2b_credit_index',
        'b2b_shoppinglist_index',
        'b2b_shoppinglist_view',
        'b2b_reorder_history',
        'b2b_quote_index',
        'b2b_quote_history',
        'b2b_quote_view',
        'b2b_company_index',
        'b2b_erporders_index',
        'b2b_erporders_view',
        'b2b_cotacao_index',
        'b2b_cotacao_view',
        'b2b_quickorder_index',
        'b2b_subscription_index',
        'b2b_approval_index',
        'erpintegration_customer_suggestedcart',
        'erpintegration_customer_suggestions',
    ];

    private const LEGACY_STYLE_IDS = [
        'awa-header-impeccable-terminal-fix',
        'awa-header-impeccable-cascade-lock-v11',
        'awa-header-impeccable-cascade-lock-v12',
        'awa-header-impeccable-cascade-lock-v13',
        'awa-header-impeccable-cascade-lock-v14',
        'awa-header-impeccable-cascade-lock-v15',
        'awa-header-impeccable-cascade-lock-v16',
        'awa-header-impeccable-cascade-lock-v17',
    ];

    private const GUARD_SCRIPT_ID = 'awa-header-cascade-lock-guard';

    public static function headerA11yRules(): string
    {
        return 'html body#html-body .awa-site-header :is(a,button,[role="button"],[tabindex="0"]):focus:not(:focus-visible){outline:none!important}'
            . 'html body#html-body .awa-site-header :is(a,button,[role="button"],[tabindex="0"]):focus-visible{'
            . 'outline:2px solid var(--awa-primary,oklch(48% .14 20))!important;outline-offset:3px!important;border-radius:3px}'
            . 'html body#html-body .awa-site-header form#search_mini_form input:focus-visible{'
            . 'outline:2px solid var(--awa-primary,oklch(48% .14 20))!important;outline-offset:0!important}'
            . 'html body#html-body .awa-site-header :is(.awa-skip-link,.skip-link):focus-visible{'
            . 'position:fixed!important;inset-block-start:8px!important;inset-inline-start:8px!important;'
            . 'z-index:10000!important;padding:8px 12px!important;background:var(--awa-bg-subtle,oklch(97.5% .006 20))!important;'
            . 'color:var(--awa-text,oklch(22% .02 20))!important;clip:auto!important;width:auto!important;height:auto!important}'
            . 'html body#html-body:has(.awa-site-header){scroll-padding-block-start:var(--awa-header-scroll-offset,116px)}'
            . 'html body#html-body .awa-site-header #awa-main-content{scroll-margin-block-start:var(--awa-header-scroll-offset,116px)}'
            . '@media (max-width:991px){'
            . 'html body#html-body .awa-site-header .header-wrapper-sticky :is(.nav-toggle,.action.showcart,.awa-header-cart-link,button.our_categories){'
            . 'min-width:44px!important;min-height:44px!important;touch-action:manipulation!important}'
            . '}'
            . '@media (prefers-reduced-motion:reduce){'
            . 'html body#html-body .awa-site-header .header-wrapper-sticky,'
            . 'html body#html-body .awa-site-header .logo,'
            . 'html body#html-body .awa-site-header .logo img{transition:none!important}'
            . '}';
    }

    /**
     * Terminal header contrast/layout lock.
     *
     * Uses high specificity and !important because Magento critical inline CSS and
     * retired Ayo bundles still compete after static deployment on cached pages.
     */
    public static function headerBolderActionContrastLockRules(): string
    {
        $shell = 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header';
        $actions = $shell . ' :is('
            . '.block-search button.action.search,'
            . '.block-search .action.search,'
            . 'form#search_mini_form button.action.search,'
            . 'form#search_mini_form button.action.search[disabled],'
            . '.minicart-wrapper .action.showcart,'
            . '.awa-header-minicart .action.showcart,'
            . '.awa-header-minicart a.showcart.header-mini-cart'
            . ')';
        $promo = $shell . ' :is(#awa-b2b-promo-bar,.awa-b2b-promo-bar)';
        $searchForm = $shell . ' form#search_mini_form';
        $searchShell = $shell . ' .awa-header-search-col > .block-search';

        return $actions . '{'
            . 'background:var(--awa-primary,var(--awa-red))!important;'
            . 'border-color:var(--awa-primary,var(--awa-red))!important;'
            . 'color:var(--awa-text-inverse,var(--awa-white))!important;'
            . 'opacity:1!important}'
            . $actions . ':is(:disabled,[disabled]){'
            . 'color:var(--awa-text-inverse,var(--awa-white))!important;'
            . 'opacity:1!important}'
            . $actions . '::before{'
            . 'color:currentColor!important;opacity:1!important}'
            . $actions . ' :is(svg,path){'
            . 'color:currentColor!important;stroke:currentColor!important;opacity:1!important}'
            // Desktop: o botão de busca traz SVG inline + lupa desenhada via ::before/::after (círculo+cabo).
            // Em ≥992px suprimimos a lupa-pseudo e mantemos apenas o SVG, evitando lupa duplicada/deslocada (home e catálogo).
            . '@media(min-width:992px){'
            . $searchForm . ' button.action.search::before,'
            . $searchForm . ' button.action.search::after,'
            . $searchForm . ' .actions button.action.search::before,'
            . $searchForm . ' .actions button.action.search::after{'
            . 'content:none!important;display:none!important;border:0!important;background:none!important}'
            . $searchForm . ' button.action.search svg{display:block!important;margin:0 auto!important}'
            . '}'
            . '@media(max-width:767px){'
            . $promo . '{'
            . 'align-items:center!important;background:var(--awa-primary,var(--awa-red))!important;'
            . 'background-color:var(--awa-primary,var(--awa-red))!important;box-sizing:border-box!important;'
            . 'color:var(--awa-text-inverse,var(--awa-white))!important;display:flex!important;'
            . 'flex:1 1 100%!important;height:36px!important;justify-content:center!important;line-height:36px!important;'
            . 'inline-size:auto!important;margin:0!important;max-height:36px!important;max-inline-size:100%!important;'
            . 'max-width:100%!important;min-height:36px!important;min-inline-size:0!important;'
            . 'overflow:hidden!important;padding:0 44px 0 12px!important;position:relative!important;width:auto!important}'
            . $promo . ' :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout){'
            . 'align-items:center!important;background:transparent!important;box-sizing:border-box!important;'
            . 'display:flex!important;height:36px!important;justify-content:center!important;'
            . 'line-height:36px!important;max-height:36px!important;min-height:36px!important;'
            . 'overflow:hidden!important;padding:0!important;width:100%!important}'
            . $promo . ' :is(*,a,strong,span,p,.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__cta *){'
            . 'color:var(--awa-text-inverse,var(--awa-white))!important;opacity:1!important}'
            . $promo . ' :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__cta){'
            . 'display:block!important;line-height:36px!important;margin:0!important;max-width:100%!important;'
            . 'min-width:0!important;overflow:hidden!important;padding:0 4px!important;text-overflow:ellipsis!important;'
            . 'white-space:nowrap!important}'
            . $shell . ' :is(.awa-b2b-promo-close,#awa-b2b-promo-close){'
            . 'align-items:center!important;display:flex!important;height:36px!important;inset-block-start:0!important;'
            . 'inset-inline-end:0!important;justify-content:center!important;margin:0!important;min-height:36px!important;'
            . 'padding:0!important;position:absolute!important;width:44px!important}'
            . $searchShell . '{'
            . 'background:transparent!important;border:0!important;border-radius:0!important;'
            . 'box-shadow:none!important;box-sizing:border-box!important;outline:none!important;overflow:visible!important}'
            . $searchForm . '{'
            . 'align-items:stretch!important;background:var(--awa-bg,var(--awa-white))!important;'
            . 'border:1px solid color-mix(in srgb,var(--awa-primary,var(--awa-red)) 24%,var(--awa-border,#e5e7eb))!important;'
            . 'border-radius:var(--awa-radius-sm)!important;'
            . 'box-shadow:none!important;box-sizing:border-box!important;outline:none!important;'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;'
            . 'height:44px!important;max-height:44px!important;min-height:44px!important;overflow:hidden!important;padding:0!important}'
            . $searchForm . ' :is(.field.search,.field.search .control){'
            . 'display:block!important;height:40px!important;max-height:40px!important;min-height:40px!important;'
            . 'min-width:0!important;overflow:hidden!important;padding:0!important}'
            . $searchForm . ' input#search{'
            . 'background:transparent!important;border:0!important;box-shadow:none!important;font-size:16px!important;'
            . 'height:40px!important;line-height:40px!important;padding:0 12px!important;width:100%!important}'
            . $searchForm . ' .actions{'
            . 'display:flex!important;height:40px!important;max-height:40px!important;min-height:40px!important;'
            . 'width:44px!important}'
            . $searchForm . ' button.action.search{'
            . 'align-items:center!important;background:var(--awa-primary,var(--awa-red))!important;border:0!important;'
            . 'border-radius:0 var(--awa-radius-sm) var(--awa-radius-sm) 0!important;'
            . 'color:var(--awa-text-inverse,var(--awa-white))!important;display:flex!important;height:40px!important;'
            . 'justify-content:center!important;max-height:40px!important;min-height:40px!important;opacity:1!important;width:44px!important}'
            . 'html body#html-body:has(nav.fixed-bottom.hidden-sm.hidden-md.hidden-lg) .page-wrapper{'
            . 'padding-bottom:calc(88px + env(safe-area-inset-bottom,0px))!important}'
            . '}';
    }

    public static function minicartInteractionRules(): string
    {
        return 'html body#html-body .page-wrapper .awa-site-header '
            . '.minicart-wrapper:not(.active):not(.is-open):not(.show) .block-minicart:not(._active){'
            . 'display:none!important;visibility:hidden!important;opacity:0!important;'
            . 'pointer-events:none!important;width:0!important;min-width:0!important;max-width:0!important;'
            . 'height:0!important;min-height:0!important;max-height:0!important;overflow:hidden!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-minicart--expanded .block-minicart,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.minicart-wrapper:is(.active,.is-open,.show) .block-minicart,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.minicart-wrapper .block-minicart._active,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '[data-role=dropdownDialog].block-minicart._active{'
            . 'display:flex!important;visibility:visible!important;opacity:1!important;'
            . 'pointer-events:auto!important;position:absolute!important;z-index:100130!important;'
            . 'box-sizing:border-box!important;background:var(--awa-bg-surface,var(--awa-bg))!important;'
            . 'border:0!important;border-top-width:0!important;border-radius:var(--awa-radius-lg)!important;'
            . 'box-shadow:var(--awa-shadow-xl)!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .block-minicart '
            . ':is(.action.checkout,.action.primary.checkout,.action.viewcart,#top-cart-btn-checkout){'
            . 'min-height:44px!important;border-radius:var(--awa-radius-md,8px)!important;text-transform:none!important}'
            . '@media(max-width:767px){'
            . '/* Mobile minicart: escapa o containing block de 44px para evitar painel off-canvas. */'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-minicart--expanded .minicart-wrapper,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.minicart-wrapper:is(.active,.is-open,.show){'
            . 'contain:none!important;overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-minicart--expanded .block-minicart,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.minicart-wrapper:is(.active,.is-open,.show) .block-minicart,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.minicart-wrapper .block-minicart._active,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '[data-role=dropdownDialog].block-minicart._active{'
            . 'position:fixed!important;top:calc(var(--awa-header-main-row-h,96px) + 8px)!important;'
            . 'right:var(--awa-header-shell-pad,16px)!important;left:var(--awa-header-shell-pad,16px)!important;'
            . 'width:calc(100vw - (var(--awa-header-shell-pad,16px) * 2))!important;'
            . 'min-width:0!important;max-width:none!important;transform:none!important;'
            . 'max-height:min(78vh,720px)!important;overflow:visible!important}'
            . '}'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-minicart '
            . '.minicart-wrapper .action.showcart,'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-minicart '
            . '.minicart-wrapper a.showcart.header-mini-cart{'
            . 'pointer-events:auto!important;cursor:pointer!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,a.showcart.header-mini-cart){'
            . 'float:none!important;position:absolute!important;inset:auto!important;left:auto!important;right:0!important;'
            . 'top:0!important;bottom:auto!important;transform:none!important;place-self:center!important;'
            . 'justify-self:auto!important;margin:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper .awa-site-header .awa-header-minicart :is(.minicart-wrapper,.mini-carts){'
            . 'position:relative!important;overflow:visible!important}';
    }

    public static function promoBarRules(): string
    {
        return 'html body#html-body .page-wrapper :is('
            . '#awa-b2b-promo-bar,'
            . '#header.header-container[data-awa-header-shell="true"] .top-header.awa-b2b-promo-bar,'
            . '#header.header-container[data-awa-header-shell="true"] .awa-b2b-promo-bar[data-awa-header-utility],'
            . '#header .top-header.awa-b2b-promo-bar,'
            . '#header .awa-b2b-promo-bar[data-awa-header-utility],'
            . '.awa-site-header .top-header.awa-b2b-promo-bar,'
            . '.awa-site-header .awa-b2b-promo-bar[data-awa-header-utility]'
            . '){'
            . 'background:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'background-color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'background-image:none!important;'
            . 'border:0!important;box-shadow:none!important;'
            . 'box-sizing:border-box!important;min-height:44px!important;max-height:44px!important;'
            . 'height:44px!important;'
            . 'padding-block:8px!important;padding-inline:clamp(12px,2vw,16px)!important;'
            . 'overflow:visible!important;position:relative!important;'
            . 'display:flex!important;align-items:center!important;'
            . 'flex:1 1 100%!important;min-width:0!important;width:100%!important;max-width:100%!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . 'html body#html-body .page-wrapper :is('
            . '.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout'
            . '){width:100%!important;max-width:var(--awa-container-catalog,var(--awa-container-max,1440px))!important;'
            . 'margin-inline:auto!important;justify-content:center!important}'
            . 'html body#html-body .page-wrapper :is(#awa-b2b-promo-bar,.awa-b2b-promo-bar) .awa-b2b-promo-close,'
            . 'html body#html-body .page-wrapper button.awa-b2b-promo-close{'
            . 'position:absolute!important;inset-block-start:50%!important;inset-inline-end:clamp(8px,1.5vw,12px)!important;'
            . 'transform:translateY(-50%)!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'inline-size:44px!important;block-size:44px!important;min-width:44px!important;min-height:44px!important;'
            . 'padding:0!important;margin:0!important;border-radius:999px!important;'
            . 'border:1px solid color-mix(in srgb,var(--awa-text-inverse,oklch(99% .002 20)) 24%,transparent)!important;'
            . 'background:color-mix(in srgb,var(--awa-text-inverse,oklch(99% .002 20)) 10%,transparent)!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important;opacity:.92!important}'
            . 'html body#html-body .page-wrapper :is('
            . '.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout'
            . '){border:0!important;box-shadow:none!important;background:transparent!important;'
            . 'border-radius:0!important;overflow:visible!important;'
            . 'padding-block:8px!important;padding-inline:0!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper :is('
            . '.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__tail'
            . '){border:0!important;box-shadow:none!important;background:transparent!important;'
            . 'border-radius:0!important;overflow:visible!important;padding:0!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-b2b-promo-bar__cta,'
            . 'html body#html-body .page-wrapper #header .awa-b2b-promo-bar__cta{'
            . 'display:inline!important;padding:0!important;border:0!important;border-radius:0!important;'
            . 'background:transparent!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}';
    }

    public static function headerPolishRules(): string
    {
        return 'html body#html-body .page-wrapper .awa-site-header{'
            . '--awa-header-polish-ease:cubic-bezier(.22,1,.36,1);'
            . '--awa-header-polish-hover:oklch(42% .13 20);'
            . '--awa-header-polish-ring:color-mix(in srgb,var(--awa-primary,oklch(48% .14 20)) 26%,transparent)}'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . '.top-header.awa-b2b-promo-bar,.awa-b2b-promo-bar[data-awa-header-utility]'
            . ') :is(a,span,strong,p,button){'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . '.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.awa-nav-bar'
            . ') :is(.awa-nav-quick-links__link,.custommenu.main-nav a,.navigation.custommenu a){'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'font-weight:650!important;text-decoration:none!important}'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . '.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.awa-nav-bar'
            . ') :is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]){'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'font-weight:700!important}'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . 'form#search_mini_form,.action.search,.action.showcart,.showcart.header-mini-cart,'
            . '.awa-minicart-trigger,.awa-nav-quick-links__link,'
            . '.custommenu.main-nav a,.navigation.custommenu a,'
            . '.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]'
            . '){transition:background-color .18s var(--awa-header-polish-ease),'
            . 'border-color .18s var(--awa-header-polish-ease),'
            . 'box-shadow .18s var(--awa-header-polish-ease),'
            . 'color .18s var(--awa-header-polish-ease),'
            . 'transform .12s var(--awa-header-polish-ease)!important}'
            . 'html body#html-body .page-wrapper .awa-site-header form#search_mini_form:hover{'
            . 'border-color:color-mix(in srgb,var(--awa-primary,oklch(48% .14 20)) 38%,var(--awa-border,oklch(90% .008 20)))!important}'
            . 'html body#html-body .page-wrapper .awa-site-header form#search_mini_form:focus-within{'
            . 'border-color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'box-shadow:0 0 0 3px var(--awa-header-polish-ring)!important}'
            . 'html body#html-body .page-wrapper .awa-site-header form#search_mini_form input#search::placeholder{'
            . 'color:oklch(50% .018 20)!important;opacity:1!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .action.search:hover{'
            . 'background:var(--awa-header-polish-hover)!important;'
            . 'box-shadow:0 6px 14px rgb(15 23 42/14%)!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .action.search:active{'
            . 'transform:translateY(1px)!important;box-shadow:none!important}'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . '.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger'
            . ') .counter.qty{'
            . 'position:absolute!important;inset-block-start:-5px!important;inset-inline-end:-6px!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'min-width:18px!important;height:18px!important;padding:0 5px!important;'
            . 'border-radius:999px!important;background:oklch(99% .002 20)!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'font-size:11px!important;font-weight:800!important;line-height:18px!important;'
            . 'box-shadow:0 0 0 2px var(--awa-primary,oklch(48% .14 20))!important}'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . '.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger'
            . ') .counter.qty.empty{display:none!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .counter.qty .counter-number{'
            . 'display:inline!important;position:static!important;width:auto!important;height:auto!important;'
            . 'clip:auto!important;clip-path:none!important;overflow:visible!important;white-space:nowrap!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .counter.qty .counter-label{'
            . 'position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;'
            . 'clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;white-space:nowrap!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header :is('
            . '.awa-nav-quick-links__link,.custommenu.main-nav a,.navigation.custommenu a'
            . '):hover,'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header :is('
            . '.awa-nav-quick-links__link,.custommenu.main-nav a,.navigation.custommenu a'
            . '):focus-visible{'
            . 'background:color-mix(in srgb,var(--awa-text-inverse,oklch(99% .002 20)) 14%,transparent)!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'border-radius:8px!important;text-decoration:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header :is('
            . '.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]'
            . '):hover,'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header :is('
            . '.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]'
            . '):focus-visible{'
            . 'background:oklch(35% .11 20)!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . '@media (min-width:992px){'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt .awa-header-account-prompt__line1{'
            . 'color:oklch(38% .022 20)!important;font-weight:500!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt .awa-header-account-prompt__separator{'
            . 'color:oklch(52% .018 20)!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt '
            . '.awa-header-account-prompt__link:not(.awa-header-account-prompt__link--register){'
            . 'color:oklch(28% .024 20)!important;font-weight:650!important}'
            . '}'
            . '@media (prefers-reduced-motion:reduce){'
            . 'html body#html-body .page-wrapper .awa-site-header :is('
            . 'form#search_mini_form,.action.search,.action.showcart,.showcart.header-mini-cart,'
            . '.awa-minicart-trigger,.awa-nav-quick-links__link,'
            . '.custommenu.main-nav a,.navigation.custommenu a,'
            . '.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]'
            . '){transition:none!important;transform:none!important}'
            . '}';
    }

    public static function impeccableSurfaceRules(): string
    {
        return 'html body#html-body .page-wrapper :is('
            . '.navigation.verticalmenu .submenu.navigation__submenu,'
            . '.navigation.verticalmenu div[id^="submenu-menu-"],'
            . '.awa-vmf-portal.navigation__submenu'
            . '){border:0!important;box-shadow:0 4px 12px rgb(15 23 42/10%)!important}'
            . 'html body#html-body .page-wrapper :is(#search_autocomplete,.search-autocomplete,.searchsuite-autocomplete,.mst-searchautocomplete__autocomplete){'
            . 'border:0!important;box-shadow:0 4px 16px rgb(15 23 42/12%)!important}'
            /* Mobile nav lives outside .page-wrapper — no ancestor requirement */
            . 'html body#html-body :is(nav.fixed-bottom.hidden-sm,nav.fixed-bottom,.fixed-bottom,.awa-mobile-bottom-nav){'
            . 'border:0!important;border-top:0!important;'
            . 'box-shadow:0 -2px 8px rgb(15 23 42/8%)!important}'
            . '@media(max-width:991px){'
            . 'html:has(body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)){'
            . 'height:100%!important;min-height:100%!important}'
            . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view){'
            . 'height:100%!important;min-height:100%!important}'
            . 'html body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . ':is(nav.fixed-bottom.hidden-sm.hidden-md.hidden-lg,nav.fixed-bottom.mobile-bottom-nav,nav.fixed-bottom.awa-mobile-bottom-nav){'
            . 'position:fixed!important;inset:auto 0 0 0!important;top:auto!important;right:0!important;bottom:0!important;left:0!important;'
            . 'display:block!important;visibility:visible!important;opacity:1!important;'
            . 'width:100%!important;max-width:100%!important;height:calc(64px + env(safe-area-inset-bottom,0px))!important;'
            . 'min-height:64px!important;max-height:calc(72px + env(safe-area-inset-bottom,0px))!important;'
            . 'margin:0!important;padding:0!important;background:var(--awa-bg,#fff)!important;'
            . 'z-index:998!important;transform:none!important;translate:none!important}'
            . 'html body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . ':is(nav.fixed-bottom.hidden-sm.hidden-md.hidden-lg,nav.fixed-bottom.mobile-bottom-nav,nav.fixed-bottom.awa-mobile-bottom-nav) .mobile-bottom-link{'
            . 'align-items:stretch!important;display:flex!important;gap:0!important;height:100%!important;'
            . 'justify-content:space-between!important;margin:0!important;padding:4px 10px calc(4px + env(safe-area-inset-bottom,0px))!important}'
            . '}'
            . 'html body#html-body nav.fixed-bottom .mobile-bottom-link>li>a,'
            . 'html body#html-body nav.fixed-bottom .mobile-bottom-link>li>button{'
            . 'display:inline-flex!important;flex-direction:column!important;align-items:center!important;'
            . 'justify-content:center!important;gap:2px!important;line-height:1!important}'
            . 'html body#html-body nav.fixed-bottom .mobile-bottom-link>li>a :is(.icon,span.icon),'
            . 'html body#html-body nav.fixed-bottom .mobile-bottom-link>li>button :is(.icon,span.icon){'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'line-height:1!important;margin:0!important}'
            . 'html body#html-body #awa-cookie-banner,'
            . 'html body#html-body #awa-cookie-banner.awa-cookie-banner--visible{'
            . 'border:0!important;box-shadow:0 -4px 12px rgb(15 23 42/10%)!important}'
            . 'html body#html-body .b2b-login-modal{'
            . 'border:0!important;box-shadow:0 8px 24px rgb(15 23 42/14%)!important;overflow:visible!important}'
            . 'html body#html-body .page-wrapper :is(#header .header-content,.header-container .header-content,.header-content){'
            . 'border:0!important;box-shadow:none!important;background:var(--awa-bg,oklch(99% .002 20))!important}'
            . 'html body#html-body .page-wrapper .awa-header-categories.menu_left_home1,'
            . 'html body#html-body .page-wrapper .awa-header-categories.menu_left_home1 '
            . ':is(.navigation.verticalmenu,.our_categories.title-category-dropdown){'
            . 'border:0!important;box-shadow:none!important;background:transparent!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .content-top-home .awa-section-header__eyebrow{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'overflow:hidden!important;margin:0!important;padding:0!important;border:0!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(.awa-category-carousel__item,.awa-category-carousel__item--compact) .awa-category-carousel__icon,'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-category-carousel__item:hover .awa-category-carousel__icon{'
            . 'transition:transform .24s cubic-bezier(.22,1,.36,1)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-category-carousel__item{'
            . 'transition:border-color .2s cubic-bezier(.22,1,.36,1),'
            . 'background-color .2s cubic-bezier(.22,1,.36,1),'
            . 'box-shadow .2s cubic-bezier(.22,1,.36,1)!important}'
            . 'html body#html-body .page-wrapper .awa-hero-trust-strip__text{'
            . 'font-size:max(12px,.75rem)!important;line-height:1.4!important}'
            . '@media (min-width:576px) and (max-width:991px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;'
            . 'gap:20px!important;align-items:stretch!important;margin:16px 0 0!important;padding:0!important;'
            . 'list-style:none!important;overflow:visible!important;flex-flow:unset!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item{display:flex!important;flex-direction:column!important;'
            . 'align-items:center!important;gap:16px!important;flex:1 1 auto!important;min-height:48px!important;'
            . 'max-width:none!important;padding:24px 16px!important;text-align:center!important;list-style:none!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . 'svg.awa-hero-trust-strip__icon,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-trust-strip__icon{display:block!important;flex:0 0 48px!important;width:48px!important;'
            . 'min-width:48px!important;max-width:48px!important;height:48px!important;min-height:48px!important;'
            . 'max-height:48px!important}}'
            . '@media (min-width:992px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;'
            . 'gap:24px!important;align-items:stretch!important;margin:16px 0 0!important;padding:0!important;'
            . 'list-style:none!important;overflow:visible!important;flex-flow:unset!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item{display:flex!important;flex-direction:column!important;'
            . 'align-items:center!important;gap:16px!important;flex:1 1 auto!important;min-height:48px!important;'
            . 'max-width:none!important;padding:24px 16px!important;text-align:center!important;list-style:none!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . 'svg.awa-hero-trust-strip__icon,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-trust-strip__icon{display:block!important;flex:0 0 48px!important;width:48px!important;'
            . 'min-width:48px!important;max-width:48px!important;height:48px!important;min-height:48px!important;'
            . 'max-height:48px!important}}'
            . 'html body#html-body .page-wrapper .page_footer :is(h2,h3).awa-newsletter-title{'
            . 'color:var(--awa-ink,oklch(22% .02 20))!important}'
            /* BUG-CONTRAST-FOOTER-TRUST (2026-07-06): --awa-ink resolve para
             * --awa-oklch-ink = oklch(22% .012 27), token de "tinta escura para
             * fundo claro". Aplicado (por engano, copiado de outra regra) ao
             * titulo em negrito do selo de confianca, que fica sobre o fundo
             * VERMELHO ESCURO do rodape (.page_footer). Contraste medido: 2.96:1,
             * abaixo do minimo WCAG AA (4.5:1) — texto quase ilegivel. Trocado
             * para var(--awa-white,#fff), mesmo tom que o span irmao ja usa com
             * sucesso (5.19:1). Esta regra e injetada inline (maior prioridade
             * de cascata) — corrigir apenas nos bundles CSS externos nao surtia
             * efeito. */
            . 'html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy strong,'
            . 'html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-item .awa-footer-trust-copy strong{'
            . 'color:var(--awa-white,#fff)!important}'
            . 'html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy span{'
            . 'color:color-mix(in srgb,#fff 88%,transparent)!important}'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label--social,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label--social{'
            . 'color:oklch(45% .02 20)!important}'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store{'
            . 'background:oklch(99% .002 20)!important;border:1px solid oklch(92% .01 20)!important;'
            . 'padding:12px!important;border-radius:8px!important}'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store '
            . 'p.awa-footer-atendimento__store-name,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store '
            . 'p.awa-footer-atendimento__store-address,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__store-name,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__store-address,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento__store-name,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento__store-address{'
            . 'color:oklch(22% .01 20)!important}'
            . '@media (min-width:768px){html body#html-body:not(.cms-index-index):not(.cms-home):not(.cms-homepage_ayo_home5) .page-wrapper .header-wrapper-sticky{padding-block:4px!important}}'
            . 'html body#html-body:not(.cms-index-index):not(.cms-home):not(.cms-homepage_ayo_home5) .page-wrapper .header_main.awa-main-header-inner-wrap{padding-inline:8px!important}'
            . 'html body#html-body .page-wrapper .awa-b2b-min-order-progress--minicart{padding-top:8px!important}'
            . 'html body#html-body .page-wrapper .header-control.header-nav.awa-nav-bar{'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .header-control.header-nav .container{'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-nav-bar__inner{'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-site-header form#search_mini_form.minisearch,'
            . 'html body#html-body .page-wrapper .awa-site-header form#search_mini_form.minisearch .control,'
            . 'html body#html-body .page-wrapper .awa-site-header a.action.showcart.header-mini-cart,'
            . 'html body#html-body .page-wrapper .awa-site-header .minicart-wrapper .action.showcart{'
            . 'overflow:visible!important;position:relative!important}'
            . 'html body#html-body .page-wrapper .custommenu.main-nav .ui-menu-item.navigation__item>a,'
            . 'html body#html-body .page-wrapper .navigation.custommenu .ui-menu-item.navigation__item>a{'
            . 'padding-block:10px!important}'
            . 'html body#html-body .page-wrapper .subchildmenu.navigation__inner-list{padding-right:12px!important}'
            . 'html body#html-body .page-wrapper .subchildmenu.navigation__inner-list>li.ui-menu-item.navigation{'
            . 'padding:8px 12px!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-category-carousel__viewport{overflow-x:hidden!important;overflow-y:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.product-thumb{padding-block:8px 4px!important}';
    }

    public static function homeImpeccablePolishRules(): string
    {
        return 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) :is('
            . '.awa-sr-only,.sr-only,.visually-hidden,label.visually-hidden,.awa-carousel-live.visually-hidden'
            . '):not(.awa-skip-link){'
            . 'position:fixed!important;top:0!important;left:0!important;width:1px!important;height:1px!important;'
            . 'min-width:1px!important;min-height:1px!important;max-width:1px!important;max-height:1px!important;'
            . 'margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;'
            . 'clip:rect(0 0 0 0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;'
            . 'font-size:0!important;line-height:0!important;word-break:break-all!important;contain:strict!important}'
            . 'html body#html-body .awa-skip-link:not(:focus):not(:focus-visible){'
            . 'position:fixed!important;top:0!important;left:0!important;width:1px!important;height:1px!important;'
            . 'min-width:1px!important;min-height:1px!important;max-width:1px!important;max-height:1px!important;'
            . 'margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;'
            . 'clip:rect(0 0 0 0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;'
            . 'font-size:0!important;line-height:0!important;word-break:break-all!important;contain:strict!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5),'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper{'
            . 'font-family:var(--awa-font-family,"Source Sans 3",system-ui,sans-serif)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is('
            . 'h1,h2,h3,h4,h5,h6,.title-catthum,.awa-section-header__title,.rokan-product-heading h2,.block-title strong,'
            . '.awa-section-header h2,.awa-shelf__header h2,.awa-carousel-section .section-title h2,'
            . '.awa-category-carousel__header h2'
            . '){font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is('
            . '.navigation.verticalmenu,.navigation.verticalmenu a,.navigation.verticalmenu .our_categories,'
            . '.awa-site-header,.awa-site-header .header,.header-control.header-nav,#footer,.footer-bottom,'
            . '.awa-footer-newsletter,.top-home-content,.awa-benefits-bar,.page_footer,.awa-footer-trust-bar'
            . '){font-family:var(--awa-font-family,"Source Sans 3",system-ui,sans-serif)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5){margin:0!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.content-top-home{padding-inline:0!important;box-sizing:border-box!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(.home-main,.awa-hero-b2b-cta){'
            . 'padding-inline:clamp(16px,3vw,48px)!important;box-sizing:border-box!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.content-top-home .ayo-home5-wrapper--template-driven>.awa-carousel-section:not(.top-home-content--above-fold){'
            . 'padding-inline:clamp(16px,3vw,48px)!important;box-sizing:border-box!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(.awa-footer-business-contact__copy,.awa-newsletter-desc,.awa-footer-copyright__legal,'
            . '.awa-footer-copyright__disclaimer,#b2b-login-desc,.awa-hero-b2b-cta__lead){'
            . 'max-width:min(72ch,100%)!important;overflow-wrap:anywhere!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.page_footer :is(.footer-bottom,.footer-bottom-inner){padding-block:12px!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .content-top-home '
            . '.ayo-home5-wrapper--template-driven>.awa-carousel-section{'
            . 'overflow:visible!important;contain:layout!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(.awa-carousel-section,.top-home-content.awa-home-section){overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header :is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar){'
            . 'padding-block:0!important;padding-inline:0!important;box-sizing:border-box!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form,'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form .field.search,'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form .control{overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(.page-footer,.page_footer) .footer-bottom,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .footer-bottom{padding:12px clamp(16px,3vw,48px)!important;box-sizing:border-box!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(.page-footer,.page_footer) :is(.footer-container,#footer){'
            . 'padding-block:16px!important;box-sizing:border-box!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5){'
            . '--awa-font-family:"Source Sans 3",system-ui,sans-serif!important;'
            . '--awa-font-heading:"Rubik",system-ui,sans-serif!important;'
            . '--awa-font-body:"Source Sans 3",system-ui,sans-serif!important;'
            . '--awa-font-display:"Rubik",system-ui,sans-serif!important;'
            . '--vm-font:"Source Sans 3",system-ui,sans-serif!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is('
            . '.awa-search-helper-copy,.awa-category-carousel__subtitle,.awa-section-header__subtitle,'
            . '.awa-hero-b2b-cta__lead,#b2b-login-desc,.product-thumb .hot-onsale .onsale .sale-text){'
            . 'font-size:max(12px,.75rem)!important;line-height:1.45!important}'
            . 'html body#html-body .page-wrapper :is(.page-footer,.page_footer) :is('
            . '.velaFooterTitle,h4.velaFooterTitle,[id^="awa-footer-title-"]){'
            . 'color:oklch(22% .01 20)!important;background:transparent!important}'
            . 'html body#html-body .page-wrapper :is(.page-footer,.page_footer) :is('
            . '.vela-content,.vela-content.velaFooterMenu,.footer-container .col-lg-3,.footer-container .col-md-6){'
            . 'background:transparent!important;background-color:transparent!important}'
            . 'html body#html-body .page-wrapper :is('
            . '.awa-footer-atendimento__store,.awa-footer-atendimento__store-name,'
            . '.awa-footer-atendimento__store-address,p.awa-footer-atendimento__store-name,'
            . 'p.awa-footer-atendimento__store-address){color:oklch(22% .01 20)!important}'
            . 'html body#html-body .page-wrapper .awa-footer-atendimento__store{'
            . 'background:oklch(99% .002 20)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is('
            . '.awa-carousel-section,.top-home-content.awa-home-section,.ayo-home5-wrapper,.awa-shelf--carousel,'
            . '.item-product.awa-carousel-card-slot,.content-item-product.awa-product-card,'
            . '.rokan-bestseller.awa-shelf,.rokan-newproduct.awa-shelf){overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is('
            . '.awa-carousel__viewport,.awa-shelf-swiper,.swiper:not(.awa-hero-swiper),.swiper-wrapper,'
            . '.product-thumb,.wrapper_slider .swiper,.wrapper_slider .owl){overflow:hidden!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper :is('
            . '.wrapper_slider,.banner-slider,.banner-slider2){overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-owl-progress{overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-owl-progress__bar{'
            . 'width:100%!important;transform:scaleX(var(--awa-progress,.2))!important;'
            . 'transform-origin:left center!important;'
            . 'transition:transform .22s cubic-bezier(.22,1,.36,1)!important}'
            . 'html body#html-body .page-wrapper .awa-site-header form#search_mini_form .field.search{'
            . 'padding:4px 8px!important;box-sizing:border-box!important}'
            /* §109 shelf-carousel.css owns tier rhythm (featured/standard/compact); padding-block-start here caused 36/48 asymmetry on desktop */
            . 'html body#html-body .page-wrapper :is(.b2b-login-modal,#b2b-login-modal,.b2b-login-modal-overlay){'
            . 'padding:20px 24px!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu ul.togge-menu.list-category-dropdown'
            . ':is(.vmm-open,.menu-open,[aria-hidden="false"]){overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper{'
            . 'overflow-x:visible!important;overflow-y:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . ':is(#maincontent,.columns,.column.main){overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . 'html body#html-body .page-wrapper .product-thumb .hot-onsale .onsale{padding:4px 8px!important}'
            . 'html body#html-body .page-wrapper .product-thumb .hot-onsale .onsale .sale-text{'
            . 'padding:2px 4px!important;line-height:1.35!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.item-product.awa-carousel-card-slot,'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.content-item-product.awa-product-card{overflow:visible!important}'
            . '@media (max-width:767px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;'
            . 'gap:8px!important;align-items:stretch!important;margin:16px 0 0!important;padding:0!important;'
            . 'list-style:none!important;overflow:visible!important;flex-flow:unset!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item{display:flex!important;flex-direction:column!important;'
            . 'align-items:center!important;gap:8px!important;flex:1 1 auto!important;min-height:44px!important;'
            . 'max-width:none!important;padding:12px 8px!important;text-align:center!important;list-style:none!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item:last-child:nth-child(odd){'
            . 'grid-column:1/-1!important;max-width:calc(50% - 4px)!important;margin-inline:auto!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . 'svg.awa-hero-trust-strip__icon,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-trust-strip__icon{display:block!important;flex:0 0 40px!important;width:40px!important;'
            . 'min-width:40px!important;max-width:40px!important;height:40px!important;min-height:40px!important;'
            . 'max-height:40px!important}}'
            . '@media (min-width:576px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;'
            . 'gap:16px!important;align-items:stretch!important;margin:16px 0 0!important;padding:0!important;'
            . 'list-style:none!important;overflow:visible!important;flex-flow:unset!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item{display:flex!important;flex-direction:column!important;'
            . 'align-items:center!important;gap:12px!important;flex:1 1 auto!important;min-height:48px!important;'
            . 'max-width:none!important;padding:20px 12px!important;text-align:center!important;list-style:none!important;'
            . 'border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;'
            . 'background:var(--awa-bg,#fff)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item:last-child:nth-child(odd){'
            . 'grid-column:auto!important;max-width:none!important;margin-inline:0!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . 'svg.awa-hero-trust-strip__icon,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-trust-strip__icon{display:block!important;flex:0 0 40px!important;width:40px!important;'
            . 'min-width:40px!important;max-width:40px!important;height:40px!important;min-height:40px!important;'
            . 'max-height:40px!important}}'
            . '@media (min-width:768px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip{gap:20px!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip__item{padding:24px 16px!important;gap:16px!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . 'svg.awa-hero-trust-strip__icon,html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-trust-strip__icon{flex:0 0 48px!important;width:48px!important;min-width:48px!important;'
            . 'max-width:48px!important;height:48px!important;min-height:48px!important;max-height:48px!important}}'
            . '@media (min-width:992px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.awa-hero-b2b-cta .awa-hero-trust-strip{gap:24px!important}}'
            . 'html body#html-body .page-wrapper :is('
            . 'button,input,select,textarea,.action,.b2b-login-to-buy-btn,.b2b--listing,'
            . '.b2b-login-to-see-price,.b2b-login-to-see-price a){'
            . 'font-family:var(--awa-font-family,"Source Sans 3",system-ui,sans-serif)!important}'
            . 'html body#html-body :is(.toggle-nav-footer,.awa-cookie-banner__btn,.awa-back-to-top,'
            . '.b2b-login-modal-close,#awa-cookie-banner button){'
            . 'font-family:var(--awa-font-family,"Source Sans 3",system-ui,sans-serif)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.navigation.verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown{'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu :is('
            . 'ul.togge-menu.list-category-dropdown,.side-verticalmenu>ul.togge-menu){'
            . 'overflow:visible!important;padding:8px 12px!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu :is('
            . '.submenu,.level0.submenu,.navigation__submenu,.subchildmenu,.navigation__inner-list){'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .page_footer.awa-footer-exp-control{'
            . 'padding-block:clamp(16px,2vw,24px)!important;'
            . 'padding-inline:clamp(16px,3vw,48px)!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .page_footer.awa-footer-exp-control>:is('
            . 'section,.container,.footer-container,#footer,.footer-bottom){'
            . 'padding-inline:clamp(8px,1.5vw,16px)!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu,'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu '
            . '.togge-menu.list-category-dropdown,'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu '
            . '>ul.togge-menu.list-category-dropdown{contain:layout style!important;overflow:visible!important}'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu '
            . ':is(.level0.submenu,.level0>.level0.submenu,.subchildmenu,.navigation__submenu,'
            . '.navigation__inner-list){overflow:visible!important}'
            . '@media (min-width:992px){'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu '
            . '>ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open,[aria-hidden="false"],'
            . '[data-awa-menu-state="open"]),'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu '
            . '.togge-menu.list-category-dropdown{overflow:visible!important;overflow-x:visible!important;'
            . 'contain:layout style!important}'
            . 'html body#html-body .page-wrapper .navigation.verticalmenu.side-verticalmenu '
            . '>ul.togge-menu.list-category-dropdown>li.ui-menu-item.level0{overflow:visible!important}}'
            . '@media (min-width:992px){'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.menu_left_home1 .verticalmenu.side-verticalmenu>ul.togge-menu.list-category-dropdown:is('
            . '.menu-open,.vmm-open,[aria-hidden="false"],[data-awa-menu-state="open"]){'
            . 'overflow:visible!important;overflow-x:visible!important;contain:layout style!important}}';
    }

    public static function b2bRegisterSurfaceRules(): string
    {
        return 'html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell{'
            . 'background:oklch(98.5% .004 20)!important;border:0!important;box-shadow:none!important;'
            . 'padding:clamp(20px,4vw,34px)!important;border-radius:16px!important}'
            . 'html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell '
            . ':is(.b2b-register-container,.b2b-register-page){'
            . 'border:0!important;box-shadow:none!important;background:transparent!important;padding:0!important}'
            . 'html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell .b2b-register-progress{'
            . 'border:0!important;background:transparent!important;box-shadow:none!important;padding:0 0 12px!important}'
            . 'html body#html-body.b2b-register-index .page-wrapper #b2b-register-shell .form-section{'
            . 'border:0!important;background:transparent!important;box-shadow:none!important;padding:0 0 16px!important}'
            . 'html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper{'
            . 'overflow-x:clip!important;overflow-y:visible!important}'
            . 'html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper .awa-skip-link:not(:focus):not(:focus-visible){'
            . 'left:0!important;width:1px!important;height:1px!important;clip-path:inset(50%)!important;'
            . 'overflow:hidden!important;margin:-1px!important}'
            . 'html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper '
            . ':is(#awa-search-label,#awa-search-panel-a11y,.mst-searchautocomplete__autocomplete){'
            . 'overflow:visible!important}'
            . 'html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper '
            . 'li.ui-menu-item.navigation__item--parent{overflow:visible!important}'
            . 'html body#html-body:is(.b2b-auth-shell,.b2b-register-index) .page-wrapper '
            . ':is(.level0.submenu.navigation__submenu,.subchildmenu.navigation__inner-list){'
            . 'overflow:visible!important;padding-right:12px!important}';
    }

    public static function b2bDashboardSurfaceRules(): string
    {
        return 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{'
            . 'display:grid!important;grid-template-columns:1fr!important;align-items:start!important;'
            . 'width:100%!important;gap:8px!important}'
            . '@media (min-width:769px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns{'
            . 'grid-template-columns:220px minmax(0,1fr)!important;column-gap:32px!important;gap:32px!important}}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns>'
            . ':is(.sidebar-main,.sidebar.sidebar-main){grid-column:1!important;grid-row:1!important;'
            . 'min-width:0!important;max-width:100%!important;width:100%!important}'
            . '@media (min-width:769px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns>'
            . ':is(.sidebar-main,.sidebar.sidebar-main){min-width:220px!important;max-width:220px!important;'
            . 'width:220px!important;overflow:visible!important}}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns>.column.main{'
            . 'grid-column:1!important;grid-row:1!important;min-width:0!important}'
            . '@media (min-width:769px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>.columns>.column.main{'
            . 'grid-column:2!important;padding-inline:0!important}}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .block-collapsible-nav{'
            . 'padding:8px!important;border:0!important;box-shadow:none!important;'
            . 'background:oklch(98.5% .004 20)!important;border-radius:4px!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .block-collapsible-nav .item>a,'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .block-collapsible-nav .nav.item>a{'
            . 'padding:10px 14px!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .summary-card{'
            . 'box-shadow:none!important;border:1px solid oklch(92% .01 20)!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .summary-card:hover{'
            . 'transform:none!important;box-shadow:none!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper '
            . '.b2b-dashboard-lazy-panel[data-lazy-loaded="loading"]{'
            . 'padding:12px 16px!important;min-height:0!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper '
            . '.b2b-dashboard-lazy-panel[data-lazy-loaded="error"],'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper '
            . '.b2b-dashboard-lazy-panel.b2b-dashboard-lazy-panel--error{'
            . 'border:0!important;background:transparent!important;padding:8px 0!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main.container{'
            . 'max-width:var(--awa-page-dash,1280px)!important;width:100%!important;'
            . 'margin-inline:auto!important;padding-inline:var(--awa-page-pad,24px)!important;'
            . 'box-sizing:border-box!important}'
            . '@media (min-width:1025px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>'
            . ':is(.columns.layout.row,.columns.row){display:grid!important;'
            . 'grid-template-columns:minmax(248px,260px) minmax(0,1fr)!important;'
            . 'column-gap:28px!important;gap:28px!important;align-items:start!important;'
            . 'width:100%!important;margin:0!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>'
            . ':is(.columns.layout.row,.columns.row)>:first-child{grid-column:1!important;'
            . 'width:260px!important;min-width:260px!important;max-width:260px!important;'
            . 'padding:0!important;float:none!important;box-sizing:border-box!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>'
            . ':is(.columns.layout.row,.columns.row)>.col-main{grid-column:2!important;'
            . 'width:100%!important;max-width:none!important;min-width:0!important;'
            . 'padding:0!important;float:none!important;box-sizing:border-box!important}}'
            . '@media (min-width:769px) and (max-width:1024px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>'
            . ':is(.columns.layout.row,.columns.row){display:grid!important;'
            . 'grid-template-columns:minmax(232px,240px) minmax(0,1fr)!important;'
            . 'column-gap:20px!important;gap:20px!important;margin:0!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>'
            . ':is(.columns.layout.row,.columns.row)>:first-child{grid-column:1!important;'
            . 'width:240px!important;min-width:240px!important;max-width:240px!important;'
            . 'padding:0!important;float:none!important;box-sizing:border-box!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .page-main>'
            . ':is(.columns.layout.row,.columns.row)>.col-main{grid-column:2!important;'
            . 'width:100%!important;min-width:0!important;padding:0!important;float:none!important}}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper #block-collapsible-nav '
            . ':is(ul.nav.items,.account-nav-items){display:flex!important;flex-direction:column!important;'
            . 'gap:2px!important;width:100%!important;margin:0!important;padding:0!important;'
            . 'list-style:none!important;columns:1!important;column-count:1!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper #block-collapsible-nav '
            . ':is(li.nav.item,.account-nav-item,.account-nav-section){display:block!important;'
            . 'width:100%!important;max-width:100%!important;float:none!important;clear:both!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper #block-collapsible-nav '
            . ':is(li.nav.item>a,li.nav.item>strong,.account-nav-link,.account-nav-text){'
            . 'display:block!important;width:100%!important;min-height:36px!important;'
            . 'padding:8px 10px!important;border-radius:4px!important;font-size:13px!important;'
            . 'line-height:1.35!important;white-space:normal!important;overflow:visible!important;'
            . 'text-overflow:clip!important;word-break:normal!important;overflow-wrap:normal!important;'
            . 'text-align:left!important;box-sizing:border-box!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper '
            . ':is(.sidebar-additional1,.sidebar .block-reorder){display:none!important}';
    }

    /**
     * Painel B2B ativo: oculta o prompt legado em qualquer superfície (conta, pedidos, cotações…).
     */
    public static function headerB2bPanelCoexistenceRules(): string
    {
        return 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-right-col:has(.b2b-status-panel)>:is(.awa-header-account-prompt,.awa-header-contact-links),'
            . 'html body#html-body .page-wrapper .awa-site-header:has(.b2b-status-panel) '
            . ':is(.top-account.awa-header-account-nav,nav.top-account.has-child.awa-header-account-nav),'
            . 'html body#html-body .page-wrapper .awa-site-header .b2b-status-panel~'
            . ':is(.top-account.awa-header-account-nav,nav.top-account.has-child.awa-header-account-nav){'
            . 'display:none!important;visibility:hidden!important;pointer-events:none!important;'
            . 'width:0!important;min-width:0!important;max-width:0!important;margin:0!important;padding:0!important;'
            . 'height:0!important;min-height:0!important;max-height:0!important;'
            . 'overflow:hidden!important;opacity:0!important;position:absolute!important;'
            . 'clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important}'
            // awa-super-global aplica gradiente/pill no trigger — reset terminal em todas as superfícies
            . 'html body#html-body .page-wrapper .awa-site-header .b2b-status-panel .b2b-status-trigger{'
            . 'background:transparent!important;background-image:none!important;'
            . 'border:0!important;border-radius:0!important;box-shadow:none!important;'
            . 'padding:0!important;padding-inline:0!important;min-block-size:0!important;min-height:0!important}';
    }

    /**
     * Prompt logado em FPC (home/PLP/carrinho) — sem card/pill; painel B2B cobre páginas de conta.
     */
    public static function headerLoggedPromptCleanRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body:not(.b2b-account-dashboard) .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt[data-awa-auth-state="customer"]{'
            . 'padding:0!important;border:0!important;background:transparent!important;'
            . 'background-image:none!important;box-shadow:none!important;border-radius:0!important}'
            . 'html body#html-body:not(.b2b-account-dashboard) .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt[data-awa-auth-state="customer"] '
            . ':is(.awa-account-dropdown__trigger,.awa-header-account-prompt__link){'
            . 'padding:0!important;border:0!important;background:transparent!important;'
            . 'background-image:none!important;box-shadow:none!important;border-radius:0!important}'
            . '}';
    }

    /**
     * BUG-04: prompt de conta desktop — min 44px touch, altura auto para não clipar 2 linhas.
     */
    public static function headerAccountPromptCompactRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-contact-links.awa-header-account-prompt,'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt{'
            . 'display:inline-flex!important;align-items:flex-start!important;'
            . 'height:auto!important;min-height:44px!important;max-height:none!important;'
            . 'padding-block:6px!important;padding-inline:0!important;margin:0!important;'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt__icon{'
            . 'width:28px!important;min-width:28px!important;height:28px!important;'
            . 'min-height:28px!important;display:inline-flex!important;align-items:center!important}'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt__text,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt__guest,'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt__customer{'
            . 'display:flex!important;flex-direction:column!important;justify-content:center!important;'
            . 'gap:1px!important;min-height:0!important;line-height:1.35!important;'
            . 'overflow:visible!important}'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt__line1{'
            . 'font-size:12px!important;line-height:1.35!important;white-space:nowrap!important}'
            . 'html body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt__line2{'
            . 'font-size:13px!important;line-height:1.35!important;white-space:nowrap!important}'
            . '}';
    }

    /**
     * Regras mínimas de header para superfícies sem cascade-lock (~112KB).
     */
    public static function headerEssentialTerminalRules(): string
    {
        return self::headerB2bPanelCoexistenceRules()
            . self::headerMinicartGhostTerminalRules()
            . self::headerLoggedPromptCleanRules()
            . self::headerAccountPromptCompactRules()
            . self::headerStickyPositionFixRules();
    }

    /**
     * FIX 2026-07-06 (Impeccable "Nested cards" div.header-wrapper-sticky.is-sticky):
     * o CSS que troca position:sticky -> position:fixed vive em
     * awa-align-grid-terminal-2026-06-11.min.css, mas esse arquivo só é carregado
     * pelo awa-css-gate (JS) após interação real do usuário ou timeout de 18s
     * (ver bootstrap inline em OptimizeHeadStylesPlugin). Isso deixa o header
     * "quebrado" (scrolla junto com a página) nos primeiros segundos de qualquer
     * visita — inclusive para o crawler do Impeccable, que não interage.
     * Solução: duplicar a regra crítica aqui, no CSS síncrono (inline, sem gate),
     * para que o header já nasça fixo assim que .is-sticky é aplicado pelo JS de
     * scroll (awa-header-sticky.js), sem depender do carregamento adiado.
     */
    public static function headerStickyPositionFixRules(): string
    {
        return 'html body#html-body:not(.onepagecheckout-index-index):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header[data-awa-header-mode=default] .header-wrapper-sticky.is-sticky{'
            . 'position:fixed!important;top:0!important;left:0!important;right:0!important;'
            . 'width:100%!important;z-index:1000!important;overflow:visible!important;contain:none!important}';
    }

    /**
     * Minicart ghost (ícone vermelho outline) — todas as páginas exceto checkout e dashboard B2B.
     * O botão vermelho sólido fica restrito a b2bDashboardHeaderRules().
     */
    public static function headerMinicartGhostTerminalRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger){'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:44px!important;min-width:44px!important;height:44px!important;min-height:44px!important;'
            . 'padding:0!important;border:0!important;border-radius:0!important;'
            . 'background:transparent!important;color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'box-shadow:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger):is(:hover,:focus-visible){'
            . 'background:transparent!important;box-shadow:none!important;opacity:.88!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon){'
            . 'display:inline-block!important;visibility:visible!important;opacity:1!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon),'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon) path{'
            . 'width:28px!important;height:28px!important;min-width:28px!important;min-height:28px!important;'
            . 'stroke:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important;fill:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon) circle{'
            . 'fill:var(--awa-primary,oklch(48% .14 20))!important;stroke:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.b2b-account-dashboard) '
            . '.page-wrapper .awa-site-header .awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) .counter.qty:not(.empty){'
            . 'position:absolute!important;top:auto!important;bottom:2px!important;right:0!important;'
            . 'width:auto!important;min-width:14px!important;max-width:34px!important;height:14px!important;'
            . 'padding:0 4px!important;font-size:10px!important;font-weight:700!important;line-height:1!important;'
            . 'background:var(--awa-bg-surface,#fff)!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'border:1px solid var(--awa-primary,oklch(48% .14 20))!important;'
            . 'box-shadow:none!important;border-radius:999px!important}'
            . '}';
    }

    public static function b2bDashboardHeaderRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header{'
            . 'background:var(--awa-surface,oklch(99% .002 20))!important;'
            . 'box-shadow:0 1px 0 var(--awa-border,oklch(90% .008 20))!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.header-wrapper-sticky{min-height:68px!important;max-height:68px!important;'
            . 'padding-block:0!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap){'
            . 'min-height:68px!important;height:68px!important;max-height:68px!important;'
            . 'padding:0 clamp(16px,3vw,24px)!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'display:grid!important;grid-template-areas:"brand search actions"!important;'
            . 'grid-template-columns:minmax(104px,148px) minmax(360px,620px) minmax(88px,180px)!important;'
            . 'align-items:center!important;justify-content:center!important;gap:24px!important;'
            . 'width:100%!important;max-width:1224px!important;height:68px!important;'
            . 'min-height:68px!important;max-height:68px!important;margin-inline:auto!important;'
            . 'padding:0!important;box-sizing:border-box!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-primary-row,.awa-header-right-col){display:contents!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-brand-cell,.col-md-2.awa-header-brand){'
            . 'grid-area:brand!important;display:flex!important;align-items:center!important;'
            . 'justify-content:flex-start!important;height:68px!important;min-height:68px!important;'
            . 'max-height:68px!important;min-width:0!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-brand-cell,.col-md-2.awa-header-brand) :is(.logo,.logo a){'
            . 'display:flex!important;align-items:center!important;justify-content:flex-start!important;'
            . 'height:56px!important;max-height:56px!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-brand-cell,.col-md-2.awa-header-brand) .logo img{'
            . 'display:block!important;width:auto!important;height:44px!important;max-height:44px!important;'
            . 'object-fit:contain!important;object-position:center!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-search-col,.top-search){'
            . 'grid-area:search!important;display:block!important;width:100%!important;'
            . 'min-width:0!important;max-width:620px!important;margin:0!important;'
            . 'padding:0!important;align-self:center!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-search-col,.top-search) :is(.block-search,.block-content){'
            . 'display:block!important;width:100%!important;min-width:0!important;'
            . 'max-width:100%!important;height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-search-col,.top-search) :is(form#search_mini_form,form.minisearch){'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;'
            . 'grid-template-areas:"field submit"!important;align-items:stretch!important;'
            . 'width:100%!important;min-width:0!important;max-width:100%!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'margin:0!important;padding:0!important;background:var(--awa-surface,oklch(99% .002 20))!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'border-radius:10px!important;box-shadow:none!important;overflow:hidden!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . 'form#search_mini_form :is(.field.search,.field.search .control){'
            . 'grid-area:field!important;display:flex!important;width:100%!important;'
            . 'min-width:0!important;height:44px!important;min-height:44px!important;'
            . 'margin:0!important;padding:0!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . 'form#search_mini_form :is(input#search,input[name=q]){'
            . 'position:static!important;width:100%!important;min-width:0!important;'
            . 'height:44px!important;min-height:44px!important;border:0!important;'
            . 'box-shadow:none!important;padding:0 14px!important;font-size:14px!important;'
            . 'line-height:44px!important;background:transparent!important;color:var(--awa-text,oklch(22% .02 20))!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . 'form#search_mini_form .actions{'
            . 'grid-area:submit!important;display:flex!important;align-items:stretch!important;'
            . 'justify-content:center!important;width:44px!important;height:44px!important;'
            . 'min-width:44px!important;min-height:44px!important;margin:0!important;padding:0!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . 'form#search_mini_form .action.search{'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:44px!important;min-width:44px!important;height:44px!important;min-height:44px!important;'
            . 'margin:0!important;padding:0!important;border:0!important;border-radius:0!important;'
            . 'background:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-minicart,.mini-cart-wrapper,.minicart-wrapper){'
            . 'grid-area:actions!important;justify-self:start!important;align-self:center!important;'
            . 'position:relative!important;display:flex!important;align-items:center!important;'
            . 'justify-content:flex-start!important;width:auto!important;min-width:44px!important;'
            . 'height:44px!important;min-height:44px!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger){'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:44px!important;min-width:44px!important;height:44px!important;min-height:44px!important;'
            . 'margin:0!important;padding:0!important;border-radius:10px!important;'
            . 'background:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            // Especificidade boostada (.awa-header-minicart .minicart-wrapper) para vencer o
            // contrato ghost do _awa-header-stack.less §5, que força stroke vermelho no ícone
            // — vermelho sobre o botão vermelho deixava o cart como bloco sólido sem ícone.
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon),'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon){'
            . 'display:inline-block!important;visibility:visible!important;opacity:1!important;'
            . 'width:20px!important;height:20px!important;min-width:20px!important;min-height:20px!important;'
            . 'fill:none!important;stroke:currentColor!important;color:currentColor!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon) path{'
            . 'stroke:currentColor!important;color:currentColor!important;fill:none!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) :is(svg,.awa-minicart-icon) circle{'
            . 'fill:currentColor!important;stroke:none!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) .counter.qty:not(.empty){'
            . 'position:absolute!important;top:-6px!important;bottom:auto!important;right:-6px!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:auto!important;min-width:18px!important;max-width:34px!important;'
            . 'height:18px!important;padding:0 4px!important;box-sizing:border-box!important;'
            . 'border-radius:999px!important;overflow:hidden!important;'
            . 'background:var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'border:1px solid var(--awa-primary,oklch(48% .14 20))!important;'
            . 'font-size:10px!important;font-weight:800!important;line-height:16px!important;white-space:nowrap!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) .counter.qty:not(.empty) '
            . ':is(.total-mini-cart-item,.counter-number){'
            . 'position:static!important;display:inline!important;width:auto!important;height:auto!important;'
            . 'color:inherit!important;font-size:inherit!important;line-height:inherit!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-minicart .minicart-wrapper '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-minicart-trigger) .counter.qty.empty{'
            . 'display:none!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.awa-header-right-col:has(.b2b-status-panel)>:is(.awa-header-account-prompt,.awa-header-contact-links),'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-header-account-prompt,.awa-header-contact-links,.top-account.awa-header-account-nav,'
            . 'nav.top-account.has-child.awa-header-account-nav){'
            . 'display:none!important;visibility:hidden!important;pointer-events:none!important;'
            . 'width:0!important;min-width:0!important;max-width:0!important;margin:0!important;padding:0!important;'
            . 'height:0!important;min-height:0!important;max-height:0!important;'
            . 'overflow:hidden!important;opacity:0!important;position:absolute!important;'
            . 'clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.awa-nav-bar){'
            . 'display:block!important;background:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'border:0!important;box-shadow:none!important;min-height:48px!important;'
            . 'height:48px!important;max-height:48px!important;padding:0 clamp(16px,3vw,24px)!important;'
            . 'overflow:visible!important;box-sizing:border-box!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.awa-nav-bar) '
            . ':is(.container,.row,.awa-nav-bar__inner){'
            . 'display:flex!important;align-items:center!important;justify-content:space-between!important;'
            . 'width:100%!important;max-width:1224px!important;height:48px!important;'
            . 'min-height:48px!important;max-height:48px!important;margin-inline:auto!important;'
            . 'padding:0!important;box-sizing:border-box!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]){'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'height:44px!important;min-height:44px!important;padding:0 16px!important;'
            . 'background:color-mix(in srgb,var(--awa-primary,oklch(48% .14 20)) 86%,oklch(20% .02 20))!important;'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important;border:0!important;'
            . 'border-radius:8px!important;font-weight:700!important;line-height:1!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-nav-quick-links,.custommenu.main-nav,.navigation.custommenu){'
            . 'display:flex!important;align-items:center!important;justify-content:flex-end!important;'
            . 'height:48px!important;min-height:48px!important;margin-left:auto!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-nav-quick-links__list,.custommenu.main-nav>ul,.navigation.custommenu>ul){'
            . 'display:flex!important;align-items:center!important;gap:clamp(18px,3vw,48px)!important;'
            . 'height:48px!important;min-height:48px!important;margin:0!important;padding:0!important;'
            . 'list-style:none!important;overflow:visible!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.awa-nav-quick-links__link,.custommenu.main-nav a,.navigation.custommenu a){'
            . 'display:inline-flex!important;align-items:center!important;min-height:44px!important;'
            . 'padding:0 4px!important;color:var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'font-size:13px!important;font-weight:600!important;line-height:1.2!important;text-decoration:none!important}'
            . '}'
            . '@media (max-width:991px){'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.awa-nav-bar){'
            . 'display:none!important;visibility:hidden!important;height:0!important;min-height:0!important;'
            . 'max-height:0!important;padding:0!important;overflow:hidden!important}'
            . 'html body#html-body.b2b-account-dashboard .page-wrapper .awa-site-header '
            . '.header-wrapper-sticky{min-height:96px!important;max-height:none!important}'
            . '}';
    }

    public static function headerNavShellRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .header-control.awa-nav-bar .awa-header-primary-nav.menu_primary:has(nav.top-menu:empty){'
            . 'display:none!important;visibility:hidden!important;flex:0 0 0!important;'
            . 'width:0!important;min-width:0!important;max-width:0!important;height:0!important;'
            . 'min-height:0!important;max-height:0!important;margin:0!important;padding:0!important;'
            . 'overflow:hidden!important;pointer-events:none!important;border:0!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .header-control.awa-nav-bar .awa-nav-bar__inner{'
            . 'display:flex!important;align-items:stretch!important;justify-content:flex-start!important;'
            . 'gap:0!important;width:100%!important;max-width:100%!important;'
            . 'min-height:var(--awa-nav-bar-h,48px)!important;height:var(--awa-nav-bar-h,48px)!important;'
            . 'max-height:var(--awa-nav-bar-h,48px)!important;margin:0!important;padding:0!important;'
            . 'box-sizing:border-box!important;overflow:visible!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .header-control.awa-nav-bar .awa-nav-quick-links{'
            . 'display:flex!important;align-items:center!important;justify-content:flex-end!important;'
            . 'flex:1 1 auto!important;min-width:0!important;margin-left:auto!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;background:transparent!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .header-control.awa-nav-bar .awa-nav-quick-links__list{'
            . 'display:flex!important;align-items:center!important;gap:clamp(16px,2.5vw,40px)!important;'
            . 'margin:0!important;padding:0!important;list-style:none!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header :is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar){'
            . 'width:100vw!important;max-width:100vw!important;'
            . 'margin-inline:calc(50% - 50vw)!important;'
            . 'margin-block:0!important;padding:0!important;'
            . 'border:0!important;box-shadow:none!important;overflow:visible!important;'
            . 'background:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'background-color:var(--awa-primary,oklch(48% .14 20))!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar > .container{'
            . 'width:100%!important;max-width:var(--awa-container-catalog,var(--awa-container-max,1440px))!important;'
            . 'margin-inline:auto!important;padding-inline:clamp(16px,3vw,24px)!important;'
            . 'background:transparent!important;border:0!important;overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar .awa-nav-bar__inner{'
            . 'background:transparent!important;border:0!important;overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar '
            . ':is(.awa-header-categories,.awa-nav-quick-links,.sections.nav-sections,.section-items){'
            . 'background:transparent!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar .awa-header-categories.menu_left_home1{'
            . 'flex:0 0 auto!important;max-width:none!important;min-width:0!important;background:transparent!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar '
            . ':is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]){'
            . 'display:inline-flex!important;align-items:center!important;gap:8px!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;min-height:var(--awa-nav-bar-h,48px)!important;'
            . 'max-height:var(--awa-nav-bar-h,48px)!important;padding:0 18px!important;margin:0!important;'
            . 'border:0!important;border-radius:0!important;box-shadow:none!important;'
            . 'background:transparent!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar '
            . ':is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]):hover,'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar '
            . ':is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]):focus-visible{'
            . 'background:color-mix(in srgb,var(--awa-text-inverse,oklch(99% .002 20)) 12%,transparent)!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar '
            . ':is(.awa-nav-quick-links__link,.header-wrapper-sticky .awa-nav-quick-links__link){'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar .awa-nav-quick-links__link::after{'
            . 'background:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5):not(.checkout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.awa-nav-bar .awa-nav-quick-links__link:hover{'
            . 'color:var(--awa-text-inverse,oklch(99% .002 20))!important}'
            . '}';
    }

    public static function headerVisualStandardRules(): string
    {
        return 'html body#html-body .page-wrapper .awa-site-header{'
            . '--awa-header-control-h:44px;--awa-header-control-radius:10px;'
            . '--awa-header-main-row-h:68px;--awa-header-row-h:var(--awa-header-main-row-h);'
            . '--awa-header-nav-h:48px;--awa-nav-bar-h:var(--awa-header-nav-h)}'
            . '@media (min-width:992px){'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .header_main.awa-main-header-inner-wrap .container{'
            . 'max-width:var(--awa-container-catalog,var(--awa-container-max,1440px))!important;'
            . 'width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:clamp(16px,3vw,24px)!important;box-sizing:border-box!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'max-width:var(--awa-container-catalog,var(--awa-container-max,1440px))!important;'
            . 'width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:clamp(16px,3vw,24px)!important;box-sizing:border-box!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) var(--awa-header-control-h)!important;'
            . 'grid-template-areas:"field submit"!important;align-items:stretch!important;'
            . 'height:var(--awa-header-control-h)!important;'
            . 'min-height:var(--awa-header-control-h)!important;max-height:var(--awa-header-control-h)!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'border-radius:var(--awa-header-control-radius)!important;overflow:hidden!important;'
            . 'background:var(--awa-surface,oklch(99% .002 20))!important;box-shadow:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form :is(.field.search,.field.search .control){'
            . 'grid-area:field!important;display:flex!important;width:100%!important;min-width:0!important;'
            . 'max-width:100%!important;height:var(--awa-header-control-h)!important;'
            . 'min-height:var(--awa-header-control-h)!important;margin:0!important;padding:0!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form input#search{'
            . 'position:static!important;width:100%!important;min-width:0!important;max-width:100%!important;'
            . 'flex:1 1 auto!important;box-sizing:border-box!important;'
            . 'height:var(--awa-header-control-h)!important;min-height:var(--awa-header-control-h)!important;'
            . 'line-height:var(--awa-header-control-h)!important;font-size:14px!important;border:0!important;'
            . 'box-shadow:none!important;padding:0 14px!important;background:transparent!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form .actions{'
            . 'grid-area:submit!important;display:flex!important;align-items:stretch!important;'
            . 'justify-content:center!important;width:var(--awa-header-control-h)!important;'
            . 'height:var(--awa-header-control-h)!important;min-width:var(--awa-header-control-h)!important;'
            . 'min-height:var(--awa-header-control-h)!important;margin:0!important;padding:0!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header form#search_mini_form .action.search{'
            . 'width:var(--awa-header-control-h)!important;min-width:var(--awa-header-control-h)!important;'
            . 'height:var(--awa-header-control-h)!important;min-height:var(--awa-header-control-h)!important;'
            . 'border-radius:0!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-search-col{'
            . 'align-self:center!important;height:var(--awa-header-control-h)!important;'
            . 'min-height:var(--awa-header-control-h)!important;max-height:var(--awa-header-control-h)!important;'
            . 'overflow:visible!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-search-col .block-search{'
            . 'height:var(--awa-header-control-h)!important;min-height:var(--awa-header-control-h)!important;'
            . 'max-height:var(--awa-header-control-h)!important;overflow:visible!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-search-col :is(.block-search .label,.block-search .block-title,.nested){'
            . 'display:none!important;visibility:hidden!important;height:0!important;max-height:0!important;'
            . 'overflow:hidden!important;pointer-events:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-search-col .awa-search-helper-copy{'
            . 'display:none!important;visibility:hidden!important;height:0!important;max-height:0!important;'
            . 'margin:0!important;overflow:hidden!important;pointer-events:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt[data-awa-is-home="1"] '
            . ':is(.awa-header-account-prompt__link--register,.awa-header-account-prompt__separator){'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'overflow:hidden!important;margin:0!important;padding:0!important;pointer-events:none!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt__icon svg{'
            . 'width:24px!important;height:24px!important;display:block!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .awa-header-right-col{'
            . 'display:flex!important;align-items:center!important;gap:clamp(8px,1.2vw,14px)!important}'
            . '}';
    }

    public static function verticalMenuTerminalRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu .awa-vmenu-search-wrap{'
            . 'position:relative!important;display:flex!important;align-items:center!important;width:100%!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu .awa-vmenu-search-icon{'
            . 'position:absolute!important;left:10px!important;top:50%!important;transform:translateY(-50%)!important;'
            . 'width:14px!important;height:14px!important;max-width:14px!important;max-height:14px!important;'
            . 'flex:0 0 14px!important;pointer-events:none!important;overflow:hidden!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu .awa-vmenu-search-icon svg{'
            . 'display:block!important;width:14px!important;height:14px!important;'
            . 'max-width:14px!important;max-height:14px!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu .awa-vmenu-search-input{'
            . 'width:100%!important;min-height:34px!important;height:auto!important;max-height:none!important;'
            . 'padding:7px 10px 7px 32px!important;font-size:12.5px!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu '
            . '[data-role="awa-vmenu-search-row"]{display:block!important;list-style:none!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu.side-verticalmenu '
            . '> ul.togge-menu.list-category-dropdown:is(.vmm-open,.menu-open,[aria-hidden="false"]){'
            . 'position:absolute!important;inset-block-start:100%!important;inset-inline-start:0!important;'
            . 'z-index:100100!important;width:290px!important;min-width:290px!important;max-width:290px!important;'
            . 'overflow-x:hidden!important;overflow-y:auto!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .navigation.verticalmenu.side-verticalmenu '
            . '> ul.togge-menu.list-category-dropdown:is(.vmm-open,.menu-open,[aria-hidden="false"]) svg:not('
            . '.awa-vmenu-trigger-icon svg,.awa-vmenu-search-icon svg,.awa-vmenu-search-empty-icon svg'
            . '){max-width:20px!important;max-height:20px!important}'
            . '}';
    }

    public static function headerStickyShellRules(): string
    {
        return '@media (min-width:768px) and (max-width:991px){'
            . 'html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky,'
            . 'html body#html-body .page-wrapper #header .header-wrapper-sticky{'
            . 'height:auto!important;min-height:96px!important;max-height:none!important;'
            . 'overflow:visible!important;contain:none!important;padding-block:0!important;'
            . 'box-sizing:border-box!important}'
            . 'html body#html-body:has(.awa-site-header){scroll-padding-block-start:128px!important}'
            . 'html body#html-body .awa-site-header #awa-main-content{scroll-margin-block-start:128px!important}'
            . 'html{scroll-padding-top:128px!important}'
            . ':root{--awa-header-scroll-offset:128px!important;--awa-header-site-shell-h:128px!important;'
            . '--awa-header-sticky-h:96px!important}'
            . '}'
            . '@media (min-width:992px){'
            . 'html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky,'
            . 'html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky.is-sticky,'
            . 'html body#html-body .page-wrapper #header .header-wrapper-sticky,'
            . 'html body#html-body .page-wrapper #header .header-wrapper-sticky.is-sticky{'
            . 'height:auto!important;min-height:64px!important;max-height:64px!important;'
            . 'overflow:visible!important;contain:none!important;padding-block:0!important;'
            . 'box-sizing:border-box!important}'
            . 'html body#html-body:has(.awa-site-header){scroll-padding-block-start:136px!important}'
            . 'html body#html-body .awa-site-header #awa-main-content{scroll-margin-block-start:136px!important}'
            . 'html{scroll-padding-top:136px!important}'
            . ':root{--awa-header-scroll-offset:136px!important;--awa-header-site-shell-h:136px!important;'
            . '--awa-header-sticky-h:104px!important}'
            . '}';
    }

    public static function mobileHeaderSearchLayoutRules(): string
    {
        return '';
    }

    /**
     * Mobile ≤767 — stack 112px (32 promo + 80 main). Padding só no inner row.
     * Sem !important; escopo .awa-site-header apenas.
     */
    public static function mobileHeaderCompact112Rules(): string
    {
        $root = '.awa-site-header[data-awa-header-mode="default"]';
        $sticky = $root . ' .header-wrapper-sticky';
        $inner = $sticky . ' :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';

        return '@media (max-width:767px){'
            . $root . '{'
            . '--awa-header-promo-h:44px;--awa-header-main-row-h:96px;--awa-header-shell-pad:12px;'
            . '--awa-header-stack-h:calc(var(--awa-header-promo-h)+var(--awa-header-main-row-h));'
            . '--awa-header-scroll-offset:140px;--awa-header-sticky-h:96px'
            . '}'
            . $root . ' .header-container:has(.awa-b2b-promo-bar),'
            . $root . ' .header-container:has(.awa-b2b-promo-bar) .header-content{'
            . 'height:var(--awa-header-promo-h);min-height:var(--awa-header-promo-h);'
            . 'max-height:var(--awa-header-promo-h);padding-block:0;margin-block:0;box-sizing:border-box'
            . '}'
            . $sticky . '{'
            . 'min-height:var(--awa-header-main-row-h);height:auto;max-height:none;'
            . 'padding-block:0;margin-block:0;box-sizing:border-box;overflow:visible'
            . '}'
            . $sticky . ' :is(.header.awa-main-header,.header-main,.header_main,.header-main>.container,.header_main>.container){'
            . 'display:block;height:var(--awa-header-main-row-h);min-height:var(--awa-header-main-row-h);'
            . 'max-height:var(--awa-header-main-row-h);margin:0;padding:0;border:0;'
            . 'box-sizing:border-box;overflow:visible'
            . '}'
            . $sticky . ' .header.awa-main-header{padding-block:0}'
            . $inner . '{'
            . 'display:grid;grid-template-areas:"toggle brand cart" "search search search";'
            . 'grid-template-columns:44px minmax(0,1fr) 44px;grid-template-rows:44px 44px;gap:4px 8px;'
            . 'width:100%;max-width:min(100%,1280px);margin-inline:auto;'
            . 'padding-inline:var(--awa-header-shell-pad);padding-block:0;'
            . 'height:var(--awa-header-main-row-h);min-height:var(--awa-header-main-row-h);'
            . 'max-height:var(--awa-header-main-row-h);box-sizing:border-box;overflow:visible'
            . '}'
            . $sticky . ' .awa-header-primary-row{display:contents}'
            . $sticky . ' .awa-header-right-col{display:contents}'
            . $sticky . ' :is(.awa-header-mobile-toggle,.action.nav-toggle,[data-action="toggle-nav"]){'
            . 'grid-area:toggle;width:44px;min-width:44px;height:44px;min-height:44px;'
            . 'margin:0;padding:0;align-self:center;justify-self:start'
            . '}'
            . $sticky . ' :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){'
            . 'grid-area:brand;align-self:center;justify-self:center;'
            . 'height:44px;max-height:44px;margin:0;padding:0;overflow:visible'
            . '}'
            . $sticky . ' :is(.awa-header-brand-cell,.col-md-2.awa-header-brand) :is(.logo,.logo a){'
            . 'display:flex;align-items:center;justify-content:flex-start;'
            . 'height:44px;margin:0;padding:0'
            . '}'
            . $sticky . ' .awa-header-brand-cell .logo img{'
            . 'height:32px;max-height:32px;width:auto;margin:0;padding:0;'
            . 'object-fit:contain;object-position:left center'
            . '}'
            . $sticky . ' .awa-header-search-col{'
            . 'grid-area:search;grid-column:1/-1;width:100%;min-width:0;'
            . 'height:44px;min-height:44px;max-height:44px;margin:0;padding:0'
            . '}'
            . $sticky . ' .awa-header-search-col :is(.block-search,.block-content){'
            . 'width:100%;height:44px;min-height:44px;max-height:44px;margin:0;padding:0'
            . '}'
            . $sticky . ' .awa-header-search-col form#search_mini_form{'
            . 'display:grid;grid-template-columns:minmax(0,1fr) 44px;'
            . 'grid-template-areas:"field submit";width:100%;height:44px;min-height:44px;'
            . 'max-height:44px;margin:0;padding:0;box-sizing:border-box;overflow:hidden;'
            . 'background:var(--awa-bg,#fff);border:1px solid var(--awa-border);border-radius:8px'
            . '}'
            . $sticky . ' .awa-header-search-col form#search_mini_form :is(.field.search,.field.search .control){'
            . 'grid-area:field;display:flex;width:100%;height:44px;min-height:44px;margin:0;padding:0'
            . '}'
            . $sticky . ' .awa-header-search-col form#search_mini_form input#search{'
            . 'width:100%;height:44px;min-height:44px;box-sizing:border-box;padding:0 8px;font-size:16px'
            . '}'
            . $sticky . ' .awa-header-search-col form#search_mini_form .actions{'
            . 'grid-area:submit;display:flex;width:44px;min-width:44px;height:44px;margin:0;padding:0'
            . '}'
            . $sticky . ' .awa-header-search-col form#search_mini_form .action.search{'
            . 'width:44px;min-width:44px;height:44px;min-height:44px;padding:0'
            . '}'
            . $sticky . ' :is(.awa-header-minicart,.mini-cart-wrapper,.minicart-wrapper){'
            . 'grid-area:cart;width:44px;min-width:44px;height:44px;min-height:44px;'
            . 'align-self:center;justify-self:end;margin:0;padding:0'
            . '}'
            . $sticky . ' :is(.awa-header-minicart,.mini-cart-wrapper,.minicart-wrapper) '
            . ':is(.action.showcart,.showcart.header-mini-cart,.awa-header-cart-fallback){'
            . 'width:44px;min-width:44px;height:44px;min-height:44px;'
            . 'display:inline-flex;align-items:center;justify-content:center;margin:0;padding:0'
            . '}'
            . $sticky . ' :is(.awa-header-account-prompt,.awa-b2b-mode-badge,.awa-header-account-nav){'
            . 'display:none;visibility:hidden;width:0;height:0;overflow:hidden;'
            . 'pointer-events:none;margin:0;padding:0'
            . '}'
            . $root . ' .header-control.awa-nav-bar{'
            . 'height:0;min-height:0;max-height:0;margin:0;padding:0;border:0;'
            . 'overflow:hidden;visibility:hidden;pointer-events:none'
            . '}'
            . ':root{--awa-header-scroll-offset:140px;--awa-header-sticky-h:96px}'
            . 'html{scroll-padding-top:140px}'
            . 'html body#html-body:has(.awa-site-header){scroll-padding-block-start:140px}'
            . 'html body#html-body .awa-site-header #awa-main-content{scroll-margin-block-start:140px}'
            . '}';
    }


    public static function visualCrawlSystemicRules(): string
    {
        return 'html body#html-body .page-wrapper #awa-b2b-promo-bar :is(button.awa-b2b-promo-close,.awa-b2b-promo-bar__cta),'
            . 'html body#html-body .page-wrapper .grid-mode-show-type-products a,'
            . 'html body#html-body .page-wrapper :is(.awa-b2b-gate-card__action,.b2b-login-to-see-price .price-label a,.b2b-login-to-see-price a),'
            . 'html body#html-body .page-wrapper div.b2b-login-to-see-price.price-box span.price-label>a,'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) :is(.awa-footer-section__toggle,.velaFooterLinks a,.awa-footer-atendimento__actions a,.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link,.awa-seal),'
            . 'html body#html-body .page-wrapper .block-search .action.search{'
            . 'align-items:center!important;box-sizing:border-box!important;display:inline-flex!important;'
            . 'justify-content:center!important;line-height:1.2!important;min-height:44px!important;'
            . 'min-width:44px!important;text-align:center!important;text-decoration:none!important}'
            . 'html body#html-body .page-wrapper #awa-b2b-promo-bar button.awa-b2b-promo-close,'
            . 'html body#html-body .page-wrapper .grid-mode-show-type-products a,'
            . 'html body#html-body .page-wrapper .block-search .action.search{height:44px!important;width:44px!important}'
            . 'html body#html-body .page-wrapper :is(.awa-b2b-gate-card__action,.b2b-login-to-see-price .price-label a,.b2b-login-to-see-price a){padding:8px 14px!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) :is(.awa-footer-section__toggle,.velaFooterLinks a,.awa-footer-atendimento__actions a,.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link){padding-block:7px!important}'
            . 'html body#html-body .page-wrapper .awa-category-hero__title{background-color:rgba(0,0,0,.56)!important;'
            . 'border-radius:var(--awa-radius-sm,8px)!important;color:rgb(255,255,255)!important;'
            . 'display:inline-block!important;padding:6px 12px!important;text-shadow:none!important}'
            . 'html body#html-body .page-wrapper :is(.awa-b2b-sku__label,.awa-nav-quick-links__item,.awa-nav-quick-links__link,.product-info-stock-sku .stock.available span){'
            . 'color:var(--awa-ink,oklch(22% .02 20))!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-trust-item{'
            . 'background-color:transparent!important;color:var(--awa-text-muted,oklch(45% .02 20))!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-atendimento__store-badge{'
            . 'background-color:rgba(183,51,55,.08)!important;color:var(--awa-primary,oklch(48% .14 20))!important;text-shadow:none!important}'
            . 'html body#html-body .page-wrapper .awa-pdp-whatsapp-cta{background-color:rgba(0,96,48,.92)!important;color:rgb(255,255,255)!important}'
            . 'html body#html-body .page-wrapper .awa-pdp-whatsapp-cta span{color:rgb(255,255,255)!important}'
            . 'html body#html-body .page-wrapper .awa-owl-nav__btn{background-color:rgba(0,0,0,.72)!important;color:rgb(255,255,255)!important;border-color:rgba(255,255,255,.32)!important}'
            . 'html body#html-body .page-wrapper .awa-owl-nav__btn svg{stroke:currentColor!important}'
            . 'html body#html-body .page-wrapper .awa-category-hero__eyebrow{background-color:rgba(0,0,0,.72)!important;border-radius:999px!important;color:rgb(255,255,255)!important;display:inline-block!important;padding:4px 10px!important;text-shadow:none!important}'
            . 'html body#html-body .page-wrapper :is(.b2b-login-forgot__text,.b2b-login-divider span){background-color:rgb(255,255,255)!important;color:rgb(51,51,51)!important;text-shadow:none!important}'
            . 'html body#html-body .page-wrapper .awa-sr-only{background-color:rgb(0,0,0)!important;color:rgb(255,255,255)!important}';
    }

    /**
     * Tipografia trust bar + atendimento (extraído de impeccableSurfaceRules).
     */
    public static function footerTrustSurfaceRules(): string
    {
        return 'html body#html-body .page-wrapper .page_footer :is(h2,h3).awa-newsletter-title{'
            . 'color:var(--awa-ink,oklch(22% .02 20))!important}'
            // BUG-CONTRAST-FOOTER-TRUST (2026-07-06): ver comentario em impeccableSurfaceRules().
            . 'html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy strong,'
            . 'html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-item .awa-footer-trust-copy strong{'
            . 'color:var(--awa-white,#fff)!important}'
            . 'html body#html-body .page-wrapper .awa-footer-trust-bar .awa-footer-trust-copy span{'
            . 'color:color-mix(in srgb,#fff 88%,transparent)!important}'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento p.awa-footer-atendimento__label--social,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__label--social{'
            . 'color:oklch(45% .02 20)!important}'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store{'
            . 'background:oklch(99% .002 20)!important;border:1px solid oklch(92% .01 20)!important;'
            . 'padding:12px!important;border-radius:8px!important}'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store '
            . 'p.awa-footer-atendimento__store-name,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento .awa-footer-atendimento__store '
            . 'p.awa-footer-atendimento__store-address,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__store-name,'
            . 'html body#html-body .page-wrapper .page_footer p.awa-footer-atendimento__store-address,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento__store-name,'
            . 'html body#html-body .page-wrapper .page_footer .awa-footer-atendimento__store-address{'
            . 'color:oklch(22% .01 20)!important}';
    }


    /**
     * Neutraliza containment legado do footer para evitar placeholder/altura fantasma.
     */
    public static function footerStructuralContainmentRules(): string
    {
        $scope = 'html body#html-body#html-body#html-body .page-wrapper .page-footer .page_footer';

        return $scope . ','
            . $scope . ' #footer,'
            . $scope . ' .footer-bottom,'
            . $scope . ' .awa-footer-devby,'
            . $scope . ' .awa-footer-categories-expand{'
            . 'content-visibility:visible!important;contain:none!important;'
            . 'contain-intrinsic-size:unset!important;box-sizing:border-box!important}'
            . $scope . '{display:flow-root!important;height:auto!important;min-height:0!important;'
            . 'max-height:none!important;overflow:visible!important}'
            . $scope . ' .awa-footer-devby{max-height:64px!important;overflow:hidden!important}';
    }


    /**
     * Compactacao do footer em CSS para remover dependencia de estilos inline tardios.
     */
    public static function footerNoJsCompactRules(): string
    {
        $scope = 'html body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer)';
        $shell = 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper footer.page-footer';
        $root = $shell . ' .page_footer';
        $hard = $root . ' #footer.footer-container';

        return '/*awa-footer-no-js-compact-v1*/'
            . $scope . ','
            . $scope . ' #footer,'
            . $scope . ' .footer-bottom{'
            . 'content-visibility:visible!important;contain:none!important;contain-intrinsic-size:unset!important;'
            . 'height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important}'
            . $scope . ' a{height:auto!important;min-width:0!important;overflow-wrap:anywhere!important}'
            . $scope . ' .footer-bottom{box-sizing:border-box!important;padding:16px!important}'
            . $scope . ' .footer-bottom-inner{'
            . 'box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(0,1fr)!important;'
            . 'gap:8px!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important}'
            . $scope . ' .footer-bottom > .container{'
            . 'display:block!important;height:auto!important;margin-inline:auto!important;max-width:100%!important;'
            . 'min-height:0!important;padding:0!important;width:min(100%,1280px)!important}'
            . $scope . ' :is(.velaFooterLinks a,.awa-footer-atendimento__actions a){'
            . 'height:auto!important;min-height:44px!important;min-width:0!important;padding-block:6px!important}'
            . $scope . ' :is(.velaFooterTitle,.awa-footer-section__toggle){'
            . 'height:auto!important;min-height:44px!important;margin-bottom:4px!important;padding-bottom:4px!important}'
            . $scope . ' :is(.awa-footer-atendimento,.awa-footer-atendimento .velaContent){'
            . 'gap:4px!important;height:auto!important;min-height:0!important;max-height:none!important}'
            . $scope . ' :is(.awa-footer-atendimento__phone,.awa-footer-atendimento__email,'
            . '.awa-footer-atendimento__store-address){'
            . 'height:auto!important;min-height:0!important;margin:0 0 4px!important;'
            . 'padding:0!important;line-height:1.25!important}'
            . $scope . ' :is(.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a){'
            . 'height:auto!important;min-height:44px!important;margin:0!important;padding:6px 0!important}'
            . $scope . ' .awa-footer-atendimento__store{'
            . 'height:auto!important;min-height:0!important;margin-bottom:8px!important;padding:8px!important;gap:2px!important}'
            . $scope . ' .awa-footer-categories-expand{'
            . 'height:auto!important;min-height:0!important;max-height:none!important;margin-top:8px!important;'
            . 'padding:8px 0 4px!important}'
            . $scope . ' .awa-footer-categories-expand__inner{'
            . 'display:grid!important;grid-template-columns:auto minmax(0,1fr)!important;align-items:center!important;'
            . 'gap:10px!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important}'
            . $scope . ' .awa-footer-categories-expand__panel{'
            . 'display:block!important;width:100%!important;min-width:0!important;height:auto!important;'
            . 'min-height:0!important;max-height:none!important;padding:0!important}'
            . $scope . ' .awa-footer-categories-list{'
            . 'display:flex!important;flex-wrap:wrap!important;gap:6px!important;width:100%!important;'
            . 'height:auto!important;min-height:0!important;margin:0!important;padding:0!important;list-style:none!important}'
            . $scope . ' .awa-footer-categories-list li{list-style:none!important;margin:0!important;padding:0!important}'
            . $scope . ' .awa-footer-categories-list a{'
            . 'height:32px!important;min-height:32px!important;padding:6px 10px!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__copyright{'
            . 'box-sizing:border-box!important;grid-column:1 / -1!important;flex:0 0 100%!important;width:100%!important;'
            . 'max-width:none!important;height:auto!important;margin-top:0!important;padding:6px 0 0!important;'
            . 'border-top:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'text-align:left!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__copyright :is(p,.awa-footer-copyright__legal,'
            . '.awa-footer-copyright__disclaimer){'
            . 'box-sizing:border-box!important;width:100%!important;max-width:none!important;margin:0!important;'
            . 'padding:0!important;font-size:11px!important;line-height:1.3!important}'
            . $scope . ' .footer-bottom .awa-footer-copyright__disclaimer{margin-top:2px!important}'
            . $scope . ' .awa-footer-devby{'
            . 'block-size:56px!important;height:56px!important;min-height:0!important;max-height:56px!important;'
            . 'padding:6px 0!important;overflow:hidden!important}'
            . $scope . ' .awa-footer-devby .container,'
            . $scope . ' .awa-footer-devby__inner{'
            . 'block-size:36px!important;height:36px!important;min-height:36px!important;max-height:36px!important}'
            . '@media (max-width:767px){'
            . $scope . ' a{min-height:44px!important}'
            . $scope . ' #footer.footer-container{padding:10px 16px 8px!important}'
            . $scope . ' .awa-footer-trust-bar{padding:12px 0!important;margin-bottom:8px!important}'
            . $scope . ' .awa-footer-trust-grid{gap:8px!important;padding:8px 0!important}'
            . $scope . ' .awa-footer-trust-item{'
            . 'display:grid!important;grid-template-columns:36px minmax(0,1fr)!important;align-items:center!important;'
            . 'gap:10px!important;min-height:56px!important;height:auto!important;padding:8px 10px!important}'
            . $scope . ' .awa-footer-trust-icon{width:36px!important;height:36px!important;min-width:36px!important}'
            . $scope . ' .awa-footer-trust-copy{'
            . 'display:flex!important;flex-direction:column!important;align-items:flex-start!important;'
            . 'gap:0!important;width:100%!important}'
            . $scope . ' .awa-footer-newsletter{margin-bottom:8px!important;padding:10px 12px!important}'
            . $scope . ' :is(.awa-newsletter-wrapper,.awa-newsletter-form-container,.newsletter-footer,'
            . '.form.subscribe,.field.newsletter){'
            . 'box-sizing:border-box!important;height:auto!important;min-height:0!important;max-height:none!important;'
            . 'margin:0!important;padding:0!important;max-width:min(100%,calc(100vw - 32px))!important}'
            . $scope . ' :is(.awa-footer-newsletter,.awa-footer-newsletter>.container,.awa-newsletter-wrapper){'
            . 'box-sizing:border-box!important;width:100%!important;max-width:min(100%,calc(100vw - 32px))!important;'
            . 'margin-inline:auto!important;overflow:visible!important}'
            . $scope . ' :is(.awa-newsletter-wrapper,.form.subscribe){gap:8px!important}'
            . $scope . ' .awa-newsletter-info{'
            . 'display:grid!important;grid-template-columns:40px minmax(0,1fr)!important;align-items:center!important;'
            . 'gap:10px!important;height:auto!important;min-height:0!important}'
            . $scope . ' .awa-newsletter-icon{width:40px!important;height:40px!important;margin:0!important}'
            . $scope . ' .awa-newsletter-title{margin-bottom:4px!important;font-size:15px!important;line-height:1.25!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer #newsletter-validate-detail.form.subscribe{'
            . 'display:block!important;width:100%!important;height:auto!important;min-height:0!important;overflow:hidden!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer #newsletter-validate-detail .field.newsletter{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 118px!important;align-items:stretch!important;'
            . 'gap:8px!important;height:44px!important;min-height:44px!important;width:100%!important;min-width:0!important;overflow:hidden!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer #newsletter-validate-detail :is(.control,.actions){'
            . 'height:44px!important;min-height:44px!important;min-width:0!important;width:auto!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer #newsletter-validate-detail input[type=email]{'
            . 'height:44px!important;min-height:44px!important;width:100%!important;min-width:0!important;font-size:14px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer #newsletter-validate-detail button.action.subscribe{'
            . 'height:44px!important;min-height:44px!important;width:118px!important;max-width:118px!important;'
            . 'padding-inline:8px!important;white-space:normal!important;font-size:12px!important;line-height:1.15!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer .awa-footer-trust-grid{'
            . 'display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer .awa-footer-trust-item{'
            . 'display:grid!important;grid-template-columns:1fr!important;justify-items:start!important;align-content:start!important;'
            . 'min-height:96px!important;height:auto!important;padding:10px!important;gap:6px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer .awa-footer-trust-icon{'
            . 'width:32px!important;height:32px!important;min-width:32px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer .awa-footer-trust-copy{gap:2px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer .awa-footer-trust-copy strong{'
            . 'font-size:12px!important;line-height:1.25!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper footer.page-footer .page_footer .awa-footer-trust-copy span{'
            . 'font-size:11px!important;line-height:1.3!important}'
            . $scope . ' #footer.footer-container>.container{padding:10px 16px!important}'
            . $scope . ' #footer.footer-container .rowFlexMargin{gap:8px!important;padding:8px 0!important}'
            . $scope . ' .vela-content.velaFooterMenu{height:auto!important;padding:4px 8px!important}'
            . $scope . ' .velaFooterTitle{height:auto!important;min-height:52px!important;padding:4px 0!important}'
            . $scope . ' .awa-footer-categories-expand{'
            . 'height:auto!important;min-height:0!important;max-height:none!important;margin-top:8px!important;'
            . 'padding:8px 0 4px!important}'
            . $scope . ' .awa-footer-categories-expand__panel[hidden]{'
            . 'display:none!important;height:0!important;min-height:0!important;max-height:0!important;'
            . 'padding:0!important;overflow:hidden!important}'
            . $scope . ' .awa-footer-categories-expand:has(.awa-footer-categories-expand__panel[hidden]){'
            . 'max-height:64px!important;overflow:hidden!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){'
            . 'gap:8px!important;margin:0!important;padding:0!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals)>li{'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:auto!important;min-width:44px!important;min-height:32px!important;margin:0!important;'
            . 'padding:0!important;list-style:none!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__copyright{'
            . 'width:100%!important;max-width:none!important;margin:8px 0 0!important;padding:8px 0 0!important;'
            . 'text-align:left!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__copyright :is(p,.awa-footer-copyright__legal,'
            . '.awa-footer-copyright__disclaimer){'
            . 'width:100%!important;max-width:none!important;margin:0!important;padding:0!important;'
            . 'font-size:10.5px!important;line-height:1.35!important}'
            . $scope . ' .footer-bottom .awa-footer-copyright__disclaimer{margin-top:2px!important}'
            . $scope . ' .awa-footer-devby{'
            . 'height:auto!important;min-height:0!important;max-height:64px!important;padding:8px 0!important;'
            . 'overflow:hidden!important}'
            . '}'
            . '@media (max-width:575px){'
            . $scope . ' .awa-footer-pay-logos{grid-template-columns:repeat(4,minmax(40px,1fr))!important}'
            . '}'
            . '@media (min-width:768px){'
            . $hard . '{padding-block:8px!important}'
            . $root . ' .awa-footer-newsletter{padding-block:14px!important;margin-bottom:12px!important}'
            . $root . ' .awa-footer-trust-bar{padding-block:16px!important;margin-bottom:16px!important}'
            . $root . ' .awa-footer-trust-grid{padding-block:10px!important;gap:12px!important}'
            . $root . ' .awa-footer-trust-item{min-height:48px!important;padding-block:4px!important}'
            . $hard . ' .row.rowFlexMargin{gap:12px!important;padding-block:4px!important}'
            . $hard . ' .vela-content.velaFooterMenu{padding:6px 8px!important}'
            . $hard . ' .velaFooterTitle{height:auto!important;min-height:44px!important;margin-bottom:4px!important;padding-bottom:4px!important}'
            . $hard . ' .awa-footer-section__toggle{align-items:center!important;display:flex!important;min-height:44px!important;height:auto!important;margin-bottom:4px!important;padding:0!important}'
            . $hard . ' .awa-footer-atendimento .velaContent{gap:4px!important}'
            . $hard . ' .awa-footer-atendimento :is(.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a){'
            . 'display:inline-flex!important;align-items:center!important;height:auto!important;min-height:44px!important;padding:6px 0!important}'
            . $hard . ' .velaFooterLinks a{align-items:center!important;display:flex!important;min-height:44px!important;padding-block:6px!important}'
            . $hard . ' .awa-footer-atendimento :is(.awa-footer-atendimento__phone,.awa-footer-atendimento__email){'
            . 'height:auto!important;min-height:0!important;margin-bottom:4px!important}'
            . $hard . ' .awa-footer-atendimento__store{padding:8px!important;margin-bottom:6px!important;gap:2px!important}'
            . $hard . ' .awa-footer-atendimento__store-badge{margin:2px 0 4px!important;padding:3px 6px!important}'
            . $hard . ' .awa-footer-atendimento__actions{gap:4px!important}'
            . $hard . ' .awa-footer-pro__social{gap:8px!important;height:auto!important;min-height:44px!important}'
            . $hard . ' .awa-footer-pro__social-link{align-items:center!important;display:inline-flex!important;height:44px!important;justify-content:center!important;min-height:44px!important;min-width:44px!important;width:44px!important;padding:6px!important}'
            . $shell . ' .footer-bottom{padding:16px!important;margin-top:8px!important}'
            . $shell . ' .footer-bottom .footer-bottom-inner{gap:8px!important}'
            . '}';
    }

    /**
     * Touch targets e trust items planos (extraído de visualCrawlSystemicRules).
     */
    public static function footerTouchAndTrustRules(): string
    {
        return 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) :is(.awa-footer-section__toggle,.velaFooterLinks a,.awa-footer-atendimento__actions a,.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link,.awa-seal){'
            . 'align-items:center!important;box-sizing:border-box!important;display:inline-flex!important;'
            . 'justify-content:flex-start!important;line-height:1.45!important;min-height:44px!important;'
            . 'min-width:44px!important;text-align:start!important;text-decoration:none!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) :is(.awa-footer-section__toggle,.velaFooterLinks a,.awa-footer-atendimento__actions a,.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link){'
            . 'padding-block:7px!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-trust-item{'
            . 'background-color:transparent!important;color:var(--awa-text-muted,oklch(45% .02 20))!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-atendimento__store-badge{'
            . 'background-color:rgba(183,51,55,.08)!important;color:var(--awa-primary,oklch(48% .14 20))!important;text-shadow:none!important}';
    }

    /**
     * Regras terminais do footer para páginas sem cascade-lock do header (PLP/PDP/checkout).
     */
    public static function footerTerminalRules(): string
    {
        return self::footerStructuralContainmentRules()
            . self::footerTrustSurfaceRules()
            . self::footerTouchAndTrustRules()
            . self::footerLightLayoutRules()
            . self::footerBottomModernRules()
            . self::footerInteractionPolishRules()
            . self::footerAtendimentoPolishRules()
            . self::footerBusinessBandsRules()
            . self::footerSealImgPolishRules()
            . self::footerAlignShellRules()
            . self::footerNoJsCompactRules()
            . self::footerVtexNeutralTerminalRules();
    }

    /**
     * Lock final do footer em superficie neutra: vence locks antigos vermelhos sem alterar markup.
     */
    public static function footerVtexNeutralTerminalRules(): string
    {
        $scope = 'html body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper :is(.page_footer,.page-footer)';
        $hard = $scope . ' #footer.footer-container';

        return '/*awa-footer-vtex-neutral-terminal-v1*/'
            . $scope . '{'
            . 'background:var(--awa-bg,Canvas)!important;background-color:var(--awa-bg,Canvas)!important;'
            . 'border-top:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'color:var(--awa-text,CanvasText)!important;font-size:13px!important;line-height:1.4!important;'
            . 'text-shadow:none!important}'
            . $scope . ' :is(#footer.footer-container,.footer-container,.container,.vela-content,.velaFooterMenu,.velaBlock){'
            . 'background:transparent!important;background-color:transparent!important;color:var(--awa-text,CanvasText)!important;'
            . 'box-shadow:none!important;text-shadow:none!important}'
            . $hard . '{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;'
            . 'padding:16px!important;width:min(100%,1280px)!important}'
            . $hard . ' :is(a,p,li,span,strong,.velaFooterTitle,h4,.awa-footer-section__toggle){'
            . 'color:var(--awa-text,CanvasText)!important;text-shadow:none!important}'
            . $hard . ' :is(.velaFooterLinks a,.awa-footer-atendimento__actions a,'
            . '.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link){'
            . 'align-items:center!important;color:var(--awa-text-secondary,var(--awa-text-muted,CanvasText))!important;'
            . 'display:inline-flex!important;font-size:13px!important;line-height:1.35!important;min-height:36px!important;'
            . 'padding-block:6px!important;text-decoration:none!important}'
            . $hard . ' :is(.velaFooterLinks a,.awa-footer-atendimento__actions a,'
            . '.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link):hover{'
            . 'color:var(--awa-primary,var(--awa-red,currentColor))!important}'
            . $hard . ' .row.rowFlexMargin{'
            . 'align-items:start!important;display:grid!important;gap:16px!important;'
            . 'grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(280px,.86fr)!important;'
            . 'padding-block:8px!important}'
            . $hard . ' :is(.vela-content.velaFooterMenu,.awa-footer-atendimento){'
            . 'border:0!important;box-shadow:none!important;min-width:0!important;padding:8px!important}'
            . $hard . ' .awa-footer-atendimento__store{'
            . 'background:var(--awa-bg-soft,color-mix(in srgb,CanvasText 3%,Canvas))!important;'
            . 'border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'border-radius:8px!important;color:var(--awa-text,CanvasText)!important;padding:10px!important}'
            . $scope . ' .awa-footer-newsletter{'
            . 'background:var(--awa-bg-soft,color-mix(in srgb,CanvasText 3%,Canvas))!important;'
            . 'border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'border-radius:8px!important;margin:0 0 12px!important;padding:14px 16px!important}'
            . $scope . ' :is(.footer-bottom,section.awa-footer-categories-expand,.awa-footer-devby){'
            . 'background:var(--awa-bg-soft,color-mix(in srgb,CanvasText 3%,Canvas))!important;'
            . 'background-color:var(--awa-bg-soft,color-mix(in srgb,CanvasText 3%,Canvas))!important;'
            . 'border-top:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'color:var(--awa-text,CanvasText)!important;text-shadow:none!important}'
            . $scope . ' .footer-bottom{'
            . 'box-sizing:border-box!important;margin:0!important;margin-left:0!important;margin-right:0!important;'
            . 'max-width:100%!important;padding:0!important;padding-left:0!important;padding-right:0!important;'
            . 'width:100%!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner{'
            . 'box-sizing:border-box!important;display:grid!important;gap:10px!important;'
            . 'grid-template-columns:minmax(0,1fr)!important;justify-items:stretch!important;'
            . 'margin-inline:0!important;max-width:none!important;'
            . 'padding:12px 16px!important;width:100%!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'box-sizing:border-box!important;display:grid!important;'
            . 'grid-template-columns:minmax(88px,118px) minmax(220px,1fr) minmax(180px,240px)!important;'
            . 'align-items:center!important;gap:10px 18px!important;justify-self:center!important;'
            . 'margin:0 auto!important;max-width:760px!important;min-width:0!important;width:100%!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row>[class*="col-"]{'
            . 'box-sizing:border-box!important;float:none!important;margin:0!important;max-width:none!important;'
            . 'min-width:0!important;padding-inline:0!important;width:auto!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-pay-sec,.awa-footer-sec){'
            . 'height:auto!important;margin:0!important;min-height:0!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){'
            . 'gap:6px 8px!important;margin:0!important;padding:0!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__copyright{'
            . 'background:var(--awa-bg,Canvas)!important;border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'border-radius:8px!important;color:var(--awa-text-secondary,var(--awa-text-muted,CanvasText))!important;'
            . 'box-sizing:border-box!important;justify-self:center!important;margin:0!important;'
            . 'max-width:1040px!important;padding:10px 12px!important;width:100%!important}'
            . '@media (max-width:991px){'
            . $hard . ' .row.rowFlexMargin{grid-template-columns:minmax(0,1fr)!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner{grid-template-columns:minmax(0,1fr)!important;text-align:left!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'grid-template-columns:minmax(0,1fr)!important;justify-items:center!important;'
            . 'max-width:100%!important;text-align:center!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){'
            . 'justify-content:center!important}'
            . '}'
            . '@media (max-width:767px){'
            . $scope . ' :is(.velaFooterLinks a,.awa-footer-section__toggle,.awa-footer-atendimento__actions a,'
            . '.awa-footer-atendimento__phone a,.awa-footer-atendimento__email a,.awa-footer-devby__link){'
            . 'min-height:44px!important}'
            . $hard . '{padding:12px 16px!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner{padding:12px 16px!important}'
            . '}';
    }

    /**
     * Selos img externas, contraste de labels e chevron de categorias — final-wins pós refine/bugfix.
     */
    public static function footerSealImgPolishRules(): string
    {
        $scope = 'html body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer)';

        return $scope . ' .footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){'
            . 'opacity:1!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-muted-label,.awa-footer-pay-sec__label){'
            . 'color:var(--awa-text,#333333)!important;font-size:12px!important;font-weight:600!important;'
            . 'letter-spacing:.02em!important;line-height:1.35!important;margin-block:0 8px!important}'
            . $scope . ' .footer-bottom .awa-footer-sec-seals{'
            . 'display:flex!important;flex-wrap:wrap!important;gap:8px 10px!important;'
            . 'align-items:center!important;list-style:none!important;margin:0!important;padding:0!important}'
            . $scope . ' .footer-bottom .awa-footer-sec-seals .awa-seal{'
            . 'background:var(--awa-bg,#ffffff)!important;'
            . 'border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:4px!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'min-height:44px!important;min-width:44px!important;padding:6px 10px!important;'
            . 'text-decoration:none!important}'
            . $scope . ' .footer-bottom .awa-footer-sec-seals .awa-seal:hover{'
            . 'border-color:color-mix(in srgb,var(--awa-primary,#b73337) 45%,var(--awa-border,#e5e5e5))!important}'
            . $scope . ' .footer-bottom .awa-footer-sec-seals .awa-seal__img{'
            . 'display:block!important;height:auto!important;max-width:100%!important;object-fit:contain!important}'
            . $scope . ' .footer-bottom .awa-footer-copyright__legal{'
            . 'color:var(--awa-text,#333333)!important;text-wrap:pretty!important}'
            . $scope . ' .footer-bottom .awa-footer-copyright__disclaimer{'
            . 'color:color-mix(in srgb,var(--awa-text,#333333) 78%,transparent)!important;'
            . 'text-wrap:pretty!important}'
            . $scope . ' .awa-footer-categories-expand__toggle .awa-footer-categories-expand__icon{'
            . 'display:inline-flex!important;transition:transform 200ms cubic-bezier(.25,1,.5,1)!important}'
            . $scope . ' .awa-footer-categories-expand__toggle.is-expanded .awa-footer-categories-expand__icon{'
            . 'transform:rotate(180deg)!important}'
            . '@media (prefers-reduced-motion:reduce){' . $scope
            . ' .awa-footer-categories-expand__toggle .awa-footer-categories-expand__icon{'
            . 'transition:none!important}}'
            . '@media (max-width:767px){' . $scope . ' .footer-bottom .awa-footer-pay-logos{'
            . 'display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;'
            . 'gap:8px!important;justify-items:center!important;width:100%!important}}';
    }

    /**
     * Eixo 1280px único: neutraliza .container Bootstrap aninhado e alinha categorias/bottom ao header.
     */
    public static function footerAlignShellRules(): string
    {
        $scope = 'html body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer)';
        $home = 'html body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper :is(.page_footer,.page-footer)';
        $shell = 'max-width:var(--awa-home-terminal-shell,min(100%,1280px))!important;'
            . 'width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:0!important;padding-left:0!important;padding-right:0!important;'
            . 'box-sizing:border-box!important';
        $innerReset = 'max-width:none!important;width:100%!important;margin-inline:0!important;'
            . 'padding-inline:0!important;padding-left:0!important;padding-right:0!important';

        $innerContainer = '#footer.footer-container > .container,'
            . '#footer.footer-container .awa-footer-newsletter > .container';

        // Physical properties (padding-left/right) alongside logical padding-inline to guarantee
        // override against awa-layout-bundle which uses physical `padding: 0 16px !important`.
        $shellPadReset = 'padding-inline:0!important;padding-left:0!important;padding-right:0!important';

        // Trust-bar inner container must be aligned to the 1280px axis.
        $trustBar = 'max-width:var(--awa-site-shell,min(100%,1280px))!important;'
            . 'width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:var(--awa-page-pad,24px)!important;box-sizing:border-box!important';

        return $scope . ' #footer.footer-container{' . $shellPadReset . '}'
            . $scope . ' ' . $innerContainer . '{' . $innerReset . '}'
            . $home . ' ' . $innerContainer . '{' . $innerReset . '}'
            . $scope . ' section.awa-footer-categories-expand > .container{' . $shell . '}'
            . $scope . ' .footer-bottom > .container,'
            . $scope . ' .footer-bottom .footer-bottom-inner{' . $innerReset . '}'
            . $scope . ' .awa-footer-trust-bar > .container{' . $trustBar . '}'
            . $scope . ' #footer .row.rowFlexMargin{gap:16px!important}'
            . '@media (min-width:768px){' . $scope . ' .velaFooterLinks li{margin:0!important}'
            . $scope . ' .velaFooterLinks a{align-items:center!important;display:inline-flex!important;'
            . 'min-height:44px!important;padding-block:6px!important}'
            . $scope . ' .awa-footer-atendimento .velaContent{gap:6px!important;display:flex!important;'
            . 'flex-direction:column!important}'
            . $scope . ' section.awa-footer-categories-expand{margin-top:12px!important;padding-top:12px!important}'
            . $scope . ' .velaFooterTitle{width:100%!important}}';
    }

    /**
     * Footer light/flat layout terminal: largura catalogo (1440), grid de colunas
     * responsivo (1/2/3) e titulos em sentence-case. Vence bundles tardios
     * (awa-visual-bugfix 800px, awa-ui-simplify-terminal col 50%).
     */
    public static function footerLightLayoutRules(): string
    {
        return 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container{'
            . 'max-width:var(--awa-layout-max,1280px)!important;width:100%!important;margin-inline:auto!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container .container>.row.rowFlexMargin{'
            . 'display:grid!important;grid-template-columns:1fr!important;gap:var(--awa-s-3,16px)!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container .container>.row.rowFlexMargin::before,'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container .container>.row.rowFlexMargin::after{'
            . 'display:none!important;content:none!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container .container>.row.rowFlexMargin>[class*="col-"]{'
            . 'flex:initial!important;width:auto!important;max-width:none!important;min-width:0!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) :is(.velaFooterTitle,.velaFooterMenu .velaFooterTitle){'
            . 'text-transform:none!important;letter-spacing:-.01em!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-trust-item{'
            . 'background:transparent!important;border:0!important;box-shadow:none!important;border-radius:0!important;'
            . 'padding-block:8px!important;min-height:0!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-trust-grid{'
            . 'padding-block:16px!important;gap:16px!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-newsletter{'
            . 'padding-block:24px!important;gap:24px!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) :is(h2,h3).awa-newsletter-title{'
            . 'font-size:clamp(17px,1.4vw,20px)!important;line-height:1.25!important;font-weight:700!important;'
            . 'text-transform:none!important;letter-spacing:-.01em!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-newsletter-desc{'
            . 'font-size:15px!important;line-height:1.45!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) ul.velaFooterLinks a{'
            . 'text-align:start!important;justify-content:flex-start!important;line-height:1.45!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-newsletter-icon{'
            . 'background-color:rgba(183,51,55,.1)!important}'
            . '@media (min-width:768px){'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container .container>.row.rowFlexMargin{'
            . 'grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:var(--awa-s-4,24px)!important}}'
            . '@media (min-width:992px){'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-container .container>.row.rowFlexMargin{'
            . 'grid-template-columns:repeat(3,minmax(0,1fr))!important}'
            . 'html body#html-body .page-wrapper :is(.page_footer,.page-footer) .awa-footer-newsletter{'
            . 'padding-block:24px 16px!important}}';
    }

    /**
     * Footer bottom (logo/pagamentos/selos), trust grid responsivo, newsletter desktop,
     * categorias expand e focus-visible. Vence awa-super-global (flex no bottom row).
     */
    public static function footerBottomModernRules(): string
    {
        $scope = 'html body#html-body .page-wrapper :is(.page_footer,.page-footer)';

        return $scope . ' .awa-footer-trust-grid{'
            . 'display:grid!important;grid-template-columns:1fr!important;'
            . 'align-items:center!important;gap:16px!important}'
            . '@media (min-width:576px){' . $scope . ' .awa-footer-trust-grid{'
            . 'grid-template-columns:repeat(2,minmax(0,1fr))!important}}'
            . '@media (min-width:992px){' . $scope . ' .awa-footer-trust-grid{'
            . 'grid-template-columns:repeat(4,minmax(0,1fr))!important}}'
            . '@media (min-width:768px){' . $scope . ' .awa-newsletter-wrapper{'
            . 'display:flex!important;align-items:center!important;'
            . 'gap:16px!important;flex-wrap:nowrap!important}'
            . $scope . ' .awa-newsletter-form-container{'
            . 'flex:1!important;min-width:0!important;max-width:480px!important}}'
            . '@media (min-width:768px){' . $scope . ' .footer-bottom .awa-footer-bottom__row{'
            . 'display:grid!important;'
            . 'grid-template-columns:minmax(120px,1fr) minmax(0,2fr) minmax(0,1.4fr)!important;'
            . 'align-items:start!important;gap:16px!important;flex-wrap:nowrap!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__row::before,'
            . $scope . ' .footer-bottom .awa-footer-bottom__row::after{'
            . 'display:none!important;content:none!important}'
            . $scope . ' .footer-bottom .awa-footer-bottom__row>[class*="col-"]{'
            . 'flex:initial!important;width:auto!important;max-width:none!important;'
            . 'float:none!important;padding-inline:0!important}}'
            . '@media (max-width:767px){' . $scope . ' .footer-bottom .awa-footer-bottom__row{'
            . 'display:grid!important;grid-template-columns:1fr!important;'
            . 'justify-items:center!important;text-align:center!important;gap:20px!important}'
            . $scope . ' .footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){'
            . 'justify-content:center!important}}'
            . $scope . ' :is(.awa-footer-pay-sec__label,.awa-footer-muted-label){'
            . 'text-transform:none!important;letter-spacing:0!important;'
            . 'font-weight:600!important;font-size:12px!important}'
            . $scope . ' :is(.awa-footer-pay-logos,.awa-footer-sec-seals){'
            . 'display:flex!important;flex-wrap:wrap!important;'
            . 'gap:8px!important;align-items:center!important}'
            . $scope . ' .awa-footer-bottom__logo-img{'
            . 'max-height:44px!important;width:auto!important;opacity:.94!important}'
            . $scope . ' .awa-footer-bottom__copyright{'
            . 'margin-top:8px!important;padding-top:8px!important;'
            . 'border-top:1px solid var(--awa-border,oklch(90% .008 20))!important}'
            . '@media (min-width:992px){' . $scope . ' .footer-bottom .footer-bottom-inner{'
            . 'gap:12px!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'flex:1 1 560px!important;min-width:0!important}'
            . $scope . ' .footer-bottom .footer-bottom-inner>.awa-footer-bottom__copyright{'
            . 'flex:1 1 420px!important;min-width:320px!important;max-width:560px!important;'
            . 'margin-top:0!important;padding-top:8px!important}}'
            . $scope . ' .awa-footer-bottom__copyright p{'
            . 'font-size:12px!important;line-height:1.45!important}'
            . $scope . ' section.awa-footer-categories-expand{'
            . 'border-top:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'background:var(--awa-primary,oklch(48% .14 20))!important}'
            . '@media (min-width:768px){' . $scope . ' .awa-footer-categories-expand__toggle{'
            . 'display:none!important}'
            . $scope . ' .awa-footer-categories-expand__panel,'
            . $scope . ' .awa-footer-categories-expand__panel[hidden]{'
            . 'display:block!important;max-height:none!important;visibility:visible!important}'
            . $scope . ' ul.awa-footer-categories-list{'
            . 'display:flex!important;flex-wrap:wrap!important;gap:8px!important}}'
            . '@media (max-width:767px){' . $scope . ' .awa-footer-categories-expand__toggle{'
            . 'min-height:44px!important;width:100%!important;'
            . 'justify-content:space-between!important;font-weight:600!important}}'
            . '@media (max-width:575px){' . $scope . ' .awa-footer-trust-copy span{'
            . 'display:block!important;font-size:11px!important;line-height:1.35!important}}'
            . $scope . ' :is(.velaFooterLinks a,.awa-footer-section__toggle,.awa-seal,'
            . '.awa-footer-devby__link,.awa-footer-pro__social-link,'
            . '.awa-footer-bottom__logo-col a,.awa-footer-categories-expand__toggle):focus-visible{'
            . 'outline:2px solid var(--awa-primary,oklch(48% .14 20))!important;'
            . 'outline-offset:2px!important}'
            . $scope . ' #newsletter-validate-detail button.action.subscribe:focus-visible{'
            . 'outline:2px solid var(--awa-primary,oklch(48% .14 20))!important;'
            . 'outline-offset:2px!important}';
    }

    /**
     * Newsletter form, copyright/devby, pay pills e categorias — vence bundles tardios
     * (postaudit 0.75rem copyright, devby opacity .5, super-global pay pills).
     */
    public static function footerInteractionPolishRules(): string
    {
        $scope = 'html body#html-body .page-wrapper :is(.page_footer,.page-footer)';

        return $scope . ' .awa-newsletter-wrapper{gap:16px!important}'
            . $scope . ' .awa-newsletter-info{'
            . 'display:flex!important;align-items:center!important;gap:12px!important;'
            . 'flex:1!important;min-width:0!important}'
            . $scope . ' #newsletter-validate-detail{'
            . 'display:flex!important;gap:8px!important;align-items:stretch!important;width:100%!important}'
            . $scope . ' #newsletter-validate-detail :is(input[type=email],button.action.subscribe){'
            . 'min-height:44px!important;height:44px!important;box-sizing:border-box!important;'
            . 'border-radius:8px!important}'
            . $scope . ' #newsletter-validate-detail input[type=email]{'
            . 'flex:1!important;min-width:0!important;font-size:16px!important}'
            . $scope . ' #newsletter-validate-detail button.action.subscribe{'
            . 'flex:0 0 auto!important;min-width:44px!important;padding-inline:16px!important}'
            . '@media (min-width:768px){' . $scope . ' .awa-newsletter-wrapper{gap:16px!important}}'
            . '@media (max-width:991px){' . $scope . ' .page_footer .awa-newsletter-wrapper,'
            . $scope . ' .page-footer .awa-newsletter-wrapper{'
            . 'flex-direction:column!important;align-items:flex-start!important;'
            . 'text-align:start!important;width:100%!important}'
            . $scope . ' .awa-newsletter-form-container{width:100%!important;max-width:none!important}'
            . $scope . ' #newsletter-validate-detail{flex-direction:column!important;width:100%!important}}'
            . $scope . ' .footer-bottom .awa-footer-bottom__copyright{'
            . 'text-align:start!important}'
            . $scope . ' .footer-bottom :is(p.awa-footer-copyright__legal,p.awa-footer-copyright__disclaimer){'
            . 'font-size:12px!important;line-height:1.5!important;'
            . 'color:var(--awa-text-muted,oklch(45% .02 20))!important;'
            . 'max-width:72ch!important;margin-inline:0!important}'
            . $scope . ' .footer-bottom p.awa-footer-copyright__disclaimer{'
            . 'font-size:11px!important;opacity:.92!important}'
            . '@media (max-width:767px){' . $scope . ' .footer-bottom .awa-footer-bottom__copyright{'
            . 'text-align:center!important}'
            . $scope . ' .footer-bottom :is(p.awa-footer-copyright__legal,p.awa-footer-copyright__disclaimer){'
            . 'margin-inline:auto!important}}'
            . $scope . ' .awa-footer-devby{'
            . 'border-top:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'padding-block:12px!important;opacity:1!important;text-align:center!important}'
            . $scope . ' .awa-footer-devby__inner{'
            . 'display:flex!important;align-items:center!important;justify-content:center!important;'
            . 'gap:8px!important;min-height:44px!important}'
            . $scope . ' .awa-footer-devby__label{'
            . 'font-size:11px!important;letter-spacing:0!important;text-transform:none!important;'
            . 'color:var(--awa-text-muted,oklch(45% .02 20))!important}'
            . $scope . ' .awa-footer-devby__logo{max-height:30px!important;width:auto!important}'
            . $scope . ' .awa-pay-logo{'
            . 'background:transparent!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'border-radius:6px!important;padding:4px 6px!important;'
            . 'min-height:28px!important;display:inline-flex!important;align-items:center!important}'
            . $scope . ' ul.awa-footer-categories-list a{'
            . 'background:transparent!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'color:var(--awa-text-muted,oklch(45% .02 20))!important;'
            . 'border-radius:6px!important;padding:6px 10px!important;'
            . 'min-height:36px!important;display:inline-flex!important;align-items:center!important;'
            . 'font-size:13px!important;line-height:1.35!important}'
            . $scope . ' ul.awa-footer-categories-list a:hover,'
            . $scope . ' ul.awa-footer-categories-list a:focus-visible{'
            . 'background:rgba(183,51,55,.06)!important;'
            . 'border-color:rgba(183,51,55,.35)!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important}';
    }

    /**
     * Coluna Atendimento — hierarquia tipográfica, card da loja e touch targets.
     */
    public static function footerAtendimentoPolishRules(): string
    {
        $scope = 'html body#html-body .page-wrapper :is(.page_footer,.page-footer)';

        return $scope . ' .awa-footer-atendimento{'
            . 'display:flex!important;flex-direction:column!important;gap:8px!important}'
            . $scope . ' :is(.awa-footer-atendimento__label,.awa-footer-atendimento__label--social){'
            . 'text-transform:none!important;letter-spacing:0!important;'
            . 'font-size:13px!important;font-weight:600!important;'
            . 'color:var(--awa-text-muted,oklch(45% .02 20))!important;'
            . 'margin:0 0 4px!important}'
            . $scope . ' .awa-footer-atendimento__phone :is(a,span){'
            . 'font-size:15px!important;font-weight:600!important;'
            . 'color:var(--awa-secondary,oklch(35% .03 20))!important}'
            . $scope . ' .awa-footer-atendimento__email a{'
            . 'font-size:14px!important;color:var(--awa-text-muted,oklch(45% .02 20))!important;'
            . 'text-decoration:underline!important;text-underline-offset:2px!important}'
            . $scope . ' .awa-footer-atendimento__email a:hover,'
            . $scope . ' .awa-footer-atendimento__email a:focus-visible{'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important}'
            . $scope . ' .awa-footer-atendimento__store{'
            . 'display:flex!important;flex-direction:column!important;gap:4px!important;'
            . 'padding:12px!important;border-radius:8px!important;'
            . 'background:rgba(183,51,55,.05)!important;background-image:none!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important}'
            . $scope . ' :is(.awa-footer-atendimento__store-name,.awa-footer-atendimento__store-address){'
            . 'margin:0!important;line-height:1.45!important;'
            . 'color:var(--awa-text-muted,oklch(45% .02 20))!important}'
            . $scope . ' ul.awa-footer-atendimento__actions a{'
            . 'display:inline-flex!important;align-items:center!important;gap:8px!important}'
            . $scope . ' .awa-footer-atendimento__icon{flex-shrink:0!important}'
            // Visual audit Jul/2026 — ícones sociais visíveis (vence super-global transparent)
            . $scope . ' .awa-footer-pro__social-link{'
            . 'background:#374151!important;'
            . 'background:color-mix(in srgb,var(--awa-neutral-700,#374151) 88%,#fff)!important;'
            . 'border:1.5px solid color-mix(in srgb,#fff 22%,transparent)!important;'
            . 'color:#fff!important;border-radius:50%!important}'
            . $scope . ' .awa-footer-pro__social-link :is(svg,svg path){fill:currentColor!important}'
            . $scope . ' .awa-footer-pro__social-link:hover,'
            . $scope . ' .awa-footer-pro__social-link:focus-visible{'
            . 'background:var(--awa-primary,#b73337)!important;'
            . 'border-color:var(--awa-primary,#b73337)!important;color:#fff!important}'
            . '@media (min-width:992px){' . $scope . ' .footer-container .vela-content.velaFooterMenu{'
            . 'padding:16px!important}'
            . $scope . ' .footer-container .row.rowFlexMargin{'
            . 'padding-block:8px!important}'
            . $scope . ' .awa-footer-atendimento .velaContent.active{'
            . 'gap:8px!important}'
            . $scope . ' .awa-footer-atendimento :is(.awa-footer-atendimento__phone a,'
            . '.awa-footer-atendimento__email a,.awa-footer-atendimento__actions a,.awa-footer-pro__social-link){'
            . 'min-height:40px!important}'
            . $scope . ' .awa-footer-atendimento__store{'
            . 'padding:8px!important;gap:4px!important}'
            . $scope . ' .awa-footer-atendimento__store-badge{'
            . 'margin:4px 0 8px!important}}';
    }

    /**
     * Faixa comercial B2B e tags do footer — grid shell, pills e eyebrow sentence-case.
     */
    public static function footerBusinessBandsRules(): string
    {
        $scope = 'html body#html-body .page-wrapper :is(.page_footer,.page-footer)';

        return $scope . ' section.awa-footer-business-contact{'
            . 'padding-block:20px!important;'
            . 'border-top:1px solid var(--awa-border,oklch(90% .008 20))!important}'
            . $scope . ' .awa-footer-business-contact__eyebrow{'
            . 'text-transform:none!important;letter-spacing:0!important;'
            . 'font-size:12px!important;font-weight:600!important}'
            . $scope . ' .awa-footer-business-contact__shell{'
            . 'display:flex!important;flex-direction:column!important;gap:16px!important;'
            . 'padding:16px!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'border-radius:8px!important;background:oklch(99% .002 20)!important;'
            . 'box-shadow:none!important}'
            . '@media (min-width:768px){' . $scope . ' .awa-footer-business-contact__shell{'
            . 'display:grid!important;grid-template-columns:minmax(0,1.1fr) minmax(0,1.6fr)!important;'
            . 'align-items:start!important;gap:16px!important}}'
            . $scope . ' .awa-footer-business-contact__actions{'
            . 'display:grid!important;gap:8px!important}'
            . '@media (min-width:576px){' . $scope . ' .awa-footer-business-contact__actions{'
            . 'grid-template-columns:repeat(auto-fit,minmax(min(100%,11rem),1fr))!important}}'
            . $scope . ' .awa-footer-business-contact__title{'
            . 'text-transform:none!important;letter-spacing:0!important;'
            . 'font-size:16px!important;line-height:1.35!important;margin:0!important}'
            . $scope . ' .awa-footer-business-contact__copy{'
            . 'font-size:14px!important;line-height:1.45!important;margin:0!important}'
            . $scope . ' .awa-footer-business-contact__action{'
            . 'display:inline-flex!important;align-items:center!important;gap:10px!important;'
            . 'min-height:44px!important;padding:10px 14px!important;'
            . 'border-radius:8px!important;text-decoration:none!important}'
            . $scope . ' .awa-footer-business-contact__action--primary{'
            . 'background:rgba(183,51,55,.08)!important;'
            . 'border:1px solid rgba(183,51,55,.28)!important}'
            . $scope . ' .awa-footer-business-contact__action-copy strong{'
            . 'display:block!important;font-size:14px!important;line-height:1.3!important}'
            . $scope . ' .awa-footer-business-contact__action-copy small{'
            . 'display:block!important;font-size:12px!important;line-height:1.35!important;'
            . 'color:var(--awa-text-muted,oklch(45% .02 20))!important}'
            . $scope . ' section.awa-footer-tags{'
            . 'padding-block:16px!important;'
            . 'border-top:1px solid var(--awa-border,oklch(90% .008 20))!important}'
            . $scope . ' .awa-footer-tags__inner{'
            . 'display:flex!important;flex-wrap:wrap!important;align-items:center!important;'
            . 'gap:12px!important}'
            . $scope . ' .awa-footer-tags__label{'
            . 'font-size:13px!important;font-weight:600!important;margin:0!important}'
            . $scope . ' .awa-footer-tags__cloud{'
            . 'display:flex!important;flex-wrap:wrap!important;gap:8px!important}'
            . $scope . ' .awa-footer-tags__cloud a{'
            . 'display:inline-flex!important;align-items:center!important;min-height:36px!important;'
            . 'padding:6px 10px!important;border-radius:6px!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'font-size:13px!important;line-height:1.35!important}'
            . $scope . ' .awa-footer-tags__cloud a:hover,'
            . $scope . ' .awa-footer-tags__cloud a:focus-visible{'
            . 'background:rgba(183,51,55,.06)!important;'
            . 'border-color:rgba(183,51,55,.35)!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important}';
    }

    public static function rules(): string
    {
        return self::headerA11yRules()
            . self::minicartInteractionRules()
            . self::promoBarRules()
            . self::headerPolishRules()
            . self::headerNavShellRules()
            . self::headerVisualStandardRules()
            . self::verticalMenuTerminalRules()
            . self::impeccableSurfaceRules()
            . self::b2bRegisterSurfaceRules()
            . self::b2bDashboardSurfaceRules()
            . self::headerEssentialTerminalRules()
            . self::tabletHeaderCompactRules()
            . '@media (min-width:992px){'
            . 'html body#html-body#html-body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header{'
            . '--awa-header-main-row-h:68px!important;--awa-header-row-h:var(--awa-header-main-row-h)!important;'
            . '--awa-header-nav-h:48px!important;--awa-nav-bar-h:var(--awa-header-nav-h)!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner.wp-header,'
            . 'html body#html-body .page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row],'
            . 'html body#html-body .page-wrapper .awa-site-header .header.awa-main-header{'
            . 'min-height:var(--awa-header-main-row-h,68px)!important;'
            . 'height:var(--awa-header-main-row-h,68px)!important;'
            . 'max-height:var(--awa-header-main-row-h,68px)!important;padding-block:0!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-main-header__inner.wp-header,'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-main-header__inner[data-awa-header-row]{'
            . 'display:grid!important;'
            . 'grid-template-columns:minmax(132px,176px) minmax(360px,1fr) minmax(300px,auto)!important;'
            . 'grid-template-areas:"brand search actions"!important;'
            . 'align-items:center!important;column-gap:clamp(16px,2vw,28px)!important;'
            . 'overflow:visible!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-header-primary-row{display:contents!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header :is(.awa-header-mobile-toggle,.action.nav-toggle,[data-action="toggle-nav"]){'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'overflow:hidden!important;pointer-events:none!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-header-brand-cell{'
            . 'grid-area:brand!important;grid-column:auto!important;width:auto!important;max-width:176px!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-header-search-col{'
            . 'grid-area:search!important;grid-column:auto!important;min-width:0!important;width:100%!important;max-width:none!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-header-search-col .block-search{'
            . 'width:100%!important;max-width:760px!important;margin-inline:auto!important}'
            . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header .awa-header-right-col{'
            . 'grid-area:actions!important;grid-column:auto!important;width:auto!important;'
            . 'max-width:360px!important;justify-self:end!important;align-self:center!important}'
            . 'html body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . ':is(.awa-header-brand-cell,.col-md-2.awa-header-brand){'
            . 'align-self:center!important;height:auto!important;min-height:0!important;max-height:56px!important}'
            . 'html body#html-body .page-wrapper .header-control.header-nav.awa-nav-bar,'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar,'
            . 'html body#html-body .page-wrapper .awa-nav-bar,'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar > .container,'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-bar__inner,'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-quick-links,'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar .awa-nav-quick-links__list{'
            . 'min-height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important}'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar .our_categories.title-category-dropdown,'
            . 'html body#html-body .page-wrapper .header-control.awa-nav-bar button[data-role=awa-vertical-menu-trigger]{'
            . 'min-height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important}'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar,'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar > .container,'
            . 'html body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index) '
            . '.page-wrapper .awa-site-header .header-control.header-nav.awa-nav-bar .awa-nav-bar__inner{'
            . 'min-height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important}'
            . '}'
            . self::mobileHeaderSearchLayoutRules()
            . self::headerStickyShellRules()
            . self::homeImpeccablePolishRules()
            . self::visualCrawlSystemicRules()
            . self::mobileSearchTerminalRules()
            . self::mobileHeaderCompact112Rules()
            . self::footerLightLayoutRules()
            . self::footerBottomModernRules()
            . self::footerInteractionPolishRules()
            . self::footerAtendimentoPolishRules()
            . self::footerBusinessBandsRules()
            . self::footerSealImgPolishRules()
            . self::footerAlignShellRules()
            . self::homePostAuditTerminalRules()
            . self::headerBolderActionContrastLockRules()
            . self::plpB2bGuestGateTerminalRules()
            . self::plpMobileToolbarTerminalRules();
    }

    /**
     * PLP mobile toolbar + chrome — última camada body-end (cc2a40).
     * Reforça grid estável e remove overlays que cobrem cards.
     */
    public static function plpMobileToolbarTerminalRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.catalog-category-view,.catalogsearch-result-index)';
        $tb = $root . ' .page-wrapper .shop-tab-title .toolbar.toolbar-products';

        return '@layer awa-visual-priority{@media (max-width:767px){'
            . '/* §PLP-MOBILE-TOOLBAR r7 — @layer vence Round19 display:inline-flex */'
            . $root . ' .page-wrapper .shop-tab-title{height:auto!important;min-height:0!important;max-height:none!important}'
            . $tb . '{height:auto!important;min-height:0!important;max-height:none!important;display:flex!important;flex-direction:column!important}'
            . $tb . '>.center{display:grid!important;grid-template-columns:minmax(0,1fr)!important;grid-template-rows:auto auto!important;'
            . 'gap:8px!important;position:static!important;width:100%!important;height:auto!important;min-height:0!important}'
            . $tb . ' .modes{position:static!important;display:flex!important;flex-wrap:wrap!important;align-items:center!important;'
            . 'gap:8px!important;width:100%!important;min-height:44px!important;opacity:1!important;visibility:visible!important;'
            . 'clip-path:none!important;pointer-events:auto!important;overflow:visible!important}'
            . $tb . ' .pages,:is(' . $tb . ' .pages .pages-items,' . $tb . ' .pages .item){display:none!important;visibility:hidden!important}'
            . $tb . ' .toolbar-sorter.sorter{display:flex!important;width:100%!important;min-height:44px!important;grid-row:2!important}'
            . $root . ' .page-wrapper .products-grid li.item-product .actions-secondary{display:none!important}'
            . $root . ' .page-wrapper .awa-dark-mode-toggle{display:none!important;visibility:hidden!important;pointer-events:none!important}'
            . '}}';
    }

    /**
     * PLP guest B2B gate — última camada (body-end lock).
     * Vence styles-l/themes (display:block) e visual-fixes (border:dashed),
     * confirmado Playwright debug cc2a40 (computed ainda block/dashed com CSS terminal sozinho).
     */
    public static function plpB2bGuestGateTerminalRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.catalog-category-view,.catalogsearch-result-index)';
        $gate = $root . ' .page-wrapper :is(.products-grid,.wrapper.grid.products-grid)'
            . ' li.item-product .info-price :is(.b2b-login-to-see-price.price-box,.b2b-login-to-see-price)';

        return '/* §PLP-B2B-GATE — guest card CTA (2026-07-08 r5) */'
            . $gate . '{'
            . 'display:flex!important;flex-direction:column!important;flex-wrap:nowrap!important;'
            . 'justify-content:center!important;align-items:center!important;gap:4px!important;'
            . 'box-sizing:border-box!important;width:100%!important;max-width:100%!important;'
            . 'min-height:64px!important;height:auto!important;margin:0!important;padding:10px 12px!important;'
            . 'text-align:center!important;text-transform:none!important;letter-spacing:normal!important;'
            . 'font-size:12px!important;line-height:1.3!important;'
            . 'background:color-mix(in srgb,var(--awa-primary,#b73337) 6%,#fff)!important;'
            . 'border:1px solid color-mix(in srgb,var(--awa-primary,#b73337) 22%,transparent)!important;'
            . 'border-style:solid!important;border-radius:8px!important;box-shadow:none!important}'
            . $gate . ' .b2b-login-to-see-price__title{'
            . 'margin:0!important;width:100%!important;font-size:12px!important;font-weight:700!important;'
            . 'line-height:1.3!important;text-align:center!important;text-transform:none!important;'
            . 'letter-spacing:normal!important;color:var(--awa-text-primary,#333)!important}'
            . $gate . ' .b2b-login-to-see-price__message{'
            . 'margin:0!important;width:100%!important;font-size:11px!important;font-weight:400!important;'
            . 'line-height:1.35!important;text-align:center!important;text-transform:none!important;'
            . 'letter-spacing:normal!important;color:var(--awa-text-muted,#666)!important;'
            . 'display:-webkit-box!important;-webkit-box-orient:vertical!important;'
            . '-webkit-line-clamp:2!important;line-clamp:2!important;overflow:hidden!important}';
    }

    /**
     * Home pós-auditoria Product Design 2026-06-18.
     * Regras terminais porque o HTML real da home mobile usa cascade-lock inline,
     * não o bundle home-terminal em todos os contextos de cache/user-agent.
     */
    public static function homePostAuditTerminalRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)';
        $wrap = $root . ' .page-wrapper';
        $header = $root . ' .awa-site-header';

        return '/* HEADER POST-AUDIT 2026-06-19: !important necessário para vencer bundles tardios e seletores legados com .page-wrapper. */'
            . '@media (min-width:992px){'
            . $header . '{background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;border-bottom:0!important}'
            . $header . ' #header.header-container{height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'overflow:visible!important;background:transparent!important}'
            . $header . ' #header.header-container .header-content{height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'padding:0!important;align-items:center!important;overflow:visible!important}'
            . $header . ' #awa-b2b-promo-bar{position:relative!important;height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;padding:0 52px 0 16px!important;border:0!important;'
            . 'background:#ffffff!important;background-color:#ffffff!important;'
            . 'color:var(--awa-text-primary,#111827)!important;line-height:1.4!important;'
            . 'box-sizing:border-box!important}'
            . $header . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__inner{position:static!important;width:100%!important;'
            . 'max-width:1280px!important;margin:0 auto!important;padding:0!important;height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;align-items:center!important;justify-content:center!important;box-sizing:border-box!important}'
            . $header . ' #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,'
            . '.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__separator,'
            . '.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__cta strong){'
            . 'color:var(--awa-text-primary,#111827)!important;line-height:1.4!important}'
            . $header . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__cta{'
            . 'font-weight:700!important;color:inherit!important;text-decoration:underline!important;text-underline-offset:2px!important}'
            . $header . ' #awa-b2b-promo-bar .awa-b2b-promo-close{'
            . 'position:absolute!important;top:0!important;right:0!important;inset-block-start:0!important;inset-inline-end:0!important;'
            . 'width:44px!important;min-width:44px!important;max-width:44px!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'border:0!important;border-radius:0!important;background:transparent!important;'
            . 'color:inherit!important;font-size:16px!important;'
            . 'font-weight:600!important;line-height:1!important;transform:none!important}'
            . $header . ' .header-wrapper-sticky{display:block!important;height:auto!important;min-height:0!important;max-height:none!important;'
            . 'padding:0!important;margin:0!important;background:transparent!important;box-shadow:none!important}'
            . $header . ' .header.awa-main-header{height:74px!important;min-height:74px!important;max-height:74px!important;'
            . 'padding:0 16px!important;margin:0!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;'
            . 'border-bottom:0!important}'
            . $header . ' :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container,'
            . '.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'height:74px!important;min-height:74px!important;max-height:74px!important;padding-block:0!important;'
            . 'box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' .header_main.awa-main-header-inner-wrap{border-block:0!important}'
            . $header . ' .header-main>.container{display:block!important;max-width:1280px!important;margin-inline:auto!important;'
            . 'padding-inline:16px!important}'
            . $header . ' .awa-main-header__inner.wp-header{display:grid!important;'
            . 'grid-template-columns:minmax(132px,176px) minmax(420px,1fr) minmax(236px,300px)!important;'
            . 'grid-template-areas:"brand search actions"!important;align-items:center!important;column-gap:24px!important;'
            . 'padding-block:9px!important}'
            . $header . ' .awa-header-primary-row{display:contents!important}'
            . $header . ' .awa-header-brand-cell{grid-area:brand!important;align-self:center!important;height:56px!important;'
            . 'min-height:0!important;max-height:56px!important;display:flex!important;align-items:center!important;justify-content:flex-start!important}'
            . $header . ' .awa-header-brand-cell .logo,'
            . $header . ' .awa-header-brand-cell .logo a{display:flex!important;align-items:center!important;height:56px!important;min-height:0!important}'
            . $header . ' .awa-header-brand-cell .logo img{width:104px!important;height:44px!important;max-width:104px!important;'
            . 'max-height:44px!important;object-fit:contain!important}'
            . $header . ' .awa-header-search-col{grid-area:search!important;align-self:center!important;height:56px!important;'
            . 'display:flex!important;align-items:center!important;min-width:0!important}'
            . $header . ' .awa-header-search-col :is(.block-search,#search_mini_form,form.minisearch){'
            . 'width:100%!important;max-width:760px!important;margin-inline:auto!important;height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important}'
            . $header . ' form#search_mini_form.minisearch{'
            . 'background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;'
            . 'border:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;'
            . 'border-radius:var(--awa-radius-md,8px)!important;box-shadow:none!important;overflow:hidden!important}'
            . $header . ' form#search_mini_form.minisearch :is(input,#search){'
            . 'background:transparent!important;box-shadow:none!important}'
            . $header . ' .awa-header-right-col{grid-area:actions!important;justify-self:end!important;align-self:center!important;'
            . 'height:56px!important;min-height:0!important;max-height:56px!important;display:flex!important;align-items:center!important;'
            . 'gap:10px!important}'
            . $header . ' .awa-header-account-prompt{height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'padding:0 10px!important;align-items:center!important;grid-template-columns:20px minmax(0,1fr)!important;'
            . 'gap:8px!important;border-radius:8px!important;'
            . 'background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;'
            . 'border:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;'
            . 'box-shadow:none!important}'
            . $header . ' .awa-header-account-prompt__icon{width:20px!important;min-width:20px!important;'
            . 'padding:0!important;color:var(--awa-text-muted,var(--awa-text-secondary,CanvasText))!important}'
            . $header . ' .awa-header-account-prompt__guest{gap:2px!important;line-height:1.1!important}'
            . $header . ' .awa-header-account-prompt__line1{font-size:11px!important;line-height:1.05!important;'
            . 'font-weight:600!important;color:var(--awa-text-muted,var(--awa-text-secondary,CanvasText))!important}'
            . $header . ' .awa-header-account-prompt__line2{font-size:13px!important;line-height:1.1!important;font-weight:600!important}'
            . $header . ' .awa-header-account-prompt__link--login{font-size:13px!important;font-weight:700!important;'
            . 'display:inline-flex!important;align-items:center!important;min-height:44px!important;height:44px!important;'
            . 'padding:0 4px!important;color:var(--awa-text,CanvasText)!important}'
            . $header . ' .awa-header-account-prompt__separator{font-size:10px!important;font-weight:500!important;'
            . 'padding-inline:1px!important;color:var(--awa-text-muted,var(--awa-text-secondary,CanvasText))!important}'
            . $header . ' .awa-header-account-prompt__link--register{font-size:13px!important;font-weight:700!important;'
            . 'display:inline-flex!important;align-items:center!important;min-height:44px!important;height:44px!important;'
            . 'padding:0 4px!important;background:transparent!important;color:var(--awa-primary,var(--awa-red,currentColor))!important}'
            . $header . ' :is(.minicart-wrapper,.awa-header-minicart,.action.showcart){height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;width:44px!important;min-width:44px!important;max-width:44px!important;align-self:center!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important}'
            . $header . ' :is(.awa-header-minicart .action.showcart,.awa-minicart-trigger){border-radius:8px!important;box-shadow:none!important}'
            . $header . ' .header-control.header-nav.awa-nav-bar{height:48px!important;min-height:48px!important;max-height:48px!important;'
            . 'padding:0 16px!important;margin:0!important;background:var(--awa-bg-surface,var(--awa-bg,Canvas))!important;'
            . 'border-block:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;'
            . 'border-inline:0!important}'
            . $header . ' .header-control.awa-nav-bar>.container,'
            . $header . ' .header-control.awa-nav-bar .awa-nav-bar__inner{height:46px!important;min-height:46px!important;'
            . 'max-height:46px!important;max-width:1280px!important;margin-inline:auto!important;padding:0 16px!important;'
            . 'box-sizing:border-box!important}'
            . $header . ' .header-control.awa-nav-bar .awa-nav-bar__inner{display:grid!important;'
            . 'grid-template-columns:206px minmax(0,1fr) auto!important;align-items:center!important;gap:24px!important}'
            . $header . ' .awa-header-categories.menu_left_home1{grid-column:1!important;width:206px!important;height:40px!important;'
            . 'min-height:40px!important;max-height:40px!important;padding:0!important;align-self:center!important}'
            . $header . ' .header-control.awa-nav-bar button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"],'
            . $header . ' .header-control.awa-nav-bar .our_categories.title-category-dropdown{height:40px!important;min-height:40px!important;'
            . 'max-height:40px!important;border-radius:8px!important;box-shadow:none!important}'
            . $header . ' .awa-nav-quick-links{grid-column:3!important;margin:0!important;justify-self:end!important;height:40px!important;'
            . 'min-height:40px!important;max-height:40px!important;align-items:center!important}'
            . $header . ' .awa-nav-quick-links__list{height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'align-items:center!important;gap:24px!important}'
            . $header . ' .awa-nav-quick-links__link{height:40px!important;min-height:40px!important;'
            . 'padding:0 6px!important;border-radius:8px!important;font-size:13px!important;'
            . 'font-weight:600!important;color:var(--awa-text,CanvasText)!important}'
            . '}'
            . '@media (min-width:768px) and (max-width:991px){'
            . $header . '{min-height:calc(44px + 56px + 48px)!important;height:auto!important;max-height:none!important;overflow:visible!important}'
            . $header . ' .header-wrapper-sticky{display:flex!important;flex-direction:column!important;'
            . 'height:auto!important;min-height:calc(56px + 48px)!important;max-height:none!important;overflow:visible!important}'
            . $header . ' .header.awa-main-header{height:56px!important;min-height:56px!important;max-height:56px!important;flex-shrink:0!important}'
            . $header . ' :is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar){'
            . 'display:flex!important;visibility:visible!important;height:48px!important;min-height:48px!important;'
            . 'max-height:48px!important;overflow:visible!important;pointer-events:auto!important;flex-shrink:0!important}'
            . $header . ' #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,'
            . '.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__tail){'
            . 'color:var(--awa-text-primary,#111827)!important}'
            . '}'
            . $wrap . ' :is(.page_footer,.page-footer) #footer.footer-container{'
            . 'background:var(--awa-bg,Canvas)!important;background-color:var(--awa-bg,Canvas)!important;'
            . 'border-top:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;'
            . 'color:var(--awa-text,CanvasText)!important;'
            . 'padding-block:clamp(16px,3vw,24px)!important}'
            . $wrap . ' :is(.page_footer,.page-footer) #footer.footer-container :is(a,p,li,span,strong,.velaFooterTitle){'
            . 'color:var(--awa-text,CanvasText)!important;text-shadow:none!important}'
            . $wrap . ' :is(.page_footer,.page-footer) #footer.footer-container :is(.container,.row.rowFlexMargin){'
            . 'max-width:var(--awa-home-terminal-shell,min(100%,1280px))!important;'
            . 'margin-inline:auto!important;box-sizing:border-box!important}'
            . $wrap . ' :is(.page_footer,.page-footer) #footer.footer-container .row.rowFlexMargin{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr) minmax(280px,.86fr)!important;'
            . 'gap:clamp(14px,2vw,20px)!important;align-items:start!important}'
            . $wrap . ' :is(.page_footer,.page-footer) #footer.footer-container :is(.velaFooterMenu,.awa-footer-atendimento,.velaBlock){'
            . 'background:transparent!important;border:0!important;box-shadow:none!important;min-width:0!important;padding:8px!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .footer-bottom{'
            . 'background:var(--awa-bg-soft,oklch(97% .004 20))!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .footer-bottom .footer-bottom-inner{'
            . 'padding-block:clamp(14px,2vw,18px)!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .awa-footer-bottom__copyright{'
            . 'background:var(--awa-bg,oklch(99% .002 20))!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'border-radius:var(--awa-radius-md,8px)!important;'
            . 'color:var(--awa-text,oklch(22% .02 20))!important;'
            . 'padding:var(--awa-gap-lg,16px)!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .awa-footer-bottom__copyright :is(p,span){'
            . 'color:var(--awa-text,oklch(22% .02 20))!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible{'
            . 'background:var(--awa-bg,oklch(99% .002 20))!important;'
            . 'color:var(--awa-text,oklch(22% .02 20))!important;'
            . 'border:1px solid var(--awa-border,oklch(90% .008 20))!important;'
            . 'box-shadow:0 4px 16px rgb(15 23 42 / 10%)!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible :is(.awa-cookie-banner__text,#awa-cookie-desc){'
            . 'color:var(--awa-text,oklch(22% .02 20))!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible .awa-cookie-banner__link{'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important}'
            . '@media(max-width:767px){'
            . $header . ' :is(.awa-header-minicart,.minicart-wrapper,.minicart-wrapper .action.showcart){'
            . 'width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;'
            . 'min-height:44px!important;max-height:44px!important;display:inline-flex!important;align-items:center!important;'
            . 'justify-content:center!important;box-sizing:border-box!important}'
            . $wrap . ' .top-home-content--above-fold .wrapper_slider.visible-xs .banner_item_bg :is(picture,img){'
            . 'height:clamp(132px,36vw,170px)!important;min-height:clamp(132px,36vw,170px)!important;'
            . 'object-fit:contain!important;object-position:center center!important;'
            . 'background:var(--awa-bg,oklch(99% .002 20))!important}'
            . $wrap . ' .top-home-content--category-carousel .awa-category-carousel__header.awa-section-header{'
            . 'display:grid!important;grid-template-columns:1fr!important;gap:var(--awa-gap-sm,8px)!important;'
            . 'align-items:start!important}'
            . $wrap . ' .top-home-content--category-carousel .awa-category-carousel__cta-link{'
            . 'justify-self:stretch!important;order:2!important;margin-block-start:var(--awa-gap-xs,4px)!important}'
            . $wrap . ' :is(.page_footer,.page-footer) #footer.footer-container .row.rowFlexMargin{'
            . 'grid-template-columns:1fr!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible{'
            . 'left:var(--awa-gap-md,12px)!important;right:var(--awa-gap-md,12px)!important;'
            . 'bottom:calc(var(--awa-mobile-bottom-nav-h,64px) + var(--awa-gap-sm,8px) + env(safe-area-inset-bottom,0px))!important;'
            . 'max-height:min(34vh,220px)!important;border-radius:var(--awa-radius-md,8px)!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible .awa-cookie-banner__inner{'
            . 'gap:var(--awa-gap-sm,8px)!important;padding:var(--awa-gap-md,12px)!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible :is(.awa-cookie-banner__text,#awa-cookie-desc){'
            . 'font-size:max(12px,.75rem)!important;line-height:1.4!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible .awa-cookie-banner__actions{'
            . 'grid-template-columns:1fr 1fr!important}'
            . $root . ' #awa-cookie-banner.awa-cookie-banner--visible .awa-cookie-banner__btn{'
            . 'min-height:44px!important;padding:var(--awa-gap-sm,8px) var(--awa-gap-md,12px)!important}'
            . '}';
    }

    /**
     * Desktop/tablet — prompt de conta + link Cadastrar sem pill (vence styles-l/themes stale).
     */
    public static function headerVisFixTerminalRules(): string
    {
        return '@media (min-width:992px){'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt{'
            . 'max-width:none!important;overflow:visible!important;flex-shrink:1!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt__link--register{'
            . 'display:inline-flex!important;align-items:center!important;background:transparent!important;background-color:transparent!important;'
            . 'color:var(--awa-primary,#b73337)!important;border:none!important;border-radius:0!important;'
            . 'padding:0 4px!important;min-height:44px!important;height:44px!important;min-width:0!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .awa-header-minicart{flex-shrink:0!important}'
            . '}'
            . '@media (min-width:768px) and (max-width:991px){'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt{'
            . 'position:static!important;width:auto!important;max-width:none!important;overflow:visible!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt '
            . ':is(.awa-header-account-prompt__icon,.awa-header-account-prompt__text,.awa-header-account-prompt__guest){'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;overflow:hidden!important;'
            . 'pointer-events:none!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .awa-header-account-prompt__mobile-link{'
            . 'display:inline-flex!important;visibility:visible!important;pointer-events:auto!important;'
            . 'align-items:center!important;justify-content:center!important;width:44px!important;min-width:44px!important;'
            . 'height:44px!important;min-height:44px!important;color:var(--awa-primary,#b73337)!important}'
            . '}';
    }

    /**
     * Carrinho único + links de conta sem pill residual (vence _header-main/body-end async).
     */
    public static function headerCartDedupeRules(): string
    {
        $legacyCart = 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header '
            . '.header-wrapper-sticky .header .header_main .wp-header[data-awa-header-row] '
            . '>.awa-header-primary-row>.awa-header-cart-link,'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header '
            . '.header .header_main .wp-header[data-awa-header-row] '
            . '>.awa-header-primary-row>.awa-header-cart-link';

        $fallbackHide = 'html body#html-body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-minicart:has(.minicart-wrapper .showcart) '
            . ':is(.awa-header-cart-fallback,.awa-header-cart-fallback__icon)';

        $registerReset = 'html body#html-body#html-body .page-wrapper .awa-site-header '
            . '.awa-header-account-prompt :is(.awa-header-account-prompt__link--register,'
            . '.awa-header-account-prompt__line2 .awa-header-account-prompt__link)';

        return $fallbackHide . '{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'min-width:0!important;min-height:0!important;overflow:hidden!important;'
            . 'pointer-events:none!important;position:absolute!important;clip:rect(0,0,0,0)!important}'
            . '@media (max-width:991px){'
            . $legacyCart . '{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'overflow:hidden!important;pointer-events:none!important;position:absolute!important;'
            . 'clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;margin:0!important;padding:0!important}'
            . $fallbackHide . '{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'min-width:0!important;min-height:0!important;overflow:hidden!important;'
            . 'pointer-events:none!important;position:absolute!important;clip:rect(0,0,0,0)!important}'
            . $registerReset . '{'
            . 'background:transparent!important;background-color:transparent!important;'
            . 'border:none!important;border-radius:0!important;padding:0!important;min-height:0!important}'
            . '}';
    }

    /**
     * Terminal sync — promo BUG-20 + painel B2B mobile (vence align-grid 4x html-body + account-hierarchy).
     */
    public static function headerLayoutSyncTerminalRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body .page-wrapper';
        $promo = $root . ' :is('
            . '#awa-b2b-promo-bar,'
            . '#header.header-container[data-awa-header-shell="true"] .top-header.awa-b2b-promo-bar,'
            . '#header.header-container[data-awa-header-shell="true"] .awa-b2b-promo-bar[data-awa-header-utility],'
            . '#header .top-header.awa-b2b-promo-bar,'
            . '#header .awa-b2b-promo-bar[data-awa-header-utility],'
            . '.awa-site-header .top-header.awa-b2b-promo-bar,'
            . '.awa-site-header .awa-b2b-promo-bar[data-awa-header-utility]'
            . ')';

        return $promo . '{'
            . 'background:#ffffff!important;'
            . 'background-color:#ffffff!important;'
            . 'background-image:none!important;'
            . 'color:var(--awa-text-primary,#111827)!important;'
            . 'min-height:44px!important;max-height:44px!important;height:44px!important}'
            . $root . ' .awa-site-header .awa-b2b-promo-bar :is('
            . '.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__tail,'
            . '.awa-b2b-promo-bar__separator){color:inherit!important;line-height:1.4!important}'
            . $root . ' .awa-site-header .awa-b2b-promo-bar :is('
            . '.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__cta strong){'
            . 'color:inherit!important;line-height:1.4!important;text-decoration:underline!important;'
            . 'text-underline-offset:2px!important}'
            . $root . ' .awa-site-header button.awa-b2b-promo-close,'
            . $root . ' #header button.awa-b2b-promo-close{'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;padding:0!important;box-sizing:border-box!important;border:0!important;'
            . 'background:transparent!important;color:inherit!important;opacity:1!important}'
            . $root . ' .awa-site-header .b2b-status-panel .b2b-status-trigger{'
            . 'min-height:44px!important;min-width:44px!important;box-sizing:border-box!important;'
            . 'align-items:center!important}'
            . '@media(max-width:767px){'
            . $root . ' #header .header-wrapper-sticky .awa-header-right-col:not(:has(.b2b-status-panel)),'
            . $root . ' .awa-site-header .header-wrapper-sticky .awa-header-right-col:not(:has(.b2b-status-panel)){'
            . 'display:contents!important}'
            . $root . ' #header .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel),'
            . $root . ' .awa-site-header .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel){'
            . 'display:flex!important;grid-area:cart!important;align-items:center!important;'
            . 'justify-content:flex-end!important;gap:2px!important;flex-wrap:nowrap!important;'
            . 'max-width:min(200px,52vw)!important;min-width:0!important;overflow:hidden!important}'
            . $root . ' #header .header-wrapper-sticky .awa-header-right-col > .b2b-status-panel,'
            . $root . ' .awa-site-header .header-wrapper-sticky .awa-header-right-col > .b2b-status-panel{'
            . 'display:flex!important;visibility:visible!important;pointer-events:auto!important;'
            . 'width:auto!important;min-width:0!important;max-width:min(140px,38vw)!important;'
            . 'height:auto!important;overflow:hidden!important;align-items:center!important;flex:1 1 auto!important}'
            . $root . ' .awa-site-header .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel) .awa-header-minicart{'
            . 'grid-area:unset!important;flex:0 0 44px!important;width:44px!important;'
            . 'min-width:44px!important;max-width:44px!important}'
            . '}';
    }

    /**
     * Adapt responsivo terminal — grid 44px, promo 44px, busca 16px mobile, safe-area.
     * Vence align-grid 32px promo + mobile-grid-critical 40px colunas na home.
     */
    public static function headerAdaptResponsiveRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body .page-wrapper';
        $shell = $root . ' .awa-site-header';
        $promo = $root . ' :is('
            . '#awa-b2b-promo-bar,'
            . '#header .top-header.awa-b2b-promo-bar,'
            . '#header .awa-b2b-promo-bar[data-awa-header-utility],'
            . '.awa-site-header .top-header.awa-b2b-promo-bar,'
            . '.awa-site-header .awa-b2b-promo-bar[data-awa-header-utility]'
            . ')';
        $row = $shell . ' .header-wrapper-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $searchInput = $shell . ' .awa-header-search-col form#search_mini_form input#search';

        return $promo . '{'
            . 'min-height:44px!important;max-height:44px!important;height:44px!important;'
            . 'box-sizing:border-box!important}'
            . $promo . ' :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout){'
            . 'min-height:44px!important;max-height:44px!important;height:44px!important;'
            . 'align-items:center!important;box-sizing:border-box!important}'
            . '@media(max-width:767px){'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header:not(.awa-header-condensed) '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'display:grid!important;grid-template-columns:44px minmax(0,1fr) 44px!important;'
            . 'grid-template-rows:44px 44px!important;'
            . 'grid-template-areas:"toggle brand cart" "search search search"!important;'
            . 'gap:4px 8px!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:4px 16px 0!important;box-sizing:border-box!important}'
            . $shell . ' .header-wrapper-sticky{'
            . 'min-height:96px!important;height:96px!important;max-height:96px!important;'
            . 'padding-top:max(0px,env(safe-area-inset-top,0px))!important;box-sizing:border-box!important}'
            . $row . '{'
            . 'grid-template-columns:44px minmax(0,1fr) 44px!important;'
            . 'grid-template-rows:44px 44px!important;'
            . 'grid-template-areas:"toggle brand cart" "search search search"!important;'
            . 'gap:4px 8px!important;max-height:96px!important;height:96px!important;min-height:96px!important;'
            . 'padding:4px 16px 0!important;box-sizing:border-box!important}'
            . $shell . ' .header-wrapper-sticky :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important}'
            . $shell . ' .header-wrapper-sticky .awa-header-mobile-toggle{'
            . 'display:inline-flex!important;width:44px!important;min-width:44px!important;'
            . 'height:44px!important;min-height:44px!important;touch-action:manipulation!important}'
            . $searchInput . '{font-size:max(16px,1rem)!important;line-height:1.25!important}'
            . $shell . ' :is(.awa-header-mobile-toggle,button.awa-b2b-promo-close,'
            . '.awa-header-minicart .action.showcart,form#search_mini_form button.action.search,'
            . '.b2b-status-trigger,.awa-header-account-prompt__mobile-link){'
            . 'touch-action:manipulation!important}'
            . '}'
            . '@media(min-width:768px) and (max-width:991px){'
            . $searchInput . '{font-size:max(16px,1rem)!important}'
            . $shell . ' .header-wrapper-sticky .awa-header-right-col .awa-header-account-prompt{'
            . 'min-height:44px!important;align-items:center!important}'
            . $shell . ' .header-wrapper-sticky .awa-header-right-col '
            . '.awa-header-account-prompt__mobile-link{'
            . 'min-width:44px!important;min-height:44px!important}'
            . '}'
            . '@media(max-width:480px){'
            . $row . '{'
            . 'padding-inline:max(16px,env(safe-area-inset-left,0px)) '
            . 'max(16px,env(safe-area-inset-right,0px))!important}'
            . '}'
            . '@media(min-width:992px){'
            . $searchInput . '{font-size:14px!important;line-height:1.35!important}'
            . '}';
    }

    /**
     * Harden terminal — overflow/i18n/edge cases (vence adapt; promo, B2B, busca, minicart).
     */
    public static function headerHardenCssRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body .page-wrapper';
        $shell = $root . ' .awa-site-header';
        $promo = $root . ' :is('
            . '#awa-b2b-promo-bar,'
            . '#header .top-header.awa-b2b-promo-bar,'
            . '#header .awa-b2b-promo-bar[data-awa-header-utility],'
            . '.awa-site-header .top-header.awa-b2b-promo-bar,'
            . '.awa-site-header .awa-b2b-promo-bar[data-awa-header-utility]'
            . ')';
        $promoText = $promo . ' :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,'
            . '.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__cta)';
        $account = $shell . ' .awa-header-account-prompt';
        $b2bTrigger = $shell . ' .b2b-status-panel .b2b-status-trigger';
        $minicartBadge = $shell . ' .awa-header-minicart .counter.qty,.minicart-wrapper .counter-number';

        return $promo . ' :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout){'
            . 'min-width:0!important;width:100%!important;overflow:hidden!important}'
            . $promoText . '{min-width:0!important;overflow:hidden!important;'
            . 'text-overflow:ellipsis!important;white-space:nowrap!important}'
            . $promo . '{padding-inline:max(40px,env(safe-area-inset-left,0px)+36px) '
            . 'max(12px,env(safe-area-inset-right,0px))!important}'
            . $b2bTrigger . '{min-width:0!important;max-width:100%!important;overflow:hidden!important}'
            . $shell . ' .b2b-status-panel .status-info{min-width:0!important;flex:1 1 auto!important}'
            . $shell . ' .b2b-status-panel :is(.status-greeting,.status-company){'
            . 'max-width:min(14ch,100%)!important}'
            . $account . ' .awa-header-account-prompt__text{min-width:0!important;max-width:100%!important}'
            . $account . '--long-name .awa-header-account-prompt__customer .awa-header-account-prompt__line1{'
            . 'display:block!important;max-width:min(14ch,100%)!important;overflow:hidden!important;'
            . 'text-overflow:ellipsis!important;white-space:nowrap!important}'
            . $shell . ' .awa-account-dropdown__menu{'
            . 'min-width:12rem!important;max-width:min(18rem,calc(100vw - 24px))!important}'
            . $shell . ' .awa-account-dropdown__item{overflow:hidden!important;'
            . 'text-overflow:ellipsis!important;white-space:nowrap!important}'
            . $minicartBadge . '{max-width:2.5ch!important;overflow:hidden!important;'
            . 'text-overflow:clip!important;font-size:max(10px,0.625rem)!important}'
            . '@media(max-width:767px){'
            . $shell . ' .b2b-status-panel{min-width:0!important;max-width:100%!important}'
            . $b2bTrigger . '{padding-inline:8px!important;gap:6px!important}'
            . $shell . ' .b2b-status-panel .status-group{min-width:0!important;overflow:hidden!important}'
            . $account . '{min-width:0!important;max-width:100%!important}'
            . $account . ' .awa-header-account-prompt__line1{'
            . 'max-width:min(12ch,100%)!important;overflow:hidden!important;'
            . 'text-overflow:ellipsis!important;white-space:nowrap!important}'
            . '}'
            . '@media(min-width:992px){'
            . $account . ' .awa-header-account-prompt__text{min-width:0!important}'
            . $account . '[data-awa-auth-state="customer"] .awa-header-account-prompt__customer{'
            . 'min-width:0!important;max-width:100%!important}'
            . '}'
            . '@media(prefers-reduced-motion:reduce){'
            . $promo . '.is-dismissing{transition:none!important;animation:none!important;opacity:0!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-pending="true"] '
            . '.awa-header-account-prompt__guest{transition:none!important}'
            . '}';
    }

    /**
     * Harden síncrono — busca vazia, promo dismiss FOUC, live region.
     */
    public static function headerHardenScript(): string
    {
        return '<script id="awa-header-harden-script-20260616">'
            . '(function(){'
            . 'if(window.__awaHeaderHardenInit){return;}'
            . 'window.__awaHeaderHardenInit=true;'
            . 'var bar=document.getElementById("awa-b2b-promo-bar");'
            . 'var btn=document.getElementById("awa-b2b-promo-close");'
            . 'var reduced=window.matchMedia("(prefers-reduced-motion: reduce)").matches;'
            . 'function hidePromo(){if(!bar){return;}'
            . 'bar.style.display="none";bar.setAttribute("aria-hidden","true");'
            . 'try{localStorage.setItem("awa_b2b_promo_dismissed","1");}catch(e){}}'
            . 'try{if(localStorage.getItem("awa_b2b_promo_dismissed")==="1"){hidePromo();}}catch(e){}'
            . 'if(bar&&btn&&!window.__awaPromoDismissInit){'
            . 'window.__awaPromoDismissInit=true;var dismissing=false;'
            . 'btn.addEventListener("click",function(){'
            . 'if(dismissing||bar.getAttribute("aria-hidden")==="true"){return;}'
            . 'dismissing=true;btn.disabled=true;btn.setAttribute("aria-busy","true");'
            . 'if(reduced){hidePromo();return;}'
            . 'bar.classList.add("is-dismissing");window.setTimeout(hidePromo,320);'
            . '});}'
            . 'var form=document.querySelector(".awa-site-header form#search_mini_form");'
            . 'if(!form){return;}'
            . 'form.addEventListener("submit",function(ev){'
            . 'var input=form.querySelector("#search");'
            . 'if(!input){return;}'
            . 'if(!String(input.value||"").trim()){'
            . 'ev.preventDefault();ev.stopImmediatePropagation();input.setAttribute("aria-invalid","true");input.focus();'
            . 'var live=document.getElementById("awa-header-search-live");'
            . 'if(!live){live=document.createElement("div");live.id="awa-header-search-live";'
            . 'live.className="awa-sr-live";live.setAttribute("role","status");'
            . 'live.setAttribute("aria-live","polite");form.appendChild(live);}'
            . 'live.textContent="Digite um termo para buscar";'
            . 'window.setTimeout(function(){input.removeAttribute("aria-invalid");live.textContent="";},3000);'
            . '}},true);'
            . '})();'
            . '</script>';
    }

    /**
     * Layout terminal — tokens de ritmo, seam promo/main, col-gap e nav flush.
     * Corrige header-container 36px vs promo 44px (overlap ~8px medido em runtime).
     */
    public static function headerLayoutSpacingRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body .page-wrapper';
        $shell = $root . ' .awa-site-header';
        $promo = $root . ' :is('
            . '#awa-b2b-promo-bar,'
            . '#header .top-header.awa-b2b-promo-bar,'
            . '#header .awa-b2b-promo-bar[data-awa-header-utility],'
            . '.awa-site-header .top-header.awa-b2b-promo-bar,'
            . '.awa-site-header .awa-b2b-promo-bar[data-awa-header-utility]'
            . ')';
        $headerContainer = $shell . ' .header-container:has(.awa-b2b-promo-bar),'
            . $shell . ' .header-container:has(#awa-b2b-promo-bar),'
            . $root . ' #header.header-container:has(.awa-b2b-promo-bar),'
            . $root . ' #header.header-container:has(#awa-b2b-promo-bar)';
        $sticky = $shell . ' .header-wrapper-sticky';
        $row = $sticky . ' :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $nav = $shell . ' :is(.header-control.awa-nav-bar,.header-control.header-nav.awa-nav-bar)';
        $rightCol = $shell . ' .awa-header-right-col';
        $promoInner = $promo . ' :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout,.header-content)';

        return $shell . '{'
            . '--awa-header-promo-h:32px;--awa-header-nav-h:48px;'
            . '--awa-header-shell-pad:16px;--awa-header-col-gap:clamp(16px,2vw,24px)}'
            . '@media(max-width:767px){' . $shell . '{'
            . '--awa-header-main-row-h:96px;--awa-header-shell-pad:16px;--awa-header-col-gap:8px;'
            . '--awa-header-stack-h:calc(var(--awa-header-promo-h)+var(--awa-header-main-row-h))}}'
            . '@media(min-width:768px) and (max-width:991px){' . $shell . '{'
            . '--awa-header-main-row-h:56px;--awa-header-col-gap:12px;'
            . '--awa-header-stack-h:calc(var(--awa-header-promo-h)+var(--awa-header-main-row-h)+var(--awa-header-nav-h))}}'
            . '@media(min-width:992px){' . $shell . '{'
            . '--awa-header-main-row-h:76px;'
            . '--awa-header-stack-h:calc(var(--awa-header-promo-h)+var(--awa-header-main-row-h)+var(--awa-header-nav-h));'
            . '--awa-header-scroll-offset:calc(var(--awa-header-stack-h)+8px)}}'
            . $headerContainer . '{'
            . 'height:var(--awa-header-promo-h)!important;min-height:var(--awa-header-promo-h)!important;'
            . 'max-height:var(--awa-header-promo-h)!important;overflow:visible!important;'
            . 'padding-block:8px!important;margin-block:0!important;box-sizing:border-box!important}'
            . $promo . '{'
            . 'height:var(--awa-header-promo-h)!important;min-height:var(--awa-header-promo-h)!important;'
            . 'max-height:var(--awa-header-promo-h)!important;'
            . 'padding-block:8px!important;margin-block:0!important;box-sizing:border-box!important}'
            . $promoInner . '{'
            . 'height:100%!important;min-height:0!important;max-height:100%!important;'
            . 'align-items:center!important;padding-block:8px!important;box-sizing:border-box!important}'
            . $sticky . '{margin-block-start:0!important;padding-block-start:0!important;box-sizing:border-box!important}'
            . '@media(min-width:768px){' . $row . '{'
            . 'column-gap:var(--awa-header-col-gap)!important;row-gap:0!important;'
            . 'padding-inline:var(--awa-header-shell-pad)!important}}'
            . $rightCol . '{display:inline-flex!important;align-items:center!important;'
            . 'gap:min(var(--awa-header-col-gap,12px),12px)!important;flex-wrap:nowrap!important}'
            . '@media(min-width:992px){' . $sticky . '{'
            . 'display:flex!important;flex-direction:column!important;gap:0!important}'
            . $nav . '{margin-block-start:1px!important;padding-block-start:0!important;flex-shrink:0!important}'
            . $shell . ' .header-wrapper-sticky :is(.header-main,.header_main){'
            . 'margin-block-end:0!important;padding-block-end:0!important}}'
            . '@media(min-width:768px) and (max-width:991px){'
            . $shell . ' .awa-header-search-col,'
            . $shell . ' .awa-header-search-col form#search_mini_form{'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important}'
            . '}';
    }

    /**
     * Polish terminal — tokens live, estados de interação, seam main/nav, focus rings.
     * headerPolishRules do cascade-lock nem sempre está no HTML (home/PLP omit).
     */
    public static function headerPolishTerminalRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body .page-wrapper';
        $shell = $root . ' .awa-site-header';
        $row = $shell . ' .header-wrapper-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $mainHeader = $shell . ' .header-wrapper-sticky .header.awa-main-header';
        $nav = $shell . ' :is(.header-control.awa-nav-bar,.header-control.header-nav.awa-nav-bar)';
        $searchForm = $shell . ' form#search_mini_form';
        $searchBtn = $shell . ' form#search_mini_form button.action.search';
        $promoClose = $root . ' button.awa-b2b-promo-close,#awa-b2b-promo-close';
        $mobileToggle = $shell . ' :is(.awa-header-mobile-toggle,.nav-toggle,[data-action="toggle-nav"])';
        $interactive = $shell . ' :is('
            . 'form#search_mini_form,button.action.search,.action.showcart,.awa-minicart-trigger,'
            . '.awa-nav-quick-links__link,.custommenu.main-nav a,.navigation.custommenu a,'
            . 'button.awa-b2b-promo-close,.awa-header-mobile-toggle,'
            . 'button[data-role=awa-vertical-menu-trigger],.our_categories.title-category-dropdown'
            . ')';

        return $shell . '{'
            . '--awa-header-polish-ease:cubic-bezier(.22,1,.36,1);'
            . '--awa-header-polish-hover:oklch(42% .13 20);'
            . '--awa-header-polish-ring:color-mix(in srgb,var(--awa-primary,oklch(48% .14 20)) 26%,transparent)}'
            . $interactive . '{'
            . 'transition:background-color .18s var(--awa-header-polish-ease),'
            . 'border-color .18s var(--awa-header-polish-ease),'
            . 'box-shadow .18s var(--awa-header-polish-ease),'
            . 'color .18s var(--awa-header-polish-ease),'
            . 'transform .12s var(--awa-header-polish-ease)!important}'
            . $searchForm . ' input#search::placeholder{'
            . 'color:oklch(50% .018 20)!important;opacity:1!important}'
            . $searchForm . ':hover{'
            . 'border-color:color-mix(in srgb,var(--awa-primary,oklch(48% .14 20)) 38%,var(--awa-border,oklch(90% .008 20)))!important}'
            . $searchForm . ':focus-within{'
            . 'border-color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'box-shadow:0 0 0 3px var(--awa-header-polish-ring)!important}'
            . $searchBtn . ':hover{background:var(--awa-header-polish-hover)!important;'
            . 'box-shadow:0 6px 14px rgb(15 23 42/14%)!important}'
            . $searchBtn . ':active{transform:translateY(1px)!important;box-shadow:none!important}'
            . $searchBtn . ':focus-visible{outline:2px solid var(--awa-primary,oklch(48% .14 20))!important;'
            . 'outline-offset:2px!important}'
            . $promoClose . ':focus-visible{outline:2px solid var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'outline-offset:2px!important}'
            . $mobileToggle . ':focus-visible{outline:2px solid var(--awa-primary,oklch(48% .14 20))!important;'
            . 'outline-offset:2px!important}'
            . $nav . ' :is(a,button):focus-visible{'
            . 'outline:2px solid var(--awa-text-inverse,oklch(99% .002 20))!important;'
            . 'outline-offset:2px!important;border-radius:8px!important}'
            . $nav . ' :is(a,button):hover{'
            . 'background:color-mix(in srgb,var(--awa-text-inverse,oklch(99% .002 20)) 14%,transparent)!important;'
            . 'border-radius:8px!important;text-decoration:none!important}'
            . $shell . ' :is(.action.showcart,.awa-minicart-trigger) .counter.qty{'
            . 'position:absolute!important;inset-block-start:-5px!important;inset-inline-end:-6px!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'min-width:18px!important;height:18px!important;padding:0 5px!important;'
            . 'border-radius:999px!important;background:oklch(99% .002 20)!important;'
            . 'color:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'font-size:11px!important;font-weight:800!important;line-height:18px!important;'
            . 'box-shadow:0 0 0 2px var(--awa-primary,oklch(48% .14 20))!important}'
            . '@media(max-width:991px){' . $shell . ':not(.awa-header-condensed) ' . $row . '{'
            . 'overflow:visible!important}'
            . $shell . ' :is(.awa-header-mobile-toggle,.nav-toggle,[data-action="toggle-nav"])[aria-expanded="true"]{'
            . 'background:var(--awa-primary,oklch(48% .14 20))!important;'
            . 'border-color:var(--awa-primary,oklch(48% .14 20))!important;color:oklch(99% .002 20)!important}'
            . $shell . ' :is(.awa-header-mobile-toggle,.nav-toggle,[data-action="toggle-nav"])[aria-expanded="true"] svg{'
            . 'stroke:oklch(99% .002 20)!important;color:oklch(99% .002 20)!important}'
            . '}'
            . '@media(min-width:992px){' . $mainHeader . '{'
            . 'display:flex!important;align-items:center!important;'
            . 'height:var(--awa-header-main-row-h,72px)!important;'
            . 'min-height:var(--awa-header-main-row-h,72px)!important;'
            . 'max-height:var(--awa-header-main-row-h,72px)!important;'
            . 'overflow:hidden!important;line-height:1!important;padding-block:0!important;margin-block:0!important;'
            . 'box-sizing:border-box!important}'
            . $row . '{height:var(--awa-header-main-row-h,72px)!important;'
            . 'max-height:var(--awa-header-main-row-h,72px)!important;min-height:0!important;'
            . 'margin-block:0!important;padding-block:0!important;box-sizing:border-box!important;'
            . 'align-self:center!important}'
            . $nav . '{margin-block-start:0!important;padding-block-start:0!important;transform:translateY(1px)!important}'
            . $shell . ' .awa-header-brand-cell :is(a,img){'
            . 'display:flex!important;align-items:center!important;'
            . 'max-height:calc(var(--awa-header-main-row-h,72px) - 16px)!important}'
            . '}'
            . '@media(prefers-reduced-motion:reduce){' . $interactive . '{transition:none!important}'
            . $searchBtn . ':active{transform:none!important}'
            . '}';
    }

    /**
     * Container pad zero — mobile header inner (vence themes.min.css tardio).
     */
    public static function headerAlignGridContainerPadRules(): string
    {
        return 'html body#html-body#html-body .page-wrapper .header .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header .header_main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header .header-main .container,'
            . 'html body#html-body#html-body .page-wrapper .header .header_main .container,'
            . 'html body#html-body#html-body .page-wrapper .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header_main>.container,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header_main>.container'
            . '{padding:0!important;padding-inline:0!important;padding-left:0!important;padding-right:0!important}';
    }

    public static function headerAlignGridContainerPadScript(): string
    {
        return '<script id="awa-header-container-pad-zero">(function(){'
            . '"use strict";function z(){if(window.innerWidth>991)return;'
            . 'document.querySelectorAll(".page-wrapper .header-main>.container,.page-wrapper .header_main>.container").forEach(function(el){'
            . 'el.style.removeProperty("padding");'
            . 'el.style.removeProperty("padding-inline");'
            . 'el.style.removeProperty("padding-left");'
            . 'el.style.removeProperty("padding-right");'
            . 'if(!el.getAttribute("style"))el.removeAttribute("style");});}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",z,{once:true});}'
            . 'else{z();}window.addEventListener("load",z,{once:true,passive:true});})();</script>';
    }

    /**
     * Distill terminal — SSOT único: substitui 6 blocos inline (~90KB) por 1 (~35KB).
     *
     * @param bool $includeHomeSupplement regras home-only (a11y, minicart, compact) quando omitimos home-light-lock
     */
    public static function headerDistillTerminalCss(bool $includeHomeSupplement = false): string
    {
        $css = self::headerLayoutAlignRules()
            . self::mobileSearchTerminalRules()
            . self::headerVisFixTerminalRules()
            . self::headerCartDedupeRules()
            . self::headerLayoutSyncTerminalRules()
            . self::headerAdaptResponsiveRules()
            . self::headerLayoutSpacingRules()
            . self::headerHardenCssRules()
            . self::headerPolishTerminalRules()
            . self::headerEssentialTerminalRules()
            . self::headerAlignGridContainerPadRules();

        if ($includeHomeSupplement) {
            $css .= self::headerDistillHomeSupplementRules();
        }

        $css .= self::headerLayoutTerminalLockRules()
            . self::headerImpeccableCommercePaddingRules()
            . self::homeHeaderRailTerminalRules()
            . self::plpImpeccableTerminalLockRules()
            . self::catalogMobileHeaderClampRules(self::checkoutImpeccableLockRoot())
            . self::headerBolderActionContrastLockRules()
            . self::headerAccountVtexCleanTerminalRules()
            . self::headerSimplifyUiTerminalRules();

        return $css;
    }

    /**
     * UI simplificada — menos ruído visual, uma linha por zona, sem duplicatas.
     */
    public const HEADER_SIMPLIFY_UI_STYLE_ID = 'awa-header-simplify-ui-terminal-lock';

    /**
     * Tag terminal — injetada após align-grid/header-container (final-wins sobre home 10× #html-body).
     */
    public static function headerSimplifyUiTerminalStyleTag(): string
    {
        return '<style id="' . self::HEADER_SIMPLIFY_UI_STYLE_ID . '">'
            . self::headerSimplifyUiTerminalRules()
            . '</style>';
    }

    public static function headerSimplifyUiTerminalRules(): string
    {
        $shell = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header[data-awa-header-mode="default"]';
        $homeShell = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5).page-layout-1column .page-wrapper '
            . '.awa-site-header[data-awa-header-mode="default"]';
        $visuallyGone = 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'min-width:0!important;min-height:0!important;max-width:0!important;max-height:0!important;'
            . 'flex:0 0 0!important;margin:0!important;padding:0!important;overflow:hidden!important;'
            . 'pointer-events:none!important;position:absolute!important;clip:rect(0,0,0,0)!important;'
            . 'clip-path:inset(50%)!important';

        return '@media(min-width:768px){'
            . $shell . ' .awa-header-primary-row>.awa-header-cart-link,' . $shell . ' .awa-header-cart-link{'
            . $visuallyGone . '}'
            . $shell . ' .top-account.awa-header-account-nav[hidden]{' . $visuallyGone . '}'
            . $shell . ' .header-control.awa-nav-bar{background:var(--awa-bg,Canvas)!important;'
            . 'border-block-start:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;'
            . 'border-block-end:0!important;box-shadow:none!important}'
            . $shell . ' .awa-nav-quick-links__link{color:var(--awa-text-secondary,CanvasText)!important;'
            . 'font-weight:500!important;text-decoration:none!important}'
            . $shell . ' .awa-nav-quick-links__link:hover{color:var(--awa-primary,CanvasText)!important}'
            . $shell . ' form#search_mini_form{box-shadow:none!important;'
            . 'border:1px solid var(--awa-border-subtle,var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas)))!important;'
            . 'background:var(--awa-bg-subtle,color-mix(in srgb,Canvas 96%,CanvasText 4%))!important}'
            . $shell . ' form#search_mini_form:focus-within{background:var(--awa-bg,Canvas)!important;'
            . 'border-color:var(--awa-primary,CanvasText)!important}'
            . $shell . ' form#search_mini_form button.action.search{background:transparent!important;'
            . 'color:var(--awa-primary,CanvasText)!important;box-shadow:none!important}'
            . '}'
            . '@media(min-width:992px){'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"],'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="guest"]{'
            . 'display:inline-flex!important;align-items:center!important;gap:8px!important;'
            . 'grid-template-columns:unset!important;max-width:none!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] a.awa-header-account-prompt__icon,'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] a.awa-header-account-prompt__icon{'
            . 'display:inline-flex!important;visibility:visible!important;align-items:center!important;'
            . 'justify-content:center!important;flex:0 0 auto!important;width:auto!important;height:auto!important;'
            . 'min-width:0!important;min-height:0!important;max-width:none!important;max-height:none!important;'
            . 'margin:0!important;padding:0!important;overflow:visible!important;pointer-events:auto!important;'
            . 'position:static!important;clip:auto!important;clip-path:none!important;'
            . 'color:var(--awa-primary,#b73337)!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] a.awa-header-account-prompt__icon svg,'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] a.awa-header-account-prompt__icon svg{'
            . 'display:block!important;width:24px!important;height:24px!important;color:var(--awa-primary,#b73337)!important}'
            . $shell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__text,.awa-header-account-prompt__guest),'
            . $homeShell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__text,.awa-header-account-prompt__guest){'
            . 'display:inline-flex!important;flex-direction:row!important;align-items:center!important;'
            . 'gap:0!important;height:44px!important;max-height:44px!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__guest,'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__guest{'
            . 'display:inline-flex!important;flex-direction:column!important;align-items:flex-start!important;'
            . 'justify-content:center!important;gap:1px!important;height:44px!important;max-height:44px!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="customer"] .awa-header-account-prompt__line1,'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="customer"] .awa-header-account-prompt__line1{'
            . 'display:none!important;height:0!important;overflow:hidden!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__line1,'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__line1{'
            . 'display:block!important;height:auto!important;max-height:none!important;overflow:visible!important;'
            . 'white-space:nowrap!important;font-size:11px!important;line-height:1.3!important;'
            . 'font-weight:400!important;color:var(--awa-text-secondary,#666666)!important;'
            . 'text-transform:none!important}'
            . $shell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__line2,.awa-header-account-prompt__actions),'
            . $homeShell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__line2,.awa-header-account-prompt__actions){'
            . 'display:inline-flex!important;align-items:center!important;gap:4px!important;height:auto!important;'
            . 'line-height:1.3!important;font-size:13px!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__link,'
            . $homeShell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__link{'
            . 'color:var(--awa-text,#1a1a1a)!important;font-weight:700!important;text-decoration:none!important}'
            . $shell . ' .awa-header-account-prompt__separator,' . $homeShell . ' .awa-header-account-prompt__separator{'
            . 'color:var(--awa-text-muted,CanvasText)!important;font-weight:400!important;padding-inline:2px!important}'
            . $shell . ' .awa-header-primary-nav.menu_primary:has(nav.top-menu:empty){display:none!important;'
            . 'visibility:hidden!important;width:0!important;height:0!important;overflow:hidden!important;'
            . 'pointer-events:none!important;flex:0 0 0!important}'
            . $shell . ' .awa-header-right-col{gap:6px!important;max-width:none!important;min-width:0!important}'
            . $shell . ' .awa-nav-bar__inner{gap:16px!important}'
            . $shell . ' button.title-category-dropdown.our_categories{border-radius:var(--awa-radius-md,8px)!important;'
            . 'font-weight:600!important;box-shadow:none!important}'
            . $shell . ' .header-control.awa-nav-bar[data-awa-header-nav="true"] button.title-category-dropdown.our_categories{border-radius:0!important}'
            . '}';
    }

    /**
     * Conta B2B compacta — última camada do distill para remover card alto do header desktop.
     */
    public static function headerAccountVtexCleanTerminalRules(): string
    {
        $shell = 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header';
        $account = $shell . ' :is(.awa-header-contact-links.awa-header-account-prompt,.awa-header-account-prompt)';
        $text = $shell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__text,'
            . '.awa-header-account-prompt__guest,.awa-header-account-prompt__customer)';

        return '@media(min-width:992px){'
            . $shell . ' .awa-header-right-col{align-items:center!important;height:44px!important;max-height:44px!important;'
            . 'overflow:visible!important;position:relative!important;z-index:100270!important}'
            . $account . '{align-items:center!important;background:transparent!important;border:0!important;box-shadow:none!important;'
            . 'box-sizing:border-box!important;display:inline-flex!important;flex:1 1 auto!important;gap:6px!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;max-width:232px!important;'
            . 'min-width:0!important;overflow:visible!important;padding:0!important;position:relative!important;'
            . 'width:auto!important;z-index:100260!important}'
            . $shell . ' .awa-header-account-prompt__icon{align-items:center!important;display:flex!important;flex:0 0 44px!important;'
            . 'height:44px!important;justify-content:center!important;max-height:44px!important;max-width:44px!important;'
            . 'min-height:44px!important;min-width:44px!important;padding:0!important;width:44px!important}'
            . $text . '{box-sizing:border-box!important;display:flex!important;flex:1 1 auto!important;flex-direction:column!important;'
            . 'height:44px!important;justify-content:center!important;line-height:1.1!important;max-height:44px!important;'
            . 'max-width:160px!important;min-height:44px!important;min-width:0!important;overflow:hidden!important;width:auto!important}'
            . $shell . ' .awa-header-account-prompt__line1{display:none!important;font-size:10px!important;font-weight:700!important;'
            . 'height:0!important;line-height:0!important;margin:0!important;overflow:hidden!important;white-space:nowrap!important}'
            . $shell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__line2,.awa-header-account-prompt__actions){'
            . 'align-items:center!important;box-sizing:border-box!important;display:flex!important;flex-wrap:nowrap!important;'
            . 'gap:2px 6px!important;height:44px!important;line-height:44px!important;max-height:44px!important;'
            . 'max-width:160px!important;min-height:44px!important;min-width:0!important;overflow:hidden!important;width:auto!important}'
            . $shell . ' .awa-header-account-prompt :is(a,.awa-header-account-prompt__link){align-items:center!important;'
            . 'box-sizing:border-box!important;display:inline-flex!important;height:44px!important;line-height:44px!important;'
            . 'min-height:44px!important;padding:0 2px!important;white-space:nowrap!important}'
            . $shell . ' .awa-header-account-prompt__separator{height:44px!important;line-height:44px!important;margin:0!important;padding-inline:1px!important}'
            . $shell . ' .awa-account-dropdown{overflow:visible!important;position:relative!important;z-index:100270!important}'
            . $shell . ' .awa-account-dropdown__trigger{position:relative!important;z-index:100280!important}'
            . $shell . ' .awa-account-dropdown__menu{background:var(--awa-bg,Canvas)!important;'
            . 'border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 12%,Canvas))!important;'
            . 'border-radius:8px!important;box-shadow:0 8px 20px color-mix(in srgb,CanvasText 14%,transparent)!important;'
            . 'box-sizing:border-box!important;display:none!important;inset-block-start:calc(100% - 1px)!important;'
            . 'inset-inline-end:0!important;margin:0!important;margin-block-start:0!important;'
            . 'min-width:12rem!important;max-width:min(18rem,calc(100vw - 24px))!important;'
            . 'overflow:hidden!important;padding:6px!important;position:absolute!important;'
            . 'top:calc(100% - 1px)!important;transform:none!important;visibility:hidden!important;'
            . 'z-index:100300!important}'
            . $shell . ' .awa-account-dropdown__menu[aria-hidden="false"]{'
            . 'display:grid!important;opacity:1!important;pointer-events:auto!important;visibility:visible!important}'
            . $shell . ' .awa-account-dropdown__item{align-items:center!important;box-sizing:border-box!important;'
            . 'display:grid!important;grid-template-columns:18px minmax(0,1fr)!important;gap:8px!important;'
            . 'min-height:40px!important;padding:8px 10px!important}'
            . $shell . ' .awa-account-dropdown__item svg{height:18px!important;width:18px!important}'
            . $shell . '{position:relative!important;z-index:100120!important}'
            . $shell . ':has(.awa-account-dropdown__trigger[aria-expanded="true"]){z-index:100260!important}'
            . $shell . ' .header-wrapper-sticky{z-index:100130!important}'
            . $shell . ':has(.awa-account-dropdown__trigger[aria-expanded="true"]) .header-wrapper-sticky{z-index:100260!important}'
            . $shell . ':has(.awa-account-dropdown__trigger[aria-expanded="true"]) '
            . ':is(.header-wrapper-sticky,.header.awa-main-header,.header-main,.header-main>.container,'
            . '.awa-main-header__inner,.awa-header-right-col){'
            . 'overflow:visible!important;position:relative!important}'
            . $shell . ' .header-control.awa-nav-bar{height:48px!important;min-height:48px!important;max-height:48px!important;overflow:visible!important;position:relative!important;z-index:100140!important}'
            . $shell . ':has(.awa-account-dropdown__trigger[aria-expanded="true"]) .header-control.awa-nav-bar{z-index:100120!important}'
            . $shell . ' .header-control.awa-nav-bar :is(.container,.awa-nav-bar__inner){align-items:center!important;height:48px!important;min-height:48px!important;max-height:48px!important;overflow:visible!important;position:relative!important;z-index:100140!important}'
            . $shell . ':has(.awa-account-dropdown__trigger[aria-expanded="true"]) .header-control.awa-nav-bar :is(.container,.awa-nav-bar__inner){z-index:100120!important}'
            . $shell . ' .awa-header-categories.menu_left_home1{height:44px!important;min-height:44px!important;max-height:44px!important;overflow:visible!important;position:relative!important}'
            . $shell . ' .awa-header-categories.menu_left_home1>nav.awa-nav-categories{height:44px!important;left:auto!important;max-height:44px!important;overflow:visible!important;position:relative!important;top:auto!important;width:100%!important;z-index:100140!important}'
            . $shell . ' .awa-header-categories.menu_left_home1>nav.awa-nav-categories>.sections.nav-sections.category-dropdown{box-sizing:border-box!important;height:44px!important;left:auto!important;max-height:44px!important;overflow:visible!important;position:relative!important;top:auto!important;width:100%!important;z-index:100140!important}'
            . $shell . ' .awa-header-categories.menu_left_home1 :is(.section-items.nav-sections.category-dropdown-items,.section-item-content.nav-sections.category-dropdown-item-content,.navigation.verticalmenu.side-verticalmenu){box-sizing:border-box!important;height:44px!important;max-height:44px!important;overflow:visible!important;position:relative!important;width:100%!important}'
            . $shell . ' .awa-header-categories.menu_left_home1 .navigation.verticalmenu.side-verticalmenu{background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important;display:flex!important;max-width:206px!important}'
            . $shell . ' .awa-header-categories.menu_left_home1 .navigation.verticalmenu.side-verticalmenu :is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]){height:44px!important;left:auto!important;max-height:44px!important;min-height:44px!important;position:relative!important;top:auto!important;width:206px!important}'
            . $shell . ' .awa-header-categories.menu_left_home1 ul.togge-menu.list-category-dropdown{background:var(--awa-bg,Canvas)!important;border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;border-radius:0 0 8px 8px!important;box-shadow:0 4px 10px color-mix(in srgb,CanvasText 10%,transparent)!important;box-sizing:border-box!important;display:none!important;height:auto!important;left:0!important;max-height:min(70vh,560px)!important;overflow-x:hidden!important;overflow-y:auto!important;position:absolute!important;top:44px!important;width:min(304px,calc(100vw - 32px))!important;z-index:100150!important}'
            . $shell . ' .awa-header-categories.menu_left_home1:is(:hover,.is-open,.active,.awa-vmf-active) ul.togge-menu.list-category-dropdown,'
            . $shell . ' .awa-header-categories.menu_left_home1 ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open,[aria-hidden="false"]){display:block!important;height:auto!important;max-height:min(70vh,560px)!important;opacity:1!important;overflow-x:hidden!important;overflow-y:auto!important;visibility:visible!important}'
            . $shell . ' .awa-header-categories.menu_left_home1:is(:hover,.is-open,.active,.awa-vmf-active) ul.togge-menu.list-category-dropdown>li{box-sizing:border-box!important;display:block!important;height:auto!important;min-height:40px!important;opacity:1!important;overflow:visible!important;visibility:visible!important}'
            . $shell . ' .awa-header-categories.menu_left_home1:is(:hover,.is-open,.active,.awa-vmf-active) ul.togge-menu.list-category-dropdown>li>a{align-items:center!important;display:flex!important;min-height:40px!important;padding:10px 12px!important}'
            . '}'
            . '@media(min-width:768px) and (max-width:991px){'
            . $account . '{height:44px!important;min-height:44px!important;max-height:44px!important;overflow:hidden!important}'
            . '}';
    }

    /**
     * Terminal lock — última camada do distill (logo, busca desktop, busca mobile fill, pad mobile).
     */
    public static function headerLayoutTerminalLockRules(): string
    {
        $shell = 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header';
        $row = $shell . ' .header-wrapper-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $searchInput = $shell . ' .awa-header-search-col form#search_mini_form input#search';

        return '@media(min-width:992px){'
            . $shell . ' :is(.awa-header-brand-cell,.col-md-2.awa-header-brand) .logo img{'
            . 'width:104px!important;max-width:104px!important;min-width:0!important;height:44px!important;max-height:44px!important;'
            . 'aspect-ratio:auto!important;object-fit:contain!important;object-position:left center!important}'
            . $searchInput . '{font-size:14px!important;line-height:1.35!important}'
            . '}'
            . '@media(max-width:767px){'
            . $row . '{padding-inline:16px!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-search-col{display:block!important;grid-template-columns:none!important;'
            . 'width:100%!important;min-width:0!important;max-width:100%!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-search-col :is(.block-search,.block-content){'
            . 'display:block!important;width:100%!important;max-width:100%!important;min-width:0!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;'
            . 'grid-template-areas:"field submit"!important;width:100%!important;max-width:100%!important;'
            . 'box-sizing:border-box!important;margin:0!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form input#search{'
            . 'width:100%!important;min-width:0!important;max-width:100%!important}'
            . '}'
            . '@media(min-width:992px){'
            . $shell . ' :is(.awa-header-contact-links.awa-header-account-prompt,.awa-header-account-prompt){'
            . 'display:inline-flex!important;align-items:flex-start!important;'
            . 'height:auto!important;min-height:44px!important;max-height:none!important;'
            . 'padding-block:6px!important;padding-inline:0!important;margin:0!important;'
            . 'overflow:visible!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-account-prompt :is(.awa-header-account-prompt__text,'
            . '.awa-header-account-prompt__guest,.awa-header-account-prompt__customer){'
            . 'overflow:visible!important;line-height:1.35!important}'
            . '}';
    }

    /**
     * Impeccable commerce padding — última camada do distill (promo, minicart, footer, kbd).
     */
    public static function headerImpeccableCommercePaddingRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body .page-wrapper';
        $rootNonHome = 'html body#html-body#html-body#html-body#html-body'
            . ':not(.cms-index-index):not(.cms-home):not(.cms-homepage_ayo_home5):not(.checkout-cart-index) .page-wrapper';
        $promo = $root . ' :is('
            . '#awa-b2b-promo-bar,'
            . '#header .top-header.awa-b2b-promo-bar,'
            . '#header .awa-b2b-promo-bar[data-awa-header-utility],'
            . '.awa-site-header .top-header.awa-b2b-promo-bar,'
            . '.awa-site-header .awa-b2b-promo-bar[data-awa-header-utility]'
            . ')';
        $promoInner = $promo . ' :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout)';
        $headerShell = $rootNonHome . ' #header.header-container:has(.awa-b2b-promo-bar),'
            . $rootNonHome . ' #header.header-container:has(#awa-b2b-promo-bar)';
        $stickyWrap = $rootNonHome . ' .awa-site-header .header-wrapper-sticky,'
            . $rootNonHome . ' .awa-site-header .header-wrapper-sticky.is-sticky';
        $stickyInner = $stickyWrap . ' :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.awa-main-header__inner)';
        $navItem = $rootNonHome . ' .awa-site-header '
            . ':is(.header-control.header-nav,.header-control.awa-nav-bar) .ui-menu-item.navigation__item';
        $navBar = $rootNonHome . ' .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.header-control.header-nav)';
        $navContainer = $navBar . ' > .container';
        $mainRow = $rootNonHome . ' .awa-site-header .header-wrapper-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $mainHeader = $rootNonHome . ' .awa-site-header '
            . ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap)';
        $mainHeaderSticky = $rootNonHome . ' .awa-site-header .header-wrapper-sticky '
            . ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header_main)';
        $searchBlockContent = $rootNonHome . ' .awa-site-header '
            . ':is(.block.block-search>.block-content,.awa-header-search-col .block-content)';
        $cartRoot = 'html body#html-body#html-body#html-body#html-body#html-body'
            . '.checkout-cart-index.checkout-cart-index .page-wrapper';
        $cartHeaderShell = $cartRoot . ' #header.header-container:has(.awa-b2b-promo-bar),'
            . $cartRoot . ' #header.header-container:has(#awa-b2b-promo-bar)';
        $cartHeaderContent = $cartRoot . ' #header.header-container[data-awa-header-shell="true"] .header-content';
        $cartPromo = $cartRoot . ' :is(#awa-b2b-promo-bar,.awa-b2b-promo-bar)';
        $cartPromoInner = $cartPromo . ' :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout)';
        $cartPromoClose = $cartPromo . ' .awa-b2b-promo-close';
        $cartSticky = $cartRoot . ' .awa-site-header .header-wrapper-sticky,'
            . $cartRoot . ' .header-wrapper-sticky';
        $cartStickyInner = $cartRoot . ' .awa-site-header .header-wrapper-sticky '
            . ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.awa-main-header__inner,'
            . '.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $cartNav = $cartRoot . ' .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.header-control.header-nav)';
        $cartNavContainer = $cartNav . ' > .container';
        $cartNavInner = $cartRoot . ' .awa-site-header .awa-nav-bar__inner';
        $cartStrongRoot = 'html body#html-body.checkout-cart-index.page-layout-1column'
            . ':not(.onepagecheckout-index-index):not(.checkout-index-index)'
            . ':not(.cms-index-index):not(.cms-home):not(.cms-homepage_ayo_home5) '
            . '.page-wrapper:has(#header.header-container[data-awa-header-shell="true"]):has(#awa-b2b-promo-bar)';
        $cartStrongSticky = $cartStrongRoot
            . ' .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky';
        $cartStrongClose = $cartStrongRoot . ' #awa-b2b-promo-bar .awa-b2b-promo-close';

        $kbd = 'html body#html-body#html-body#html-body#html-body '
            . ':is(.page-wrapper,.awa-ks-trigger-hint,#awa-shortcuts-modal) kbd';

        return $headerShell . '{padding:8px!important;box-sizing:border-box!important}'
            . $stickyWrap . '{'
            . 'padding:8px!important;padding-block:8px!important;box-sizing:border-box!important;'
            . 'box-shadow:none!important;border:0!important;border-block-end:1px solid var(--awa-border,#e5e7eb)!important;'
            . 'border-radius:0!important;overflow:visible!important}'
            . $stickyInner . '{'
            . 'padding:8px!important;padding-block:8px!important;border:0!important;box-shadow:none!important;'
            . 'border-radius:0!important;background:var(--awa-bg,#fff)!important;box-sizing:border-box!important;'
            . 'overflow:visible!important}'
            . $mainHeaderSticky . '{'
            . 'padding:8px!important;padding-block:8px!important;padding-inline:8px!important;'
            . 'box-sizing:border-box!important;overflow:visible!important;'
            . 'height:auto!important;max-height:none!important;min-height:0!important}'
            . $stickyWrap . ' :is(.header-main,.header_main,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'height:auto!important;max-height:none!important;'
            . 'min-height:var(--awa-header-main-row-h,56px)!important;box-sizing:border-box!important}'
            . $searchBlockContent . '{'
            . 'padding:8px!important;padding-block:8px!important;box-sizing:border-box!important;'
            . 'overflow:visible!important;height:auto!important;min-height:60px!important}'
            . $root . ' .awa-site-header .awa-header-search-col :is(.block-search,form#search_mini_form){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'box-sizing:border-box!important}'
            . $promo . '{padding:8px!important;box-sizing:border-box!important}'
            . $promoInner . '{padding:8px!important;box-sizing:border-box!important}'
            . $navBar . '{padding:8px!important;box-sizing:border-box!important}'
            . $navContainer . '{padding:8px!important;box-sizing:border-box!important}'
            . $navItem . '{padding:8px!important;box-sizing:border-box!important}'
            . $mainHeader . '{padding:8px!important;box-sizing:border-box!important}'
            . '@media(min-width:768px){' . $mainRow . '{padding:12px 8px!important;padding-block:12px!important;box-sizing:border-box!important}}'
            . $root . ' .block-minicart .awa-minicart-empty__hint{'
            . 'padding:8px!important;font-size:12px!important;line-height:1.45!important;box-sizing:border-box!important}'
            . $root . ' :is(.page_footer,.page-footer) #footer.footer-container .vela-content.velaFooterMenu{'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $kbd . '{'
            . 'padding-block:8px!important;padding-inline:6px!important;box-sizing:border-box!important;'
            . 'color:var(--awa-text-primary,#333)!important;'
            . 'background:var(--awa-bg-muted,#f3f4f6)!important;'
            . 'border:1px solid var(--awa-border,#e5e7eb)!important}'
            . $cartHeaderShell . '{'
            . 'height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'padding:0!important;padding-block:0!important;padding-inline:0!important;'
            . 'margin:0!important;box-sizing:border-box!important;overflow:visible!important}'
            . $cartHeaderContent . '{'
            . 'height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'padding:0 16px!important;box-sizing:border-box!important}'
            . $cartPromo . '{'
            . 'height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'padding:0!important;padding-block:0!important;padding-inline:0!important;'
            . 'margin:0!important;box-sizing:border-box!important}'
            . $cartPromoInner . '{'
            . 'height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'padding:0 52px 0 16px!important;box-sizing:border-box!important}'
            . $cartPromoClose . '{'
            . 'top:0!important;right:0!important;bottom:0!important;left:auto!important;'
            . 'height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'padding:0!important;margin:0!important;transform:none!important;box-sizing:border-box!important}'
            . $cartSticky . '{'
            . 'padding:0!important;padding-block:0!important;padding-inline:0!important;'
            . 'box-sizing:border-box!important;overflow:visible!important}'
            . $cartStickyInner . '{'
            . 'padding:8px 0!important;padding-block:8px!important;padding-inline:0!important;'
            . 'box-sizing:border-box!important}'
            . $cartNav . '{padding:0!important;padding-block:0!important;padding-inline:0!important;box-sizing:border-box!important}'
            . $cartNavContainer . '{padding:0 16px!important;box-sizing:border-box!important}'
            . $cartNavInner . '{padding:0!important;box-sizing:border-box!important}'
            . $cartStrongSticky . '{'
            . 'padding:0!important;padding-block:0!important;padding-inline:0!important;'
            . 'box-sizing:border-box!important;overflow:visible!important}'
            . $cartStrongSticky . ' :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,'
            . '.awa-main-header__inner,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'padding:8px 0!important;padding-block:8px!important;padding-inline:0!important;box-sizing:border-box!important}'
            . $cartStrongClose . '{'
            . 'top:0!important;right:0!important;bottom:0!important;left:auto!important;'
            . 'height:40px!important;min-height:40px!important;max-height:40px!important;'
            . 'padding:0!important;margin:0!important;transform:none!important;box-sizing:border-box!important}';
    }

    /**
     * Home header rail — vence commerce-padding 8px aninhados; logo/nav no eixo 16px.
     */
    public static function homeHeaderRailTerminalRules(): string
    {
        $home = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)';
        $shell = $home . ' .page-wrapper .awa-site-header';
        $zeroChain = $shell . ' :is('
            . '.header-wrapper-sticky:not(.is-sticky),'
            . '.header.awa-main-header,'
            . '.header_main.awa-main-header-inner-wrap,'
            . '.header-main,'
            . '.header_main,'
            . '.header-main>.container,'
            . '.header_main>.container'
            . ')';
        $row = $shell . ' .header-wrapper-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $navBar = $shell . ' :is(.header-control.awa-nav-bar,.header-control.header-nav)';
        $navContainer = $navBar . '>.container';
        $navInner = $shell . ' .awa-nav-bar__inner';
        $stickyHome = $shell . ' .header-wrapper-sticky.is-sticky '
            . ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap)';
        $stickyInner = $shell . ' .header-wrapper-sticky.is-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row],.awa-main-header__inner)';

        return $zeroChain . '{'
            . 'padding:0!important;padding-block:0!important;padding-inline:0!important;'
            . 'margin-inline:0!important;box-sizing:border-box!important}'
            . $shell . ' :is(.header-main>.container,.header_main>.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:min(100%,1280px)!important;padding-inline:0!important;width:100%!important}'
            . $stickyHome . '{'
            . 'padding:0!important;padding-block:0!important;padding-inline:0!important;'
            . 'box-sizing:border-box!important}'
            . $stickyInner . '{padding-inline:16px!important;box-sizing:border-box!important}'
            . $navBar . '{padding:0!important;padding-inline:0!important;box-sizing:border-box!important}'
            . $navContainer . '{'
            . 'padding:0!important;padding-inline:0!important;'
            . 'max-width:1280px!important;width:100%!important;margin-inline:auto!important;'
            . 'box-sizing:border-box!important}'
            . $navInner . '{'
            . 'padding-inline:16px!important;max-width:1280px!important;width:100%!important;'
            . 'margin-inline:auto!important;box-sizing:border-box!important}'
            . '@media(max-width:767px){'
            . $row . '{padding-inline:16px!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-brand-cell{justify-self:start!important;max-width:none!important}'
            . $shell . ' .awa-header-brand-cell :is(.logo,.logo a){justify-content:flex-start!important}'
            . '}'
            . '@media(min-width:992px){'
            . $row . '{max-width:100%!important;width:100%!important;margin-inline:auto!important;'
            . 'padding:0 16px!important;padding-inline:16px!important;box-sizing:border-box!important}}'
            . '@media(min-width:768px) and (max-width:991px){'
            . $row . '{padding-inline:16px!important;max-width:100%!important;margin-inline:auto!important}}'
            . $shell . ' .header-wrapper-sticky.is-sticky{'
            . 'box-sizing:border-box!important;'
            . 'padding-block-start:4px!important;'
            . 'padding-inline:max(16px,calc((100% - min(100%,1280px))/2))!important}';
    }

    public static function homeImpeccablePolishTerminalRules(): string
    {
        $home = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)';
        $wrap = $home . ' .page-wrapper';

        return $wrap . ' .awa-site-header .header-wrapper-sticky.is-sticky{'
            . 'box-sizing:border-box!important;'
            . 'padding-block-start:4px!important;'
            . 'padding-inline:max(16px,calc((100% - min(100%,1280px))/2))!important}'
            . $wrap . ' .awa-site-header :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap){'
            . 'overflow:visible!important}'
            . $wrap . ' .awa-site-header .header-wrapper-sticky.is-sticky '
            . ':is(.awa-main-header__inner,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'padding-inline:16px!important}'
            . $wrap . ' .content-top-home>.ayo-home5-wrapper.ayo-home5-wrapper--template-driven{'
            . 'padding-inline:0!important}'
            . $wrap . ' .content-top-home :is(.awa-hero-b2b-cta,.awa-home-pricing-notice,'
            . '.ayo-home5-wrapper--template-driven>:is(.top-home-content,.awa-home-section,.awa-carousel-section,#awa-home-niche-shelves)){'
            . 'padding-inline:16px!important;box-sizing:border-box!important}'
            . $wrap . ' .awa-site-header .header-control.awa-nav-bar .awa-nav-bar__inner{'
            . 'padding-block:8px!important;padding-inline:16px!important}'
            . $wrap . ' .content-top-home>.top-home-content--above-fold>.banner-slider.banner-slider2{'
            . 'overflow:visible!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .awa-footer-newsletter{padding-inline:0!important}';
    }

    /**
     * Home shell center — content-top-home + header container no eixo 1280 (viewports >1280px).
     */
    public static function homeShellCenterTerminalRules(): string
    {
        $home = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)';
        $wrap = $home . ' .page-wrapper';

        return $wrap . ' .content-top-home{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:min(100%,1280px)!important;padding-inline:0!important;width:100%!important}'
            . $wrap . ' .awa-site-header :is(.header-main>.container,.header_main>.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:min(100%,1280px)!important;padding-inline:0!important;width:100%!important}'
            . $wrap . ' .awa-site-header '
            . ':is(.awa-main-header__inner,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'margin-inline:auto!important;max-width:100%!important;width:100%!important}';
    }

    /**
     * Seletor PLP com especificidade máxima (vence headerLayoutAlignRules padding-block:0).
     */
    private static function plpImpeccableLockRoot(): string
    {
        return 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)';
    }

    /**
     * PDP-only root — regras que não devem vazar para PLP/busca.
     */
    private static function pdpImpeccableLockRoot(): string
    {
        return 'html body#html-body#html-body#html-body#html-body#html-body.catalog-product-view';
    }

    /**
     * SR/hidden copy — display:none evita overflow Impeccable em caixa 1×1 com texto longo.
     */
    private static function plpImpeccableSrOnlyHideRules(string $wrap): string
    {
        $hide = $wrap . ' :is('
            . '.pages :is(span.label,.label,strong.label.pages-label,#paging-label-bottom),'
            . '.toolbar .modes-mode>span,'
            . 'button.action.search .awa-sr-only,'
            . 'a.action.showcart .awa-sr-only,'
            . '.minicart-wrapper .action.showcart .awa-sr-only,'
            . '.awa-category-carousel__count.awa-sr-only,'
            . ':is(.page_footer,.page-footer) label.visually-hidden,'
            . '.awa-whatsapp-float__label'
            . '){display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'max-width:0!important;max-height:0!important;overflow:hidden!important;'
            . 'position:absolute!important;margin:0!important;padding:0!important;border:0!important;'
            . 'clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;'
            . 'font-size:0!important;line-height:0!important;background:transparent!important;color:transparent!important}';

        $shell = $wrap . ' .awa-site-header';

        return $hide
            . $wrap . ' .awa-sr-only{background:transparent!important;color:transparent!important}'
            . $shell . ' .awa-search-helper-copy{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'overflow:hidden!important;position:absolute!important;margin:0!important;padding:0!important}'
            . $shell . ' button.action.search{overflow:visible!important;position:relative!important}'
            . $shell . ' a.action.showcart{overflow:visible!important;position:relative!important}';
    }

    /**
     * Mobile catalog/checkout — busca não pode escapar do sticky (vence commerce-padding + PLP impeccable).
     */
    private static function catalogMobileHeaderClampRules(string $pageRoot): string
    {
        $wrap = $pageRoot . ' .page-wrapper';
        $sticky = $wrap . ' .awa-site-header:not(.awa-header-condensed) .header-wrapper-sticky';
        $chain = $sticky . ' :is('
            . '.header.awa-main-header,'
            . '.header_main.awa-main-header-inner-wrap,'
            . '.header-main,'
            . '.header_main,'
            . '.header-main>.container,'
            . '.header_main>.container'
            . ')';
        $child = $sticky . ' .header_main.awa-main-header-inner-wrap>:is(.header-main,.header_main)';
        $searchContent = $wrap . ' .awa-site-header '
            . ':is(.block.block-search>.block-content,.awa-header-search-col .block-content)';

        return '@media(max-width:767px){'
            . $sticky . '{'
            . 'box-sizing:border-box!important;height:96px!important;max-height:96px!important;'
            . 'min-height:96px!important;padding:0!important;margin:0!important;overflow:hidden!important}'
            . $chain . '{'
            . 'box-sizing:border-box!important;height:96px!important;max-height:96px!important;'
            . 'min-height:96px!important;margin:0!important;padding:0!important;overflow:hidden!important}'
            . $child . '{'
            . 'margin:0!important;padding:0!important;height:96px!important;max-height:96px!important;'
            . 'min-height:96px!important;overflow:hidden!important}'
            . $searchContent . '{'
            . 'padding:0!important;min-height:0!important;height:auto!important;'
            . 'max-height:96px!important;overflow:hidden!important;box-sizing:border-box!important}'
            . '}';
    }

    /**
     * Padding shell header — path com .header-wrapper-sticky vence layout-align padding-block:0.
     */
    private static function plpImpeccableHeaderPaddingRules(string $wrap): string
    {
        $shell = $wrap . ' .awa-site-header';
        $sticky = $shell . ' .header-wrapper-sticky';
        $header = $sticky . ' .header.awa-main-header';
        $headerMain = $sticky . ' .header_main.awa-main-header-inner-wrap';
        $inner = $sticky . ' :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $searchContent = $shell . ' :is(.block.block-search>.block-content,.awa-header-search-col .block-content)';

        return $header . '{'
            . 'padding:8px!important;padding-block:8px!important;padding-inline:8px!important;'
            . 'box-sizing:border-box!important;overflow:visible!important;'
            . 'height:auto!important;max-height:none!important;min-height:0!important}'
            . $headerMain . '{'
            . 'padding:0 8px!important;padding-block:0!important;padding-inline:8px!important;'
            . 'box-sizing:border-box!important;overflow:visible!important;'
            . 'height:auto!important;max-height:none!important;min-height:0!important}'
            . '@media(min-width:768px){'
            . $headerMain . '>:is(.header-main,.header_main){'
            . 'margin:8px 0!important;box-sizing:border-box!important;'
            . 'height:auto!important;max-height:none!important;'
            . 'min-height:var(--awa-header-main-row-h,56px)!important}}'
            . $inner . '{'
            . 'height:auto!important;max-height:none!important;'
            . 'min-height:var(--awa-header-main-row-h,56px)!important;'
            . 'padding-block:12px!important;box-sizing:border-box!important}'
            . $searchContent . '{'
            . 'padding:8px!important;padding-block:8px!important;box-sizing:border-box!important;'
            . 'overflow:visible!important;height:auto!important;min-height:60px!important}'
            . $shell . ' .awa-header-search-col :is(.block-search,form#search_mini_form){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'box-sizing:border-box!important}'
            . '@media(min-width:992px){'
            . $header . '{display:block!important;align-items:stretch!important;'
            . 'height:auto!important;min-height:0!important;max-height:none!important;'
            . 'overflow:visible!important;padding-block:8px!important;margin-block:0!important}'
            . $headerMain . '{height:auto!important;max-height:none!important;overflow:visible!important;padding:0!important}'
            . $inner . '{height:auto!important;max-height:none!important;'
            . 'min-height:var(--awa-header-main-row-h,56px)!important;padding-block:12px!important}'
            . '}';
    }

    /**
     * PLP toolbar — vence --m-text-sm (0.8125rem × html 10px = 8.125px).
     */
    private static function plpImpeccableToolbarTypeRules(string $wrap): string
    {
        return $wrap . ' .toolbar.toolbar-products{'
            . 'font-size:13px!important;line-height:1.35!important}'
            . $wrap . ' .toolbar.toolbar-products :is(.toolbar-sorter,.sorter,.field.limiter,.modes,.toolbar-amount){'
            . 'font-size:13px!important;line-height:1.35!important}'
            . $wrap . ' .toolbar.toolbar-products :is(.modes .modes-mode,a.modes-mode,strong.modes-mode,'
            . '.mode-grid,.mode-list){align-items:center!important;box-sizing:border-box!important;'
            . 'display:inline-flex!important;height:44px!important;justify-content:center!important;'
            . 'line-height:1!important;margin:0!important;max-height:44px!important;max-width:44px!important;'
            . 'min-height:44px!important;min-width:44px!important;padding:0!important;width:44px!important}';
    }

    /**
     * PLP head critical — 1º paint (Impeccable scan antes do align-grid/distill async).
     */
    public static function plpImpeccableHeadCriticalCss(): string
    {
        $root = self::plpImpeccableLockRoot();
        $wrap = $root . ' .page-wrapper';
        $shell = $wrap . ' .awa-site-header';

        return $root . ','
            . $wrap . '{font-family:"Source Sans 3",system-ui,-apple-system,sans-serif!important}'
            . $wrap . '{'
            . '--awa-plp-card-radius:8px;--awa-plp-chrome-radius:8px;--awa-modern-card-radius:8px;'
            . 'overflow-x:clip!important;overflow-y:visible!important;'
            . 'font-family:"Source Sans 3",system-ui,-apple-system,sans-serif!important}'
            . $wrap . ' :is(.page_footer,.page-footer,.page-footer *){'
            . 'font-family:"Source Sans 3",system-ui,-apple-system,sans-serif!important}'
            . self::plpImpeccableHeaderPaddingRules($wrap)
            . self::catalogMobileHeaderClampRules(self::plpImpeccableLockRoot())
            . $wrap . ' .wrapper.grid.products-grid .item-product{border-radius:8px!important}'
            . $wrap . ' :is('
            . '.shop-tab-select .toolbar.toolbar-products:not(.toolbar-products--bottom-slim),'
            . '#layered-ajax-filter-block.block.filter,.awa-plp-b2b-gate-banner){border-radius:8px!important}'
            . $shell . ' :is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.header-control.header-nav){'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $shell . ' :is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar)>.container{'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' :is('
            . '#awa-b2b-promo-bar,.awa-b2b-promo-bar,.awa-b2b-promo-bar__inner,'
            . '.toolbar-sorter.sorter,.field.limiter>.control,'
            . '#layered-ajax-filter-block,.block.filter,.category-view-move,'
            . '.page-main>.columns,.columns.layout,.columns.layout.layout-2-col){'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .category-view-move{overflow:visible!important;height:auto!important;'
            . 'max-height:none!important;margin-block-end:12px!important}'
            . $wrap . ' .awa-category-hero--has-image .awa-category-hero__content{'
            . 'background:#fff!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important}'
            . $wrap . ' h1.awa-category-hero__title,'
            . $wrap . ' .awa-category-hero--has-image h1.awa-category-hero__title{'
            . 'color:#333333!important;text-shadow:none!important}'
            . $wrap . ' .awa-category-hero--has-image :is(.awa-category-hero__title,.awa-category-hero__count){'
            . 'color:#333!important;text-shadow:none!important}'
            . $wrap . ' .awa-category-hero--has-image .awa-category-hero__count{color:#666!important}'
            . self::plpImpeccableSrOnlyHideRules($wrap)
            . $wrap . ' :is('
            . '#search_mini_form,#search_mini_form .field.search,'
            . '.mst-searchautocomplete__autocomplete,#awa-b2b-promo-bar,'
            . '.awa-b2b-promo-bar__inner,.block-minicart,.block-minicart.ui-dialog-content){'
            . 'overflow:visible!important}'
            . '@media(min-width:992px){' . $wrap . ' .shop-tab-select '
            . '.toolbar.toolbar-products:not(.toolbar-products--bottom-slim){'
            . 'min-height:52px!important;max-height:56px!important;padding:8px!important;'
            . 'box-sizing:border-box!important;align-items:center!important}}'
            . '/* AWA layout audit: B2B promo CTA touch target */'
            . $wrap . ' .awa-site-header :is(.awa-b2b-promo-bar,#awa-b2b-promo-bar) :is(a.awa-b2b-promo-bar__cta,.awa-b2b-promo-bar__cta){'
            . 'display:inline-flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:auto!important;min-width:44px!important;height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;padding:0 12px!important;margin-block:0!important;'
            . 'box-sizing:border-box!important;line-height:1.15!important;white-space:nowrap!important}'
            . self::plpImpeccableToolbarTypeRules($wrap)
            . $wrap . ' .toolbar.toolbar-products--bottom-slim .pages .items.pages-items .item a,'
            . $wrap . ' .toolbar.toolbar-products--bottom-slim :is(.pages .item a,.pages .item.current strong){'
            . 'min-width:44px!important;min-height:44px!important;display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important;box-sizing:border-box!important}'
            . self::plpImpeccablePass2Rules();
    }

    /**
     * PDP head critical — attr-product, gallery, carousel, colunas (1º paint antes do async pai).
     */
    public static function pdpImpeccableHeadCriticalCss(): string
    {
        $root = self::pdpImpeccableLockRoot();
        $wrap = $root . ' .page-wrapper';
        $shell = $wrap . ' .awa-site-header';

        return $root . ','
            . $wrap . '{font-family:"Source Sans 3",system-ui,-apple-system,sans-serif!important}'
            . self::plpImpeccableHeaderPaddingRules($wrap)
            . self::catalogMobileHeaderClampRules(self::pdpImpeccableLockRoot())
            . self::plpImpeccableSrOnlyHideRules($wrap)
            . $shell . ' .header-content{padding-block:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .product-info-main .attr-product{'
            . 'border:0!important;box-shadow:none!important;background:transparent!important;'
            . 'padding:0!important;overflow:visible!important;box-sizing:border-box!important}'
            . $wrap . ' :is(.product.attribute,.product-info-stock-sku .product.attribute){'
            . 'padding-block:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .product-info-price .price-label{padding:4px 8px!important;box-sizing:border-box!important}'
            . $wrap . ' :is(.columns,.column.main){padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' :is(.product-view,.column.main){overflow:visible!important}'
            . $wrap . ' .product.media{'
            . 'box-sizing:border-box!important;isolation:isolate!important;max-width:100%!important;'
            . 'min-width:0!important;overflow:hidden!important;position:relative!important;z-index:1!important}'
            . $wrap . ' .product-info-main{'
            . 'isolation:isolate!important;position:relative!important;z-index:2!important;min-width:0!important}'
            . $wrap . ' .product-info-main .page-title-wrapper :is(h1.page-title,.page-title,.page-title .base){'
            . 'font-size:clamp(22px,1.25rem + .6vw,30px)!important;font-weight:700!important;'
            . 'line-height:1.2!important;margin-block:0 12px!important}'
            . $wrap . ' .main-detail>.row>.col-md-6:first-child{'
            . 'min-width:0!important;overflow:hidden!important}'
            . $wrap . ' .gallery-placeholder{'
            . 'padding:8px!important;box-shadow:none!important;overflow:hidden!important;'
            . 'border:1px solid var(--awa-border,#e5e7eb)!important;'
            . 'background:var(--awa-surface,#fff)!important;box-sizing:border-box!important}'
            . $wrap . ' .product.media :is(.gallery-placeholder,.fotorama-item,.fotorama,.fotorama__wrap,'
            . '.fotorama__stage,.fotorama__stage__shaft,.fotorama__stage__frame){overflow:hidden!important}'
            . $wrap . ' :is(.awa-carousel__viewport,[class*="awa-carousel__viewport"]){'
            . 'padding-inline:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .item-product.awa-carousel-card-slot{'
            . 'padding-block:8px!important;overflow:hidden!important;box-sizing:border-box!important}'
            . $wrap . ' .item-product.awa-carousel-card-slot .product-thumb{overflow:clip!important;padding:8px!important}'
            . $wrap . ' :is(#menu\\.vertical\\.extra,.awa-vertical-extra){padding-block-start:8px!important}'
            . '@media(min-width:992px){' . $shell . ' .header-content:has(.awa-b2b-promo-bar){'
            . 'align-items:stretch!important;width:100%!important;max-width:min(100%,1280px)!important}'
            . $shell . ' :is(.top-header,.awa-b2b-promo-bar){width:100%!important;max-width:100%!important}'
            . $shell . ' .awa-b2b-promo-bar__inner{width:100%!important;max-width:min(100%,1280px)!important}}'
            . $shell . ' .awa-header-search-col :is(.block-content,.block-search){'
            . 'padding:0!important;margin:0!important;box-sizing:border-box!important}'
            . $shell . ' :is(#search_mini_form,.mst-searchautocomplete__autocomplete,button.action.search,'
            . '#awa-b2b-promo-bar,.awa-b2b-promo-bar__inner){overflow:visible!important}'
            . self::pdpImpeccablePass2Rules();
    }

    /**
     * PDP Impeccable pass 2 — overflow allowlist, ruído 10px inline, Inter→Source Sans 3, fotorama anim.
     */
    public static function pdpImpeccablePass2Rules(): string
    {
        $pdp = self::pdpImpeccableLockRoot();
        $wrap = $pdp . ' .page-wrapper';

        $structural = ':is('
            . '#awa-pdp-terminal-lock-inline,#awa-plp-terminal-lock-inline,#awa-critical-inline-site,'
            . '#awa-cls-nav-fix,#awa-bugfix-terminal-inline,#awa-cookie-consent-critical,head>style,head>noscript,title'
            . '){font-size:0!important;line-height:0!important}';

        $overflow = $pdp . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . ' :is('
            . '#awa-b2b-promo-bar,.awa-b2b-promo-bar,.awa-b2b-promo-bar__inner,'
            . '#search_mini_form,.mst-searchautocomplete__autocomplete,'
            . 'ul.togge-menu.list-category-dropdown,[id^="awa-vertical-menu-"],'
            . '[id^="submenu-menu-"],.navigation__submenu,.navigation__inner-list,'
            . '.columns,.column.main,.product-view,'
            . '.attr-product,.item-product.awa-carousel-card-slot,.content-item-product.awa-product-card,'
            . '.product-thumb,footer.page-footer,.page_footer,#footer,.footer-container,'
            . 'a.awa-whatsapp-float,.awa-header-categories.menu_left_home1'
            . '){overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}';

        $galleryClip = $wrap . ' .product.media,'
            . $wrap . ' .product.media :is(.gallery-placeholder,.fotorama-item,.fotorama,.fotorama__wrap,'
            . '.fotorama__stage,.fotorama__stage__shaft,.fotorama__stage__frame,'
            . '.fotorama__nav-wrap,.fotorama__nav,.fotorama__nav__shaft,.fotorama__nav__frame){'
            . 'overflow:hidden!important}'
            . $wrap . ' .gallery-placeholder{overflow:hidden!important}'
            . $wrap . ' .main-detail>.row>.col-md-6:first-child{overflow:hidden!important}';

        $fotoramaAnim = $wrap . ' :is(.fotorama__stage__shaft,.fotorama__nav__shaft,.fotorama__thumb-border){'
            . 'transition-property:opacity,transform!important}';

        $type = $wrap . ' .navigation.verticalmenu.side-verticalmenu{'
            . '--vm-font:"Source Sans 3",system-ui,-apple-system,sans-serif!important}'
            . $wrap . ' :is('
            . '.navigation.verticalmenu,.our_categories.title-category-dropdown,'
            . '.awa-vmenu-trigger-text,.navigation__link,.navigation__label,'
            . '.navigation__inner-link,ul.togge-menu.list-category-dropdown,'
            . '[id^="submenu-menu-"] *'
            . '){font-family:"Source Sans 3",system-ui,-apple-system,sans-serif!important}';

        return $structural . $overflow . $galleryClip . $fotoramaAnim . $type;
    }

    /**
     * Checkout-only root — carrinho, OPC e sucesso (sr-only, overflow, touch targets).
     */
    private static function checkoutImpeccableLockRoot(): string
    {
        return 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.checkout-cart-index,.checkout-index-index,.rokanthemes-onepagecheckout,'
            . '.onepagecheckout-index-index,.checkout-onepage-success)';
    }

    /**
     * Home-only root — regras Impeccable head critical (sr-only, overflow chrome).
     */
    private static function homeImpeccableLockRoot(): string
    {
        return 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)';
    }

    /**
     * Checkout head critical — sr-only, overflow allowlist, chips/OPC 44px (1º paint).
     */
    public static function checkoutImpeccableHeadCriticalCss(): string
    {
        $root = self::checkoutImpeccableLockRoot();
        $wrap = $root . ' .page-wrapper';
        $shell = $wrap . ' .awa-site-header';
        $opc = ':is(body.checkout-index-index,body.rokanthemes-onepagecheckout,.onepagecheckout-index-index)';

        return self::plpImpeccableSrOnlyHideRules($wrap)
            . self::catalogMobileHeaderClampRules(self::checkoutImpeccableLockRoot())
            . $root . ','
            . $wrap . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . ' :is('
            . '#search_mini_form,.mst-searchautocomplete__autocomplete,'
            . '#awa-b2b-promo-bar,.awa-b2b-promo-bar,.awa-b2b-promo-bar__inner,'
            . '.cart-container,.cart-summary,.checkout-container,.opc-wrapper,#checkout,'
            . '.block.block-minicart,.block-minicart.ui-dialog-content,.minicart-wrapper .mage-dropdown-dialog,'
            . 'ul.togge-menu.list-category-dropdown,.navigation__submenu,.navigation__inner-list,'
            . 'footer.page-footer,.page_footer,#footer,.footer-container,a.awa-whatsapp-float'
            . '){overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . ' section.awa-footer-trust-bar{padding-block:14px 16px!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .footer-bottom .footer-bottom-inner{'
            . 'padding-inline:max(16px,env(safe-area-inset-left),env(safe-area-inset-right))!important}'
            . $shell . ' :is(#search_mini_form,button.action.search,a.action.showcart){'
            . 'overflow:visible!important;position:relative!important}'
            . $wrap . ' :is(.awa-cart-empty__category-chip,.awa-order-success__category-chip){'
            . 'min-height:44px!important;padding-block:10px!important;box-sizing:border-box!important;'
            . 'display:inline-flex!important;align-items:center!important}'
            . $opc . ' .opc-progress-bar-item>span::before,'
            . $opc . ' .opc-progress-bar-item>span:before{'
            . 'width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;'
            . 'line-height:40px!important;box-sizing:border-box!important}'
            . $opc . ' .opc-wrapper .step-title{font-weight:700!important}'
            . $shell . ' .awa-header-account-prompt :is(a,.awa-header-account-prompt__link){'
            . 'min-height:44px!important;display:inline-flex!important;align-items:center!important;'
            . 'padding-block:8px!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-account-prompt__icon{'
            . 'width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;'
            . 'flex:0 0 44px!important}'
            . '/* AWA: vence o sr-only legado do carrinho vazio; evita child posicionado clipado. */'
            . $wrap . ':has(.awa-cart-empty,.cart-empty) .page-title-wrapper{'
            . 'position:static!important;width:100%!important;height:auto!important;padding:0!important;'
            . 'margin:0 0 var(--awa-space-3,12px)!important;overflow:visible!important;'
            . 'clip:auto!important;clip-path:none!important;white-space:normal!important;border:0!important}'
            . $wrap . ':has(.awa-cart-empty,.cart-empty) .page-title-wrapper :is(h1,.page-title,.page-title .base){'
            . 'position:static!important;display:block!important;width:auto!important;height:auto!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important;clip:auto!important;'
            . 'clip-path:none!important;white-space:normal!important;border:0!important;'
            . 'font-size:var(--awa-font-3xl,28px)!important;line-height:1.2!important}'
            . self::checkoutSearchImpeccableDistillRules()
            . self::checkoutCartMobileMinicartTerminalRules()
            . self::checkoutOpenModalTerminalRules()
            . self::checkoutAgreementsModalTerminalRules();
    }

    /**
     * Cart mobile minicart — opened dropdown must escape the 44px header shell.
     */
    private static function checkoutCartMobileMinicartTerminalRules(): string
    {
        $root = 'html body#html-body.checkout-cart-index .page-wrapper';
        $open = $root . ' .minicart-wrapper:is(.is-open,.active,.show)';
        $panel = $open . ' .block-minicart:is(._active,.active,.is-open)';

        return '@media(max-width:767px){'
            . $root . ' .awa-header-minicart,' . $open . '{overflow:visible!important}'
            . $open . ' .mage-dropdown-dialog{position:fixed!important;inset:0!important;'
            . 'display:block!important;width:100vw!important;height:100dvh!important;'
            . 'overflow:visible!important;z-index:10020!important;transform:none!important}'
            . $panel . '{position:fixed!important;inset:0!important;display:flex!important;'
            . 'flex-direction:column!important;box-sizing:border-box!important;width:100vw!important;'
            . 'max-width:none!important;min-width:0!important;height:100dvh!important;'
            . 'max-height:100dvh!important;margin:0!important;padding:16px!important;'
            . 'overflow:auto!important;border:0!important;border-radius:0!important;'
            . 'background:var(--awa-bg-surface,Canvas)!important;box-shadow:none!important;'
            . 'z-index:10021!important;transform:none!important}'
            . $open . ' #minicart-content-wrapper{display:flex!important;flex-direction:column!important;'
            . 'width:100%!important;min-height:0!important}'
            . '}';
    }

    /**
     * Checkout shipping address modal — keeps the open modal outside document flow.
     */
    private static function checkoutOpenModalTerminalRules(): string
    {
        $root = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.checkout-index-index,.rokanthemes-onepagecheckout,.onepagecheckout-index-index)';
        $modal = $root . ' .modal-popup.new-shipping-address-modal.modal-slide._inner-scroll._show';
        $wrap = $modal . ' .modal-inner-wrap';
        $close = $modal . ' .modal-header .action-close';
        $overlay = $root . ' .modals-overlay';

        return '/* AWA checkout modal terminal v16 — exact shipping address fixed in viewport */'
            . $modal . '{position:fixed!important;inset:0!important;z-index:10010!important;'
            . 'display:flex!important;align-items:center!important;justify-content:center!important;'
            . 'width:100%!important;height:100vh!important;height:100dvh!important;min-height:100vh!important;'
            . 'padding:max(16px,env(safe-area-inset-top,0px)) 16px max(16px,env(safe-area-inset-bottom,0px))!important;'
            . 'box-sizing:border-box!important;overflow:auto!important;transform:none!important;will-change:auto!important}'
            . $wrap . '{position:relative!important;inset:auto!important;top:auto!important;right:auto!important;'
            . 'bottom:auto!important;left:auto!important;width:min(720px,calc(100vw - 32px))!important;'
            . 'max-width:min(720px,calc(100vw - 32px))!important;max-height:calc(100vh - 32px)!important;'
            . 'max-height:calc(100dvh - 32px)!important;margin:auto!important;overflow:auto!important;'
            . 'transform:none!important;box-sizing:border-box!important}'
            . $close . '{position:relative!important;width:44px!important;height:44px!important;'
            . 'min-width:44px!important;min-height:44px!important;display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important;margin:0!important;'
            . 'overflow:visible!important;flex-shrink:0!important}'
            . $overlay . '{position:fixed!important;inset:0!important;width:100%!important;'
            . 'height:100vh!important;height:100dvh!important;z-index:10000!important}'
            . '@media (max-width:767px){'
            . $modal . '{align-items:flex-start!important;'
            . 'padding:max(10px,env(safe-area-inset-top,0px)) 10px max(10px,env(safe-area-inset-bottom,0px))!important}'
            . $wrap . '{width:100%!important;max-width:100%!important;'
            . 'max-height:calc(100vh - 20px)!important;max-height:calc(100dvh - 20px)!important;'
            . 'margin-block:auto!important}}';
    }

    /**
     * Checkout agreements-modal — vence consolidated .modal-inner-wrap overflow:hidden (1º paint).
     */
    private static function checkoutAgreementsModalTerminalRules(): string
    {
        $root = self::checkoutImpeccableLockRoot();
        $modal = $root
            . ' .modal-popup.agreements-modal._inner-scroll';
        $wrap = $modal . ' .modal-inner-wrap';
        $header = $modal . ' .modal-header';
        $content = $modal . ' .modal-content';
        $close = $header . ' .action-close';
        $closeSpan = $close . '>span';

            return '/* AWA agreements-modal terminal v10 — scroll content, close 44px visível */'
                . $modal . ':not(._show){display:none!important;visibility:hidden!important;opacity:0!important;'
                . 'pointer-events:none!important;position:absolute!important;inset:auto!important;'
                . 'width:0!important;height:0!important;min-width:0!important;min-height:0!important;'
                . 'margin:0!important;overflow:hidden!important}'
                . $wrap . ','
                . $header . '{overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . $content . '{overflow-y:auto!important;overscroll-behavior:contain;max-height:min(70vh,520px)!important}'
            . $close . '{position:relative!important;width:44px!important;height:44px!important;'
            . 'min-width:44px!important;min-height:44px!important;padding:0!important;margin:0!important;'
            . 'overflow:visible!important;display:inline-flex!important;align-items:center!important;'
            . 'justify-content:center!important;box-sizing:border-box!important;flex-shrink:0!important}'
            . $closeSpan . '{position:absolute!important;width:1px!important;height:1px!important;'
            . 'padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;'
            . 'clip-path:inset(50%)!important;white-space:nowrap!important;border:0!important;pointer-events:none!important}';
    }

    /**
     * Carrinho/checkout — busca distill (nested cards + autocomplete shadow-only).
     */
    private static function checkoutSearchImpeccableDistillRules(): string
    {
        $root = self::checkoutImpeccableLockRoot();
        $shell = $root . ' .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-header-search-col';
        $ac = $root . ' .page-wrapper :is(#search_autocomplete,.search-autocomplete,.searchsuite-autocomplete,.mst-searchautocomplete__autocomplete)';

        return $ac . '{border:0!important;box-shadow:0 4px 16px rgb(15 23 42/12%)!important}'
            . $shell . ' :is(>.block-search,>.block-search>.block-content){'
            . 'background:transparent!important;border:0!important;border-radius:0!important;'
            . 'box-shadow:none!important;outline:none!important;overflow:visible!important}'
            . $shell . ' form#search_mini_form{'
            . 'background:var(--awa-bg,#fff)!important;'
            . 'border:1px solid color-mix(in srgb,var(--awa-primary,#b73337) 24%,var(--awa-border,#e5e7eb))!important;'
            . 'box-shadow:none!important;outline:none!important}'
            . $shell . ' form#search_mini_form:focus-within{'
            . 'border-color:var(--awa-primary,#b73337)!important;box-shadow:none!important;outline:none!important}';
    }

    /**
     * Home head critical — sr-only display:none + overflow allowlist (1º paint).
     */
    public static function homeImpeccableHeadCriticalCss(): string
    {
        $root = self::homeImpeccableLockRoot();
        $wrap = $root . ' .page-wrapper';
        $shell = $wrap . ' .awa-site-header';

        return self::homeHeaderRailTerminalRules()
            . self::plpImpeccableSrOnlyHideRules($wrap)
            . $shell . ' .header-content{padding-block:8px!important;box-sizing:border-box!important}'
            . $root . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . ' :is('
            . '#search_mini_form,.mst-searchautocomplete__autocomplete,'
            . '#awa-b2b-promo-bar,.awa-b2b-promo-bar,.awa-b2b-promo-bar__inner,'
            . '.columns,.column.main,#maincontent,footer.page-footer,.page_footer,'
            . '#footer,.footer-container,a.awa-whatsapp-float,'
            . '.awa-carousel-section,.top-home-content.awa-home-section'
            . '){overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . $shell . ' :is(#search_mini_form,button.action.search){overflow:visible!important;position:relative!important}'
            /* Home CLS: autocomplete e overlay, nao conteudo in-flow. */
            . $shell . ' form#search_mini_form .field.search .control{position:relative!important;overflow:visible!important}'
            . $shell . ' form#search_mini_form #search_autocomplete.search-autocomplete{'
            . 'position:absolute!important;top:calc(100% + 8px)!important;left:0!important;right:0!important;'
            . 'z-index:60!important;margin:0!important;min-height:0!important;height:auto!important;display:none!important}'
            . $shell . ' form#search_mini_form #search_autocomplete.search-autocomplete:not(:empty){display:block!important}'
            . $shell . ' a.action.showcart{overflow:visible!important;position:relative!important}';
    }

    /**
     * PLP terminal lock — vence postaudit (#fff hero), padding-inline:0 em columns e sr-only overflow.
     */
    public static function plpImpeccableTerminalLockRules(): string
    {
        $plp = self::plpImpeccableLockRoot();
        $wrap = $plp . ' .page-wrapper';

        return self::plpImpeccableHeaderPaddingRules($wrap)
            . self::catalogMobileHeaderClampRules(self::plpImpeccableLockRoot())
            . self::plpImpeccableSrOnlyHideRules($wrap)
            . $wrap . ' h1.awa-category-hero__title,'
            . $wrap . ' .awa-category-hero--has-image h1.awa-category-hero__title{'
            . 'color:#333333!important;text-shadow:none!important}'
            . $wrap . ' .awa-category-hero--has-image '
            . ':is(.awa-category-hero__title,.awa-category-hero__count,.awa-category-hero__eyebrow,'
            . '.awa-category-hero__content,.awa-category-hero__content *){'
            . 'color:#333!important;text-shadow:none!important}'
            . $wrap . ' .awa-category-hero--has-image .awa-category-hero__count{color:#666!important}'
            . $wrap . ' .awa-category-hero--has-image .awa-category-hero__content{'
            . 'background:#fff!important;background-color:#fff!important;'
            . 'backdrop-filter:none!important;-webkit-backdrop-filter:none!important;'
            . 'border:0!important;box-shadow:none!important;box-sizing:border-box!important}'
            . $wrap . ' .awa-category-hero--has-image .awa-category-hero__content .awa-category-hero__count{'
            . 'color:#666!important}'
            . $wrap . ' :is(.page-main>.columns,.columns.layout,.columns.layout.layout-2-col){'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .category-view-move{padding:8px!important;box-sizing:border-box!important;overflow:visible!important}'
            . $wrap . ' :is(#layered-ajax-filter-block,.block.filter){padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .awa-header-categories.menu_left_home1{padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .wrapper.grid.products-grid .item-product '
            . ':is(.awa-b2b-sku,.awa-b2b-sku__label,.awa-b2b-sku__value){'
            . 'padding:8px!important;font-size:12px!important;line-height:1.35!important;box-sizing:border-box!important}'
            . $wrap . ' .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.header-control.header-nav){'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .awa-site-header '
            . ':is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar) > .container{'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . $wrap . ' .wrapper.grid.products-grid .item-product .product-info{'
            . 'padding:8px!important;box-sizing:border-box!important}'
            . self::plpImpeccableToolbarTypeRules($wrap)
            . self::plpImpeccablePass2Rules();
    }

    /**
     * PLP Impeccable pass 2 — ruído estrutural 10px, overflow allowlist, Inter→Source Sans 3 no menu.
     */
    public static function plpImpeccablePass2Rules(): string
    {
        $plp = self::plpImpeccableLockRoot();
        $wrap = $plp . ' .page-wrapper';
        $shell = $wrap . ' .awa-site-header';

        $structural = ':is('
            . '#awa-plp-terminal-lock-inline,#awa-critical-inline-site,#awa-cls-nav-fix,'
            . '#awa-bugfix-terminal-inline,#awa-cookie-consent-critical,head>style,head>noscript,title'
            . '){font-size:0!important;line-height:0!important}';

        $overflow = $plp . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . '{overflow-x:visible!important;overflow-y:visible!important}'
            . $wrap . ' :is('
            . '#layered-ajax-filter-block,.block.filter,.awa-category-hero,#layered-ajax-list-products,'
            . '.product-content-right,footer.page-footer,.page_footer,#footer,.footer-container,'
            . 'a.awa-whatsapp-float,.awa-header-categories.menu_left_home1,'
            . 'ul.togge-menu.list-category-dropdown,[id^="awa-vertical-menu-"],'
            . '[id^="submenu-menu-"],.navigation__submenu,.navigation__inner-list'
            . '){overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . $shell . ' :is(.header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar){'
            . 'overflow:visible!important}'
            . $wrap . ' .products-grid .item-product,'
            . $wrap . ' .products-grid .item-product .product-thumb,'
            . $wrap . ' .products-grid :is(.product-image-container,.product-image-wrapper){'
            . 'overflow:visible!important;contain:none!important}';

        $type = $wrap . ' .navigation.verticalmenu.side-verticalmenu{'
            . '--vm-font:"Source Sans 3",system-ui,-apple-system,sans-serif!important}'
            . $wrap . ' :is('
            . '.navigation.verticalmenu,.our_categories.title-category-dropdown,'
            . '.awa-vmenu-trigger-text,.navigation__link,.navigation__label,'
            . '.navigation__inner-link,ul.togge-menu.list-category-dropdown,'
            . '[id^="submenu-menu-"] *'
            . '){font-family:"Source Sans 3",system-ui,-apple-system,sans-serif!important}';

        return $structural . $overflow . $type;
    }

    /**
     * Home-only — regras não duplicadas no distill base (a11y, tablet, search polish).
     */
    public static function headerDistillHomeSupplementRules(): string
    {
        return self::headerA11yRules()
            . self::minicartInteractionRules()
            . self::headerStickyShellRules()
            . self::tabletHeaderCompactRules()
            . self::homeSearchApfPolishRules();
    }

    /**
     * Scripts distill — harden + a11y (substitui 2 blocos script separados).
     */
    public static function headerDistillTerminalScripts(): string
    {
        return self::headerHardenScript()
            . self::headerPolishA11yScript()
            . self::headerAlignGridContainerPadScript()
            . self::headerDistillMobileGridScript();
    }

    /**
     * Mobile grid lock via inline !important — vence bundles assíncronos pós-distill (PLP + home).
     */
    public static function headerDistillMobileGridScript(): string
    {
        return '<script id="awa-header-distill-mobile-grid-20260616c">(function(){'
            . '"use strict";'
            . 'function applyLogo(){'
            . 'if(window.innerWidth<992)return;'
            . 'var logo=document.querySelector(".awa-header-brand-cell .logo img,.col-md-2.awa-header-brand .logo img");'
            . 'if(!logo)return;'
            . 'logo.style.setProperty("width","104px","important");'
            . 'logo.style.setProperty("max-width","104px","important");'
            . 'logo.style.setProperty("min-width","0","important");'
            . 'logo.style.setProperty("height","44px","important");'
            . 'logo.style.setProperty("max-height","44px","important");'
            . 'logo.style.setProperty("aspect-ratio","auto","important");'
            . 'logo.style.setProperty("object-fit","contain","important");}'
            . 'function apply(){'
            . 'applyLogo();'
            . 'if(window.innerWidth>767)return;'
            . 'var grid=document.querySelector(".awa-main-header__inner.wp-header,[data-awa-header-row]");'
            . 'var sc=document.querySelector(".header-wrapper-sticky .awa-header-search-col");'
            . 'var form=sc&&sc.querySelector("form#search_mini_form");'
            . 'var input=sc&&sc.querySelector("input#search");'
            . 'if(grid){["padding","grid-template-columns","grid-template-areas","grid-template-rows","height","max-height"].forEach(function(p){grid.style.removeProperty(p);});'
            . 'if(!grid.getAttribute("style"))grid.removeAttribute("style");}'
            . 'if(sc){["display","grid-column","width","max-width","box-sizing"].forEach(function(p){sc.style.removeProperty(p);});'
            . 'if(!sc.getAttribute("style"))sc.removeAttribute("style");}'
            . 'if(form){["display","grid-template-columns","width","max-width","box-sizing"].forEach(function(p){form.style.removeProperty(p);});'
            . 'if(!form.getAttribute("style"))form.removeAttribute("style");}'
            . 'if(input){input.style.setProperty("font-size","16px","important");input.style.removeProperty("width");'
            . 'if(!input.getAttribute("style"))input.removeAttribute("style");}}'
            . 'document.addEventListener("DOMContentLoaded",apply,{once:true});'
            . 'window.addEventListener("load",apply,{once:true,passive:true});'
            . 'window.addEventListener("resize",apply,{passive:true});'
            . 'if(document.body&&window.MutationObserver){new MutationObserver(function(){if(document.getElementById("awa-home-cls-shell"))applyLogo();}).observe(document.body,{childList:true,subtree:true});}'
            . '})();</script>';
    }

    /**
     * Remove blocos header terminais legados antes de injetar distill.
     *
     * @param bool $stripCriticalGlobal PLP/busca: omitir critical-global (~35KB) — mobile-grid-critical + distill cobrem
     */
    public static function stripLegacyHeaderTerminalBlocks(string $html, bool $stripCriticalGlobal = false): string
    {
        foreach (self::LEGACY_HEADER_TERMINAL_IDS as $legacyId) {
            $pattern = '/<style\\s+id="' . preg_quote($legacyId, '/') . '"[^>]*>.*?<\\/style>\\s*/is';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        foreach (self::LEGACY_HEADER_TERMINAL_SCRIPT_IDS as $scriptId) {
            $pattern = '/<script\\s+id="' . preg_quote($scriptId, '/') . '"[^>]*>.*?<\\/script>\\s*/is';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        foreach (self::LEGACY_HEADER_DUPLICATE_IDS as $duplicateId) {
            $pattern = '/<style\\s+id="' . preg_quote($duplicateId, '/') . '"[^>]*>.*?<\\/style>\\s*/is';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        if ($stripCriticalGlobal) {
            $pattern = '/<style\\s+id="' . preg_quote(self::HEADER_CRITICAL_GLOBAL_ID, '/') . '"[^>]*>.*?<\\/style>\\s*/is';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        $html = preg_replace(
            '/<script\\s+id="awa-header-container-pad-zero"[^>]*>.*?<\\/script>\\s*/is',
            '',
            $html
        ) ?? $html;

        $html = self::stripHomeLightFromHtml($html);

        $html = preg_replace(
            '/<style\\s+id="awa-header-distill-terminal-[^"]+"[^>]*>.*?<\\/style>\\s*/is',
            '',
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * A11y polish síncrono — aria-label busca mobile quando o template não define.
     */
    public static function headerPolishA11yScript(): string
    {
        return '<script id="awa-header-a11y-polish-20260616">'
            . '(function(){'
            . 'var btn=document.querySelector('
            . '".awa-site-header .awa-header-search-col form#search_mini_form button.action.search"'
            . ');'
            . 'if(btn&&!btn.getAttribute("aria-label")){btn.setAttribute("aria-label","Buscar");}'
            . '})();'
            . '</script>';
    }

    /**
     * Shell 1280px + alinhamento grid/busca/logo/ícones (vence super-global legacy top negativo).
     */
    public static function headerLayoutAlignRules(): string
    {
        $shell = 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header';
        $row = $shell . ' .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';
        $container = $shell . ' .header-wrapper-sticky :is(.header_main.awa-main-header-inner-wrap,.header.awa-main-header) '
            . ':is(.header-main,.header_main)>.container,'
            . $shell . ' .header-wrapper-sticky .header-main>.container';

        return '@media (min-width:992px){'
            . $container . '{'
            . 'max-width:1280px!important;width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:0!important;box-sizing:border-box!important}'
            . $shell . ' .header-wrapper-sticky .header.awa-main-header{'
            . 'padding-block:0!important;margin-block:0!important;height:auto!important;min-height:0!important;'
            . 'overflow:visible!important}'
            . $shell . ' .header-wrapper-sticky :is(.header-main,.header_main,.header_main.awa-main-header-inner-wrap){'
            . 'height:auto!important;min-height:var(--awa-header-main-row-h,72px)!important;'
            . 'padding-block:0!important;margin-block:0!important;overflow:visible!important}'
            . $row . '{max-width:1280px!important;width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:16px!important;box-sizing:border-box!important;align-items:center!important}'
            . $shell . ' :is(.header-control.awa-nav-bar,.header-control.header-nav.awa-nav-bar)>.container,'
            . $shell . ' .header-control.awa-nav-bar .awa-nav-bar__inner{'
            . 'max-width:1280px!important;width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:16px!important;box-sizing:border-box!important}'
            . $shell . ' :is(.awa-b2b-promo-bar__inner,.top-header .container){'
            . 'max-width:1280px!important;width:100%!important;margin-inline:auto!important;'
            . 'padding-inline:16px!important;box-sizing:border-box!important}'
            . $shell . ' .header-content:has(.awa-b2b-promo-bar){'
            . 'align-items:stretch!important;width:100%!important;max-width:min(100%,1280px)!important}'
            . $shell . ' :is(.top-header,.top-header.awa-b2b-promo-bar,.awa-b2b-promo-bar){'
            . 'width:100%!important;max-width:100%!important;align-self:stretch!important;flex:1 1 100%!important}'
            . $shell . ' .awa-header-search-col{'
            . 'display:flex!important;align-items:center!important;align-self:stretch!important;'
            . 'grid-template-columns:none!important;grid-template-areas:none!important;height:auto!important}'
            . $shell . ' .awa-header-search-col :is(.block-search,.block-content){'
            . 'display:flex!important;align-items:center!important;width:100%!important;min-height:0!important}'
            . $shell . ' .awa-header-search-col :is(.block-content,form#search_mini_form,form.minisearch){'
            . 'width:100%!important;max-width:100%!important}'
            . $shell . ' .awa-search-helper-copy{display:none!important;visibility:hidden!important;height:0!important;overflow:hidden!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form{align-self:center!important;margin-block:0!important}'
            . $shell . ' .awa-header-right-col{align-items:center!important;align-self:center!important}'
            . $shell . ' .awa-header-minicart{align-self:center!important}'
            . $shell . ' :is(.awa-header-brand-cell,.col-md-2.awa-header-brand){align-self:center!important}'
            . $shell . ' :is(.awa-header-brand-cell,.col-md-2.awa-header-brand) .logo img{'
            . 'width:104px!important;max-width:104px!important;min-width:0!important;height:44px!important;max-height:44px!important;'
            . 'aspect-ratio:auto!important;object-fit:contain!important;object-position:left center!important}'
            . '}'
            . '@media (min-width:768px) and (max-width:991px){'
            . $shell . ' .header-wrapper-sticky .header.awa-main-header{'
            . 'padding-block:0!important;margin-block:0!important;height:auto!important}'
            . $shell . ' .header-wrapper-sticky :is(.header-main,.header_main){'
            . 'padding-block:0!important;margin-block:0!important;height:auto!important}'
            . $shell . ' .awa-header-search-col{'
            . 'display:block!important;grid-template-columns:none!important;grid-template-areas:none!important;width:100%!important}'
            . $shell . ' .awa-header-search-col .block-content{'
            . 'display:block!important;grid-template-columns:none!important;grid-template-areas:none!important;width:100%!important}'
            . $shell . ' .awa-header-search-col :is(form#search_mini_form,form.minisearch,.block-search,.block-content){'
            . 'width:100%!important;max-width:100%!important;min-width:0!important}'
            . $shell . ' .awa-search-helper-copy{display:none!important;visibility:hidden!important;width:0!important;height:0!important;overflow:hidden!important}'
            . $row . '{max-width:1280px!important;margin-inline:auto!important;padding-inline:16px!important}'
            . '}'
            . '@media (max-width:767px){'
            . $shell . ' .header-wrapper-sticky :is(.header-main,.header_main){'
            . 'padding:0!important;padding-block:0!important;margin:0!important;'
            . 'height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'overflow:hidden!important;box-sizing:border-box!important}'
            . $row . '{'
            . 'align-items:center!important;align-content:center!important;contain:none!important;overflow:visible!important;'
            . 'grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:44px 44px!important;'
            . 'grid-template-areas:"toggle brand cart" "search search search"!important;gap:4px 8px!important;'
            . 'padding:4px 16px 0!important;box-sizing:border-box!important}'
            . $shell . ' :is(.awa-header-mobile-toggle,.action.nav-toggle,[data-action="toggle-nav"]){'
            . 'position:relative!important;top:auto!important;right:auto!important;left:auto!important;inset:auto!important;'
            . 'align-self:center!important;justify-self:center!important;margin:0!important;transform:none!important}'
            . $shell . ' :is(.awa-header-brand-cell,.col-md-2.awa-header-brand,.awa-header-minicart){'
            . 'align-self:center!important;justify-self:center!important;position:relative!important;top:auto!important}'
            . $shell . ' .awa-header-minicart .minicart-wrapper{position:static!important;top:auto!important;right:auto!important}'
            . $shell . ' .awa-header-search-col{align-self:stretch!important;contain:none!important;'
            . 'display:block!important;grid-template-columns:none!important;width:100%!important;'
            . 'min-width:0!important;max-width:100%!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-search-col :is(.block-search,.block-content){'
            . 'display:block!important;width:100%!important;max-width:100%!important;min-width:0!important;'
            . 'padding:0!important;margin:0!important;height:44px!important;max-height:44px!important;box-sizing:border-box!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;'
            . 'grid-template-areas:"field submit"!important;width:100%!important;max-width:100%!important;'
            . 'box-sizing:border-box!important;margin:0!important;height:44px!important;max-height:44px!important}'
            . '}';
    }

    /**
     * Regras terminais mobile para o campo de busca.
     * Usa longhands grid-column-start/end (não shorthand grid-area) para evitar
     * conflitos com regras grid-area:search de bundles assíncronos (awa-header-stack).
     * Seletor de máxima especificidade garante vitória na cascata.
     */
    public static function mobileSearchTerminalRules(): string
    {
        return '@media (max-width:767px){'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header .awa-header-search-col.top-search{'
            . 'display:block!important;grid-template-columns:none!important;grid-template-areas:none!important;'
            . 'width:100%!important;min-width:0!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header .awa-header-search-col '
            . ':is(.block-search,.block-content,form#search_mini_form,form.minisearch){'
            . 'width:100%!important;max-width:100%!important;min-width:0!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header .awa-header-search-col form#search_mini_form{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;'
            . 'grid-template-areas:"field submit"!important;width:100%!important;max-width:100%!important;'
            . 'box-sizing:border-box!important;margin:0!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header .awa-header-search-col '
            . 'form#search_mini_form .field.search{flex:1 1 auto!important;min-width:0!important;width:auto!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header .awa-header-search-col '
            . 'form#search_mini_form input#search{width:100%!important;min-width:0!important;max-width:100%!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header .awa-header-search-col .awa-search-helper-copy{'
            . 'display:none!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header '
            . ':is(.awa-header-brand-cell,.col-md-2.awa-header-brand){'
            . 'min-width:56px!important;width:auto!important;overflow:visible!important}'
            . '}';
    }

    /**
     * Tablet 768–991 — grid corporativo brand | search | actions (56px), não mobile 2-row.
     */
    public static function tabletHeaderCompactRules(): string
    {
        $row = 'html body#html-body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]),'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . '.header.awa-main-header .header_main.awa-main-header-inner-wrap '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row])';

        return '@media (min-width:768px) and (max-width:991px){'
            . $row . '{'
            . 'display:grid!important;grid-template-areas:"brand search actions"!important;'
            . 'grid-template-columns:clamp(112px,14vw,148px) minmax(0,1fr) minmax(220px,max-content)!important;'
            . 'grid-template-rows:56px!important;gap:0 12px!important;'
            . 'height:56px!important;min-height:56px!important;max-height:56px!important;'
            . 'padding:0 16px!important;max-width:min(100%,1280px)!important;margin-inline:auto!important;'
            . 'box-sizing:border-box!important;width:100%!important;overflow:visible!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . ':is(.awa-header-mobile-toggle,.action.nav-toggle,[data-action="toggle-nav"]){display:none!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . '.awa-header-primary-row{display:contents!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . '.awa-header-right-col{display:inline-flex!important;grid-area:actions!important;'
            . 'gap:8px!important;align-items:center!important;justify-content:flex-end!important;width:auto!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-wrapper-sticky '
            . '.awa-header-search-col{grid-area:search!important;grid-column:auto!important;grid-row:auto!important;'
            . 'width:100%!important;min-width:0!important;height:44px!important;max-height:44px!important;min-height:44px!important}'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .content-top-home .awa-carousel-section>.container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:min(100%,1280px)!important;padding-inline:16px!important;width:100%!important}'
            . '}';
    }

    public static function styleTag(): string
    {
        return '<style id="' . self::STYLE_ID . '">' . self::rules() . '</style>';
    }

    public static function guardScriptTag(): string
    {
        $styleId = self::STYLE_ID;
        $guardId = self::GUARD_SCRIPT_ID;

        /* Snapshot do <style> existente — evita duplicar ~10KB de rules no HTML (TBT/parse). */
        /* applyMobileGrid — aplica via setProperty para garantir vitória sobre bundles assíncronos. */
        return '<script id="' . $guardId . '">(function(){'
            . '"use strict";'
            . 'var id=' . json_encode($styleId, JSON_UNESCAPED_SLASHES) . ';'
            . 'var snap="";'
            . 'function capture(){var el=document.getElementById(id);if(el&&el.textContent){snap=el.textContent;}}'
            . 'function apply(){if(!snap){return;}var el=document.getElementById(id);'
            . 'var root=document.body||document.documentElement;if(!el){el=document.createElement("style");'
            . 'el.id=id;root.appendChild(el);}if(el.textContent!==snap){el.textContent=snap;}'
            . 'if(document.body&&el.parentNode!==document.body){document.body.appendChild(el);}}'
            . 'function applyMobileGrid(){'
            . 'if(window.innerWidth>767)return;'
            . 'var grid=document.querySelector(".awa-main-header__inner.wp-header");'
            . 'var sc=document.querySelector(".header-wrapper-sticky .awa-header-search-col");'
            . 'var hm=document.querySelector(".header-wrapper-sticky .header-main");'
            . 'if(grid){["grid-template-columns","grid-template-areas","grid-template-rows","gap","padding","height","min-height","max-height","box-sizing","overflow"].forEach(function(p){grid.style.removeProperty(p);});'
            . 'if(!grid.getAttribute("style"))grid.removeAttribute("style");}'
            . 'if(sc){["grid-column","grid-row","width","min-width","max-width","display","height","min-height","max-height","overflow"].forEach(function(p){sc.style.removeProperty(p);});'
            . 'if(!sc.getAttribute("style"))sc.removeAttribute("style");'
            . 'var form=sc&&sc.querySelector("form#search_mini_form");'
            . 'var input=sc&&sc.querySelector("input#search");'
            . 'if(form){["height","min-height","max-height","width","max-width","box-sizing"].forEach(function(p){form.style.removeProperty(p);});'
            . 'if(!form.getAttribute("style"))form.removeAttribute("style");}'
            . 'if(input){input.style.setProperty("font-size","16px","important");input.style.removeProperty("height");input.style.removeProperty("min-height");'
            . 'if(!input.getAttribute("style"))input.removeAttribute("style");}'
            . 'if(hm){hm.style.setProperty("overflow","visible","important");}}}'
            . 'capture();apply();'
            . 'document.addEventListener("DOMContentLoaded",function(){capture();apply();applyMobileGrid();},{once:true});'
            . 'window.addEventListener("load",function(){applyMobileGrid();},{once:true,passive:true});'
            . 'if(typeof MutationObserver!=="undefined"&&document.body){'
            . 'new MutationObserver(function(){if(!document.getElementById(id)){apply();}})'
            . '.observe(document.body,{childList:true,subtree:false});}'
            . '})();</script>';
    }

    public static function homeSearchApfPolishRules(): string
    {
        return '/* §APF-T — busca desktop .actions stretch (polish 2026-06-12) */'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header:not(.awa-header-condensed) .awa-header-search-col '
            . ':is(form#search_mini_form,form.minisearch){'
            . 'display:flex!important;align-items:stretch!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header:not(.awa-header-condensed) .awa-header-search-col '
            . 'form#search_mini_form .actions{'
            . 'display:flex!important;align-items:stretch!important;align-self:stretch!important;'
            . 'flex:0 0 48px!important;width:48px!important;min-width:48px!important;'
            . 'height:auto!important;min-height:0!important;max-height:none!important;'
            . 'margin:0!important;padding:0!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header:not(.awa-header-condensed) .awa-header-search-col '
            . 'form#search_mini_form .action.search{'
            . 'align-self:stretch!important;width:100%!important;min-width:48px!important;'
            . 'height:100%!important;min-height:44px!important;max-height:none!important}'
            // Desktop: suprimir a lupa desenhada via ::before/::after do botão, mantendo só o SVG inline (evita lupa duplicada).
            . '@media(min-width:992px){'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header .awa-header-search-col form#search_mini_form button.action.search::before,'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header .awa-header-search-col form#search_mini_form button.action.search::after{'
            . 'content:none!important;display:none!important;border:0!important;background:none!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header .awa-header-search-col form#search_mini_form button.action.search svg{'
            . 'display:block!important;margin:0 auto!important}'
            . '}'
            . '@media(max-width:991px){html body#html-body#html-body#html-body#html-body .page-wrapper '
            . ':is(.awa-shelf--carousel,.product-item) a.b2b-login-link{'
            . 'min-height:44px!important;min-width:44px!important;display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important}}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__cta{'
            . 'display:inline-flex!important;align-items:center!important;position:relative!important;'
            . 'min-height:44px!important;padding-block:8px!important;margin-block:-8px!important;'
            . 'box-sizing:border-box!important}';
    }

    /**
     * Home PSI — regras pós-async que o v10 + critical-home não cobrem (promo, minicart, a11y, mobile search).
     */
    public static function homeHeaderLightLockRules(): string
    {
        return self::headerA11yRules()
            . self::minicartInteractionRules()
            . self::promoBarRules()
            . self::headerStickyShellRules()
            . self::headerVisFixTerminalRules()
            . self::headerCartDedupeRules()
            . self::headerLayoutAlignRules()
            . self::mobileSearchTerminalRules()
            . self::tabletHeaderCompactRules()
            . self::mobileHeaderCompact112Rules()
            . self::headerEssentialTerminalRules()
            . self::homeSearchApfPolishRules();
    }

    public static function headerEssentialStyleTag(): string
    {
        return '<style id="' . self::HEADER_ESSENTIAL_STYLE_ID . '">'
            . self::headerEssentialTerminalRules()
            . '</style>';
    }

    public static function stripHeaderEssentialFromHtml(string $html): string
    {
        $pattern = '/<style\\s+id="' . preg_quote(self::HEADER_ESSENTIAL_STYLE_ID, '/') . '"[^>]*>.*?<\\/style>\\s*/is';

        return preg_replace($pattern, '', $html) ?? $html;
    }

    public static function homeLightStyleTag(): string
    {
        return '<style id="' . self::HOME_LIGHT_STYLE_ID . '">' . self::homeHeaderLightLockRules() . '</style>';
    }

    public static function homeLightLinkTag(string $staticBase): string
    {
        $href = rtrim($staticBase, '/') . '/css/' . self::HOME_LIGHT_CSS_FILE . self::HOME_LIGHT_QUERY;

        return '<link rel="preload" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" as="style"/>'
            . '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" media="all" id="'
            . self::HOME_LIGHT_STYLE_ID . '"/>';
    }

    public static function htmlHasHomeLightLock(string $html): bool
    {
        if (!str_contains($html, self::HOME_LIGHT_STYLE_ID)) {
            return false;
        }

        return (bool) preg_match(
            '/<(?:style|link)[^>]+id=["\']' . preg_quote(self::HOME_LIGHT_STYLE_ID, '/') . '["\'][^>]*>/i',
            $html
        );
    }

    public static function stripHomeLightFromHtml(string $html): string
    {
        $id = preg_quote(self::HOME_LIGHT_STYLE_ID, '/');
        $html = preg_replace('/<style\\s+id="' . $id . '"[^>]*>.*?<\\/style>\\s*/is', '', $html) ?? $html;
        $html = preg_replace('/<link\\s[^>]+id=["\']' . $id . '["\'][^>]*\\/?>\\s*/is', '', $html) ?? $html;
        $html = preg_replace('/<link\\s[^>]+href=["\'][^"\']*' . preg_quote(self::HOME_LIGHT_CSS_FILE, '/') . '[^"\']*["\'][^>]*\\/?>\\s*/is', '', $html) ?? $html;

        return $html;
    }

    /**
     * Home vis-fix sem applyMobileGrid — distill mobile-grid script é SSOT.
     */
    public static function homeVisFixOnlyScriptTag(): string
    {
        $guardId = self::HOME_GUARD_SCRIPT_ID;

        return '<script id="' . $guardId . '">(function(){'
            . '"use strict";'
            . 'function applyHeaderVisFix(){'
            . 'var prompt=document.querySelector(".awa-site-header .awa-header-account-prompt");'
            . 'if(prompt&&window.innerWidth>=992){'
            . 'prompt.style.removeProperty("max-width");prompt.style.removeProperty("overflow");'
            . 'if(!prompt.getAttribute("style"))prompt.removeAttribute("style");'
            . 'var reg=prompt.querySelector(".awa-header-account-prompt__link--register");'
            . 'if(reg){reg.style.removeProperty("background");reg.style.removeProperty("background-color");'
            . 'reg.style.removeProperty("color");reg.style.removeProperty("border-radius");reg.style.removeProperty("padding");'
            . 'if(!reg.getAttribute("style"))reg.removeAttribute("style");}}'
            . 'if(window.innerWidth>=768&&window.innerWidth<=991&&prompt){'
            . 'prompt.querySelectorAll(".awa-header-account-prompt__icon,.awa-header-account-prompt__text,.awa-header-account-prompt__guest")'
            . '.forEach(function(el){el.style.setProperty("display","none","important");});'
            . 'var ml=prompt.querySelector(".awa-header-account-prompt__mobile-link");'
            . 'if(ml){ml.style.setProperty("display","inline-flex","important");ml.style.setProperty("visibility","visible","important");}}'
            . 'var legacyCart=document.querySelector(".awa-header-primary-row>.awa-header-cart-link");'
            . 'if(legacyCart&&window.innerWidth<=991){legacyCart.style.removeProperty("display");'
            . 'legacyCart.style.removeProperty("visibility");legacyCart.style.removeProperty("pointer-events");'
            . 'if(!legacyCart.getAttribute("style"))legacyCart.removeAttribute("style");}'
            . 'var fallback=document.querySelector(".awa-header-minicart .awa-header-cart-fallback");'
            . 'var hasShowcart=document.querySelector(".awa-header-minicart .minicart-wrapper .showcart");'
            . 'if(fallback&&hasShowcart){fallback.style.removeProperty("display");'
            . 'fallback.style.removeProperty("visibility");fallback.style.removeProperty("pointer-events");'
            . 'if(!fallback.getAttribute("style"))fallback.removeAttribute("style");}'
            . 'var mainContainer=document.querySelector(".awa-site-header .header-main>.container,.awa-site-header .header_main>.container");'
            . 'if(mainContainer&&window.innerWidth<=767){["padding","padding-inline","padding-left","padding-right"].forEach(function(p){mainContainer.style.removeProperty(p);});'
            . 'if(!mainContainer.getAttribute("style"))mainContainer.removeAttribute("style");}'
            . 'var toggle=document.querySelector(".awa-header-mobile-toggle,.action.nav-toggle,[data-action=\\"toggle-nav\\"]");'
            . 'if(toggle&&window.innerWidth<=767){["position","top","right","left","transform"].forEach(function(p){toggle.style.removeProperty(p);});'
            . 'if(!toggle.getAttribute("style"))toggle.removeAttribute("style");}}'
            . 'function boot(){applyHeaderVisFix();}'
            . 'document.addEventListener("DOMContentLoaded",boot,{once:true});'
            . 'window.addEventListener("load",boot,{once:true,passive:true});'
            . 'window.addEventListener("resize",boot,{passive:true});'
            . '})();</script>';
    }

    public static function homeGuardScriptTag(): string
    {
        $guardId = self::HOME_GUARD_SCRIPT_ID;

        return '<script id="' . $guardId . '">(function(){'
            . '"use strict";'
            . 'var distillGridId=' . json_encode(self::DISTILL_MOBILE_GRID_SCRIPT_ID, JSON_UNESCAPED_SLASHES) . ';'
            . 'function applyMobileGrid(){'
            . 'if(document.getElementById(distillGridId)){return;}'
            . 'if(window.innerWidth>767)return;'
            . 'var grid=document.querySelector(".awa-main-header__inner.wp-header");'
            . 'var sc=document.querySelector(".header-wrapper-sticky .awa-header-search-col");'
            . 'if(grid){["grid-template-columns","grid-template-areas","grid-template-rows","gap","padding","height","max-height"].forEach(function(p){grid.style.removeProperty(p);});'
            . 'if(!grid.getAttribute("style"))grid.removeAttribute("style");}'
            . 'if(sc){["grid-column","width","height","min-height","max-height","display"].forEach(function(p){sc.style.removeProperty(p);});'
            . 'if(!sc.getAttribute("style"))sc.removeAttribute("style");'
            . 'var form=sc&&sc.querySelector("form#search_mini_form");'
            . 'var input=sc&&sc.querySelector("input#search");'
            . 'if(form){["height","min-height","max-height","width","max-width","box-sizing","display","grid-template-columns"].forEach(function(p){form.style.removeProperty(p);});'
            . 'if(!form.getAttribute("style"))form.removeAttribute("style");}'
            . 'if(input){input.style.removeProperty("height");input.style.removeProperty("min-height");input.style.removeProperty("width");'
            . 'if(!input.getAttribute("style"))input.removeAttribute("style");}'
            . 'if(sc){sc.style.removeProperty("display");if(!sc.getAttribute("style"))sc.removeAttribute("style");}'
            . 'var brand=document.querySelector(".awa-site-header .awa-header-brand-cell,.awa-site-header .col-md-2.awa-header-brand");'
            . 'if(brand){brand.style.removeProperty("min-width");brand.style.removeProperty("width");'
            . 'if(!brand.getAttribute("style"))brand.removeAttribute("style");}}}'
            . 'function applyHeaderVisFix(){'
            . 'var prompt=document.querySelector(".awa-site-header .awa-header-account-prompt");'
            . 'if(prompt&&window.innerWidth>=992){'
            . 'prompt.style.removeProperty("max-width");prompt.style.removeProperty("overflow");'
            . 'if(!prompt.getAttribute("style"))prompt.removeAttribute("style");'
            . 'var reg=prompt.querySelector(".awa-header-account-prompt__link--register");'
            . 'if(reg){reg.style.removeProperty("background");reg.style.removeProperty("background-color");'
            . 'reg.style.removeProperty("color");reg.style.removeProperty("border-radius");reg.style.removeProperty("padding");'
            . 'if(!reg.getAttribute("style"))reg.removeAttribute("style");}}'
            . 'if(window.innerWidth>=768&&window.innerWidth<=991&&prompt){'
            . 'prompt.querySelectorAll(".awa-header-account-prompt__icon,.awa-header-account-prompt__text,.awa-header-account-prompt__guest")'
            . '.forEach(function(el){el.style.setProperty("display","none","important");});'
            . 'var ml=prompt.querySelector(".awa-header-account-prompt__mobile-link");'
            . 'if(ml){ml.style.setProperty("display","inline-flex","important");ml.style.setProperty("visibility","visible","important");}}'
            . 'var legacyCart=document.querySelector(".awa-header-primary-row>.awa-header-cart-link");'
            . 'if(legacyCart&&window.innerWidth<=991){legacyCart.style.removeProperty("display");'
            . 'legacyCart.style.removeProperty("visibility");legacyCart.style.removeProperty("pointer-events");'
            . 'if(!legacyCart.getAttribute("style"))legacyCart.removeAttribute("style");}'
            . 'var fallback=document.querySelector(".awa-header-minicart .awa-header-cart-fallback");'
            . 'var hasShowcart=document.querySelector(".awa-header-minicart .minicart-wrapper .showcart");'
            . 'if(fallback&&hasShowcart){fallback.style.removeProperty("display");'
            . 'fallback.style.removeProperty("visibility");fallback.style.removeProperty("pointer-events");'
            . 'if(!fallback.getAttribute("style"))fallback.removeAttribute("style");}'
            . 'var mainContainer=document.querySelector(".awa-site-header .header-main>.container,.awa-site-header .header_main>.container");'
            . 'if(mainContainer&&window.innerWidth<=767){["padding","padding-inline","padding-left","padding-right"].forEach(function(p){mainContainer.style.removeProperty(p);});'
            . 'if(!mainContainer.getAttribute("style"))mainContainer.removeAttribute("style");}'
            . 'var toggle=document.querySelector(".awa-header-mobile-toggle,.action.nav-toggle,[data-action=\\"toggle-nav\\"]");'
            . 'if(toggle&&window.innerWidth<=767){["position","top","right","left","transform"].forEach(function(p){toggle.style.removeProperty(p);});'
            . 'if(!toggle.getAttribute("style"))toggle.removeAttribute("style");}'
            . 'applyMobileGrid();}'
            . 'function boot(){applyHeaderVisFix();}'
            . 'document.addEventListener("DOMContentLoaded",boot,{once:true});'
            . 'window.addEventListener("load",boot,{once:true,passive:true});'
            . '})();</script>';
    }

    public static function homeLightInjection(?string $staticBase = null): string
    {
        $css = ($staticBase !== null && $staticBase !== '')
            ? self::homeLightLinkTag($staticBase)
            : self::homeLightStyleTag();

        return $css . "\n" . self::homeGuardScriptTag();
    }

    /**
     * Home: omitir cascade-lock completo (~112KB); v10 + critical-home cobrem 1º paint.
     */
    public static function injectHomeLightBeforeBodyClose(string $html): string
    {
        $html = self::stripLegacyFromHtml($html);
        $html = self::stripFooterTerminalFromHtml($html);
        $html = self::stripHomeLightFromHtml($html);
        $html = self::stripHomeImpeccableTerminalFromHtml($html);
        $html = preg_replace(
            '/<script\\s+id="' . preg_quote(self::HOME_GUARD_SCRIPT_ID, '/') . '"[^>]*>.*?<\\/script>\\s*/is',
            '',
            $html
        ) ?? $html;

        $tag = '';
        if (!str_contains($html, self::HOME_GUARD_SCRIPT_ID)) {
            $tag = str_contains($html, self::DISTILL_MOBILE_GRID_SCRIPT_ID)
                ? self::homeVisFixOnlyScriptTag()
                : self::homeGuardScriptTag();
        }
        if ($tag === '') {
            return $html;
        }

        if (!str_contains($html, 'id="awa-impeccable-critical-home"')) {
            $tag = self::footerOnlyStyleTag() . "\n" . $tag;
        }

        if (!str_contains($html, self::HOME_IMPECCABLE_TERMINAL_STYLE_ID)) {
            $tag = self::homeImpeccableTerminalStyleTag() . "\n" . $tag;
        }

        $pos = stripos($html, '</body>');
        if ($pos === false) {
            return $html . "\n" . $tag;
        }

        return substr($html, 0, $pos) . $tag . "\n" . substr($html, $pos);
    }

    public static function footerInjection(): string
    {
        return self::styleTag() . "\n" . self::guardScriptTag() . "\n" . self::catalogRootHeightScriptTag();
    }

    private static function catalogRootHeightScriptTag(): string
    {
        return '<script id="awa-catalog-root-height-lock">(function(){'
            . 'var d=document,b=d.body,doc=d.documentElement;'
            . 'if(!b||!b.matches||!b.matches(".catalog-category-view,.catalogsearch-result-index,.catalog-product-view")){return;}'
            . 'function apply(){'
            . 'if(!d.querySelector("nav.fixed-bottom")){return;}'
            . 'doc.style.setProperty("height","100%","important");'
            . 'doc.style.setProperty("min-height","100%","important");'
            . 'b.style.setProperty("height","100%","important");'
            . 'b.style.setProperty("min-height","100%","important");'
            . '}'
            . 'apply();'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",apply,{once:true});}'
            . 'window.addEventListener("load",apply,{once:true,passive:true});'
            . 'window.addEventListener("resize",apply,{passive:true});'
            . '})();</script>';
    }

    public static function footerOnlyStyleTag(): string
    {
        return '<style id="' . self::FOOTER_STYLE_ID . '">' . self::footerTerminalRules() . '</style>';
    }

    /**
     * Home: terminal Impeccable — 4× #html-body vence home-density-grid (3×) e body-end bundles.
     * Injetado por último antes de </body> (inline + ordem DOM).
     */
    public static function homeImpeccableTerminalStyleTag(): string
    {
        return '<style id="' . self::HOME_IMPECCABLE_TERMINAL_STYLE_ID . '">'
            . self::homeImpeccableTerminalRules()
            . '</style>';
    }

    public static function homeImpeccableTerminalRules(): string
    {
        $h = 'html body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)'
            . ' .page-wrapper';

        return $h . ' .content-top-home :is(.awa-shelf--carousel,.awa-carousel-section)'
            . ' :is(.content-item-product.awa-product-card,.product-thumb,span.first-thumb,.item-product.awa-carousel-card-slot,'
            . '.product-image-container,.product-image-wrapper,.owl-stage-outer){overflow:visible!important}'
            . $h . ' :is(.awa-category-carousel__viewport,.awa-category-carousel__item,a.awa-category-carousel__item,'
            . '.awa-owl-progress.awa-carousel-chrome-ssr){overflow:visible!important}'
            . $h . ' .top-home-content--category-carousel .awa-category-carousel__viewport{'
            . 'margin-inline:0!important;padding:0!important;box-sizing:border-box!important}'
            . $h . ' .top-home-content--category-carousel .awa-category-carousel__track,'
            . $h . ' .top-home-content--category-carousel #awa-cat-carousel.awa-category-carousel__track{'
            . 'margin-inline:0!important;padding:0!important;scroll-padding-inline:0!important;gap:8px!important}'
            . $h . ' :is(.swiper.awa-hero-swiper,.swiper-wrapper,.banner_item,.banner_item_bg,'
            . '.banner-slider.banner-slider2,.wrapper_slider){overflow:visible!important}'
            . $h . ' .ayo-home5-wrapper.ayo-home5-wrapper--template-driven{overflow:visible!important}'
            . $h . ' :is(.block.block-minicart,.block-minicart.ui-dialog-content,.minicart-wrapper .mage-dropdown-dialog){overflow:visible!important}'
            . $h . ' :is(ul.togge-menu.list-category-dropdown,.level0.submenu.navigation__submenu,'
            . '.level0.submenu.navigation__inner-list,.awa-nav-bar .container,.header-control.awa-nav-bar .container){overflow:visible!important}'
            . $h . ' :is(#b2b-status-dropdown,.b2b-status-dropdown){'
            . 'border-width:0!important;box-shadow:0 2px 8px rgb(15 23 42 / 10%)!important}'
            /* Nav bar home — respiro vertical; botão Departamentos não cola no topo/fundo */
            . 'html body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)'
            . ' .page-wrapper .header-control.awa-nav-bar{--awa-nav-bar-h:48px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)'
            . ' .page-wrapper .awa-site-header :is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar,'
            . '.header-control.awa-nav-bar,.awa-nav-bar){'
            . 'padding:0 max(16px,env(safe-area-inset-left),env(safe-area-inset-right))!important;'
            . 'min-height:48px!important;height:48px!important;max-height:48px!important;box-sizing:border-box!important;'
            . 'overflow:visible!important}'
            . '@media(min-width:992px){'
            . $h . ' .awa-site-header[data-awa-header-mode="default"]{'
            . 'overflow:visible!important;position:relative!important;z-index:100120!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"]:has(.awa-account-dropdown__trigger[aria-expanded="true"]){'
            . 'z-index:100260!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"] :is('
            . '.header-content,.header-wrapper-sticky,.header.awa-main-header,.header-main,.header-main>.container,'
            . '.awa-main-header__inner,.awa-header-right-col,.awa-header-account-prompt,.awa-account-dropdown){'
            . 'overflow:visible!important;position:relative!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"] .awa-header-right-col{'
            . 'z-index:100270!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"] .awa-account-dropdown{'
            . 'z-index:100270!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"] .awa-account-dropdown__trigger{'
            . 'position:relative!important;z-index:100280!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"] .awa-account-dropdown__menu{'
            . 'background:var(--awa-bg,Canvas)!important;'
            . 'border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 12%,Canvas))!important;'
            . 'border-radius:8px!important;box-shadow:0 8px 20px color-mix(in srgb,CanvasText 14%,transparent)!important;'
            . 'box-sizing:border-box!important;display:none!important;inset-block-start:calc(100% - 1px)!important;'
            . 'inset-inline-end:0!important;margin:0!important;margin-block-start:0!important;'
            . 'min-width:12rem!important;max-width:min(18rem,calc(100vw - 24px))!important;'
            . 'overflow:hidden!important;padding:6px!important;position:absolute!important;'
            . 'top:calc(100% - 1px)!important;transform:none!important;visibility:hidden!important;'
            . 'z-index:100300!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"] .awa-account-dropdown__menu[aria-hidden="false"]{'
            . 'display:grid!important;opacity:1!important;pointer-events:auto!important;visibility:visible!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"]:has(.awa-account-dropdown__trigger[aria-expanded="true"]) '
            . '.header-wrapper-sticky{overflow:visible!important;z-index:100260!important}'
            . $h . ' .awa-site-header[data-awa-header-mode="default"]:has(.awa-account-dropdown__trigger[aria-expanded="true"]) '
            . ':is(.header-control.awa-nav-bar,.header-control.awa-nav-bar>.container,.header-control.awa-nav-bar .awa-nav-bar__inner){'
            . 'z-index:100120!important}'
            . '}'
            . $h . ' .header-control.awa-nav-bar .awa-nav-bar__inner{'
            . 'align-items:center!important;padding-block:0!important;padding-inline:16px!important;'
            . 'min-height:48px!important;height:48px!important;max-height:48px!important}'
            . $h . ' .header-control.awa-nav-bar > .container{'
            . 'align-items:center!important;padding:0!important;padding-inline:0!important;'
            . 'min-height:48px!important;height:48px!important;max-height:48px!important}'
            . $h . ' .header-control.awa-nav-bar .awa-nav-quick-links{'
            . 'align-items:center!important;height:auto!important;min-height:40px!important}'
            . $h . ' .header-control.awa-nav-bar .awa-nav-quick-links__list{align-items:center!important}'
            . $h . ' .header-control.awa-nav-bar .awa-nav-quick-links__link{'
            . 'display:inline-flex!important;align-items:center!important;'
            . 'min-height:44px!important;padding-block:0!important}'
            . $h . ' .header-control.awa-nav-bar'
            . ' :is(.our_categories.title-category-dropdown,button[data-role=awa-vertical-menu-trigger]){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'border-radius:8px!important;align-self:center!important}'
            . $h . ' .awa-header-categories.menu_left_home1{'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'padding:0!important;box-sizing:border-box!important;overflow:visible!important}'
            . $h . ' .awa-header-categories.menu_left_home1 :is(nav.awa-nav-categories,'
            . '.sections.nav-sections.category-dropdown,.section-items.nav-sections.category-dropdown-items,'
            . '.section-item-content.nav-sections.category-dropdown-item-content,.navigation.verticalmenu.side-verticalmenu){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'overflow:visible!important;position:relative!important;width:100%!important}'
            . $h . ' .awa-header-categories.menu_left_home1 ul.togge-menu.list-category-dropdown{'
            . 'background:var(--awa-bg,Canvas)!important;border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 10%,Canvas))!important;'
            . 'border-radius:0 0 8px 8px!important;box-shadow:0 4px 10px color-mix(in srgb,CanvasText 10%,transparent)!important;'
            . 'box-sizing:border-box!important;display:none!important;height:auto!important;left:0!important;'
            . 'max-height:min(70vh,560px)!important;opacity:0!important;overflow-x:hidden!important;overflow-y:auto!important;'
            . 'padding:8px 10px 10px!important;position:absolute!important;top:44px!important;'
            . 'visibility:hidden!important;width:min(304px,calc(100vw - 32px))!important;z-index:100150!important}'
            . $h . ' .awa-header-categories.menu_left_home1:is(:hover,:focus-within,.is-open,.active,.awa-vmf-active) ul.togge-menu.list-category-dropdown,'
            . $h . ' .awa-header-categories.menu_left_home1 ul.togge-menu.list-category-dropdown:is(.menu-open,.vmm-open,[aria-hidden="false"]){'
            . 'display:block!important;height:auto!important;max-height:min(70vh,560px)!important;opacity:1!important;'
            . 'overflow-x:hidden!important;overflow-y:auto!important;visibility:visible!important}'
            . $h . ' .minicart-wrapper .counter.qty{border-width:0!important;box-shadow:none!important}'
            . $h . ' section.awa-footer-trust-bar{padding-block:14px 16px!important}'
            . $h . ' .content-top-home>.ayo-home5-wrapper.ayo-home5-wrapper--template-driven{'
            . 'padding-inline:0!important;box-sizing:border-box!important}'
            . $h . ' .content-top-home>.top-home-content--above-fold{'
            . 'padding-inline:max(16px,env(safe-area-inset-left),env(safe-area-inset-right))!important;box-sizing:border-box!important}'
            . $h . ' .content-top-home :is(.awa-hero-b2b-cta,.awa-home-pricing-notice,'
            . '.ayo-home5-wrapper--template-driven>:is(.top-home-content,.awa-home-section,.awa-carousel-section,#awa-home-niche-shelves)){'
            . 'padding-inline:max(16px,env(safe-area-inset-left),env(safe-area-inset-right))!important;box-sizing:border-box!important}'
            . $h . ' .content-top-home .ayo-home5-wrapper--template-driven>.top-home-content.awa-home-section{'
            . 'padding-inline:0!important}'
            . $h . ' .content-top-home>.top-home-content--above-fold>.banner-slider.banner-slider2{'
            . 'width:100%!important;max-width:100%!important;margin-inline:0!important;margin-left:0!important;'
            . 'padding:0!important;box-sizing:border-box!important;overflow:hidden!important}'
            . $h . ' .top-home-content--above-fold .banner_item .text-banner:not(:empty){'
            . 'background:linear-gradient(to top,rgb(15 23 42 / 72%) 0%,rgb(15 23 42 / 28%) 55%,transparent 100%)!important;'
            . 'padding:clamp(16px,3vw,32px) clamp(16px,4vw,48px)!important;'
            . 'border-radius:0!important;box-sizing:border-box!important;z-index:2!important}'
            . $h . ' .top-home-content--above-fold .banner_item .text-banner :is('
            . 'h2.slide-title,.slide-title,.slide-content p,.banner-content p,p.slide-desc,.slide-desc,p'
            . '){color:rgb(248 250 252 / 92%)!important;text-shadow:none!important;background-color:transparent!important}'
            . $h . ' .top-home-content--above-fold .banner_item .text-banner :is(h2.slide-title,.slide-title){'
            . 'color:#fff!important}'
            . $h . ' .content-top-home :is(.awa-carousel-section,.top-home-content--category-carousel,.awa-home-recent-orders,.awa-grid-section,.awa-home-niche-shelves) > .container,'
            . $h . ' .awa-hero-b2b-cta__inner.container{'
            . 'margin-inline:0!important;padding-inline:0!important;width:100%!important;max-width:100%!important;box-sizing:border-box!important}'
            . $h . ' .awa-shelf--carousel{padding-inline:0!important}'
            . $h . ' .awa-shelf--carousel :is(.awa-carousel__viewport,.owl-wrapper-outer){'
            . 'margin-inline:0!important;padding-inline:0!important;width:100%!important}'
            . $h . ' .content-top-home .awa-hero-b2b-cta{padding-block:12px!important;padding-inline:0!important}'
            . $h . ' .content-top-home .ayo-home5-wrapper--template-driven > .top-home-content.awa-home-section{'
            . 'padding-block:8px!important;padding-inline:0!important;margin-block:0!important}'
            . $h . ' .content-top-home :is(.awa-section-header,.awa-shelf__header,.awa-category-carousel__header.awa-section-header){'
            . 'margin-block-end:8px!important;padding-block-end:8px!important;gap:8px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5){'
            . '--awa-home-shell-gutter:16px;--awa-home-pad-compact:8px;--awa-home-pad-standard:12px;'
            . '--awa-home-pad-featured:16px;--awa-home-pad-category:12px;--awa-home-section-gap:12px}'
            . 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper'
            . ' :is(.page_footer,.page-footer) .footer-container .vela-content'
            . ' :is(p.awa-footer-atendimento__label,p.awa-footer-atendimento__label--social){'
            /* FIX 2026-07-06: .vela-content deste bloco tem fundo --awa-primary (vermelho),
               não branco/claro — confirmado após corrigir corrupção em _awa-consolidated.less
               que mantinha a regra de fundo vermelho (mais específica, com #html-body) inativa.
               Texto cinza (--awa-text-secondary) não teria contraste sobre vermelho; volta ao
               branco original (76% opacidade), que já dava ~5.9:1 no fundo real. */
            . 'color:color-mix(in srgb,var(--awa-text-inverse,white) 92%,transparent)!important}'
            . self::headerStickyPositionFixRules()
            . 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper'
            . ' :is(.page_footer,.page-footer) .awa-footer-newsletter{'
            . 'padding-inline:max(16px,env(safe-area-inset-left),env(safe-area-inset-right))!important;'
            . 'box-sizing:border-box!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body .page-wrapper'
            . ' :is(.page_footer,.page-footer) .footer-bottom .footer-bottom-inner{'
            . 'padding-inline:max(16px,env(safe-area-inset-left),env(safe-area-inset-right))!important}'
            . self::homeShellCenterTerminalRules()
            . self::homeImpeccablePolishTerminalRules()
            . self::homeHeaderRailTerminalRules()
            . self::homeImpeccablePaddingTerminalRules();
    }

    /**
     * Override final dos achados Impeccable de padding.
     * Mantém o eixo visual do header, mas vence os resets do homeHeaderRailTerminalRules().
     */
    public static function homeImpeccablePaddingTerminalRules(): string
    {
        $home = 'html body#html-body#html-body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)';
        $wrap = $home . ' .page-wrapper';
        $header = $wrap . ' .awa-site-header';
        $safePad = 'max(var(--awa-space-4,16px),env(safe-area-inset-left),env(safe-area-inset-right))';

        return $wrap . ' :is(#b2b-status-dropdown,.b2b-status-dropdown){'
            . 'border-width:0!important;box-shadow:0 2px 8px rgb(15 23 42 / 10%)!important}'
            . $wrap . ' :is(.awa-site-header .header-wrapper-sticky,#header .header-wrapper-sticky){'
            . 'padding:var(--awa-space-2,8px) ' . $safePad . '!important;'
            . 'min-height:calc(var(--awa-header-main-h,68px) + var(--awa-space-4,16px))!important;'
            . 'height:auto!important;max-height:none!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap){'
            . 'padding-block:var(--awa-space-2,8px)!important;padding-inline:0!important;'
            . 'min-height:calc(var(--awa-header-main-h,68px) + var(--awa-space-4,16px))!important;'
            . 'height:auto!important;max-height:none!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' .header-wrapper-sticky > .header.awa-main-header{'
            . 'padding:var(--awa-space-2,8px) var(--awa-space-4,16px)!important;'
            . 'min-height:calc(var(--awa-header-main-h,68px) + var(--awa-space-4,16px))!important;'
            . 'height:auto!important;max-height:none!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'padding-block:var(--awa-space-2,8px)!important;padding-inline:var(--awa-space-4,16px)!important;'
            . 'min-height:calc(var(--awa-header-main-h,68px) + var(--awa-space-4,16px))!important;'
            . 'height:auto!important;max-height:none!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' .header-wrapper-sticky .header.awa-main-header '
            . ':is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'padding-block:var(--awa-space-2,8px)!important;'
            . 'padding-inline:var(--awa-space-4,16px)!important;'
            . 'min-height:calc(var(--awa-header-main-h,68px) + var(--awa-space-4,16px))!important;'
            . 'height:auto!important;max-height:none!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' :is(.awa-header-search-col,.top-search) :is(.block-search,.block-content){'
            . 'padding:0!important;'
            . 'min-height:var(--awa-touch-min,44px)!important;'
            . 'height:var(--awa-touch-min,44px)!important;max-height:var(--awa-touch-min,44px)!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' :is(.awa-header-search-col,.top-search) :is(form#search_mini_form,form.minisearch){'
            . 'height:var(--awa-touch-min,44px)!important;min-height:var(--awa-touch-min,44px)!important;'
            . 'max-height:var(--awa-touch-min,44px)!important}'
            . $header . ' :is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar,'
            . '.header-control.awa-nav-bar,.awa-nav-bar){'
            . 'padding:0 ' . $safePad . '!important;'
            . 'min-height:var(--awa-nav-bar-h,48px)!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important;overflow:visible!important}'
            . $header . ' :is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar,'
            . '.header-control.awa-nav-bar,.awa-nav-bar) > .container{'
            . 'padding-inline:0!important;min-height:var(--awa-nav-bar-h,48px)!important;'
            . 'height:var(--awa-nav-bar-h,48px)!important;max-height:var(--awa-nav-bar-h,48px)!important;box-sizing:border-box!important;overflow:visible!important}'
            . $wrap . ' :is(.page_footer,.page-footer) .awa-footer-newsletter{'
            . 'padding-inline:' . $safePad . '!important;box-sizing:border-box!important}';
    }

    public static function stripHomeImpeccableTerminalFromHtml(string $html): string
    {
        $pattern = '/<style\\s+id="' . preg_quote(self::HOME_IMPECCABLE_TERMINAL_STYLE_ID, '/') . '"[^>]*>.*?<\\/style>\\s*/is';

        return preg_replace($pattern, '', $html) ?? $html;
    }

    public static function stripFooterTerminalFromHtml(string $html): string
    {
        $pattern = '/<style\\s+id="' . preg_quote(self::FOOTER_STYLE_ID, '/') . '"[^>]*>.*?<\\/style>\\s*/is';

        return preg_replace($pattern, '', $html) ?? $html;
    }

    /**
     * Injeta lock leve do footer antes de </body> quando o cascade-lock do header foi omitido.
     */
    public static function injectFooterOnlyBeforeBodyClose(string $html): string
    {
        if (str_contains($html, self::STYLE_ID)) {
            return $html;
        }

        $html = self::stripFooterTerminalFromHtml($html);
        $html = self::stripHeaderEssentialFromHtml($html);
        $tag = self::footerOnlyStyleTag();
        /* headerEssentialTerminalRules migrado para awa-header-distill-terminal (2026-06-16). */
        $pos = stripos($html, '</body>');
        if ($pos === false) {
            return $html . "\n" . $tag;
        }

        return substr($html, 0, $pos) . $tag . "\n" . substr($html, $pos);
    }

    public static function htmlHasSiteHeader(string $html): bool
    {
        if (self::isAuthShellHtml($html)) {
            return false;
        }

        if (
            str_contains($html, 'data-awa-site-header="true"')
            || str_contains($html, "data-awa-site-header='true'")
        ) {
            return true;
        }

        return preg_match('/<(?:header|div)[^>]*class="[^"]*\bawa-site-header\b/', $html) === 1
            || preg_match('/<[^>]*class="[^"]*\bawa-b2b-promo-bar\b/', $html) === 1
            || preg_match('/<[^>]*class="[^"]*\bheader-wrapper-sticky\b/', $html) === 1;
    }

    /** Só páginas de login/cadastro — evita falso positivo em seletores CSS inline da PLP. */
    public static function isAuthShellHtml(string $html): bool
    {
        return (bool) preg_match(
            '/<body[^>]*class="[^"]*\bb2b-auth-shell\b/',
            $html
        );
    }

    /** Painel B2B logado — CSS próprio; omitir cascade-lock global (~112KB) + styles-m. */
    public static function isB2bAccountOperationalHtml(string $html): bool
    {
        return (bool) preg_match(
            '/<body[^>]*class="[^"]*\b(?:b2b-account-dashboard|awa-account-operational)\b/',
            $html
        );
    }

    /**
     * Catálogo/PDP/busca: lock final de paridade do header.
     * Injeta no fim do body para vencer bundles tardios com !important.
     */
    public static function injectCatalogParityBeforeBodyClose(string $html): string
    {
        $html = preg_replace(
            '/<style\\s+id="' . preg_quote(self::CATALOG_PARITY_STYLE_ID, '/') . '"[^>]*>.*?<\\/style>\\s*/is',
            '',
            $html
        ) ?? $html;

        $tag = '<style id="' . self::CATALOG_PARITY_STYLE_ID . '">' . self::catalogParityRules() . '</style>';
        $pos = stripos($html, '</body>');
        if ($pos === false) {
            return $html . "\n" . $tag;
        }

        return substr($html, 0, $pos) . $tag . "\n" . substr($html, $pos);
    }

    private static function catalogParityRules(): string
    {
        $header = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header';

        return '@media (min-width:992px){'
            . $header . '{'
            . '--awa-header-main-row-h:64px!important;--awa-header-row-h:var(--awa-header-main-row-h)!important;'
            . '--awa-header-nav-h:48px!important;--awa-nav-bar-h:var(--awa-header-nav-h)!important}'
            . $header . ' #header.header-container{height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'padding-block:0!important;margin:0!important}'
            . $header . ' #header.header-container .header-content{height:32px!important;min-height:32px!important;max-height:32px!important;'
            . 'padding-block:0!important;margin:0 auto!important}'
            . $header . ' .header-wrapper-sticky{display:flex!important;flex-direction:column!important;'
            . 'height:auto!important;min-height:118px!important;max-height:none!important;'
            . 'padding-block:0!important;margin:0!important}'
            . $header . ' .header.awa-main-header{'
            . 'height:var(--awa-header-main-row-h,68px)!important;min-height:var(--awa-header-main-row-h,68px)!important;'
            . 'max-height:var(--awa-header-main-row-h,68px)!important;'
            . 'padding-block:0!important;width:min(1280px,calc(100% - 32px))!important;max-width:1280px!important;'
            . 'margin:0 auto!important;padding-inline:0!important}'
            . $header . ' :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){'
            . 'height:var(--awa-header-main-row-h,68px)!important;'
            . 'min-height:var(--awa-header-main-row-h,68px)!important;'
            . 'max-height:var(--awa-header-main-row-h,68px)!important;'
            . 'width:100%!important;max-width:100%!important;padding:0!important;margin:0!important}'
            . $header . ' .header.awa-main-header :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){'
            . 'display:grid!important;grid-template-columns:160px minmax(0,1fr) 316px!important;'
            . 'column-gap:16px!important;align-items:center!important;'
            . 'min-height:var(--awa-header-main-row-h,68px)!important;'
            . 'height:var(--awa-header-main-row-h,68px)!important;'
            . 'max-height:var(--awa-header-main-row-h,68px)!important;'
            . 'width:100%!important;max-width:1280px!important;margin-inline:auto!important;'
            . 'padding-inline:0!important;padding-block:0!important}'
            . $header . ' .awa-header-search-col{'
            . 'padding:0!important;min-height:44px!important;height:44px!important;max-height:44px!important;'
            . 'display:flex!important;align-items:center!important}'
            . $header . ' .awa-header-search-col :is(.block-search,#search_mini_form,form.minisearch){'
            . 'width:100%!important;max-width:none!important}'
            . $header . ' .awa-header-search-col .block-search{'
            . 'min-height:44px!important;height:44px!important;max-height:44px!important}'
            . $header . ' .awa-header-search-col .block-content{'
            . 'padding:0!important;padding-inline:0!important;width:100%!important;max-width:none!important}'
            . $header . ' form#search_mini_form{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;'
            . 'min-height:44px!important;height:44px!important;max-height:44px!important;'
            . 'max-width:none!important;width:100%!important;margin-inline:0!important;overflow:hidden!important}'
            . $header . ' .awa-header-search-col .block-search form#search_mini_form{'
            . 'max-width:none!important;width:100%!important;margin-inline:0!important}'
            . $header . ' #header .awa-header-search-col form#search_mini_form,'
            . $header . ' .header-wrapper-sticky .awa-header-search-col form#search_mini_form{'
            . 'max-width:none!important;width:100%!important;margin-left:0!important;margin-right:0!important;'
            . 'justify-self:stretch!important;place-self:stretch!important}'
            . $header . ' form#search_mini_form .control{'
            . 'min-height:44px!important;height:44px!important;max-height:44px!important}'
            . $header . ' :is(.awa-header-brand-cell,.awa-header-brand-cell .logo,.page-header .logo){'
            . 'width:160px!important;min-width:160px!important;max-width:160px!important}'
            . $header . ' :is(.awa-header-brand-cell .logo img,.page-header .logo img){'
            . 'width:126px!important;max-width:126px!important;max-height:44px!important;height:auto!important;object-fit:contain!important}'
            . $header . ' :is(.awa-header-right-col,.page-header .header.links,.page-header .customer-links,.page-header .header-account,.page-header .login-area,.page-header .account-wrapper){'
            . 'width:316px!important;min-width:316px!important;max-width:316px!important;'
            . 'display:inline-flex!important;align-items:center!important;justify-content:flex-end!important;gap:12px!important}'
            . $header . ' :is(.awa-header-account-prompt,.awa-header-contact-links.awa-header-account-prompt,.page-header .authorization-link,.page-header .customer-welcome,.page-header .header-account .account-link,.page-header .login-area .authorization-link,.page-header .b2b-login-box){'
            . 'max-width:266px!important;min-height:44px!important;height:44px!important;margin:0!important;padding-inline:10px!important}'
            . $header . ' :is(.awa-header-minicart,.awa-header-minicart .mini-carts,.awa-header-minicart .minicart-wrapper,.page-header .minicart-wrapper,.page-header .minicart-wrapper .action.showcart,.awa-header-minicart .action.showcart,.awa-header-minicart .showcart.header-mini-cart){'
            . 'width:44px!important;min-width:44px!important;max-width:44px!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'margin:0!important;padding:0!important;justify-self:end!important;align-self:center!important}'
            // Nav: full-bleed (fundo 100vw) igual à home + 6px de respiro acima do header.
            . $header . ' :is(.header-wrapper-sticky > .header-control.awa-nav-bar,.header-wrapper-sticky > .header-control.header-nav.awa-nav-bar,.header-control.awa-nav-bar,.header-control.header-nav.awa-nav-bar){'
            . 'height:var(--awa-header-nav-h,48px)!important;'
            . 'min-height:var(--awa-header-nav-h,48px)!important;'
            . 'max-height:var(--awa-header-nav-h,48px)!important;'
            . 'display:block!important;width:100vw!important;max-width:100vw!important;'
            . 'margin-inline:calc(50% - 50vw)!important;margin-block:6px 0!important;padding-inline:0!important;box-sizing:border-box!important}'
            // Nav inner: contido em 1280 centralizado, alinhado ao rail do header.
            . $header . ' :is(.header-control.awa-nav-bar > .container,.header-control.header-nav.awa-nav-bar > .container,.header-control.awa-nav-bar .awa-nav-bar__inner,.header-control.header-nav.awa-nav-bar .awa-nav-bar__inner){'
            . 'height:var(--awa-header-nav-h,48px)!important;'
            . 'min-height:var(--awa-header-nav-h,48px)!important;'
            . 'max-height:var(--awa-header-nav-h,48px)!important;'
            . 'width:min(1280px,calc(100% - 32px))!important;max-width:1280px!important;'
            . 'margin-inline:auto!important;padding-inline:0!important;box-sizing:border-box!important}'
            // Busca: esconder label/lupa default do Magento (evita lupa duplicada à esquerda do placeholder).
            . $header . ' .awa-header-search-col :is(.block-search .label,.block-search .block-title,.nested,label.search,label[for="search"]){'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;max-height:0!important;'
            . 'overflow:hidden!important;pointer-events:none!important;position:absolute!important;clip:rect(0,0,0,0)!important}'
            // Busca: input deve preencher os 44px de altura do controle (PLP herdava 36px).
            . $header . ' form#search_mini_form :is(input#search,.control input,input[type="text"]){'
            . 'min-height:44px!important;height:44px!important;max-height:44px!important;box-sizing:border-box!important}'
            // Busca: o botão precisa ser position:relative para conter seus pseudos absolutos (evita ancorar no form).
            . $header . ' form#search_mini_form :is(.actions,.actions button.action.search,button.action.search){'
            . 'position:relative!important}'
            // Busca: suprimir a lupa desenhada via ::before/::after (círculo+cabo) do botão, mantendo só o SVG inline.
            // Isso elimina a lupa duplicada à direita e a lupa deslocada à esquerda (quando o botão era position:static).
            . $header . ' form#search_mini_form button.action.search::before,'
            . $header . ' form#search_mini_form button.action.search::after,'
            . $header . ' form#search_mini_form .actions button.action.search::before,'
            . $header . ' form#search_mini_form .actions button.action.search::after{'
            . 'content:none!important;display:none!important;border:0!important;background:none!important}'
            . $header . ' form#search_mini_form button.action.search svg{'
            . 'display:block!important;margin:0 auto!important}'
            // Departamentos: suprimir o ícone-fonte (::before/::after) do tema, mantendo só o SVG inline (evita hambúrguer duplicado).
            . $header . ' :is(.our_categories.title-category-dropdown,[data-role="awa-vertical-menu-trigger"]) :is(.icon-menu,.vm-icon,.awa-vmenu-trigger-icon)::before,'
            . $header . ' :is(.our_categories.title-category-dropdown,[data-role="awa-vertical-menu-trigger"]) :is(.icon-menu,.vm-icon,.awa-vmenu-trigger-icon)::after{'
            . 'content:none!important;display:none!important;background:none!important}'
            . $header . ' :is(.our_categories.title-category-dropdown,[data-role="awa-vertical-menu-trigger"]) :is(.icon-menu,.vm-icon,.awa-vmenu-trigger-icon) svg{'
            . 'display:block!important;width:18px!important;height:18px!important}'
            // Carrinho: na PDP uma regra de .catalog-product-view forçava o path do SVG (color/stroke vermelho) — some no fundo vermelho.
            // Restaurar o ícone de contorno branco explícito (não currentColor, pois o color do path é sobrescrito p/ vermelho na PDP).
            . $header . ' :is(.awa-header-minicart,.minicart-wrapper) .action.showcart svg,'
            . $header . ' :is(.awa-header-minicart,.minicart-wrapper) .action.showcart svg :is(path,polyline,line,circle,rect,ellipse){'
            . 'color:var(--awa-text-inverse,#fff)!important;fill:none!important;stroke:var(--awa-text-inverse,#fff)!important}'
            . '}';
    }

    public static function injectBeforeBodyClose(string $html): string
    {
        $html = self::stripLegacyFromHtml($html);
        $html = preg_replace(
            '/<script\\s+id="' . preg_quote(self::GUARD_SCRIPT_ID, '/') . '"[^>]*>.*?<\\/script>\\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script\\s+id="awa-catalog-root-height-lock"[^>]*>.*?<\\/script>\\s*/is',
            '',
            $html
        ) ?? $html;

        $pos = stripos($html, '</body>');
        if ($pos === false) {
            return $html . "\n" . self::footerInjection();
        }

        return substr($html, 0, $pos) . self::footerInjection() . "\n" . substr($html, $pos);
    }

    public static function isPresentInHtml(string $html): bool
    {
        if (!str_contains($html, self::STYLE_ID)) {
            return false;
        }

        return str_contains($html, 'max-height:none!important')
            && str_contains($html, 'cubic-bezier(.22,1,.36,1)')
            && str_contains($html, 'min-height:68px!important')
            && str_contains($html, 'grid-template-areas:"toggle brand cart"')
            && str_contains($html, 'max-height:96px!important')
            && str_contains($html, '.header-control.awa-nav-bar')
            && str_contains($html, 'box-shadow:0 4px 12px rgb(15 23 42/10%)')
            && str_contains($html, 'oklch(45% .02 20)!important')
            && str_contains($html, 'background:oklch(99% .002 20)!important')
            && str_contains($html, 'subchildmenu.navigation__inner-list{padding-right:12px!important')
            && str_contains($html, 'oklch(98.5% .004 20)!important')
            && str_contains($html, 'b2b-dashboard-lazy-panel[data-lazy-loaded="loading"]')
            && str_contains($html, '--awa-header-polish-ease:cubic-bezier(.22,1,.36,1)')
            && str_contains($html, '.counter.qty .counter-number')
            && str_contains($html, 'font-weight:650!important')
            && str_contains($html, 'awa-header-primary-nav.menu_primary:has(nav.top-menu:empty)')
            && str_contains($html, 'margin-inline:calc(50% - 50vw)')
            && str_contains($html, 'awa-header-account-prompt__line1')
            && str_contains($html, 'border-radius:10px!important')
            && str_contains($html, '--awa-header-control-h:44px')
            && str_contains($html, '.awa-vmenu-search-icon svg')
            && str_contains($html, 'data-awa-is-home="1"]')
            && str_contains($html, '.awa-search-helper-copy')
            && str_contains($html, 'grid-area:search!important;grid-column:1/-1!important')
            && str_contains($html, 'applyMobileGrid')
            && str_contains($html, ':is(.navigation__submenu,.subchildmenu){width:0!important')
            && str_contains($html, 'contain:layout!important}');
    }

    public static function stripLegacyFromHtml(string $html): string
    {
        foreach (self::LEGACY_STYLE_IDS as $legacyId) {
            $pattern = '/<style\\s+id="' . preg_quote($legacyId, '/') . '"[^>]*>.*?<\\/style>\\s*/is';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        $pattern = '/<style\\s+id="' . preg_quote(self::STYLE_ID, '/') . '"[^>]*>.*?<\\/style>\\s*/is';
        return preg_replace($pattern, '', $html) ?? $html;
    }

    /**
     * Remove apenas style tags de versões depreciadas (v11–v17).
     * Preserva o cascade lock ativo (STYLE_ID = v18) para manter o header estável.
     * Usar em páginas de catálogo onde stripLegacyFromHtml causaria regressão visual.
     */
    public static function stripDeprecatedOnlyFromHtml(string $html): string
    {
        foreach (self::LEGACY_STYLE_IDS as $legacyId) {
            $pattern = '/<style\\s+id="' . preg_quote($legacyId, '/') . '"[^>]*>.*?<\\/style>\\s*/is';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        return $html;
    }
}
