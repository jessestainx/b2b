<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Queue\OrderSyncConsumer;
use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterfaceFactory;
use GrupoAwamotos\ERPIntegration\Model\CronFileLock;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\DB\Sql\Expression;
use Magento\MysqlMq\Model\QueueManagement;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron job to process ERP order sync queue
 *
 * Uses direct database access to process queue messages
 * instead of ConsumerFactory to avoid class generation issues
 */
class ProcessOrderQueue
{
    private const LOCK_NAME = 'grupoawamotos_erp_process_order_queue';
    private const MAX_MESSAGES_PER_RUN = 50;
    private const RETRY_MAX_MESSAGES = 20;
    private const QUEUE_NAME = 'erp.order.sync.queue';
    private const RETRY_QUEUE_NAME = 'erp.order.sync.retry.queue';
    private const MESSAGE_STATUS_NEW = QueueManagement::MESSAGE_STATUS_NEW;
    private const MESSAGE_STATUS_IN_PROGRESS = QueueManagement::MESSAGE_STATUS_IN_PROGRESS;
    private const MESSAGE_STATUS_COMPLETE = QueueManagement::MESSAGE_STATUS_COMPLETE;
    private const MESSAGE_STATUS_RETRY_REQUIRED = QueueManagement::MESSAGE_STATUS_RETRY_REQUIRED;
    private const MESSAGE_STATUS_ERROR = QueueManagement::MESSAGE_STATUS_ERROR;
    private const MAX_STATUS_TRIALS = 5;

    /**
     * @var OrderSyncConsumer
     */
    private OrderSyncConsumer $consumer;

    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var OrderSyncMessageInterfaceFactory
     */
    private OrderSyncMessageInterfaceFactory $messageFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param OrderSyncConsumer $consumer
     * @param Helper $helper
     * @param ResourceConnection $resourceConnection
     * @param SerializerInterface $serializer
     * @param OrderSyncMessageInterfaceFactory $messageFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderSyncConsumer $consumer,
        Helper $helper,
        ResourceConnection $resourceConnection,
        SerializerInterface $serializer,
        OrderSyncMessageInterfaceFactory $messageFactory,
        LoggerInterface $logger
    ) {
        $this->consumer = $consumer;
        $this->helper = $helper;
        $this->resourceConnection = $resourceConnection;
        $this->serializer = $serializer;
        $this->messageFactory = $messageFactory;
        $this->logger = $logger;
    }

    /**
     * Process pending orders in the queue
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isOrderQueueEnabled()) {
            return;
        }

        $lockHandle = CronFileLock::acquire(self::LOCK_NAME);
        if ($lockHandle === null) {
            $this->logger->info('[ERP Cron] ProcessOrderQueue already running; skipping overlap.');
            return;
        }

        $processedMain = 0;
        $processedRetry = 0;
        $errors = 0;

        try {
            try {
                // Process main queue
                $processedMain = $this->processQueue(self::QUEUE_NAME, self::MAX_MESSAGES_PER_RUN, false);
            } catch (\Exception $e) {
                $this->logger->error('[ERP Cron] Error processing main queue: ' . $e->getMessage());
                $errors++;
            }

            try {
                // Process retry queue with lower priority
                $processedRetry = $this->processQueue(self::RETRY_QUEUE_NAME, self::RETRY_MAX_MESSAGES, true);
            } catch (\Exception $e) {
                $this->logger->error('[ERP Cron] Error processing retry queue: ' . $e->getMessage());
                $errors++;
            }

            if ($processedMain > 0 || $processedRetry > 0 || $errors > 0) {
                $this->logger->info('[ERP Cron] Order queue processing completed', [
                    'main_processed' => $processedMain,
                    'retry_processed' => $processedRetry,
                    'errors' => $errors,
                ]);
            }
        } finally {
            CronFileLock::release($lockHandle);
        }
    }

    /**
     * Process messages from a specific queue
     *
     * @param string $queueName
     * @param int $maxMessages
     * @param bool $isRetry
     * @return int Number of processed messages
     */
    private function processQueue(string $queueName, int $maxMessages, bool $isRetry): int
    {
        $connection = $this->resourceConnection->getConnection();
        $queueTable = $this->resourceConnection->getTableName('queue_message');
        $queueStatusTable = $this->resourceConnection->getTableName('queue_message_status');

        // Get pending messages
        $select = $connection->select()
            ->from(['qm' => $queueTable], ['id', 'body'])
            ->join(
                ['qms' => $queueStatusTable],
                'qm.id = qms.message_id',
                [
                    'status_id' => 'id',
                    'status' => 'status',
                    'number_of_trials' => 'number_of_trials',
                ]
            )
            ->where('qms.status IN (?)', [self::MESSAGE_STATUS_NEW, self::MESSAGE_STATUS_RETRY_REQUIRED])
            ->where('qm.topic_name = ?', $isRetry ? 'erp.order.sync.retry' : 'erp.order.sync')
            ->order('qm.id ASC')
            ->limit($maxMessages);

        $messages = $connection->fetchAll($select);
        $processed = 0;

        foreach ($messages as $messageData) {
            $statusId = (int) ($messageData['status_id'] ?? 0);
            $expectedStatus = (int) ($messageData['status'] ?? 0);
            $previousTrials = (int) ($messageData['number_of_trials'] ?? 0);

            if ($statusId <= 0 || !$this->claimMessageInProgress($connection, $queueStatusTable, $statusId, $expectedStatus)) {
                continue;
            }

            try {
                // Decode message - Magento uses JSON serialization for queue messages
                $body = $messageData['body'];

                try {
                    $decodedBody = $this->serializer->unserialize($body);
                } catch (\Exception $e) {
                    // Fallback to json_decode if serializer fails
                    $decodedBody = json_decode($body, true);
                }

                if (!$decodedBody || !is_array($decodedBody)) {
                    $this->logger->warning('[ERP Cron] Invalid message body', [
                        'message_id' => $messageData['id'],
                        'body' => substr($body, 0, 200)
                    ]);
                    $this->markMessageFailed($connection, $queueStatusTable, $statusId);
                    continue;
                }

                // Create message object - Magento MessageEncoder uses snake_case keys
                $message = $this->messageFactory->create();
                $message->setOrderId((int)($decodedBody['order_id'] ?? $decodedBody['orderId'] ?? 0));
                $message->setIncrementId((string)($decodedBody['increment_id'] ?? $decodedBody['incrementId'] ?? ''));
                $message->setRetryCount((int)($decodedBody['retry_count'] ?? $decodedBody['retryCount'] ?? 0));
                $message->setQueuedAt((string)($decodedBody['queued_at'] ?? $decodedBody['queuedAt'] ?? date('Y-m-d H:i:s')));
                $message->setLastError($decodedBody['last_error'] ?? $decodedBody['lastError'] ?? null);

                // Process using our consumer
                if ($isRetry) {
                    $this->consumer->processRetry($message);
                } else {
                    $this->consumer->process($message);
                }

                // Mark as complete
                $this->markMessageComplete($connection, $queueStatusTable, $statusId);
                $processed++;
            } catch (\Exception $e) {
                $currentTrials = $previousTrials + 1;
                $this->logger->error('[ERP Cron] Error processing message', [
                    'message_id' => $messageData['id'],
                    'error' => $e->getMessage(),
                    'trials' => $currentTrials,
                ]);

                if ($currentTrials >= self::MAX_STATUS_TRIALS) {
                    $this->markMessageFailed($connection, $queueStatusTable, $statusId);
                    continue;
                }

                $this->markMessageRetryRequired($connection, $queueStatusTable, $statusId);
            }
        }

        return $processed;
    }

    /**
     * Mark message as complete
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $table
     * @param int $statusId
     * @return void
     */
    private function markMessageComplete($connection, string $table, int $statusId): void
    {
        $connection->update(
            $table,
            ['status' => self::MESSAGE_STATUS_COMPLETE, 'updated_at' => date('Y-m-d H:i:s')],
            [
                'id = ?' => $statusId,
                'status = ?' => self::MESSAGE_STATUS_IN_PROGRESS,
            ]
        );
    }

    /**
     * Mark message as retry required.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $table
     * @param int $statusId
     * @return void
     */
    private function markMessageRetryRequired($connection, string $table, int $statusId): void
    {
        $connection->update(
            $table,
            [
                'status' => self::MESSAGE_STATUS_RETRY_REQUIRED,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id = ?' => $statusId,
                'status = ?' => self::MESSAGE_STATUS_IN_PROGRESS,
            ]
        );
    }

    /**
     * Mark message as failed after max retries or invalid payload.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $table
     * @param int $statusId
     * @return void
     */
    private function markMessageFailed($connection, string $table, int $statusId): void
    {
        $connection->update(
            $table,
            [
                'status' => self::MESSAGE_STATUS_ERROR,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id = ?' => $statusId,
                'status = ?' => self::MESSAGE_STATUS_IN_PROGRESS,
            ]
        );
    }

    /**
     * Atomically claims a queue message for processing.
     */
    private function claimMessageInProgress(
        $connection,
        string $table,
        int $statusId,
        int $expectedStatus
    ): bool {
        $updated = (int) $connection->update(
            $table,
            [
                'status' => self::MESSAGE_STATUS_IN_PROGRESS,
                'number_of_trials' => new Expression('number_of_trials + 1'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'id = ?' => $statusId,
                'status = ?' => $expectedStatus,
            ]
        );

        return $updated === 1;
    }
}
