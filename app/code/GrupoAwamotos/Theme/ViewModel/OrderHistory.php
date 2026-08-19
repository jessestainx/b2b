<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Helper\Reorder;

/**
 * Order history reorder actions without $this->helper() in the template.
 */
class OrderHistory implements ArgumentInterface
{
    public function __construct(
        private readonly Reorder $reorderHelper,
        private readonly PostHelper $postHelper
    ) {
    }

    public function canReorder(int|string $orderId): bool
    {
        return (bool) $this->reorderHelper->canReorder($orderId);
    }

    public function getReorderPostData(string $reorderUrl): string
    {
        return (string) $this->postHelper->getPostData($reorderUrl);
    }
}
