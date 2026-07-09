<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\DataPipeline;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CrossSellRecommender
{
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    /** Max cross-sell recommendations per customer */
    private const MAX_PER_CUSTOMER = 15;

    /** Minimum lift to consider a rule */
    private const MIN_LIFT = 1.5;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Generate per-customer cross-sell recommendations using network rules + purchase history
     *
     * For each customer:
     *   1. Get their purchased products
     *   2. Match against rexis_network_rules (antecedent = purchased product)
     *   3. Suggest consequent products they haven't bought yet
     *   4. Write to rexis_dataset_recomendacao with tipo_recomendacao='crosssell'
     *
     * @param array $orders Raw order rows from ERP (order_id, customer_code, product_code, etc.)
     * @param array $products Product master data (product_code, price, description, category)
     * @return int Number of cross-sell recommendations generated
     */
    public function recommend(array $orders, array $products): int
    {
        if (empty($orders)) {
            return 0;
        }

        $connection = $this->resource->getConnection();
        $rulesTable = $this->resource->getTableName('rexis_network_rules');
        $recTable = $this->resource->getTableName('rexis_dataset_recomendacao');
        $rfmTable = $this->resource->getTableName('rexis_customer_classification');

        $now = new \DateTime();
        $mesCode = $now->format('m-Y');

        // Load active association rules
        $rules = $connection->fetchAll(
            $connection->select()
                ->from($rulesTable, ['antecedent', 'consequent', 'confidence', 'lift', 'support'])
                ->where('is_active = 1')
                ->where('lift >= ?', self::MIN_LIFT)
                ->order('lift DESC')
        );

        if (empty($rules)) {
            $this->logger->warning('[RexisML CrossSell Recommender] No active rules found');
            return 0;
        }

        $this->logger->info(sprintf('[RexisML CrossSell Recommender] Using %d rules', count($rules)));

        // Index rules by antecedent for fast lookup
        $rulesByAntecedent = [];
        $maxLift = 1.0;
        foreach ($rules as $rule) {
            $rulesByAntecedent[$rule['antecedent']][] = $rule;
            $maxLift = max($maxLift, (float)$rule['lift']);
        }

        // Build product price map
        $priceMap = [];
        foreach ($products as $p) {
            $priceMap[$p['product_code']] = (float)($p['price'] ?? 0);
        }

        // Group purchases by customer: customer_code => [product_code => last_order_date]
        $customerPurchases = [];
        foreach ($orders as $row) {
            $cid = $row['customer_code'];
            $pid = $row['product_code'];
            $date = $row['order_date'];

            if (!isset($customerPurchases[$cid][$pid]) || $date > $customerPurchases[$cid][$pid]) {
                $customerPurchases[$cid][$pid] = $date;
            }
        }

        // Load RFM data for classification labels
        $rfmRows = $connection->fetchAll(
            $connection->select()->from($rfmTable, ['customer_id', 'classificacao_cliente'])
                ->where('mes_rexis_code = ?', $mesCode)
        );
        $rfmMap = [];
        foreach ($rfmRows as $r) {
            $rfmMap[$r['customer_id']] = $r['classificacao_cliente'];
        }

        // Clear current month's crosssell recommendations
        $connection->delete($recTable, [
            'mes_rexis_code = ?' => $mesCode,
            'tipo_recomendacao = ?' => 'crosssell',
        ]);

        // Filtrar customer_ids inválidos antes do INSERT para evitar FK violation em
        // rexis_dataset_recomendacao.customer_id → customer_entity.entity_id
        $potentialIds = array_keys($customerPurchases);
        $validIdSet = $this->fetchExistingCustomerIds($connection, $potentialIds);
        if (count($validIdSet) < count($potentialIds)) {
            $this->logger->info(sprintf(
                '[RexisML CrossSell] Skipped %d deleted customers (FK protection)',
                count($potentialIds) - count($validIdSet)
            ));
            $customerPurchases = array_intersect_key($customerPurchases, array_flip($validIdSet));
        }

        $count = 0;
        $batchData = [];
        $batchSize = 500;

        foreach ($customerPurchases as $cid => $purchasedProducts) {
            $suggestions = [];

            // For each product the customer bought, find cross-sell candidates
            foreach (array_keys($purchasedProducts) as $boughtProduct) {
                if (!isset($rulesByAntecedent[$boughtProduct])) {
                    continue;
                }

                foreach ($rulesByAntecedent[$boughtProduct] as $rule) {
                    $suggestedProduct = $rule['consequent'];

                    // Skip if customer already bought this product
                    if (isset($purchasedProducts[$suggestedProduct])) {
                        continue;
                    }

                    // Normalize score: confidence * (lift / maxLift) => 0 to 1
                    $score = round(
                        (float)$rule['confidence'] * ((float)$rule['lift'] / $maxLift),
                        4
                    );

                    // Keep the best score if multiple rules suggest the same product
                    if (!isset($suggestions[$suggestedProduct]) || $score > $suggestions[$suggestedProduct]['score']) {
                        $recency = 0;
                        if (isset($purchasedProducts[$boughtProduct])) {
                            try {
                                $lastDate = new \DateTime($purchasedProducts[$boughtProduct]);
                                $recency = (int)$lastDate->diff($now)->days;
                            } catch (\Exception $e) {
                                $recency = 0;
                            }
                        }

                        $suggestions[$suggestedProduct] = [
                            'score' => $score,
                            'confidence' => (float)$rule['confidence'],
                            'lift' => (float)$rule['lift'],
                            'antecedent' => $boughtProduct,
                            'recency' => $recency,
                        ];
                    }
                }
            }

            // Sort by score desc and limit per customer
            uasort($suggestions, fn($a, $b) => $b['score'] <=> $a['score']);
            $suggestions = array_slice($suggestions, 0, self::MAX_PER_CUSTOMER, true);

            $classification = $rfmMap[$cid] ?? 'Desconhecido';

            foreach ($suggestions as $pid => $info) {
                $unitPrice = $priceMap[$pid] ?? 0;
                $chaveGlobal = "{$cid}-{$pid}-{$mesCode}-xs";

                $batchData[] = [
                    'chave_global'           => $chaveGlobal,
                    'mes_rexis_code'         => $mesCode,
                    'identificador_cliente'  => (string)$cid,
                    'customer_id'            => (int)$cid,
                    'identificador_produto'  => (string)$pid,
                    'classificacao_cliente'  => $classification,
                    'classificacao_produto'  => 'crosssell',
                    'ja_comprou'             => 0,
                    'pred'                   => $info['score'],
                    'probabilidade_compra'   => $info['confidence'],
                    'previsao_gasto_round_up' => $unitPrice,
                    'valor_total_esperado'   => $unitPrice,
                    'valor_unitario'         => $unitPrice,
                    'tipo_recomendacao'      => 'crosssell',
                    'recencia'               => $info['recency'],
                ];
                $count++;

                // Flush batch
                if (count($batchData) >= $batchSize) {
                    $this->insertBatch($connection, $recTable, $batchData);
                    $batchData = [];
                }
            }
        }

        // Flush remaining
        if (!empty($batchData)) {
            $this->insertBatch($connection, $recTable, $batchData);
        }

        $this->logger->info("[RexisML CrossSell Recommender] Generated {$count} cross-sell recommendations for {$mesCode}");
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

    /**
     * Insert a batch of rows using insertOnDuplicate
     */
    private function insertBatch($connection, string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $connection->insertOnDuplicate($table, $row, [
                'pred', 'probabilidade_compra', 'previsao_gasto_round_up',
                'valor_total_esperado', 'classificacao_cliente', 'recencia', 'updated_at'
            ]);
        }
    }
}
