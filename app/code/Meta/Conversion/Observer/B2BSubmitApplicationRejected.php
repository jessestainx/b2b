<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a SubmitApplication event for rejected B2B customers.
 */
class B2BSubmitApplicationRejected implements ObserverInterface
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly LoggerInterface $logger,
        private readonly B2BSignalBuilder $b2bSignalBuilder,
        private readonly ?UserDataBuilder $userDataBuilder = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $customer = $observer->getEvent()->getData('customer');
            if (!$customer instanceof CustomerInterface || !$customer->getId()) {
                return;
            }

            $personType = strtolower((string) $this->getCustomAttributeValue($customer, 'b2b_person_type'));
            if ($personType !== 'pj') {
                return;
            }

            $storeId = $customer->getStoreId() !== null ? (int) $customer->getStoreId() : null;
            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $customerId = (string) $customer->getId();
            $phone = (string) ($this->getCustomAttributeValue($customer, 'b2b_phone') ?? '');
            $userData = $this->userDataBuilder
                ? $this->userDataBuilder->build(
                    (string) $customer->getEmail(),
                    $phone,
                    $customerId,
                    (string) ($customer->getFirstname() ?: ''),
                    (string) ($customer->getLastname() ?: '')
                )
                : [];
            $reason = trim((string) $observer->getEvent()->getData('reason'));

            $event = [
                'event_name' => 'SubmitApplication',
                'event_time' => time(),
                'event_id' => sprintf('sa-b2b-rejected-%s', $customerId),
                'action_source' => 'system_generated',
                'user_data' => $userData,
                'custom_data' => array_merge($this->b2bSignalBuilder->build([
                    'application_status' => 'rejected',
                    'approval_status' => 'rejected',
                    'register_channel' => 'b2b_register_form'
                ], $customer), [
                    'application_status' => 'rejected',
                    'reject_reason' => $reason !== '' ? mb_substr($reason, 0, 120) : 'not_informed'
                ])
            ];

            $this->capiDispatcher->sendEvents($pixelId, [$event], $storeId, 'SubmitApplicationRejected');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] SubmitApplication rejected event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getCustomAttributeValue(CustomerInterface $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        if ($attribute === null) {
            return null;
        }

        $value = trim((string) $attribute->getValue());

        return $value === '' ? null : $value;
    }
}
