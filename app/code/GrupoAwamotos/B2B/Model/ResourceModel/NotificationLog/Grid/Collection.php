<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\NotificationLog\Grid;

use GrupoAwamotos\B2B\Model\ResourceModel\NotificationLog;
use GrupoAwamotos\B2B\Model\ResourceModel\NotificationLog\Collection as NotificationLogCollection;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Psr\Log\LoggerInterface;

class Collection extends NotificationLogCollection implements SearchResultInterface
{
    private AggregationInterface $aggregations;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'grupoawamotos_b2b_notifications',
        string $resourceModel = NotificationLog::class,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_mainTable = $mainTable;
        $this->_setIdFieldName('notification_id');
        $this->setModel(Document::class);
        $this->_init(Document::class, $resourceModel);
    }

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations): static
    {
        $this->aggregations = $aggregations;

        return $this;
    }

    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria): static
    {
        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount): static
    {
        return $this;
    }

    public function setItems(?array $items = null): static
    {
        return $this;
    }
}
