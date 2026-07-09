<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\DataPipeline;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CrossSellGenerator
{
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    /** Minimum support threshold (fraction of total baskets) */
    private const MIN_SUPPORT = 0.01;

    /** Minimum confidence threshold */
    private const MIN_CONFIDENCE = 0.1;

    /** Minimum lift threshold */
    private const MIN_LIFT = 1.0;

    /** Maximum rules to keep */
    private const MAX_RULES = 500;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Generate cross-sell association rules using co-occurrence analysis
     *
     * Simplified Market Basket Analysis:
     * - For each pair of products bought together in the same order,
     *   calculate support, confidence and lift.
     *
     * @param array $orders Raw order rows from ERP
     * @return int Number of rules generated
     */
    public function generate(array $orders): int
    {
        if (empty($orders)) {
            return 0;
        }

        // Group items by order (basket)
        $baskets = [];
        foreach ($orders as $row) {
            $oid = $row['order_id'];
            $pid = $row['product_code'];
            $baskets[$oid][$pid] = true;
        }

        $totalBaskets = count($baskets);
        if ($totalBaskets < 10) {
            $this->logger->warning('[RexisML Cross-sell] Too few baskets for analysis: ' . $totalBaskets);
            return 0;
        }

        $this->logger->info("[RexisML Cross-sell] Analyzing {$totalBaskets} baskets");

        // Count single item frequency
        $itemFreq = [];
        foreach ($baskets as $items) {
            foreach (array_keys($items) as $pid) {
                $itemFreq[$pid] = ($itemFreq[$pid] ?? 0) + 1;
            }
        }

        // Filter out very rare items (less than 0.5% of baskets)
        $minItemCount = max(2, (int)($totalBaskets * 0.005));
        $itemFreq = array_filter($itemFreq, fn($c) => $c >= $minItemCount);

        // Count co-occurrences (pairs)
        $pairFreq = [];
        foreach ($baskets as $items) {
            $products = array_keys(array_intersect_key($items, $itemFreq));
            $n = count($products);
            // Limit to baskets with reasonable size
            if ($n < 2 || $n > 50) {
                continue;
            }
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $products[$i];
                    $b = $products[$j];
                    // Ensure consistent key ordering
                    if ($a > $b) {
                        [$a, $b] = [$b, $a];
                    }
                    $key = "{$a}|{$b}";
                    $pairFreq[$key] = ($pairFreq[$key] ?? 0) + 1;
                }
            }
        }

        // Generate rules: A → B and B → A
        $rules = [];
        foreach ($pairFreq as $key => $coCount) {
            [$a, $b] = explode('|', $key);

            $supportAB = $coCount / $totalBaskets;
            if ($supportAB < self::MIN_SUPPORT) {
                continue;
            }

            // Rule A → B
            $confAB = $coCount / $itemFreq[$a];
            $liftAB = $confAB / ($itemFreq[$b] / $totalBaskets);

            if ($confAB >= self::MIN_CONFIDENCE && $liftAB >= self::MIN_LIFT) {
                $convictionAB = $liftAB > 0 ? (1 - ($itemFreq[$b] / $totalBaskets)) / max(0.001, 1 - $confAB) : 999;
                $leverageAB = $supportAB - ($itemFreq[$a] / $totalBaskets) * ($itemFreq[$b] / $totalBaskets);

                $rules[] = [
                    'antecedent'  => (string)$a,
                    'consequent'  => (string)$b,
                    'support'     => round($supportAB, 6),
                    'confidence'  => round($confAB, 6),
                    'lift'        => round($liftAB, 4),
                    'conviction'  => round(min($convictionAB, 999), 4),
                    'leverage'    => round($leverageAB, 6),
                ];
            }

            // Rule B → A
            $confBA = $coCount / $itemFreq[$b];
            $liftBA = $confBA / ($itemFreq[$a] / $totalBaskets);

            if ($confBA >= self::MIN_CONFIDENCE && $liftBA >= self::MIN_LIFT) {
                $convictionBA = $liftBA > 0 ? (1 - ($itemFreq[$a] / $totalBaskets)) / max(0.001, 1 - $confBA) : 999;
                $leverageBA = $supportAB - ($itemFreq[$b] / $totalBaskets) * ($itemFreq[$a] / $totalBaskets);

                $rules[] = [
                    'antecedent'  => (string)$b,
                    'consequent'  => (string)$a,
                    'support'     => round($supportAB, 6),
                    'confidence'  => round($confBA, 6),
                    'lift'        => round($liftBA, 4),
                    'conviction'  => round(min($convictionBA, 999), 4),
                    'leverage'    => round($leverageBA, 6),
                ];
            }
        }

        // Sort by lift desc and limit
        usort($rules, fn($a, $b) => $b['lift'] <=> $a['lift']);
        $rules = array_slice($rules, 0, self::MAX_RULES);

        // Write to DB
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('rexis_network_rules');

        // Clear old rules
        $connection->truncateTable($table);

        $count = 0;
        foreach ($rules as $rule) {
            $connection->insertOnDuplicate($table, [
                'antecedent'  => $rule['antecedent'],
                'consequent'  => $rule['consequent'],
                'support'     => $rule['support'],
                'confidence'  => $rule['confidence'],
                'lift'        => $rule['lift'],
                'conviction'  => $rule['conviction'],
                'leverage'    => $rule['leverage'],
                'is_active'   => 1,
            ], ['support', 'confidence', 'lift', 'conviction', 'leverage', 'updated_at']);
            $count++;
        }

        $this->logger->info("[RexisML Cross-sell] Generated {$count} association rules");
        return $count;
    }
}
