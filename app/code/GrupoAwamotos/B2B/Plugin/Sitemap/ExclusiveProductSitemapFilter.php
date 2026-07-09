<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Sitemap;

use Magento\Framework\App\ResourceConnection;
use Magento\Sitemap\Model\ResourceModel\Catalog\Product as SitemapProductResource;

/**
 * Remove produtos Private Label do sitemap.xml.
 * SKUs como "7070 P NACIONAL" não devem ser indexados por buscadores — um
 * concorrente não pode descobrir o portfólio OEM de outro cliente navegando
 * no sitemap público.
 */
class ExclusiveProductSitemapFilter
{
    private const TABLE = 'grupoawamotos_b2b_exclusive_product';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Remove do resultado qualquer produto que seja exclusivo de algum cliente.
     * O array retornado pelo método original é indexado por entity_id do produto.
     */
    public function afterGetCollection(
        SitemapProductResource $subject,
        array $result
    ): array {
        if (empty($result)) {
            return $result;
        }

        $exclusiveIds = $this->loadExclusiveProductIds();

        if (empty($exclusiveIds)) {
            return $result;
        }

        foreach ($exclusiveIds as $productId) {
            unset($result[$productId]);
        }

        return $result;
    }

    /** @return int[] */
    private function loadExclusiveProductIds(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName(self::TABLE);

        return array_map(
            'intval',
            $connection->fetchCol(
                $connection->select()->from($table, ['product_id'])
            )
        );
    }
}
