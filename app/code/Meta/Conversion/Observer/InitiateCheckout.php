<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Observer to send InitiateCheckout events via Conversions API
 */
class InitiateCheckout implements ObserverInterface
{
    private const SESSION_KEY_LAST_SIGNATURE = 'meta_last_initiate_checkout_signature';

    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly \Magento\Checkout\Model\Session $checkoutSession,
        private readonly LoggerInterface $logger,
        private readonly ?UserDataBuilder $userDataBuilder = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote || !$quote->getId()) {
                return;
            }

            $storeId = $quote->getStoreId() !== null ? (int) $quote->getStoreId() : null;
            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $contentIds = [];
            $contents = [];
            $numItems = 0;

            foreach ($quote->getAllVisibleItems() as $item) {
                $contentIds[] = $item->getSku();
                $contents[] = [
                    'id' => $item->getSku(),
                    'quantity' => (int) $item->getQty()
                ];
                $numItems += (int) $item->getQty();
            }

            if ($numItems <= 0 || $contents === []) {
                return;
            }

            $currency = (string) ($quote->getQuoteCurrencyCode() ?: 'BRL');
            $quoteSignature = $this->buildQuoteSignature($quote, $contents, $currency);
            $lastSignature = (string) ($this->checkoutSession->getData(self::SESSION_KEY_LAST_SIGNATURE) ?: '');
            if ($quoteSignature !== '' && hash_equals($lastSignature, $quoteSignature)) {
                return;
            }

            $eventTime = time();
            $eventId = sprintf('ic-%s-%d', (string) $quote->getId(), $eventTime);
            $externalId = (string) ($quote->getCustomerId() ?: $quote->getId());

            $address    = $quote->getShippingAddress() ?: $quote->getBillingAddress();
            $telephone  = (string) ($address?->getTelephone() ?: '');
            $firstName  = (string) ($quote->getCustomerFirstname() ?: $address?->getFirstname() ?: '');
            $lastName   = (string) ($quote->getCustomerLastname() ?: $address?->getLastname() ?: '');
            $city       = (string) ($address?->getCity() ?: '');
            $state      = (string) ($address?->getRegionCode() ?: $address?->getRegion() ?: '');
            $zip        = (string) ($address?->getPostcode() ?: '');
            $country    = strtolower((string) ($address?->getCountryId() ?: 'br'));

            $userData = $this->userDataBuilder
                ? $this->userDataBuilder->build(
                    (string) ($quote->getCustomerEmail() ?: ''),
                    $telephone,
                    $externalId,
                    $firstName,
                    $lastName,
                    $city,
                    $state,
                    $zip,
                    $country
                )
                : [];
            if ($this->userDataBuilder && !$this->userDataBuilder->hasMinimumSignals($userData)) {
                return;
            }
            $eventSourceUrl = $this->userDataBuilder?->getEventSourceUrl();

            $event = [
                'event_name' => 'InitiateCheckout',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => [
                    'content_ids' => $contentIds,
                    'content_type' => 'product',
                    'contents' => $contents,
                    'num_items' => $numItems,
                    'value' => (float) $quote->getGrandTotal(),
                    'currency' => $currency
                ]
            ];
            if ($eventSourceUrl !== null) {
                $event['event_source_url'] = $eventSourceUrl;
            }

            $eventData = [$event];

            if ($quoteSignature !== '') {
                $this->checkoutSession->setData(self::SESSION_KEY_LAST_SIGNATURE, $quoteSignature);
            }

            $this->capiDispatcher->sendEvents($pixelId, $eventData, $storeId, 'InitiateCheckout');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] InitiateCheckout event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param array<int, array{id:string, quantity:int}> $contents
     */
    private function buildQuoteSignature(Quote $quote, array $contents, string $currency): string
    {
        $lines = [];
        foreach ($contents as $content) {
            $id = trim((string) ($content['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $qty = max(1, (int) ($content['quantity'] ?? 1));
            $lines[] = $id . ':' . $qty;
        }

        sort($lines, SORT_STRING);

        return hash('sha256', implode('|', [
            (string) $quote->getId(),
            number_format((float) $quote->getGrandTotal(), 4, '.', ''),
            strtoupper($currency),
            implode(',', $lines)
        ]));
    }
}
