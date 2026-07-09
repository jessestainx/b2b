<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Block\Adminhtml;

use GrupoAwamotos\LogMonitoring\Api\AlertRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Api\MonitoringInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Alerts extends Template
{
    private AlertRepositoryInterface $alertRepository;
    private MonitoringInterface $monitoringService;
    private Json $serializer;

    public function __construct(
        Context $context,
        AlertRepositoryInterface $alertRepository,
        MonitoringInterface $monitoringService,
        Json $serializer,
        array $data = []
    ) {
        $this->alertRepository = $alertRepository;
        $this->monitoringService = $monitoringService;
        $this->serializer = $serializer;
        parent::__construct($context, $data);
    }

    public function getActiveAlerts(): array
    {
        try {
            return $this->alertRepository->getActiveAlerts();
        } catch (\Exception $e) {
            $this->_logger->error('[LogMonitoring] Failed to load active alerts: ' . $e->getMessage());
            return [];
        }
    }

    public function getCriticalAlerts(): array
    {
        try {
            return $this->alertRepository->getCriticalAlerts();
        } catch (\Exception $e) {
            $this->_logger->error('[LogMonitoring] Failed to load critical alerts: ' . $e->getMessage());
            return [];
        }
    }

    public function getAlertSummary(): array
    {
        try {
            return $this->monitoringService->getAlertSummary();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getAlertsJson(): string
    {
        try {
            $activeAlerts = $this->getActiveAlerts();
            return $this->serializer->serialize(array_map(function ($alert) {
                return [
                    'id' => $alert->getEntityId(),
                    'type' => $alert->getAlertType(),
                    'severity' => $alert->getSeverity(),
                    'title' => $alert->getTitle(),
                    'message' => $alert->getMessage(),
                    'status' => $alert->getStatus(),
                    'occurrences' => $alert->getOccurrences(),
                    'last_occurrence' => $alert->getLastOccurrence(),
                    'source' => $alert->getSource(),
                    'context_data' => $alert->getContextData()
                ];
            }, $activeAlerts));
        } catch (\Exception $e) {
            return $this->serializer->serialize(['error' => $e->getMessage()]);
        }
    }

    public function getAcknowledgeUrl(): string
    {
        return $this->getUrl('rest/V1/log-monitoring/alerts');
    }

    public function getResolveUrl(): string
    {
        return $this->getUrl('rest/V1/log-monitoring/alerts');
    }

    public function getSeverityClass(string $severity): string
    {
        switch ($severity) {
            case 'critical':
                return 'critical-alert';
            case 'high':
                return 'high-alert';
            case 'medium':
                return 'medium-alert';
            case 'low':
                return 'low-alert';
            default:
                return 'default-alert';
        }
    }

    protected function _toHtml(): string
    {
        $this->setTemplate('GrupoAwamotos_LogMonitoring::alerts/index.phtml');
        return parent::_toHtml();
    }
}
