<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class DataCleanup
{
    private ResourceConnection $resourceConnection;
    private DateTime $dateTime;
    private LoggerInterface $logger;
    private FileDriver $fileDriver;
    private string $logDir;

    public function __construct(
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        LoggerInterface $logger,
        FileDriver $fileDriver,
        ?string $logDir = null
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->fileDriver = $fileDriver;
        $this->logDir = $logDir ?? (BP . '/var/log');
    }

    public function execute(): void
    {

        try {
            $connection = $this->resourceConnection->getConnection();

            // Cleanup old log metrics (keep 30 days)
            $oldDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-30 days'));
            $metricsTable = $this->resourceConnection->getTableName('awa_log_metrics');

            $deleted = $connection->delete(
                $metricsTable,
                ['created_at < ?' => $oldDate]
            );

            $this->logger->info("Cleaned up {$deleted} old log metrics records");

            // Cleanup resolved alerts (keep 7 days after resolution)
            $alertsTable = $this->resourceConnection->getTableName('awa_log_alerts');
            $alertCleanupDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-7 days'));

            $deletedAlerts = $connection->delete(
                $alertsTable,
                [
                    'status = ?' => 'resolved',
                    'resolved_at < ?' => $alertCleanupDate
                ]
            );

            $this->logger->info("Cleaned up {$deletedAlerts} resolved alert records");

            // Cleanup old system health records (keep 14 days)
            $healthTable = $this->resourceConnection->getTableName('awa_system_health');
            $healthCleanupDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-14 days'));

            $deletedHealth = $connection->delete(
                $healthTable,
                ['created_at < ?' => $healthCleanupDate]
            );

            $this->logger->info("Cleaned up {$deletedHealth} old system health records");

            $this->cleanOldLogArtifacts();

            $this->logger->info('Completed log monitoring data cleanup');
        } catch (\Throwable $e) {
            $this->logger->error('Error in data cleanup: ' . $e->getMessage());
        }
    }

    private function cleanOldLogArtifacts(): void
    {
        $patterns = [
            'erp_register_clients_auto_*.sql',
            'erp_register_clients_[0-9]*.sql',
        ];
        $cutoff = strtotime('-7 days');
        $deleted = 0;

        foreach ($patterns as $pattern) {
            $files = glob($this->logDir . '/' . $pattern);
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    try {
                        $this->fileDriver->deleteFile($file);
                        $deleted++;
                    } catch (\Exception $e) {
                        $this->logger->warning('Could not delete log artifact: ' . basename($file));
                    }
                }
            }
        }

        if ($deleted > 0) {
            $this->logger->info("Cleaned up {$deleted} old log artifact file(s) from var/log/");
        }
    }
}
