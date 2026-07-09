<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model;

use GrupoAwamotos\LogMonitoring\Api\Data\DashboardDataInterface;
use Magento\Framework\DataObject;

class DashboardData extends DataObject implements DashboardDataInterface
{
    public function getSystemHealth(): array
    {
        return $this->getData('system_health') ?: [];
    }

    public function setSystemHealth(array $systemHealth): DashboardDataInterface
    {
        return $this->setData('system_health', $systemHealth);
    }

    public function getLogMetrics(): array
    {
        return $this->getData('log_metrics') ?: [];
    }

    public function setLogMetrics(array $logMetrics): DashboardDataInterface
    {
        return $this->setData('log_metrics', $logMetrics);
    }

    public function getActiveAlerts(): array
    {
        return $this->getData('active_alerts') ?: [];
    }

    public function setActiveAlerts(array $activeAlerts): DashboardDataInterface
    {
        return $this->setData('active_alerts', $activeAlerts);
    }

    public function getRecentActivity(): array
    {
        return $this->getData('recent_activity') ?: [];
    }

    public function setRecentActivity(array $recentActivity): DashboardDataInterface
    {
        return $this->setData('recent_activity', $recentActivity);
    }

    public function getAwamotosMetrics(): array
    {
        return $this->getData('awamotos_metrics') ?: [];
    }

    public function setAwamotosMetrics(array $awamotosMetrics): DashboardDataInterface
    {
        return $this->setData('awamotos_metrics', $awamotosMetrics);
    }

    public function getPerformanceData(): array
    {
        return $this->getData('performance_data') ?: [];
    }

    public function setPerformanceData(array $performanceData): DashboardDataInterface
    {
        return $this->setData('performance_data', $performanceData);
    }
}
