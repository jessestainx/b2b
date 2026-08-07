<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

use GrupoAwamotos\B2B\Platform\Dashboard\B2bDashboardScopeHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;

/**
 * Query read-only da fila operacional Sectra (pedidos B2B pendentes de importação manual).
 */
class SectraOrderQueueQuery
{
    /** @var array<string, int>|null */
    private ?array $attributeIds = null;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly B2bDashboardScopeHelper $scopeHelper,
        private readonly SectraOrderQueueResolver $queueResolver
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function fetchPendingOrders(array $filters = []): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll($this->buildSelect($filters));
        $duplicateErpCounts = $this->fetchDuplicateErpCodeCounts();

        $items = [];
        foreach ($rows as $row) {
            $erpCode = (int) ($row['erp_code'] ?? 0);
            $row['erp_code_account_count'] = $erpCode > 0 ? ($duplicateErpCounts[$erpCode] ?? 1) : 0;
            $resolved = $this->queueResolver->resolve($row);
            $items[] = array_merge($row, [
                'customer_name' => trim((string) ($row['customer_name'] ?? '')),
                'sectra_chave_label' => $this->formatSectraChaveLabel($row),
                'queue_bucket' => $resolved['bucket'],
                'queue_bucket_label' => $resolved['bucket_label'],
                'block_reason' => $resolved['block_reason'],
                'next_action' => $resolved['next_action'],
                'in_oc_order_label' => $resolved['in_oc_order']
                    ? (string) __('Sim — importar no Sectra')
                    : (string) __('Não'),
            ]);
        }

        $bucketFilter = (string) ($filters['queue_bucket'] ?? '');
        if ($bucketFilter !== '') {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => ($item['queue_bucket'] ?? '') === $bucketFilter
            ));
        }

        return $items;
    }

    /**
     * @return array{ready: int, blocked: int, awaiting: int, imported: int, closed: int, total_pending: int}
     */
    public function getSummary(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll($this->buildSelect([]));

        return $this->queueResolver->summarizeRows($rows);
    }

    /**
     * Contagem de erp_code duplicados entre clientes B2B (risco na importação Sectra).
     *
     * @return array{duplicate_erp_codes: int, affected_customers: int}
     */
    public function getDuplicateErpCodeSummary(): array
    {
        $counts = $this->fetchDuplicateErpCodeCounts();
        $duplicateCodes = array_filter($counts, static fn (int $count): bool => $count > 1);

        return [
            'duplicate_erp_codes' => count($duplicateCodes),
            'affected_customers' => array_sum($duplicateCodes),
        ];
    }

    /**
     * @return array<int, int> erp_code => número de contas Magento
     */
    private function fetchDuplicateErpCodeCounts(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $erpAttrId = $this->getAttributeId('erp_code');

        $rows = $connection->fetchAll(
            "SELECT CAST(erp_attr.value AS UNSIGNED) AS erp_code, COUNT(*) AS cnt
             FROM customer_entity ce
             INNER JOIN customer_entity_varchar erp_attr
                 ON erp_attr.entity_id = ce.entity_id
                 AND erp_attr.attribute_id = ?
                 AND erp_attr.value REGEXP '^[0-9]+$'
             GROUP BY erp_code
             HAVING cnt > 1",
            [$erpAttrId]
        );

        $map = [];
        foreach ($rows as $row) {
            $code = (int) ($row['erp_code'] ?? 0);
            if ($code > 0) {
                $map[$code] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatSectraChaveLabel(array $row): string
    {
        $chave = (int) ($row['sectra_chave'] ?? 0);
        if ($chave <= 0) {
            return (string) __('—');
        }

        $legacy = (int) ($row['legacy_oc_customer_id'] ?? 0);
        $erpCode = (int) ($row['erp_code'] ?? 0);
        if ($erpCode > 0 && $legacy > 0 && $erpCode !== $legacy) {
            return sprintf('%d (%s %d)', $chave, (string) __('legado'), $legacy);
        }

        return (string) $chave;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildSelect(array $filters): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $mapTable = $this->resourceConnection->getTableName('oc_customer_id_map');
        $confirmedTable = $this->resourceConnection->getTableName('oc_customer_b2b_confirmed');
        $importedTable = $this->resourceConnection->getTableName('oc_order_imported');
        $ocCustomerTable = $this->resourceConnection->getTableName('oc_customer');
        $ocOrderTable = $this->resourceConnection->getTableName('oc_order');
        $varcharTable = $this->resourceConnection->getTableName('customer_entity_varchar');

        $erpAttrId = $this->getAttributeId('erp_code');
        $cnpjAttrId = $this->getAttributeId('b2b_cnpj');
        $sectraChaveExpr = SectraBridgeCustomerId::sqlExpression('so', 'map', 'erp_attr');
        $ocOrderIdExpr = SectraBridgeCustomerId::ocOrderIdExpression('so');

        $select = $connection->select()
            ->from(['so' => $orderTable], [
                'entity_id' => 'so.entity_id',
                'increment_id' => 'so.increment_id',
                'customer_id' => 'so.customer_id',
                'customer_name' => new Expression("CONCAT(COALESCE(so.customer_firstname, ''), ' ', COALESCE(so.customer_lastname, ''))"),
                'grand_total' => 'so.grand_total',
                'state' => 'so.state',
                'status' => 'so.status',
                'sectra_import_status' => 'so.sectra_import_status',
                'created_at' => 'so.created_at',
                'sectra_chave' => new Expression($sectraChaveExpr),
                'oc_order_id' => new Expression($ocOrderIdExpr),
                'legacy_oc_customer_id' => 'map.old_oc_customer_id',
            ])
            ->joinInner(['ce' => $customerTable], 'ce.entity_id = so.customer_id', [])
            ->joinLeft(['map' => $mapTable], 'map.magento_customer_id = so.customer_id', [])
            ->joinLeft(
                ['erp_attr' => $varcharTable],
                'erp_attr.entity_id = ce.entity_id AND erp_attr.attribute_id = ' . $erpAttrId,
                ['erp_code' => 'erp_attr.value']
            )
            ->joinLeft(
                ['b2b_conf' => $confirmedTable],
                'b2b_conf.customer_id = ' . $sectraChaveExpr,
                ['is_b2b_confirmed' => new Expression('CASE WHEN b2b_conf.customer_id IS NULL THEN 0 ELSE 1 END')]
            )
            ->joinLeft(
                ['oi' => $importedTable],
                'oi.order_id = ' . $ocOrderIdExpr,
                ['is_imported' => new Expression('CASE WHEN oi.order_id IS NULL THEN 0 ELSE 1 END')]
            )
            ->joinLeft(
                ['oc' => $ocOrderTable],
                'oc.order_id = ' . $ocOrderIdExpr,
                [
                    'in_oc_order' => new Expression('CASE WHEN oc.order_id IS NULL THEN 0 ELSE 1 END'),
                    'oc_order_customer_id' => 'oc.customer_id',
                ]
            )
            ->joinLeft(
                ['oc_cust' => $ocCustomerTable],
                'oc_cust.customer_id = ' . $sectraChaveExpr,
                [
                    'has_oc_customer' => new Expression('CASE WHEN oc_cust.customer_id IS NULL THEN 0 ELSE 1 END'),
                    'oc_customer_group_id' => 'oc_cust.customer_group_id',
                    'oc_customer_cnpj' => new Expression("JSON_UNQUOTE(JSON_EXTRACT(oc_cust.custom_field, '$.\"6\"'))"),
                ]
            )
            ->joinLeft(
                ['cnpj_attr' => $varcharTable],
                'cnpj_attr.entity_id = ce.entity_id AND cnpj_attr.attribute_id = ' . $cnpjAttrId,
                ['b2b_cnpj' => 'cnpj_attr.value']
            )
            ->where('ce.group_id IN (?)', $this->scopeHelper->getB2bGroupIds())
            ->where('so.state IN (?)', ['new', 'pending_payment', 'processing'])
            ->where(
                '(so.sectra_import_status IS NULL OR so.sectra_import_status NOT IN (?))',
                [SectraImportStatus::IMPORTED, SectraImportStatus::ORDER_CANCELLED_BEFORE_ERP_IMPORT]
            )
            ->where('oi.order_id IS NULL')
            ->order('so.created_at DESC');

        $this->scopeHelper->applyOrderCustomerScope($select);
        $this->applyFilters($select, $filters);

        return $select;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Select $select, array $filters): void
    {
        if (!empty($filters['increment_id'])) {
            $select->where('so.increment_id LIKE ?', '%' . $filters['increment_id'] . '%');
        }

        if (!empty($filters['customer_name'])) {
            $select->where(
                "CONCAT(COALESCE(so.customer_firstname, ''), ' ', COALESCE(so.customer_lastname, '')) LIKE ?",
                '%' . $filters['customer_name'] . '%'
            );
        }

        if (!empty($filters['erp_code'])) {
            $select->where('erp_attr.value LIKE ?', '%' . $filters['erp_code'] . '%');
        }

        if (!empty($filters['b2b_cnpj'])) {
            $select->where('cnpj_attr.value LIKE ?', '%' . $filters['b2b_cnpj'] . '%');
        }

        if (!empty($filters['sectra_import_status'])) {
            $select->where('so.sectra_import_status = ?', $filters['sectra_import_status']);
        }

        if (!empty($filters['state'])) {
            $select->where('so.state = ?', $filters['state']);
        }

        if (!empty($filters['created_at']['from'])) {
            $select->where('so.created_at >= ?', $filters['created_at']['from']);
        }

        if (!empty($filters['created_at']['to'])) {
            $select->where('so.created_at <= ?', $filters['created_at']['to']);
        }

        if (isset($filters['in_oc_order']) && $filters['in_oc_order'] !== '') {
            if ((string) $filters['in_oc_order'] === '1') {
                $select->where('oc.order_id IS NOT NULL');
            } else {
                $select->where('oc.order_id IS NULL');
            }
        }
    }

    private function getAttributeId(string $code): int
    {
        if ($this->attributeIds === null) {
            $this->attributeIds = [];
        }

        if (isset($this->attributeIds[$code])) {
            return $this->attributeIds[$code];
        }

        $connection = $this->resourceConnection->getConnection();
        $this->attributeIds[$code] = (int) $connection->fetchOne(
            "SELECT ea.attribute_id FROM eav_attribute ea
             INNER JOIN eav_entity_type et ON et.entity_type_id = ea.entity_type_id
             WHERE ea.attribute_code = ? AND et.entity_type_code = 'customer'",
            [$code]
        );

        return $this->attributeIds[$code];
    }
}
