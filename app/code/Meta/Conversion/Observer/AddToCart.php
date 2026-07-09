<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Observer to send AddToCart events via Conversions API
 */
class AddToCart implements ObserverInterface
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly LoggerInterface $logger,
        private readonly ?UserDataBuilder $userDataBuilder = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getEvent()->getData('product');
            /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
            $quoteItem = $observer->getEvent()->getData('quote_item');
            if (!$product || !$quoteItem) {
                return;
            }

            $eventQuoteItem = $quoteItem->getParentItem() ?: $quoteItem;
            $quote = $eventQuoteItem->getQuote();
            $eventProduct = $eventQuoteItem->getProduct() ?: $product;

            $storeId = $quote?->getStoreId();
            if ($storeId === null) {
                $storeId = $eventProduct->getStoreId() !== null ? (int) $eventProduct->getStoreId() : null;
            }
            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $currency = (string) (($quote?->getQuoteCurrencyCode()) ?: ($quote?->getBaseCurrencyCode()) ?: 'BRL');
            $sku = trim((string) ($eventQuoteItem->getSku() ?: $eventProduct->getSku()));
            if ($sku === '') {
                return;
            }

            $itemName = trim((string) ($eventQuoteItem->getName() ?: $eventProduct->getName()));
            $qty = max(1, (int) ceil((float) $eventQuoteItem->getQty()));
            $value = (float) ($eventQuoteItem->getCalculationPrice() ?: $eventQuoteItem->getPrice());
            if ($value <= 0 && $qty > 0) {
                $rowTotal = (float) $eventQuoteItem->getRowTotal();
                if ($rowTotal > 0) {
                    $value = round($rowTotal / $qty, 2);
                }
            }
            $eventTime = time();
            $quoteReference = (string) ($quote?->getId() ?: 'guest');
            $quoteItemReference = (string) ($eventQuoteItem->getId() ?: 'new');
            $eventId = sprintf('atc-%s-%s-%s-%d', $quoteReference, $quoteItemReference, $sku, $qty);
            $externalId  = (string) ($quote?->getCustomerId() ?: ($quote?->getId() ?: ''));
            $atcAddress  = $quote?->getBillingAddress() ?: $quote?->getShippingAddress();
            $userData    = $this->userDataBuilder
                ? $this->userDataBuilder->build(
                    (string) ($quote?->getCustomerEmail() ?: ''),
                    (string) ($atcAddress?->getTelephone() ?: ''),
                    $externalId,
                    (string) ($quote?->getCustomerFirstname() ?: $atcAddress?->getFirstname() ?: ''),
                    (string) ($quote?->getCustomerLastname() ?: $atcAddress?->getLastname() ?: ''),
                    (string) ($atcAddress?->getCity() ?: ''),
                    (string) ($atcAddress?->getRegionCode() ?: $atcAddress?->getRegion() ?: ''),
                    (string) ($atcAddress?->getPostcode() ?: ''),
                    strtolower((string) ($atcAddress?->getCountryId() ?: 'br'))
                )
                : [];
            if ($this->userDataBuilder && !$this->userDataBuilder->hasMinimumSignals($userData)) {
                return;
            }
            $eventSourceUrl = $this->userDataBuilder?->getEventSourceUrl();

            $event = [
                'event_name' => 'AddToCart',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => [
                    'content_ids' => [$sku],
                    'content_name' => $itemName,
                    'content_type' => 'product',
                    'value' => $value,
                    'currency' => $currency,
                    'contents' => [
                        [
                            'id' => $sku,
                            'quantity' => $qty
                        ]
                    ]
                ]
            ];
            if ($eventSourceUrl !== null) {
                $event['event_source_url'] = $eventSourceUrl;
            }

            $eventData = [$event];

            $this->capiDispatcher->sendEvents($pixelId, $eventData, $storeId, 'AddToCart');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] AddToCart event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
