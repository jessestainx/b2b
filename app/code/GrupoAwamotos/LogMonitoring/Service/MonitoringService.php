<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service;

use GrupoAwamotos\LogMonitoring\Api\AlertRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Api\Data\DashboardDataInterface;
use GrupoAwamotos\LogMonitoring\Api\Data\DashboardDataInterfaceFactory;
use GrupoAwamotos\LogMonitoring\Api\LogMetricsRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Api\MonitoringInterface;
use GrupoAwamotos\LogMonitoring\Service\LogAnalyzer\AnalyzerPool;
use GrupoAwamotos\LogMonitoring\Service\SystemHealthService;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class MonitoringService implements MonitoringInterface
{
    private LogMetricsRepositoryInterface $logMetricsRepository;
    private AlertRepositoryInterface $alertRepository;
    private DashboardDataInterfaceFactory $dashboardDataFactory;
    private SystemHealthService $systemHealthService;
    private AnalyzerPool $analyzerPool;
    private LoggerInterface $logger;

    public function __construct(
        LogMetricsRepositoryInterface $logMetricsRepository,
        AlertRepositoryInterface $alertRepository,
        DashboardDataInterfaceFactory $dashboardDataFactory,
        SystemHealthService $systemHealthService,
        AnalyzerPool $analyzerPool,
        LoggerInterface $logger
    ) {
        $this->logMetricsRepository = $logMetricsRepository;
        $this->alertRepository = $alertRepository;
        $this->dashboardDataFactory = $dashboardDataFactory;
        $this->systemHealthService = $systemHealthService;
        $this->analyzerPool = $analyzerPool;
        $this->logger = $logger;
    }

    public function getDashboardData(): DashboardDataInterface
    {
        try {
            $dashboardData = $this->dashboardDataFactory->create();

            // System Health
            $systemHealth = $this->getSystemHealth();
            $dashboardData->setSystemHealth($systemHealth);

            // Log Metrics
            $logMetrics = $this->getLogAnalysis();
            $dashboardData->setLogMetrics($logMetrics);

            // Active Alerts
            $activeAlerts = $this->getActiveAlertsData();
            $dashboardData->setActiveAlerts($activeAlerts);

            // Recent Activity
            $recentActivity = $this->getRecentActivity();
            $dashboardData->setRecentActivity($recentActivity);

            // AWA Metrics
            $awamotosMetrics = $this->getAwamotosMetrics();
            $dashboardData->setAwamotosMetrics($awamotosMetrics);

            // Performance Data
            $performanceData = $this->getPerformanceMetrics();
            $dashboardData->setPerformanceData($performanceData);

            return $dashboardData;
        } catch (\Exception $e) {
            $this->logger->error('Error getting dashboard data: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to load dashboard data: %1', $e->getMessage()));
        }
    }

    public function getSystemHealth(): array
    {
        return $this->systemHealthService->getOverallHealth();
    }

    public function getLogAnalysis(string $logType = ''): array
    {
        try {
            if ($logType) {
                $metrics = $this->logMetricsRepository->getMetricsByType($logType, 50);
                $trendData = $this->logMetricsRepository->getTrendData($logType, 7);
            } else {
                $metrics = $this->logMetricsRepository->getLatestMetrics(50);
                $trendData = [];
            }

            return [
                'current_metrics' => array_map(function ($metric) {
                    return [
                        'id' => $metric->getEntityId(),
                        'log_type' => $metric->getLogType(),
                        'source_file' => $metric->getSourceFile(),
                        'total_entries' => $metric->getTotalEntries(),
                        'error_entries' => $metric->getErrorEntries(),
                        'warning_entries' => $metric->getWarningEntries(),
                        'critical_entries' => $metric->getCriticalEntries(),
                        'file_size_mb' => round($metric->getFileSizeBytes() / 1024 / 1024, 2),
                        'analysis_data' => $metric->getAnalysisData(),
                        'created_at' => $metric->getCreatedAt()
                    ];
                }, $metrics),
                'trend_data' => $trendData,
                'summary' => $this->calculateLogSummary($metrics)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error analyzing logs: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getAwamotosMetrics(): array
    {
        try {
            $erpMetrics = $this->analyzerPool->getAnalyzer('erp')->getSpecificMetrics();
            $performanceMetrics = $this->analyzerPool->getAnalyzer('performance')->getSpecificMetrics();

            return [
                'erp_sync' => $erpMetrics,
                'performance' => $performanceMetrics,
                'overall_score' => $this->calculateOverallScore($erpMetrics, $performanceMetrics)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting AWA metrics: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function triggerLogAnalysis(): bool
    {
        try {
            $this->logger->info('Manual log analysis triggered');

            // Trigger all analyzers
            foreach ($this->analyzerPool->getAllAnalyzers() as $analyzer) {
                $analyzer->analyze();
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error triggering log analysis: ' . $e->getMessage());
            return false;
        }
    }

    public function getAlertSummary(): array
    {
        try {
            $activeAlerts = $this->alertRepository->getActiveAlerts();
            $criticalAlerts = $this->alertRepository->getCriticalAlerts();

            $summary = [
                'total_active' => count($activeAlerts),
                'critical_count' => count($criticalAlerts),
                'by_severity' => [
                    'critical' => 0,
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                ],
                'by_type' => []
            ];

            foreach ($activeAlerts as $alert) {
                $severity = $alert->getSeverity();
                $type = $alert->getAlertType();

                if (isset($summary['by_severity'][$severity])) {
                    $summary['by_severity'][$severity]++;
                }

                if (!isset($summary['by_type'][$type])) {
                    $summary['by_type'][$type] = 0;
                }
                $summary['by_type'][$type]++;
            }

            return $summary;
        } catch (\Exception $e) {
            $this->logger->error('Error getting alert summary: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getPerformanceMetrics(): array
    {
        return $this->systemHealthService->getPerformanceMetrics();
    }

    public function acknowledgeAlert(int $alertId, string $acknowledgedBy): bool
    {
        return $this->alertRepository->acknowledgeAlert($alertId, $acknowledgedBy);
    }

    public function resolveAlert(int $alertId, string $resolvedBy): bool
    {
        return $this->alertRepository->resolveAlert($alertId, $resolvedBy);
    }

    public function testNotifications(): array
    {
        // This would be implemented to test notification channels
        return [
            'email' => true,
            'slack' => true,
            'webhook' => true
        ];
    }

    private function getActiveAlertsData(): array
    {
        $activeAlerts = $this->alertRepository->getActiveAlerts();

        return array_map(function ($alert) {
            return [
                'id' => $alert->getEntityId(),
                'type' => $alert->getAlertType(),
                'severity' => $alert->getSeverity(),
                'title' => $alert->getTitle(),
                'message' => $alert->getMessage(),
                'source' => $alert->getSource(),
                'status' => $alert->getStatus(),
                'occurrences' => $alert->getOccurrences(),
                'last_occurrence' => $alert->getLastOccurrence(),
                'context_data' => $alert->getContextData()
            ];
        }, $activeAlerts);
    }

    private function getRecentActivity(): array
    {
        $recentMetrics = $this->logMetricsRepository->getLatestMetrics(10);
        $recentAlerts = $this->alertRepository->getActiveAlerts();

        $activity = [];

        foreach ($recentMetrics as $metric) {
            $activity[] = [
                'type' => 'log_analysis',
                'timestamp' => $metric->getCreatedAt(),
                'message' => "Analyzed {$metric->getLogType()} logs: {$metric->getErrorEntries()} errors found",
                'data' => [
                    'log_type' => $metric->getLogType(),
                    'errors' => $metric->getErrorEntries(),
                    'file_size' => $metric->getFileSizeBytes()
                ]
            ];
        }

        foreach (array_slice($recentAlerts, 0, 10) as $alert) {
            $activity[] = [
                'type' => 'alert',
                'timestamp' => $alert->getLastOccurrence(),
                'message' => "Alert: {$alert->getTitle()}",
                'severity' => $alert->getSeverity(),
                'data' => [
                    'alert_type' => $alert->getAlertType(),
                    'source' => $alert->getSource(),
                    'occurrences' => $alert->getOccurrences()
                ]
            ];
        }

        // Sort by timestamp desc
        usort($activity, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activity, 0, 20);
    }

    private function calculateLogSummary(array $metrics): array
    {
        $totalErrors = 0;
        $totalWarnings = 0;
        $totalCritical = 0;
        $totalSize = 0;

        foreach ($metrics as $metric) {
            $totalErrors += $metric->getErrorEntries();
            $totalWarnings += $metric->getWarningEntries();
            $totalCritical += $metric->getCriticalEntries();
            $totalSize += $metric->getFileSizeBytes();
        }

        return [
            'total_errors' => $totalErrors,
            'total_warnings' => $totalWarnings,
            'total_critical' => $totalCritical,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'files_analyzed' => count($metrics)
        ];
    }

    private function calculateOverallScore(array $erpMetrics, array $performanceMetrics): float
    {
        $erpScore = $erpMetrics['health_score'] ?? 100;
        $perfScore = $performanceMetrics['health_score'] ?? 100;

        return round(($erpScore + $perfScore) / 2, 2);
    }
}
