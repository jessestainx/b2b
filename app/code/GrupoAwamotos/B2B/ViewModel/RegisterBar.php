<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for the B2B register incentive bar (awa-b2b-register-bar.phtml).
 *
 * Exposes B2B config data without using the deprecated $this->helper() pattern.
 */
class RegisterBar implements ArgumentInterface
{
    public function __construct(
        private readonly Config $b2bConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->b2bConfig->isEnabled();
    }

    public function isStrictB2B(): bool
    {
        return $this->b2bConfig->isEnabled() && $this->b2bConfig->isStrictB2B();
    }
}
