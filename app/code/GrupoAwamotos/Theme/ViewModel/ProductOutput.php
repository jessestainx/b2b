<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Layout-safe wrapper around Magento\Catalog\Helper\Output.
 *
 * Magento 2.4 still injects the Output helper via Block constructor $data on
 * ListProduct. PDP description/attribute blocks do not — templates must not
 * receive the helper as a layout `xsi:type="object"` argument.
 */
class ProductOutput implements ArgumentInterface
{
    public function __construct(
        private readonly OutputHelper $outputHelper
    ) {
    }

    /**
     * @param mixed $attributeHtml
     */
    public function productAttribute(Product $product, $attributeHtml, string $attributeName): string
    {
        return (string) $this->outputHelper->productAttribute($product, $attributeHtml, $attributeName);
    }
}
