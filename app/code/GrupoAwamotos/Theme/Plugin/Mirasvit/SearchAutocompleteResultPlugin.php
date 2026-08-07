<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Mirasvit;

use Magento\Framework\Phrase;
use Mirasvit\SearchAutocomplete\Model\Result;

/**
 * Alinha o CTA "Ver todos" ao total de produtos (PLP catalogsearch),
 * normaliza nomes vindos do índice/ERP, remove CMS "Informações" do
 * painel (menos DOM/JSON) e compacta payload de produto.
 */
class SearchAutocompleteResultPlugin
{
    /** Índices omitidos do autocomplete (não da PLP de busca). */
    private const EXCLUDED_INDEX_IDENTIFIERS = [
        'magento_cms_page',
    ];

    /** Chaves pesadas/não usadas no template AWA quando flags off. */
    private const PRODUCT_PAYLOAD_DROP_KEYS = [
        'description',
        'rating',
        'reviews',
        'addToCartUrl',
    ];

    /**
     * @param Result $subject
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterToArray(Result $subject, array $result): array
    {
        $productTotal = 0;
        $indexes = $result['indexes'] ?? [];

        if (!is_array($indexes)) {
            return $result;
        }

        $normalizedIndexes = [];

        foreach ($indexes as $index) {
            if (!is_array($index)) {
                continue;
            }

            $identifier = (string) ($index['identifier'] ?? '');
            if (in_array($identifier, self::EXCLUDED_INDEX_IDENTIFIERS, true)) {
                continue;
            }

            if ($identifier === 'magento_catalog_product' || $identifier === 'catalogsearch_fulltext') {
                $productTotal = (int) ($index['totalItems'] ?? 0);
            }

            if (!empty($index['items']) && is_array($index['items'])) {
                foreach ($index['items'] as $itemKey => $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    if (!empty($item['name']) && is_string($item['name'])) {
                        $index['items'][$itemKey]['name'] = trim(
                            preg_replace('/\s+/u', ' ', $item['name']) ?? $item['name']
                        );
                    }

                    if ($identifier === 'magento_catalog_product' || $identifier === 'catalogsearch_fulltext') {
                        foreach (self::PRODUCT_PAYLOAD_DROP_KEYS as $dropKey) {
                            unset($index['items'][$itemKey][$dropKey]);
                        }
                    }
                }
            }

            $normalizedIndexes[] = $index;
        }

        $result['indexes'] = $normalizedIndexes;

        // Recalcula totalItems sem CMS (evita contagem inflada no painel).
        $visibleTotal = 0;
        foreach ($normalizedIndexes as $index) {
            $visibleTotal += (int) ($index['totalItems'] ?? 0);
        }
        $result['totalItems'] = $visibleTotal;
        $result['noResults'] = $visibleTotal === 0;

        if ($productTotal > 0) {
            /** @var Phrase $phrase */
            $phrase = __('Ver todos os %1 resultados →', $productTotal);
            $result['textAll'] = html_entity_decode((string) $phrase, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $result;
    }
}
