<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use GrupoAwamotos\Theme\Helper\HeaderExperiment as HeaderExperimentHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Layout-safe wrapper around the header experiment helper.
 *
 * Magento 2.4 layout `xsi:type="object"` arguments must implement
 * ArgumentInterface. Helpers do not — injecting them removes the block.
 */
class HeaderExperiment implements ArgumentInterface
{
    public function __construct(
        private readonly HeaderExperimentHelper $headerExperimentHelper
    ) {
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function getPayload(?int $storeId = null): array
    {
        return $this->headerExperimentHelper->getPayload($storeId);
    }
}
