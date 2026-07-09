<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Account;

use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Model\Session as CustomerSession;

class ErpCustomerContext
{
    private ?int $erpCustomerCode = null;
    private bool $resolved = false;

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly SyncLogResource $syncLogResource,
        private readonly PurchaseHistory $purchaseHistory,
        private readonly ErpHelper $erpHelper
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->erpHelper->isEnabled();
    }

    public function isLoggedIn(): bool
    {
        $this->customerSession->start();
        return $this->customerSession->isLoggedIn();
    }

    public function getErpCustomerCode(): ?int
    {
        if ($this->resolved) {
            return $this->erpCustomerCode;
        }
        $this->resolved = true;

        if (!$this->isLoggedIn()) {
            return null;
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return null;
        }

        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
        if ($erpCode !== null) {
            $this->erpCustomerCode = (int) $erpCode;
            return $this->erpCustomerCode;
        }

        $customer = $this->customerSession->getCustomer();
        $cnpj = (string) ($customer->getData('b2b_cnpj') ?: $customer->getTaxvat() ?: '');
        if ($cnpj !== '') {
            $this->erpCustomerCode = $this->purchaseHistory->getCustomerCodeByCnpj($cnpj);
        }

        return $this->erpCustomerCode;
    }
}
