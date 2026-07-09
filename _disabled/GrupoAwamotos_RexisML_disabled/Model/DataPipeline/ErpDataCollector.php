<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\DataPipeline;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use Psr\Log\LoggerInterface;

class ErpDataCollector
{
    private ConnectionInterface $connection;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * Fetch orders with items from ERP (last N months)
     */
    public function fetchOrders(int $monthsBack = 24): array
    {
        $cutoffDate = (new \DateTime())
            ->modify("-{$monthsBack} months")
            ->format('Y-m-d');

        $this->logger->info("[RexisML] Fetching orders since {$cutoffDate}");

        return $this->connection->query("
            SELECT
                p.CODIGO AS order_id,
                p.CLIENTE AS customer_code,
                p.DTPEDIDO AS order_date,
                p.VLRTOTAL AS order_total,
                i.MATERIAL AS product_code,
                i.QTDE AS qty,
                i.VLRUNITARIO AS unit_price
            FROM VE_PEDIDO p
            INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
            WHERE p.STATUS NOT IN ('C', 'X')
              AND p.DTPEDIDO >= :cutoff
            ORDER BY p.DTPEDIDO DESC
        ", [':cutoff' => $cutoffDate]);
    }

    /**
     * Fetch customer master data from ERP
     */
    public function fetchCustomers(): array
    {
        $this->logger->info('[RexisML] Fetching customer master data');

        return $this->connection->query("
            SELECT
                f.CODIGO AS customer_code,
                f.RAZAO AS legal_name,
                f.FANTASIA AS trade_name,
                f.CGC AS cnpj,
                f.UF AS state
            FROM FN_FORNECEDORES f
            WHERE f.CKCLIENTE = 'S'
        ");
    }

    /**
     * Fetch product master data from ERP
     */
    public function fetchProducts(): array
    {
        $this->logger->info('[RexisML] Fetching product master data');

        return $this->connection->query("
            SELECT
                m.CODIGO AS product_code,
                m.DESCRICAO AS description,
                m.GRUPOCOMERCIAL AS category,
                COALESCE(p.VLRVENDA, 0) AS price
            FROM MT_MATERIAL m
            LEFT JOIN MT_COMPOSICAOPRECO p ON p.MATERIAL = m.CODIGO
            WHERE m.CCKATIVO = 'S'
        ");
    }
}
