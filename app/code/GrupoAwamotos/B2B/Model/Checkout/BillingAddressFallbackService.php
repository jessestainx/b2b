<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * Preenche billing a partir do shipping quando o payload do checkout vem incompleto.
 */
class BillingAddressFallbackService
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AddressInterfaceFactory $addressFactory
    ) {
    }

    public function resolveForQuote(int $cartId, ?AddressInterface $billingAddress): ?AddressInterface
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        if ($quote->isVirtual()) {
            return $billingAddress;
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress instanceof QuoteAddress || !$this->isAddressUsable($shippingAddress)) {
            return $billingAddress;
        }

        if ($billingAddress !== null && $this->isAddressUsable($billingAddress)) {
            return $billingAddress;
        }

        return $this->createBillingFromShipping($shippingAddress);
    }

    private function isAddressUsable(AddressInterface $address): bool
    {
        $street = $address->getStreet();
        $streetLine = is_array($street) ? trim((string) ($street[0] ?? '')) : trim((string) $street);

        return trim((string) $address->getFirstname()) !== ''
            && trim((string) $address->getLastname()) !== ''
            && trim((string) $address->getCity()) !== ''
            && trim((string) $address->getPostcode()) !== ''
            && trim((string) $address->getTelephone()) !== ''
            && trim((string) $address->getCountryId()) !== ''
            && $streetLine !== '';
    }

    private function createBillingFromShipping(QuoteAddress $shippingAddress): AddressInterface
    {
        /** @var QuoteAddress $billingAddress */
        $billingAddress = $this->addressFactory->create();
        $billingAddress->setAddressType(QuoteAddress::ADDRESS_TYPE_BILLING);
        $billingAddress->setCustomerId($shippingAddress->getCustomerId());
        $billingAddress->setCustomerAddressId($shippingAddress->getCustomerAddressId());
        $billingAddress->setEmail($shippingAddress->getEmail());
        $billingAddress->setPrefix($shippingAddress->getPrefix());
        $billingAddress->setFirstname($shippingAddress->getFirstname());
        $billingAddress->setMiddlename($shippingAddress->getMiddlename());
        $billingAddress->setLastname($shippingAddress->getLastname());
        $billingAddress->setSuffix($shippingAddress->getSuffix());
        $billingAddress->setCompany($shippingAddress->getCompany());
        $billingAddress->setStreet($shippingAddress->getStreet());
        $billingAddress->setCity($shippingAddress->getCity());
        $billingAddress->setRegion($shippingAddress->getRegion());
        $billingAddress->setRegionId($shippingAddress->getRegionId());
        $billingAddress->setRegionCode($shippingAddress->getRegionCode());
        $billingAddress->setPostcode($shippingAddress->getPostcode());
        $billingAddress->setCountryId($shippingAddress->getCountryId());
        $billingAddress->setTelephone($shippingAddress->getTelephone());
        $billingAddress->setFax($shippingAddress->getFax());
        $billingAddress->setVatId($shippingAddress->getVatId());
        $billingAddress->setSameAsBilling(0);
        $billingAddress->setSaveInAddressBook((int) $shippingAddress->getSaveInAddressBook());

        return $billingAddress;
    }
}
