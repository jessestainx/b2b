<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a RequestQuote event to Meta CAPI when a B2B quote request is submitted.
 */
class B2BQuoteRequest implements ObserverInterface
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly LoggerInterface $logger,
        private readonly B2BSignalBuilder $b2bSignalBuilder,
        private readonly UserDataBuilder $userDataBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $event = $observer->getEvent();
            $quoteRequest = $event->getData('quote_request');
            if (!$quoteRequest instanceof QuoteRequestInterface || !$quoteRequest->getRequestId()) {
                return;
            }

            $storeId = $event->getData('store_id');
            $storeId = $storeId !== null ? (int) $storeId : null;

            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $customerData = $event->getData('customer_data');
            if (!is_array($customerData)) {
                $customerData = [];
            }

            $items = $event->getData('quote_items');
            if (!is_array($items)) {
                $items = $quoteRequest->getItems();
            }

            $itemCount = 0;
            $contentIds = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku !== '') {
                    $contentIds[] = $sku;
                }

                $itemCount += (int) ($item['qty'] ?? $item['quantity'] ?? 1);
            }

            $estimatedValue = (float) ($event->getData('quote_estimated_value') ?? 0.0);
            $customerId = $event->getData('customer_id');
            $externalId = $customerId !== null
                ? (string) $customerId
                : (string) ($quoteRequest->getCustomerEmail() ?: $quoteRequest->getRequestId());

            $qrFullName = trim((string) ($customerData['name'] ?? $quoteRequest->getCustomerName() ?? ''));
            $qrNameParts = $qrFullName !== '' ? explode(' ', $qrFullName, 2) : [];
            $userData = $this->userDataBuilder->build(
                (string) ($customerData['email'] ?? $quoteRequest->getCustomerEmail()),
                (string) ($customerData['phone'] ?? $quoteRequest->getPhone()),
                $externalId,
                $qrNameParts[0] ?? null,
                $qrNameParts[1] ?? null
            );
            $eventSourceUrl = $this->userDataBuilder->getEventSourceUrl();

            $customData = array_merge($this->b2bSignalBuilder->build([
                'lead_type' => 'b2b_quote_request',
                'person_type' => !empty($customerData['cnpj']) ? 'pj' : 'pj',
                'approval_status' => 'pending',
                'register_channel' => 'b2b_quote_form'
            ]), [
                'funnel_stage' => 'request_quote',
                'quote_request_id' => $quoteRequest->getRequestId(),
                'quote_status' => $quoteRequest->getStatus(),
                'quote_item_count' => $itemCount,
                'value' => $estimatedValue,
                'currency' => 'BRL'
            ]);

            if ($contentIds !== []) {
                $customData['content_ids'] = $contentIds;
            }

            $message = trim((string) ($event->getData('quote_message') ?? $quoteRequest->getMessage()));
            if ($message !== '') {
                $customData['quote_message_present'] = true;
            }

            $capiEvent = [
                'event_name' => 'RequestQuote',
                'event_time' => time(),
                'event_id' => sprintf('request-quote-b2b-%s', (string) $quoteRequest->getRequestId()),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => $customData
            ];

            if ($eventSourceUrl !== null) {
                $capiEvent['event_source_url'] = $eventSourceUrl;
            }

            $this->capiDispatcher->sendEvents($pixelId, [$capiEvent], $storeId, 'RequestQuote');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] RequestQuote event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
