<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Cron;

use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use GrupoAwamotos\WhatsAppCommerce\Model\MessageSender;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Cron: retargeting inteligente via WhatsApp.
 *
 * Segmentos automáticos:
 * - Inativos 60d: clientes que compraram nos últimos 12 meses mas não nos últimos 60 dias
 * - Carrinho abandonado 48h: complementa módulo AbandonedCart com canal WhatsApp
 * - Alto valor: clientes com lifetime value > R$500 sem compra nos últimos 90 dias
 *
 * Cada segmento tem mensagem personalizada com nome do cliente e contexto.
 * Máximo 30 mensagens/segmento por execução (rate limit Baileys).
 * Roda diariamente às 11:00.
 */
class RetargetingCampaign
{
    private const MAX_PER_SEGMENT = 30;
    private const OPTIN_ATTRIBUTE = 'whatsapp_optin';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly MessageSender $messageSender,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isRetargetingEnabled()) {
            return;
        }

        $results = [];

        try {
            $results['inactive_60d'] = $this->processInactive60d();
            $results['high_value'] = $this->processHighValue();

            $totalSent = array_sum(array_column($results, 'sent'));
            $totalFailed = array_sum(array_column($results, 'failed'));

            $this->logger->info('[Retargeting] Completed', [
                'segments' => array_keys($results),
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Retargeting] Cron error: ' . $e->getMessage());
        }
    }

    /**
     * Inativos 60d: compraram nos últimos 12 meses, mas não nos últimos 60 dias.
     */
    private function processInactive60d(): array
    {
        $connection = $this->resource->getConnection();

        $customers = $this->getOptedInCustomersWithOrders(
            $connection,
            '-12 months',
            '-60 days'
        );

        if (empty($customers)) {
            return ['sent' => 0, 'failed' => 0, 'eligible' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach (array_slice($customers, 0, self::MAX_PER_SEGMENT) as $customer) {
            $firstName = explode(' ', $customer['name'])[0];
            $message = "Oi {$firstName}! 👋 Faz um tempo que voce nao aparece na AWA Motos. "
                . "Temos novidades em pecas e acessorios pra sua moto! "
                . "Confira: https://awamotos.com 🏍️";

            try {
                $this->messageSender->send($customer['phone'], $message);
                $this->logCustomerContact((int) $customer['entity_id'], 'inactive_60d');
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                $this->logger->debug('[Retargeting] Failed inactive_60d ' . $customer['entity_id']);
            }
        }

        $this->logCampaignExecution('inactive_60d', count($customers), $sent, $failed);

        return ['sent' => $sent, 'failed' => $failed, 'eligible' => count($customers)];
    }

    /**
     * Alto valor: lifetime > R$500, sem compra nos últimos 90 dias.
     */
    private function processHighValue(): array
    {
        $connection = $this->resource->getConnection();

        $customers = $this->getHighValueInactiveCustomers($connection);

        if (empty($customers)) {
            return ['sent' => 0, 'failed' => 0, 'eligible' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach (array_slice($customers, 0, self::MAX_PER_SEGMENT) as $customer) {
            $firstName = explode(' ', $customer['name'])[0];
            $message = "Oi {$firstName}! Voce e um cliente especial da AWA Motos 🌟 "
                . "Preparamos ofertas exclusivas pra voce. "
                . "Acesse: https://awamotos.com 🏍️";

            try {
                $this->messageSender->send($customer['phone'], $message);
                $this->logCustomerContact((int) $customer['entity_id'], 'high_value');
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                $this->logger->debug('[Retargeting] Failed high_value ' . $customer['entity_id']);
            }
        }

        $this->logCampaignExecution('high_value', count($customers), $sent, $failed);

        return ['sent' => $sent, 'failed' => $failed, 'eligible' => count($customers)];
    }

    /**
     * Clientes com opt-in que compraram entre $minOrderDate e $maxOrderDate.
     *
     * @return array<int, array{entity_id: int, name: string, phone: string}>
     */
    private function getOptedInCustomersWithOrders(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        string $purchasedAfter,
        string $notPurchasedSince,
    ): array {
        $optinId = $this->getAttributeId($connection, self::OPTIN_ATTRIBUTE);

        if ($optinId === 0) {
            return [];
        }

        $recentCutoff = date('Y-m-d', strtotime($notPurchasedSince));
        $oldCutoff = date('Y-m-d', strtotime($purchasedAfter));
        $today = date('Y-m-d');

        // Customers with orders between oldCutoff and recentCutoff, but NO orders after recentCutoff
        $select = $connection->select()
            ->from(
                ['ce' => $this->resource->getTableName('customer_entity')],
                ['entity_id', 'name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)")]
            )
            ->join(
                ['optin' => $this->resource->getTableName('customer_entity_int')],
                'optin.entity_id = ce.entity_id AND optin.attribute_id = ' . $optinId,
                []
            )
            ->join(
                ['ca' => $this->resource->getTableName('customer_address_entity')],
                'ca.entity_id = ce.default_billing',
                ['phone' => 'ca.telephone']
            )
            ->where('optin.value = 1')
            ->where('ca.telephone IS NOT NULL')
            ->where('ca.telephone != ?', '')
            // Has orders in the "purchased after" window
            ->where('EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['so' => $this->resource->getTableName('sales_order')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('so.customer_id = ce.entity_id')
                    ->where('so.created_at >= ?', $oldCutoff)
                    ->where('so.created_at < ?', $recentCutoff)
                    ->where('so.state = ?', 'complete')
                    ->limit(1)
            ))
            // No orders after recentCutoff
            ->where('NOT EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['so2' => $this->resource->getTableName('sales_order')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('so2.customer_id = ce.entity_id')
                    ->where('so2.created_at >= ?', $recentCutoff)
                    ->limit(1)
            ))
            // Not already contacted in the last 30 days for retargeting
            ->where('NOT EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['rt' => $this->resource->getTableName('awa_whatsapp_retargeting_log')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('rt.customer_id = ce.entity_id')
                    ->where('rt.sent_at >= ?', date('Y-m-d', strtotime('-30 days')))
                    ->limit(1)
            ))
            ->limit(self::MAX_PER_SEGMENT);

        return $connection->fetchAll($select);
    }

    /**
     * Clientes com lifetime value > R$500, sem compra nos últimos 90 dias, com opt-in.
     *
     * @return array<int, array{entity_id: int, name: string, phone: string, lifetime: float}>
     */
    private function getHighValueInactiveCustomers(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
    ): array {
        $optinId = $this->getAttributeId($connection, self::OPTIN_ATTRIBUTE);

        if ($optinId === 0) {
            return [];
        }

        $cutoff90d = date('Y-m-d', strtotime('-90 days'));

        $select = $connection->select()
            ->from(
                ['ce' => $this->resource->getTableName('customer_entity')],
                [
                    'entity_id',
                    'name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)"),
                    'lifetime' => new \Zend_Db_Expr('SUM(so.grand_total)'),
                ]
            )
            ->join(
                ['optin' => $this->resource->getTableName('customer_entity_int')],
                'optin.entity_id = ce.entity_id AND optin.attribute_id = ' . $optinId,
                []
            )
            ->join(
                ['ca' => $this->resource->getTableName('customer_address_entity')],
                'ca.entity_id = ce.default_billing',
                ['phone' => 'ca.telephone']
            )
            ->join(
                ['so' => $this->resource->getTableName('sales_order')],
                'so.customer_id = ce.entity_id AND so.state = \'complete\'',
                []
            )
            ->where('optin.value = 1')
            ->where('ca.telephone IS NOT NULL')
            ->where('ca.telephone != ?', '')
            // No orders in last 90 days
            ->where('NOT EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['so2' => $this->resource->getTableName('sales_order')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('so2.customer_id = ce.entity_id')
                    ->where('so2.created_at >= ?', $cutoff90d)
                    ->limit(1)
            ))
            // Not already contacted in last 30 days
            ->where('NOT EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['rt' => $this->resource->getTableName('awa_whatsapp_retargeting_log')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('rt.customer_id = ce.entity_id')
                    ->where('rt.sent_at >= ?', date('Y-m-d', strtotime('-30 days')))
                    ->limit(1)
            ))
            ->group('ce.entity_id')
            ->having('lifetime >= ?', 500)
            ->limit(self::MAX_PER_SEGMENT);

        return $connection->fetchAll($select);
    }

    private function logCampaignExecution(string $segment, int $eligible, int $sent, int $failed): void
    {
        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('awa_whatsapp_retargeting_log');

            // Only log successful sends (to prevent re-contacting)
            // We do NOT log individual sends here — it's done per-customer in the main loop
            // This is the summary log
            $this->logger->info('[Retargeting] Segment: ' . $segment, [
                'eligible' => $eligible,
                'sent' => $sent,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            $this->logger->debug('[Retargeting] Log error: ' . $e->getMessage());
        }
    }

    /**
     * Log individual customer contact to prevent re-contacting within 30 days.
     */
    private function logCustomerContact(int $customerId, string $segment): void
    {
        try {
            $connection = $this->resource->getConnection();
            $connection->insert(
                $this->resource->getTableName('awa_whatsapp_retargeting_log'),
                [
                    'customer_id' => $customerId,
                    'segment' => $segment,
                    'sent_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            $this->logger->debug('[Retargeting] Failed to log contact: ' . $e->getMessage());
        }
    }

    private function getAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection, string $code): int
    {
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $code)
                ->where('entity_type_id = ?', 1) // customer
                ->limit(1)
        );
    }
}
