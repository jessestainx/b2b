<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Vitrine "Mais Vendidos" da home — resiliente a módulo Rokanthemes desabilitado e pedidos vazios.
 */
class HomeBestsellerCarousel extends CategoryProductCarousel
{
    private const DEFAULT_QTY = 4;

    private const MAX_PER_ROOT_CATEGORY = 1;

    private ?Collection $resolvedCollection = null;

    private ?bool $usingSalesFallback = null;

    /**
     * Mais vendidos por vendas; se vazio, fallback para lançamentos recentes no catálogo.
     */
    public function getProducts(): Collection
    {
        if ($this->resolvedCollection !== null) {
            return $this->resolvedCollection;
        }

        $qty = $this->resolveQty();
        $poolQty = max(32, $qty * 8);
        $this->setData('qty', $poolQty);
        $collection = parent::getProducts();
        $this->setData('qty', $qty);
        $this->usingSalesFallback = false;

        if ((int) $collection->getSize() === 0) {
            $this->usingSalesFallback = true;
            $collection = $this->buildNewestFallbackCollection($qty);
        } else {
            $collection = $this->buildDiversifiedCollection($collection, $qty);
        }

        $this->resolvedCollection = $collection;

        return $collection;
    }

    /**
     * True quando não há ranking de vendas e a vitrine usa produtos recentes.
     */
    public function isUsingSalesFallback(): bool
    {
        if ($this->usingSalesFallback === null) {
            $this->getProducts();
        }

        return $this->usingSalesFallback ?? false;
    }

    public function hasProducts(): bool
    {
        return $this->getProducts()->getSize() > 0;
    }

    /**
     * @param mixed $att
     */
    public function getConfig($att): mixed
    {
        if ($att === 'title') {
            return $this->getData('title') ?? '';
        }

        return parent::getConfig($att);
    }

    private function resolveQty(): int
    {
        $qty = $this->getData('qty') ?: $this->getConfig('qty') ?: self::DEFAULT_QTY;

        return max(1, (int) $qty);
    }

    private function buildNewestFallbackCollection(int $qty): Collection
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $collection = $this->productCollectionFactory->create()->setStoreId($storeId);

        $collection
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite()
            ->setVisibility($this->productVisibility->getVisibleInCatalogIds())
            ->addAttributeToFilter('image', ['neq' => 'no_selection'])
            ->addAttributeToSort('created_at', 'desc');

        $collection->setPageSize(max($qty * 4, 16))->setCurPage(1);

        $this->_eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $collection]
        );

        return $this->buildDiversifiedCollection($collection, $qty);
    }

    /**
     * Limita repetição da mesma categoria raiz e exclui produtos sem imagem válida.
     */
    private function buildDiversifiedCollection(Collection $source, int $qty): Collection
    {
        $picked = [];
        $rootCategoryCounts = [];
        $storeId = (int) $this->storeManager->getStore()->getId();

        foreach ($source as $product) {
            if (!$this->hasValidImage($product)) {
                continue;
            }

            $rootCategoryId = $this->resolveRootCategoryId($product);
            $rootCount = $rootCategoryCounts[$rootCategoryId] ?? 0;
            if ($rootCount >= self::MAX_PER_ROOT_CATEGORY) {
                continue;
            }

            $picked[] = (int) $product->getId();
            $rootCategoryCounts[$rootCategoryId] = $rootCount + 1;

            if (count($picked) >= $qty) {
                break;
            }
        }

        if (count($picked) < $qty) {
            foreach ($source as $product) {
                $productId = (int) $product->getId();
                if (in_array($productId, $picked, true) || !$this->hasValidImage($product)) {
                    continue;
                }

                $rootCategoryId = $this->resolveRootCategoryId($product);
                $rootCount = $rootCategoryCounts[$rootCategoryId] ?? 0;
                if ($rootCount >= 2) {
                    continue;
                }

                $picked[] = $productId;
                $rootCategoryCounts[$rootCategoryId] = $rootCount + 1;

                if (count($picked) >= $qty) {
                    break;
                }
            }
        }

        if ($picked === []) {
            return $source;
        }

        $collection = $this->productCollectionFactory->create()->setStoreId($storeId);
        $collection
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite()
            ->setVisibility($this->productVisibility->getVisibleInCatalogIds())
            ->addAttributeToFilter('entity_id', ['in' => $picked])
            ->addAttributeToFilter('image', ['neq' => 'no_selection']);

        $collection->getSelect()->order(new \Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $picked) . ')'));

        $this->_eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $collection]
        );

        return $collection;
    }

    private function hasValidImage(Product $product): bool
    {
        $image = (string) $product->getData('image');

        return $image !== '' && $image !== 'no_selection';
    }

    private function resolveRootCategoryId(Product $product): int
    {
        $categoryIds = array_values(array_filter(
            array_map('intval', (array) $product->getCategoryIds()),
            static fn (int $id): bool => $id > 2
        ));

        if ($categoryIds === []) {
            return 0;
        }

        sort($categoryIds);
        $categoryId = (int) end($categoryIds);

        $path = (string) $this->connection->fetchOne(
            'SELECT path FROM ' . $this->resource->getTableName('catalog_category_entity') . ' WHERE entity_id = ?',
            [$categoryId]
        );

        if ($path === '') {
            return $categoryId;
        }

        $parts = explode('/', $path);

        return isset($parts[2]) ? (int) $parts[2] : $categoryId;
    }
}
