<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model\ResourceModel\LogMetrics;

use GrupoAwamotos\LogMonitoring\Model\LogMetrics;
use GrupoAwamotos\LogMonitoring\Model\ResourceModel\LogMetrics as LogMetricsResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(LogMetrics::class, LogMetricsResource::class);
    }

    public function addLogTypeFilter(string $logType): self
    {
        return $this->addFieldToFilter('log_type', $logType);
    }

    public function addDateRangeFilter(string $from, string $to): self
    {
        return $this->addFieldToFilter(
            'created_at',
            ['from' => $from, 'to' => $to]
        );
    }

    public function addErrorCountFilter(int $minErrors = 1): self
    {
        return $this->addFieldToFilter('error_entries', ['gteq' => $minErrors]);
    }

    public function orderByLatest(): self
    {
        return $this->setOrder('created_at', 'DESC');
    }
}
