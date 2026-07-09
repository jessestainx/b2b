<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Model\Checkout\ShippingAddressFallbackService;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;

class EnsureShippingAddressPlugin
{
    public function __construct(
        private readonly ShippingAddressFallbackService $shippingAddressFallback
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $this->shippingAddressFallback->resolveForQuote((int) $cartId);

        return [$cartId, $addressInformation];
    }
}
