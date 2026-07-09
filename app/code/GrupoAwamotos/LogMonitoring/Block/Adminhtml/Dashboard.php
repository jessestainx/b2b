<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Block\Adminhtml;

use GrupoAwamotos\LogMonitoring\Api\MonitoringInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;

class Dashboard extends Template
{
    private MonitoringInterface $monitoringService;
    private Json $serializer;

    public function __construct(
        Context $context,
        MonitoringInterface $monitoringService,
        Json $serializer,
        array $data = []
    ) {
        $this->monitoringService = $monitoringService;
        $this->serializer = $serializer;
        parent::__construct($context, $data);
    }

    public function getDashboardData(): string
    {
        try {
            $dashboardData = $this->monitoringService->getDashboardData();
            return $this->serializer->serialize([
                'system_health' => $dashboardData->getSystemHealth(),
                'log_metrics' => $dashboardData->getLogMetrics(),
                'active_alerts' => $dashboardData->getActiveAlerts(),
                'recent_activity' => $dashboardData->getRecentActivity(),
                'awamotos_metrics' => $dashboardData->getAwamotosMetrics(),
                'performance_data' => $dashboardData->getPerformanceData()
            ]);
        } catch (\Exception $e) {
            $this->_logger->error('[LogMonitoring] Failed to load dashboard data: ' . $e->getMessage());
            return $this->serializer->serialize(['error' => $e->getMessage()]);
        }
    }

    public function getSystemHealth(): array
    {
        try {
            return $this->monitoringService->getSystemHealth();
        } catch (\Exception $e) {
            $this->_logger->error('[LogMonitoring] Failed to load system health: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getAlertSummary(): array
    {
        try {
            return $this->monitoringService->getAlertSummary();
        } catch (\Exception $e) {
            $this->_logger->error('[LogMonitoring] Failed to load alert summary: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function getApiUrl(string $endpoint): string
    {
        return $this->getUrl('rest/V1/log-monitoring/' . $endpoint);
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('awalogmonitoring/dashboard/index');
    }

    public function getTriggerAnalysisUrl(): string
    {
        return $this->getApiUrl('trigger-analysis');
    }

    protected function _toHtml(): string
    {
        $this->setTemplate('GrupoAwamotos_LogMonitoring::dashboard/index.phtml');
        return parent::_toHtml();
    }
}
