<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;

class CheckoutAccessValidator
{
    public const STATE_APPROVED = 'approved';
    public const STATE_PENDING = ApprovalStatus::STATUS_PENDING;
    public const STATE_REJECTED = ApprovalStatus::STATUS_REJECTED;
    public const STATE_SUSPENDED = ApprovalStatus::STATUS_SUSPENDED;
    public const STATE_PENDING_ERP = 'pending_erp';

    /** @var array<int, \Magento\Customer\Api\Data\CustomerInterface> */
    private array $customerCache = [];

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getCustomer(int $customerId): \Magento\Customer\Api\Data\CustomerInterface
    {
        if (!isset($this->customerCache[$customerId])) {
            $this->customerCache[$customerId] = $this->customerRepository->getById($customerId);
        }

        return $this->customerCache[$customerId];
    }

    public function resolveCustomerState(int $customerId): string
    {
        if ($customerId <= 0) {
            return self::STATE_APPROVED;
        }

        try {
            $customer = $this->getCustomer($customerId);
            $approvalStatusAttr = $customer->getCustomAttribute('b2b_approval_status');
            $approvalStatus = $approvalStatusAttr ? (string) $approvalStatusAttr->getValue() : '';

            if ($approvalStatus !== '' && $approvalStatus !== ApprovalStatus::STATUS_APPROVED) {
                return $approvalStatus;
            }

            return self::STATE_APPROVED;
        } catch (\Exception $exception) {
            $this->logger->error('[B2B CheckoutAccessValidator] resolveCustomerState error: ' . $exception->getMessage(), [
                'customer_id' => $customerId,
                'exception' => $exception,
            ]);

            return self::STATE_PENDING;
        }
    }
}
