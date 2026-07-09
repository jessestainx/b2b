<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * Sincroniza a categoria Ofertas (138) com produtos elegíveis por special_price.
 */
class OfertasCategorySync
{
    public const OFERTAS_CATEGORY_ID = 138;

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryLinkManagementInterface $categoryLinkManagement,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ResourceConnection $resourceConnection,
        private readonly Visibility $productVisibility,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int[]
     */
    public function getEligibleProductIds(): array
    {
        $todayDate = date('Y-m-d');
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);
        $collection->addAttributeToSelect('entity_id');
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->productVisibility->getVisibleInCatalogIds());
        $collection->addAttributeToFilter('special_price', ['gt' => 0]);
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'special_to_date', 'null' => true],
                ['attribute' => 'special_to_date', 'gteq' => $todayDate, 'date' => true],
            ]
        );
        $collection->addAttributeToFilter(
            [
                ['attribute' => 'special_from_date', 'null' => true],
                ['attribute' => 'special_from_date', 'lteq' => $todayDate, 'date' => true],
            ]
        );

        $ids = [];
        foreach ($collection as $product) {
            $ids[] = (int) $product->getId();
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return int[]
     */
    public function getCurrentOfertasProductIds(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_category_product');

        $select = $connection->select()
            ->from($table, ['product_id'])
            ->where('category_id = ?', self::OFERTAS_CATEGORY_ID);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @return array{added: int, removed: int, total: int}
     */
    public function sync(): array
    {
        $eligibleIds = $this->getEligibleProductIds();
        $currentIds = $this->getCurrentOfertasProductIds();

        $toAdd = array_diff($eligibleIds, $currentIds);
        $toRemove = array_diff($currentIds, $eligibleIds);

        $added = 0;
        $removed = 0;

        foreach ($toAdd as $productId) {
            if ($this->assignToOfertasCategory((int) $productId)) {
                $added++;
            }
        }

        foreach ($toRemove as $productId) {
            if ($this->removeFromOfertasCategory((int) $productId)) {
                $removed++;
            }
        }

        $result = [
            'added' => $added,
            'removed' => $removed,
            'total' => count($eligibleIds),
        ];

        if ($added > 0 || $removed > 0) {
            $this->logger->info('[CatalogFix] Ofertas category sync completed.', $result);
        }

        return $result;
    }

    private function assignToOfertasCategory(int $productId): bool
    {
        try {
            $product = $this->productRepository->getById($productId, false, Store::DEFAULT_STORE_ID);
            $categoryIds = array_map('intval', $product->getCategoryIds());

            if (in_array(self::OFERTAS_CATEGORY_ID, $categoryIds, true)) {
                return false;
            }

            $categoryIds[] = self::OFERTAS_CATEGORY_ID;
            $this->categoryLinkManagement->assignProductToCategories(
                $product->getSku(),
                array_values(array_unique($categoryIds))
            );

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('[CatalogFix] Failed to add product to Ofertas category.', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function removeFromOfertasCategory(int $productId): bool
    {
        try {
            $product = $this->productRepository->getById($productId, false, Store::DEFAULT_STORE_ID);
            $categoryIds = array_map('intval', $product->getCategoryIds());

            if (!in_array(self::OFERTAS_CATEGORY_ID, $categoryIds, true)) {
                return false;
            }

            $categoryIds = array_values(array_filter(
                $categoryIds,
                static fn (int $id): bool => $id !== self::OFERTAS_CATEGORY_ID
            ));

            $this->categoryLinkManagement->assignProductToCategories(
                $product->getSku(),
                $categoryIds
            );

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('[CatalogFix] Failed to remove product from Ofertas category.', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
