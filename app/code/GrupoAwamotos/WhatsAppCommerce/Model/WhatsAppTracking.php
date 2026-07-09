<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\TrackingInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class WhatsAppTracking implements TrackingInterface
{
    private const MAX_ORDERS = 5;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getOrders(string $phone): array
    {
        $phone = $this->normalizePhone($phone);

        if (strlen($phone) < 8) {
            return ['orders' => [], 'message' => 'Telefone inválido'];
        }

        try {
            $connection = $this->resource->getConnection();

            // Search using last 8 digits, stripping non-digit chars via SQL REGEXP
            $lastDigits = substr($phone, -8);

            $sql = $connection->select()
                ->from(['o' => $this->resource->getTableName('sales_order')], [
                    'increment_id',
                    'created_at',
                    'status',
                    'grand_total',
                    'total_item_count',
                    'entity_id',
                ])
                ->joinLeft(
                    ['a' => $this->resource->getTableName('sales_order_address')],
                    'o.entity_id = a.parent_id AND a.address_type = \'billing\'',
                    []
                )
                ->where(
                    'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.telephone, \'(\', \'\'), \')\', \'\'), \'-\', \'\'), \' \', \'\'), \'+\', \'\') LIKE ?',
                    '%' . $lastDigits
                )
                ->order('o.created_at DESC')
                ->limit(self::MAX_ORDERS);

            $rows = $connection->fetchAll($sql);

            $orders = [];
            foreach ($rows as $row) {
                // Fetch tracking
                $trackSql = $connection->select()
                    ->from(['s' => $this->resource->getTableName('sales_shipment')], [])
                    ->joinLeft(
                        ['t' => $this->resource->getTableName('sales_shipment_track')],
                        's.entity_id = t.parent_id',
                        ['title', 'track_number']
                    )
                    ->where('s.order_id = ?', $row['entity_id']);

                $tracks = $connection->fetchAll($trackSql);
                $tracking = [];
                foreach ($tracks as $track) {
                    if (!empty($track['track_number'])) {
                        $tracking[] = [
                            'carrier' => $track['title'] ?? '',
                            'code' => $track['track_number'],
                        ];
                    }
                }

                $total = (float) $row['grand_total'];
                $orders[] = [
                    'order_id' => $row['increment_id'],
                    'date' => $row['created_at'],
                    'status' => $row['status'],
                    'total' => $total,
                    'total_formatted' => 'R$ ' . number_format($total, 2, ',', '.'),
                    'items_count' => (int) $row['total_item_count'],
                    'tracking' => $tracking,
                ];
            }

            if (empty($orders)) {
                return [
                    'orders' => [],
                    'message' => 'Nenhum pedido encontrado para este telefone',
                ];
            }

            return [
                'orders' => $orders,
                'message' => sprintf('Encontrei %d pedido(s) recente(s)', count($orders)),
            ];
        } catch (\Exception $e) {
            $this->logger->error('WhatsAppTracking::getOrders error', [
                'phone' => substr($phone, 0, 4) . '****',
                'error' => $e->getMessage(),
            ]);
            return ['orders' => [], 'error' => 'Erro ao buscar pedidos'];
        }
    }

    /**
     * Normalize phone number: remove non-digits, keep last 11 digits (BR format)
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) > 11 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
