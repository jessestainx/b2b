<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Api;

use GrupoAwamotos\LogMonitoring\Api\Data\DashboardDataInterface;

/**
 * @api
 */
interface MonitoringInterface
{
    public function getDashboardData(): DashboardDataInterface;

    public function getSystemHealth(): array;

    public function getLogAnalysis(string $logType = ''): array;

    public function getAwamotosMetrics(): array;

    public function triggerLogAnalysis(): bool;

    public function getAlertSummary(): array;

    public function getPerformanceMetrics(): array;

    public function acknowledgeAlert(int $alertId, string $acknowledgedBy): bool;

    public function resolveAlert(int $alertId, string $resolvedBy): bool;

    public function testNotifications(): array;
}
