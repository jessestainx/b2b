<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Cron;

use GrupoAwamotos\LogMonitoring\Api\AlertRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Service\NotificationService;
use Psr\Log\LoggerInterface;

class AlertProcessor
{
    private AlertRepositoryInterface $alertRepository;
    private NotificationService $notificationService;
    private LoggerInterface $logger;

    public function __construct(
        AlertRepositoryInterface $alertRepository,
        NotificationService $notificationService,
        LoggerInterface $logger
    ) {
        $this->alertRepository = $alertRepository;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            // Get critical alerts that need immediate attention
            $criticalAlerts = $this->alertRepository->getCriticalAlerts();

            foreach ($criticalAlerts as $alert) {
                $alertData = [
                    'type' => $alert->getAlertType(),
                    'severity' => $alert->getSeverity(),
                    'title' => $alert->getTitle(),
                    'message' => $alert->getMessage(),
                    'context' => $alert->getContextData()
                ];

                // Send notification for unacknowledged critical alerts
                if ($alert->getStatus() === 'open' && $alert->getOccurrences() <= 3) {
                    $result = $this->notificationService->sendAlert($alertData);

                    if (array_filter($result)) { // If any notification method succeeded
                        $this->logger->info('Alert notification sent', [
                            'alert_id' => $alert->getEntityId(),
                            'type' => $alert->getAlertType(),
                            'result' => $result
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in alert processing: ' . $e->getMessage());
        }
    }
}
