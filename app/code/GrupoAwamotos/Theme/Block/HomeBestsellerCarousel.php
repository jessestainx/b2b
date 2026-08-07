<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Vitrine "Mais Vendidos" da home — ranking real por qty_ordered (janela recente).
 */
class HomeBestsellerCarousel extends CategoryProductCarousel
{
    private const DEFAULT_QTY = 8;

    /** Janela principal de vendas (dias). */
    private const SALES_WINDOW_DAYS = 90;

    /** Fallback se a janela principal não encher a vitrine. */
    private const SALES_FALLBACK_DAYS = 365;

    /**
     * Diversidade suave: evita 8× a mesma categoria raiz sem esconder o top real
     * (ex.: dois retrovisores #1 e #2).
     */
    private const MAX_PER_ROOT_CATEGORY = 3;

    private const CANCELED_ORDER_STATES = [
        'canceled',
        'closed',
        'paypal_canceled_reversal',
        'fraud',
    ];

    private ?Collection $resolvedCollection = null;

    private ?bool $usingSalesFallback = null;

    /**
     * Mais vendidos por vendas recentes (ordem preservada); fallback para catálogo recente.
     */
    public function getProducts(): Collection
    {
        if ($this->resolvedCollection !== null) {
            return $this->resolvedCollection;
        }

        $qty = $this->resolveQty();
        $this->usingSalesFallback = false;

        $rankedIds = $this->fetchTopSellerIds(self::SALES_WINDOW_DAYS, max(48, $qty * 8));
        if (count($rankedIds) < $qty) {
            $extra = $this->fetchTopSellerIds(self::SALES_FALLBACK_DAYS, max(64, $qty * 10));
            $rankedIds = $this->mergeUniqueIds($rankedIds, $extra);
        }
        if (count($rankedIds) < $qty) {
            $extra = $this->fetchTopSellerIds(0, max(80, $qty * 12));
            $rankedIds = $this->mergeUniqueIds($rankedIds, $extra);
        }

        $picked = $this->pickVisibleRankedIds($rankedIds, $qty);

        if ($picked === []) {
            $this->usingSalesFallback = true;
            $collection = $this->buildNewestFallbackCollection($qty);
        } else {
            $collection = $this->buildCollectionFromIds($picked);
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

        if ($att === 'show_price') {
            $override = $this->getData('show_price');

            return $override !== null ? $override : parent::getConfig($att);
        }

        return parent::getConfig($att);
    }

    private function resolveQty(): int
    {
        $qty = $this->getData('qty') ?: $this->getConfig('qty') ?: self::DEFAULT_QTY;

        return max(1, (int) $qty);
    }

    /**
     * @return list<int>
     */
    private function fetchTopSellerIds(int $days, int $limit): array
    {
        $orderItemTable = $this->resource->getTableName('sales_order_item');
        $orderTable = $this->resource->getTableName('sales_order');

        $select = $this->connection->select()
            ->from(['oi' => $orderItemTable], [
                'product_id' => 'oi.product_id',
                'qty' => new Expression('SUM(oi.qty_ordered)'),
            ])
            ->joinInner(['o' => $orderTable], 'o.entity_id = oi.order_id', [])
            ->where('oi.parent_item_id IS NULL')
            ->where('oi.product_id IS NOT NULL')
            ->where('oi.product_id > 0')
            ->where('o.state NOT IN (?)', self::CANCELED_ORDER_STATES)
            ->group('oi.product_id')
            ->order(['qty DESC', 'oi.product_id ASC'])
            ->limit($limit);

        if ($days > 0) {
            $select->where('o.created_at >= ?', (new \DateTimeImmutable('-' . $days . ' days'))->format('Y-m-d H:i:s'));
        }

        $ids = [];
        foreach ($this->connection->fetchAll($select) as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId > 0) {
                $ids[] = $productId;
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $base
     * @param list<int> $extra
     * @return list<int>
     */
    private function mergeUniqueIds(array $base, array $extra): array
    {
        $seen = array_fill_keys($base, true);
        foreach ($extra as $id) {
            if (!isset($seen[$id])) {
                $base[] = $id;
                $seen[$id] = true;
            }
        }

        return $base;
    }

    /**
     * Mantém ordem do ranking; aplica imagem + diversidade suave por categoria raiz.
     *
     * @param list<int> $rankedIds
     * @return list<int>
     */
    private function pickVisibleRankedIds(array $rankedIds, int $qty): array
    {
        if ($rankedIds === []) {
            return [];
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $source = $this->productCollectionFactory->create()->setStoreId($storeId);
        $source
            ->addAttributeToSelect(['image', 'small_image', 'thumbnail'])
            ->addAttributeToFilter('entity_id', ['in' => $rankedIds])
            ->addAttributeToFilter('image', ['neq' => 'no_selection'])
            ->setVisibility($this->productVisibility->getVisibleInCatalogIds());

        $source->getSelect()->order(
            new Expression('FIELD(e.entity_id,' . implode(',', array_map('intval', $rankedIds)) . ')')
        );

        $byId = [];
        foreach ($source as $product) {
            $byId[(int) $product->getId()] = $product;
        }

        $picked = [];
        $rootCategoryCounts = [];

        foreach ($rankedIds as $productId) {
            if (!isset($byId[$productId])) {
                continue;
            }

            /** @var Product $product */
            $product = $byId[$productId];
            if (!$this->hasValidImage($product)) {
                continue;
            }

            $rootCategoryId = $this->resolveRootCategoryId($product);
            $rootCount = $rootCategoryCounts[$rootCategoryId] ?? 0;
            if ($rootCount >= self::MAX_PER_ROOT_CATEGORY) {
                continue;
            }

            $picked[] = $productId;
            $rootCategoryCounts[$rootCategoryId] = $rootCount + 1;

            if (count($picked) >= $qty) {
                break;
            }
        }

        // Se diversidade cortou demais, completa pela ordem do ranking sem teto de categoria.
        if (count($picked) < $qty) {
            foreach ($rankedIds as $productId) {
                if (in_array($productId, $picked, true) || !isset($byId[$productId])) {
                    continue;
                }
                if (!$this->hasValidImage($byId[$productId])) {
                    continue;
                }
                $picked[] = $productId;
                if (count($picked) >= $qty) {
                    break;
                }
            }
        }

        return $picked;
    }

    /**
     * @param list<int> $picked
     */
    private function buildCollectionFromIds(array $picked): Collection
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
            ->addAttributeToFilter('entity_id', ['in' => $picked])
            ->addAttributeToFilter('image', ['neq' => 'no_selection']);

        $collection->getSelect()->order(
            new Expression('FIELD(e.entity_id,' . implode(',', array_map('intval', $picked)) . ')')
        );
        $collection->setPageSize(count($picked))->setCurPage(1);

        $this->_eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $collection]
        );

        return $collection;
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

        $collection->setPageSize($qty)->setCurPage(1);

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
