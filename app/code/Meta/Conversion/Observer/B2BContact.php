<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a Contact event to Meta CAPI when a B2B visitor clicks a contact CTA (WhatsApp/email/phone).
 */
class B2BContact implements ObserverInterface
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

            $contactAction = (string) ($event->getData('contact_action') ?? '');
            if ($contactAction === '') {
                return;
            }

            $storeId = null;

            /** @var Customer|null $customer */
            $customer = $event->getData('customer');
            if ($customer instanceof Customer && $customer->getId()) {
                $storeId = (int) $customer->getData('store_id') ?: null;
            }

            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $contactChannel = (string) ($event->getData('contact_channel') ?? 'unknown');
            $funnelStage = (string) ($event->getData('funnel_stage') ?? 'consideration');
            $touchpoint = (string) ($event->getData('touchpoint') ?? 'b2b_contact');
            $eventId = (string) ($event->getData('event_id') ?? '');

            // Build user data from request context (IP, UA, FBP/FBC cookies).
            // Customer email/phone are added only when a logged-in customer is present.
            $email = null;
            $phone = null;
            $externalId = null;

            $contactFn = null;
            $contactLn = null;
            if ($customer instanceof Customer && $customer->getId()) {
                $email = $customer->getData('email') ?: null;
                $externalId = (string) $customer->getId();
                $contactFn = ($customer->getFirstname() ?: null);
                $contactLn = ($customer->getLastname() ?: null);
            }

            $userData = $this->userDataBuilder->build($email, $phone, $externalId, $contactFn, $contactLn);
            $eventSourceUrl = $this->userDataBuilder->getEventSourceUrl();

            $uniqueId = $eventId !== '' ? $eventId : sprintf('contact-b2b-%s-%d', $contactAction, time());

            $capiEvent = [
                'event_name' => 'Contact',
                'event_time' => time(),
                'event_id' => $uniqueId,
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => array_merge($this->b2bSignalBuilder->build([
                    'funnel_stage' => $funnelStage,
                    'register_channel' => 'b2b_contact'
                ], $customer), [
                    'contact_action' => $contactAction,
                    'contact_channel' => $contactChannel,
                    'funnel_stage' => $funnelStage,
                    'touchpoint' => $touchpoint
                ])
            ];

            if ($eventSourceUrl !== null) {
                $capiEvent['event_source_url'] = $eventSourceUrl;
            }

            $this->capiDispatcher->sendEvents($pixelId, [$capiEvent], $storeId, 'Contact');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] Contact event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
