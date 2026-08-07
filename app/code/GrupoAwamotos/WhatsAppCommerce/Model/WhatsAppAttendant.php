<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\AttendantInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Resolves the assigned attendant for a customer by phone number or customer ID.
 *
 * Lookup chain:
 * 1. (getByPhone only) Find customer by phone (billing address)
 * 2. Check grupoawamotos_b2b_customer_attendant mapping
 * 3. If not mapped, check ERP VENDPREF → match to erp_seller_code
 * 4. If no match, return round-robin fallback
 */
class WhatsAppAttendant implements AttendantInterface
{
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getByPhone(string $phone): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) > 11) {
            $phone = substr($phone, -11);
        }

        if (strlen($phone) < 10) {
            return ['found' => false, 'message' => 'Telefone inválido', 'source' => 'invalid_phone'];
        }

        $connection = $this->resource->getConnection();

        $customerId = $this->findCustomerByPhone($connection, $phone);

        if (!$customerId) {
            // No customer found — return default attendant (round-robin)
            $attendant = $this->getDefaultAttendant($connection);
            if ($attendant) {
                $attendant['message'] = 'Cliente não encontrado — atendente padrão';
                return $attendant;
            }
            return ['found' => false, 'message' => 'Nenhum atendente disponível', 'source' => 'no_attendant'];
        }

        return $this->getByCustomerId($customerId);
    }

    /**
     * @inheritDoc
     */
    public function getByCustomerId(int $customerId): array
    {
        if ($customerId <= 0) {
            return ['found' => false, 'message' => 'Customer ID inválido', 'source' => 'invalid_customer'];
        }

        $connection = $this->resource->getConnection();

        // 1. Check direct assignment in customer_attendant table
        $attendant = $this->getAssignedAttendant($connection, $customerId);
        if ($attendant) {
            $attendant['message'] = 'Atendente designada do cliente';
            return $attendant;
        }

        // 2. Check ERP VENDPREF via customer attribute or erp_data
        $attendant = $this->getAttendantFromErpSeller($connection, $customerId);
        if ($attendant) {
            // Also save the assignment for next time
            $this->saveAssignment($connection, $customerId, (int) $attendant['attendant_id']);
            $attendant['message'] = 'Atendente do ERP (VENDPREF)';
            return $attendant;
        }

        // 3. Fallback — round-robin
        $attendant = $this->getDefaultAttendant($connection);
        if ($attendant) {
            $this->saveAssignment($connection, $customerId, (int) $attendant['attendant_id']);
            $attendant['message'] = 'Atendente padrão (distribuição automática)';
            return $attendant;
        }

        return ['found' => false, 'message' => 'Nenhum atendente disponível', 'source' => 'no_attendant'];
    }

    /**
     * Find customer_id by phone number.
     *
     * Uses REGEXP_REPLACE to strip formatting chars before matching.
     * Lookup order: sales_order_address → customer_address_entity (all addresses).
     */
    private function findCustomerByPhone(\Magento\Framework\DB\Adapter\AdapterInterface $connection, string $phone): ?int
    {
        $lastDigits = substr($phone, -8);
        $digitExpr = "REGEXP_REPLACE(telephone, '[^0-9]', '')";

        // 1. Recent order billing address (most reliable — phone entered at checkout)
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('sales_order_address'),
                ['customer_id']
            )
            ->where('address_type = ?', 'billing')
            ->where('customer_id IS NOT NULL')
            ->where(new \Magento\Framework\DB\Sql\Expression("{$digitExpr} LIKE " . $connection->quote('%' . $lastDigits)))
            ->order('entity_id DESC')
            ->limit(1);

        $result = $connection->fetchOne($select);

        if (!$result) {
            // 2. Any customer address (no default_billing restriction)
            $select = $connection->select()
                ->from(
                    $this->resource->getTableName('customer_address_entity'),
                    ['parent_id']
                )
                ->where(new \Magento\Framework\DB\Sql\Expression("{$digitExpr} LIKE " . $connection->quote('%' . $lastDigits)))
                ->order('entity_id DESC')
                ->limit(1);

            $result = $connection->fetchOne($select);
        }

        return $result ? (int) $result : null;
    }

    /**
     * Get attendant assigned to customer via B2B table
     */
    private function getAssignedAttendant(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        int $customerId
    ): ?array {
        $select = $connection->select()
            ->from(
                ['ca' => $this->resource->getTableName('grupoawamotos_b2b_customer_attendant')],
                ['attendant_id']
            )
            ->join(
                ['a' => $this->resource->getTableName('grupoawamotos_b2b_attendants')],
                'ca.attendant_id = a.attendant_id',
                ['name', 'email', 'phone', 'whatsapp', 'department', 'erp_seller_code']
            )
            ->where('ca.customer_id = ?', $customerId)
            ->where('a.is_active = ?', 1);

        $row = $connection->fetchRow($select);
        if ($row) {
            return [
                'found' => true,
                'attendant_id' => (int) $row['attendant_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'whatsapp' => $row['whatsapp'],
                'source' => 'assigned'
            ];
        }

        return null;
    }

    /**
     * Try to match the customer's ERP seller code to an attendant
     */
    private function getAttendantFromErpSeller(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        int $customerId
    ): ?array {
        // Check if customer has erp_code attribute
        $erpCode = $this->getCustomerErpCode($connection, $customerId);
        if (!$erpCode) {
            return null;
        }

        // Look up VENDPREF in erp_integration data if available
        // Check grupoawamotos_erp_customer_mapping or customer attributes
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('grupoawamotos_b2b_attendants'),
                ['attendant_id', 'name', 'email', 'phone', 'whatsapp', 'erp_seller_code']
            )
            ->where('is_active = ?', 1)
            ->where('erp_seller_code = ?', $erpCode);

        $row = $connection->fetchRow($select);
        if ($row) {
            return [
                'found' => true,
                'attendant_id' => (int) $row['attendant_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'whatsapp' => $row['whatsapp'],
                'source' => 'erp_vendpref'
            ];
        }

        return null;
    }

    /**
     * Get the customer's ERP seller code from EAV attributes
     */
    private function getCustomerErpCode(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        int $customerId
    ): ?int {
        // Try to get erp_seller_code from customer_entity_int (EAV)
        $attrSelect = $connection->select()
            ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
            ->where('attribute_code = ?', 'erp_code')
            ->where('entity_type_id = ?', 1);

        $attrId = $connection->fetchOne($attrSelect);
        if (!$attrId) {
            return null;
        }

        // Get the ERP customer code
        $select = $connection->select()
            ->from($this->resource->getTableName('customer_entity_varchar'), ['value'])
            ->where('attribute_id = ?', $attrId)
            ->where('entity_id = ?', $customerId);

        $erpCustomerCode = $connection->fetchOne($select);
        if (!$erpCustomerCode) {
            return null;
        }

        // Now look up this customer's VENDPREF in the ERP mapping table (if synced locally)
        // Check if we have a local erp_customer_seller table
        $tableName = $this->resource->getTableName('grupoawamotos_erp_customer_data');
        if ($connection->isTableExists($tableName)) {
            $select = $connection->select()
                ->from($tableName, ['seller_code'])
                ->where('customer_code = ?', $erpCustomerCode);

            $sellerCode = $connection->fetchOne($select);
            return $sellerCode ? (int) $sellerCode : null;
        }

        return null;
    }

    /**
     * Get default attendant (round-robin: least customers)
     */
    private function getDefaultAttendant(\Magento\Framework\DB\Adapter\AdapterInterface $connection): ?array
    {
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('grupoawamotos_b2b_attendants'),
                ['attendant_id', 'name', 'email', 'phone', 'whatsapp', 'customer_count']
            )
            ->where('is_active = ?', 1)
            ->where('department = ?', 'sales')
            ->order('customer_count ASC')
            ->limit(1);

        $row = $connection->fetchRow($select);
        if ($row) {
            return [
                'found' => true,
                'attendant_id' => (int) $row['attendant_id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'whatsapp' => $row['whatsapp'],
                'source' => 'round_robin'
            ];
        }

        return null;
    }

    /**
     * Save customer-attendant assignment for future lookups
     */
    private function saveAssignment(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        int $customerId,
        int $attendantId
    ): void {
        try {
            $connection->insertOnDuplicate(
                $this->resource->getTableName('grupoawamotos_b2b_customer_attendant'),
                [
                    'customer_id' => $customerId,
                    'attendant_id' => $attendantId,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Auto-assigned via WhatsApp routing'
                ],
                ['attendant_id', 'assigned_at', 'notes']
            );

            // Update counter
            $countSelect = $connection->select()
                ->from(
                    $this->resource->getTableName('grupoawamotos_b2b_customer_attendant'),
                    ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')]
                )
                ->where('attendant_id = ?', $attendantId);

            $count = (int) $connection->fetchOne($countSelect);
            $connection->update(
                $this->resource->getTableName('grupoawamotos_b2b_attendants'),
                ['customer_count' => $count],
                ['attendant_id = ?' => $attendantId]
            );
        } catch (\Exception $e) {
            $this->logger->warning('[WhatsApp Attendant] Failed to save assignment: ' . $e->getMessage());
        }
    }
}
