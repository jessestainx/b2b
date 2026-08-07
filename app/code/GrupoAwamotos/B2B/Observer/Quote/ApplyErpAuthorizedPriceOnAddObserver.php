<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer\Quote;

use GrupoAwamotos\B2B\Model\Quote\ErpAuthorizedPriceApplier;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;

/**
 * Stamps Sectra-authorized custom_price when products are added to the quote.
 *
 * Event: sales_quote_product_add_after (global — covers frontend and REST).
 */
class ApplyErpAuthorizedPriceOnAddObserver implements ObserverInterface
{
    public function __construct(
        private readonly ErpAuthorizedPriceApplier $priceApplier
    ) {
    }

    public function execute(Observer $observer): void
    {
        $items = $observer->getEvent()->getItems();
        if (!is_array($items) || $items === []) {
            return;
        }

        foreach ($items as $item) {
            if (!$item instanceof Item || $item->getParentItem()) {
                continue;
            }

            $quote = $item->getQuote();
            if ($quote === null) {
                continue;
            }

            $this->priceApplier->applyToItem($quote, $item, true);
        }
    }
}
