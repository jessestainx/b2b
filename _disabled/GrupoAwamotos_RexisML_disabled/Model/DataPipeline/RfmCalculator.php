<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\DataPipeline;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class RfmCalculator
{
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    private const SEGMENTS = [
        'champions'      => ['r' => [4,5], 'f' => [4,5], 'm' => [4,5]],
        'loyal'          => ['r' => [3,4,5], 'f' => [3,4,5], 'm' => [3,4,5]],
        'potential'      => ['r' => [4,5], 'f' => [1,2,3], 'm' => [1,2,3]],
        'new_customers'  => ['r' => [5], 'f' => [1], 'm' => [1,2]],
        'at_risk'        => ['r' => [1,2], 'f' => [3,4,5], 'm' => [3,4,5]],
        'cant_lose'      => ['r' => [1,2], 'f' => [4,5], 'm' => [4,5]],
        'hibernating'    => ['r' => [1,2], 'f' => [1,2], 'm' => [1,2]],
        'lost'           => ['r' => [1], 'f' => [1], 'm' => [1]],
    ];

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Calculate RFM from order data and populate rexis_customer_classification
     *
     * @param array $orders Raw order rows from ERP
     * @param array $customers Customer master data
     * @return int Number of customers classified
     */
    public function calculate(array $orders, array $customers): int
    {
        if (empty($orders)) {
            $this->logger->warning('[RexisML RFM] No orders to process');
            return 0;
        }

        $now = new \DateTime();
        $mesCode = $now->format('m-Y');

        // Build customer map
        $customerMap = [];
        foreach ($customers as $c) {
            $customerMap[$c['customer_code']] = $c;
        }

        // Aggregate per customer: last_date, frequency, monetary
        $agg = [];
        foreach ($orders as $row) {
            $cid = $row['customer_code'];
            if (!isset($agg[$cid])) {
                $agg[$cid] = [
                    'last_date' => $row['order_date'],
                    'orders' => [],
                    'monetary' => 0.0,
                ];
            }
            if ($row['order_date'] > $agg[$cid]['last_date']) {
                $agg[$cid]['last_date'] = $row['order_date'];
            }
            $agg[$cid]['orders'][$row['order_id']] = true;
            $agg[$cid]['monetary'] += (float)$row['qty'] * (float)$row['unit_price'];
        }

        // Extract R, F, M arrays
        $recArr = [];
        $freqArr = [];
        $monArr = [];
        foreach ($agg as $cid => $data) {
            $lastDate = new \DateTime($data['last_date']);
            $recArr[$cid] = (int)$lastDate->diff($now)->days;
            $freqArr[$cid] = count($data['orders']);
            $monArr[$cid] = $data['monetary'];
        }

        // Calculate quintiles
        $rQuintiles = $this->quintiles(array_values($recArr));
        $fQuintiles = $this->quintiles(array_values($freqArr));
        $mQuintiles = $this->quintiles(array_values($monArr));

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('rexis_customer_classification');
        $customerEntityTable = $this->resource->getTableName('customer_entity');

        // Filter out deleted customers to avoid FK constraint violation
        $customerIds = array_map('intval', array_keys($agg));
        $existingIds = [];
        foreach (array_chunk($customerIds, 1000) as $chunk) {
            $select = $connection->select()
                ->from($customerEntityTable, ['entity_id'])
                ->where('entity_id IN (?)', $chunk);
            $existingIds = array_merge($existingIds, $connection->fetchCol($select));
        }
        $existingIdsMap = array_flip($existingIds);
        $skipped = count($agg) - count(array_intersect_key($agg, $existingIdsMap));
        if ($skipped > 0) {
            $this->logger->info(
                sprintf('[RexisML RFM] Skipped %d customers (deleted from customer_entity)', $skipped)
            );
        }

        // Clear current month data
        $connection->delete($table, ['mes_rexis_code = ?' => $mesCode]);

        $count = 0;
        foreach ($agg as $cid => $data) {
            // Skip customers that no longer exist in customer_entity
            if (!isset($existingIdsMap[(int)$cid])) {
                continue;
            }
            // Recency: lower days = better = higher score (invert)
            $rScore = 6 - $this->quintileScore($recArr[$cid], $rQuintiles);
            $fScore = $this->quintileScore($freqArr[$cid], $fQuintiles);
            $mScore = $this->quintileScore($monArr[$cid], $mQuintiles);

            $segment = $this->determineSegment($rScore, $fScore, $mScore);
            $freq = $freqArr[$cid];
            $mon = $monArr[$cid];
            $ticket = $freq > 0 ? $mon / $freq : 0;

            $customerInfo = $customerMap[$cid] ?? [];
            $identifier = $customerInfo['cnpj'] ?? (string)$cid;

            $connection->insertOnDuplicate($table, [
                'customer_id'           => (int)$cid,
                'identificador_cliente' => $identifier,
                'mes_rexis_code'        => $mesCode,
                'classificacao_cliente' => $segment,
                'rfm_score'             => "{$rScore}{$fScore}{$mScore}",
                'recency'               => $recArr[$cid],
                'frequency'             => $freq,
                'monetary'              => $mon,
                'mean_ticket_per_order'  => round($ticket, 2),
                'ltv'                   => round($mon * 1.2, 2),
            ], ['classificacao_cliente', 'rfm_score', 'recency', 'frequency', 'monetary', 'mean_ticket_per_order', 'ltv', 'updated_at']);

            $count++;
        }

        $this->logger->info("[RexisML RFM] Classified {$count} customers for period {$mesCode}");
        return $count;
    }

    private function quintiles(array $values): array
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return [0, 0, 0, 0];
        }
        return [
            $values[(int)($n * 0.2)] ?? 0,
            $values[(int)($n * 0.4)] ?? 0,
            $values[(int)($n * 0.6)] ?? 0,
            $values[(int)($n * 0.8)] ?? 0,
        ];
    }

    private function quintileScore(float $value, array $quintiles): int
    {
        if ($value <= $quintiles[0]) {
            return 1;
        }
        if ($value <= $quintiles[1]) {
            return 2;
        }
        if ($value <= $quintiles[2]) {
            return 3;
        }
        if ($value <= $quintiles[3]) {
            return 4;
        }
        return 5;
    }

    private function determineSegment(int $r, int $f, int $m): string
    {
        if ($r >= 4 && $f >= 4 && $m >= 4) {
            return 'Champions';
        }
        if ($r >= 3 && $f >= 3 && $m >= 3) {
            return 'Loyal';
        }
        if ($r >= 4 && $f <= 2) {
            return 'Novos Clientes';
        }
        if ($r <= 2 && $f >= 4 && $m >= 4) {
            return 'Não Pode Perder';
        }
        if ($r <= 2 && $f >= 3) {
            return 'Em Risco';
        }
        if ($r >= 3 && $f <= 2 && $m <= 3) {
            return 'Potencial';
        }
        if ($r <= 2 && $f <= 2) {
            return 'Hibernando';
        }
        if ($r <= 1 && $f <= 1) {
            return 'Perdido';
        }
        return 'Atenção Necessária';
    }
}
