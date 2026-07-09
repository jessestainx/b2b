<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\Collection as CreditLimitCollection;
use Psr\Log\LoggerInterface;

class Collection extends CreditLimitCollection implements SearchResultInterface
{
    private AggregationInterface $aggregations;

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        string $mainTable = 'grupoawamotos_b2b_credit_limit',
        string $resourceModel = \GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit::class,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_mainTable = $mainTable;
        $this->_setIdFieldName('entity_id');
        $this->setModel(Document::class);
        $this->_init(Document::class, $resourceModel);
    }

    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }
    public function setAggregations($a): static
    {
        $this->aggregations = $a;
        return $this;
    }
    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }
    public function setSearchCriteria(SearchCriteriaInterface $s): static
    {
        return $this;
    }
    public function getTotalCount(): int
    {
        return $this->getSize();
    }
    public function setTotalCount($t): static
    {
        return $this;
    }
    public function setItems(?array $items = null): static
    {
        return $this;
    }
}
