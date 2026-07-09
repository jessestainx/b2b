<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\AdminDashboardInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

class WhatsAppAdminDashboard implements AdminDashboardInterface
{
    /**
     * Cache de request para evitar repetição de trabalho no mesmo ciclo.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $requestCache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function salesToday(): array
    {
        $cacheKey = 'sales_today_' . date('Ymd');

        /** @var array<string, mixed> */
        return $this->remember($cacheKey, function (): array {
            try {
                $connection = $this->resource->getConnection();
                $table = $this->resource->getTableName('sales_order');
                $today = date('Y-m-d');
                $todayStart = $today . ' 00:00:00';
                $tomorrowStart = date('Y-m-d 00:00:00', strtotime('+1 day'));
                $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));

                $sql = $connection->select()
                ->from($table, [
                    'today_orders' => new \Zend_Db_Expr(
                        sprintf("SUM(CASE WHEN created_at >= '%s' AND created_at < '%s' THEN 1 ELSE 0 END)", $todayStart, $tomorrowStart)
                    ),
                    'today_revenue' => new \Zend_Db_Expr(
                        sprintf("COALESCE(SUM(CASE WHEN created_at >= '%s' AND created_at < '%s' THEN grand_total ELSE 0 END), 0)", $todayStart, $tomorrowStart)
                    ),
                    'today_avg_ticket' => new \Zend_Db_Expr(
                        sprintf("COALESCE(AVG(CASE WHEN created_at >= '%s' AND created_at < '%s' THEN grand_total END), 0)", $todayStart, $tomorrowStart)
                    ),
                    'today_items' => new \Zend_Db_Expr(
                        sprintf("COALESCE(SUM(CASE WHEN created_at >= '%s' AND created_at < '%s' THEN total_qty_ordered ELSE 0 END), 0)", $todayStart, $tomorrowStart)
                    ),
                    'yesterday_orders' => new \Zend_Db_Expr(
                        sprintf("SUM(CASE WHEN created_at >= '%s' AND created_at < '%s' THEN 1 ELSE 0 END)", $yesterdayStart, $todayStart)
                    ),
                    'yesterday_revenue' => new \Zend_Db_Expr(
                        sprintf("COALESCE(SUM(CASE WHEN created_at >= '%s' AND created_at < '%s' THEN grand_total ELSE 0 END), 0)", $yesterdayStart, $todayStart)
                    ),
                ])
                    // Evita DATE(created_at) para preservar índice em created_at.
                    ->where('created_at >= ?', $yesterdayStart)
                    ->where('created_at < ?', $tomorrowStart)
                    ->where('state NOT IN (?)', ['canceled', 'closed']);

                $row = $connection->fetchRow($sql);

                return [
                    'date' => $today,
                    'orders' => (int) ($row['today_orders'] ?? 0),
                    'revenue' => round((float) ($row['today_revenue'] ?? 0), 2),
                    'avg_ticket' => round((float) ($row['today_avg_ticket'] ?? 0), 2),
                    'items_sold' => (int) ($row['today_items'] ?? 0),
                    'yesterday_orders' => (int) ($row['yesterday_orders'] ?? 0),
                    'yesterday_revenue' => round((float) ($row['yesterday_revenue'] ?? 0), 2),
                    'message' => sprintf(
                        "📊 Vendas hoje (%s): %d pedidos | R$ %s | Ticket: R$ %s",
                        date('d/m'),
                        (int) ($row['today_orders'] ?? 0),
                        number_format((float) ($row['today_revenue'] ?? 0), 2, ',', '.'),
                        number_format((float) ($row['today_avg_ticket'] ?? 0), 2, ',', '.')
                    ),
                ];
            } catch (\Exception $e) {
                $this->logger->error('AdminDashboard::salesToday error', ['error' => $e->getMessage()]);
                return ['error' => true, 'message' => 'Erro ao consultar vendas.'];
            }
        });
    }

    public function stockCheck(string $sku): array
    {
        $normalizedSku = trim($sku);
        if ($normalizedSku === '') {
            return ['error' => true, 'message' => 'SKU inválido.'];
        }

        $cacheKey = 'stock_check_' . sha1($normalizedSku);

        /** @var array<string, mixed> */
        return $this->remember($cacheKey, function () use ($normalizedSku): array {
            try {
                $product = $this->productRepository->get($normalizedSku);
                $stockItem = $this->stockRegistry->getStockItemBySku($normalizedSku);

                return [
                'sku' => $normalizedSku,
                'name' => $product->getName(),
                'qty' => (int) $stockItem->getQty(),
                'is_in_stock' => $stockItem->getIsInStock(),
                'min_qty' => (int) $stockItem->getMinQty(),
                'price' => (float) $product->getPrice(),
                'message' => sprintf(
                    "📦 %s (SKU: %s): %d un em estoque%s | R$ %s",
                    $product->getName(),
                    $normalizedSku,
                    (int) $stockItem->getQty(),
                    $stockItem->getIsInStock() ? '' : ' ⚠️ FORA DE ESTOQUE',
                    number_format((float) $product->getPrice(), 2, ',', '.')
                ),
                ];
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                return ['error' => true, 'message' => "Produto SKU '{$normalizedSku}' não encontrado."];
            } catch (\Exception $e) {
                $this->logger->error('AdminDashboard::stockCheck error', ['sku' => $normalizedSku, 'error' => $e->getMessage()]);
                return ['error' => true, 'message' => 'Erro ao consultar estoque.'];
            }
        });
    }

    public function newCustomers(): array
    {
        $cacheKey = 'new_customers_' . date('YmdH');

        /** @var array<string, mixed> */
        return $this->remember($cacheKey, function (): array {
            try {
                $connection = $this->resource->getConnection();
                $table = $this->resource->getTableName('customer_entity');

                $sql = $connection->select()
                ->from($table, [
                    'last_30d' => new \Zend_Db_Expr('COUNT(*)'),
                    'last_7d' => new \Zend_Db_Expr(
                        'SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END)'
                    ),
                    'last_24h' => new \Zend_Db_Expr(
                        'SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END)'
                    ),
                ])
                    // Reduz conjunto lido para preservar I/O sem perder resultado.
                    ->where('created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
                $counts = $connection->fetchRow($sql) ?: [];

                $last24h = (int) ($counts['last_24h'] ?? 0);
                $last7d = (int) ($counts['last_7d'] ?? 0);
                $last30d = (int) ($counts['last_30d'] ?? 0);

                return [
                    'last_24h' => $last24h,
                    'last_7d' => $last7d,
                    'last_30d' => $last30d,
                    'message' => sprintf(
                        "👥 Novos clientes: %d (24h) | %d (7d) | %d (30d)",
                        $last24h,
                        $last7d,
                        $last30d
                    ),
                ];
            } catch (\Exception $e) {
                $this->logger->error('AdminDashboard::newCustomers error', ['error' => $e->getMessage()]);
                return ['error' => true, 'message' => 'Erro ao consultar clientes.'];
            }
        });
    }

    public function orderDetail(string $incrementId): array
    {
        try {
            $this->searchCriteriaBuilder->addFilter('increment_id', $incrementId);
            $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)->create();
            $orders = $this->orderRepository->getList($searchCriteria);

            if ($orders->getTotalCount() === 0) {
                return ['error' => true, 'message' => "Pedido #{$incrementId} não encontrado."];
            }

            $order = current($orders->getItems());
            $items = [];
            foreach ($order->getItems() as $item) {
                if ($item->getParentItemId()) {
                    continue;
                }
                $items[] = sprintf("%dx %s", (int) $item->getQtyOrdered(), $item->getName());
            }

            $trackings = [];
            foreach ($order->getShipmentsCollection() as $shipment) {
                foreach ($shipment->getAllTracks() as $track) {
                    $trackings[] = $track->getTrackNumber();
                }
            }

            return [
                'increment_id' => $incrementId,
                'status' => $order->getStatusLabel(),
                'state' => $order->getState(),
                'grand_total' => round((float) $order->getGrandTotal(), 2),
                'customer_name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                'created_at' => $order->getCreatedAt(),
                'items' => $items,
                'tracking' => $trackings,
                'payment_method' => $order->getPayment()->getMethod(),
                'message' => sprintf(
                    "📋 Pedido #%s\nStatus: %s\nCliente: %s\nTotal: R$ %s\nItens: %s%s",
                    $incrementId,
                    $order->getStatusLabel(),
                    $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                    number_format((float) $order->getGrandTotal(), 2, ',', '.'),
                    implode(', ', $items),
                    $trackings ? "\nRastreio: " . implode(', ', $trackings) : ''
                ),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AdminDashboard::orderDetail error', ['order' => $incrementId, 'error' => $e->getMessage()]);
            return ['error' => true, 'message' => 'Erro ao consultar pedido.'];
        }
    }

    public function topSelling(int $days = 30): array
    {
        $days = max(1, $days);
        $cacheKey = 'top_selling_' . $days;

        /** @var array<string, mixed> */
        return $this->remember($cacheKey, function () use ($days): array {
            try {
                $connection = $this->resource->getConnection();
                $sql = $connection->select()
                ->from(
                    ['oi' => $this->resource->getTableName('sales_order_item')],
                    [
                        'sku' => 'oi.sku',
                        'name' => 'oi.name',
                        'total_qty' => new \Zend_Db_Expr('SUM(oi.qty_ordered)'),
                        'total_revenue' => new \Zend_Db_Expr('SUM(oi.row_total)'),
                    ]
                )
                    ->join(
                        ['o' => $this->resource->getTableName('sales_order')],
                        'oi.order_id = o.entity_id',
                        []
                    )
                    ->where('o.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', $days)
                    ->where('o.state NOT IN (?)', ['canceled', 'closed'])
                    ->where('oi.parent_item_id IS NULL')
                    ->group('oi.sku')
                    ->order('total_qty DESC')
                    ->limit(10);

                $rows = $connection->fetchAll($sql);

                $products = [];
                $lines = [];
                foreach ($rows as $i => $row) {
                    $products[] = [
                        'sku' => $row['sku'],
                        'name' => $row['name'],
                        'qty' => (int) $row['total_qty'],
                        'revenue' => round((float) $row['total_revenue'], 2),
                    ];
                    $lines[] = sprintf(
                        "%d. %s — %d un (R$ %s)",
                        $i + 1,
                        $row['name'],
                        (int) $row['total_qty'],
                        number_format((float) $row['total_revenue'], 2, ',', '.')
                    );
                }

                return [
                'period_days' => $days,
                'products' => $products,
                'message' => "🏆 Top 10 mais vendidos ({$days}d):\n" . implode("\n", $lines),
                ];
            } catch (\Exception $e) {
                $this->logger->error('AdminDashboard::topSelling error', ['error' => $e->getMessage()]);
                return ['error' => true, 'message' => 'Erro ao consultar mais vendidos.'];
            }
        });
    }

    /**
     * @param callable():array<string, mixed> $resolver
     * @return array<string, mixed>
     */
    private function remember(string $cacheKey, callable $resolver): array
    {
        if (isset($this->requestCache[$cacheKey])) {
            return $this->requestCache[$cacheKey];
        }

        $data = $resolver();
        $this->requestCache[$cacheKey] = $data;

        return $this->requestCache[$cacheKey];
    }
}
