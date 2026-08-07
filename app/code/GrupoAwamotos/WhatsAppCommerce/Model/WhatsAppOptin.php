<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\OptinInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

/**
 * Manages WhatsApp opt-in/opt-out via phone number lookup.
 * Called by Typebot when customer says SIM/SAIR.
 */
class WhatsAppOptin implements OptinInterface
{
    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param ResourceConnection $resourceConnection
     * @param RemoteAddress $remoteAddress
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly FilterBuilder $filterBuilder,
        private readonly ResourceConnection $resourceConnection,
        private readonly RemoteAddress $remoteAddress,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function setOptin(string $phone, int $optin, ?string $source = null): array
    {
        $phone = $this->normalizePhone($phone);
        $optinValue = $optin === 1 ? '1' : '0';
        $source = $source ?? 'whatsapp';

        $customerId = $this->findCustomerByPhone($phone);

        if ($customerId === null) {
            return [
                'success' => false,
                'message' => 'Customer not found for phone: ' . $phone,
            ];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('whatsapp_optin', $optinValue);
            $this->customerRepository->save($customer);

            $this->logConsent($customerId, $phone, (int) $optinValue, $source);

            $this->logger->info('WhatsApp opt-in updated via API', [
                'customer_id' => $customerId,
                'optin' => $optinValue,
                'source' => $source,
            ]);

            return [
                'success' => true,
                'customer_id' => $customerId,
                'optin' => (int) $optinValue,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to set WhatsApp opt-in via API', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update opt-in: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function getOptin(string $phone): array
    {
        $phone = $this->normalizePhone($phone);
        $customerId = $this->findCustomerByPhone($phone);

        if ($customerId === null) {
            return [
                'found' => false,
                'optin' => 0,
            ];
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $attr = $customer->getCustomAttribute('whatsapp_optin');
            $optin = $attr ? (int) $attr->getValue() : 0;

            return [
                'found' => true,
                'customer_id' => $customerId,
                'optin' => $optin,
                'name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            ];
        } catch (\Exception $e) {
            return [
                'found' => false,
                'optin' => 0,
            ];
        }
    }

    /**
     * Find customer ID by phone number (billing address or telephone attribute).
     *
     * Uses same REGEXP_REPLACE approach as AttendantInterface for Brazilian phones.
     *
     * @param string $phone
     * @return int|null
     */
    private function findCustomerByPhone(string $phone): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 10) {
            return null;
        }

        // Strip country code 55 if present
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        $sql = $connection->select()
            ->from(
                ['addr' => $connection->getTableName('customer_address_entity')],
                ['parent_id']
            )
            ->where(
                "REGEXP_REPLACE(addr.telephone, '[^0-9]', '') LIKE ?",
                '%' . $digits
            )
            ->limit(1);

        $customerId = $connection->fetchOne($sql);

        return $customerId ? (int) $customerId : null;
    }

    /**
     * Normaliza telefone mantendo apenas dígitos.
     *
     * @param string $phone
     * @return string
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    /**
     * Persiste trilha de auditoria da mudança de consentimento.
     *
     * @param int $customerId
     * @param string $phone
     * @param int $optin
     * @param string $source
     * @return void
     */
    private function logConsent(int $customerId, string $phone, int $optin, string $source): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');

            $connection->insert($tableName, [
                'customer_id' => $customerId,
                'phone' => $phone,
                'optin' => $optin,
                'source' => $source,
                'ip_address' => $this->remoteAddress->getRemoteAddress() ?? '0.0.0.0',
                'user_agent' => 'WhatsApp Bot API',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log consent via API: ' . $e->getMessage());
        }
    }
}
