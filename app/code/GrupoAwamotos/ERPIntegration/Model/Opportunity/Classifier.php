<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Opportunity;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Opportunity Classifier
 *
 * Classifies customer products into 8 opportunity categories based on
 * purchase behavior analysis from ERP SQL Server data.
 */
class Classifier
{
    private const CACHE_PREFIX = 'erp_opportunity_';
    private const CACHE_TTL = 3600; // 1 hour

    /** @var array<string, string> Opportunity type labels */
    public const TYPES = [
        'all'                  => 'Todas as Oportunidades',
        'monthly'              => 'Oportunidade Mensal',
        'quarterly_not_bought' => 'Trimestral (não comprou)',
        'quarterly_bought'     => 'Trimestral (já comprou)',
        'expansion_monthly'    => 'Expansão Mensal',
        'expansion_quarterly'  => 'Expansão Trimestral',
        'irregular'            => 'Oportunidade Irregular',
        'churn'                => 'Oportunidade Churn',
        'cross_sell'           => 'Cross-sell',
    ];

    /** @var array<string, string> Badge colors per type */
    private const BADGE_COLORS = [
        'monthly'              => '#2196F3',
        'quarterly_not_bought' => '#FF9800',
        'quarterly_bought'     => '#4CAF50',
        'expansion_monthly'    => '#9C27B0',
        'expansion_quarterly'  => '#673AB7',
        'irregular'            => '#607D8B',
        'churn'                => '#F44336',
        'cross_sell'           => '#00BCD4',
    ];

    /** @var array<string, string> Whitelist of sort columns */
    private const SORT_COLUMNS = [
        'days_since_last' => 'days_since_last',
        'total_qty'       => 'total_qty',
        'avg_price'       => 'avg_price',
        'order_count'     => 'order_count',
        'name'            => 'name',
    ];

    private ConnectionInterface $connection;
    private Helper $helper;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Classify customer products into opportunity categories
     *
     * @param int $customerCode ERP customer code
     * @param string $opportunityType Filter by type ('all' for all types)
     * @param array $filters {sort_by, sort_dir, limit, offset, min_price, max_price}
     * @return array {items: array, total_count: int}
     */
    public function classify(int $customerCode, string $opportunityType = 'all', array $filters = []): array
    {
        if (!isset(self::TYPES[$opportunityType])) {
            $opportunityType = 'all';
        }

        $cacheKey = $this->buildCacheKey($customerCode, $opportunityType, $filters);
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            if ($opportunityType === 'cross_sell') {
                $result = $this->getCrossSellOpportunities($customerCode, $filters);
            } else {
                $result = $this->getClassifiedProducts($customerCode, $opportunityType, $filters);
            }

            $this->cache->save(
                json_encode($result),
                $cacheKey,
                [self::CACHE_PREFIX . $customerCode],
                self::CACHE_TTL
            );
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Opportunity] Classification error: ' . $e->getMessage());
            return ['items' => [], 'total_count' => 0];
        }
    }

    /**
     * Get summary counts per opportunity type
     *
     * @return array<string, int> e.g. ['monthly' => 15, 'churn' => 8, ...]
     */
    public function getSummary(int $customerCode): array
    {
        $cacheKey = self::CACHE_PREFIX . 'summary_' . $customerCode;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $safeCode = (int)$customerCode;

        try {
            $cteSql = $this->buildClassificationCte($safeCode);
            $sql = "
                {$cteSql}
                SELECT opportunity_type, COUNT(*) AS cnt
                FROM Classified
                GROUP BY opportunity_type
            ";

            $rows = $this->connection->query($sql);
            $summary = [];
            foreach (self::TYPES as $key => $label) {
                if ($key !== 'all' && $key !== 'cross_sell') {
                    $summary[$key] = 0;
                }
            }
            foreach ($rows as $row) {
                $type = $row['opportunity_type'];
                if (isset($summary[$type])) {
                    $summary[$type] = (int)$row['cnt'];
                }
            }

            $this->cache->save(
                json_encode($summary),
                $cacheKey,
                [self::CACHE_PREFIX . $customerCode],
                self::CACHE_TTL
            );
            return $summary;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Opportunity] Summary error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Main classification query (categories 1-7)
     */
    private function getClassifiedProducts(int $customerCode, string $opportunityType, array $filters): array
    {
        $safeCode = (int)$customerCode;
        $sortBy = $filters['sort_by'] ?? 'days_since_last';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $limit = min(max((int)($filters['limit'] ?? 20), 1), 100);
        $offset = max((int)($filters['offset'] ?? 0), 0);
        $minPrice = (float)($filters['min_price'] ?? 0);
        $maxPrice = (float)($filters['max_price'] ?? 0);

        $orderByCol = self::SORT_COLUMNS[$sortBy] ?? 'days_since_last';

        // Build price HAVING clause for the outer query
        $priceFilter = '';
        if ($minPrice > 0) {
            $priceFilter .= ' AND avg_price >= ' . (float)$minPrice;
        }
        if ($maxPrice > 0) {
            $priceFilter .= ' AND avg_price <= ' . (float)$maxPrice;
        }

        $typeFilter = $opportunityType === 'all'
            ? ''
            : " AND opportunity_type = '" . $this->sanitizeType($opportunityType) . "'";

        $cteSql = $this->buildClassificationCte($safeCode);

        // Count query
        $countSql = "
            {$cteSql}
            SELECT COUNT(*) AS total
            FROM Classified
            WHERE 1=1 {$typeFilter} {$priceFilter}
        ";
        $countResult = $this->connection->fetchOne($countSql);
        $totalCount = (int)($countResult['total'] ?? 0);

        // Data query
        $safeOffset = (int)$offset;
        $safeLimit = (int)$limit;
        $dataSql = "
            {$cteSql}
            SELECT
                MATERIAL AS sku,
                DESCRICAO AS name,
                order_count,
                total_qty,
                avg_price,
                max_price,
                first_ever AS first_order_date,
                last_order AS last_order_date,
                days_since_last,
                months_12,
                quarters_4,
                bought_q AS bought_current_quarter,
                opportunity_type
            FROM Classified
            WHERE 1=1 {$typeFilter} {$priceFilter}
            ORDER BY {$orderByCol} {$sortDir}
            OFFSET {$safeOffset} ROWS FETCH NEXT {$safeLimit} ROWS ONLY
        ";
        $items = $this->connection->query($dataSql);

        return [
            'items' => $items,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Cross-sell query (category 8)
     *
     * Products bought by similar customers that this customer has never purchased.
     */
    private function getCrossSellOpportunities(int $customerCode, array $filters): array
    {
        $safeCode = (int)$customerCode;
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limit = min(max((int)($filters['limit'] ?? 20), 1), 100);
        $offset = max((int)($filters['offset'] ?? 0), 0);
        $minPrice = (float)($filters['min_price'] ?? 0);
        $maxPrice = (float)($filters['max_price'] ?? 0);

        $priceHaving = '';
        if ($minPrice > 0) {
            $priceHaving .= ' AND AVG(i2.VLRUNITARIO) >= ' . (float)$minPrice;
        }
        if ($maxPrice > 0) {
            $priceHaving .= ' AND AVG(i2.VLRUNITARIO) <= ' . (float)$maxPrice;
        }

        $safeOffset = (int)$offset;
        $safeLimit = (int)$limit;

        // Count query
        $countSql = "
            WITH
            TopProducts AS (
                SELECT TOP 10 i.MATERIAL
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
                GROUP BY i.MATERIAL
                ORDER BY SUM(i.QTDE) DESC
            ),
            SimilarCustomers AS (
                SELECT TOP 50 p.CLIENTE
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE i.MATERIAL IN (SELECT MATERIAL FROM TopProducts)
                  AND p.CLIENTE <> {$safeCode}
                  AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(YEAR, -2, GETDATE())
                GROUP BY p.CLIENTE
                HAVING COUNT(DISTINCT i.MATERIAL) >= 3
                ORDER BY COUNT(DISTINCT i.MATERIAL) DESC
            ),
            CustomerProducts AS (
                SELECT DISTINCT i.MATERIAL
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
            )
            SELECT COUNT(*) AS total FROM (
                SELECT i2.MATERIAL
                FROM VE_PEDIDOITENS i2
                INNER JOIN VE_PEDIDO p2 ON i2.PEDIDO = p2.CODIGO
                WHERE p2.CLIENTE IN (SELECT CLIENTE FROM SimilarCustomers)
                  AND i2.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerProducts)
                  AND p2.STATUS NOT IN ('C', 'X')
                  AND p2.DTPEDIDO >= DATEADD(YEAR, -1, GETDATE())
                GROUP BY i2.MATERIAL
                HAVING COUNT(DISTINCT p2.CLIENTE) >= 2 {$priceHaving}
            ) sub
        ";
        $countResult = $this->connection->fetchOne($countSql);
        $totalCount = (int)($countResult['total'] ?? 0);

        // Data query
        $dataSql = "
            WITH
            TopProducts AS (
                SELECT TOP 10 i.MATERIAL
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
                GROUP BY i.MATERIAL
                ORDER BY SUM(i.QTDE) DESC
            ),
            SimilarCustomers AS (
                SELECT TOP 50 p.CLIENTE
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE i.MATERIAL IN (SELECT MATERIAL FROM TopProducts)
                  AND p.CLIENTE <> {$safeCode}
                  AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(YEAR, -2, GETDATE())
                GROUP BY p.CLIENTE
                HAVING COUNT(DISTINCT i.MATERIAL) >= 3
                ORDER BY COUNT(DISTINCT i.MATERIAL) DESC
            ),
            CustomerProducts AS (
                SELECT DISTINCT i.MATERIAL
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
            )
            SELECT
                i2.MATERIAL AS sku,
                MAX(i2.DESCRICAO) AS name,
                COUNT(DISTINCT p2.CLIENTE) AS similar_customers_count,
                0 AS order_count,
                SUM(i2.QTDE) AS total_qty,
                AVG(i2.VLRUNITARIO) AS avg_price,
                MAX(i2.VLRUNITARIO) AS max_price,
                NULL AS last_order_date,
                0 AS days_since_last,
                'cross_sell' AS opportunity_type
            FROM VE_PEDIDOITENS i2
            INNER JOIN VE_PEDIDO p2 ON i2.PEDIDO = p2.CODIGO
            WHERE p2.CLIENTE IN (SELECT CLIENTE FROM SimilarCustomers)
              AND i2.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerProducts)
              AND p2.STATUS NOT IN ('C', 'X')
              AND p2.DTPEDIDO >= DATEADD(YEAR, -1, GETDATE())
            GROUP BY i2.MATERIAL
            HAVING COUNT(DISTINCT p2.CLIENTE) >= 2 {$priceHaving}
            ORDER BY COUNT(DISTINCT p2.CLIENTE) DESC, SUM(i2.QTDE) DESC
            OFFSET {$safeOffset} ROWS FETCH NEXT {$safeLimit} ROWS ONLY
        ";
        $items = $this->connection->query($dataSql);

        return [
            'items' => $items,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Build the common classification CTE SQL
     */
    private function buildClassificationCte(int $safeCode): string
    {
        return "
            WITH
            ProductHistory AS (
                SELECT
                    i.MATERIAL,
                    MAX(i.DESCRICAO) AS DESCRICAO,
                    COUNT(DISTINCT i.PEDIDO) AS order_count,
                    SUM(i.QTDE) AS total_qty,
                    AVG(i.VLRUNITARIO) AS avg_price,
                    MAX(i.VLRUNITARIO) AS max_price,
                    MIN(p.DTPEDIDO) AS first_ever,
                    MAX(p.DTPEDIDO) AS last_order,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) AS days_since_last
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
                GROUP BY i.MATERIAL
            ),
            MonthlyPresence AS (
                SELECT
                    i.MATERIAL,
                    COUNT(DISTINCT FORMAT(p.DTPEDIDO, 'yyyy-MM')) AS distinct_months
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(MONTH, -12, GETDATE())
                GROUP BY i.MATERIAL
            ),
            QuarterlyPresence AS (
                SELECT
                    i.MATERIAL,
                    COUNT(DISTINCT
                        CAST(DATEPART(YEAR, p.DTPEDIDO) AS VARCHAR) + '-Q'
                        + CAST(DATEPART(QUARTER, p.DTPEDIDO) AS VARCHAR)
                    ) AS distinct_quarters,
                    MAX(CASE
                        WHEN DATEPART(YEAR, p.DTPEDIDO) = DATEPART(YEAR, GETDATE())
                         AND DATEPART(QUARTER, p.DTPEDIDO) = DATEPART(QUARTER, GETDATE())
                        THEN 1 ELSE 0
                    END) AS bought_current_quarter
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = {$safeCode} AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(QUARTER, -4, GETDATE())
                GROUP BY i.MATERIAL
            ),
            Classified AS (
                SELECT
                    ph.MATERIAL,
                    ph.DESCRICAO,
                    ph.order_count,
                    ph.total_qty,
                    ph.avg_price,
                    ph.max_price,
                    ph.first_ever,
                    ph.last_order,
                    ph.days_since_last,
                    COALESCE(mp.distinct_months, 0) AS months_12,
                    COALESCE(qp.distinct_quarters, 0) AS quarters_4,
                    COALESCE(qp.bought_current_quarter, 0) AS bought_q,
                    CASE
                        WHEN ph.first_ever >= DATEADD(DAY, -30, GETDATE())
                        THEN 'expansion_monthly'
                        WHEN ph.first_ever >= DATEADD(DAY, -90, GETDATE())
                         AND ph.first_ever < DATEADD(DAY, -30, GETDATE())
                        THEN 'expansion_quarterly'
                        WHEN ph.days_since_last >= 121 THEN 'churn'
                        WHEN COALESCE(mp.distinct_months, 0) >= 7 THEN 'monthly'
                        WHEN COALESCE(qp.distinct_quarters, 0) >= 2
                         AND COALESCE(qp.bought_current_quarter, 0) = 0
                        THEN 'quarterly_not_bought'
                        WHEN COALESCE(qp.distinct_quarters, 0) >= 2
                         AND COALESCE(qp.bought_current_quarter, 0) = 1
                        THEN 'quarterly_bought'
                        WHEN ph.order_count >= 2 THEN 'irregular'
                        ELSE 'irregular'
                    END AS opportunity_type
                FROM ProductHistory ph
                LEFT JOIN MonthlyPresence mp ON ph.MATERIAL = mp.MATERIAL
                LEFT JOIN QuarterlyPresence qp ON ph.MATERIAL = qp.MATERIAL
            )
        ";
    }

    /**
     * Sanitize opportunity type value for safe SQL inclusion
     */
    private function sanitizeType(string $type): string
    {
        return isset(self::TYPES[$type]) && $type !== 'all' ? $type : 'all';
    }

    /**
     * Build cache key
     */
    private function buildCacheKey(int $customerCode, string $type, array $filters): string
    {
        return self::CACHE_PREFIX . $customerCode . '_' . $type . '_' . hash('xxh128', json_encode($filters));
    }

    /**
     * Get label for opportunity type
     */
    public static function getTypeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    /**
     * Get badge color for opportunity type
     */
    public static function getTypeBadgeColor(string $type): string
    {
        return self::BADGE_COLORS[$type] ?? '#9E9E9E';
    }

    /**
     * Clear all opportunity caches for a customer
     */
    public function clearCache(int $customerCode): void
    {
        // Remove summary cache
        $this->cache->remove(self::CACHE_PREFIX . 'summary_' . $customerCode);
        // Remove all filter combination caches via tag
        $this->cache->clean([self::CACHE_PREFIX . $customerCode]);
    }
}
