<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Api\Data;

interface DashboardDataInterface
{
    public function getSystemHealth(): array;
    public function setSystemHealth(array $systemHealth): self;

    public function getLogMetrics(): array;
    public function setLogMetrics(array $logMetrics): self;

    public function getActiveAlerts(): array;
    public function setActiveAlerts(array $activeAlerts): self;

    public function getRecentActivity(): array;
    public function setRecentActivity(array $recentActivity): self;

    public function getAwamotosMetrics(): array;
    public function setAwamotosMetrics(array $awamotosMetrics): self;

    public function getPerformanceData(): array;
    public function setPerformanceData(array $performanceData): self;
}
