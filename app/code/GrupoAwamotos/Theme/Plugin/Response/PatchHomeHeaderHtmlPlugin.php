<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use GrupoAwamotos\Theme\Model\HeaderImpeccableCascadeLockCss;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\HttpInterface;

/**
 * Corrige cabeçalho na home quando o HTML completo é atribuído à resposta (FPM).
 */
class PatchHomeHeaderHtmlPlugin
{
    /** Carrinho/checkout: critical inline cobre header — omitir ~170KB cascade-lock. */
    private const CHECKOUT_FOCUS_ACTIONS = [
        'checkout_cart_index',
        'checkout_index_index',
        'onepagecheckout_index_index',
    ];

    private static bool $isPatching = false;

    public function __construct(
        private readonly HttpRequest $request,
    ) {
    }

    public function afterAppendBody(HttpInterface $subject, mixed $result, $value): mixed
    {
        return $this->patchHomeHtmlIfNeeded($subject, $result);
    }

    public function afterSetBody(HttpInterface $subject, mixed $result, $body): mixed
    {
        return $this->patchHomeHtmlIfNeeded($subject, $result);
    }

    public function beforeSendResponse(HttpInterface $subject): void
    {
        $this->patchHomeHtmlIfNeeded($subject, null);
    }

    private function patchHomeHtmlIfNeeded(HttpInterface $subject, mixed $result): mixed
    {
        $html = (string) $subject->getBody();
        $action = $this->request->getFullActionName();

        if (self::$isPatching || $html === '' || !str_contains($html, '</body>')) {
            return $result;
        }

        if (!HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)) {
            return $result;
        }

        self::$isPatching = true;
        try {
            $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;
            $html = preg_replace(
                '/awa-header-refine-terminal\.min\.css(?:\?v=[^"\'&\s>]+)?/',
                'awa-header-refine-terminal.min.css?v=' . $terminalV,
                $html
            ) ?? $html;

            if ($action === 'cms_index_index') {
                // Normaliza qualquer versão legada do gate script para a versão canônica atual.
                // Usa regex para evitar lista crescente de versões hardcoded (BUG-01 Camada 4).
                $html = preg_replace(
                    '/awa-css-gate\.min\.js\?v=[^"\'&\s>]+/',
                    'awa-css-gate.min.js?v=' . HeaderImpeccableCascadeLockCss::GATE_SCRIPT_QUERY,
                    $html
                ) ?? $html;
            }

            if ($action === 'cms_index_index') {
                // Home vis-fix + distill: OptimizeHeadStylesPlugin (sortOrder 998) injeta após distill terminal.
            } elseif (
                in_array($action, self::CHECKOUT_FOCUS_ACTIONS, true)
                || in_array($action, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
                || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html)
                || HeaderImpeccableCascadeLockCss::isB2bAccountOperationalHtml($html)
            ) {
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
            } elseif (
                !HeaderImpeccableCascadeLockCss::isPresentInHtml($html)
                || !str_contains($html, 'awa-header-cascade-lock-guard')
            ) {
                // BUG-MOB-SEARCH-001 (2026-07-04): CATALOG_STACK_ACTIONS foi removido do
                // ramo de strip acima. OptimizeHeadStylesPlugin::execute() já injeta o
                // cascade-lock v18 (com a regra display:grid do form de busca mobile,
                // BUG-01 fix) para catalog_category_view/catalogsearch_result_index/
                // catalog_product_view via injectBeforeBodyClose(); stripLegacyFromHtml()
                // também remove o STYLE_ID atual (não só os legados), então rodar esse
                // strip aqui de novo apagava o v18 recém-injetado, deixando só o guard
                // script (evidência: <style id="v18"> ausente, <script id="...-guard">
                // presente). Agora cai neste ramo, que só reinjeta se realmente faltar.
                $html = HeaderImpeccableCascadeLockCss::injectBeforeBodyClose($html);
            }

            $subject->setBody($html);
            // Não sobrescrever headers especializados (v12-auth, v12-b2b-account) setados pelo OptimizeHeadStylesPlugin.
            $existingHeader = $subject->getHeader('X-Awa-Header-Optimize');
            $existingValue = ($existingHeader && $existingHeader !== false)
                ? (string) $existingHeader->getFieldValue()
                : '';
            if ($existingValue === '' || $existingValue === 'v12') {
                $subject->setHeader('X-Awa-Header-Optimize', 'v12', true);
            }
        } finally {
            self::$isPatching = false;
        }

        return $result;
    }
}
