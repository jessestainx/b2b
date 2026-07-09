<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model;

use GrupoAwamotos\LogMonitoring\Api\Data\LogMetricsInterface;
use GrupoAwamotos\LogMonitoring\Api\Data\LogMetricsInterfaceFactory;
use GrupoAwamotos\LogMonitoring\Api\LogMetricsRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Model\ResourceModel\LogMetrics as LogMetricsResource;
use GrupoAwamotos\LogMonitoring\Model\ResourceModel\LogMetrics\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class LogMetricsRepository implements LogMetricsRepositoryInterface
{
    private LogMetricsResource $resource;
    private LogMetricsInterfaceFactory $logMetricsFactory;
    private CollectionFactory $collectionFactory;
    private SearchResultsInterfaceFactory $searchResultsFactory;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private LoggerInterface $logger;

    public function __construct(
        LogMetricsResource $resource,
        LogMetricsInterfaceFactory $logMetricsFactory,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logMetricsFactory = $logMetricsFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    public function save(LogMetricsInterface $logMetrics): LogMetricsInterface
    {
        try {
            $this->resource->save($logMetrics);
        } catch (\Exception $e) {
            $this->logger->error('Error saving log metrics: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to save log metrics: %1', $e->getMessage()));
        }

        return $logMetrics;
    }

    public function getById(int $id): LogMetricsInterface
    {
        $logMetrics = $this->logMetricsFactory->create();
        $this->resource->load($logMetrics, $id);

        if (!$logMetrics->getEntityId()) {
            throw new NoSuchEntityException(__('Log metrics with ID %1 does not exist', $id));
        }

        return $logMetrics;
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ?: 'eq';
                $collection->addFieldToFilter($filter->getField(), [$condition => $filter->getValue()]);
            }
        }

        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());

        foreach ($searchCriteria->getSortOrders() as $sortOrder) {
            $collection->setOrder($sortOrder->getField(), $sortOrder->getDirection());
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function delete(LogMetricsInterface $logMetrics): bool
    {
        try {
            $this->resource->delete($logMetrics);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting log metrics: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to delete log metrics: %1', $e->getMessage()));
        }

        return true;
    }

    public function deleteById(int $id): bool
    {
        $logMetrics = $this->getById($id);
        return $this->delete($logMetrics);
    }

    public function getMetricsByType(string $logType, int $limit = 100): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addLogTypeFilter($logType)
            ->orderByLatest()
            ->setPageSize($limit);

        return $collection->getItems();
    }

    public function getMetricsByDateRange(string $from, string $to): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addDateRangeFilter($from, $to)
            ->orderByLatest();

        return $collection->getItems();
    }

    public function getLatestMetrics(int $limit = 50): array
    {
        $collection = $this->collectionFactory->create();
        $collection->orderByLatest()
            ->setPageSize($limit);

        return $collection->getItems();
    }

    public function getTrendData(string $logType, int $days = 7): array
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getMainTable();

        $fromDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $select = $connection->select()
            ->from(
                $tableName,
                [
                    'date' => 'DATE(created_at)',
                    'total_errors' => 'SUM(error_entries)',
                    'total_warnings' => 'SUM(warning_entries)',
                    'total_critical' => 'SUM(critical_entries)',
                    'avg_file_size' => 'AVG(file_size_bytes)'
                ]
            )
            ->where('log_type = ?', $logType)
            ->where('created_at >= ?', $fromDate)
            ->group('DATE(created_at)')
            ->order('DATE(created_at) ASC');

        return $connection->fetchAll($select);
    }
}
