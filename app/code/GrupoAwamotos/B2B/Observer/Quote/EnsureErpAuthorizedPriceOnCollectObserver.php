<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer\Quote;

use GrupoAwamotos\B2B\Model\Quote\ErpAuthorizedPriceApplier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;

/**
 * Re-asserts authorized custom_price before Subtotal runs getFinalPrice().
 *
 * Critical for webapi_rest checkout AJAX, where GroupPricePlugin is absent.
 * Uses persisted custom_price / provider cache — no synchronous ERP on every
 * collect when the item already carries a validated price for the current list.
 *
 * Event: sales_quote_collect_totals_before (global).
 */
class EnsureErpAuthorizedPriceOnCollectObserver implements ObserverInterface
{
    public function __construct(
        private readonly ErpAuthorizedPriceApplier $priceApplier
    ) {
    }

    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getQuote();
        if (!$quote instanceof Quote) {
            return;
        }

        $this->priceApplier->ensureQuoteItems($quote);
    }
}
