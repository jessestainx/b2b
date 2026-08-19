<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Model;

/**
 * Fase 6 fatias 2–3 — paridade entre catalogStrip* (plugin) e pipeline Magento
 * (PLP + busca).
 *
 * O plugin {@see \GrupoAwamotos\Theme\Plugin\Response\OptimizeHeadStylesPlugin}
 * continua com regex de safety-net. Esta classe documenta o espelhamento em
 * catalog_category_view.xml (Magento_Catalog + Magento_Theme) e
 * catalogsearch_result_index.xml, e o skip de emit do refine em
 * awa-head-preload.phtml para $isCatalogCssGate.
 */
final class CatalogPlpCssParity
{
    /**
     * Fragmentos que o plugin remove na PLP/busca (catalogStripImpeccable
     * + catalogStripLegacyBundle). Devem permanecer no manifest (safety-net).
     *
     * @var list<string>
     */
    public const STRIP_FRAGMENTS = [
        'awa-impeccable-audit-2026-05-28',
        'awa-commerce-impeccable-refine',
        'awa-ui-simplify-terminal',
        'awa-head-tail-bundle',
        'awa-bundle-async-distill-lock',
        'awa-plp-distill',
        'mage/gallery/gallery',
    ];

    /**
     * PageConfig &lt;remove src&gt; de PLP e busca.
     * PLP: Magento_Catalog + Magento_Theme catalog_category_view.xml (gallery e
     * CSS home/checkout no Theme). Busca: lista completa em
     * Magento_CatalogSearch/layout/catalogsearch_result_index.xml.
     *
     * @var list<string>
     */
    public const LAYOUT_REMOVE_SRCS = [
        'css/awa-impeccable-audit-2026-05-28.min.css',
        'css/awa-impeccable-audit-2026-05-28.css',
        'css/awa-commerce-impeccable-refine.min.css',
        'css/awa-ui-simplify-terminal.min.css',
        'css/awa-head-tail-bundle.min.css',
        'css/awa-bundle-async-distill-lock.min.css',
        'css/awa-plp-distill.min.css',
        'css/awa-plp-distill.css',
        'mage/gallery/gallery.css',
        'css/awa-home-corporate-density-grid-2026-06.css',
        'css/awa-home-density-grid-20260611.min.css',
        'css/awa-home-shell-final.css',
        'css/awa-page-b2b-cart-checkout-premium.css',
    ];

    /**
     * @param list<string> $layoutXmlChunks
     * @return list<string>
     */
    public static function missingLayoutRemoves(array $layoutXmlChunks): array
    {
        $joined = implode("\n", $layoutXmlChunks);
        $missing = [];
        foreach (self::LAYOUT_REMOVE_SRCS as $src) {
            $needle = '<remove src="' . $src . '"/>';
            if (!str_contains($joined, $needle)) {
                $missing[] = $src;
            }
        }

        return $missing;
    }

    /**
     * @param list<string> $pluginStripFragments
     * @return list<string>
     */
    public static function missingStripFragments(array $pluginStripFragments): array
    {
        $joined = implode("\n", $pluginStripFragments);
        $missing = [];
        foreach (self::STRIP_FRAGMENTS as $fragment) {
            if (!str_contains($joined, $fragment)) {
                $missing[] = $fragment;
            }
        }

        return $missing;
    }
}
