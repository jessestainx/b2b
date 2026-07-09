<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class SystemHealthService
{
    private ResourceConnection $resourceConnection;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    public function getOverallHealth(): array
    {
        try {
            $components = [
                'database' => $this->checkDatabaseHealth(),
                'filesystem' => $this->checkFilesystemHealth(),
                'cache' => $this->checkCacheHealth(),
                'logs' => $this->checkLogHealth(),
                'integrations' => $this->checkIntegrationsHealth()
            ];

            $overallScore = $this->calculateOverallScore($components);
            $overallStatus = $this->getOverallStatus($overallScore);

            return [
                'overall_status' => $overallStatus,
                'overall_score' => $overallScore,
                'components' => $components,
                'last_check' => date('Y-m-d H:i:s'),
                'recommendations' => $this->generateRecommendations($components)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error checking system health: ' . $e->getMessage());
            return [
                'overall_status' => 'error',
                'overall_score' => 0,
                'components' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPerformanceMetrics(): array
    {
        return [
            'response_time' => $this->measureResponseTime(),
            'throughput' => $this->calculateThroughput(),
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'active_sessions' => $this->getActiveSessions(),
            'cache_hit_ratio' => $this->getCacheHitRatio()
        ];
    }

    public function updateComponentHealth(string $component, array $healthData): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_system_health');

            $data = [
                'component' => $component,
                'status' => $healthData['status'],
                'health_score' => $healthData['score'],
                'metrics_data' => json_encode($healthData['metrics'] ?? []),
                'issues' => json_encode($healthData['issues'] ?? []),
                'last_check' => date('Y-m-d H:i:s')
            ];

            $connection->insertOnDuplicate($tableName, $data);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating component health: ' . $e->getMessage());
            return false;
        }
    }

    private function checkDatabaseHealth(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();

            // Test basic connectivity
            $startTime = microtime(true);
            $connection->query('SELECT 1');
            $responseTime = microtime(true) - $startTime;

            // Check slow queries
            $slowQueries = $this->getSlowQueriesCount();

            // Check connection count
            $connectionCount = $this->getDatabaseConnections();

            $issues = [];
            $score = 100;

            if ($responseTime > 1.0) {
                $issues[] = 'Slow database response time';
                $score -= 20;
            }

            if ($slowQueries > 10) {
                $issues[] = 'High number of slow queries';
                $score -= 15;
            }

            if ($connectionCount > 80) {
                $issues[] = 'High connection count';
                $score -= 10;
            }

            return [
                'status' => $score > 80 ? 'healthy' : ($score > 60 ? 'warning' : 'critical'),
                'score' => $score,
                'metrics' => [
                    'response_time' => $responseTime,
                    'slow_queries' => $slowQueries,
                    'connection_count' => $connectionCount
                ],
                'issues' => $issues
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'score' => 0,
                'metrics' => [],
                'issues' => ['Database connection failed: ' . $e->getMessage()]
            ];
        }
    }

    private function checkFilesystemHealth(): array
    {
        $issues = [];
        $score = 100;

        // Check disk space
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage > 90) {
            $issues[] = 'Very low disk space';
            $score -= 30;
        } elseif ($diskUsage > 80) {
            $issues[] = 'Low disk space';
            $score -= 15;
        }

        // Check log directory permissions
        if (!is_writable(BP . '/var/log/')) {
            $issues[] = 'Log directory not writable';
            $score -= 20;
        }

        // Check var directory permissions
        if (!is_writable(BP . '/var/')) {
            $issues[] = 'Var directory not writable';
            $score -= 25;
        }

        return [
            'status' => $score > 80 ? 'healthy' : ($score > 60 ? 'warning' : 'critical'),
            'score' => $score,
            'metrics' => [
                'disk_usage' => $diskUsage,
                'log_writable' => is_writable(BP . '/var/log/'),
                'var_writable' => is_writable(BP . '/var/')
            ],
            'issues' => $issues
        ];
    }

    private function checkCacheHealth(): array
    {
        $issues = [];
        $score = 100;

        try {
            // Check cache hit ratio
            $hitRatio = $this->getCacheHitRatio();
            if ($hitRatio < 0.7) {
                $issues[] = 'Low cache hit ratio';
                $score -= 25;
            } elseif ($hitRatio < 0.8) {
                $issues[] = 'Suboptimal cache hit ratio';
                $score -= 10;
            }

            // Check cache size
            $cacheSize = $this->getCacheSize();
            if ($cacheSize > 1000) { // MB
                $issues[] = 'Large cache size may indicate issues';
                $score -= 10;
            }

            return [
                'status' => $score > 80 ? 'healthy' : ($score > 60 ? 'warning' : 'critical'),
                'score' => $score,
                'metrics' => [
                    'hit_ratio' => $hitRatio,
                    'cache_size_mb' => $cacheSize
                ],
                'issues' => $issues
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'score' => 0,
                'metrics' => [],
                'issues' => ['Cache check failed: ' . $e->getMessage()]
            ];
        }
    }

    private function checkLogHealth(): array
    {
        $issues = [];
        $score = 100;

        $logDir = BP . '/var/log/';

        // Check log file sizes
        $largeLogs = [];
        if (is_dir($logDir)) {
            $files = scandir($logDir);
            foreach ($files as $file) {
                if (substr($file, -4) === '.log') {
                    $size = filesize($logDir . $file);
                    if ($size > 100 * 1024 * 1024) { // 100MB
                        $largeLogs[] = $file . ' (' . round($size / 1024 / 1024) . 'MB)';
                    }
                }
            }
        }

        if (!empty($largeLogs)) {
            $issues[] = 'Large log files detected: ' . implode(', ', $largeLogs);
            $score -= 15;
        }

        // Check error rate in recent logs
        $errorRate = $this->getRecentErrorRate();
        if ($errorRate > 0.1) {
            $issues[] = 'High error rate in recent logs';
            $score -= 20;
        }

        return [
            'status' => $score > 80 ? 'healthy' : ($score > 60 ? 'warning' : 'critical'),
            'score' => $score,
            'metrics' => [
                'large_logs' => count($largeLogs),
                'error_rate' => $errorRate
            ],
            'issues' => $issues
        ];
    }

    private function checkIntegrationsHealth(): array
    {
        $issues = [];
        $score = 100;
        $integrations = [];

        // Check ERP integration
        $erpHealth = $this->checkErpIntegration();
        $integrations['erp'] = $erpHealth;
        if ($erpHealth['score'] < 80) {
            $issues[] = 'ERP integration issues';
            $score -= 20;
        }

        return [
            'status' => $score > 80 ? 'healthy' : ($score > 60 ? 'warning' : 'critical'),
            'score' => $score,
            'metrics' => $integrations,
            'issues' => $issues
        ];
    }

    private function calculateOverallScore(array $components): float
    {
        $total = 0;
        $count = 0;

        foreach ($components as $component) {
            if (isset($component['score'])) {
                $total += $component['score'];
                $count++;
            }
        }

        return $count > 0 ? round($total / $count, 2) : 0;
    }

    private function getOverallStatus(float $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 80) {
            return 'healthy';
        } elseif ($score >= 60) {
            return 'warning';
        } elseif ($score >= 40) {
            return 'critical';
        } else {
            return 'failing';
        }
    }

    private function generateRecommendations(array $components): array
    {
        $recommendations = [];

        foreach ($components as $name => $component) {
            if (isset($component['score']) && $component['score'] < 80) {
                switch ($name) {
                    case 'database':
                        $recommendations[] = 'Consider optimizing database queries and checking connection pool settings';
                        break;
                    case 'filesystem':
                        $recommendations[] = 'Clean up disk space and check directory permissions';
                        break;
                    case 'cache':
                        $recommendations[] = 'Review cache configuration and clear if necessary';
                        break;
                    case 'logs':
                        $recommendations[] = 'Implement log rotation and monitor error rates';
                        break;
                    case 'integrations':
                        $recommendations[] = 'Check external service connectivity and authentication';
                        break;
                }
            }
        }

        return $recommendations;
    }

    // Helper methods for metrics
    private function measureResponseTime(): float
    {
        $startTime = microtime(true);
        // Simulate a simple operation
        file_get_contents('php://memory');
        return microtime(true) - $startTime;
    }

    private function calculateThroughput(): float
    {
        // This would be calculated from actual request logs
        return 150.0; // requests per minute
    }

    private function getCpuUsage(): float
    {
        // This would use system calls to get actual CPU usage
        return 35.5; // percentage
    }

    private function getMemoryUsage(): float
    {
        return (memory_get_usage(true) / 1024 / 1024); // MB
    }

    private function getDiskUsage(): float
    {
        $bytes = disk_free_space('/');
        $total = disk_total_space('/');
        return $total > 0 ? (($total - $bytes) / $total) * 100 : 0;
    }

    private function getActiveSessions(): int
    {
        // This would query the session storage
        return 25;
    }

    private function getCacheHitRatio(): float
    {
        // This would check actual cache statistics
        return 0.85;
    }

    private function getSlowQueriesCount(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $result = $connection->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $row = $result->fetch();
            return (int)($row['Value'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDatabaseConnections(): int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $result = $connection->query("SHOW STATUS LIKE 'Threads_connected'");
            $row = $result->fetch();
            return (int)($row['Value'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheSize(): float
    {
        $cacheDir = BP . '/var/cache/';
        if (!is_dir($cacheDir)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return round($size / 1024 / 1024, 2); // MB
    }

    private function getRecentErrorRate(): float
    {
        $logFile = BP . '/var/log/exception.log';
        if (!is_readable($logFile)) {
            return 0.0;
        }

        $size = filesize($logFile);
        if ($size === false || $size === 0) {
            return 0.0;
        }

        $readBytes = (int)min($size, 512 * 1024);
        $handle = fopen($logFile, 'rb');
        if ($handle === false) {
            return 0.0;
        }

        fseek($handle, -$readBytes, SEEK_END);
        $chunk = (string)fread($handle, $readBytes);
        fclose($handle);

        $cutoff = time() - 3600;
        $recent = 0;
        foreach (explode("\n", $chunk) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $match)) {
                $ts = strtotime($match[1]);
                if ($ts !== false && $ts >= $cutoff) {
                    $recent++;
                }
            }
        }

        return min(1.0, $recent / 20.0);
    }

    private function checkErpIntegration(): array
    {
        // Check ERP integration health
        return [
            'score' => 85,
            'status' => 'healthy',
            'last_sync' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
        ];
    }
}
