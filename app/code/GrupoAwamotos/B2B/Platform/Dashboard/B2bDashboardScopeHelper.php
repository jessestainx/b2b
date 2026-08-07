<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\Model\Customer\B2bGroupIds;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Escopo read-only de clientes/pedidos B2B para agregações do dashboard.
 */
class B2bDashboardScopeHelper
{
    /**
     * @deprecated Use B2bGroupIds::resolve() — kept for BC of call sites expecting a const.
     * @var int[]
     */
    public const B2B_GROUP_IDS = [4, 5, 6, 8];

    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return list<int>
     */
    public function getB2bGroupIds(): array
    {
        return B2bGroupIds::resolve($this->resourceConnection);
    }

    public function canBypassPortfolioScope(): bool
    {
        return $this->portfolioScope->canBypassPortfolioScope();
    }

    /**
     * @return int[]
     */
    public function getVisibleCustomerIds(): array
    {
        return $this->portfolioScope->getVisibleCustomerIds();
    }

    /**
     * @return int[]
     */
    public function getVisibleAttendantIds(): array
    {
        return $this->portfolioScope->getVisibleAttendantIds();
    }

    /**
     * Base SELECT pedidos B2B com join em customer_entity.
     */
    public function createB2bOrderSelect(): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        $select = $connection->select()
            ->from(['so' => $orderTable])
            ->joinInner(['ce' => $customerTable], 'ce.entity_id = so.customer_id', [])
            ->where('ce.group_id IN (?)', $this->getB2bGroupIds());

        return $this->applyOrderCustomerScope($select);
    }

    /**
     * Base SELECT clientes B2B (grupos aprovados).
     */
    public function createB2bCustomerSelect(): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        $select = $connection->select()
            ->from(['ce' => $customerTable])
            ->where('ce.group_id IN (?)', $this->getB2bGroupIds());

        return $this->applyCustomerScope($select);
    }

    public function applyOrderCustomerScope(Select $select, string $orderAlias = 'so'): Select
    {
        if ($this->portfolioScope->canBypassPortfolioScope()) {
            return $select;
        }

        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            $select->where('1 = 0');

            return $select;
        }

        $select->where($orderAlias . '.customer_id IN (?)', $customerIds);

        return $select;
    }

    public function applyCustomerScope(Select $select, string $customerAlias = 'ce'): Select
    {
        if ($this->portfolioScope->canBypassPortfolioScope()) {
            return $select;
        }

        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            $select->where('1 = 0');

            return $select;
        }

        $select->where($customerAlias . '.entity_id IN (?)', $customerIds);

        return $select;
    }

    public function getTodayRange(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            'from' => $today . ' 00:00:00',
            'to' => $today . ' 23:59:59',
        ];
    }
}
