<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Api;

use GrupoAwamotos\LogMonitoring\Api\Data\LogMetricsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface LogMetricsRepositoryInterface
{
    public function save(LogMetricsInterface $logMetrics): LogMetricsInterface;

    public function getById(int $id): LogMetricsInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    public function delete(LogMetricsInterface $logMetrics): bool;

    public function deleteById(int $id): bool;

    public function getMetricsByType(string $logType, int $limit = 100): array;

    public function getMetricsByDateRange(string $from, string $to): array;

    public function getLatestMetrics(int $limit = 50): array;

    public function getTrendData(string $logType, int $days = 7): array;
}
