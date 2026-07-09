<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use GrupoAwamotos\Theme\Model\HeaderImpeccableCascadeLockCss;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\HttpInterface;

/**
 * Performance — dedupe CSS async + gate bundles grandes na home (Sprint 3 / PSI).
 */
class OptimizeHeadStylesPlugin
{
    private const HOME_ACTION = 'cms_index_index';
    /** 404/URL inexistente — sem cobertura de defer; cai no fallback total-blocking (BUG-404-CSS). */
    private const NOROUTE_ACTION = 'cms_noroute_index';
    /**
     * Todas as páginas CMS por identificador (Sobre, Contato, FAQ, Termos, Trocas,
     * Formas de Pagamento, Atacado, etc. — 25+ páginas) compartilham este fullAction
     * (Magento\Cms\Controller\Page\View). Mesmo bug do BUG-404-HANG (ver NOROUTE_ACTION
     * abaixo): confirmado via Playwright em about-us/contact-us/faq (renderer do
     * Chromium trava/derruba com awa-master-fix.js ativo, 0/3 runs completam; 3/3 OK
     * bloqueado). Agrupado com NOROUTE_ACTION no mesmo branch de mitigação.
     */
    private const CMS_PAGE_VIEW_ACTION = 'cms_page_view';
    /**
     * Landing page de marketing B2B ("seja-revendedor" via url_rewrite →
     * b2b/marketing/landing). Não é cms_page_view, mas herda o mesmo layout
     * default.xml completo e reproduz o mesmo travamento (confirmado via Playwright:
     * falha intermitente ~1 a cada 3-5 runs com awa-master-fix.js ativo).
     */
    private const B2B_MARKETING_LANDING_ACTION = 'b2b_marketing_landing';
    private const HOME_DENSITY_GRID_FILE = 'awa-home-density-grid-20260611.min.css';
    private const HOME_DENSITY_GRID_QUERY = '?v=20260623-shell1280-r20';
    private const HEADER_CONTRACT_GRID_FILE = 'awa-header-contract-grid-20260626.min.css';
    private const HEADER_CONTRACT_GRID_QUERY = '?v=20260626-phase3d24-r2';
    // MORTO: awa-catalog-density-grid-20260611.min.css → _deprecated/
    private const CATALOG_DENSITY_GRID_FILE = '';
    private const CATALOG_DENSITY_GRID_QUERY = '';

    /** PLP/busca/PDP: stack dedicado §89–§94 — omitir terminal Impeccable (~183KB). */
    private const CATALOG_STACK_ACTIONS = [
        'catalog_category_view',
        'catalogsearch_result_index',
        'catalog_product_view',
    ];

    /** @deprecated use CATALOG_STACK_ACTIONS — mantido para defer não-crítico PLP/busca */
    private const CATALOG_HEADER_ACTIONS = [
        'catalog_category_view',
        'catalogsearch_result_index',
    ];

    /** align-grid async — inline lock cobre container/grid no 1º paint */
    private const DEFER_ALIGN_GRID_ACTIONS = [
        self::HOME_ACTION,
        'catalog_category_view',
        'catalog_product_view',
        self::CART_ACTION,
    ];

    private const CATALOG_STRIP_IMPECCABLE_FRAGMENTS = [
        'awa-impeccable-audit-2026-05-28',
        'awa-commerce-impeccable-refine',
    ];

    /** PLP/busca: omitir bundles globais — stack dedicado (plp-distill + promax).
     * NÃO remover awa-shelf-carousel: PLP/busca ainda renderizam vitrines
     * (.awa-shelf--carousel). Remover só o shelf deixava awa-carousel-contract
     * órfão e quebrava o par CSS emitido por awa-shelf-carousel-loader.phtml. */
    private const CATALOG_STRIP_LEGACY_BUNDLE_FRAGMENTS = [
        'awa-ui-simplify-terminal',
        'awa-head-tail-bundle',
        'awa-bundle-async-distill-lock',
    ];

    /** PLP/busca/PDP: não bloqueiam header/above-fold; defer print/onload (menos TBT no 1º paint). */
    private const CATALOG_DEFER_CSS_FRAGMENTS = [
        'awa-impeccable-audit-2026-05-28',
        'awa-audit-bundle.css',
        'awa-audit-bundle.min.css',
        'social-proof.css',
        'gallery.css',
        'awa-pdp-shell-final',
    ];

    /** Menu v2 — inline critical cobre mobile drawer + preflight dept; folha completa async. */
    private const MENU_DEFER_CSS_FRAGMENTS = [
        // MORTO: 'awa-menu-v2-dept-open-fix' → _deprecated/
    ];

    /** PLP/busca — grid/hero no inline lock + head-preload critical; polish async. */
    private const PLP_DEFER_CSS_FRAGMENTS = [
        'awa-plp-critical-fixes',
    ];

    /**
     * 404 (cms_noroute_index) e páginas CMS genéricas (cms_page_view): rotas não
     * cobertas por nenhum branch específico, então herdavam layout default.xml
     * inteiro com ~30 folhas síncronas (`media="all"`), quase o dobro de requests
     * bloqueantes da home (75 vs 55 recursos) — TTFB da página em si é rápido
     * (~0.3s via PHP-FPM/Varnish, confirmado com curl), mas o head bloqueante causa
     * latência/variância alta de load no navegador real.
     * Lista restrita aos fragmentos já validados como seguros para defer/strip em
     * outras rotas (HOME_GATE_CSS_FRAGMENTS, CATALOG_DEFER_CSS_FRAGMENTS,
     * CATALOG_STRIP_IMPECCABLE_FRAGMENTS, HOME_NEVER_GATE_FRAGMENTS, AUTH_STRIP,
     * CART_STRIP) — evita repetir julgamento novo sobre folhas não testadas
     * (ex.: styles-l.css, awa-cms-shell-final, awa-layout-bundle continuam síncronas).
     */
    private const NOROUTE_DEFER_CSS_FRAGMENTS = [
        'awa-align-grid-terminal-2026-06-11',
        'awa-header-contract-grid-20260626',
        'awa-audit-bundle',
        'awa-commerce-impeccable-refine',
        'awa-defer-global-bundle',
        'awa-focus-visible',
        'awa-head-tail-bundle',
        'awa-impeccable-audit-2026-05-28',
        'awa-structural-fix-2026-05-20',
        'awa-ui-promax-bundle',
        'awa-ui-simplify-terminal',
        'awa-ui-ux-pro-max-header-2026-05-19',
        'awa-visual-bugfix',
    ];

    private const CART_ACTION = 'checkout_cart_index';

    /** Carrinho/checkout: header em modo foco — omitir cascade-lock inline (~170KB + guard script). */
    private const CHECKOUT_FOCUS_ACTIONS = [
        'checkout_cart_index',
        'checkout_index_index',
        'onepagecheckout_index_index',
        'checkout_onepage_success',
    ];

    /** Carrinho: stack dedicado (critical + terminal + polish async); omitir refine global (~42KB). */
    private const CART_STRIP_CSS_FRAGMENTS = [
        'awa-ui-promax-bundle.min.css',
        // MORTO: 'awa-ui-promax-bundle.css' → _deprecated/
        'awa-commerce-impeccable-refine',
        'awa-focus-visible',
    ];

    /** Auth B2B: login.css + critical inline cobrem o shell — omitir bundles globais (~180KB). */
    private const AUTH_STRIP_CSS_FRAGMENTS = [
        'awa-plp-final-polish',
        'awa-ui-promax-bundle',
        'awa-commerce-impeccable-refine',
        'awa-ui-ux-pro-max-header',
        'awa-structural-fix-2026-05-20',
        // MORTO: 'awa-responsive-guard' → _deprecated/
        'custom_default.css',
        'styles-m.css',
        'styles-l.css',
    ];

    /**
     * Painel B2B logado — remover apenas folhas cosméticas/experimentais.
     * Mantemos a base global (styles-m/l, super/layout/theme) para evitar regressões visuais.
     */
    private const B2B_ACCOUNT_STRIP_CSS_FRAGMENTS = [
        'awa-plp-final-polish',
        'awa-audit-bundle',
        'awa-carousel-bundle',
    ];

    /** Fragment sem extensão — detecta tanto .css quanto .min.css no HTML. */
    /** Migrados para styles-l via _extend.less (43.01 / 43.02) — remover do HTML. */
    private const MIGRATED_HEADER_CSS_FRAGMENTS = [
        'awa-header-stack-2026-05-28',
        'awa-header-refine-terminal',
    ];

    private const REFINE_CSS_FRAGMENT = 'awa-commerce-impeccable-refine';

    /**
     * Home — CSS abaixo do fold / bundles grandes (anti-FOUC inline cobre above-fold).
     *
     * @var string[]
     */
    /** Home — só cosmético / below-fold; estrutural fica no head (print/onload stagger). */
    private const HOME_GATE_CSS_FRAGMENTS = [
        'awa-home-hover-lock.min.css',
        'awa-home-hover-lock.css',
        'awa-visual-audit-2026-05-18.min.css',
        'awa-visual-audit-2026-05-18.css',
        'awa-impeccable-audit-2026-05-28.min.css',
        'awa-impeccable-audit-2026-05-28.css',
        // MORTO: 'awa-homepage-hierarchy.min.css' → _deprecated/
        // MORTO: 'awa-home-cosmetic-bundle.min.css' → _deprecated/
        // MORTO: 'awa-home-flex-final.min.css' → _deprecated/
        'awa-home-flex-grid-flow.min.css',
        // MORTO: 'awa-home-modernize-2026.min.css' → _deprecated/
        // MORTO: 'awa-home-b2b-density-terminal.min.css' → _deprecated/
        // MORTO: 'awa-head-preload-home-ext.min.css' → _deprecated/
        'awa-head-tail-bundle.min.css',
        // MORTO: 'awa-home-gate-postaudit-bundle' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        // MORTO: 'awa-home-gate-polish-bundle' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        // MORTO: 'awa-home-gate-polish-cards' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        // MORTO: 'awa-home-gate-polish-type' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        'awa-defer-global-bundle',
        // MORTO: 'awa-grid-container-audit' → _deprecated/
        // MORTO: 'awa-layout-grid-system' → _deprecated/
        'awa-bundle-refinements',
        'awa-home-gate-visual-bundle',
        'awa-ui-simplify-terminal',
        'awa-home-standardize-terminal-wins',
        'awa-align-grid-terminal-2026-06-11',
        'awa-commerce-impeccable-refine',
        'awa-home-density-grid-20260611',
        'awa-header-contract-grid-20260626',
        'awa-footer-terminal-lock-v1',
        'awa-home-critical-stack-2026-06-11',
        'awa-home-critical-stack',
    ];

    /** Folhas que permanecem no head (async stagger) — nunca remover para fila idle. */
    private const HOME_NEVER_GATE_FRAGMENTS = [
        'awa-focus-visible',
        'styles-l.css',
        'awa-carousel-bundle',
        'awa-shelf-carousel',
        'awa-home-body-end-bundle',
        'awa-bundle-async-distill-lock',
        'awa-visual-bugfix.min.css',
        'awa-visual-bugfix.css',
        'awa-ui-ux-pro-max-header-2026-05-19',
        'awa-structural-fix-2026-05-20',
    ];

    /**
     * Scripts legados do tema pai que fazem varreduras globais de DOM.
     *
     * Home, PLP, busca e PDP possuem stacks dedicados de CSS/JS; manter este
     * script nessas rotas reintroduz risco de long tasks e travamentos no
     * Chromium durante carrosséis, filtros e autocomplete.
     *
     * @var string[]
     */
    private const HEAVY_LEGACY_SCRIPT_FRAGMENTS = [
        'awa-master-fix.js',
        'awa-master-fix.min.js',
    ];

    /** Checkout/auth/conta: hidratação B2B não é necessária e aumenta trabalho no main thread. */
    private const CHECKOUT_AUTH_STRIP_SCRIPT_FRAGMENTS = [
        'GrupoAwamotos_B2B/js/b2b-panel-hydrate.min.js',
        'GrupoAwamotos_B2B/js/b2b-panel-hydrate.js',
    ];

    public function __construct(
        private readonly HttpRequest $request,
    ) {
    }

    public function beforeSendResponse(HttpInterface $subject): void
    {
        if (!$subject instanceof \Magento\Framework\HTTP\PhpEnvironment\Response) {
            return;
        }

        $contentType = $subject->getHeader('Content-Type');
        if ($contentType && stripos($contentType->getFieldValue(), 'text/html') === false) {
            return;
        }

        $html = (string) $subject->getBody();
        if ($html === '') {
            return;
        }

        $html = $this->stripRedundantAsyncNoscript($html);
        $html = $this->normalizeCssStaggerOnloadHandlers($html);
        $html = $this->appendVisualFixesCacheBuster($html);
        $html = $this->appendMasterFixCacheBuster($html);
        $fullAction = $this->request->getFullActionName();

        if ($fullAction === self::CART_ACTION) {
            $html = $this->stripStylesheetFragments($html, self::CART_STRIP_CSS_FRAGMENTS);
            $html = $this->injectAlignGridStylesheetIfMissing($html);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12-cart', true);
        }

        if (in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)) {
            $html = $this->injectAlignGridStylesheetIfMissing($html);
        }

        if ($fullAction === 'catalog_product_view') {
            $html = $this->injectAlignGridStylesheetIfMissing($html);
        }

        if ($fullAction === self::HOME_ACTION) {
            $html = $this->stripScriptFragments($html, self::HEAVY_LEGACY_SCRIPT_FRAGMENTS);
            $html = $this->stripStandaloneStylesheetNoscript($html);
            $html = $this->stripStaleHomeCriticalStylesheets($html);
            $html = $this->patchStaleHomeHeaderAssets($html);
            /* Fila gate antes de gateHomeLargeStylesheets — inject vazio após gate quebrava merge (H-opt). */
            $html = $this->injectHomeHeaderTerminalAssets($html);
            $html = $this->injectHomeAlignGridStylesheetsIfMissing($html);
            $html = $this->gateHomeLargeStylesheets($html);
            $html = $this->pruneHomeNeverGateQueueUrls($html);
            $html = $this->removePrintLinksListedInGateQueue($html);
            $html = $this->stripHomeGatedNoscriptFallbacks($html);
            $html = $this->dedupeStylesheetHrefs($html);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12', true);
        } elseif (in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            $html = $this->injectHeaderTerminalStylesheetIfMissing($html);
            $html = $this->injectAlignGridStylesheetIfMissing($html);
            $html = $this->deferCatalogNonCriticalStylesheets($html);
            $html = $this->stripStylesheetFragments($html, self::CATALOG_STRIP_LEGACY_BUNDLE_FRAGMENTS);
            $html = $this->injectPlpDistillStylesheetIfMissing($html);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12-catalog', true);
        } elseif (
            $fullAction === self::NOROUTE_ACTION
            || $fullAction === self::CMS_PAGE_VIEW_ACTION
            || $fullAction === self::B2B_MARKETING_LANDING_ACTION
        ) {
            // BUG-404-HANG / BUG-CMS-HANG: awa-master-fix.js trava/derruba o renderer
            // do Chromium nestas rotas (confirmado via Playwright em cms_noroute_index,
            // cms_page_view — about-us/contact-us/faq — e b2b_marketing_landing —
            // seja-revendedor: falhas intermitentes/consistentes com o script ativo
            // vs 100% OK bloqueado). Já é removido em HOME_ACTION/CATALOG_STACK_ACTIONS
            // pelo mesmo motivo — nunca foi estendido às demais rotas que herdam o
            // layout default.xml completo (25+ páginas institucionais + landing B2B).
            $html = $this->stripScriptFragments($html, self::HEAVY_LEGACY_SCRIPT_FRAGMENTS);
            $html = $this->deferStylesheetsByFragments($html, self::NOROUTE_DEFER_CSS_FRAGMENTS);
            $optimizeTag = match ($fullAction) {
                self::NOROUTE_ACTION => 'v12-noroute',
                self::CMS_PAGE_VIEW_ACTION => 'v12-cms-page',
                default => 'v12-b2b-landing',
            };
            $subject->setHeader('X-Awa-Header-Optimize', $optimizeTag, true);
        }

        if (in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)) {
            $html = $this->stripScriptFragments($html, self::HEAVY_LEGACY_SCRIPT_FRAGMENTS);
            $html = $this->stripStylesheetFragments($html, self::CATALOG_STRIP_IMPECCABLE_FRAGMENTS);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12-catalog-stack', true);
        }

        $html = $this->normalizeRefineStylesheetQuery($html);

        if ($fullAction === 'catalog_product_view') {
            $html = $this->normalizePdpTerminalStylesheets($html);
            $html = $this->deferCatalogNonCriticalStylesheets($html);
        }

        $isAuthFocusPageEarly = in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
            || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html);
        $isB2bAccountFocusEarly = $this->isB2bAccountFocusPage($fullAction, $html);
        if (
            !in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
            && $fullAction !== self::CART_ACTION
            && !$isAuthFocusPageEarly
            && !$isB2bAccountFocusEarly
        ) {
            $html = $this->injectGlobalRefineStylesheetIfMissing($html);
        }

        if (HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)) {
            $html = $this->stripStylesheetFragments($html, self::MIGRATED_HEADER_CSS_FRAGMENTS);
            $html = $this->normalizeHeaderTerminalStylesheetVersion($html);
            if ($fullAction === self::HOME_ACTION) {
                // Home: v10 + critical-home no head; omitir cascade-lock (~112KB) — lock leve no body.
                $html = HeaderImpeccableCascadeLockCss::injectHomeLightBeforeBodyClose($html);
            } elseif (
                in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
                || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html)
            ) {
                // Auth B2B: critical inline cobre layout — só remover cascade (~111KB).
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
            } elseif ($isB2bAccountFocusEarly) {
                // Painel B2B: remove só cascade-lock global; não injeta home-light para não degradar a UI da conta.
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
            } elseif (in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)) {
                // PLP/PDP/busca: injeta cascade lock v18 do header (BUG-01 fix).
                // injectBeforeBodyClose faz strip de legado + injeta v18 + guard script.
                $html = HeaderImpeccableCascadeLockCss::injectBeforeBodyClose($html);
            } elseif (in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)) {
                // Checkout/carrinho: mantém sem guard para preservar o shell foco.
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
                $html = HeaderImpeccableCascadeLockCss::injectFooterOnlyBeforeBodyClose($html);
            } else {
                $html = $this->injectHeaderTerminalFixStyle($html);
            }
        }

        // Converte footer terminal inline (~58KB) para link async externo — footer é below-fold.
        $html = $this->convertFooterCssToAsync($html);

        $html = $this->dedupeStylesheetHrefs($html);
        $isAuthFocusPage = in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
            || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html);
        $isB2bAccountFocusPage = $this->isB2bAccountFocusPage($fullAction, $html);
        if (
            $fullAction !== self::HOME_ACTION
            && !in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)
            && !$isAuthFocusPage
            && !$isB2bAccountFocusPage
        ) {
            $html = $this->deferStylesheetsByFragments($html, self::MENU_DEFER_CSS_FRAGMENTS);
        }
        if (in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            $html = $this->deferStylesheetsByFragments($html, self::PLP_DEFER_CSS_FRAGMENTS);
        }
        if (!$isAuthFocusPage && !$isB2bAccountFocusPage) {
            $html = $this->consolidateAlignGridToBodyTerminal($html, $fullAction);
            if (!in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)) {
                $html = $this->injectSiteShellInlineLock($html, $fullAction);
            }
            $html = $this->injectAlignGridHeaderContainerTerminal($html);
            $html = $this->injectHeaderSimplifyUiTerminalLock($html);
            $html = $this->injectMainContentSkipTargetTabindex($html);
        } else {
            // Auth / painel B2B: critical inline define layout — omitir locks globais + folhas pesadas.
            $html = preg_replace('/<style id="awa-align-grid-inline-lock[^"]*"[^>]*>.*?<\/style>/s', '', $html) ?? $html;
            $html = preg_replace('/<style id="awa-align-grid-header-container-terminal"[^>]*>.*?<\/style>/s', '', $html) ?? $html;
            $html = preg_replace('/<link\s[^>]*awa-align-grid-terminal[^>]*\/?>\s*/i', '', $html) ?? $html;
            $html = preg_replace('/<link\s[^>]*awa-header-contract-grid-20260626[^>]*\/?>\s*/i', '', $html) ?? $html;
            $stripFragments = $isB2bAccountFocusPage
                ? self::B2B_ACCOUNT_STRIP_CSS_FRAGMENTS
                : self::AUTH_STRIP_CSS_FRAGMENTS;
            $html = $this->stripStylesheetFragments($html, $stripFragments);
            $html = preg_replace('/<script[^>]*awa-mirasvit-autocomplete-init[^>]*><\/script>\s*/i', '', $html) ?? $html;
            $html = preg_replace('/<link[^>]+href=["\'][^"\']*\/fonts\/(?:rubik|source-sans-3)\/[^"\']+["\'][^>]*>\s*/i', '', $html) ?? $html;
            $html = preg_replace('/<link[^>]+rel=["\']preload["\'][^>]+as=["\']style["\'][^>]+awa-cookie-consent-fix[^>]*>\s*/i', '', $html) ?? $html;
            if ($isAuthFocusPage) {
                $html = $this->stripAuthPageNoise($html);
                $subject->setHeader('X-Awa-Header-Optimize', 'v12-auth', true);
            } else {
                $html = $this->stripB2bAccountPageNoise($html);
                $subject->setHeader('X-Awa-Header-Optimize', 'v12-b2b-account', true);
            }
        }

        $html = $this->normalizeHeaderGeometryAuthorityInlineStyles(
            $html,
            $fullAction,
            $isAuthFocusPage,
            $isB2bAccountFocusPage
        );

        $html = $this->stripHeavyHeadPreloadInlineStyleOutsideHome(
            $html,
            $fullAction,
            $isAuthFocusPage,
            $isB2bAccountFocusPage
        );

        $html = $this->stripRedundantAsyncNoscript($html);
        $html = $this->stripStylePreloadDuplicates($html);
        $html = $this->injectGlobalFocusVisibleFallback($html);
        $html = $this->injectGlobalWebVitalsRum($html);
          $html = $this->normalizeExcessiveHtmlBodySpecificity($html);

        /* Home: gate após body-terminal — folhas pesadas entram na fila idle (PSI/TBT). */
        if ($fullAction === self::HOME_ACTION) {
            $html = $this->deferStylesheetsByFragments($html, ['custom_default.css']);
            $html = $this->gateHomeLargeStylesheets($html);
            $html = $this->pruneHomeNeverGateQueueUrls($html);
            $html = $this->removePrintLinksListedInGateQueue($html);
            $html = $this->stripHomeGatedNoscriptFallbacks($html);
            $html = $this->dedupeStylesheetHrefs($html);
        }

        $html = $this->injectHeaderVisualAuditFixes($html, $fullAction, $isAuthFocusPage, $isB2bAccountFocusPage);
        $html = $this->injectHomeGapMarginHotfix($html, $fullAction);
        $html = $this->injectVisualBugfixTerminalStyles($html, $fullAction);
        $html = $this->injectHomeCompactSpacingTerminalStyles($html, $fullAction);

        if ($fullAction === self::HOME_ACTION) {
            $html = $this->stripEmptyHomePriceShells($html);
        }

        if (
            $fullAction === self::HOME_ACTION
            || in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
            || in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)
            || $isAuthFocusPage
            || $isB2bAccountFocusPage
        ) {
            $html = $this->stripScriptFragments($html, self::HEAVY_LEGACY_SCRIPT_FRAGMENTS);
        }
        if (
            in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)
            || $isAuthFocusPage
            || $isB2bAccountFocusPage
        ) {
            $html = $this->stripScriptFragments($html, self::CHECKOUT_AUTH_STRIP_SCRIPT_FRAGMENTS);
        }
        $subject->setBody($html);
    }

    /**
     * Home B2B guest — remove wrappers de preco vazios deixados por blocos Rokanthemes.
     * Motivo do ponto de corte: os templates podem vir de cache/preprocessamento, entao a limpeza
     * precisa acontecer na resposta final sem afetar cards que contenham preco/gate real.
     */
    /**
     * Acrescenta ?v=... ao href já renderizado de awa-visual-fixes-2026-06-29-final.min.css.
     *
     * Não pode ser feito via <css src="...?v=..."> no layout XML: isso quebra
     * `pathinfo($path, PATHINFO_EXTENSION)` no core
     * (Magento\Framework\View\Asset\Source::getContentType), fazendo o
     * Magento omitir `rel="stylesheet"` do <link> renderizado — o navegador
     * então ignora o arquivo inteiro (bug real, confirmado ao vivo via curl
     * no HTML bruto antes de qualquer plugin deste tema rodar; não é
     * suposição). Aplicando a query DEPOIS que o core já resolveu o asset e
     * montou o <link> completo (com rel="stylesheet" correto), evitamos o
     * problema e mantemos o cache-busting necessário (pub/static é servido
     * com cache-control immutable de 1 ano).
     *
     * @param string $html
     * @return string
     */
    private function appendVisualFixesCacheBuster(string $html): string
    {
        $needle = 'awa-visual-fixes-2026-06-29-final.min.css';
        if (strpos($html, $needle) === false) {
            return $html;
        }

        $query = HeaderImpeccableCascadeLockCss::VISUAL_FIXES_CSS_QUERY;

        return preg_replace_callback(
            '/<link\s[^>]*href=["\']([^"\']*' . preg_quote($needle, '/') . ')(\?[^"\']*)?["\'][^>]*\/?>/i',
            static function (array $m) use ($query): string {
                if (stripos($m[0], 'rel="stylesheet"') === false && stripos($m[0], "rel='stylesheet'") === false) {
                    return $m[0];
                }
                $newHref = $m[1] . '?v=' . $query;
                return str_replace($m[1] . ($m[2] ?? ''), $newHref, $m[0]);
            },
            $html
        ) ?? $html;
    }

    /**
     * Acrescenta cache-buster ao awa-master-fix.js depois que o HTML foi gerado.
     *
     * O script e servido por static/version... com cache imutavel. Sem query,
     * navegadores que ja carregaram a versao anterior continuam executando o
     * init bloqueante que segura DOMContentLoaded em PLP/PDP.
     *
     * @param string $html
     * @return string
     */
    private function appendMasterFixCacheBuster(string $html): string
    {
        $needle = 'awa-master-fix.js';
        if (strpos($html, $needle) === false) {
            return $html;
        }

        $query = HeaderImpeccableCascadeLockCss::MASTER_FIX_JS_QUERY;

        return preg_replace_callback(
            '/<script\s[^>]*src=["\']([^"\']*' . preg_quote($needle, '/') . ')(\?[^"\']*)?["\'][^>]*><\/script>/i',
            static function (array $m) use ($query): string {
                $newSrc = $m[1] . '?v=' . $query;
                return str_replace($m[1] . ($m[2] ?? ''), $newSrc, $m[0]);
            },
            $html
        ) ?? $html;
    }

    private function stripEmptyHomePriceShells(string $html): string
    {
        return preg_replace('/\s*<div\s+class=["\']info-price["\']>\s*<\/div>/i', '', $html) ?? $html;
    }

    /**
     * Correções visuais finais para rotas auditadas em 2026-07-07.
     *
     * Mantido em <style> separado porque o bloco grande de grid/header passa por
     * normalização de especificidade e acumula regras legadas; este estilo curto
     * precisa ser parseado por inteiro e carregado por último.
     */
    private function injectVisualBugfixTerminalStyles(string $html, string $fullAction): string
    {
        if ($fullAction !== self::HOME_ACTION && !in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            return $html;
        }

        $styleId = 'awa-visual-bugfix-terminal-20260707';
        $scriptId = 'awa-visual-bugfix-terminal-runtime-20260707';
        $html = preg_replace('/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>\s*/is', '', $html) ?? $html;
        $html = preg_replace('/<script id="' . preg_quote($scriptId, '/') . '"[^>]*>.*?<\/script>\s*/is', '', $html) ?? $html;

        $targetScope = 'html body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5,.catalog-category-view,.catalogsearch-result-index) .page-wrapper';
        $catalogScope = 'html body#html-body#html-body#html-body'
            . ':is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper';

        $css = '<style id="' . $styleId . '">'
            . $targetScope . ' :is(footer.page-footer,.page_footer,.page-footer){'
            . 'box-sizing:border-box!important;background:var(--awa-bg-soft,var(--awa-bg,Canvas))!important;'
            . 'background-color:var(--awa-bg-soft,var(--awa-bg,Canvas))!important;color:var(--awa-text,CanvasText)!important;'
            . 'height:auto!important;min-height:0!important;max-width:100%!important;overflow-x:clip!important;padding-block:0!important}'
            . $targetScope . ' :is(footer.page-footer,.page_footer,.page-footer) '
            . ':is(#footer,.footer-container,.footer.content,.footer-top,.footer-middle,.footer-content,.row,.rowFlexMargin,.velaBlock,.velaContent,.awa-footer-newsletter){'
            . 'background:transparent!important;background-color:transparent!important;color:var(--awa-text,CanvasText)!important;min-height:0!important}'
            . $targetScope . ' :is(footer.page-footer,.page_footer,.page-footer) '
            . ':is(a,p,li,span,strong,h2,h3,h4,.footer-title,.awa-footer-title,.footer.links a,.footer-content a){'
            . 'color:var(--awa-text,CanvasText)!important;text-shadow:none!important}'
            . $targetScope . ' :is(.page_footer,.page-footer) .footer-bottom{'
            . 'box-sizing:border-box!important;background:var(--awa-bg,Canvas)!important;background-color:var(--awa-bg,Canvas)!important;'
            . 'border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 14%,Canvas))!important;border-radius:8px!important;'
            . 'color:var(--awa-text,CanvasText)!important;margin-inline:auto!important;max-width:calc(100% - 32px)!important;'
            . 'overflow:hidden!important;padding:clamp(16px,2vw,24px)!important;width:min(100%,1248px)!important;box-shadow:none!important}'
            . $targetScope . ' :is(.page_footer,.page-footer) .footer-bottom>.container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;padding-inline:0!important;width:100%!important}'
            . $catalogScope . ' .products-grid .product-thumb{position:relative!important;overflow:hidden!important}'
            . $catalogScope . ' .products-grid .quickview-link{'
            . 'box-sizing:border-box!important;inline-size:40px!important;block-size:40px!important;max-width:40px!important;'
            . 'min-width:0!important;right:0!important;inset-inline-end:0!important}'
            . $catalogScope . ' .toolbar.toolbar-products .grid-mode-show-type-products,'
            . $catalogScope . ' .products-grid :is(.product-rating,.product-reviews-summary,.rating-summary,.reviews-actions),'
            . $catalogScope . ' .filter-options-content li.item:has(> a[href*="cat=89"]),'
            . $catalogScope . ' .filter-options-content a[href*="cat=89"]{display:none!important}'
            . '@media(max-width:767px){'
            . $targetScope . ' :is(.page_footer,.page-footer) .footer-bottom{max-width:calc(100% - 24px)!important;padding:16px!important}'
            . $catalogScope . ' .toolbar.toolbar-products{display:grid!important;grid-template-columns:minmax(0,1fr)!important;gap:10px!important}'
            . $catalogScope . ' .toolbar.toolbar-products :is(.modes,.toolbar-sorter,.field.limiter,.pages){'
            . 'box-sizing:border-box!important;display:flex!important;flex-wrap:wrap!important;width:100%!important;max-width:100%!important;min-width:0!important}'
            . $catalogScope . ' .toolbar.toolbar-products :is(.sorter-options,select.limiter-options){'
            . 'box-sizing:border-box!important;flex:1 1 160px!important;min-width:0!important;max-width:100%!important}}'
            . '</style>';

        $script = '<script id="' . $scriptId . '">(function(){'
            . '"use strict";'
            . 'function s(e,p,v){if(e){e.style.setProperty(p,v,"important");}}'
            . 'function f(){'
            . 'document.querySelectorAll(".page_footer,footer.page-footer,.page-footer").forEach(function(e){'
            . 's(e,"background","var(--awa-bg,var(--awa-bg-surface,Canvas))");'
            . 's(e,"background-color","var(--awa-bg,var(--awa-bg-surface,Canvas))");'
            . 's(e,"color","var(--awa-text,CanvasText)");s(e,"min-height","0");s(e,"height","auto");s(e,"overflow-x","clip");});'
            . 'document.querySelectorAll(".page_footer #footer,.page-footer #footer,.page_footer .footer-container,.page-footer .footer-container").forEach(function(e){'
            . 's(e,"background","transparent");s(e,"background-color","transparent");s(e,"color","var(--awa-text,CanvasText)");s(e,"min-height","0");});'
            . 'document.querySelectorAll(".page_footer .footer-bottom,.page-footer .footer-bottom").forEach(function(e){'
            . 's(e,"box-sizing","border-box");s(e,"background","var(--awa-bg,Canvas)");s(e,"background-color","var(--awa-bg,Canvas)");'
            . 's(e,"color","var(--awa-text,CanvasText)");s(e,"margin-inline","auto");s(e,"max-width","calc(100% - 32px)");'
            . 's(e,"width","min(100%,1248px)");s(e,"overflow","hidden");s(e,"box-shadow","none");});'
            . 'document.querySelectorAll(".products-grid .product-thumb").forEach(function(e){s(e,"position","relative");s(e,"overflow","hidden");});'
            . 'document.querySelectorAll(".products-grid .quickview-link").forEach(function(e){s(e,"box-sizing","border-box");s(e,"inline-size","40px");'
            . 's(e,"block-size","40px");s(e,"max-width","40px");s(e,"min-width","0");s(e,"right","0");s(e,"inset-inline-end","0");});'
            . '}'
            . 'if(document.readyState!=="loading"){f();}else{document.addEventListener("DOMContentLoaded",f,{once:true});}'
            . 'window.addEventListener("load",f,{once:true,passive:true});window.setTimeout(f,800);window.setTimeout(f,2400);'
            . '}());</script>';

        $injected = preg_replace('/<\/body>/i', $css . "\n" . $script . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Home: compacta o ritmo vertical final sem depender de static deploy.
     *
     * As folhas assíncronas da home ainda carregam locks antigos de min-height e
     * gaps largos para evitar CLS. O estado final fica estável, mas com áreas
     * vazias grandes no mobile. Esta regra terminal mantém a estrutura B2B
     * content-driven e reduz apenas espaçamento artificial.
     */
    private function injectHomeCompactSpacingTerminalStyles(string $html, string $fullAction): string
    {
        if ($fullAction !== self::HOME_ACTION) {
            return $html;
        }

        $styleId = 'awa-home-compact-spacing-terminal-20260707';
        $html = preg_replace('/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>\s*/is', '', $html) ?? $html;

        $scope = 'html body#html-body#html-body#html-body'
            . ':is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper';

        $css = '<style id="' . $styleId . '">'
            . '/* AWA compact home spacing: !important wins legacy async min-height/margin locks that create empty vertical gaps. */'
            . $scope . '{--awa-home-compact-section-pad:clamp(10px,1.4vw,18px);'
            . '--awa-home-compact-tight-gap:clamp(8px,1vw,12px);'
            . '--awa-home-compact-group-gap:clamp(12px,1.6vw,20px)}'
            . $scope . ' .content-top-home :is(.top-home-content,.awa-home-section,.awa-carousel-section,'
            . '.awa-grid-section,.awa-carousel-section--featured,.awa-grid-section--featured,.awa-home-niche-shelves,'
            . '.awa-home-pricing-notice,.awa-product-promo-banners,.top-home-content--category-carousel)'
            . ':not(.top-home-content--above-fold,.awa-hero-b2b-cta){'
            . 'margin-block:0!important;padding-block:var(--awa-home-compact-section-pad)!important}'
            . $scope . ' .content-top-home :is(.awa-section-header,.awa-shelf__header,.rokan-product-heading){'
            . 'gap:8px 12px!important;margin-block:0 8px!important}'
            . $scope . ' .top-home-content--above-fold{margin-block-end:0!important;padding-block-end:clamp(4px,.8vw,8px)!important}'
            . $scope . ' .top-home-content--above-fold :is(.banner-slider,.banner-slider2,.owl-stage-outer,'
            . '.owl-stage,.owl-item,.banner_item,.banner_item_bg,.wrapper_slider){'
            . 'box-sizing:border-box!important;max-width:100%!important;overflow:hidden!important}'
            . $scope . ' .top-home-content--above-fold :is(picture,img){'
            . 'display:block!important;inline-size:100%!important;max-inline-size:100%!important;object-fit:cover!important}'
            . $scope . ' .awa-home-pricing-notice{margin-block:var(--awa-home-compact-tight-gap)!important}'
            . $scope . ' .awa-home-pricing-notice__inner{gap:12px!important;padding:clamp(12px,1.6vw,16px)!important}'
            . $scope . ' .awa-product-promo-banners__grid{gap:var(--awa-home-compact-group-gap)!important}'
            . $scope . ' .awa-product-promo-banners__copy{gap:4px!important;padding:10px 12px!important}'
            . $scope . ' .top-home-content--category-carousel :is(.awa-category-carousel__track,.awa-reel,.owl-stage,.owl-wrapper){'
            . 'gap:var(--awa-home-compact-tight-gap)!important}'
            . $scope . ' .top-home-content--category-carousel :is(.awa-category-carousel__item,.awa-category-carousel__link){'
            . 'min-height:104px!important;padding-block:8px!important}'
            . $scope . ' :is(.awa-carousel-section,.awa-shelf--carousel) '
            . ':is(.product-thumb,.product-thumb-link,.product-item-photo,.product-image-container,.product-image-wrapper){'
            . 'background:var(--awa-bg-soft,var(--awa-bg,Canvas))!important;box-sizing:border-box!important;'
            . 'min-width:0!important;overflow:hidden!important}'
            . $scope . ' .awa-carousel-section :is(.product-thumb,.product-thumb-link,.product-image-wrapper){'
            . 'aspect-ratio:1/1!important;display:grid!important;place-items:center!important}'
            . $scope . ' .awa-carousel-section :is(.product-image-photo,.product-thumb img,.product-item-photo img){'
            . 'block-size:100%!important;display:block!important;inline-size:100%!important;'
            . 'max-block-size:100%!important;max-inline-size:100%!important;object-fit:contain!important}'
            . $scope . ' :is(.awa-carousel-card-slot,.item-product,.content-item-product.awa-product-card,.product-item-info){'
            . 'box-sizing:border-box!important;height:100%!important;min-width:0!important;overflow:hidden!important}'
            . $scope . ' :is(.product-name,a.product-item-link,.awa-shelf--carousel .product-name a){'
            . 'overflow-wrap:anywhere!important;text-overflow:clip!important;text-transform:none!important;word-break:normal!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-container>.container{'
            . 'padding-block:clamp(16px,2vw,24px)!important}'
            . $scope . ' :is(.page_footer,.page-footer) .awa-footer-newsletter{'
            . 'margin-block:0!important;padding-block:clamp(14px,2vw,22px)!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-bottom{'
            . 'height:auto!important;min-height:0!important;padding-block:clamp(14px,2vw,22px)!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-bottom-inner{gap:16px!important;height:auto!important;min-height:0!important}'
            . '@media(max-width:767px){'
            . $scope . '{--awa-home-compact-section-pad:10px;--awa-home-compact-tight-gap:8px;'
            . '--awa-home-compact-group-gap:10px}'
            . $scope . ' .content-top-home :is(.top-home-content,.awa-home-section,.awa-carousel-section,'
            . '.awa-grid-section,.awa-carousel-section--featured,.awa-grid-section--featured,.awa-home-niche-shelves,'
            . '.awa-home-pricing-notice,.awa-product-promo-banners,.top-home-content--category-carousel)'
            . ':not(.top-home-content--above-fold,.awa-hero-b2b-cta){padding-block:10px!important}'
            . $scope . ' :is(.top-home-content--above-fold,'
            . '.top-home-content--above-fold>.banner-slider.banner-slider2){'
            . 'height:auto!important;min-height:clamp(252px,74vw,288px)!important;max-height:none!important}'
            . $scope . ' .top-home-content--above-fold .wrapper_slider.visible-xs,'
            . $scope . ' .top-home-content--above-fold .wrapper_slider.visible-xs :is(.awa-hero-swiper,'
            . '.swiper-wrapper,.swiper-slide,.banner_item,.banner_item_bg){'
            . 'height:clamp(252px,74vw,288px)!important;min-height:clamp(252px,74vw,288px)!important;'
            . 'max-height:clamp(252px,74vw,288px)!important}'
            . $scope . ' .top-home-content--above-fold .wrapper_slider.visible-xs :is(picture,img,.banner_item_bg){'
            . 'block-size:100%!important;height:100%!important;min-height:0!important;object-fit:cover!important}'
            . $scope . ' .awa-hero-b2b-cta{height:auto!important;margin-block:0!important;'
            . 'min-height:0!important;padding-block:10px!important}'
            . $scope . ' .awa-hero-b2b-cta__inner{height:auto!important;min-height:0!important;'
            . 'padding-block:0!important;row-gap:10px!important}'
            . $scope . ' .awa-hero-benefits{gap:8px!important;height:auto!important;min-height:0!important}'
            . $scope . ' .awa-hero-benefits__item{gap:10px!important;min-height:64px!important;padding:10px 12px!important}'
            . $scope . ' .awa-hero-b2b-cta :is(.awa-hero-benefits__copy,.awa-hero-benefits__title,'
            . '.awa-hero-benefits__text,.awa-hero-benefits__link){'
            . '-webkit-line-clamp:unset!important;display:block!important;min-width:0!important;'
            . 'overflow:visible!important;overflow-wrap:anywhere!important;text-overflow:clip!important;white-space:normal!important}'
            . $scope . ' .awa-hero-b2b-cta__actions{margin-top:8px!important}'
            . $scope . ' .top-home-content--category-carousel :is(.awa-category-carousel__track,.awa-reel){'
            . 'align-items:stretch!important;display:flex!important;overflow-x:auto!important;overflow-y:hidden!important;'
            . 'scroll-snap-type:x proximity!important}'
            . $scope . ' .top-home-content--category-carousel :is(.awa-category-carousel__item,.awa-category-carousel__link){'
            . 'flex:0 0 clamp(104px,30vw,128px)!important;max-width:128px!important;'
            . 'min-height:96px!important;min-width:104px!important;padding-block:8px!important;scroll-snap-align:start!important}'
            . $scope . ' .top-home-content--category-carousel .awa-category-carousel__icon{'
            . 'height:44px!important;margin-inline:auto!important;width:44px!important}'
            . $scope . ' .top-home-content--category-carousel '
            . ':is(.awa-category-carousel__label,.awa-category-carousel__title,.awa-category-carousel__name){'
            . '-webkit-line-clamp:2!important;display:-webkit-box!important;overflow:hidden!important;'
            . 'overflow-wrap:anywhere!important;text-overflow:clip!important;white-space:normal!important}'
            . $scope . ' .awa-carousel-section :is(.owl-stage,.owl-wrapper){align-items:stretch!important}'
            . $scope . ' :is(.awa-carousel-card-slot,.item-product,.content-item-product.awa-product-card,.product-item-info){'
            . 'min-height:0!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-container>.container{padding-block:14px!important}'
            . $scope . ' :is(.page_footer,.page-footer) .awa-footer-newsletter{padding-block:14px!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-bottom{height:auto!important;min-height:0!important;'
            . 'padding-block:14px!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-bottom :is(.footer-bottom-inner,.awa-footer-bottom__row,'
            . '.awa-footer-bottom__copyright){height:auto!important;min-height:0!important}'
            . $scope . ' :is(.page_footer,.page-footer) .footer-bottom :is(.footer-bottom-inner,.awa-footer-bottom__row){'
            . 'gap:12px!important;margin:0!important}'
            . $scope . ' :is(.page_footer,.page-footer) .awa-footer-bottom__copyright{'
            . 'margin:0!important;padding:12px!important}'
            . '}'
            . '</style>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Home mobile: neutraliza margin-block legado de 16px entre seções.
     *
     * Evidência runtime (Playwright + CSSOM): awa-visual-fixes-2026-06-29-final.min.css
     * aplica margin-block-start/end:16px em .top-home-content/.awa-home-section no mobile,
     * somando com gap do wrapper e gerando espaçamento visual excessivo.
     */
    private function injectHomeGapMarginHotfix(string $html, string $fullAction): string
    {
        if ($fullAction !== self::HOME_ACTION) {
            return $html;
        }

        $styleId = 'awa-home-gap-hotfix-20260705';
        $html = preg_replace('/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>\s*/is', '', $html) ?? $html;

        $css = '<style id="' . $styleId . '">@layer awa-visual-priority{@media(max-width:767px){'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
            . '.content-top-home :is(.awa-home-section,.top-home-content,.awa-carousel-section--featured,.awa-grid-section--featured,'
            . '.awa-home-niche-shelves,.awa-home-pricing-notice,.awa-product-promo-banners,.top-home-content--category-carousel)'
            . ':not(.top-home-content--above-fold,.awa-hero-b2b-cta){margin-block-start:0!important;margin-block-end:0!important}'
            . '}}</style>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    private function isB2bAccountFocusPage(string $fullAction, string $html): bool
    {
        if (in_array($fullAction, HeaderImpeccableCascadeLockCss::B2B_ACCOUNT_FOCUS_ACTIONS, true)) {
            return true;
        }

        if (
            str_starts_with($fullAction, 'b2b_account_')
            && !in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
        ) {
            return true;
        }

        return HeaderImpeccableCascadeLockCss::isB2bAccountOperationalHtml($html);
    }

    /**
     * Painel B2B logado — remove JS/CSS global desnecessário (quickview, maps, css-gate home).
     */
    private function stripB2bAccountPageNoise(string $html): string
    {
        $html = $this->stripAuthPageNoise($html);
        $html = preg_replace('/<script[^>]*awa-css-gate[^>]*><\/script>\s*/i', '', $html) ?? $html;
        $html = preg_replace('/<script id="awa-css-gate-queue"[^>]*>.*?<\/script>\s*/is', '', $html) ?? $html;

        return $html;
    }


    private function normalizeExcessiveHtmlBodySpecificity(string $html): string
    {
        return preg_replace_callback(
            '/<style\b[^>]*>.*?<\/style>/is',
            static function (array $matches): string {
                return preg_replace('/(?:#html-body){4,}/i', '#html-body#html-body#html-body', $matches[0]) ?? $matches[0];
            },
            $html
        ) ?? $html;
    }

    /**
     * Auth B2B — remove JS/CSS residual do shell global (quickview, ajaxsuite, Page Builder maps, print).
     */
    private function stripAuthPageNoise(string $html): string
    {
        $html = preg_replace('/<link\s[^>]*href=["\'][^"\']*\/css\/print\.css[^"\']*["\'][^>]*\/?>\s*/i', '', $html) ?? $html;
        $html = preg_replace('/<noscript>\s*<link[^>]+awa-cookie-consent-fix[^>]+>\s*<\/noscript>\s*/i', '', $html) ?? $html;
        $html = preg_replace(
            '/<script type="text\/x-magento-init">\s*\{[^}]*"rokanthemes\/ajaxsuite"[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script type="text\/x-magento-init">\s*\{[^}]*quickview-product[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script type="text\/x-magento-init">\s*\{[^}]*"pageCache"[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script[^>]*>\s*require\.config\(\{[^}]*googleMaps[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script[^>]*>\s*require\.config\(\{[^}]*wysiwygAdapter[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script[^>]*>\s*require\.config\(\{[^}]*Magento_PageBuilder\/js\/utils\/map[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<body([^>]*)\sdata-mage-init=\'[^\']*loaderAjax[^\']*\'/i',
            '<body$1',
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Neutralizador terminal — container intermediário do header sem pad (vence themes.min.css tardio).
     */
    private function injectAlignGridHeaderContainerTerminal(string $html): string
    {
        $html = preg_replace('/<style id="awa-align-grid-header-container-terminal"[^>]*>.*?<\/style>/s', '', $html) ?? $html;

        $css = '<style id="awa-align-grid-header-container-terminal">'
            . 'html body#html-body#html-body .page-wrapper .header .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header .header_main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header .header-main .container,'
            . 'html body#html-body#html-body .page-wrapper .header .header_main .container,'
            . 'html body#html-body#html-body .page-wrapper .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header_main>.container,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header_main>.container'
            . '{padding:0!important;padding-inline:0!important;padding-left:0!important;padding-right:0!important}'
            . '</style>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Header UI simplify — style terminal após align-grid/header-container (10× #html-body vence home critical).
     */
    private function injectHeaderSimplifyUiTerminalLock(string $html): string
    {
        $styleId = HeaderImpeccableCascadeLockCss::HEADER_SIMPLIFY_UI_STYLE_ID;
        $html = preg_replace('/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>/s', '', $html) ?? $html;

        if (!HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)) {
            return $html;
        }

        $tag = HeaderImpeccableCascadeLockCss::headerSimplifyUiTerminalStyleTag();
        $injected = preg_replace('/<\/body>/i', $tag . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Last-mile header repair from the 390/768/1440 audit.
     *
     * Uses a final inline layer because this Magento theme emits several cached
     * header locks after static assets; the fix must win the final rendered HTML.
     */
    private function injectHeaderVisualAuditFixes(
        string $html,
        string $fullAction,
        bool $isAuthFocusPage,
        bool $isB2bAccountFocusPage
    ): string {
        $styleId = 'awa-header-visual-audit-fixes-20260630';
        $html = preg_replace('/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>\s*/is', '', $html) ?? $html;

        if (
            $isAuthFocusPage
            || $isB2bAccountFocusPage
            || !HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)
        ) {
            return $html;
        }

        $shell = 'html body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper .awa-site-header[data-awa-header-mode="default"]';
        $auditLock = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header[data-awa-header-mode="default"]';
        $home = 'html body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .awa-site-header[data-awa-header-mode="default"]';
        $plp = 'html body#html-body#html-body#html-body#html-body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index) '
            . '.page-wrapper ';
        $hide = 'display:none!important;visibility:hidden!important;pointer-events:none!important;'
            . 'width:0!important;height:0!important;min-width:0!important;min-height:0!important;'
            . 'max-width:0!important;max-height:0!important;overflow:hidden!important;'
            . 'position:absolute!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important';

        $css = '<style id="' . $styleId . '">'
            . $shell . ' :is(.header-content,.header-wrapper-sticky,.header.awa-main-header,.header_main.awa-main-header-inner-wrap,'
            . '.header-main,.header-main>.container,.header-control.header-nav.awa-nav-bar,.header-control.header-nav.awa-nav-bar>.container,'
            . '.awa-nav-bar__inner){box-sizing:border-box!important}'
            . '@media(min-width:992px){'
            . $shell . ' :is(.header-content,.top-header.awa-b2b-promo-bar,.header-main>.container,'
            . '.header-control.header-nav.awa-nav-bar,.header-control.header-nav.awa-nav-bar>.container){'
            . 'width:min(100%,1280px)!important;max-width:1280px!important;margin-left:auto!important;margin-right:auto!important}'
            . $shell . ' .awa-nav-bar__inner{width:100%!important;max-width:100%!important;margin-left:auto!important;margin-right:auto!important}'
            . $shell . ' .header-wrapper-sticky .awa-main-header__inner.wp-header{'
            . 'width:100%!important;max-width:1280px!important;grid-template-columns:minmax(128px,168px) minmax(420px,1fr) minmax(248px,320px)!important;'
            . 'column-gap:clamp(16px,2vw,28px)!important;padding-inline:clamp(16px,2vw,24px)!important}'
            . $shell . ' .awa-header-search-col{min-width:0!important;width:100%!important;max-width:none!important}'
            . $shell . ' .awa-header-search-col :is(.block-search,.block-content){display:block!important;width:100%!important;max-width:100%!important;min-width:0!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form{'
            . 'align-items:stretch!important;background:var(--awa-bg,var(--awa-white))!important;'
            . 'border:1px solid var(--awa-border-subtle,var(--awa-border))!important;border-radius:var(--awa-radius-sm,6px)!important;'
            . 'box-shadow:none!important;box-sizing:border-box!important;display:grid!important;grid-template-columns:minmax(0,1fr) 48px!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;margin:0!important;overflow:hidden!important;padding:0!important;width:100%!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form :is(.field.search,.field.search .control){'
            // FIX 2026-07-06 (Impeccable "positioned child clipped by overflow container"):
            // overflow:hidden nos dois eixos cortava o dropdown de sugestoes
            // (#search_autocomplete), que fica dentro de .control e precisa cair
            // visivelmente abaixo do input. min-width:0 ja resolve o encolhimento do
            // grid; mantemos overflow-x:hidden e liberamos overflow-y para o dropdown.
            . 'display:block!important;grid-column:1!important;height:44px!important;min-height:44px!important;max-height:44px!important;margin:0!important;min-width:0!important;overflow-x:hidden!important;overflow-y:visible!important;padding:0!important;width:100%!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form input#search{'
            . 'background:transparent!important;border:0!important;box-shadow:none!important;box-sizing:border-box!important;display:block!important;'
            . 'height:44px!important;line-height:44px!important;margin:0!important;min-width:0!important;max-width:100%!important;padding:0 14px!important;position:static!important;width:100%!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form .actions{'
            . 'align-items:stretch!important;display:flex!important;grid-column:2!important;height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'margin:0!important;min-width:48px!important;max-width:48px!important;padding:0!important;position:static!important;width:48px!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search){'
            . 'align-items:center!important;background:var(--awa-primary,var(--awa-red))!important;border:0!important;border-radius:0!important;'
            . 'box-shadow:none!important;color:var(--awa-text-inverse,var(--awa-white))!important;display:flex!important;'
            . 'height:44px!important;justify-content:center!important;margin:0!important;min-height:44px!important;max-height:44px!important;'
            . 'min-width:48px!important;max-width:48px!important;opacity:1!important;padding:0!important;position:static!important;transform:none!important;width:48px!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form button.action.search::before,'
            . $shell . ' .awa-header-search-col form#search_mini_form button.action.search::after{content:none!important;display:none!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form button.action.search svg{display:block!important;margin:0!important;position:static!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"]{'
            . 'align-items:center!important;background:transparent!important;border:0!important;box-shadow:none!important;display:inline-flex!important;'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important;max-width:220px!important;overflow:hidden!important;padding:0!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] :is(.awa-header-account-prompt__text,'
            . '.awa-header-account-prompt__line2,.awa-header-account-prompt__actions){display:inline-flex!important;align-items:center!important;flex-direction:row!important;height:auto!important;min-height:0!important;line-height:1.3!important;max-height:none!important;overflow:visible!important;white-space:nowrap!important}'
            /* .guest é o container que empilha __line1 ("Para ver os preços faça o") sobre
             * __line2 ("Login ou cadastre-se") — precisa ser column, não herdar o row/44px
             * de altura fixa do bloco acima (que ainda serve .text/.line2/.actions, usados
             * como filas internas de uma única linha). */
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__guest{'
            . 'display:inline-flex!important;flex-direction:column!important;align-items:flex-start!important;'
            . 'justify-content:center!important;gap:1px!important;height:44px!important;max-height:44px!important;overflow:visible!important;white-space:nowrap!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__link{height:auto!important;line-height:1.3!important;min-height:0!important;padding-inline:0!important}'
            . $shell . ' .awa-header-account-prompt[data-awa-auth-state="guest"] .awa-header-account-prompt__separator{height:auto!important;line-height:1.3!important;padding-inline:2px!important}'
            . $shell . ' .awa-b2b-promo-close{width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;max-width:44px!important;max-height:44px!important}'
            . '}'
            . '@media(min-width:768px) and (max-width:991px){'
            . $shell . ' .header-wrapper-sticky .awa-main-header__inner.wp-header{'
            . 'display:grid!important;grid-template-columns:minmax(88px,112px) minmax(0,1fr) 44px!important;grid-template-areas:"brand search actions"!important;'
            . 'column-gap:16px!important;padding-inline:16px!important;width:100%!important}'
            . $shell . ' .awa-header-right-col{width:44px!important;min-width:44px!important;max-width:44px!important;justify-content:flex-end!important}'
            . $shell . ' .awa-header-search-col :is(.block-search,.block-content){display:block!important;width:100%!important;max-width:100%!important;min-width:0!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form{display:grid!important;grid-template-columns:minmax(0,1fr) 48px!important;height:44px!important;min-height:44px!important;max-height:44px!important;overflow:hidden!important;padding:0!important;width:100%!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form :is(.field.search,.field.search .control){grid-column:1!important;height:44px!important;min-height:44px!important;max-height:44px!important;margin:0!important;min-width:0!important;overflow:hidden!important;padding:0!important;width:100%!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form input#search{height:44px!important;line-height:44px!important;padding:0 14px!important;position:static!important;width:100%!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form .actions{grid-column:2!important;display:flex!important;height:44px!important;margin:0!important;padding:0!important;position:static!important;width:48px!important;min-width:48px!important;max-width:48px!important}'
            . $shell . ' .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search){display:flex!important;height:44px!important;min-height:44px!important;max-height:44px!important;position:static!important;margin:0!important;width:48px!important;min-width:48px!important;max-width:48px!important;padding:0!important;transform:none!important}'
            . $shell . ' .header-control.header-nav.awa-nav-bar{display:block!important;height:48px!important;min-height:48px!important;max-height:48px!important;padding:0!important;overflow:visible!important}'
            . $shell . ' .header-control.header-nav.awa-nav-bar>.container,' . $shell . ' .awa-nav-bar__inner{display:flex!important;align-items:center!important;height:48px!important;min-height:48px!important;max-height:48px!important;gap:12px!important;overflow:visible!important;width:100%!important;max-width:100%!important;padding-inline:16px!important}'
            . $shell . ' .awa-header-categories.menu_left_home1{display:flex!important;flex:0 0 196px!important;width:196px!important;max-width:196px!important;height:44px!important;overflow:visible!important}'
            . $shell . ' .awa-header-primary-nav.menu_primary:has(nav.top-menu:empty){' . $hide . '}'
            . $shell . ' .awa-nav-quick-links{display:flex!important;align-items:center!important;flex:1 1 auto!important;gap:8px!important;min-width:0!important;overflow:hidden!important}'
            . $shell . ' .awa-nav-quick-links__link{display:inline-flex!important;align-items:center!important;height:44px!important;min-height:44px!important;padding-inline:10px!important;white-space:nowrap!important}'
            . '}'
            . '@media(max-width:767px){'
            . $shell . ' .header-wrapper-sticky .awa-main-header__inner.wp-header{grid-template-areas:"primary" "search" "actions"!important;grid-template-columns:minmax(0,1fr)!important;grid-template-rows:auto auto auto!important;padding:12px!important;row-gap:8px!important}'
            . $shell . ' .awa-header-primary-row{display:grid!important;grid-area:primary!important;grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-areas:"toggle brand cart"!important;align-items:center!important;column-gap:8px!important;width:100%!important}'
            . $shell . ' .awa-header-mobile-toggle{grid-area:toggle!important;justify-self:start!important}'
            . $shell . ' .awa-header-brand-cell{grid-area:brand!important;justify-self:center!important}'
            . $shell . ' .awa-header-cart-link{grid-area:cart!important;justify-self:end!important}'
            . $shell . ' .awa-b2b-promo-bar__lead-short{display:inline!important;visibility:visible!important;width:auto!important;height:auto!important;overflow:visible!important;clip:auto!important;clip-path:none!important}'
            . $shell . ' .awa-b2b-promo-bar__lead-long,' . $shell . ' .awa-b2b-promo-bar__cta-long{display:none!important}'
            . $shell . ' .awa-b2b-promo-bar__text{display:flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;min-width:0!important;max-width:100%!important}'
            . $shell . ' .awa-b2b-promo-bar__cta-short{display:inline!important}'
            . $shell . ' .awa-b2b-promo-close{width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;top:-4px!important;right:0!important}'
            . '}'
            . $shell . ' .awa-header-minicart:has(.minicart-wrapper .showcart)>.awa-header-cart-fallback,'
            . $home . ' .awa-header-minicart:has(.minicart-wrapper .showcart)>.awa-header-cart-fallback{'
            . $hide . '}'
            . $auditLock . '{box-sizing:border-box!important;max-width:min(100%,1280px)!important;width:min(100%,1280px)!important;margin-inline:auto!important}'
            . $auditLock . ' .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search){opacity:1!important}'
            . $auditLock . ' .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search)::before,'
            . $auditLock . ' .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search)::after{content:none!important;display:none!important}'
            . $auditLock . ' .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search) svg{display:block!important;opacity:1!important;visibility:visible!important;color:inherit!important;stroke:currentColor!important}'
            . '@media(min-width:768px) and (max-width:991px){'
            . $auditLock . ' .header-control.header-nav.awa-nav-bar,'
            . $auditLock . ' .header-control.header-nav.awa-nav-bar>.container,'
            . $auditLock . ' .awa-nav-bar__inner{'
            . 'display:flex!important;visibility:visible!important;opacity:1!important;position:relative!important;clip:auto!important;clip-path:none!important;'
            . 'height:48px!important;min-height:48px!important;max-height:48px!important;overflow:visible!important}'
            . $auditLock . ' .awa-nav-quick-links{'
            . 'align-items:center!important;display:flex!important;flex:1 1 auto!important;flex-direction:row!important;flex-wrap:nowrap!important;'
            . 'gap:8px!important;height:44px!important;justify-content:flex-start!important;min-height:44px!important;'
            . 'max-height:44px!important;max-width:none!important;min-width:0!important;opacity:1!important;overflow:hidden!important;position:static!important;'
            . 'visibility:visible!important;width:auto!important;clip:auto!important;clip-path:none!important}'
            . $auditLock . ' .awa-nav-quick-links__link{'
            . 'align-items:center!important;display:inline-flex!important;height:44px!important;min-height:44px!important;opacity:1!important;'
            . 'padding-inline:10px!important;position:static!important;visibility:visible!important;white-space:nowrap!important;width:auto!important}'
            . $auditLock . ' .awa-nav-quick-links__list{'
            . 'align-items:center!important;display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;gap:8px!important;'
            . 'height:44px!important;list-style:none!important;margin:0!important;min-width:0!important;overflow:hidden!important;padding:0!important;width:100%!important}'
            . $auditLock . ' .awa-nav-quick-links__item{'
            . 'display:inline-flex!important;flex:0 1 auto!important;height:44px!important;margin:0!important;min-width:0!important;padding:0!important;position:static!important}'
            . $auditLock . ' .awa-nav-quick-links__link *{opacity:1!important;visibility:visible!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-account-prompt,'
            . $auditLock . ' .header-wrapper-sticky .awa-header-account-prompt *{'
            . $hide . '}'
            . $auditLock . ' .awa-header-categories.menu_left_home1{position:relative!important;overflow:visible!important}'
            . $auditLock . ' .awa-header-categories.menu_left_home1 :is(.awa-nav-categories,.sections.category-dropdown,'
            . '.section-items.nav-sections.category-dropdown-items,#awa-category-navigation,#menu\\.vertical,'
            . '.section-item-content.nav-sections.category-dropdown-item-content,.navigation.verticalmenu.side-verticalmenu){'
            . 'display:contents!important;left:auto!important;right:auto!important;inset:auto!important;margin:0!important;'
            . 'position:static!important;transform:none!important;translate:none!important;width:auto!important;min-width:0!important;'
            . 'max-width:none!important;height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important}'
            . $auditLock . ' .awa-header-categories.menu_left_home1 button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"]{'
            . 'align-items:center!important;display:inline-flex!important;gap:8px!important;height:44px!important;justify-content:flex-start!important;'
            . 'left:auto!important;margin:0!important;min-height:44px!important;max-height:44px!important;min-width:196px!important;'
            . 'max-width:196px!important;overflow:hidden!important;padding-inline:12px!important;position:static!important;transform:none!important;'
            . 'translate:none!important;visibility:visible!important;width:196px!important}'
            . $auditLock . ' .awa-header-categories.menu_left_home1 button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"]::before,'
            . $auditLock . ' .awa-header-categories.menu_left_home1 button.our_categories.title-category-dropdown[data-role="awa-vertical-menu-trigger"]::after{content:none!important;display:none!important}'
            . $auditLock . ' .awa-header-categories.menu_left_home1 .awa-vmenu-trigger-icon{display:inline-flex!important;flex:0 0 22px!important;margin:0!important;width:22px!important}'
            . $auditLock . ' .awa-header-categories.menu_left_home1 .awa-vmenu-trigger-text{display:block!important;flex:1 1 auto!important;min-width:0!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}'
            . '}'
            . '@media(max-width:767px){'
            . $auditLock . ','
            . $auditLock . ' :is(.header-content,.header-wrapper-sticky,.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){'
            . 'box-sizing:border-box!important;margin-inline:0!important;max-width:100%!important;width:100%!important}'
            . $auditLock . ' .header-wrapper-sticky div.awa-main-header__inner.wp-header,'
            . $auditLock . ' .header-wrapper-sticky div.awa-main-header__inner[data-awa-header-row="brand-search"]{'
            . 'align-items:center!important;box-sizing:border-box!important;display:grid!important;gap:8px 8px!important;'
            . 'grid-template:"toggle brand cart" auto "search search search" auto / 44px minmax(0,1fr) 44px!important;'
            . 'height:auto!important;margin:0!important;max-height:none!important;max-width:100%!important;min-height:0!important;overflow:visible!important;'
            . 'padding:8px 12px 10px!important;width:100%!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-primary-row{display:contents!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-mobile-toggle{grid-area:toggle!important;justify-self:start!important;align-self:center!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important;justify-self:center!important;align-self:center!important;min-width:0!important;max-width:160px!important;width:auto!important;height:44px!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;display:block!important;justify-self:stretch!important;min-width:0!important;max-width:100%!important;width:100%!important;height:44px!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-right-col{grid-area:cart!important;align-items:center!important;display:flex!important;justify-content:flex-end!important;justify-self:end!important;min-width:44px!important;max-width:44px!important;width:44px!important;height:44px!important;overflow:visible!important}'
            // BUG-B2B-PANEL-MOBILE-2026-07-07: $auditLock tem especificidade maior que o
            // contrato mais novo (enforcePrimaryRowResponsiveContract) e por isso vence a
            // cascata — precisa do mesmo ajuste para não colapsar o painel B2B (largura 0).
            . $auditLock . ' .header-wrapper-sticky:has(.b2b-status-panel) div.awa-main-header__inner.wp-header,'
            . $auditLock . ' .header-wrapper-sticky:has(.b2b-status-panel) div.awa-main-header__inner[data-awa-header-row="brand-search"]{grid-template-columns:44px minmax(0,1fr) minmax(44px,auto)!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel){min-width:44px!important;max-width:min(150px,40vw)!important;width:auto!important;gap:4px!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel) .b2b-status-panel{width:auto!important;max-width:none!important;overflow:visible!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-cart-link{display:none!important;visibility:hidden!important;pointer-events:none!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-right-col .awa-header-account-prompt{display:none!important;visibility:hidden!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-minicart,'
            . $auditLock . ' .header-wrapper-sticky .minicart-wrapper,'
            . $auditLock . ' .header-wrapper-sticky .minicart-wrapper .showcart{width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col :is(.block-search,.block-content){display:block!important;width:100%!important;max-width:100%!important;min-width:0!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col form#search_mini_form{'
            . 'display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;height:44px!important;min-height:44px!important;'
            . 'max-height:44px!important;overflow:hidden!important;padding:0!important;width:100%!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col form#search_mini_form :is(.field.search,.field.search .control){grid-column:1!important;height:44px!important;margin:0!important;min-width:0!important;overflow:hidden!important;padding:0!important;width:100%!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col form#search_mini_form input#search{height:44px!important;line-height:44px!important;min-width:0!important;padding:0 12px!important;position:static!important;width:100%!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col form#search_mini_form .actions{display:flex!important;grid-column:2!important;height:44px!important;margin:0!important;min-width:44px!important;max-width:44px!important;padding:0!important;position:static!important;width:44px!important}'
            . $auditLock . ' .header-wrapper-sticky .awa-header-search-col form#search_mini_form :is(button.action.search,.action.search){height:44px!important;min-height:44px!important;max-height:44px!important;min-width:44px!important;max-width:44px!important;padding:0!important;position:static!important;transform:none!important;width:44px!important}'
            . $auditLock . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__text{display:flex!important;align-items:center!important;justify-content:center!important;gap:8px!important;min-width:0!important;max-width:calc(100% - 52px)!important;overflow:hidden!important;white-space:nowrap!important}'
            . $auditLock . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__lead{display:inline-flex!important;align-items:center!important;min-width:0!important;max-width:96px!important;overflow:hidden!important;visibility:visible!important}'
            . $auditLock . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__lead-short{display:inline!important;height:auto!important;max-width:96px!important;opacity:1!important;overflow:visible!important;position:static!important;visibility:visible!important;width:auto!important;clip:auto!important;clip-path:none!important}'
            . $auditLock . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__lead-long,'
            . $auditLock . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__cta-long{display:none!important;visibility:hidden!important}'
            . $auditLock . ' #awa-b2b-promo-bar .awa-b2b-promo-bar__cta-short{display:inline!important;opacity:1!important;visibility:visible!important}'
            . '}'
            . '/* FIX 2026-07-07: Impeccable home polish. !important vence locks finais inline/styles-l.css já marcados como important. */'
            . $home . '{display:block!important;width:100%!important;overflow:visible!important}'
            . $home . ' :is(.header-content,#awa-b2b-promo-bar,.top-header.awa-b2b-promo-bar,'
            . '.awa-b2b-promo-bar__inner.awa-b2b-promo-bar__layout){overflow:visible!important;box-sizing:border-box!important}'
            . $home . ' .header-content{height:44px!important;min-height:44px!important;max-height:44px!important;padding-block:0!important}'
            . $home . ' :is(.header-control.header-nav,.header-control.header-nav>.container){'
            . 'padding-inline:16px!important;box-sizing:border-box!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper div.awa-hero-b2b-cta__inner.container{padding-inline:16px!important;box-sizing:border-box!important}'
            . '@media(max-width:767px){@layer awa-header-first-paint-lock{'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .top-home-content--above-fold .awa-hero__viewport .awa-hero__pagination{'
            . 'inset:auto 16px -44px 16px!important;top:auto!important;bottom:-44px!important;height:44px!important;'
            . 'min-height:44px!important;max-height:44px!important;display:flex!important;align-items:center!important;'
            . 'justify-content:center!important;gap:8px!important;z-index:2!important}'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .top-home-content--above-fold .wrapper_slider.visible-xs .text-banner{'
            . 'top:-24px!important;bottom:auto!important;height:48px!important;max-height:48px!important;'
            . 'min-height:0!important;padding:0 12px!important;display:flex!important;align-items:center!important;'
            . 'justify-content:center!important;overflow:hidden!important;pointer-events:none!important;'
            . 'visibility:hidden!important;opacity:0!important}'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .top-home-content--above-fold .wrapper_slider.visible-xs .text-banner '
            . ':is(.slide-title,.slide-desc,.button-slider){display:none!important}'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .top-home-content--above-fold .awa-hero__viewport .awa-hero__pagination .swiper-pagination-bullet{'
            . 'width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important;'
            . 'max-width:44px!important;max-height:44px!important;padding:0!important;margin:0!important;'
            . 'box-sizing:border-box!important;background:transparent!important;border:0!important;box-shadow:none!important;opacity:1!important}'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .top-home-content--above-fold .awa-hero__viewport .awa-hero__pagination .swiper-pagination-bullet::before{'
            . 'width:8px!important;height:8px!important;min-width:8px!important;min-height:8px!important;'
            . 'box-shadow:0 0 0 1px rgba(183,51,55,.22)!important}'
            . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
            . '.page-wrapper .top-home-content--above-fold .awa-hero__viewport .awa-hero-pause-btn{'
            . 'top:auto!important;bottom:-44px!important;left:auto!important;right:16px!important;width:44px!important;'
            . 'height:44px!important;min-width:44px!important;min-height:44px!important;max-width:44px!important;'
            . 'max-height:44px!important;z-index:3!important;opacity:.85!important}'
            . '/* FIX 2026-07-07: PLP mobile first fold compact. */'
            . $plp . '.category-view-move{height:auto!important;min-height:0!important;padding:0!important;margin:0 0 8px!important}'
            . $plp . '.category-view-move .awa-category-hero{height:72px!important;min-height:72px!important;max-height:72px!important;margin:0!important}'
            . $plp . '.category-view-move .awa-category-hero__bg-image,'
            . $plp . '.category-view-move .awa-category-hero__overlay{height:72px!important;min-height:72px!important;max-height:72px!important}'
            . $plp . '.category-view-move .awa-category-hero__content{height:72px!important;min-height:72px!important;max-height:72px!important;'
            . 'padding:8px 12px!important;display:flex!important;flex-direction:column!important;justify-content:center!important}'
            . $plp . '.category-view-move .awa-category-hero__title{font-size:17px!important;line-height:1.2!important;margin:0!important}'
            . $plp . '.category-view-move .awa-category-hero__count{font-size:12px!important;line-height:1.3!important;margin:4px 0 0!important}'
            . $plp . '.mst_categorySearch{display:none!important}'
            . '/* FIX 2026-07-08: PLP mobile toolbar — grid estável (cc2a40: absolute center + sr-only pages vazava). */'
            . $plp . '.shop-tab-title{height:auto!important;min-height:0!important;max-height:none!important;margin:0 0 8px!important;overflow:visible!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products{height:auto!important;min-height:0!important;max-height:none!important;'
            . 'padding:8px!important;margin:0!important;position:relative!important;overflow:visible!important;display:flex!important;flex-direction:column!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products>.center{display:grid!important;grid-template-columns:minmax(0,1fr)!important;'
            . 'grid-template-rows:auto auto!important;gap:8px!important;position:static!important;top:auto!important;left:auto!important;right:auto!important;'
            . 'width:100%!important;height:auto!important;min-height:0!important;max-height:none!important;align-items:stretch!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .modes{position:static!important;width:100%!important;height:auto!important;'
            . 'min-width:0!important;min-height:44px!important;max-width:100%!important;max-height:none!important;margin:0!important;padding:0!important;'
            . 'display:flex!important;flex-wrap:wrap!important;align-items:center!important;gap:8px!important;overflow:visible!important;'
            . 'clip:auto!important;clip-path:none!important;opacity:1!important;visibility:visible!important;pointer-events:auto!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .modes .modes-label{visibility:visible!important;opacity:1!important;'
            . 'width:auto!important;height:auto!important;min-height:36px!important;max-width:100%!important;white-space:normal!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .pages{display:none!important;visibility:hidden!important;'
            . 'width:0!important;height:0!important;overflow:hidden!important;pointer-events:none!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .pages :is(.pages-items,.item,a,strong){display:none!important;visibility:hidden!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .toolbar-sorter.sorter{display:flex!important;align-items:center!important;'
            . 'justify-content:space-between!important;gap:8px!important;width:100%!important;height:auto!important;min-height:44px!important;'
            . 'max-height:none!important;padding:0!important;margin:0!important;grid-row:2!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .sorter-label{height:auto!important;min-height:0!important;font-size:11px!important;'
            . 'line-height:1.2!important;margin:0!important;white-space:normal!important;flex:0 0 auto!important}'
            . $plp . '.shop-tab-title .toolbar.toolbar-products .sorter-options{flex:1 1 auto!important;min-width:0!important;height:40px!important;'
            . 'min-height:40px!important;max-height:44px!important;margin:0!important;padding:6px 10px!important}'
            . $plp . '.awa-plp-b2b-gate-banner{display:block!important;padding:8px!important;margin-bottom:8px!important;min-height:0!important;height:auto!important}'
            . $plp . '.awa-plp-b2b-gate-banner__badge{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;'
            . 'clip-path:inset(50%)!important;opacity:0!important;visibility:hidden!important;pointer-events:none!important}'
            . $plp . '.awa-plp-b2b-gate-banner__actions{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;'
            . 'gap:8px!important;margin:0!important;height:44px!important}'
            . $plp . '.awa-plp-b2b-gate-banner__action{height:44px!important;min-height:44px!important;padding:8px!important;'
            . 'font-size:12px!important;line-height:1.1!important;white-space:nowrap!important}'
            . '}}'
            . '</style>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * WCAG 2.4.1 — skip-link precisa de alvo focável (#maincontent nas páginas não-home).
     */
    private function injectMainContentSkipTargetTabindex(string $html): string
    {
        if (!str_contains($html, 'id="maincontent"')) {
            return $html;
        }

        if (preg_match('/<main\s[^>]*id="maincontent"[^>]*\btabindex\s*=/i', $html)) {
            return $html;
        }

        $patched = preg_replace(
            '/<main(\s[^>]*id="maincontent"[^>]*)>/i',
            '<main$1 tabindex="-1">',
            $html,
            1
        );

        return is_string($patched) ? $patched : $html;
    }

    /**
     * Lock inline — última camada da cascata; eixo 1280px site-wide (vence page-containers async).
     */
    private function injectSiteShellInlineLock(string $html, string $fullAction): string
    {
        $html = preg_replace('/<style id="awa-align-grid-inline-lock[^"]*"[^>]*>.*?<\/style>/s', '', $html) ?? $html;

        $css = '<style id="awa-align-grid-inline-lock-20260626-phase3d22b">'
            . 'html body#html-body#html-body{--awa-grid-shell-max:min(100%,1280px);--awa-grid-container-pad:16px;'
            . '--awa-grid-card-gap:12px;--awa-grid-col-gap:12px;--awa-grid-section-gap:16px}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.header-main,.header_main)>.container,html body#html-body#html-body .page-wrapper #header'
            . ' :is(.header-main,.header_main)>.container{padding-inline:0!important;max-width:100%!important;'
            . 'width:100%!important;margin-inline:0!important;box-sizing:border-box!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.awa-main-header__inner,.awa-header-inner,.header-content){box-sizing:border-box!important;'
            . 'margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.rokanthemes-onepagecheckout)'
            . ' .page-wrapper :is(.page-main.container,#maincontent.page-main.container,#maincontent#maincontent.page-main.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:100%!important;padding-inline:16px!important;width:min(100%,1280px)!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer.footer-container,'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer .footer-container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . 'footer.page-footer .page_footer>#footer.footer-container{'
            . 'box-sizing:border-box!important;margin-left:auto!important;margin-right:auto!important;'
            . 'margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-left:16px!important;padding-right:16px!important;width:100%!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer.footer-container>.container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;'
            . 'padding-inline:0!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom{'
            . 'box-sizing:border-box!important;margin:0!important;margin-left:0!important;margin-right:0!important;'
            . 'max-width:100%!important;padding:0!important;padding-left:0!important;padding-right:0!important;'
            . 'width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom>.container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;'
            . 'padding:0!important;width:min(100%,1280px)!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom .footer-bottom-inner{'
            . 'box-sizing:border-box!important;display:grid!important;gap:10px!important;'
            . 'grid-template-columns:minmax(0,1fr)!important;justify-items:stretch!important;'
            . 'margin:0!important;max-width:none!important;padding:12px 16px!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'box-sizing:border-box!important;display:grid!important;'
            . 'grid-template-columns:minmax(88px,118px) minmax(220px,1fr) minmax(180px,240px)!important;'
            . 'align-items:center!important;gap:10px 18px!important;justify-self:center!important;'
            . 'margin:0 auto!important;max-width:760px!important;min-width:0!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row>[class*="col-"]{'
            . 'box-sizing:border-box!important;float:none!important;margin:0!important;max-width:none!important;'
            . 'min-width:0!important;padding-inline:0!important;width:auto!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .awa-footer-bottom__copyright{'
            . 'box-sizing:border-box!important;justify-self:center!important;margin:0!important;'
            . 'max-width:1040px!important;width:100%!important}'
            . '@media(max-width:991px){'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'grid-template-columns:minmax(0,1fr)!important;justify-items:center!important;'
            . 'max-width:100%!important;text-align:center!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){justify-content:center!important}'
            . '}'
            . 'html body#html-body#html-body.checkout-cart-index .page-wrapper .cart-container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body.checkout-cart-index .page-wrapper :is(.page-main.container,#maincontent.page-main.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view){'
            . 'box-sizing:border-box!important;display:block!important;margin:0!important;min-width:0!important;'
            . 'position:static!important;transform:none!important;width:100%!important;max-width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view) .page-wrapper{'
            . 'box-sizing:border-box!important;display:block!important;min-width:0!important;width:100%!important;max-width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)'
            . ' .page-wrapper .page-main.container .breadcrumbs{box-sizing:border-box!important;margin-inline:0!important;'
            . 'max-width:100%!important;padding-inline:0!important;width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)'
            . ' .page-wrapper .nav-breadcrumbs{min-height:0!important;max-height:none!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.awa-utility-bar>.container,.top-header .container){box-sizing:border-box!important;'
            . 'margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . '@media (max-width:991px){html body#html-body#html-body .page-wrapper .awa-site-header,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header :is(.header.awa-main-header,.header-main,.header_main,.top-header){'
            . 'width:100%!important;max-width:100%!important;margin-inline:0!important;padding-inline:0!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header :is(.header-main,.header_main)>.container,'
            . 'html body#html-body#html-body .page-wrapper #header :is(.header-main,.header_main)>.container{'
            . 'padding-inline:0!important;max-width:100%!important;width:100%!important;margin-inline:0!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.awa-main-header__inner,.awa-header-inner,.header-content),html body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index)'
            . ':not(.rokanthemes-onepagecheckout) .page-wrapper :is(.page-main.container,#maincontent.page-main.container),'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer .footer-container{'
            . 'max-width:100%!important;padding-inline:16px!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view){'
            . 'box-sizing:border-box!important;display:block!important;margin:0!important;min-width:0!important;'
            . 'position:static!important;transform:none!important;width:100%!important;max-width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view) .page-wrapper{'
            . 'box-sizing:border-box!important;display:block!important;min-width:0!important;width:100%!important;max-width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)'
            . ' .page-wrapper :is(.page_footer,.page-footer) :is(.awa-footer-newsletter,.awa-footer-newsletter>.container,.awa-newsletter-wrapper,.awa-newsletter-form-container){'
            . 'box-sizing:border-box!important;max-width:min(100%,calc(100vw - 32px))!important;min-width:0!important;'
            . 'width:min(100%,calc(100vw - 32px))!important;margin-inline:auto!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)'
            . ' .page-wrapper :is(.page_footer,.page-footer) #newsletter-validate-detail{'
            . 'box-sizing:border-box!important;max-width:100%!important;min-width:0!important;width:100%!important}}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header'
            . ' #awa-minicart-panel .minicart-wrapper:not(.active):not(.is-open):not(.show) .block-minicart:not(._active){'
            . 'overflow:hidden!important}'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header'
            . ' #awa-minicart-panel .minicart-wrapper:is(.active,.is-open,.show) .block-minicart,'
            . 'html body#html-body#html-body#html-body .page-wrapper .awa-site-header'
            . ' #awa-minicart-panel .minicart-wrapper .block-minicart._active{overflow:visible!important}';

        if ($fullAction === self::HOME_ACTION) {
            $css .= 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home{padding-inline:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home'
                . ' .ayo-home5-wrapper--template-driven :is('
                . '.top-home-content:not(.top-home-content--above-fold)>.container,'
                . '.top-home-content--category-carousel.awa-home-section>.container),'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home'
                . ' :is(.top-home-content.awa-home-section,.top-home-content.awa-carousel-section)>.container,'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home .awa-hero-b2b-cta__inner.container,'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-home-pricing-notice>.container'
                . '{box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
                . 'padding-inline:16px!important;width:100%!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home .ayo-home5-wrapper--template-driven'
                . ' .top-home-content--category-carousel.awa-home-section{width:100%!important;max-width:100%!important;margin-inline:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home :is(.top-home-content.awa-home-section,.top-home-content.awa-carousel-section):not(.top-home-content--above-fold)'
                . '{padding-inline:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home :is(.awa-section-header,.awa-shelf__header){padding-inline:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .awa-carousel-section :is(.owl-stage,.owl-wrapper,.owl-wrapper-outer){align-items:stretch!important;display:flex!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .awa-carousel-section .product-thumb .product-thumb-link{aspect-ratio:1/1!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .awa-carousel-section :is(.content-item-product,.item-product){max-height:none!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' :is(.top-home-content.awa-home-section,.top-home-content.awa-carousel-section):not(.top-home-content--above-fold)'
                . '{padding-block:clamp(12px,1.5vw,20px)!important;margin-block:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' :is(.awa-section-header,.awa-shelf__header){display:flex!important;flex-wrap:wrap!important;'
                . 'justify-content:space-between!important;align-items:flex-end!important;'
                . 'gap:clamp(8px,1vw,16px)!important;margin-block-end:12px!important}'
                . '@media(max-width:767px){html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)'
                . ' .page-wrapper :is(.page-main.container,#maincontent.page-main.container){padding-inline:16px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5){overflow-x:clip!important}}';
        }

        if (in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            $css .= 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .page-main>.columns.layout.layout-2-col.row{margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .page-main>.columns.layout.layout-2-col>[class*=col-]{float:none!important;width:auto!important;'
                . 'max-width:none!important;padding-inline:0!important;min-width:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .columns{'
                . 'display:grid!important;column-gap:12px!important;row-gap:0!important;'
                . 'grid-template-columns:minmax(220px,260px) minmax(0,1fr)!important;align-items:start!important;'
                . 'margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' :is(.products-grid .product-items,.products-grid ul.row.product-grid,'
                . '.products-grid ul.container-products-switch,ul.row.product-grid.container-products-switch){'
                . 'display:grid!important;gap:12px!important;'
                . 'grid-template-columns:repeat(4,minmax(0,1fr))!important;align-items:stretch!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .products-grid .item-product{'
                . 'display:flex!important;flex-direction:column!important;align-items:stretch!important;'
                . 'height:100%!important;min-width:0!important;width:100%!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .products-grid .item-product :is(.product-info,.product-item-details){'
                . 'display:flex!important;flex-direction:column!important;flex:1 1 auto!important;'
                . 'justify-content:flex-start!important;align-items:stretch!important;min-height:0!important;width:100%!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .products-grid .item-product .info-price{'
                . 'margin-block-start:auto!important;margin-top:auto!important;width:100%!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' :is(.page-title-wrapper .page-title,.awa-category-hero__title){'
                . 'font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important;'
                . 'font-weight:600!important;font-size:clamp(1.25rem,1rem + 1vw,1.5rem)!important;line-height:1.3!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .toolbar.toolbar-products{'
                . 'display:flex!important;flex-wrap:wrap!important;align-items:center!important;'
                . 'justify-content:space-between!important;gap:8px 12px!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .toolbar.toolbar-products .field.limiter,'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .toolbar.toolbar-products .limiter .control{'
                . 'box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;'
                . 'flex:0 0 auto!important;min-width:0!important;width:auto!important;max-width:160px!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .toolbar.toolbar-products select.limiter-options{'
                . 'box-sizing:border-box!important;min-width:72px!important;width:auto!important;max-width:96px!important}'
                . '@media(max-width:1199px){html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' :is(.products-grid .product-items,.products-grid ul.row.product-grid,ul.row.product-grid.container-products-switch)'
                . '{grid-template-columns:repeat(3,minmax(0,1fr))!important}}'
                . '@media(max-width:991px){html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .columns{'
                . 'grid-template-columns:minmax(0,1fr)!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' :is(.products-grid .product-items,.products-grid ul.row.product-grid,ul.row.product-grid.container-products-switch)'
                . '{grid-template-columns:repeat(2,minmax(0,1fr))!important}}'
                /* ≤479px: manter 2 colunas (anti-regressão §7 — não forçar 1 col no UL real) */
                . '@media(max-width:479px){html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' :is(.products-grid .product-items,.products-grid ul.row.product-grid,ul.row.product-grid.container-products-switch)'
                . '{grid-template-columns:repeat(2,minmax(0,1fr))!important}}';

            $catalogScope = 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body'
                . ':is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper';
            $css .= $catalogScope . ' .toolbar.toolbar-products .grid-mode-show-type-products{display:none!important}'
                . $catalogScope . ' .toolbar.toolbar-products{min-width:0!important;overflow:visible!important}'
                . $catalogScope . ' .toolbar.toolbar-products :is(.modes,.toolbar-sorter,.field.limiter,.pages){min-width:0!important;max-width:100%!important}'
                . $catalogScope . ' .toolbar.toolbar-products :is(.sorter-label,.limiter-text,.limiter-label){white-space:normal!important;overflow-wrap:anywhere!important}'
                . $catalogScope . ' .products-grid :is(.product-rating,.product-reviews-summary,.rating-summary,.reviews-actions){'
                . 'display:none!important;margin:0!important;padding:0!important;width:0!important;height:0!important;overflow:hidden!important}'
                . $catalogScope . ' .products-grid :is(.item-product,.product-item,.product-info,.product-item-info,.product-item-details){'
                . 'box-sizing:border-box!important;min-width:0!important;max-width:100%!important;overflow:hidden!important}'
                . $catalogScope . ' .products-grid :is(.product.name,.product-item-name,.product-item-link,.product-sku,.awa-b2b-price-lock){'
                . 'max-width:100%!important;overflow-wrap:anywhere!important;word-break:normal!important}'
                . $catalogScope . ' .products-grid .product-thumb{position:relative!important;overflow:hidden!important}'
                . $catalogScope . ' .products-grid :is(.actions-secondary,.product-extra-link,.quickview-product){'
                . 'box-sizing:border-box!important;max-width:44px!important;min-width:0!important;overflow:hidden!important}'
                . $catalogScope . ' .products-grid .quickview-link{'
                . 'box-sizing:border-box!important;inline-size:40px!important;block-size:40px!important;max-width:40px!important;min-width:0!important}'
                . $catalogScope . ' .filter-options-content li.item:has(> a[href*="cat=89"]){display:none!important}'
                . $catalogScope . ' .filter-options-content a[href*="cat=89"]{display:none!important}'
                . $catalogScope . ' :is(.page_footer,.page-footer){'
                . 'box-sizing:border-box!important;background:var(--awa-bg-soft,var(--awa-bg,Canvas))!important;'
                . 'color:var(--awa-text,CanvasText)!important;height:auto!important;min-height:0!important;'
                . 'margin-inline:0!important;max-width:100%!important;overflow-x:clip!important;padding-block:0!important;width:100%!important}'
                . $catalogScope . ' :is(.page_footer,.page-footer) '
                . ':is(#footer,.footer-container,.footer.content,.footer-top,.footer-middle,.footer-content,.row,.rowFlexMargin,.velaBlock,.velaContent,.awa-footer-newsletter){'
                . 'background:transparent!important;background-color:transparent!important;color:var(--awa-text,CanvasText)!important;min-height:0!important}'
                . $catalogScope . ' :is(.page_footer,.page-footer) :is(h2,h3,h4,.footer-title,.awa-footer-title,a,p,li,span,.footer.links a,.footer-content a){'
                . 'color:var(--awa-text,CanvasText)!important}'
                . $catalogScope . ' :is(.page_footer,.page-footer) .footer-bottom{'
                . 'box-sizing:border-box!important;background:var(--awa-bg,Canvas)!important;border:1px solid var(--awa-border,color-mix(in srgb,CanvasText 14%,Canvas))!important;'
                . 'border-radius:8px!important;color:var(--awa-text,CanvasText)!important;margin:0 auto!important;max-width:calc(100% - 32px)!important;'
                . 'overflow:hidden!important;padding:clamp(16px,2vw,24px)!important;width:min(100%,1248px)!important;box-shadow:none!important}'
                . $catalogScope . ' :is(.page_footer,.page-footer) .footer-bottom>.container{'
                . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;padding-inline:0!important;width:100%!important}'
                . '@media(max-width:767px){' . $catalogScope . ' .toolbar.toolbar-products{'
                . 'display:grid!important;grid-template-columns:minmax(0,1fr)!important;align-items:stretch!important;gap:10px!important}'
                . $catalogScope . ' .toolbar.toolbar-products :is(.modes,.toolbar-sorter,.field.limiter,.pages){'
                . 'box-sizing:border-box!important;display:flex!important;flex-wrap:wrap!important;width:100%!important}'
                . $catalogScope . ' .toolbar.toolbar-products :is(.sorter-options,select.limiter-options){'
                . 'box-sizing:border-box!important;flex:1 1 160px!important;min-width:0!important;max-width:100%!important}'
                . $catalogScope . ' :is(.page_footer,.page-footer) .footer-bottom{max-width:calc(100% - 24px)!important;padding:16px!important}}';
        }

        if ($fullAction === 'catalog_product_view') {
            $css .= 'html body#html-body.catalog-product-view .page-wrapper'
                . ' :is(.nav-breadcrumbs,#maincontent.page-main.container,.page-main.container){'
                . 'box-sizing:border-box!important;margin-inline:auto!important;'
                . 'max-width:min(100%,1280px)!important;padding-inline:16px!important;width:100%!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .page-main.container{'
                . 'box-sizing:border-box!important;margin-inline:auto!important;'
                . 'max-width:min(100%,1280px)!important;padding-inline:16px!important;width:100%!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .page-title-wrapper .page-title{'
                . 'font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important;'
                . 'font-weight:600!important;font-size:clamp(1.375rem,1.1rem + 1.2vw,1.75rem)!important;'
                . 'line-height:1.25!important;margin-block-end:12px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main{'
                . 'display:flex!important;flex-direction:column!important;gap:12px!important;min-width:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main'
                . ' :is(.product-info-stock-sku,.product-info-price){margin:0!important;padding:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-info-price{'
                . 'display:grid!important;gap:8px!important;margin-block-end:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart{'
                . 'border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;'
                . 'background:var(--awa-bg,#ffffff)!important;padding:8px!important;margin:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart .fieldset{'
                . 'display:grid!important;grid-template-columns:minmax(84px,108px) minmax(0,1fr)!important;'
                . 'gap:8px!important;align-items:end!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart'
                . ' :is(.field.qty,.actions){margin:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form{'
                . 'margin:0!important;padding:8px!important;border:1px solid var(--awa-border,#e5e5e5)!important;'
                . 'border-radius:8px!important;background:var(--awa-bg,#ffffff)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form{display:grid!important;grid-template-columns:minmax(84px,108px) minmax(0,1fr)!important;'
                . 'gap:8px!important;align-items:end!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form>:is(.attr-product,.actions){margin:0!important;min-width:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form>.actions .action.tocart{width:100%!important;max-width:none!important;'
                . 'display:inline-flex!important;justify-content:center!important}'
                . '@media(max-width:991px){html body#html-body.catalog-product-view .page-wrapper .main-detail>.row{'
                . 'display:flex!important;flex-direction:column!important;gap:12px!important;align-items:stretch!important;'
                . 'grid-template-columns:none!important;margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>:is(.col-md-6,.col-sm-6){'
                . 'flex:0 0 100%!important;width:100%!important;max-width:100%!important;min-width:0!important}}'
                . '@media(min-width:992px){html body#html-body.catalog-product-view .page-wrapper .main-detail>.row{'
                . 'display:flex!important;flex-wrap:nowrap!important;gap:12px!important;align-items:flex-start!important;'
                . 'grid-template-columns:none!important;margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>.col-md-6.col-sm-6.col-xs-12:first-child{'
                . 'flex:1 1 54%!important;max-width:54%!important;min-width:0!important;width:auto!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>.col-md-6.col-sm-6.col-xs-12:last-child{'
                . 'flex:1 1 46%!important;max-width:46%!important;min-width:0!important;width:auto!important}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>.col-md-6:last-child .product-info-main,'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main.detail-info{'
                . 'width:100%!important;max-width:100%!important;min-width:0!important;box-sizing:border-box!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form{'
                . 'align-items:end!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form>.attr-product{'
                . 'align-self:end!important;margin-bottom:0!important;padding-bottom:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form>.actions{'
                . 'align-self:end!important;margin-bottom:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form'
                . ' :is(.info-qty input.qty,.actions .action.tocart){height:44px!important;box-sizing:border-box!important}'
                . '@media(max-width:767px){html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart .fieldset{'
                . 'grid-template-columns:minmax(0,1fr)!important;gap:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form{grid-template-columns:minmax(0,1fr)!important;gap:8px!important}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.info.detailed{'
                . 'box-sizing:border-box!important;margin-top:clamp(12px,2vw,20px)!important;margin-bottom:clamp(8px,1.5vw,12px)!important;padding:12px!important;'
                . 'border:1px solid var(--awa-border)!important;border-radius:8px!important;background:var(--awa-bg)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items{'
                . 'box-sizing:border-box!important;margin-top:0!important;margin-bottom:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.title{'
                . 'margin:0!important;min-height:40px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.title>.switch{'
                . 'min-height:40px!important;padding:8px 12px!important;border-radius:6px 6px 0 0!important;'
                . 'font-size:13px!important;font-weight:700!important;line-height:1.35!important;letter-spacing:.01em!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.content{'
                . 'box-sizing:border-box!important;padding:12px!important;border:1px solid var(--awa-border)!important;'
                . 'border-radius:0 8px 8px 8px!important;background:var(--awa-bg)!important;font-size:13px!important;line-height:1.45!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items'
                . ' :is(table,table.additional-attributes,.additional-attributes){width:100%!important;max-width:100%!important;'
                . 'font-size:13px!important;line-height:1.4!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items'
                . ' :is(th,td){padding:8px 12px!important;border-color:var(--awa-border)!important;vertical-align:top!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items th{'
                . 'font-weight:700!important;color:var(--awa-text)!important;background:color-mix(in srgb,var(--awa-primary) 5%,var(--awa-bg))!important}'
                . '@media(min-width:768px){html body#html-body#html-body#html-body.catalog-product-view .page-wrapper'
                . ' .product.data.items table.additional-attributes :is(th,td),'
                . 'html body#html-body#html-body#html-body.catalog-product-view .page-wrapper'
                . ' .product.data.items .additional-attributes :is(th,td){display:table-cell!important;'
                . 'padding:8px 12px!important;margin:0!important;line-height:1.4!important}}'
                . '@media(max-width:767px){html body#html-body.catalog-product-view .page-wrapper .product.info.detailed{'
                . 'margin-top:12px!important;padding:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.content{padding:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items :is(th,td){padding:8px!important}}'
                . '@media(max-width:991px){html body#html-body.catalog-product-view .page-wrapper'
                . ' :is(.gallery-placeholder,.fotorama-item,.fotorama){'
                . 'min-height:min(374px,calc(100vw - 16px))!important;box-sizing:border-box!important}}'
                . '@media(min-width:992px){html body#html-body.catalog-product-view .page-wrapper .product.media .fotorama__stage{'
                . 'min-height:0!important;height:clamp(340px,32vw,400px)!important;max-height:clamp(340px,32vw,400px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.media'
                . ' :is(.fotorama__wrap,.fotorama-item,.gallery-placeholder){max-height:clamp(440px,38vw,500px)!important;min-height:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.media .fotorama__nav-wrap{'
                . 'margin-top:6px!important;padding-block:4px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.media .fotorama__nav__frame{height:56px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media{min-height:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media .fotorama__stage{'
                . 'height:clamp(280px,24vw,340px)!important;max-height:clamp(280px,24vw,340px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media'
                . ' :is(.fotorama__wrap,.fotorama-item,.gallery-placeholder){max-height:clamp(380px,30vw,440px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media'
                . ' .fotorama__nav__frame{height:52px!important}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .col-main>.product-view{margin-bottom:0!important}'
                // FIX 2026-07-06: a margem negativa de -140px sobrepunha o buy-box
                // (QTD + Adicionar ao Carrinho) por 116px em produtos B2B — o gap que
                // ela fechava (~301px) encolheu para ~24px depois que caixa de
                // compatibilidade/prova social/aviso de preço passaram a ocupar a
                // coluna do buy-box. Confirmado via Playwright (overlap de 116px
                // idêntico em 3/3 produtos testados). Regra removida.
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related{'
                . 'margin-top:clamp(16px,2.5vw,24px)!important;margin-bottom:clamp(16px,2.5vw,24px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related.awa-shelf--carousel .awa-carousel__viewport{'
                . 'display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;overflow-x:auto!important;'
                . 'width:100%!important;gap:0!important;padding-block:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related.awa-shelf--carousel .awa-carousel__track{'
                . 'display:flex!important;flex-direction:row!important;gap:12px!important;width:max-content!important;min-width:100%!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related>.awa-owl-nav,'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related>.awa-owl-progress{'
                . 'display:none!important}'
                . '@media(pointer:coarse){html body#html-body.catalog-product-view .page-wrapper .product-info-main'
                . ' :is(.action.primary,#product-addtocart-button){min-height:44px!important;min-width:44px!important;'
                . 'display:inline-flex!important;align-items:center!important;justify-content:center!important}}';
        }

        if ($fullAction === self::CART_ACTION) {
            $css .= 'html body#html-body.checkout-cart-index .page-wrapper .page-title-wrapper .page-title{'
                . 'font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important;'
                . 'font-weight:600!important;font-size:clamp(1.25rem,1rem + 1vw,1.5rem)!important;line-height:1.3!important}'
                . '@media(min-width:768px){html body#html-body.checkout-cart-index .page-wrapper .cart-container{'
                . 'display:grid!important;grid-template-columns:minmax(0,1fr) minmax(300px,360px)!important;'
                . 'gap:clamp(20px,2.5vw,28px)!important;align-items:start!important}}'
                . '@media(max-width:767px){html body#html-body.checkout-cart-index .page-wrapper .cart-container{'
                . 'display:flex!important;flex-direction:column!important;gap:clamp(16px,4vw,24px)!important}'
                . 'html body#html-body.checkout-cart-index .page-wrapper .cart-container .cart-summary{order:-1!important}}'
                . 'html body#html-body#html-body.checkout-cart-index .page-wrapper .awa-site-header .awa-header-search-col '
                . 'form#search_mini_form{display:flex!important;align-items:stretch!important;flex-wrap:nowrap!important}'
                . 'html body#html-body#html-body.checkout-cart-index .page-wrapper .awa-site-header .awa-header-search-col '
                . 'form#search_mini_form .field.search{flex:1 1 auto!important;min-width:0!important;width:auto!important}'
                . 'html body#html-body#html-body.checkout-cart-index .page-wrapper '
                . ':is(.awa-cart-empty__trust,.awa-cart-empty__category-chips){'
                . 'box-sizing:border-box!important;display:flex!important;flex-wrap:wrap!important;max-width:100%!important;min-width:0!important;'
                . 'overflow:visible!important;white-space:normal!important;overflow-wrap:anywhere!important}'
                . 'html body#html-body#html-body.checkout-cart-index .page-wrapper '
                . ':is(.awa-cart-empty__trust,.awa-cart-empty__category-chips)>*{min-width:0!important;max-width:100%!important}';
        }

        // §APF-T — busca header mobile compacta (polish 2026-06-12, última camada inline)
        $css .= 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col :is(form#search_mini_form,form.minisearch){display:flex!important;align-items:stretch!important;'
            . 'height:36px!important;min-height:36px!important;max-height:36px!important;overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .field.search{display:flex!important;flex:1 1 auto!important;'
            . 'min-width:0!important;height:36px!important;min-height:36px!important;max-height:36px!important;margin:0!important;padding:0!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .field.search>label.label{border:0!important;'
            . 'clip:rect(0,0,0,0)!important;height:1px!important;margin:-1px!important;overflow:hidden!important;'
            . 'padding:0!important;position:absolute!important;width:1px!important;white-space:nowrap!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .field.search .control{display:flex!important;flex:1 1 auto!important;'
            . 'min-width:0!important;width:auto!important;height:36px!important;min-height:36px!important;max-height:36px!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form input#search{position:static!important;display:block!important;'
            . 'width:100%!important;min-width:0!important;height:36px!important;min-height:36px!important;'
            . 'max-height:36px!important;margin:0!important;box-sizing:border-box!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .actions{display:flex!important;align-items:stretch!important;'
            . 'align-self:stretch!important;flex:0 0 48px!important;width:48px!important;min-width:48px!important;'
            . 'max-width:48px!important;height:36px!important;min-height:36px!important;max-height:36px!important;'
            . 'margin:0!important;padding:0!important;position:static!important;inset:auto!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form :is(.action.search,button.action.search){display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important;flex:0 0 48px!important;'
            . 'width:48px!important;min-width:48px!important;max-width:48px!important;'
            . 'height:36px!important;min-height:36px!important;max-height:36px!important;align-self:stretch!important;'
            . 'position:static!important;inset:auto!important;margin:0!important;padding:0!important;box-sizing:border-box!important}'
            . '@media(max-width:767px){'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar{height:32px!important;'
            . 'min-height:32px!important;max-height:32px!important;overflow:visible!important;background:var(--awa-primary)!important;'
            . 'color:var(--awa-on-primary,white)!important;width:100vw!important;max-width:100vw!important;'
            . 'margin-inline:calc(50% - 50vw)!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__inner{height:32px!important;'
            . 'min-height:32px!important;max-height:32px!important;display:flex!important;align-items:center!important;'
            . 'justify-content:center!important;position:relative!important;padding-inline:48px!important;overflow:visible!important;'
            . 'background:var(--awa-primary)!important;color:var(--awa-on-primary,white)!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar '
            . ':is(.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__lead-short,'
            . '.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__cta-long){display:none!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__cta-short{display:block!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__cta{display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important;min-height:44px!important;height:44px!important;'
            . 'padding:8px 10px!important;margin-block:-8px!important;line-height:1.2!important;white-space:nowrap!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-close{position:absolute!important;'
            . 'right:6px!important;top:-6px!important;width:44px!important;height:44px!important;min-width:44px!important;'
            . 'min-height:44px!important;display:flex!important;align-items:center!important;justify-content:center!important;'
            . 'border:0!important;background:transparent!important;color:var(--awa-on-primary,white)!important;box-shadow:none!important}'
            . '}';
            $css .= '@media(max-width:991px){html body#html-body#html-body#html-body#html-body .page-wrapper '
                . '.awa-site-header .awa-b2b-promo-close{'
                . 'box-sizing:border-box!important;width:44px!important;height:44px!important;min-width:44px!important;'
                . 'min-height:44px!important;max-width:44px!important;max-height:44px!important;display:flex!important;'
                . 'align-items:center!important;justify-content:center!important;padding:0!important;line-height:1!important}}';

            $css .= 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-b2b-promo-bar :is(strong.awa-b2b-promo-bar__cta-long,.awa-b2b-promo-bar__cta-long){'
                . 'color:var(--awa-on-primary,var(--awa-text-inverse,CanvasText))!important;text-shadow:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(form#search_mini_form,#search_mini_form,.awa-site-header form#search_mini_form) {'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '#search_mini_form :is(.field.search,.control,.search-autocomplete,.mst-searchautocomplete__autocomplete){'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar){'
                . 'box-sizing:border-box!important;padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar)>.container{'
                . 'box-sizing:border-box!important;padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.content-top-home,.content-top-home .ayo-home5-wrapper,.content-top-home .ayo-home5-wrapper--template-driven){'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.awa-product-promo-banners__item,a.awa-product-promo-banners__item){'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . '@media(max-width:767px){html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper :is(.top-home-content--above-fold,.top-home-content--above-fold>.banner-slider.banner-slider2){'
                . 'box-sizing:border-box!important;height:auto!important;min-height:336px!important;max-height:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .wrapper_slider.visible-xs{min-height:288px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .wrapper_slider.visible-xs .awa-hero-swiper{min-height:288px!important;height:288px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-product-promo-banners{overflow-x:clip!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-product-promo-banners :is(.container,.awa-product-promo-banners__grid){'
                . 'box-sizing:border-box!important;inline-size:100%!important;max-inline-size:100%!important;min-inline-size:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-product-promo-banners__grid{display:flex!important;flex-wrap:nowrap!important;'
                . 'overflow-x:auto!important;overflow-y:hidden!important;overscroll-behavior-x:contain!important;scrollbar-width:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-product-promo-banners__grid::-webkit-scrollbar{display:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper :is(.awa-product-promo-banners__item,a.awa-product-promo-banners__item){'
                . 'box-sizing:border-box!important;flex:0 0 min(82vw,320px)!important;inline-size:min(82vw,320px)!important;'
                . 'max-inline-size:min(82vw,320px)!important;min-inline-size:0!important;overflow:hidden!important;'
                . 'overflow-x:hidden!important;overflow-y:hidden!important}}'
                . '@media(max-width:767px){html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper :is(.awa-hero-swiper__nav,.swiper-button-prev,.swiper-button-next){'
                . 'box-sizing:border-box!important;width:44px!important;height:44px!important;min-width:44px!important;'
                . 'min-height:44px!important;max-width:44px!important;max-height:44px!important;padding:0!important;'
                . 'display:grid!important;place-items:center!important;line-height:1!important;transform:translateY(-50%)!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper :is(.awa-hero-swiper__nav,.swiper-button-prev,.swiper-button-next) '
                . ':is(svg,span){box-sizing:border-box!important;width:20px!important;height:20px!important;'
                . 'min-width:20px!important;min-height:20px!important;max-width:20px!important;max-height:20px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper{position:relative!important;overflow:hidden!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper .swiper-pagination{position:absolute!important;left:16px!important;'
                . 'right:16px!important;bottom:8px!important;top:auto!important;height:60px!important;margin:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper .awa-hero-pause-btn{position:absolute!important;right:16px!important;'
                . 'bottom:8px!important;top:auto!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper .swiper-slide{display:none!important;visibility:hidden!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper:not(.swiper-initialized) .swiper-slide:first-child,'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-swiper .swiper-slide.swiper-slide-active{display:block!important;visibility:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper :is(.awa-hero-inline-cta,.awa-hero-inline-cta a,.awa-hero-b2b-cta,'
                . '.awa-hero-b2b-cta__inner,.awa-hero-benefits,'
                . '.awa-hero-benefits__item,.awa-hero-benefits__copy,.awa-hero-benefits__title,'
                . '.awa-hero-benefits__text,.awa-hero-benefits__link){'
                . 'box-sizing:border-box!important;min-height:0!important;max-height:none!important;height:auto!important}'
                /* opt23 CLS (2026-07-05): a regra acima zera min-height do item p/ deixar 100%
                 * content-driven, mas isso nao evita o shift — a causa raiz real (confirmada via
                 * diff de TODAS as propriedades computadas, nao so as obvias) e flex-direction:
                 * o item nasce com flex-direction:column (icone empilhado sobre o texto, ~127px)
                 * e so muda p/ flex-direction:row (icone ao lado do texto, ~72px) quando a folha
                 * "final" entre as ~8 concorrentes (density-grid, corporate-density-grid,
                 * impeccable-layout, align-grid-terminal, themes.min.css, standardize-terminal-
                 * wins) termina de carregar — shift isolado de CLS ~0.98 em mobile (390px), a
                 * maior fonte de CLS da home. padding/gap/icon-size nao mudavam (medido antes),
                 * so a direção do flex — por isso fixar so min-height/padding nao resolvia.
                 * Fixando flex-direction:row (+ demais props de layout) nesta mesma posicao/
                 * especificidade sincrona (corpo do HTML, vence por ordem+especificidade sobre
                 * as folhas async concorrentes) elimina a corrida em vez de so mitiga-la. */
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta .awa-hero-benefits{align-items:start!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta .awa-hero-benefits__item{'
                . 'min-height:72px!important;padding:12px!important;gap:10px!important;'
                . 'flex-direction:row!important;align-items:flex-start!important;align-self:start!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta .awa-hero-benefits__icon{'
                . 'flex:0 0 44px!important;width:44px!important;height:44px!important;'
                . 'min-width:44px!important;min-height:44px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-inline-cta .action.primary,'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta .action.primary{'
                . 'min-height:44px!important;display:inline-flex!important;align-items:center!important;'
                . 'justify-content:center!important;padding-block:0!important;padding-inline:18px!important;'
                . 'box-sizing:border-box!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta{padding-block:12px!important;margin-block:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta{min-height:clamp(540px,145vw,620px)!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-b2b-cta__inner{min-height:clamp(512px,138vw,592px)!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-benefits{display:grid!important;gap:8px!important;min-height:clamp(420px,118vw,500px)!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-benefits__icon{box-sizing:border-box!important;display:inline-grid!important;'
                . 'place-items:center!important;width:44px!important;height:44px!important;min-width:44px!important;'
                . 'min-height:44px!important;max-width:44px!important;max-height:44px!important;padding:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) '
                . '.page-wrapper .awa-hero-benefits__icon :is(svg,img){box-sizing:border-box!important;width:22px!important;'
                . 'height:22px!important;min-width:22px!important;min-height:22px!important;max-width:22px!important;'
                . 'max-height:22px!important}}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.page_footer,.page-footer) :is(.footer-bottom,.footer-bottom-inner,.awa-footer-bottom__row,.awa-footer-bottom__copyright){'
                . 'content-visibility:visible!important;contain-intrinsic-size:unset!important;contain:none!important}'
                . '@media(max-width:767px){html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.page_footer,.page-footer) .footer-bottom{display:block!important;visibility:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.page_footer,.page-footer) .footer-bottom .footer-bottom-inner{display:grid!important;min-height:540px!important;visibility:visible!important}}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__item :is(picture,img){border-radius:inherit!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__media{box-sizing:border-box!important;display:grid!important;place-items:center!important;'
                . 'aspect-ratio:463/349!important;background:color-mix(in srgb,var(--awa-primary) 6%,Canvas)!important;color:var(--awa-primary)!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__media :is(svg,img,picture){max-width:72px!important;max-height:72px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__copy{display:grid!important;gap:4px!important;padding:12px!important;'
                . 'color:var(--awa-text,var(--awa-text-primary,CanvasText))!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__copy strong{font-size:15px!important;font-weight:800!important;line-height:1.25!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__copy span{color:var(--awa-text-muted,var(--awa-muted,CanvasText))!important;'
                . 'font-size:12px!important;line-height:1.35!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.content-top-home :is(.awa-section-header,.awa-shelf__header,.rokan-product-heading){'
                . 'align-items:flex-end!important;gap:8px 16px!important;margin-bottom:16px!important;text-align:left!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.content-top-home :is(.awa-section-header__title,.awa-shelf__title,.rokan-product-heading h2,.rokan-product-heading strong){'
                . 'line-height:1.2!important;text-wrap:balance!important;text-transform:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.content-top-home :is(.awa-section-header__subtitle,.awa-shelf__subtitle){'
                . 'color:var(--awa-text-muted,var(--awa-muted,CanvasText))!important;line-height:1.45!important;max-width:64ch!important;text-wrap:pretty!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.top-home-content--category-carousel :is(.awa-category-carousel__item,.awa-category-carousel__link){'
                . 'align-items:center!important;box-sizing:border-box!important;display:flex!important;flex-direction:column!important;'
                . 'gap:8px!important;justify-content:flex-start!important;min-height:118px!important;text-align:center!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.top-home-content--category-carousel .awa-category-carousel__icon{display:grid!important;margin-inline:auto!important;place-items:center!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.top-home-content--category-carousel :is(.awa-category-carousel__label,.awa-category-carousel__title,.awa-category-carousel__name){'
                . 'display:-webkit-box!important;font-size:13px!important;font-weight:700!important;line-height:1.25!important;'
                . 'max-width:18ch!important;overflow:hidden!important;text-transform:none!important;text-wrap:balance!important;'
                . '-webkit-box-orient:vertical!important;-webkit-line-clamp:2!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.awa-carousel-card-slot,.item-product,.content-item-product.awa-product-card) '
                . ':is(h3.product-name,.product-name,a.product-item-link){line-height:1.35!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel :is(.product-name a,a.product-item-link){display:-webkit-box!important;'
                . '-webkit-box-orient:vertical!important;-webkit-line-clamp:2!important;overflow:hidden!important;'
                . 'min-height:calc(1.35em * 2)!important;text-wrap:pretty!important;text-transform:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel .awa-b2b-sku{align-items:center!important;color:var(--awa-text-muted,var(--awa-muted,CanvasText))!important;'
                . 'display:inline-flex!important;font-size:11px!important;font-weight:600!important;gap:3px!important;line-height:1.3!important;'
                . 'margin-top:6px!important;max-width:100%!important;min-height:18px!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel .awa-b2b-sku__label{color:var(--awa-text-muted,var(--awa-muted,CanvasText))!important;font-weight:700!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel .product-rating{align-items:center!important;display:flex!important;'
                . 'min-height:18px!important;margin-top:6px!important;max-width:100%!important;overflow:hidden!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel :is(.product-reviews-summary,.rating-summary,.reviews-actions){'
                . 'align-items:center!important;display:inline-flex!important;line-height:1!important;margin:0!important;min-height:18px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel :is(.reviews-actions,.review-count){font-size:11px!important;line-height:1!important;'
                . 'max-width:9ch!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-hero-inline-cta{display:flex!important;justify-content:center!important;padding:12px 16px 4px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-hero-inline-cta :is(a,.action.primary){box-sizing:border-box!important;display:inline-flex!important;'
                . 'align-items:center!important;justify-content:center!important;min-height:44px!important;padding-inline:18px!important;'
                . 'border-radius:999px!important;font-weight:700!important;text-align:center!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-hero-b2b-cta__actions{display:flex!important;justify-content:center!important;margin-top:14px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-hero-b2b-cta__button{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;'
                . 'justify-content:center!important;min-height:44px!important;padding-inline:20px!important;text-align:center!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-card-view-product{box-sizing:border-box!important;display:inline-flex!important;align-items:center!important;'
                . 'justify-content:center!important;min-height:44px!important;width:100%!important;margin-top:10px!important;'
                . 'border:1px solid var(--awa-border,var(--awa-border-color,color-mix(in srgb,CanvasText 12%,Canvas)))!important;border-radius:8px!important;'
                . 'color:var(--awa-primary)!important;font-size:13px!important;font-weight:700!important;text-decoration:none!important;'
                . 'text-transform:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-card-view-product:is(:hover,:focus-visible){border-color:var(--awa-primary)!important;'
                . 'background:color-mix(in srgb,var(--awa-primary) 8%,Canvas)!important;text-decoration:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel :is(.item-product,.product-item-info,.content-item-product,.awa-product-card){'
                . 'display:flex!important;flex-direction:column!important;height:100%!important;min-height:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel :is(.product-info,.product-item-details){display:flex!important;'
                . 'flex:1 1 auto!important;flex-direction:column!important;min-height:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-shelf--carousel .awa-card-view-product{margin-top:auto!important}'
                . 'html body#html-body#html-body .page-wrapper .security-seals__link{display:inline-flex!important;'
                . 'align-items:center!important;justify-content:center!important;min-height:44px!important}';
        $css .= 'html body#html-body#html-body .page-wrapper :is([id^="awa-vertical-menu-"],.navigation.verticalmenu,.navigation.verticalmenu.side-verticalmenu,.side-verticalmenu>ul.togge-menu){'
            . 'box-shadow:0 2px 8px color-mix(in srgb,CanvasText 8%,transparent)!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) p.awa-footer-atendimento__store-address{'
            . 'line-height:1.45!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) p.awa-footer-copyright__disclaimer{'
            . 'line-height:1.45!important;margin-inline:auto!important;max-width:min(110ch,100%)!important;text-wrap:pretty!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .awa-footer-bottom__copyright p.awa-footer-copyright__disclaimer{'
            . 'line-height:1.45!important;margin-inline:auto!important;max-width:min(110ch,100%)!important;width:auto!important;text-wrap:pretty!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta strong.awa-b2b-promo-bar__cta-long{'
            . 'color:Canvas!important;text-shadow:none!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header #search_mini_form{overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header .header-control.header-nav.awa-nav-bar{'
            . 'padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header .header-control.header-nav.awa-nav-bar>.container{'
            . 'padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #header.header-container{'
            . 'box-sizing:border-box!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding-bottom:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar.awa-b2b-promo-bar{'
            . 'background:var(--awa-primary)!important;background-color:var(--awa-primary)!important;box-sizing:border-box!important;color:var(--awa-on-primary,Canvas)!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding-bottom:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-close,#awa-b2b-promo-close){'
            . 'width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;'
            . 'min-height:44px!important;max-height:44px!important;display:inline-flex!important;align-items:center!important;'
            . 'justify-content:center!important;color:inherit!important;background:transparent!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta{'
            . 'color:inherit!important;min-height:44px!important;height:44px!important;max-height:44px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,'
            . '.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__separator){'
            . 'color:inherit!important}'
            . '@media(min-width:768px) and (max-width:991px){'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header .header-wrapper-sticky{display:flex!important;flex-direction:column!important;'
            . 'height:auto!important;min-height:calc(56px + 48px)!important;max-height:none!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header :is(.header-control.header-nav,.header-control.awa-nav-bar){'
            . 'display:flex!important;visibility:visible!important;height:48px!important;min-height:48px!important;'
            . 'max-height:48px!important;overflow:visible!important;pointer-events:auto!important}}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt :is(.awa-header-account-prompt__link--login,'
            . '.awa-header-account-prompt__link--register,.awa-header-account-prompt__link){'
            . 'display:inline-flex!important;align-items:center!important;min-height:44px!important;height:44px!important;'
            . 'box-sizing:border-box!important;line-height:1.2!important;padding:0 4px!important}'
            . '@media(min-width:768px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta strong.awa-b2b-promo-bar__cta-long{'
            . 'color:var(--awa-on-primary,Canvas)!important;text-shadow:none!important}}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header div.awa-main-header__inner.wp-header{'
            . 'box-sizing:border-box!important;height:64px!important;min-height:64px!important;max-height:64px!important;'
            . 'padding-top:0!important;padding-bottom:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-search-label>span{'
            . 'border:0!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;height:1px!important;margin:-1px!important;overflow:hidden!important;'
            . 'font-size:0!important;line-height:0!important;padding:0!important;position:absolute!important;width:1px!important;white-space:nowrap!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header .header-control.header-nav.awa-nav-bar>.container{'
            . 'box-sizing:border-box!important;padding-left:16px!important;padding-right:16px!important}'
            . '@media(max-width:767px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header .header-wrapper-sticky{'
            . 'box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:0 16px!important;padding-block:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){'
            . 'box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header div.awa-main-header__inner.wp-header{'
            . 'box-sizing:border-box!important;display:grid!important;gap:4px 8px!important;grid-template-areas:"toggle brand cart" "search search search"!important;'
            . 'grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:44px 44px!important;'
            . 'height:96px!important;min-height:96px!important;max-height:96px!important;overflow:visible!important;'
            . 'padding:4px 16px 0!important;padding-block:4px 0!important;padding-inline:16px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header :is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar,'
            . '.header-control.header-nav.awa-nav-bar>.container,.awa-nav-bar__inner){'
            . 'box-sizing:border-box!important;height:0!important;min-height:0!important;max-height:0!important;'
            . 'padding:0!important;margin:0!important;overflow:hidden!important;visibility:hidden!important}}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . ':is([id^="awa-vertical-menu-"],.navigation.verticalmenu,.navigation.verticalmenu.side-verticalmenu,.side-verticalmenu>ul.togge-menu){'
            . 'border:0!important;box-shadow:0 2px 8px color-mix(in srgb,CanvasText 8%,transparent)!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header .awa-header-minicart:has(.minicart-wrapper .showcart)>.awa-header-cart-fallback{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'min-width:0!important;min-height:0!important;overflow:hidden!important;pointer-events:none!important;'
            . 'position:absolute!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header #search_mini_form #awa-search-clear.awa-search-clear-btn[hidden]{display:none!important;visibility:hidden!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header #search_mini_form #awa-search-clear.awa-search-clear-btn:not([hidden]){display:inline-flex!important;visibility:visible!important}'
            . '@layer awa-fixes{/* Required: legacy awa-fixes !important header rules beat unlayered terminal CSS. */'
            . '@media(max-width:767px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky>div.header.awa-main-header[data-awa-header-main="true"]{'
            . 'box-sizing:border-box!important;display:block!important;height:96px!important;min-height:96px!important;'
            . 'max-height:96px!important;block-size:96px!important;min-block-size:96px!important;max-block-size:96px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body:not(.cms-index-index) '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky{'
            . 'box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky div.awa-main-header__inner.wp-header{'
            . 'box-sizing:border-box!important;display:grid!important;grid-template:"toggle brand cart" 44px "search search search" 44px/44px minmax(0,1fr) 44px!important;'
            . 'gap:4px 8px!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:4px 16px 0!important;padding-block:4px 0!important;padding-inline:16px!important;'
            . 'align-content:start!important;align-items:center!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky :is(.awa-header-search-col,.block-search,.block-search .block-content){'
            . 'box-sizing:border-box!important;height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky>.header-control.header-nav.awa-nav-bar[data-awa-header-nav="true"]{'
            . 'display:none!important;visibility:hidden!important;height:0!important;min-height:0!important;max-height:0!important;'
            . 'padding:0!important;margin:0!important;border:0!important;overflow:hidden!important}}}';

        $css .= $this->buildUnifiedHeaderGeometryAuthorityCss();

        $css .= '</style>';
        /* Patch 2026-07-02: o MutationObserver original observava document.documentElement
         * inteiro (subtree:true) e reagia a QUALQUER mutação da página (carrosséis, grids,
         * animações) chamando sync() via requestAnimationFrame + 2x setTimeout. Como sync()
         * também escreve atributos (tabindex/aria-hidden/inert) nos próprios nós observados,
         * cada execução gerava novas mutações que realimentavam o observer indefinidamente.
         * Medido via Lighthouse/PageSpeed: este script sozinho respondia por a maior fatia de
         * "Style & Layout" no main-thread e ajudava a travar a página inteira (TBT 11s+,
         * "the page stopped responding"). Fix: escopo do observer restrito ao próprio header
         * (não o documento todo) + desconectar/reconectar durante a escrita (zero reentrância)
         * + debounce único por rAF nas mutações (em vez de 3 disparos por evento). */
        $injectHiddenFocusSync = !in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)
            && !in_array($fullAction, self::CATALOG_STACK_ACTIONS, true);

        $script = '<script id="awa-header-hidden-focus-sync">(function(){'
            . 'var owned="data-awa-hidden-focus-sync",storedTab="data-awa-prev-tabindex",storedAria="data-awa-prev-aria-hidden";'
            . 'function isHidden(el){if(!el||!el.isConnected){return true;}'
            . 'if(el.hidden||el.closest("[hidden]")){return true;}'
            . 'var cs=getComputedStyle(el),r=el.getBoundingClientRect();'
            . 'return cs.display==="none"||cs.visibility==="hidden"||r.width===0||r.height===0;}'
            . 'function remember(el){if(!el.hasAttribute(storedTab)){el.setAttribute(storedTab,el.hasAttribute("tabindex")?el.getAttribute("tabindex"):"");}'
            . 'if(!el.hasAttribute(storedAria)){el.setAttribute(storedAria,el.hasAttribute("aria-hidden")?el.getAttribute("aria-hidden"):"");}}'
            . 'function restore(el){if(el.getAttribute(owned)!=="1"){return;}'
            . 'var tab=el.getAttribute(storedTab),aria=el.getAttribute(storedAria);'
            . 'if(tab===""){el.removeAttribute("tabindex");}else if(tab!==null){el.setAttribute("tabindex",tab);}'
            . 'if(aria===""){el.removeAttribute("aria-hidden");}else if(aria!==null){el.setAttribute("aria-hidden",aria);}'
            . 'if("inert" in el){el.inert=false;}el.removeAttribute(owned);el.removeAttribute(storedTab);el.removeAttribute(storedAria);}'
            . 'function hide(el){if(el.getAttribute(owned)==="1"&&el.getAttribute("tabindex")==="-1"&&el.getAttribute("aria-hidden")==="true"){return;}remember(el);el.setAttribute("tabindex","-1");el.setAttribute("aria-hidden","true");'
            . 'if("inert" in el){el.inert=true;}el.setAttribute(owned,"1");}'
            . 'var headerObserverOptions={childList:true,subtree:true,attributes:true,attributeFilter:["class","style","hidden","aria-hidden"]};'
            . 'var headerObserver=null;'
            . 'function sync(){var root=document.querySelector(".awa-site-header");if(!root){return;}'
            . 'if(headerObserver){headerObserver.disconnect();}'
            . 'var nodes=root.querySelectorAll(".action.nav-toggle,.awa-header-mobile-toggle,.awa-header-cart-link,.awa-header-cart-fallback,.awa-minicart-continue,.header-control.header-nav,.header-control.header-nav a,.header-control.header-nav button,.awa-nav-quick-links__link,.title-category-dropdown,.awa-header-account-prompt__icon,.awa-header-account-prompt__link,.awa-top-link-anchor,.header.links a,.top-header a[href]");'
            . 'nodes.forEach(function(el){if(isHidden(el)){hide(el);}else{restore(el);}});'
            . 'if(headerObserver){headerObserver.observe(root,headerObserverOptions);}}'
            . 'function soon(){requestAnimationFrame(sync);setTimeout(sync,250);setTimeout(sync,1200);}'
            . 'var mutationSyncQueued=false;'
            . 'function syncSoonFromMutation(){if(mutationSyncQueued){return;}mutationSyncQueued=true;'
            . 'requestAnimationFrame(function(){mutationSyncQueued=false;sync();});}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",soon,{once:true});}else{soon();}'
            . 'window.addEventListener("load",soon,{once:true,passive:true});window.addEventListener("resize",soon,{passive:true});'
            . 'if(window.MutationObserver){'
            . 'headerObserver=new MutationObserver(function(){syncSoonFromMutation();});'
            . 'var bindHeaderObserver=function(){var root=document.querySelector(".awa-site-header");'
            . 'if(!root){return false;}headerObserver.observe(root,headerObserverOptions);return true;};'
            . 'if(!bindHeaderObserver()){document.addEventListener("DOMContentLoaded",bindHeaderObserver,{once:true});}'
            . '}'
            . '})();</script>';

        $payload = $css;
        if ($injectHiddenFocusSync) {
            $payload .= $script;
        }

        $injected = preg_replace('/<\/body>/i', $payload . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Geometria única do header (3D.2.2B): fonte de verdade para home/PLP/PDP/cart.
     */
    private function buildUnifiedHeaderGeometryAuthorityCss(): string
    {
        $css = <<<'CSS'
@media(min-width:1200px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]{--awa-header-main-row-h:68px!important;--awa-header-nav-h:48px!important;--awa-header-shell-pad:24px!important;--awa-header-col-gap:24px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .header.awa-main-header{height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;padding:0 var(--awa-header-shell-pad)!important;box-sizing:border-box!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;box-sizing:border-box!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template-columns:minmax(140px,172px) minmax(360px,1fr) minmax(240px,300px)!important;grid-template-areas:"brand search actions"!important;align-items:center!important;column-gap:var(--awa-header-col-gap)!important;width:min(100%,1280px)!important;max-width:1280px!important;margin-inline:auto!important;padding-inline:var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;height:var(--awa-header-main-row-h)!important;min-height:0!important;max-height:var(--awa-header-main-row-h)!important;overflow:hidden!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;display:flex!important;align-items:center!important;width:100%!important;min-width:0!important;max-width:none!important;height:var(--awa-header-main-row-h)!important;min-height:0!important;max-height:var(--awa-header-main-row-h)!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:actions!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:8px!important;width:100%!important;min-width:0!important;max-width:300px!important;height:var(--awa-header-main-row-h)!important;min-height:0!important;max-height:var(--awa-header-main-row-h)!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] :is(.header-control.header-nav,.header-control.awa-nav-bar,.awa-nav-bar){height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-nav-bar__inner{height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;box-sizing:border-box!important;width:min(100%,1280px)!important;max-width:1280px!important;margin-inline:auto!important;padding-inline:var(--awa-header-shell-pad)!important}}
@media(min-width:992px) and (max-width:1199px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]{--awa-header-main-row-h:64px!important;--awa-header-nav-h:46px!important;--awa-header-shell-pad:20px!important;--awa-header-col-gap:16px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){grid-template-columns:minmax(132px,160px) minmax(320px,1fr) minmax(220px,280px)!important;grid-template-areas:"brand search actions"!important;column-gap:var(--awa-header-col-gap)!important;padding-inline:var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .header.awa-main-header{height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;padding:0 var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] :is(.header-control.header-nav,.header-control.awa-nav-bar,.awa-nav-bar){height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-nav-bar__inner{height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;padding-inline:var(--awa-header-shell-pad)!important}}
@media(min-width:768px) and (max-width:991px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]{--awa-header-main-row-h:56px!important;--awa-header-nav-h:44px!important;--awa-header-shell-pad:16px!important;--awa-header-col-gap:16px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template-columns:minmax(112px,148px) minmax(260px,1fr) minmax(180px,240px)!important;grid-template-areas:"brand search actions"!important;column-gap:var(--awa-header-col-gap)!important;padding-inline:var(--awa-header-shell-pad)!important;height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .header.awa-main-header{height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;padding:0 var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;max-width:none!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:actions!important;max-width:240px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] :is(.header-control.header-nav,.header-control.awa-nav-bar,.awa-nav-bar){height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-nav-bar__inner{height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;padding-inline:var(--awa-header-shell-pad)!important}}
@media(max-width:767px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky{height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template-areas:"toggle brand cart" "search search search"!important;grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:auto auto!important;row-gap:12px!important;column-gap:12px!important;height:auto!important;min-height:0!important;max-height:none!important;padding:12px 16px!important;overflow:visible!important;box-sizing:border-box!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-mobile-toggle{grid-area:toggle!important;justify-self:start!important;align-self:center!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important;justify-self:center!important;align-self:center!important;min-width:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-cart-link{grid-area:cart!important;justify-self:end!important;align-self:center!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;width:100%!important;min-width:0!important;max-width:none!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form{display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;grid-template-areas:"field submit"!important;width:100%!important;min-width:0!important;max-width:100%!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form .field.search{grid-area:field!important;min-width:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col input#search{height:44px!important;min-height:44px!important;max-height:44px!important;line-height:44px!important;font-size:16px!important;padding-block:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form .actions{grid-area:submit!important;width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important;margin:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form :is(.action.search,button.action.search){width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:search!important;width:100%!important;min-width:0!important;max-width:none!important}}
CSS;

        return $css;
    }

    private function normalizeHeaderGeometryAuthorityInlineStyles(
        string $html,
        string $fullAction,
        bool $isAuthFocusPage,
        bool $isB2bAccountFocusPage
    ): string {
        if ($isAuthFocusPage || $isB2bAccountFocusPage) {
            return $html;
        }

        // Legacy lock de paridade do catálogo introduz uma segunda fonte de verdade para header.
        $html = preg_replace('/<style id="awa-header-catalog-parity-v1"[^>]*>.*?<\/style>\s*/is', '', $html) ?? $html;

        if ($fullAction === self::HOME_ACTION) {
            foreach (
                [
                'awa-home-cls-critical-opt21',
                'awa-home-critical-cls-shell',
                'awa-home-critical-cls-final-lock',
                'awa-header-vtex-clean-critical-20260622',
                ] as $styleId
            ) {
                $html = preg_replace(
                    '/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>\s*/is',
                    '',
                    $html
                ) ?? $html;
            }

            // O bloco awa-home-cls-critical-opt21 removido acima era a ÚNICA fonte
            // inline do reset base do body no 1º paint. Sem ele, o UA default
            // body{margin:8px} fica visível até os bundles assíncronos carregarem
            // (evidência runtime e85cbc: footer.rect.left=8 / bodyScrollWidth=424
            // no parse vs 440 após ~3,4s em iPhone real). Reinjeta só o reset.
            if (strpos($html, 'awa-home-first-paint-base-reset') === false) {
                $html = preg_replace(
                    '/<head[^>]*>/i',
                    '$0<style id="awa-home-first-paint-base-reset">'
                        // H75-H79 fix: awa-super-global-20260611m.css declara
                        // "@layer awa-reset,awa-core,awa-layout,awa-components,awa-consistency,
                        // awa-fixes,awa-grid" e a regra de width do .awa-main-header__inner mora
                        // em @layer awa-fixes. Em CSS Cascade Layers, !important SEM layer tem a
                        // MENOR prioridade de todas — por isso o crítico inline (sem layer) perdia
                        // para @layer awa-fixes no first paint (evidência: mainInner width 376px
                        // no render vs 408px no dom-ready, quando o JS/CSS async de maior
                        // especificidade finalmente vence). Declarar este layer aqui, ANTES de
                        // qualquer outro @layer no documento, garante que ele seja o "primeiro
                        // declarado" — e para !important a ordem é invertida (primeiro layer
                        // declarado vence), então este layer passa a vencer awa-fixes também.
                        . '@layer awa-header-first-paint-lock;'
                        . 'html,body#html-body{margin:0!important;padding:0!important}'
                        . 'html{overflow-x:clip;scrollbar-width:thin}'
                        . 'body{overflow-x:clip;background-color:#ffffff}'
                        // H60-H64: no 1º paint mobile da home, o form de busca em #header
                        // inicia como bloco e a .actions cai para a linha de baixo até o
                        // estado "complete"/scroll. Força grid 1fr+44px já no critical CSS.
                        . '@media(max-width:767px){'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) form#search_mini_form{display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;grid-template-areas:"field submit"!important;align-items:stretch!important;width:100%!important;min-width:0!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) form#search_mini_form .field.search{grid-area:field!important;min-width:0!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) form#search_mini_form .actions{grid-area:submit!important;display:flex!important;width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;justify-content:center!important;align-items:center!important;padding:0!important;margin:0!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) form#search_mini_form :is(input#search,.input-text){width:100%!important;min-width:0!important;height:44px!important;min-height:44px!important;max-height:44px!important;line-height:44px!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) form#search_mini_form :is(.action.search,button.action.search){width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important}'
                        // H65: first paint do iPhone ainda recebia estilo nativo (cinza/preto)
                        // em nav-toggle/search/cart; aplica aparência crítica estável.
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .action.nav-toggle.awa-header-mobile-toggle{appearance:none!important;-webkit-appearance:none!important;background:#b73337!important;color:#fff!important;border:0!important;box-shadow:none!important;border-radius:0!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .minicart-wrapper .action.showcart.header-mini-cart{appearance:none!important;-webkit-appearance:none!important;background:#b73337!important;color:#fff!important;border:0!important;box-shadow:none!important;border-radius:0!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) form#search_mini_form :is(.action.search,button.action.search){appearance:none!important;-webkit-appearance:none!important;background:transparent!important;color:#b73337!important;border:0!important;box-shadow:none!important;border-radius:0!important}'
                        // H75-H79: trava contrato final do header mobile já no first paint
                        // (evita salto render->dom-ready em sticky/promo/nav). Dentro do layer
                        // declarado acima para vencer @layer awa-fixes do awa-super-global.
                        . '@layer awa-header-first-paint-lock{'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky{padding-inline:16px!important;box-sizing:border-box!important;min-height:84px!important;height:112px!important;max-height:112px!important}'
                        // H81: a promo bar (#awa-b2b-promo-bar) mora FORA de .header-wrapper-sticky,
                        // dentro de #header.header-container > .header-content. Sem regra própria
                        // aqui, .header-content nasce com padding:0 (left:0,width:440) e só ganha
                        // padding-inline:16px (left:16,width:408) quando o CSS assíncrono chega.
                        . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #header.header-container{box-sizing:border-box!important}'
                        . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header #header.header-container .header-content{width:100%!important;max-width:none!important;margin:0!important;padding-inline:16px!important;box-sizing:border-box!important}'
                        . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap){width:100%!important;max-width:100%!important;min-height:96px!important;height:96px!important;max-height:96px!important;margin:0!important;padding:0!important;box-sizing:border-box!important}'
                        // H80: wrapper extra ".header-main" (hífen, distinto de ".header_main"
                        // com underscore) fica ENTRE mainWrap e .container->mainInner. Evidência
                        // runtime e85cbc: cadeia real de ancestrais é header-wrapper-sticky >
                        // .header.awa-main-header > .header_main.awa-main-header-inner-wrap >
                        // .header-main > .container > .awa-main-header__inner. O CSS síncrono
                        // "awa-home-impeccable-terminal-lock" aplica nele, sem @media, width:
                        // calc(100% - 32px)+margin-left/right:16px!important (pensado para
                        // desktop) — no first paint mobile isso soma exatamente os 32px de perda
                        // de largura e 16px de deslocamento à esquerda vistos no mainInner
                        // (376px/left:32 no render vs 408px/left:16 no dom-ready).
                        . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky .header-main{width:100%!important;max-width:100%!important;margin:0!important;box-sizing:border-box!important}'
                        . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky :is(.header-main>.container,.header_main>.container){width:100%!important;max-width:none!important;margin:0!important;padding:0!important;box-sizing:border-box!important}'
                        . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row]){width:100%!important;max-width:none!important;margin:0!important;padding-inline:12px!important;box-sizing:border-box!important}'
                        . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .awa-site-header .header-wrapper-sticky :is(.header-control.header-nav.awa-nav-bar,.header-control.header-nav.header-nav-global.cms_home_1,#awa-primary-navigation){display:none!important;height:0!important;min-height:0!important;max-height:0!important;margin:0!important;padding:0!important;border:0!important;overflow:hidden!important}'
                        . '}'
                        . '}'
                        . '</style>',
                    $html,
                    1
                ) ?? $html;
            }

            $html = str_replace(
                [
                    'minmax(420px,1fr)',
                    'min-height:84px!important',
                    'height:84px!important',
                    'max-height:84px!important',
                ],
                [
                    'minmax(360px,1fr)',
                    'min-height:68px!important',
                    'height:68px!important',
                    'max-height:68px!important',
                ],
                $html
            );
        }

        $html = str_replace(
            [
                'grid-template-columns:clamp(128px,13vw,184px) minmax(0,1fr) minmax(260px,max-content)!important;',
                'grid-template-columns:clamp(112px,14vw,148px) minmax(0,1fr) minmax(220px,max-content)!important;',
                'minmax(260px,max-content)',
                'minmax(220px,max-content)',
            ],
            [
                'grid-template-columns:minmax(140px,172px) minmax(360px,1fr) minmax(240px,300px)!important;',
                'grid-template-columns:minmax(112px,148px) minmax(260px,1fr) minmax(180px,240px)!important;',
                'minmax(240px,300px)',
                'minmax(180px,240px)',
            ],
            $html
        );


        // Mobile (<=767): o DOM real expõe toggle/brand/cart como grid items diretos
        // via .awa-header-primary-row{display:contents}; manter o contrato alinhado a isso.
        // FIX 2026-07-06 (Impeccable "cramped padding" div.footer-bottom, no inset no bottom):
        // o padrão 'padding:12px 16px!important;' era substituído via str_replace() SEM escopo,
        // ou seja, batia em QUALQUER ocorrência literal idêntica no $html inteiro — inclusive na
        // regra ".footer-bottom .footer-bottom-inner{...padding:12px 16px!important;...}"
        // (OptimizeHeadStylesPlugin::injectFooterModernRules / HeaderImpeccableCascadeLockCss),
        // que por coincidência usa o mesmo valor. Isso zerava o padding-bottom do footer
        // (virava "4px 12px 0") mesmo fora do contexto de header mobile pretendido. Ancorado
        // com o texto vizinho único do bloco de header (max-height:none...overflow:visible)
        // para que a troca só ocorra dentro do grid mobile do header, nunca no footer.
        $html = str_replace(
            [
                'grid-template-areas:"toggle brand cart" "search search search"!important;',
                'grid-template-columns:44px minmax(0,1fr) 44px!important;',
                'grid-template-rows:auto auto!important;',
                'row-gap:12px!important;column-gap:12px!important;',
                'max-height:none!important;padding:12px 16px!important;overflow:visible!important;',
                '.header-wrapper-sticky .awa-header-primary-row{display:contents!important}',
                '.header-wrapper-sticky .awa-header-right-col{grid-area:search!important;width:100%!important;min-width:0!important;max-width:none!important}',
            ],
            [
                'grid-template-areas:"toggle brand cart" "search search search"!important;',
                'grid-template-columns:44px minmax(0,1fr) 44px!important;',
                'grid-template-rows:44px 44px!important;',
                'row-gap:8px!important;column-gap:8px!important;',
                'max-height:none!important;padding:4px 12px 0!important;overflow:visible!important;',
                '.header-wrapper-sticky .awa-header-primary-row{display:contents!important}',
                '.header-wrapper-sticky .awa-header-right-col{grid-area:cart!important;align-items:center!important;display:flex!important;justify-content:flex-end!important;justify-self:end!important;min-width:44px!important;max-width:44px!important;width:44px!important;height:44px!important;overflow:visible!important}',
            ],
            $html
        );

        // Fase 3D.2.6 — corrige regras @layer awa-fixes mobile que ganham por prioridade de layer:
        // (1) grid-template shorthand legado → propriedades separadas com 3 áreas
        // (2) header-wrapper-sticky e awa-main-header height:96px → auto
        // (3) grid-template-rows:44px 44px → 44px 44px auto (3ª linha para actions)
        $html = str_replace(
            [
                '{box-sizing:border-box!important;display:grid!important;grid-template:"toggle brand cart" 44px "search search search" 44px/44px minmax(0,1fr) 44px!important;gap:4px 8px!important;height:96px!important;min-height:96px!important;max-height:96px!important;padding:4px 16px 0!important;padding-block:4px 0!important;padding-inline:16px!important;align-content:start!important;align-items:center!important;overflow:visible!important}',
                '{box-sizing:border-box!important;display:grid!important;gap:4px 8px!important;grid-template-areas:"primary" "search" "actions"!important;grid-template-columns:minmax(0,1fr)!important;grid-template-rows:44px 44px!important;height:96px!important;min-height:96px!important;max-height:96px!important;overflow:visible!important;padding:4px 16px 0!important;padding-block:4px 0!important;padding-inline:16px!important}',
                'header-wrapper-sticky{box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;padding:0 16px!important;padding-block:0!important;overflow:visible!important}',
                'box-sizing:border-box!important;display:block!important;height:96px!important;min-height:96px!important;max-height:96px!important;block-size:96px!important;min-block-size:96px!important;max-block-size:96px!important;padding:0!important;margin:0!important;overflow:visible!important}',
            ],
            [
                '{box-sizing:border-box!important;display:grid!important;grid-template:"toggle brand cart" 44px "search search search" 44px/44px minmax(0,1fr) 44px!important;gap:4px 8px!important;height:96px!important;min-height:96px!important;max-height:96px!important;padding:4px 12px 0!important;padding-block:4px 0!important;padding-inline:12px!important;align-content:start!important;align-items:center!important;overflow:visible!important}',
                '{box-sizing:border-box!important;display:grid!important;gap:4px 8px!important;grid-template:"toggle brand cart" 44px "search search search" 44px/44px minmax(0,1fr) 44px!important;height:96px!important;min-height:96px!important;max-height:96px!important;overflow:visible!important;padding:4px 12px 0!important;padding-block:4px 0!important;padding-inline:12px!important}',
                'header-wrapper-sticky{box-sizing:border-box!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;overflow:visible!important}',
                'box-sizing:border-box!important;display:block!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;overflow:visible!important}',
            ],
            $html
        );

        // Fase 3D.2.7 — corrige height:96px residual em .header.awa-main-header e
        // .header-wrapper-sticky para páginas fora da home (PLP, PDP, etc.):
        $html = str_replace(
            [
                ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;padding:0!important;margin:0!important;overflow:visible!important}',
                'header.awa-site-header .header-wrapper-sticky{box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;padding:0!important;margin:0!important;overflow:visible!important}',
            ],
            [
                ':is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){box-sizing:border-box!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;overflow:visible!important}',
                'header.awa-site-header .header-wrapper-sticky{box-sizing:border-box!important;height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;overflow:visible!important}',
            ],
            $html
        );

        $html = $this->enforcePrimaryRowResponsiveContract($html);

        return $html;
    }

    /**
     * Header mobile: mantém o grid de topo fiel ao DOM real
     * (toggle/brand/cart como grid items diretos; busca na segunda linha).
     */
    private function enforcePrimaryRowResponsiveContract(string $html): string
    {
        $marker = 'awa-primary-row-responsive-contract-v20260704';
        if (str_contains($html, $marker)) {
            return $html;
        }

        $contractCss = '/* ' . $marker . ' */'
            . '@media(min-width:768px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}}'
            . '@media(max-width:767px){'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template:"toggle brand cart" 44px "search search search" 44px/44px minmax(0,1fr) 44px!important;gap:4px 8px!important;height:96px!important;min-height:96px!important;max-height:96px!important;padding:4px 12px 0!important;align-content:start!important;align-items:center!important;overflow:visible!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-mobile-toggle{grid-area:toggle!important;justify-self:start!important;align-self:center!important;width:44px!important;height:44px!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important;justify-self:center!important;align-self:center!important;min-width:0!important;max-width:160px!important;height:44px!important;overflow:visible!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;display:block!important;justify-self:stretch!important;min-width:0!important;max-width:100%!important;width:100%!important;height:44px!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:cart!important;align-items:center!important;display:flex!important;justify-content:flex-end!important;justify-self:end!important;min-width:44px!important;max-width:44px!important;width:44px!important;height:44px!important;overflow:visible!important}'
            /* BUG-B2B-PANEL-MOBILE-2026-07-07: quando o painel B2B (.b2b-status-panel) está
             * presente, a coluna "cart" do grid mobile precisa de mais espaço que os 44px
             * fixos usados apenas para o ícone do carrinho — senão o botão "Olá, Fernando"
             * fica com width:0 (display:flex, mas invisível) neste breakpoint. */
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky:has(.b2b-status-panel) :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){grid-template-columns:44px minmax(0,1fr) minmax(44px,auto)!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel){min-width:44px!important;max-width:min(150px,40vw)!important;width:auto!important;gap:4px!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col:has(.b2b-status-panel) .b2b-status-panel{width:auto!important;max-width:none!important;overflow:visible!important}'
            . 'html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-header-minicart,.minicart-wrapper,.minicart-wrapper .action.showcart,.minicart-wrapper a.showcart.header-mini-cart){align-items:center!important;display:inline-flex!important;float:none!important;justify-content:center!important;justify-self:auto!important;place-self:center!important;transform:none!important;width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;margin:0!important;flex-shrink:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper header.awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-header-minicart,.minicart-wrapper){position:relative!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper header.awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-minicart .minicart-wrapper :is(.action.showcart,a.showcart.header-mini-cart){float:none!important;position:absolute!important;inset:auto!important;left:auto!important;right:0!important;top:0!important;bottom:auto!important;transform:none!important;place-self:center!important;justify-self:auto!important;margin:0!important}'
            . 'html body#html-body:not(#__awa-no-match):not(#__awa-no-match):not(#__awa-no-match):not(#__awa-no-match):not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper header.awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-minicart .minicart-wrapper :is(.action.showcart,a.showcart.header-mini-cart){float:none!important;position:absolute!important;inset:auto!important;left:auto!important;right:0!important;top:0!important;bottom:auto!important;transform:none!important;place-self:center!important;justify-self:auto!important;margin:0!important}'
            . '}';

        $patched = preg_replace_callback(
            '/(<style id="awa-align-grid-inline-lock[^"]*"[^>]*>)(.*?)(<\/style>)/is',
            static function (array $matches) use ($contractCss): string {
                return $matches[1] . $matches[2] . $contractCss . $matches[3];
            },
            $html,
            1
        );

        return is_string($patched) ? $patched : $html;
    }

    /**
     * PLP/PDP/carrinho: remove bloco legacy de head-preload (sem id, ~70KB) para reduzir parsing/heap no browser.
     * Home/auth/painel B2B preservam o comportamento atual.
     */
    private function stripHeavyHeadPreloadInlineStyleOutsideHome(
        string $html,
        string $fullAction,
        bool $isAuthFocusPage,
        bool $isB2bAccountFocusPage
    ): string {
        if ($fullAction === self::HOME_ACTION || $isAuthFocusPage || $isB2bAccountFocusPage) {
            return $html;
        }

        if (!str_contains($html, '--head-preload-c1:')) {
            return $html;
        }

        $stripped = preg_replace_callback(
            '/<style\b([^>]*)>(.*?)<\/style>\s*/is',
            static function (array $matches): string {
                $attrs = $matches[1] ?? '';
                $body = $matches[2] ?? '';

                if (preg_match('/\bid\s*=/i', $attrs)) {
                    return $matches[0];
                }

                if (
                    !str_contains($body, '--head-preload-c1:')
                    || !str_contains($body, '--head-preload-footer-surface:')
                ) {
                    return $matches[0];
                }

                return '';
            },
            $html
        );

        return is_string($stripped) ? $stripped : $html;
    }

    /**
     * Única instância do align-grid — remove head/body-end duplicados e reinjeta só antes de </body>
     * (final-wins sobre bundles async). Economiza ~37KB de download duplicado na home/PLP.
     */
    private function consolidateAlignGridToBodyTerminal(string $html, string $fullAction): string
    {
        $html = $this->normalizeAlignGridStylesheetVersion($html);
        $deferAlignGrid = in_array($fullAction, self::DEFER_ALIGN_GRID_ACTIONS, true);

        if (!str_contains($html, 'awa-align-grid-terminal-2026-06-11')) {
            return $this->injectAlignGridBodyTerminalIfMissing($html, $deferAlignGrid, $fullAction);
        }

        $pattern = '/<link\s[^>]*awa-align-grid-terminal-2026-06-11[^>]*\/?>\s*/i';
        $html = preg_replace($pattern, '', $html) ?? $html;

        return $this->injectAlignGridBodyTerminalIfMissing($html, $deferAlignGrid, $fullAction);
    }

    /**
     * Normaliza query string stale do align-grid (evita 2 versões no mesmo HTML).
     */
    private function normalizeAlignGridStylesheetVersion(string $html): string
    {
        $file = HeaderImpeccableCascadeLockCss::ALIGN_GRID_CSS_FILE;
        $query = HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;
        $canonical = $file . $query;

        $html = preg_replace(
            '/awa-align-grid-terminal-2026-06-11(?:\.min)?\.css\?v=[^"\'&\s>]+/',
            $canonical,
            $html
        ) ?? $html;

        // Mantém só o link ativo (body-terminal > head sync); remove duplicatas pós-normalização.
        $pattern = '/<link\s[^>]*href=(["\'])' . preg_quote($canonical, '/') . '\1[^>]*\/?>\s*/i';
        if (!preg_match_all($pattern, $html, $matches) || count($matches[0]) < 2) {
            return $html;
        }

        $keepBody = null;
        foreach ($matches[0] as $tag) {
            if (str_contains($tag, 'data-awa-align-grid-body-terminal')) {
                $keepBody = $tag;
                break;
            }
        }

        $keep = $keepBody ?? $matches[0][array_key_last($matches[0])];
        $first = true;

        return preg_replace_callback(
            $pattern,
            static function (array $m) use (&$first, $keep): string {
                if ($first) {
                    $first = false;

                    return $keep;
                }

                return '';
            },
            $html
        ) ?? $html;
    }

    /**
     * Última camada CSS — reinjeta align-grid antes de </body> para vencer body-end/polish-type.
     */
    private function injectAlignGridBodyTerminalIfMissing(string $html, bool $defer = false, string $fullAction = ''): string
    {
        $hasAlignGridBodyTerminal = str_contains($html, 'data-awa-align-grid-body-terminal="1"');
        $hasHeaderContractBodyTerminal = str_contains($html, 'data-awa-header-contract-grid-body-terminal="1"');
        if ($hasAlignGridBodyTerminal && $hasHeaderContractBodyTerminal) {
            return $html;
        }

        if (
            !preg_match(
                '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
                $html,
                $versionMatch
            )
        ) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_CSS_FILE
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;
        $contractHref = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . self::HEADER_CONTRACT_GRID_FILE
            . self::HEADER_CONTRACT_GRID_QUERY;
        $isHome = $fullAction === self::HOME_ACTION;
        $isCatalogDensity = in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
            || in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true);
        $hasHomeDensityHref = self::HOME_DENSITY_GRID_FILE !== '';
        $hasCatalogDensityHref = self::CATALOG_DENSITY_GRID_FILE !== '';
        $homeDensityHref = $hasHomeDensityHref
            ? '/static/' . $versionMatch[1]
                . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
                . self::HOME_DENSITY_GRID_FILE
                . self::HOME_DENSITY_GRID_QUERY
            : '';
        $catalogDensityHref = $hasCatalogDensityHref
            ? '/static/' . $versionMatch[1]
                . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
                . self::CATALOG_DENSITY_GRID_FILE
                . self::CATALOG_DENSITY_GRID_QUERY
            : '';

        if ($defer) {
            /* Home/PLP/busca: inline lock cobre container/grid no 1º paint — folha completa async */
            $tag = '';
            if (!$hasAlignGridBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal" data-awa-defer="1"/>';
            }
            if (
                $hasHomeDensityHref
                && $isHome
                && !str_contains($html, 'data-awa-home-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $homeDensityHref . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid" data-awa-defer="1"/>';
            }
            if (
                $hasCatalogDensityHref
                && $isCatalogDensity
                && !str_contains($html, 'data-awa-catalog-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $catalogDensityHref . '" media="all"'
                    . ' data-awa-catalog-density-grid-body-terminal="1" data-awa-bundle="catalog-density-grid"/>';
            }
            if (!$hasHeaderContractBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $contractHref . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-header-contract-grid-body-terminal="1" data-awa-bundle="header-contract-grid" data-awa-defer="1"/>';
            }
        } else {
            $tag = '';
            if (!$hasAlignGridBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $href . '" media="all"'
                    . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal"/>';
            }
            if (
                $hasHomeDensityHref
                && $isHome
                && !str_contains($html, 'data-awa-home-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $homeDensityHref . '" media="all"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid"/>';
            }
            if (
                $hasCatalogDensityHref
                && $isCatalogDensity
                && !str_contains($html, 'data-awa-catalog-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $catalogDensityHref . '" media="all"'
                    . ' data-awa-catalog-density-grid-body-terminal="1" data-awa-bundle="catalog-density-grid"/>';
            }
            if (!$hasHeaderContractBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $contractHref . '" media="all"'
                    . ' data-awa-header-contract-grid-body-terminal="1" data-awa-bundle="header-contract-grid"/>';
            }
        }

        if ($tag === '') {
            return $html;
        }

        $injected = preg_replace('/<\/body>/i', $tag . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * PLP/busca/PDP: folhas não críticas para header/above-fold no 1º paint.
     */
    private function deferCatalogNonCriticalStylesheets(string $html): string
    {
        return $this->deferStylesheetsByFragments($html, self::CATALOG_DEFER_CSS_FRAGMENTS);
    }

    /**
     * Converte stylesheets síncronos em async (media=print + onload).
     *
     * @param string[] $fragments
     */
    private function deferStylesheetsByFragments(string $html, array $fragments): string
    {
        foreach ($fragments as $fragment) {
            $escaped = preg_quote($fragment, '/');
            $html = preg_replace_callback(
                '/<link\s+([^>]*?' . $escaped . '[^>]*?)\s*\/?>/i',
                static function (array $matches): string {
                    $attrs = $matches[1];
                    if (!preg_match('/rel=["\']stylesheet["\']/i', $attrs)) {
                        return $matches[0];
                    }
                    if (preg_match('/media=["\']print["\']/i', $attrs) || preg_match('/onload=/i', $attrs)) {
                        return $matches[0];
                    }

                    $attrs = preg_replace('/\s*media=["\']all["\']\s*/i', ' ', $attrs) ?? $attrs;
                    $attrs = trim(preg_replace('/\s+/', ' ', $attrs) ?? $attrs);
                    $attrs = rtrim($attrs, '/');

                    return '<link ' . $attrs . ' media="print" onload="this.media=\'all\'" data-awa-defer="catalog-noncritical"/>';
                },
                $html
            ) ?? $html;
        }

        return $html;
    }

    /**
     * PLP/busca/carrinho: templates ainda referenciam v=11; normaliza em toda resposta HTML com header.
     */
    private function normalizeHeaderTerminalStylesheetVersion(string $html): string
    {
        $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;

        if (!str_contains($html, 'awa-header-refine-terminal.min.css')) {
            return $html;
        }

        return preg_replace(
            '/awa-header-refine-terminal\.min\.css(?:\?v=[^"\'&\s>]+)?/',
            'awa-header-refine-terminal.min.css?v=' . $terminalV,
            $html
        ) ?? $html;
    }

    private function isHomeNeverGateFragment(string $fragment): bool
    {
        foreach (self::HOME_NEVER_GATE_FRAGMENTS as $never) {
            if (str_contains($fragment, $never)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Home: com stack consolidado ativo, remove folhas críticas legadas (FPC stale).
     */
    private function stripStaleHomeCriticalStylesheets(string $html): string
    {
        if (!str_contains($html, 'awa-home-critical-stack')) {
            return $html;
        }

        return $this->stripStylesheetFragments($html, [
            'awa-header-mobile-grid-critical',
            'awa-impeccable-critical-home',
            'awa-header-home-light-lock-v1',
            'awa-home-first-paint-critical',
            'awa-bundle-async-distill-lock',
            // MORTO: 'awa-menu-v2-dept-open-fix' → _deprecated/
        ]);
    }

    private function patchStaleHomeHeaderAssets(string $html): string
    {
        $gateJs = 'awa-css-gate.min.js?v=' . HeaderImpeccableCascadeLockCss::GATE_SCRIPT_QUERY;
        $html = str_replace(
            [
                'awa-css-gate.min.js?v=20260528-loader',
                'awa-css-gate.min.js?v=20260531-impeccable-v10',
                'awa-css-gate.min.js?v=20260531-impeccable-v11',
                'awa-css-gate.min.js?v=20260531-impeccable-v12',
                'awa-css-gate.min.js?v=20260601-home-opt3',
                'awa-css-gate.min.js?v=20260601-home-opt4',
                'awa-css-gate.min.js?v=20260601-home-opt5',
                'awa-css-gate.min.js?v=20260601-home-opt6',
                'awa-css-gate.min.js?v=20260601-home-opt7',
                'awa-css-gate.min.js?v=20260601-home-opt8',
                'awa-css-gate.min.js?v=20260601-carousel13',
                'awa-css-gate.min.js?v=20260601-home-opt17',
                'awa-css-gate.min.js?v=20260531-optimize',
            ],
            $gateJs,
            $html
        );

        $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;
        $html = preg_replace(
            '/awa-home-critical-stack-2026-06-11\.min\.css\?v=[^"\'&\s>]+/',
            'awa-home-critical-stack-2026-06-11.min.css?v=20260708-b2b-panel-mobile-fix',
            $html
        ) ?? $html;
        if (!str_contains($html, 'awa-header-refine-terminal.min.css?v=')) {
            $html = str_replace(
                'awa-header-refine-terminal.min.css">',
                'awa-header-refine-terminal.min.css?v=' . $terminalV . '">',
                $html
            );
        }

        return preg_replace(
            '/awa-header-refine-terminal\.min\.css\?v=[^"\'&\s>]+/',
            'awa-header-refine-terminal.min.css?v=' . $terminalV,
            $html
        ) ?? $html;
    }

    private function injectHeaderTerminalFixStyle(string $html): string
    {
        return HeaderImpeccableCascadeLockCss::injectBeforeBodyClose($html);
    }

    private function injectHeaderTerminalStylesheetIfMissing(string $html): string
    {
        /* header-refine-terminal migrado para styles-l — não reinjetar folha standalone. */
        return $html;
    }

    /**
     * Home: garante terminal CSS + fila gate no HTML (preload pode omitir quando $__gatedCssUrls vazio).
     */
    private function injectHomeHeaderTerminalAssets(string $html): string
    {
        $html = $this->injectHeaderTerminalStylesheetIfMissing($html);
        if (
            !preg_match(
                '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
                $html,
                $versionMatch
            )
        ) {
            return $html;
        }

        $staticBase = '/static/' . $versionMatch[1] . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/';
        $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;
        $terminalHref = $staticBase . 'css/awa-header-refine-terminal.min.css?v=' . $terminalV;

        if (!str_contains($html, 'id="awa-css-gate-queue"')) {
            $queueTag = '<script id="awa-css-gate-queue" type="application/json" data-awa-terminal-href="'
                . htmlspecialchars($terminalHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '">[]</script>';
            $gateJs = $staticBase . 'js/awa-css-gate.min.js?v=' . HeaderImpeccableCascadeLockCss::GATE_SCRIPT_QUERY;
            $gateTag = '<script src="' . $gateJs . '" defer></script>';
            $replaced = preg_replace('/<\/head>/i', $queueTag . "\n" . $gateTag . "\n</head>", $html, 1);

            if (is_string($replaced)) {
                $html = $replaced;
            }
        }

        return $html;
    }

    /**
     * Home: garante distill-lock + align-grid terminal no head (sync media=all).
     * Templates/FPC podem omitir — plugin reinjeta antes de </head>.
     */
    /**
     * Converte o bloco footer terminal inline (~58KB) para um link CSS async externo.
     * O footer é 100% below-fold — não há risco de FOUC ou CLS ao carregar async.
     * O arquivo externo é cacheável pelo browser, reduzindo peso em page loads subsequentes.
     */
    private function convertFooterCssToAsync(string $html): string
    {
        if (!str_contains($html, 'id="' . HeaderImpeccableCascadeLockCss::FOOTER_STYLE_ID . '"')) {
            return $html;
        }

        if (
            !preg_match(
                '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
                $html,
                $versionMatch
            )
        ) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::FOOTER_CSS_FILE
            . HeaderImpeccableCascadeLockCss::FOOTER_CSS_QUERY;

        // Remove bloco inline
        $html = HeaderImpeccableCascadeLockCss::stripFooterTerminalFromHtml($html);

        // Injeta link async antes de </body>
        $tag = '<link rel="stylesheet" href="' . $href . '" media="print"'
            . ' onload="this.media=\'all\'" data-awa-footer-async="1"/>'
            . '<noscript><link rel="stylesheet" href="' . $href . '"/></noscript>';

        $pos = stripos($html, '</body>');
        if ($pos === false) {
            return $html . "
" . $tag;
        }

        return substr($html, 0, $pos) . $tag . "
" . substr($html, $pos);
    }


    private function injectHomeAlignGridStylesheetsIfMissing(string $html): string
    {
        if (
            !preg_match(
                '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
                $html,
                $versionMatch
            )
        ) {
            return $html;
        }

        $staticBase = '/static/' . $versionMatch[1] . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/';
        $tags = '';

        if (
            !str_contains($html, 'awa-home-critical-stack')
            && !str_contains($html, 'awa-bundle-async-distill-lock')
        ) {
            $tags .= '<link rel="stylesheet" href="' . $staticBase
                . 'awa-bundle-async-distill-lock.min.css' . HeaderImpeccableCascadeLockCss::HOME_DISTILL_LOCK_QUERY
                . '" media="all" data-awa-bundle="async-distill-lock"/>' . "\n";
        }

        /* align-grid: só via consolidateAlignGridToBodyTerminal (evita duplicata head + body) */

        if ($tags === '') {
            return $html;
        }

        $injected = preg_replace('/<\/head>/i', $tags . '</head>', $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * PLP/PDP/carrinho: garante align-grid terminal quando o loader body-end não renderiza (FPC).
     */
    private function injectAlignGridStylesheetIfMissing(string $html): string
    {
        if (str_contains($html, 'awa-align-grid-terminal-2026-06-11')) {
            return $html;
        }

        if (
            !preg_match(
                '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
                $html,
                $versionMatch
            )
        ) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_CSS_FILE
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;

        $tag = '<link rel="stylesheet" href="' . $href . '" media="all"'
            . ' data-awa-align-grid-terminal="1" data-awa-bundle="align-grid-terminal"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    private function normalizeRefineStylesheetQuery(string $html): string
    {
        // Normaliza TODAS as versões antigas (.css?v=X e .min.css?v=X) para .min.css?v=18.
        return preg_replace(
            '/awa-commerce-impeccable-refine\.(?:min\.)?css(?:\?v=[^"\'&\s>]+)?/',
            HeaderImpeccableCascadeLockCss::REFINE_CSS_FILE . HeaderImpeccableCascadeLockCss::REFINE_QUERY,
            $html
        ) ?? $html;
    }

    /**
     * PDP: normaliza versões stale (OPcache / block_html) para terminal round 6.
     */
    private function normalizePdpTerminalStylesheets(string $html): string
    {
        $html = preg_replace(
            '/awa-bundle-async-distill-lock\.min\.css(?:\?v=[^"\'&\s>]+)?/',
            'awa-bundle-async-distill-lock.min.css' . HeaderImpeccableCascadeLockCss::PDP_DISTILL_LOCK_QUERY,
            $html
        ) ?? $html;

        return preg_replace(
            '/awa-ui-simplify-terminal\.min\.css(?:\?v=[^"\'&\s>]+)?/',
            'awa-ui-simplify-terminal.min.css' . HeaderImpeccableCascadeLockCss::PDP_UI_SIMPLIFY_QUERY,
            $html
        ) ?? $html;
    }

    /**
     * @param string[] $fragments
     */
    private function stripStylesheetFragments(string $html, array $fragments): string
    {
        foreach ($fragments as $fragment) {
            $html = preg_replace(
                '/<link\s[^>]*' . preg_quote($fragment, '/') . '[^>]*\/?>\s*/i',
                '',
                $html
            ) ?? $html;
            $html = preg_replace(
                '/<noscript>\s*<link\s[^>]*' . preg_quote($fragment, '/') . '[^>]*\/?>\s*<\/noscript>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    private function stripScriptFragments(string $html, array $fragments): string
    {
        foreach ($fragments as $fragment) {
            $html = preg_replace(
                '/<script\b[^>]*\bsrc=["\'][^"\']*' . preg_quote($fragment, '/') . '(?:\?[^"\']*)?["\'][^>]*>\s*<\/script>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    private function injectGlobalRefineStylesheetIfMissing(string $html): string
    {
        // REFINE_CSS_FRAGMENT não tem extensão: detecta tanto .css quanto .min.css.
        if (str_contains($html, self::REFINE_CSS_FRAGMENT)) {
            return $html;
        }

        if (!preg_match('#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#', $html, $versionMatch)) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::REFINE_CSS_FILE
            . HeaderImpeccableCascadeLockCss::REFINE_QUERY;

        $tag = '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
            . ' data-awa-impeccable-terminal="refine"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * PLP/busca: garante awa-plp-distill mesmo com block_html stale (e2e + terminal wins).
     */
    private function injectPlpDistillStylesheetIfMissing(string $html): string
    {
        if (str_contains($html, 'awa-plp-distill')) {
            return $html;
        }

        if (!preg_match('#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#', $html, $versionMatch)) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-plp-distill.min.css?v=20260610-round5';

        $tag = '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
            . ' data-awa-plp-distill="terminal"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Normaliza handlers onload legados (inline ~250B × N) para __awaCssQ(this) + bootstrap único.
     */
    private function normalizeCssStaggerOnloadHandlers(string $html): string
    {
        $legacy = 'var F=window.__awaCssQ;if(!F){var q=[],M=96;F=window.__awaCssQ=function(e){if(q.length<M){q.push(e);if(!F._r){F._r=1;requestAnimationFrame(function(){!function n(){q.length?(q.shift().media=\'all\',requestAnimationFrame(function(){setTimeout(n,32)})):F._r=0}()})}}}};F(this)';
        if (!str_contains($html, $legacy)) {
            return $html;
        }

        $bootstrap = '<script>!function(w){var q=[],r=0;w.__awaCssQ=function(e){if(q.length<96){q.push(e);if(!r){r=1;w.requestAnimationFrame(function t(){q.length?(q.shift().media="all",w.requestAnimationFrame(function(){setTimeout(t,40)})):r=0})}}}}(window);</script>';
        if (!str_contains($html, 'w.__awaCssQ=function')) {
            $replaced = preg_replace(
                '/(<!-- AWA CSS Stagger[^>]*-->\s*)?(<link\s[^>]*rel=["\']stylesheet["\'])/i',
                '$1' . $bootstrap . "\n" . '$2',
                $html,
                1
            );
            if (is_string($replaced)) {
                $html = $replaced;
            }
        }

        return str_replace($legacy, '__awaCssQ(this)', $html);
    }

    private function stripRedundantAsyncNoscript(string $html): string
    {
        $pattern = '/(<link\s(?=[^>]*rel=["\']stylesheet["\'])'
            . '(?=[^>]*media=["\']print["\'])[^>]*href=(["\'])([^"\']+)\2[^>]*\/?>)'
            . '\s*<noscript>\s*<link\s[^>]*href=\2\3\2[^>]*\/?>\s*<\/noscript>/i';

        $previous = null;
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace($pattern, '$1', $html) ?? $html;
        }

        return $html;
    }

    private function stripStandaloneStylesheetNoscript(string $html): string
    {
        $pattern = '/<noscript>\s*<link\s[^>]*rel=["\']stylesheet["\'][^>]*\/?>\s*<\/noscript>/i';

        return preg_replace($pattern, '', $html) ?? $html;
    }


    private function stripStylePreloadDuplicates(string $html): string
    {
        if (!preg_match_all('/<link\s[^>]*rel=["\']stylesheet["\'][^>]*href=(["\'])([^"\']+)\1[^>]*\/?>/i', $html, $stylesheetMatches)) {
            return $html;
        }

        $stylesheetHrefs = [];
        foreach ($stylesheetMatches[2] as $href) {
            if (!is_string($href) || $href === '') {
                continue;
            }

            $stylesheetHrefs[html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')] = true;
        }

        if ($stylesheetHrefs === []) {
            return $html;
        }

        return preg_replace_callback(
            '/<link\s(?=[^>]*rel=["\']preload["\'])(?=[^>]*as=["\']style["\'])[^>]*href=(["\'])([^"\']+)\1[^>]*\/?>\s*/i',
            static function (array $matches) use ($stylesheetHrefs): string {
                $href = html_entity_decode($matches[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href !== '' && isset($stylesheetHrefs[$href])) {
                    return '';
                }

                return $matches[0];
            },
            $html
        ) ?? $html;
    }

    private function injectGlobalFocusVisibleFallback(string $html): string
    {
        if (str_contains($html, 'id="awa-focus-visible-global-guard"')) {
            return $html;
        }

        $style = '<style id="awa-focus-visible-global-guard">'
            . ':where(a,button,[role="button"],input,select,textarea,[tabindex]:not([tabindex="-1"])):focus-visible{outline:2px solid var(--awa-primary,#b73337)!important;outline-offset:2px!important}'
            . '</style>';

        $injected = preg_replace('/<\/head>/i', $style . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    private function injectGlobalWebVitalsRum(string $html): string
    {
        if (str_contains($html, 'id="awa-web-vitals-rum"')) {
            return $html;
        }

        $script = '<script id="awa-web-vitals-rum">'
            . '(function(w,d,p){"use strict";if(w.__awaWebVitalsRumInit){return;}w.__awaWebVitalsRumInit=1;'
            . 'var s=(w.PerformanceObserver&&w.PerformanceObserver.supportedEntryTypes)?w.PerformanceObserver.supportedEntryTypes:[],sent={},cls=0,lcp=null,inp=0,inpSrc="event",path=(w.location&&w.location.pathname)?w.location.pathname:"/",low=(path||"").toLowerCase(),kind=(low==="/"||low==="/index.php")?"home":"internal",nav=(p&&typeof p.getEntriesByType==="function")?p.getEntriesByType("navigation")[0]:null,done=false;'
            . 'function r(v,dg){var f;if(typeof v!=="number"||!isFinite(v)){return null;}f=Math.pow(10,dg||0);return Math.round(v*f)/f;}'
            . 'function rate(n,v){if(n==="LCP"){return v<=2500?"good":(v<=4000?"needs-improvement":"poor");}if(n==="CLS"){return v<=0.1?"good":(v<=0.25?"needs-improvement":"poor");}if(n==="INP"){return v<=200?"good":(v<=500?"needs-improvement":"poor");}if(n==="FCP"){return v<=1800?"good":(v<=3000?"needs-improvement":"poor");}if(n==="TTFB"){return v<=800?"good":(v<=1800?"needs-improvement":"poor");}return "unknown";}'
            . 'function push(n,v,e){var pld,val=r(v,n==="CLS"?4:0);if(val===null||sent[n]){return;}sent[n]=1;w.dataLayer=w.dataLayer||[];pld={event:"awa_web_vital",web_vital_name:n,web_vital_value:val,web_vital_rating:rate(n,val),web_vital_page_path:path,web_vital_page_kind:kind};if(e&&typeof e==="object"){if(e.id){pld.web_vital_id=e.id;}if(e.delta!==undefined&&e.delta!==null){pld.web_vital_delta=r(e.delta,n==="CLS"?4:0);}if(e.navigationType){pld.web_vital_navigation_type=e.navigationType;}if(e.source){pld.web_vital_source=e.source;}}try{w.dataLayer.push(pld);}catch(_){}}'
            . 'function fin(){if(done){return;}done=true;if(lcp&&typeof lcp.startTime==="number"){push("LCP",lcp.startTime,{id:lcp.id});}push("CLS",cls);if(inp>0){push("INP",inp,{source:inpSrc});}}'
            . 'if(nav&&typeof nav.responseStart==="number"){push("TTFB",nav.responseStart,{navigationType:nav.type||"navigate"});}'
            . 'if(s.indexOf("paint")!==-1){try{(new w.PerformanceObserver(function(list){list.getEntries().forEach(function(en){if(en&&en.name==="first-contentful-paint"){push("FCP",en.startTime,{id:en.name});}});})).observe({type:"paint",buffered:true});}catch(_){}}'
            . 'if(s.indexOf("largest-contentful-paint")!==-1){try{(new w.PerformanceObserver(function(list){var es=list.getEntries();if(es.length){lcp=es[es.length-1];}})).observe({type:"largest-contentful-paint",buffered:true});}catch(_){}}'
            . 'if(s.indexOf("layout-shift")!==-1){try{(new w.PerformanceObserver(function(list){list.getEntries().forEach(function(en){if(en&&!en.hadRecentInput){cls+=en.value;}});})).observe({type:"layout-shift",buffered:true});}catch(_){}}'
            . 'if(s.indexOf("event")!==-1){try{(new w.PerformanceObserver(function(list){list.getEntries().forEach(function(en){if(en&&en.interactionId&&en.duration>inp){inp=en.duration;}});})).observe({type:"event",buffered:true,durationThreshold:40});}catch(_){}}else if(s.indexOf("first-input")!==-1){inpSrc="first-input";try{(new w.PerformanceObserver(function(list){var fi=list.getEntries()[0];if(fi&&fi.duration>inp){inp=fi.duration;}})).observe({type:"first-input",buffered:true});}catch(_){}}'
            . 'w.addEventListener("pagehide",fin,{capture:true,once:true});d.addEventListener("visibilitychange",function(){if(d.visibilityState==="hidden"){fin();}},{capture:true,once:true});w.addEventListener("load",function(){w.setTimeout(fin,15000);},{once:true});'
            . '})(window,document,window.performance||null);'
            . '</script>';

        $injected = preg_replace('/<\/head>/i', $script . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    private function gateHomeLargeStylesheets(string $html): string
    {
        if (!str_contains($html, 'id="awa-css-gate-queue"')) {
            return $html;
        }

        $urlsToGate = [];
        $fragmentPattern = static function (string $fragment): string {
            return preg_quote($fragment, '/');
        };

        foreach (self::HOME_GATE_CSS_FRAGMENTS as $fragment) {
            if ($this->isHomeNeverGateFragment($fragment)) {
                continue;
            }

            if (
                preg_match(
                    '/href=(["\'])([^"\']*' . $fragmentPattern($fragment) . '[^"\']*)\1/is',
                    $html,
                    $match
                )
            ) {
                $urlsToGate[] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            /* Uma tag <link> por match — sem [\s\S] livre (evita comer o documento inteiro). */
            $html = preg_replace(
                '/<noscript>\s*<link\s[^>]*' . $fragmentPattern($fragment) . '[^>]*\/?>\s*<\/noscript>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        $urlsToGate = array_values(array_filter(array_unique($urlsToGate)));

        if ($urlsToGate === []) {
            return $html;
        }

        return $this->mergeIntoCssGateQueue($html, $urlsToGate);
    }

    /**
     * Home: remove da fila gate folhas omitidas no preload (consolidadas em critical-home / gate-polish).
     */
    private function pruneHomeNeverGateQueueUrls(string $html): string
    {
        $urls = $this->extractGateQueueUrls($html);
        if ($urls === []) {
            return $html;
        }

        $filtered = array_values(array_filter(
            $urls,
            function ($url): bool {
                if (!is_string($url)) {
                    return false;
                }
                foreach (self::HOME_NEVER_GATE_FRAGMENTS as $never) {
                    if (str_contains($url, $never)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        if (count($filtered) === count($urls)) {
            return $html;
        }

        return $this->replaceCssGateQueue($html, $filtered);
    }

    /**
     * @param string   $html
     * @param string[] $urls
     */
    private function replaceCssGateQueue(string $html, array $urls): string
    {
        $stylesM = [];
        $rest = [];
        foreach ($urls as $url) {
            if (is_string($url) && str_contains($url, 'styles-m.css')) {
                $stylesM[] = $url;
            } else {
                $rest[] = $url;
            }
        }
        $ordered = array_merge($rest, $stylesM);

        return preg_replace_callback(
            '/<script\s+id="awa-css-gate-queue"\s+type="application\/json"'
            . '(?:\s+data-awa-terminal-href="([^"]*)")?>(\[[^\]]*\])<\/script>/i',
            static function (array $matches) use ($ordered): string {
                $terminalAttr = isset($matches[1]) && $matches[1] !== ''
                    ? ' data-awa-terminal-href="' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                    : '';

                return '<script id="awa-css-gate-queue" type="application/json"' . $terminalAttr . '>'
                    . json_encode($ordered, JSON_UNESCAPED_SLASHES)
                    . '</script>';
            },
            $html,
            1
        ) ?? $html;
    }

    /**
     * Remove blocos <noscript> só com folhas já na fila gate (evita fetch duplicado no HTML).
     */
    private function stripHomeGatedNoscriptFallbacks(string $html): string
    {
        $pattern = '/<noscript>\s*((?:<link\s[^>]*\/?>\s*)+)<\/noscript>/i';

        return preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                $block = $matches[1];
                if (!preg_match_all('/href=(["\'])([^"\']+)\1/i', $block, $hrefMatches)) {
                    return $matches[0];
                }

                foreach ($hrefMatches[2] as $href) {
                    $isGated = false;
                    foreach (self::HOME_GATE_CSS_FRAGMENTS as $fragment) {
                        if (str_contains($href, $fragment)) {
                            $isGated = true;
                            break;
                        }
                    }
                    if (!$isGated) {
                        return $matches[0];
                    }
                }

                return '';
            },
            $html
        ) ?? $html;
    }

    private function removePrintLinksListedInGateQueue(string $html): string
    {
        foreach ($this->extractGateQueueUrls($html) as $url) {
            $escaped = preg_quote($url, '/');
            $html = preg_replace(
                '/<link\s[^>]*rel=["\']stylesheet["\'][^>]*href=["\']' . $escaped . '["\'][^>]*\/?>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        return $this->removePreloadsListedInGateQueue($html);
    }

    /** Evita preload de folhas já na fila idle (gate) — economiza ~150KB no 1º paint. */
    private function removePreloadsListedInGateQueue(string $html): string
    {
        foreach ($this->extractGateQueueUrls($html) as $url) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            if ($path === '') {
                continue;
            }
            $base = basename($path);
            if ($base === '') {
                continue;
            }
            $escaped = preg_quote($base, '/');
            $html = preg_replace(
                '/<link\s[^>]*rel=["\']preload["\'][^>]*href=["\'][^"\']*' . $escaped . '[^"\']*["\'][^>]*\/?>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    /**
     * @return string[]
     */
    private function extractGateQueueUrls(string $html): array
    {
        if (
            !preg_match(
                '/<script\s+id="awa-css-gate-queue"\s+type="application\/json"'
                . '(?:\s+data-awa-terminal-href="[^"]*")?>(\[[^\]]*\])<\/script>/i',
                $html,
                $match
            )
        ) {
            return [];
        }

        $decoded = json_decode($match[1], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dedupeStylesheetHrefs(string $html): string
    {
        $pattern = '/<link\s[^>]*rel=["\']stylesheet["\'][^>]*\/?>/i';
        $hrefCounts = [];
        $hasActiveTag = [];
        $seen = [];

        $isActiveStylesheet = static function (string $tag): bool {
            return !preg_match('/media=["\']print["\']/i', $tag)
                && !preg_match('/onload\s*=/i', $tag);
        };

        // Exclude <noscript> content from analysis — links inside noscript are fallbacks,
        // not active stylesheets. Counting them as active causes async print/onload links
        // to be incorrectly removed (the noscript link triggers hasActiveTag, removing the async one).
        $htmlForAnalysis = preg_replace('/<noscript>.*?<\/noscript>/is', '', $html) ?? $html;

        if (!preg_match_all($pattern, $htmlForAnalysis, $stylesheetMatches)) {
            return $html;
        }

        foreach ($stylesheetMatches[0] as $tag) {
            if (!preg_match('/href=(["\'])([^"\']+)\1/i', $tag, $hrefMatch)) {
                continue;
            }

            $href = $hrefMatch[2];
            $hrefCounts[$href] = ($hrefCounts[$href] ?? 0) + 1;
            if ($isActiveStylesheet($tag)) {
                $hasActiveTag[$href] = true;
            }
        }

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use (&$seen, $hrefCounts, $hasActiveTag, $isActiveStylesheet): string {
                $tag = $matches[0];
                if (!preg_match('/href=(["\'])([^"\']+)\1/i', $tag, $hrefMatch)) {
                    return $tag;
                }

                $href = $hrefMatch[2];
                if (($hrefCounts[$href] ?? 0) < 2) {
                    return $tag;
                }

                $active = $isActiveStylesheet($tag);
                if (($hasActiveTag[$href] ?? false) && !$active) {
                    return '';
                }

                if (isset($seen[$href])) {
                    return '';
                }

                $seen[$href] = true;

                return $tag;
            },
            $html
        ) ?? $html;
    }

    /**
     * @param string   $html
     * @param string[] $urls
     */
    private function mergeIntoCssGateQueue(string $html, array $urls): string
    {
        return preg_replace_callback(
            '/<script\s+id="awa-css-gate-queue"\s+type="application\/json"'
            . '(?:\s+data-awa-terminal-href="([^"]*)")?>(\[[^\]]*\])<\/script>/i',
            static function (array $matches) use ($urls): string {
                $existing = json_decode($matches[2], true);
                if (!is_array($existing)) {
                    $existing = [];
                }
                $merged = array_values(array_unique(array_merge($urls, $existing)));
                $stylesM = [];
                $rest = [];
                foreach ($merged as $url) {
                    if (is_string($url) && str_contains($url, 'styles-m.css')) {
                        $stylesM[] = $url;
                    } else {
                        $rest[] = $url;
                    }
                }
                $merged = array_merge($rest, $stylesM);
                $terminalAttr = isset($matches[1]) && $matches[1] !== ''
                    ? ' data-awa-terminal-href="' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                    : '';

                return '<script id="awa-css-gate-queue" type="application/json"' . $terminalAttr . '>'
                    . json_encode($merged, JSON_UNESCAPED_SLASHES)
                    . '</script>';
            },
            $html,
            1
        ) ?? $html;
    }
}
