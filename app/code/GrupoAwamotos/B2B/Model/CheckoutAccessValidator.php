<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
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
        private readonly LoggerInterface $logger,
        private readonly ?Config $config = null,
        private readonly ?ErpCodeResolver $erpCodeResolver = null,
        private readonly ?PurchaseHistory $purchaseHistory = null
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

            if ($this->isApprovedPendingErp($customerId, $customer)) {
                return self::STATE_PENDING_ERP;
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

    /**
     * Approved customer without ERP linkage has no price list yet (same rule as PriceVisibility).
     */
    private function isApprovedPendingErp(int $customerId, CustomerInterface $customer): bool
    {
        if ($this->config === null || $this->erpCodeResolver === null || !$this->config->hidePriceForNoErp()) {
            return false;
        }

        if ($this->erpCodeResolver->resolveForCustomerId($customerId, $customer) !== null) {
            return false;
        }

        return $this->resolveErpCodeByDocument($customer) === null;
    }

    /**
     * Fallback: resolve ERP code by CNPJ/taxvat, mirroring PriceVisibility::resolveErpCodeByDocument().
     */
    private function resolveErpCodeByDocument(CustomerInterface $customer): ?int
    {
        if ($this->purchaseHistory === null) {
            return null;
        }

        $cnpjAttr = $customer->getCustomAttribute('b2b_cnpj');
        $cnpj = ($cnpjAttr && $cnpjAttr->getValue()) ? (string) $cnpjAttr->getValue() : '';
        if ($cnpj === '') {
            $cnpj = (string) ($customer->getTaxvat() ?? '');
        }

        if ($cnpj === '') {
            return null;
        }

        try {
            $erpCode = $this->purchaseHistory->getCustomerCodeByCnpj($cnpj);
        } catch (\Exception $exception) {
            $this->logger->error('[B2B CheckoutAccessValidator] resolveErpCodeByDocument error: ' . $exception->getMessage());
            return null;
        }

        return ($erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
    }
}
