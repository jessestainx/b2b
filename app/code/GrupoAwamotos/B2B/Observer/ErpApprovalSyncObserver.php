<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\ErpIntegration;
use GrupoAwamotos\B2B\Model\CreditService;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer to sync approved B2B customer to ERP
 */
class ErpApprovalSyncObserver implements ObserverInterface
{
    private ErpIntegration $erpIntegration;
    private B2BHelper $b2bHelper;
    private CustomerRepositoryInterface $customerRepository;
    private B2BClientRegistration $b2bClientRegistration;
    private LoggerInterface $logger;
    private ?CreditService $creditService;

    public function __construct(
        ErpIntegration $erpIntegration,
        B2BHelper $b2bHelper,
        CustomerRepositoryInterface $customerRepository,
        B2BClientRegistration $b2bClientRegistration,
        LoggerInterface $logger,
        ?CreditService $creditService = null
    ) {
        $this->erpIntegration = $erpIntegration;
        $this->b2bHelper = $b2bHelper;
        $this->customerRepository = $customerRepository;
        $this->b2bClientRegistration = $b2bClientRegistration;
        $this->logger = $logger;
        $this->creditService = $creditService;
    }

    /**
     * Sync approved B2B customer to ERP
     */
    public function execute(Observer $observer): void
    {
        $customerId = $observer->getEvent()->getCustomerId();
        $newGroupId = $observer->getEvent()->getNewGroupId();

        if (!$customerId) {
            $this->logger->warning('ErpApprovalSyncObserver: No customer ID provided');
            return;
        }

        $this->logger->info(sprintf(
            'ErpApprovalSyncObserver: Processing approval for customer %d to group %s',
            $customerId,
            $newGroupId ?? 'unknown'
        ));

        try {
            $customer = $this->customerRepository->getById($customerId);

            $cnpj = $this->getCustomerCnpj($customer);
            if (empty($cnpj)) {
                $this->logger->debug('ErpApprovalSyncObserver: Customer has no CNPJ, skipping ERP sync');
                return;
            }

            $erpCode = $this->erpIntegration->getErpCodeForCustomer((int) $customerId);

            if ($erpCode) {
                $this->logger->info(sprintf(
                    'ErpApprovalSyncObserver: Customer already linked to ERP code %s',
                    $erpCode
                ));
                $this->updateErpCustomer($customer, (string) $erpCode, $newGroupId);
            } else {
                $this->logger->info('ErpApprovalSyncObserver: Creating new customer in ERP');
                $syncResult = $this->erpIntegration->syncApprovedCustomerToErp((int) $customerId);

                if ($syncResult['success'] && !empty($syncResult['erp_code'])) {
                    $this->updateErpCustomer($customer, (string) $syncResult['erp_code'], $newGroupId);
                }
            }

            $this->syncCreditLimit($customer);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'ErpApprovalSyncObserver: Error syncing customer %d to ERP - %s',
                $customerId,
                $e->getMessage()
            ));
            // Don't fail approval if ERP sync fails
        }
    }

    /**
     * Get customer CNPJ from custom attribute
     */
    private function getCustomerCnpj($customer): ?string
    {
        $cnpjAttribute = $customer->getCustomAttribute('cnpj');
        if ($cnpjAttribute) {
            return $cnpjAttribute->getValue();
        }

        // Try taxvat as fallback
        $taxvat = $customer->getTaxvat();
        if ($taxvat && strlen(preg_replace('/\D/', '', $taxvat)) === 14) {
            return $taxvat;
        }

        return null;
    }

    /**
     * Ensure approved customer is registered in Sectra GR_INTEGRACAOVALIDADOR.
     *
     * Without this entry Sectra rejects orders with "Cliente nao foi encontrado".
     * Tries auto-registration via write connection; falls back to logging the
     * INSERT SQL for manual execution.
     */
    private function updateErpCustomer($customer, string $erpCode, ?int $newGroupId): void
    {
        $erpCodeInt = (int) $erpCode;
        if ($erpCodeInt <= 0) {
            $this->logger->warning(sprintf(
                'ErpApprovalSyncObserver: Invalid ERP code "%s" for customer %s — skipping validator registration',
                $erpCode,
                $customer->getId()
            ));
            return;
        }

        $erpCustomerType = $this->mapGroupToErpType($newGroupId);

        $this->logger->info(sprintf(
            'ErpApprovalSyncObserver: Ensuring ERP customer %s (type %s) is in GR_INTEGRACAOVALIDADOR',
            $erpCode,
            $erpCustomerType
        ));

        // Already registered — nothing to do
        if ($this->b2bClientRegistration->isClientRegistered($erpCodeInt)) {
            $this->logger->info(sprintf(
                'ErpApprovalSyncObserver: ERP customer %s already in GR_INTEGRACAOVALIDADOR',
                $erpCode
            ));
            return;
        }

        // Try auto-registration via write connection
        if ($this->b2bClientRegistration->hasWriteAccess()) {
            $registered = $this->b2bClientRegistration->registerClient($erpCodeInt);
            if ($registered) {
                $this->logger->info(sprintf(
                    'ErpApprovalSyncObserver: ERP customer %s registered in GR_INTEGRACAOVALIDADOR',
                    $erpCode
                ));
                return;
            }
        }

        // Write access unavailable — log SQL for manual execution
        $sql = $this->b2bClientRegistration->generateRegistrationSQL([$erpCodeInt]);
        $this->logger->warning(sprintf(
            'ErpApprovalSyncObserver: ERP customer %s NOT in GR_INTEGRACAOVALIDADOR and write access unavailable. '
            . 'Orders will be held until manually registered. SQL for Sectra DBA:%s%s',
            $erpCode,
            PHP_EOL,
            $sql
        ));
    }

    /**
     * Map Magento customer group to ERP customer type
     */
    private function mapGroupToErpType(?int $groupId): string
    {
        $groupMapping = [
            $this->b2bHelper->getGroupIdByCode('b2b_atacado')    => 'ATACADO',
            $this->b2bHelper->getGroupIdByCode('b2b_vip')        => 'VIP',
            $this->b2bHelper->getGroupIdByCode('b2b_revendedor') => 'REVENDEDOR',
        ];

        return $groupMapping[$groupId] ?? 'ATACADO';
    }

    /**
     * Sync credit limit from ERP to customer
     */
    private function syncCreditLimit($customer): void
    {
        try {
            $customerId = (int) $customer->getId();
            $erpCode = $this->erpIntegration->getErpCodeForCustomer($customerId);

            if (!$erpCode) {
                return;
            }

            $creditLimit = $this->erpIntegration->getCreditLimitFromErp((string) $erpCode);

            if ($creditLimit !== null && $creditLimit > 0) {
                if ($this->creditService !== null) {
                    $this->creditService->setLimit(
                        $customerId,
                        (float) $creditLimit,
                        null,
                        'Sincronizado do ERP no observer de aprovação.'
                    );
                } else {
                    $customer->setCustomAttribute('credit_limit', $creditLimit);
                    $this->customerRepository->save($customer);
                }

                $this->logger->info(sprintf(
                    'ErpApprovalSyncObserver: Set credit limit %.2f for customer %d',
                    $creditLimit,
                    $customerId
                ));
            }
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                'ErpApprovalSyncObserver: Failed to sync credit limit - %s',
                $e->getMessage()
            ));
        }
    }
}
