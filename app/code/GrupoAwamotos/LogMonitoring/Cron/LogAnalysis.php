<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Cron;

use GrupoAwamotos\LogMonitoring\Service\LogAnalyzer\AnalyzerPool;
use GrupoAwamotos\LogMonitoring\Service\NotificationService;
use Psr\Log\LoggerInterface;

class LogAnalysis
{
    private AnalyzerPool $analyzerPool;
    private NotificationService $notificationService;
    private LoggerInterface $logger;

    public function __construct(
        AnalyzerPool $analyzerPool,
        NotificationService $notificationService,
        LoggerInterface $logger
    ) {
        $this->analyzerPool = $analyzerPool;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            // Run all analyzers
            $results = $this->analyzerPool->analyzeAll();

            // Process alerts from analyzers
            foreach ($results as $analyzerType => $result) {
                if (isset($result['error'])) {
                    $this->logger->error("Analyzer {$analyzerType} failed: " . $result['error']);
                    continue;
                }

                // Get analyzer instance and check for alerts
                try {
                    $analyzer = $this->analyzerPool->getAnalyzer($analyzerType);
                    $alerts = $analyzer->generateAlerts();

                    foreach ($alerts as $alert) {
                        if ($this->shouldSendAlert($alert)) {
                            $this->notificationService->sendAlert($alert);
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error("Error processing alerts for {$analyzerType}: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in scheduled log analysis: ' . $e->getMessage());
        }
    }

    private function shouldSendAlert(array $alert): bool
    {
        // Only send critical and high severity alerts immediately
        return in_array($alert['severity'], ['critical', 'high']);
    }
}
