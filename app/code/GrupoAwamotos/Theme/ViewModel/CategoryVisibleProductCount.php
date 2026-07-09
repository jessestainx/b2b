<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Retorna contagem de produtos visíveis na PLP (mesma collection do layer/toolbar).
 */
class CategoryVisibleProductCount implements ArgumentInterface
{
    public function __construct(
        private readonly Resolver $layerResolver
    ) {
    }

    public function getCount(Category $category): int
    {
        try {
            $layer = $this->layerResolver->get();
            $layer->setCurrentCategory($category);

            return (int) $layer->getProductCollection()->getSize();
        } catch (\Throwable) {
            return (int) $category->getProductCount();
        }
    }
}
