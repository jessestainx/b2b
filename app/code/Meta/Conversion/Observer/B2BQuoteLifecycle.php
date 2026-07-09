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

class B2BQuoteLifecycle implements ObserverInterface
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

            $lifecycleEvent = trim((string) $event->getData('lifecycle_event'));
            $eventConfig = $this->resolveEventConfig($lifecycleEvent);
            if ($eventConfig === null) {
                return;
            }

            $storeIdData = $event->getData('store_id');
            $storeId = $storeIdData !== null ? (int) $storeIdData : null;

            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $items = $event->getData('quote_items');
            if (!is_array($items)) {
                $items = $quoteRequest->getItems();
            }

            [$contentIds, $itemCount] = $this->extractContentIdsAndCount($items);

            $customerData = $this->extractCustomerData($observer, $quoteRequest);
            $customerId = $event->getData('customer_id');
            $externalId = $customerId !== null
                ? (string) $customerId
                : (string) ($quoteRequest->getCustomerEmail() ?: $quoteRequest->getRequestId());

            $lcFullName = trim((string) ($customerData['name'] ?? $quoteRequest->getCustomerName() ?? ''));
            $lcNameParts = $lcFullName !== '' ? explode(' ', $lcFullName, 2) : [];
            $userData = $this->userDataBuilder->build(
                (string) ($customerData['email'] ?? $quoteRequest->getCustomerEmail()),
                (string) ($customerData['phone'] ?? $quoteRequest->getPhone()),
                $externalId,
                $lcNameParts[0] ?? null,
                $lcNameParts[1] ?? null
            );
            $eventSourceUrl = $this->userDataBuilder->getEventSourceUrl();

            $quotedTotal = (float) ($event->getData('quoted_total') ?? $quoteRequest->getQuotedTotal() ?? 0.0);
            $customData = array_merge($this->b2bSignalBuilder->build([
                'lead_type' => $eventConfig['lead_type'],
                'person_type' => $this->resolvePersonType($customerData, $quoteRequest),
                'approval_status' => $eventConfig['approval_status'],
                'register_channel' => 'b2b_quote_form'
            ]), [
                'funnel_stage' => $eventConfig['funnel_stage'],
                'quote_lifecycle_event' => $lifecycleEvent,
                'quote_request_id' => $quoteRequest->getRequestId(),
                'quote_status' => $quoteRequest->getStatus(),
                'quote_item_count' => $itemCount,
                'quote_response_outcome' => $eventConfig['response_outcome'],
                'value' => $quotedTotal,
                'currency' => 'BRL'
            ]);

            $previousStatus = trim((string) $event->getData('previous_status'));
            if ($previousStatus !== '') {
                $customData['previous_quote_status'] = $previousStatus;
            }

            if ($contentIds !== []) {
                $customData['content_ids'] = $contentIds;
            }

            $expiresAt = $quoteRequest->getExpiresAt();
            if (is_string($expiresAt) && $expiresAt !== '') {
                $customData['quote_expires_at'] = $expiresAt;
            }

            $adminNotes = trim((string) ($quoteRequest->getAdminNotes() ?? ''));
            if ($adminNotes !== '') {
                $customData['quote_admin_notes_present'] = true;
            }

            $quoteMessage = trim((string) ($quoteRequest->getMessage() ?? ''));
            if ($quoteMessage !== '') {
                $customData['quote_message_present'] = true;
            }

            $capiEvent = [
                'event_name' => $eventConfig['meta_event_name'],
                'event_time' => time(),
                'event_id' => sprintf('%s-%s', $eventConfig['event_id_prefix'], (string) $quoteRequest->getRequestId()),
                'action_source' => $eventConfig['action_source'],
                'user_data' => $userData,
                'custom_data' => $customData
            ];

            if ($eventSourceUrl !== null) {
                $capiEvent['event_source_url'] = $eventSourceUrl;
            }

            $this->capiDispatcher->sendEvents($pixelId, [$capiEvent], $storeId, 'QuoteLifecycle');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] Quote lifecycle event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param mixed $items
     * @return array{0: array<int, string>, 1: int}
     */
    private function extractContentIdsAndCount(mixed $items): array
    {
        if (!is_array($items)) {
            return [[], 0];
        }

        $contentIds = [];
        $itemCount = 0;

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

        return [array_values(array_unique($contentIds)), $itemCount];
    }

    /**
     * @return array<string, string>
     */
    private function extractCustomerData(Observer $observer, QuoteRequestInterface $quoteRequest): array
    {
        $customerData = $observer->getEvent()->getData('customer_data');
        if (is_array($customerData)) {
            return $customerData;
        }

        return [
            'email' => $quoteRequest->getCustomerEmail(),
            'name' => $quoteRequest->getCustomerName(),
            'company_name' => (string) ($quoteRequest->getCompanyName() ?? ''),
            'cnpj' => (string) ($quoteRequest->getCnpj() ?? ''),
            'phone' => (string) ($quoteRequest->getPhone() ?? '')
        ];
    }

    /**
     * @param array<string, string> $customerData
     */
    private function resolvePersonType(array $customerData, QuoteRequestInterface $quoteRequest): string
    {
        $document = preg_replace('/\D+/', '', (string) ($customerData['cnpj'] ?? $quoteRequest->getCnpj() ?? ''));
        if (is_string($document) && strlen($document) === 11) {
            return 'pf';
        }

        return 'pj';
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveEventConfig(string $lifecycleEvent): ?array
    {
        return match ($lifecycleEvent) {
            'quoted' => [
                'meta_event_name' => 'QuoteResponded',
                'event_id_prefix' => 'quote-responded',
                'funnel_stage' => 'quote_responded',
                'lead_type' => 'b2b_quote_response',
                'approval_status' => 'pending',
                'action_source' => 'system_generated',
                'response_outcome' => 'quoted'
            ],
            'merchant_rejected' => [
                'meta_event_name' => 'QuoteRejectedByMerchant',
                'event_id_prefix' => 'quote-rejected-merchant',
                'funnel_stage' => 'quote_rejected',
                'lead_type' => 'b2b_quote_rejected',
                'approval_status' => 'rejected',
                'action_source' => 'system_generated',
                'response_outcome' => 'merchant_rejected'
            ],
            'accepted' => [
                'meta_event_name' => 'QuoteAccepted',
                'event_id_prefix' => 'quote-accepted',
                'funnel_stage' => 'quote_accepted',
                'lead_type' => 'b2b_quote_accepted',
                'approval_status' => 'approved',
                'action_source' => 'website',
                'response_outcome' => 'accepted'
            ],
            'customer_rejected' => [
                'meta_event_name' => 'QuoteRejectedByCustomer',
                'event_id_prefix' => 'quote-rejected-customer',
                'funnel_stage' => 'quote_rejected',
                'lead_type' => 'b2b_quote_rejected',
                'approval_status' => 'approved',
                'action_source' => 'website',
                'response_outcome' => 'customer_rejected'
            ],
            default => null,
        };
    }
}
