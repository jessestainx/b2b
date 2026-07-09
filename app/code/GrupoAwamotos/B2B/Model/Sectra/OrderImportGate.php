<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Exposes approved B2B orders only when Sectra can resolve the ERP client.
 */
class OrderImportGate
{
    private const B2B_GROUP_IDS = [4, 5, 6];
    private const OC_ORDER_ID_OFFSET = 200000;

    public function __construct(
        private readonly ValidatorChecker $validatorChecker,
        private readonly SectraSyncLogger $syncLogger,
        private readonly B2bConfig $b2bConfig,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Backfill sectra_import_status for orders placed before the gate existed.
     */
    public function backfillOrderImportStatus(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            "SELECT so.entity_id, so.customer_id, so.sectra_import_status
             FROM sales_order so
             INNER JOIN customer_entity ce ON ce.entity_id = so.customer_id
             WHERE ce.group_id IN (4, 5, 6, 7)
               AND so.state NOT IN ('canceled', 'closed')
               AND (so.sectra_import_status IS NULL
                   OR so.sectra_import_status IN (?, ?, ?))",
            [
                SectraImportStatus::AWAITING_CUSTOMER_VALIDATION,
                SectraImportStatus::READY_FOR_IMPORT,
                SectraImportStatus::ORDER_CANCELLED_BEFORE_ERP_IMPORT,
            ]
        );

        $updated = 0;
        $targetReady = 0;
        $targetAwaiting = 0;
        $alreadyImported = 0;
        foreach ($rows as $row) {
            $customerId = (int) $row['customer_id'];
            $orderId = (int) $row['entity_id'];
            $current = (string) ($row['sectra_import_status'] ?? '');

            if ($this->isOrderAlreadyImported($orderId)) {
                $alreadyImported++;
                if ($current !== SectraImportStatus::IMPORTED) {
                    $this->setOrderImportStatus($orderId, SectraImportStatus::IMPORTED);
                    $updated++;
                }
                continue;
            }

            $targetStatus = $this->validatorChecker->isCustomerValidatedInSectra($customerId)
                ? SectraImportStatus::READY_FOR_IMPORT
                : SectraImportStatus::AWAITING_CUSTOMER_VALIDATION;
            if ($targetStatus === SectraImportStatus::READY_FOR_IMPORT) {
                $targetReady++;
            } else {
                $targetAwaiting++;
            }

            if ($current !== $targetStatus) {
                $this->setOrderImportStatus($orderId, $targetStatus);
                $updated++;
            }
        }

        $released = $this->releaseOrdersForValidatedCustomers();

        return $updated + $released;
    }

    /**
     * Release B2B orders held for customer validation once Sectra confirms Cadastro de Cliente.
     */
    public function releaseOrdersForValidatedCustomers(): int
    {
        $connection = $this->resourceConnection->getConnection();
        /** @var array<int, array{entity_id:string|int, customer_id:string|int}> $rows */
        $rows = $connection->fetchAll(
            "SELECT so.entity_id, so.customer_id
                 FROM sales_order so
                 INNER JOIN customer_entity ce ON ce.entity_id = so.customer_id
                 INNER JOIN oc_customer_id_map map ON map.magento_customer_id = ce.entity_id
                 LEFT JOIN customer_entity_varchar erp_attr
                     ON erp_attr.entity_id = ce.entity_id
                     AND erp_attr.attribute_id = (
                         SELECT attribute_id FROM eav_attribute
                         WHERE attribute_code = 'erp_code'
                           AND entity_type_id = (
                               SELECT entity_type_id FROM eav_entity_type
                               WHERE entity_type_code = 'customer'
                           )
                     )
                     AND erp_attr.value REGEXP '^[0-9]+$'
                 INNER JOIN oc_customer_b2b_confirmed confirmed
                     ON confirmed.customer_id = COALESCE(
                         NULLIF(CAST(erp_attr.value AS UNSIGNED), 0),
                         map.old_oc_customer_id
                     )
                 WHERE ce.group_id IN (4, 5, 6, 7)
                   AND so.state IN ('new', 'pending_payment', 'processing')
                   AND so.sectra_import_status IN (?, ?, ?)
                   AND NOT EXISTS (
                       SELECT 1
                       FROM oc_order_imported oi
                       WHERE oi.order_id = so.entity_id + ?
                   )",
            [
                SectraImportStatus::AWAITING_CUSTOMER_VALIDATION,
                SectraImportStatus::ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED,
                SectraImportStatus::ORDER_CANCELLED_BEFORE_ERP_IMPORT,
                self::OC_ORDER_ID_OFFSET,
            ]
        );

        if ($rows === []) {
            return 0;
        }

        $releasableOrderIds = [];
        foreach ($rows as $row) {
            $orderId = (int) $row['entity_id'];
            $customerId = (int) $row['customer_id'];

            if ($customerId <= 0 || !$this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
                continue;
            }

            $releasableOrderIds[] = $orderId;
        }

        if ($releasableOrderIds === []) {
            return 0;
        }

        $connection->update(
            'sales_order',
            ['sectra_import_status' => SectraImportStatus::READY_FOR_IMPORT],
            ['entity_id IN (?)' => $releasableOrderIds]
        );

        foreach ($releasableOrderIds as $orderId) {
            $row = $connection->fetchRow(
                'SELECT increment_id, customer_id FROM sales_order WHERE entity_id = ?',
                [$orderId]
            );
            if ($row === false) {
                continue;
            }

            $customerId = (int) ($row['customer_id'] ?? 0);
            $this->syncLogger->log(
                ProspectEvent::ORDER_RELEASED_FOR_IMPORT,
                sprintf(
                    'Pedido #%s liberado após Cadastro de Cliente no Sectra.',
                    $row['increment_id'] ?? $orderId
                ),
                $customerId > 0 ? $customerId : null,
                $orderId,
                null,
                $customerId > 0 ? $this->validatorChecker->resolveSectraChave($customerId) : null,
                'success'
            );
        }

        $this->logger->info('[B2B-Sectra] Pedidos liberados após validação ERP', [
            'count' => count($releasableOrderIds),
            'order_ids' => $releasableOrderIds,
        ]);

        return count($releasableOrderIds);
    }

    private function isOrderAlreadyImported(int $orderId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $exists = $connection->fetchOne(
            'SELECT order_id FROM oc_order_imported WHERE order_id = ?',
            [$orderId + self::OC_ORDER_ID_OFFSET]
        );

        return $exists !== false;
    }

    public function applyOnOrderPlace(OrderInterface $order): void
    {
        if (!$this->b2bConfig->isEnabled()) {
            return;
        }

        $customerId = (int) $order->getCustomerId();
        if ($customerId <= 0) {
            return;
        }

        if (!$this->isB2bCustomer($customerId)) {
            $this->setOrderImportStatus((int) $order->getEntityId(), SectraImportStatus::NOT_APPLICABLE);
            return;
        }

        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
        if (!$this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
            $this->setOrderImportStatus((int) $order->getEntityId(), SectraImportStatus::AWAITING_CUSTOMER_VALIDATION);
            $this->syncLogger->log(
                ProspectEvent::ORDER_AWAITING_CUSTOMER,
                sprintf(
                    'Pedido #%s aguardando cadastro do cliente no B2B do Sectra.',
                    $order->getIncrementId()
                ),
                $customerId,
                (int) $order->getEntityId(),
                null,
                $sectraChave
            );
            return;
        }

        $this->setOrderImportStatus((int) $order->getEntityId(), SectraImportStatus::READY_FOR_IMPORT);

        $this->syncLogger->log(
            ProspectEvent::ORDER_RELEASED_FOR_IMPORT,
            sprintf(
                'Pedido #%s liberado para Importar Pedidos no Sectra.',
                $order->getIncrementId()
            ),
            $customerId,
            (int) $order->getEntityId(),
            null,
            $sectraChave,
            'success'
        );
    }

    /**
     * Release held orders after customer validation in Sectra.
     */
    public function releaseOrdersForCustomer(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $orderIds = $connection->fetchCol(
            'SELECT entity_id FROM sales_order
             WHERE customer_id = ?
               AND sectra_import_status = ?
               AND state NOT IN (?, ?)',
            [
                $customerId,
                SectraImportStatus::AWAITING_CUSTOMER_VALIDATION,
                Order::STATE_CANCELED,
                Order::STATE_CLOSED,
            ]
        );

        if ($orderIds === []) {
            return 0;
        }

        $connection->update(
            'sales_order',
            ['sectra_import_status' => SectraImportStatus::READY_FOR_IMPORT],
            [
                'entity_id IN (?)' => $orderIds,
            ]
        );

        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
        foreach ($orderIds as $orderId) {
            $incrementId = $connection->fetchOne(
                'SELECT increment_id FROM sales_order WHERE entity_id = ?',
                [(int) $orderId]
            );
            $this->syncLogger->log(
                ProspectEvent::ORDER_RELEASED_FOR_IMPORT,
                sprintf(
                    'Pedido #%s liberado após validação do cliente no Sectra.',
                    $incrementId ?: $orderId
                ),
                $customerId,
                (int) $orderId,
                null,
                $sectraChave,
                'success'
            );
        }

        return count($orderIds);
    }

    /**
     * Mark orders imported by Sectra (oc_order_imported ack).
     */
    public function syncImportedOrderFlags(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            'SELECT oi.order_id, so.entity_id, so.increment_id, so.customer_id
             FROM oc_order_imported oi
             INNER JOIN sales_order so ON so.entity_id + ? = oi.order_id
             WHERE so.sectra_import_status IS NULL
                OR so.sectra_import_status IN (?, ?)',
            [
                self::OC_ORDER_ID_OFFSET,
                SectraImportStatus::READY_FOR_IMPORT,
                SectraImportStatus::AWAITING_CUSTOMER_VALIDATION,
            ]
        );

        $updated = 0;
        foreach ($rows as $row) {
            $orderId = (int) $row['entity_id'];
            $this->setOrderImportStatus($orderId, SectraImportStatus::IMPORTED);
            $this->syncLogger->log(
                ProspectEvent::ORDER_IMPORTED_SUCCESS,
                sprintf('Pedido #%s importado com sucesso no ERP Sectra.', $row['increment_id']),
                (int) ($row['customer_id'] ?? 0) ?: null,
                $orderId,
                null,
                null,
                'success'
            );
            $updated++;
        }

        return $updated;
    }

    private function isB2bCustomer(int $customerId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $groupId = (int) $connection->fetchOne(
            'SELECT group_id FROM customer_entity WHERE entity_id = ?',
            [$customerId]
        );

        return in_array($groupId, self::B2B_GROUP_IDS, true);
    }

    private function setOrderImportStatus(int $orderId, string $status): void
    {
        $this->resourceConnection->getConnection()->update(
            'sales_order',
            ['sectra_import_status' => $status],
            ['entity_id = ?' => $orderId]
        );
    }

    private function addOrderComment(OrderInterface $order, string $comment): void
    {
        if (!$order instanceof Order) {
            return;
        }

        try {
            $order->addCommentToStatusHistory($comment, false, false);
            $order->save();
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                '[B2B-Sectra] Pedido #%s: falha ao adicionar comentário — %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
