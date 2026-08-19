<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Rokanthemes\LayeredAjax\Helper\Data as LayeredAjaxHelper;

/**
 * Layered navigation ViewModel — Magento Catalog + Tax + LayeredAjax helpers.
 */
class LayeredNav implements ArgumentInterface
{
    public function __construct(
        private readonly CatalogHelper $catalogHelper,
        private readonly LayeredAjaxHelper $layeredAjaxHelper,
        private readonly TaxHelper $taxHelper
    ) {
    }

    public function shouldDisplayProductCount(): bool
    {
        return (bool) $this->catalogHelper->shouldDisplayProductCountOnLayer();
    }

    public function isOpenAllTab(): bool
    {
        return (bool) $this->layeredAjaxHelper->isOpenAllTab();
    }

    public function isPriceRangeSliderEnabled(): bool
    {
        return (bool) $this->layeredAjaxHelper->isEnabledPriceRangeSliders();
    }

    /**
     * @param mixed $store
     * @return mixed
     */
    public function getPriceFormat($store = null)
    {
        return $this->taxHelper->getPriceFormat($store);
    }
}
