<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\DataPipeline;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ChurnDetector
{
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    /** Months of inactivity to consider churn */
    private const CHURN_MONTHS = 3;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Detect churn opportunities from order data
     *
     * Logic: For each customer, find products they bought before but NOT in the last CHURN_MONTHS.
     * These are churn candidates — products the customer might buy again with a nudge.
     *
     * @param array $orders Raw order rows from ERP
     * @param array $products Product master data
     * @return int Number of churn recommendations generated
     */
    public function detect(array $orders, array $products): int
    {
        if (empty($orders)) {
            return 0;
        }

        $now = new \DateTime();
        $mesCode = $now->format('m-Y');
        $churnCutoff = (clone $now)->modify('-' . self::CHURN_MONTHS . ' months');

        // Build product price map
        $priceMap = [];
        foreach ($products as $p) {
            $priceMap[$p['product_code']] = (float)($p['price'] ?? 0);
        }

        // Group orders by customer → product
        $customerProducts = [];
        foreach ($orders as $row) {
            $cid = $row['customer_code'];
            $pid = $row['product_code'];
            $date = $row['order_date'];
            $price = (float)$row['unit_price'];

            if (!isset($customerProducts[$cid][$pid])) {
                $customerProducts[$cid][$pid] = [
                    'last_date' => $date,
                    'total_qty' => 0,
                    'total_value' => 0.0,
                    'count' => 0,
                    'unit_price' => $price,
                ];
            }

            $cp = &$customerProducts[$cid][$pid];
            if ($date > $cp['last_date']) {
                $cp['last_date'] = $date;
            }
            $cp['total_qty'] += (int)$row['qty'];
            $cp['total_value'] += (float)$row['qty'] * $price;
            $cp['count']++;
            if ($price > 0) {
                $cp['unit_price'] = $price;
            }
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('rexis_dataset_recomendacao');

        // Clear current month's churn recommendations
        $connection->delete($table, [
            'mes_rexis_code = ?' => $mesCode,
            'tipo_recomendacao = ?' => 'churn',
        ]);

        // Read RFM data for scoring
        $rfmTable = $this->resource->getTableName('rexis_customer_classification');
        $rfmRows = $connection->fetchAll(
            $connection->select()->from($rfmTable, ['customer_id', 'rfm_score', 'classificacao_cliente'])
                ->where('mes_rexis_code = ?', $mesCode)
        );
        $rfmMap = [];
        foreach ($rfmRows as $r) {
            $rfmMap[$r['customer_id']] = $r;
        }

        // Filtrar customer_ids inválidos antes do INSERT para evitar FK violation em
        // rexis_dataset_recomendacao.customer_id → customer_entity.entity_id
        $potentialIds = array_keys($customerProducts);
        $validIdSet = $this->fetchExistingCustomerIds($connection, $potentialIds);
        if (count($validIdSet) < count($potentialIds)) {
            $this->logger->info(sprintf(
                '[RexisML Churn] Skipped %d deleted customers (FK protection)',
                count($potentialIds) - count($validIdSet)
            ));
            $customerProducts = array_intersect_key($customerProducts, array_flip($validIdSet));
        }

        $count = 0;
        foreach ($customerProducts as $cid => $products) {
            foreach ($products as $pid => $data) {
                $lastDate = new \DateTime($data['last_date']);

                // Only churn if NOT purchased recently
                if ($lastDate >= $churnCutoff) {
                    continue;
                }

                $recency = (int)$lastDate->diff($now)->days;

                // Score: combine frequency (count) and recency
                $freqScore = min($data['count'] / 10, 1.0);
                $recencyScore = max(0, 1 - ($recency / 365));
                $rfmBonus = 0;
                if (isset($rfmMap[$cid])) {
                    $rfmDigits = str_split($rfmMap[$cid]['rfm_score']);
                    $rfmBonus = (array_sum(array_map('intval', $rfmDigits)) / 15) * 0.3;
                }
                $score = round(($freqScore * 0.4 + $recencyScore * 0.3 + $rfmBonus), 4);

                $unitPrice = $data['unit_price'] ?: ($priceMap[$pid] ?? 0);
                $avgQty = $data['count'] > 0 ? $data['total_qty'] / $data['count'] : 1;
                $expectedSpend = round($unitPrice * $avgQty, 2);

                $chaveGlobal = "{$cid}-{$pid}-{$mesCode}";
                $classification = $rfmMap[$cid]['classificacao_cliente'] ?? 'Desconhecido';

                $connection->insertOnDuplicate($table, [
                    'chave_global'           => $chaveGlobal,
                    'mes_rexis_code'         => $mesCode,
                    'identificador_cliente'  => (string)$cid,
                    'customer_id'            => (int)$cid,
                    'identificador_produto'  => (string)$pid,
                    'classificacao_cliente'  => $classification,
                    'classificacao_produto'  => 'churn',
                    'ja_comprou'             => 1,
                    'pred'                   => $score,
                    'probabilidade_compra'   => $score,
                    'previsao_gasto_round_up' => $expectedSpend,
                    'valor_total_esperado'   => $expectedSpend,
                    'valor_unitario'         => $unitPrice,
                    'tipo_recomendacao'      => 'churn',
                    'recencia'               => $recency,
                ], ['pred', 'probabilidade_compra', 'previsao_gasto_round_up', 'valor_total_esperado', 'recencia', 'classificacao_cliente', 'updated_at']);

                $count++;
            }
        }

        $this->logger->info("[RexisML Churn] Generated {$count} churn recommendations for {$mesCode}");
        return $count;
    }

    /**
     * Retorna os customer_ids que existem em customer_entity (evita FK violation)
     *
     * @param mixed $connection
     * @param int[]|string[] $ids
     * @return list<int>
     */
    private function fetchExistingCustomerIds($connection, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $intIds = array_values(array_unique(array_map('intval', $ids)));
        $rows = $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName('customer_entity'), ['entity_id'])
                ->where('entity_id IN (?)', $intIds)
        );
        return array_map('intval', $rows);
    }
}
