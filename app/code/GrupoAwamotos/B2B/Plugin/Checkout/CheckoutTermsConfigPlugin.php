<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Model\Checkout\TermsConfigProvider;
use Magento\Checkout\Model\DefaultConfigProvider;

/**
 * Expõe termos B2B no window.checkoutConfig (OPC + checkout nativo).
 */
class CheckoutTermsConfigPlugin
{
    public function __construct(
        private readonly TermsConfigProvider $termsConfigProvider
    ) {
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function afterGetConfig(DefaultConfigProvider $subject, array $result): array
    {
        unset($subject);

        return array_replace_recursive($result, $this->termsConfigProvider->getConfig());
    }
}
