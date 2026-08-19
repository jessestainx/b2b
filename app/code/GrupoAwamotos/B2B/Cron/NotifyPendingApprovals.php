<?php

/**
 * Cron job para alertar admin sobre clientes B2B com aprovação pendente há mais de N horas.
 * Roda diariamente às 8h. Envia um único e-mail consolidado.
 * Evita spam: só reenvia se o número de pendentes aumentou.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class NotifyPendingApprovals
{
    private const HOURS_THRESHOLD = 48;
    private const CACHE_PATH      = 'grupoawamotos_b2b/pending_alert/last_sent_count';
    private const TEMPLATE_ID     = 'grupoawamotos_b2b_admin_pending_approvals';

    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resource,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly AppState $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $adminEmail = $this->config->getAdminEmail();
        if (!$adminEmail) {
            return;
        }

        $pending = $this->getPendingCustomers();

        if (empty($pending)) {
            return;
        }

        $count = count($pending);
        $lastCount = $this->getLastSentCount();

        // Only alert if the pending count increased since last notification
        if ($count <= $lastCount) {
            return;
        }

        try {
            $store = $this->storeManager->getStore();
            $oldestDays = $this->getOldestDays($pending);

            $this->appState->emulateAreaCode(
                Area::AREA_FRONTEND,
                function () use ($store, $count, $oldestDays, $adminEmail): void {
                    $transport = $this->transportBuilder
                        ->setTemplateIdentifier(self::TEMPLATE_ID)
                        ->setTemplateOptions([
                            'area'  => Area::AREA_FRONTEND,
                            'store' => (int) $store->getId(),
                        ])
                        ->setTemplateVars([
                            'pending_count'   => $count,
                            'hours_threshold' => self::HOURS_THRESHOLD,
                            'oldest_days'     => $oldestDays,
                            'store_name'      => $store->getName(),
                            'approval_url'    => $store->getBaseUrl() . 'admin/customer/index',
                        ])
                        ->setFromByScope('general')
                        ->addTo($adminEmail)
                        ->getTransport();

                    $transport->sendMessage();
                }
            );

            $this->saveLastSentCount($count);

            $this->logger->info(sprintf(
                'B2B NotifyPendingApprovals: alert sent to %s for %d pending customers.',
                $adminEmail,
                $count
            ));
        } catch (\Exception $e) {
            $this->logger->error('B2B NotifyPendingApprovals error: ' . $e->getMessage());
        }
    }

    /**
     * Returns customers with pending B2B approval for more than HOURS_THRESHOLD hours.
     *
     * @return array<int, array{entity_id: int, email: string, created_at: string}>
     */
    private function getPendingCustomers(): array
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from(
                ['c' => $connection->getTableName('customer_entity')],
                ['entity_id', 'email', 'created_at']
            )
            ->joinInner(
                ['v' => $connection->getTableName('customer_entity_varchar')],
                'c.entity_id = v.entity_id',
                []
            )
            ->joinInner(
                ['a' => $connection->getTableName('eav_attribute')],
                "v.attribute_id = a.attribute_id AND a.attribute_code = 'b2b_approval_status'",
                []
            )
            ->where('v.value = ?', 'pending')
            ->where('c.created_at < ?', new \Zend_Db_Expr(
                'DATE_SUB(NOW(), INTERVAL ' . self::HOURS_THRESHOLD . ' HOUR)'
            ))
            ->order('c.created_at ASC')
            ->limit(500);

        return $connection->fetchAll($select);
    }

    /**
     * Returns the number of days the oldest pending customer has been waiting.
     *
     * @param array<int, array{created_at: string}> $pending
     */
    private function getOldestDays(array $pending): int
    {
        if (empty($pending)) {
            return 0;
        }

        return (int) round((time() - strtotime($pending[0]['created_at'])) / 86400);
    }

    private function getLastSentCount(): int
    {
        $connection = $this->resource->getConnection();

        $select = $connection->select()
            ->from($connection->getTableName('core_config_data'), ['value'])
            ->where('path = ?', self::CACHE_PATH)
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0);

        $value = $connection->fetchOne($select);

        return $value !== false ? (int) $value : 0;
    }

    private function saveLastSentCount(int $count): void
    {
        $connection = $this->resource->getConnection();

        $connection->insertOnDuplicate(
            $connection->getTableName('core_config_data'),
            [
                'scope'    => 'default',
                'scope_id' => 0,
                'path'     => self::CACHE_PATH,
                'value'    => (string) $count,
            ],
            ['value']
        );
    }
}
