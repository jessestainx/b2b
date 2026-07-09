<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface as ErpConnectionInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Syncs customer-attendant assignments from ERP Sectra VENDPREF field.
 *
 * Runs daily at 3am. Two passes:
 *   1. Customers with no attendant yet  → assign from VENDPREF
 *   2. Customers already assigned       → update if VENDPREF changed in ERP
 *
 * The ERP is the source of truth. Any VENDPREF change is reflected in
 * Magento on the next run without any manual intervention.
 */
class SyncAttendantFromErp
{
    private ResourceConnection $resource;
    private AttendantManager $attendantManager;
    private ErpConnectionInterface $erpConnection;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        AttendantManager $attendantManager,
        ErpConnectionInterface $erpConnection,
        LoggerInterface $logger
    ) {
        $this->resource         = $resource;
        $this->attendantManager = $attendantManager;
        $this->erpConnection    = $erpConnection;
        $this->logger           = $logger;
    }

    public function execute(): void
    {

        if (!$this->erpConnection->hasAvailableDriver()) {
            $this->logger->warning('[B2B AttendantSync] ERP driver unavailable, skipping');
            return;
        }

        $connection = $this->resource->getConnection();

        // Index active attendants by erp_seller_code
        $attendants = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('grupoawamotos_b2b_attendants'))
                ->where('is_active = ?', 1)
                ->where('erp_seller_code IS NOT NULL')
        );

        if (empty($attendants)) {
            $this->logger->info('[B2B AttendantSync] No attendants with ERP seller codes found');
            return;
        }

        $attendantByErpCode = [];
        foreach ($attendants as $att) {
            $attendantByErpCode[(int) $att['erp_seller_code']] = $att;
        }

        // Fetch erp_code EAV attribute ID
        $erpCodeAttrId = $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', 'erp_code')
                ->where('entity_type_id = ?', 1)
        );

        if (!$erpCodeAttrId) {
            $this->logger->warning('[B2B AttendantSync] erp_code attribute not found');
            return;
        }

        // Fetch ALL customers with ERP codes (new and already assigned)
        $select = $connection->select()
            ->from(
                ['cev' => $this->resource->getTableName('customer_entity_varchar')],
                ['entity_id', 'erp_code' => 'cev.value']
            )
            ->joinLeft(
                ['ca' => $this->resource->getTableName('grupoawamotos_b2b_customer_attendant')],
                'cev.entity_id = ca.customer_id',
                ['current_attendant_id' => 'ca.attendant_id']
            )
            ->where('cev.attribute_id = ?', $erpCodeAttrId)
            ->where('cev.value IS NOT NULL')
            ->where('cev.value != ?', '');

        $customers = $connection->fetchAll($select);

        $this->logger->info(sprintf(
            '[B2B AttendantSync] Processing %d customers with ERP codes',
            count($customers)
        ));

        $assigned = 0;
        $updated  = 0;
        $noMatch  = 0;
        $errors   = 0;

        foreach (array_chunk($customers, 50) as $chunk) {
            $erpCodes     = array_column($chunk, 'erp_code');
            $placeholders = implode(',', array_fill(0, count($erpCodes), '?'));

            try {
                $erpData = $this->erpConnection->query(
                    "SELECT f.CODIGO, f.VENDPREF
                     FROM dbo.FN_FORNECEDORES f
                     WHERE f.CKCLIENTE = 'S' AND f.CODIGO IN ({$placeholders})",
                    $erpCodes
                );
            } catch (\Exception $e) {
                $this->logger->error('[B2B AttendantSync] ERP query error: ' . $e->getMessage());
                $errors++;
                continue;
            }

            $vendMap = [];
            foreach ($erpData as $row) {
                $vendMap[(string) $row['CODIGO']] = (int) ($row['VENDPREF'] ?? 0);
            }

            foreach ($chunk as $customer) {
                $customerId       = (int) $customer['entity_id'];
                $erpCode          = (string) $customer['erp_code'];
                $currentAttendant = $customer['current_attendant_id'] !== null
                    ? (int) $customer['current_attendant_id']
                    : null;
                $vendPref = $vendMap[$erpCode] ?? 0;

                if ($vendPref <= 0 || !isset($attendantByErpCode[$vendPref])) {
                    $noMatch++;
                    continue;
                }

                $targetAttendantId = (int) $attendantByErpCode[$vendPref]['attendant_id'];

                if ($currentAttendant === null) {
                    // New assignment
                    $this->attendantManager->assignCustomerToAttendant(
                        $customerId,
                        $targetAttendantId,
                        'Sync ERP VENDPREF=' . $vendPref
                    );
                    $assigned++;
                } elseif ($currentAttendant !== $targetAttendantId) {
                    // VENDPREF changed — follow the ERP
                    $this->attendantManager->assignCustomerToAttendant(
                        $customerId,
                        $targetAttendantId,
                        'Re-sync ERP VENDPREF=' . $vendPref . ' (anterior #' . $currentAttendant . ')'
                    );
                    $updated++;
                }
                // else: already correct attendant, nothing to do
            }
        }

        // Refresh customer_count in attendants table
        $attTable = $this->resource->getTableName('grupoawamotos_b2b_attendants');
        $caTable  = $this->resource->getTableName('grupoawamotos_b2b_customer_attendant');
        $connection->query(
            "UPDATE {$attTable} a SET a.customer_count = ("
            . "SELECT COUNT(*) FROM {$caTable} ca WHERE ca.attendant_id = a.attendant_id"
            . ")"
        );

        $this->logger->info(sprintf(
            '[B2B AttendantSync] Done: %d new, %d re-assigned, %d no ERP match, %d chunk errors',
            $assigned,
            $updated,
            $noMatch,
            $errors
        ));
    }
}
