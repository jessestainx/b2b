<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a Lead event to Meta CAPI when a B2B visitor starts filling the registration form.
 *
 * Triggered by the `grupoawamotos_b2b_lead_initiated` event dispatched from
 * GrupoAwamotos\B2B\Controller\Ajax\TrackLead (AJAX endpoint called from register-form.js).
 */
class B2BLead implements ObserverInterface
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

            $storeId = null;
            $rawStoreId = $event->getData('store_id');
            if ($rawStoreId !== null) {
                $storeId = (int) $rawStoreId ?: null;
            }

            if (!$this->config->isActive($storeId)) { // phpcs:ignore Squiz.Operators.ComparisonOperatorUsage
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $eventId = (string) ($event->getData('event_id') ?? '');
            $funnelStage = (string) ($event->getData('funnel_stage') ?? 'start');
            $registerChannel = (string) ($event->getData('register_channel') ?? 'b2b_register_form');

            // No customer yet (anonymous), build user_data from request context only.
            $userData = $this->userDataBuilder->build();
            $eventSourceUrl = $this->userDataBuilder->getEventSourceUrl();

            $uniqueId = $eventId !== '' ? $eventId : sprintf('lead-b2b-%d', time());

            $capiEvent = [
                'event_name' => 'Lead',
                'event_time' => time(),
                'event_id' => $uniqueId,
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => array_merge($this->b2bSignalBuilder->build([
                    'funnel_stage' => $funnelStage,
                    'register_channel' => $registerChannel
                ]), [
                    'funnel_stage' => $funnelStage
                ])
            ];

            if ($eventSourceUrl !== null) {
                $capiEvent['event_source_url'] = $eventSourceUrl;
            }

            $this->capiDispatcher->sendEvents($pixelId, [$capiEvent], $storeId, 'Lead');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] Lead event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
