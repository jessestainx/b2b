<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block;

use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Carrossel de produtos filtrado por categoria(s) — compatível com bestseller.phtml
 *
 * Uso no phtml:
 *   $b = $layout->createBlock(CategoryProductCarousel::class);
 *   $b->setCategoryIds('46,75,76');   // IDs separados por vírgula
 *   $b->setQty(12);                   // opcional, default 12
 *   $b->setTemplate('Rokanthemes_BestsellerProduct::bestseller.phtml');
 *   echo $b->toHtml();
 */
class CategoryProductCarousel extends \Rokanthemes\BestsellerProduct\Block\Bestseller
{
    private const DEFAULT_QTY = 12;
    private ?Collection $resolvedCollection = null;

    /**
     * Retorna produtos das categorias definidas em category_ids.
     * Sem category_ids definido, delega para o pai (mais vendidos globais).
     *
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProducts(): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        if ($this->resolvedCollection !== null) {
            return $this->resolvedCollection;
        }

        $rawIds = $this->getData('category_ids');

        if (empty($rawIds)) {
            $collection = parent::getProducts();
            $this->resolvedCollection = $collection;
            return $collection;
        }

        $ids = array_filter(
            array_map('intval', explode(',', (string) $rawIds)),
            static fn(int $id): bool => $id > 0
        );

        if (empty($ids)) {
            $collection = parent::getProducts();
            $this->resolvedCollection = $collection;
            return $collection;
        }

        $storeId = $this->storeManager->getStore()->getId();
        $collection = $this->productCollectionFactory->create()->setStoreId($storeId);

        $collection
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addCategoriesFilter(['in' => $ids])
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite()
            ->setVisibility($this->productVisibility->getVisibleInCatalogIds())
            ->addAttributeToFilter('image', ['neq' => 'no_selection'])
            ->addAttributeToSort('entity_id', 'desc');

        $qty = (int) ($this->getData('qty') ?: $this->getConfig('qty') ?: self::DEFAULT_QTY);
        $collection->setPageSize($qty)->setCurPage(1);

        $this->_eventManager->dispatch(
            'catalog_block_product_list_collection',
            ['collection' => $collection]
        );

        $this->resolvedCollection = $collection;

        return $collection;
    }

    public function hasProducts(): bool
    {
        return (int) $this->getProducts()->getSize() > 0;
    }

    /**
     * Garante que getConfig('enabled') retorna 1 para este bloco,
     * mesmo que o módulo bestsellerproduct esteja desabilitado no admin.
     */
    public function getConfig($att): mixed
    {
        if ($att === 'enabled') {
            return 1;
        }

        if ($att === 'show_price') {
            $override = $this->getData('show_price');
            return $override !== null ? $override : 1;
        }

        $override = $this->getData($att);
        if ($override !== null) {
            return $override;
        }

        return parent::getConfig($att);
    }
}
