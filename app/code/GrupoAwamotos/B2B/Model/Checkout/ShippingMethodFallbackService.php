<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use Magento\Framework\Exception\StateException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Address\Rate;

/**
 * Persiste frete na quote quando o frontend selecionou rate no KO mas não salvou no banco.
 */
class ShippingMethodFallbackService
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function resolveForQuote(int $cartId): void
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);

        if ($quote->isVirtual()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress instanceof QuoteAddress) {
            return;
        }

        if ($this->hasValidShippingMethod($shippingAddress)) {
            return;
        }

        $rate = $this->pickShippingRate($shippingAddress);
        if ($rate === null) {
            return;
        }

        $methodCode = $rate->getCarrier() . '_' . $rate->getMethod();
        $shippingAddress->setShippingMethod($methodCode);
        $shippingAddress->setShippingDescription((string) $rate->getMethodTitle());
        $shippingAddress->setLimitCarrier($rate->getCarrier());

        $quote->setShippingAddress($shippingAddress);
        try {
            $this->cartRepository->save($quote);
        } catch (StateException) {
            return;
        }
    }

    private function hasValidShippingMethod(QuoteAddress $shippingAddress): bool
    {
        $method = (string) $shippingAddress->getShippingMethod();

        return $method !== '' && $shippingAddress->getShippingRateByCode($method) instanceof Rate;
    }

    private function pickShippingRate(QuoteAddress $shippingAddress): ?Rate
    {
        $rates = $shippingAddress->getAllShippingRates();
        if ($rates) {
            foreach ($rates as $rate) {
                if ($rate instanceof Rate && $rate->getCarrier() && $rate->getMethod()) {
                    return $rate;
                }
            }
        }

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $rates = $shippingAddress->getAllShippingRates();

        foreach ($rates as $rate) {
            if ($rate instanceof Rate && $rate->getCarrier() && $rate->getMethod()) {
                return $rate;
            }
        }

        return null;
    }
}
