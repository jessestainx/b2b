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
    private const NO_SELECTION = 'no_selection';

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
        $qty = max(1, (int) ($this->getData('qty') ?: $this->getConfig('qty') ?: self::DEFAULT_QTY));

        // EAV neq no_selection is unreliable (store default / join). Over-fetch then drop placeholders.
        $products->setPageSize(max($qty * 5, 20))->setCurPage(1);
        $products->load();

        $kept = 0;
        foreach ($products->getItems() as $product) {
            $id = (int) $product->getId();
            if (!$this->productHasCatalogImage($product)) {
                $products->removeItemByKey($id);
                continue;
            }
            ++$kept;
            if ($kept > $qty) {
                $products->removeItemByKey($id);
            }
        }

        $this->resolvedCollection = $products;

        return $this->resolvedCollection;
    }

    public function hasProducts(): bool
    {
        return count($this->getProducts()->getItems()) > 0;
    }

    private function productHasCatalogImage(\Magento\Catalog\Model\Product $product): bool
    {
        foreach (['small_image', 'image', 'thumbnail'] as $attr) {
            $value = trim((string) $product->getData($attr));
            if ($value !== '' && $value !== self::NO_SELECTION) {
                return true;
            }
        }

        return false;
    }
}
