<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Block;

use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Vitrine "Lançamentos" da home — respeita qty/setData como os demais shelves.
 */
class HomeNewproductCarousel extends \Rokanthemes\Newproduct\Block\Newproduct
{
    private const DEFAULT_QTY = 4;
    private ?Collection $resolvedCollection = null;

    /**
     * @param mixed $att
     */
    public function getConfig($att): mixed
    {
        $override = $this->getData($att);
        if ($override !== null && $override !== '') {
            return $override;
        }

        return parent::getConfig($att);
    }

    public function getProducts(): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        if ($this->resolvedCollection !== null) {
            return $this->resolvedCollection;
        }

        $products = parent::getProducts();
        $products->addAttributeToFilter('image', ['neq' => 'no_selection']);
        $qty = max(1, (int) ($this->getData('qty') ?: $this->getConfig('qty') ?: self::DEFAULT_QTY));

        if ((int) $products->getPageSize() !== $qty) {
            $products->setPageSize($qty)->setCurPage(1);
        }

        $this->resolvedCollection = $products;

        return $this->resolvedCollection;
    }

    public function hasProducts(): bool
    {
        return (int) $this->getProducts()->getSize() > 0;
    }
}
