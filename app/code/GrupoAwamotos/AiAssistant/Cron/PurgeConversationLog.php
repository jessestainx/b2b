<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Cron;

use GrupoAwamotos\AiAssistant\Helper\Config;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Paginated retention for conversation logs. Does not change schema.
 */
class PurgeConversationLog
{
    private const BATCH = 200;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_ai_conversation_log');
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->getTimestamp();

        $guestBefore = gmdate('Y-m-d H:i:s', $now - ($this->config->getRetentionGuestDays() * 86400));
        $customerBefore = gmdate('Y-m-d H:i:s', $now - ($this->config->getRetentionCustomerDays() * 86400));
        $errorBefore = gmdate('Y-m-d H:i:s', $now - ($this->config->getRetentionErrorDays() * 86400));

        $deletedGuests = $this->deletePaged(
            $connection,
            $table,
            ['customer_id IS NULL', 'created_at < ?'],
            [$guestBefore]
        );
        $anonymizedCustomers = $this->anonymizePaged($connection, $table, $customerBefore);
        $clearedErrors = $this->clearErrorDetailPaged($connection, $table, $errorBefore);

        $this->logger->info('[AiAssistant] Retention cron', [
            'deleted_guests' => $deletedGuests,
            'anonymized_customers' => $anonymizedCustomers,
            'cleared_errors' => $clearedErrors,
        ]);
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param list<string> $where
     * @param list<string> $bind
     */
    private function deletePaged($connection, string $table, array $where, array $bind): int
    {
        $total = 0;
        do {
            $select = $connection->select()
                ->from($table, ['log_id'])
                ->where($where[0])
                ->where($where[1], $bind[0])
                ->order('log_id ASC')
                ->limit(self::BATCH);
            $ids = $connection->fetchCol($select);
            if ($ids === []) {
                break;
            }
            $total += $connection->delete($table, ['log_id IN (?)' => $ids]);
        } while (count($ids) === self::BATCH);

        return $total;
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function anonymizePaged($connection, string $table, string $before): int
    {
        $total = 0;
        do {
            $select = $connection->select()
                ->from($table, ['log_id'])
                ->where('customer_id IS NOT NULL')
                ->where('created_at < ?', $before)
                ->where('user_message != ?', '[expurgado]')
                ->order('log_id ASC')
                ->limit(self::BATCH);
            $ids = $connection->fetchCol($select);
            if ($ids === []) {
                break;
            }
            $total += $connection->update(
                $table,
                [
                    'user_message' => '[expurgado]',
                    'assistant_response' => null,
                    'error_detail' => null,
                    'ip_hash' => null,
                ],
                ['log_id IN (?)' => $ids]
            );
        } while (count($ids) === self::BATCH);

        return $total;
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function clearErrorDetailPaged($connection, string $table, string $before): int
    {
        $total = 0;
        do {
            $select = $connection->select()
                ->from($table, ['log_id'])
                ->where('error_detail IS NOT NULL')
                ->where('created_at < ?', $before)
                ->order('log_id ASC')
                ->limit(self::BATCH);
            $ids = $connection->fetchCol($select);
            if ($ids === []) {
                break;
            }
            $total += $connection->update(
                $table,
                ['error_detail' => null],
                ['log_id IN (?)' => $ids]
            );
        } while (count($ids) === self::BATCH);

        return $total;
    }
}
