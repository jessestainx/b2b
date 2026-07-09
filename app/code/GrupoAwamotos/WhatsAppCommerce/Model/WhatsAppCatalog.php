<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\CatalogInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WhatsAppCatalog implements CatalogInterface
{
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryFactory $categoryFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function search(string $q, int $page = 1): array
    {
        $limit = $this->config->getMaxProductsPerResponse();
        $q = trim($q);

        if ($q === '') {
            return ['products' => [], 'total' => 0, 'page' => $page];
        }

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'price', 'special_price', 'thumbnail', 'url_key', 'short_description']);
            $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', ['in' => [
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
            ]]);
            $collection->addStoreFilter($this->storeManager->getStore()->getId());

            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => "%{$q}%"],
                ['attribute' => 'sku', 'like' => "%{$q}%"],
            ]);

            $collection->setPageSize($limit);
            $collection->setCurPage($page);
            $collection->addFinalPrice();

            $products = [];
            foreach ($collection as $product) {
                $products[] = $this->formatProduct($product);
            }

            return [
                'products' => $products,
                'total' => (int) $collection->getSize(),
                'page' => $page,
                'pages' => (int) $collection->getLastPageNumber(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCatalog::search error', [
                'query' => $q,
                'error' => $e->getMessage(),
            ]);
            return ['products' => [], 'total' => 0, 'page' => $page, 'error' => 'Erro na busca'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCategories(): array
    {
        try {
            $rootCatId = (int) $this->storeManager->getStore()->getRootCategoryId();
            $rootCategory = $this->categoryRepository->get($rootCatId);
            $children = $rootCategory->getChildrenCategories();

            $categories = [];
            foreach ($children as $child) {
                if (!$child->getIsActive()) {
                    continue;
                }
                $categories[] = [
                    'id' => (int) $child->getId(),
                    'name' => (string) $child->getName(),
                    'url' => $child->getUrl(),
                ];
            }

            return ['categories' => $categories];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCatalog::getCategories error', [
                'error' => $e->getMessage(),
            ]);
            return ['categories' => [], 'error' => 'Erro ao buscar categorias'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getProductsByCategory(int $categoryId, int $page = 1): array
    {
        $limit = $this->config->getMaxProductsPerResponse();

        try {
            $category = $this->categoryRepository->get($categoryId);

            $collection = $this->productCollectionFactory->create();
            $collection->addCategoriesFilter(['in' => [$categoryId]]);
            $collection->addAttributeToSelect(['name', 'price', 'special_price', 'thumbnail', 'url_key', 'short_description']);
            $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', ['in' => [
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
            ]]);
            $collection->addStoreFilter($this->storeManager->getStore()->getId());
            $collection->setPageSize($limit);
            $collection->setCurPage($page);
            $collection->addFinalPrice();

            $products = [];
            foreach ($collection as $product) {
                $products[] = $this->formatProduct($product);
            }

            return [
                'category' => $category->getName(),
                'products' => $products,
                'total' => (int) $collection->getSize(),
                'page' => $page,
                'pages' => (int) $collection->getLastPageNumber(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCatalog::getProductsByCategory error', [
                'categoryId' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return ['products' => [], 'total' => 0, 'error' => 'Erro ao buscar produtos da categoria'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getProduct(string $sku): array
    {
        try {
            $product = $this->productRepository->get($sku);
            $data = $this->formatProduct($product);

            $stockItem = $product->getExtensionAttributes()?->getStockItem();
            $data['in_stock'] = $stockItem ? $stockItem->getIsInStock() : true;
            $data['qty'] = $stockItem ? (int) $stockItem->getQty() : 0;

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppCatalog::getProduct error', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Produto não encontrado'];
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array<string, mixed>
     */
    private function formatProduct($product): array
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        $thumbnail = $product->getThumbnail();
        $imageUrl = ($thumbnail && $thumbnail !== 'no_selection')
            ? $mediaUrl . 'catalog/product' . $thumbnail
            : '';

        $price = (float) $product->getFinalPrice();
        $originalPrice = (float) $product->getPrice();
        $hasDiscount = $originalPrice > $price && $price > 0;

        return [
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => $price,
            'price_formatted' => 'R$ ' . number_format($price, 2, ',', '.'),
            'original_price' => $hasDiscount ? $originalPrice : null,
            'discount_percent' => $hasDiscount ? (int) round((1 - $price / $originalPrice) * 100) : null,
            'image_url' => $imageUrl,
            'url' => $baseUrl . $product->getUrlKey() . '.html',
            'short_description' => strip_tags((string) $product->getShortDescription()),
        ];
    }
}
