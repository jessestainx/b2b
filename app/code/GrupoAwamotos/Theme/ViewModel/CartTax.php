<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Cart form tax display without injecting Tax Helper via layout XML.
 */
class CartTax implements ArgumentInterface
{
    public function __construct(
        private readonly TaxHelper $taxHelper
    ) {
    }

    public function displayCartBothPrices(): bool
    {
        return (bool) $this->taxHelper->displayCartBothPrices();
    }
}
