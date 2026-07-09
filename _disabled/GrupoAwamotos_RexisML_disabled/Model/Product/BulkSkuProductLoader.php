<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class BulkSkuProductLoader
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param string[] $skus
     * @return array<string, Product>
     */
    public function loadBySkus(array $skus): array
    {
        $normalizedSkus = [];
        foreach ($skus as $sku) {
            $sku = trim((string)$sku);
            if ($sku === '') {
                continue;
            }

            $normalizedSkus[$sku] = $sku;
        }

        if ($normalizedSkus === []) {
            return [];
        }

        $storeId = (int)$this->storeManager->getStore()->getId();
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect([
            'name',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'small_image',
            'thumbnail',
            'image',
            'url_key',
            'url_path',
            'status',
            'visibility',
        ]);
        $collection->addAttributeToFilter('sku', ['in' => array_values($normalizedSkus)]);

        $productsBySku = [];
        foreach ($collection as $product) {
            $sku = (string)$product->getSku();
            if ($sku !== '') {
                $productsBySku[$sku] = $product;
            }
        }

        return $productsBySku;
    }
}
