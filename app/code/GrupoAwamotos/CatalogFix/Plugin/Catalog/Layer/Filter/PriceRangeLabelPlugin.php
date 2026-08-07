<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Catalog\Layer\Filter;

use Magento\Catalog\Model\Layer\Filter\Price\Render;

/**
 * Phrase::__toString() escapa HTML dos preços formatados (span.price),
 * gerando labels visíveis como "&lt;span class=\"price\"&gt;R$ …".
 * Decodifica uma vez no retorno de renderRangeLabel.
 */
class PriceRangeLabelPlugin
{
    /**
     * @param Render $subject
     * @param mixed $result
     * @param float|string $fromPrice
     * @param float|string $toPrice
     * @return string
     */
    public function afterRenderRangeLabel(
        Render $subject,
        mixed $result,
        $fromPrice = null,
        $toPrice = null
    ): string {
        $label = (string) $result;
        if ($label !== '' && str_contains($label, '&lt;span')) {
            return htmlspecialchars_decode($label, ENT_QUOTES | ENT_HTML5);
        }

        return $label;
    }
}
