<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Purchase History Model
 *
 * Retrieves customer purchase history from ERP SQL Server
 */
class PurchaseHistory
{
    private const CACHE_PREFIX = 'erp_purchase_history_';
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_TTL_CNPJ_NEGATIVE = 900; // 15 minutes — shorter so new ERP mappings surface reasonably fast
    private const CACHE_TTL_ORDERS_LIST = 120; // 2 minutes — short-lived so status changes still surface quickly

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
     * Get customer code from ERP by CNPJ
     */
    public function getCustomerCodeByCnpj(string $cnpj): ?int
    {
        $cleanCnpj = preg_replace('/[^0-9]/', '', $cnpj);
        $cacheKey = self::CACHE_PREFIX . 'cnpj_code_' . $cleanCnpj;

        // PERF-2026-07-04: esta query usa REPLACE() aninhado na cláusula WHERE
        // (FN_FORNECEDORES.CGC), o que impede o uso de índice no ERP e força
        // varredura completa da tabela a cada chamada (~1.8s medido em produção).
        // Sem cache, todo cliente sem código ERP mapeado paga esse custo em TODA
        // página que renderiza o dashboard/recomendações B2B (Suggestions block
        // é usado como provedor de dados via chamadas diretas de método, não via
        // toHtml(), então o cache de bloco declarado em Suggestions::getCacheLifetime()
        // nunca chega a proteger esta chamada). Cache positivo e negativo separados
        // para não esconder por muito tempo um mapeamento novo feito pelo sync ERP.
        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            return $decoded['code'] ?? null;
        }

        try {
            $customer = $this->connection->fetchOne("
                SELECT CODIGO
                FROM FN_FORNECEDORES
                WHERE REPLACE(REPLACE(REPLACE(CGC, '.', ''), '-', ''), '/', '') = ?
                AND CKCLIENTE = 'S'
            ", [$cleanCnpj]);

            $code = $customer ? (int)$customer['CODIGO'] : null;
            $ttl = $code !== null ? self::CACHE_TTL : self::CACHE_TTL_CNPJ_NEGATIVE;
            $this->cache->save(json_encode(['code' => $code]), $cacheKey, [], $ttl);

            return $code;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting customer code: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get customer info from ERP
     */
    public function getCustomerInfo(int $customerCode): ?array
    {
        $cacheKey = self::CACHE_PREFIX . 'customer_' . $customerCode;
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $customer = $this->connection->fetchOne("
                SELECT
                    CODIGO,
                    RAZAO,
                    FANTASIA,
                    CGC,
                    CIDADE,
                    UF,
                    ULTIMACOMPRA
                FROM FN_FORNECEDORES
                WHERE CODIGO = ?
                AND CKCLIENTE = 'S'
            ", [$customerCode]);

            if ($customer) {
                $this->cache->save(json_encode($customer), $cacheKey, [], self::CACHE_TTL);
            }

            return $customer;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting customer info: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get customer purchase summary
     */
    public function getCustomerSummary(int $customerCode): array
    {
        $cacheKey = self::CACHE_PREFIX . 'summary_' . $customerCode;
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $summary = $this->connection->fetchOne("
                SELECT
                    COUNT(DISTINCT p.CODIGO) as total_pedidos,
                    SUM(i.VLRTOTAL) as valor_total,
                    MIN(p.DTPEDIDO) as primeira_compra,
                    MAX(p.DTPEDIDO) as ultima_compra,
                    COUNT(DISTINCT i.MATERIAL) as produtos_diferentes
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
            ", [$customerCode]);

            $result = [
                'total_pedidos' => (int)($summary['total_pedidos'] ?? 0),
                'valor_total' => (float)($summary['valor_total'] ?? 0),
                'primeira_compra' => $summary['primeira_compra'] ?? null,
                'ultima_compra' => $summary['ultima_compra'] ?? null,
                'produtos_diferentes' => (int)($summary['produtos_diferentes'] ?? 0),
            ];

            $this->cache->save(json_encode($result), $cacheKey, [], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting customer summary: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get last orders for a customer
     */
    public function getLastOrders(int $customerCode, int $limit = 10): array
    {
        $cacheKey = self::CACHE_PREFIX . 'orders_' . $customerCode . '_' . $limit;
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $orders = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    p.CODIGO as pedido_id,
                    p.DTPEDIDO as data_pedido,
                    p.STATUS as status,
                    p.VLRTOTAL as valor_total,
                    COUNT(i.CODIGO) as qtd_itens
                FROM VE_PEDIDO p
                LEFT JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.CLIENTE = ?
                GROUP BY p.CODIGO, p.DTPEDIDO, p.STATUS, p.VLRTOTAL
                ORDER BY p.DTPEDIDO DESC
            ", [$customerCode]);
            $this->cache->save(json_encode($orders), $cacheKey, [], self::CACHE_TTL);
            return $orders;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting last orders: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get most purchased products by a customer
     */
    public function getMostPurchasedProducts(int $customerCode, int $limit = 20): array
    {
        $cacheKey = self::CACHE_PREFIX . 'products_' . $customerCode;
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $products = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    i.MATERIAL as codigo_material,
                    i.DESCRICAO as descricao,
                    COUNT(DISTINCT i.PEDIDO) as vezes_comprado,
                    SUM(i.QTDE) as quantidade_total,
                    AVG(i.VLRUNITARIO) as preco_medio,
                    MAX(p.DTPEDIDO) as ultima_compra
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
                GROUP BY i.MATERIAL, i.DESCRICAO
                ORDER BY SUM(i.QTDE) DESC
            ", [$customerCode]);

            $this->cache->save(json_encode($products), $cacheKey, [], self::CACHE_TTL);

            return $products;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting most purchased products: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get products purchased in last N days
     */
    public function getRecentlyPurchasedProducts(int $customerCode, int $days = 90): array
    {
        try {
            return $this->connection->query("
                SELECT
                    i.MATERIAL as codigo_material,
                    i.DESCRICAO as descricao,
                    SUM(i.QTDE) as quantidade,
                    MAX(p.DTPEDIDO) as data_compra
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
                AND p.DTPEDIDO >= DATEADD(day, CAST(? AS INT), GETDATE())
                GROUP BY i.MATERIAL, i.DESCRICAO
                ORDER BY MAX(p.DTPEDIDO) DESC
            ", [$customerCode, -(int)$days]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting recent products: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get purchase frequency analysis
     */
    public function getPurchaseFrequency(int $customerCode): array
    {
        $cacheKey = self::CACHE_PREFIX . 'frequency_' . $customerCode;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            // Get average days between purchases
            $frequency = $this->connection->fetchOne("
                WITH OrderDates AS (
                    SELECT
                        DTPEDIDO,
                        LAG(DTPEDIDO) OVER (ORDER BY DTPEDIDO) as prev_date
                    FROM VE_PEDIDO
                    WHERE CLIENTE = ?
                    AND STATUS NOT IN ('C', 'X')
                )
                SELECT
                    AVG(DATEDIFF(day, prev_date, DTPEDIDO)) as avg_days_between,
                    MIN(DATEDIFF(day, prev_date, DTPEDIDO)) as min_days,
                    MAX(DATEDIFF(day, prev_date, DTPEDIDO)) as max_days,
                    COUNT(*) as total_orders
                FROM OrderDates
                WHERE prev_date IS NOT NULL
            ", [$customerCode]);

            $result = [
                'avg_days_between_orders' => (int)($frequency['avg_days_between'] ?? 0),
                'min_days' => (int)($frequency['min_days'] ?? 0),
                'max_days' => (int)($frequency['max_days'] ?? 0),
                'total_orders' => (int)($frequency['total_orders'] ?? 0),
            ];
            $this->cache->save(json_encode($result), $cacheKey, [], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting purchase frequency: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monthly purchase trend for charts (last N months)
     *
     * @return array [{month: '2026-01', order_count: 5, revenue: 12500.00, product_count: 18}, ...]
     */
    public function getMonthlyTrend(int $customerCode, int $months = 12): array
    {
        $safeMonths = max(1, min(60, $months));
        // PERF-2026-07-04: join + GROUP BY sobre VE_PEDIDO/VE_PEDIDOITENS no ERP
        // remoto medido em ~1.7s. Chamado a cada carregamento do fragmento de
        // inteligência do dashboard B2B (via AJAX), sem cache — mesma classe de
        // problema do getCustomerCodeByCnpj. Segue o mesmo padrão de cache já
        // usado por getPurchaseFrequency()/getLastOrders() nesta classe.
        $cacheKey = self::CACHE_PREFIX . 'monthly_trend_' . $customerCode . '_' . $safeMonths;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $result = $this->connection->query("
                SELECT
                    FORMAT(p.DTPEDIDO, 'yyyy-MM') AS month,
                    COUNT(DISTINCT p.CODIGO) AS order_count,
                    COALESCE(SUM(i.VLRTOTAL), 0) AS revenue,
                    COUNT(DISTINCT i.MATERIAL) AS product_count
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.CLIENTE = ?
                  AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(MONTH, CAST(? AS INT), GETDATE())
                GROUP BY FORMAT(p.DTPEDIDO, 'yyyy-MM')
                ORDER BY FORMAT(p.DTPEDIDO, 'yyyy-MM') ASC
            ", [$customerCode, -$safeMonths]);

            $this->cache->save(json_encode($result), $cacheKey, [], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting monthly trend: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get filtered purchase history for a customer
     *
     * @param int $customerCode ERP customer code
     * @param array $filters Filters: period_days, min_freq, max_freq, min_price, max_price, sort_by, sort_dir, limit, offset
     * @return array {items: array, total_count: int}
     */
    public function getFilteredHistory(int $customerCode, array $filters = []): array
    {
        $periodDays = (int)($filters['period_days'] ?? 0);
        $minFreq = (int)($filters['min_freq'] ?? 0);
        $maxFreq = (int)($filters['max_freq'] ?? 0);
        $minPrice = (float)($filters['min_price'] ?? 0);
        $maxPrice = (float)($filters['max_price'] ?? 0);
        $sortBy = $filters['sort_by'] ?? 'days_since_last';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $limit = min(max((int)($filters['limit'] ?? 20), 1), 100);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        // Whitelist sort columns
        $sortColumns = [
            'days_since_last' => 'DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE())',
            'total_qty' => 'SUM(i.QTDE)',
            'avg_price' => 'AVG(i.VLRUNITARIO)',
            'order_count' => 'COUNT(DISTINCT i.PEDIDO)',
            'name' => 'MAX(i.DESCRICAO)',
        ];
        $orderByExpr = $sortColumns[$sortBy] ?? $sortColumns['days_since_last'];

        // Build dynamic WHERE and HAVING
        $params = [$customerCode];
        $whereClauses = [];
        $havingClauses = [];

        if ($periodDays > 0) {
            $whereClauses[] = "p.DTPEDIDO >= DATEADD(day, -{$periodDays}, GETDATE())";
        }

        if ($minFreq > 0) {
            $havingClauses[] = 'COUNT(DISTINCT i.PEDIDO) >= ?';
            $params[] = $minFreq;
        }
        if ($maxFreq > 0) {
            $havingClauses[] = 'COUNT(DISTINCT i.PEDIDO) <= ?';
            $params[] = $maxFreq;
        }
        if ($minPrice > 0) {
            $havingClauses[] = 'AVG(i.VLRUNITARIO) >= ?';
            $params[] = $minPrice;
        }
        if ($maxPrice > 0) {
            $havingClauses[] = 'AVG(i.VLRUNITARIO) <= ?';
            $params[] = $maxPrice;
        }

        $whereExtra = !empty($whereClauses) ? ' AND ' . implode(' AND ', $whereClauses) : '';
        $havingClause = !empty($havingClauses) ? ' HAVING ' . implode(' AND ', $havingClauses) : '';

        try {
            // Count query
            $countSql = "
                SELECT COUNT(*) as total FROM (
                    SELECT i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                    AND p.STATUS NOT IN ('C', 'X')
                    {$whereExtra}
                    GROUP BY i.MATERIAL
                    {$havingClause}
                ) sub
            ";
            $countResult = $this->connection->fetchOne($countSql, $params);
            $totalCount = (int)($countResult['total'] ?? 0);

            // Data query with pagination (OFFSET/FETCH NEXT inlined as integers for dblib compatibility)
            $safeOffset = (int)$offset;
            $safeLimit = (int)$limit;
            $dataSql = "
                SELECT
                    i.MATERIAL as sku,
                    MAX(i.DESCRICAO) as name,
                    COUNT(DISTINCT i.PEDIDO) as order_count,
                    SUM(i.QTDE) as total_qty,
                    AVG(i.VLRUNITARIO) as avg_price,
                    MAX(i.VLRUNITARIO) as max_price,
                    MAX(p.DTPEDIDO) as last_order_date,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) as days_since_last
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
                {$whereExtra}
                GROUP BY i.MATERIAL
                {$havingClause}
                ORDER BY {$orderByExpr} {$sortDir}
                OFFSET {$safeOffset} ROWS FETCH NEXT {$safeLimit} ROWS ONLY
            ";
            $items = $this->connection->query($dataSql, $params);

            return [
                'items' => $items,
                'total_count' => $totalCount,
            ];
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting filtered history: ' . $e->getMessage());
            return ['items' => [], 'total_count' => 0];
        }
    }

    /**
     * Clear cache for a customer
     */
    public function clearCustomerCache(int $customerCode): void
    {
        $this->cache->remove(self::CACHE_PREFIX . 'customer_' . $customerCode);
        $this->cache->remove(self::CACHE_PREFIX . 'summary_' . $customerCode);
        $this->cache->remove(self::CACHE_PREFIX . 'products_' . $customerCode);
        $this->cache->remove(self::CACHE_PREFIX . 'frequency_' . $customerCode);
        foreach ([5, 10, 20, 50, 100] as $limit) {
            $this->cache->remove(self::CACHE_PREFIX . 'orders_' . $customerCode . '_' . $limit);
        }
        foreach ([1, 3, 6, 12, 24, 36, 60] as $months) {
            $this->cache->remove(self::CACHE_PREFIX . 'monthly_trend_' . $customerCode . '_' . $months);
        }
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    public function getPaginatedOrders(int $customerCode, int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = min(max($pageSize, 1), 50);
        $offset = ($page - 1) * $pageSize;

        // PERF-2026-07-04: 2 round-trips ao ERP (COUNT + SELECT paginado) medidos
        // em 1-2.5s. TTL curto (2min) porque status de pedido muda com frequência,
        // mas ainda elimina o custo em cliques repetidos de paginação/refresh.
        $cacheKey = self::CACHE_PREFIX . 'paginated_' . $customerCode . '_' . $page . '_' . $pageSize;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: ['items' => [], 'total_count' => 0];
        }

        try {
            $countRow = $this->connection->fetchOne("
                SELECT COUNT(*) AS total
                FROM VE_PEDIDO
                WHERE CLIENTE = ?
            ", [$customerCode]);
            $total = (int) ($countRow['total'] ?? 0);

            $orders = $this->connection->query("
                SELECT
                    p.CODIGO AS pedido_id,
                    p.DTPEDIDO AS data_pedido,
                    p.STATUS AS status,
                    p.VLRTOTAL AS valor_total,
                    (SELECT COUNT(*) FROM VE_PEDIDOITENS i WHERE i.PEDIDO = p.CODIGO) AS qtd_itens
                FROM VE_PEDIDO p
                WHERE p.CLIENTE = ?
                ORDER BY p.DTPEDIDO DESC
                OFFSET {$offset} ROWS FETCH NEXT {$pageSize} ROWS ONLY
            ", [$customerCode]);

            $result = [
                'items' => $orders,
                'total_count' => $total,
            ];
            $this->cache->save(json_encode($result), $cacheKey, [], self::CACHE_TTL_ORDERS_LIST);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting paginated orders: ' . $e->getMessage());
            return ['items' => [], 'total_count' => 0];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderDetail(int $customerCode, int $erpOrderId): ?array
    {
        try {
            $order = $this->connection->fetchOne("
                SELECT
                    p.CODIGO AS pedido_id,
                    p.DTPEDIDO AS data_pedido,
                    p.STATUS AS status,
                    p.VLRTOTAL AS valor_total
                FROM VE_PEDIDO p
                WHERE p.CLIENTE = ?
                  AND p.CODIGO = ?
            ", [$customerCode, $erpOrderId]);

            if (!$order) {
                return null;
            }

            $items = $this->connection->query("
                SELECT
                    i.MATERIAL AS sku,
                    i.DESCRICAO AS nome,
                    i.QTDE AS qty,
                    i.VLRUNITARIO AS preco_unitario,
                    i.VLRTOTAL AS total
                FROM VE_PEDIDOITENS i
                WHERE i.PEDIDO = ?
                ORDER BY i.CODIGO ASC
            ", [$erpOrderId]);

            $order['items'] = $items;
            $order['timeline'] = $this->buildStatusTimeline((string) ($order['status'] ?? ''), (string) ($order['data_pedido'] ?? ''));

            return $order;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Error getting order detail: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return list<array{label: string, state: string, date: string}>
     */
    private function buildStatusTimeline(string $status, string $orderDate): array
    {
        $steps = [
            ['key' => 'created', 'label' => 'Pedido criado', 'states' => ['A', 'P', 'W', 'B', 'L', 'V', 'S', 'F', 'E']],
            ['key' => 'approved', 'label' => 'Liberado', 'states' => ['L', 'V', 'S', 'F', 'E']],
            ['key' => 'invoiced', 'label' => 'Faturado', 'states' => ['F', 'E']],
            ['key' => 'shipped', 'label' => 'Enviado', 'states' => ['S', 'E']],
            ['key' => 'delivered', 'label' => 'Entregue', 'states' => ['E']],
        ];

        $statusIndex = 0;
        foreach ($steps as $index => $step) {
            if (in_array($status, $step['states'], true)) {
                $statusIndex = $index;
            }
        }

        $timeline = [];
        foreach ($steps as $index => $step) {
            $state = 'pending';
            if ($index < $statusIndex) {
                $state = 'done';
            } elseif ($index === $statusIndex) {
                $state = in_array($status, ['C', 'X'], true) ? 'failed' : 'current';
            }

            $timeline[] = [
                'label' => $step['label'],
                'state' => $state,
                'date' => $index === 0 && $orderDate !== '' ? substr($orderDate, 0, 10) : '',
            ];
        }

        return $timeline;
    }
}
