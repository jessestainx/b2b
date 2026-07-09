<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Model\Checkout\BillingAddressFallbackService;
use GrupoAwamotos\B2B\Model\Checkout\ShippingAddressFallbackService;
use GrupoAwamotos\B2B\Model\Checkout\ShippingMethodFallbackService;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class EnsureBillingAddressGuestPlugin
{
    public function __construct(
        private readonly BillingAddressFallbackService $billingAddressFallback,
        private readonly ShippingMethodFallbackService $shippingMethodFallback,
        private readonly ShippingAddressFallbackService $shippingAddressFallback
    ) {
    }

    private function prepareQuote(int $cartId, ?AddressInterface $billingAddress): ?AddressInterface
    {
        $this->shippingAddressFallback->resolveForQuote($cartId);
        $this->shippingMethodFallback->resolveForQuote($cartId);

        return $this->billingAddressFallback->resolveForQuote($cartId, $billingAddress);
    }

    /**
     * @return array<int, mixed>
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        return [
            $cartId,
            $email,
            $paymentMethod,
            $this->prepareQuote((int) $cartId, $billingAddress),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function beforeSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        return [
            $cartId,
            $email,
            $paymentMethod,
            $this->prepareQuote((int) $cartId, $billingAddress),
        ];
    }
}
