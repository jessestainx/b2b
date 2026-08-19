<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Rokanthemes\RokanBase\Helper\Data as SaleLabelHelper;
use Rokanthemes\RokanBase\Helper\Newlabel;

/**
 * Layout-safe wrappers for Rokanthemes product badges.
 */
class ProductLabels implements ArgumentInterface
{
    public function __construct(
        private readonly SaleLabelHelper $saleLabelHelper,
        private readonly Newlabel $newLabelHelper
    ) {
    }

    public function saleLabel(Product $product): string
    {
        return (string) $this->saleLabelHelper->showLableSalePrice($product);
    }

    public function isNew(Product $product): bool
    {
        return (bool) $this->newLabelHelper->isProductNew($product);
    }
}
