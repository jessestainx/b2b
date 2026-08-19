<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Model;

/**
 * Fase 6 fatias 4–5 — paridade PageConfig para PDP, carrinho, checkout e B2B.
 *
 * Shells canônicos NÃO entram nas listas de remove.
 * O plugin {@see \GrupoAwamotos\Theme\Plugin\Response\OptimizeHeadStylesPlugin}
 * permanece safety-net (regex).
 */
final class CascadeCommerceCssParity
{
    /**
     * PDP: só catalogStripImpeccable. Gallery / distill / ui-simplify ficam.
     *
     * @var list<string>
     */
    public const PDP_REMOVE_SRCS = [
        'css/awa-impeccable-audit-2026-05-28.min.css',
        'css/awa-impeccable-audit-2026-05-28.css',
        'css/awa-commerce-impeccable-refine.min.css',
    ];

    /**
     * Carrinho: cartStrip. Não remover awa-cart-stack.min.css.
     *
     * @var list<string>
     */
    public const CART_REMOVE_SRCS = [
        'css/awa-ui-promax-bundle.min.css',
        'css/awa-commerce-impeccable-refine.min.css',
        'css/awa-focus-visible.min.css',
        'css/awa-focus-visible.css',
    ];

    /**
     * Checkout Magento + OPC: mesmos cortes pesados; shell final permanece.
     *
     * @var list<string>
     */
    public const CHECKOUT_REMOVE_SRCS = [
        'css/awa-ui-promax-bundle.min.css',
        'css/awa-commerce-impeccable-refine.min.css',
        'css/awa-focus-visible.min.css',
        'css/awa-plp-final-polish.css',
    ];

    /**
     * Auth B2B (além do que b2b_auth_shell.xml já corta).
     *
     * @var list<string>
     */
    public const AUTH_REMOVE_SRCS = [
        'css/awa-ui-promax-bundle.min.css',
        'css/awa-commerce-impeccable-refine.min.css',
        'css/awa-structural-fix-2026-05-20.min.css',
        'css/awa-impeccable-layout-2026-06-16.min.css',
        'css/awa-plp-final-polish.css',
        'css/awa-ui-ux-pro-max-header-2026-05-19.min.css',
        'css/custom_default.css',
        'css/styles-m.css',
        'css/styles-l.css',
    ];

    /**
     * Conta B2B: b2bAccountStrip. Shell via loader, não via estes src.
     *
     * @var list<string>
     */
    public const ACCOUNT_REMOVE_SRCS = [
        'css/awa-plp-final-polish.css',
        'css/awa-audit-bundle.min.css',
        'css/awa-audit-bundle.css',
        'css/awa-carousel-bundle.min.css',
    ];

    /**
     * @var array<string, string>
     */
    public const SHELL_KEEP = [
        'cart' => 'css/awa-cart-stack.min.css',
        'checkout' => 'css/awa-checkout-shell-final.css',
        'auth' => 'css/awa-b2b-auth-shell-final.css',
        'account' => 'awa-b2b-account-shell-final-loader.phtml',
    ];

    /**
     * @param list<string> $requiredSrcs
     * @param list<string> $layoutXmlChunks
     * @return list<string>
     */
    public static function missingRemoves(array $requiredSrcs, array $layoutXmlChunks): array
    {
        $joined = implode("\n", $layoutXmlChunks);
        $missing = [];
        foreach ($requiredSrcs as $src) {
            $needle = '<remove src="' . $src . '"/>';
            if (!str_contains($joined, $needle)) {
                $missing[] = $src;
            }
        }

        return $missing;
    }

    public static function missingNeedle(string $needle, string $layoutXml): bool
    {
        return !str_contains($layoutXml, $needle);
    }
}
