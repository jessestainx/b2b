<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\ContactLog\CollectionFactory as ContactLogCollectionFactory;
use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use GrupoAwamotos\B2B\Model\Attendant\AttendantManager;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Agregador read-only de dados do cockpit comercial.
 */
class DashboardDataProvider
{
    /** @var string[] */
    private const OPEN_STATUSES = ['open', 'in_progress'];

    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly CurrentAttendant $currentAttendant,
        private readonly AttendantManager $attendantManager,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly TaskCollectionFactory $taskCollectionFactory,
        private readonly ContactLogCollectionFactory $contactLogCollectionFactory,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly TaskConfig $taskConfig
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        $attendant = $this->currentAttendant->get();

        $result = [
            'customer_count' => count($customerIds),
            'attendant_name' => $attendant['name'] ?? null,
            'can_view_all' => $this->portfolioScope->canViewAllPortfolios(),
            'pending_b2b_count' => $this->getPendingB2bCount(),
            'open_tasks_count' => $this->countOpenTasks(),
            'overdue_tasks_count' => $this->countOverdueTasks(),
            'abandoned_carts_count' => $this->countAbandonedCarts(),
            'customers_no_purchase_count' => $this->countCustomersNoPurchase(),
            'contacts_month_count' => $this->countContactsThisMonth(),
            'orders_30d_count' => $this->countRecentOrders(30),
        ];

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPortfolioClients(int $limit = 10): array
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return [];
        }

        if ($this->portfolioScope->canViewAllPortfolios()) {
            return $this->getPortfolioClientsForAllAttendants($limit);
        }

        $attendantId = $attendantIds[0];
        return $this->attendantManager->getAttendantCustomers($attendantId, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentOrders(int $limit = 15): array
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            return [];
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['in' => $customerIds]);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        $result = [];
        foreach ($collection as $order) {
            $result[] = [
                'entity_id' => (int) $order->getId(),
                'increment_id' => (string) $order->getIncrementId(),
                'customer_id' => (int) $order->getCustomerId(),
                'customer_name' => trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()),
                'grand_total' => (float) $order->getGrandTotal(),
                'status' => (string) $order->getStatus(),
                'created_at' => (string) $order->getCreatedAt(),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingTasks(int $limit = 10): array
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return [];
        }

        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);
        $collection->setOrder('due_at', 'ASC');
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        $tasks = $collection->toArray()['items'] ?? [];
        if ($tasks === []) {
            return [];
        }

        $customerIds = array_column($tasks, 'customer_id');
        $customerNames = $this->loadCustomerNames(array_map('intval', $customerIds));

        $typeLabels = $this->getTaskTypeLabels();
        $result = [];
        foreach ($tasks as $task) {
            $customerId = (int) ($task['customer_id'] ?? 0);
            $taskType = (string) ($task['task_type'] ?? '');
            $result[] = array_merge($task, [
                'customer_name' => trim(
                    ($customerNames[$customerId]['firstname'] ?? '') . ' '
                    . ($customerNames[$customerId]['lastname'] ?? '')
                ),
                'customer_email' => $customerNames[$customerId]['email'] ?? null,
                'task_type_label' => $typeLabels[$taskType] ?? $taskType,
            ]);
        }

        return $result;
    }

    /**
     * Prioridades do dia — tarefas abertas ordenadas por prazo (vencidas primeiro).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTodayPriorities(int $limit = 15): array
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return [];
        }

        $now = date('Y-m-d H:i:s');
        $endOfDay = date('Y-m-d 23:59:59');

        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);
        $collection->getSelect()->where(
            '(due_at IS NULL OR due_at <= ?)',
            $endOfDay
        );
        $collection->setOrder('due_at', 'ASC');
        $collection->setPageSize($limit);

        $tasks = $collection->toArray()['items'] ?? [];
        if ($tasks === []) {
            return [];
        }

        $customerIds = array_column($tasks, 'customer_id');
        $customerNames = $this->loadCustomerNames(array_map('intval', $customerIds));
        $typeLabels = $this->getTaskTypeLabels();

        $result = [];
        foreach ($tasks as $task) {
            $customerId = (int) ($task['customer_id'] ?? 0);
            $taskId = (int) ($task['task_id'] ?? 0);
            $dueAt = $task['due_at'] ?? null;
            $isOverdue = $dueAt !== null && $dueAt < $now;
            $taskType = (string) ($task['task_type'] ?? '');

            $result[] = array_merge($task, [
                'task_id' => $taskId,
                'customer_id' => $customerId,
                'customer_name' => trim(
                    ($customerNames[$customerId]['firstname'] ?? '') . ' '
                    . ($customerNames[$customerId]['lastname'] ?? '')
                ),
                'task_type_label' => $typeLabels[$taskType] ?? $taskType,
                'is_overdue' => $isOverdue,
            ]);
        }

        usort($result, static function (array $a, array $b): int {
            if (($a['is_overdue'] ?? false) !== ($b['is_overdue'] ?? false)) {
                return ($b['is_overdue'] ?? false) <=> ($a['is_overdue'] ?? false);
            }

            return strcmp((string) ($a['due_at'] ?? ''), (string) ($b['due_at'] ?? ''));
        });

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAbandonedCarts(int $limit = 10): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');

        if (!$connection->isTableExists($table)) {
            return [];
        }

        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if (!$this->portfolioScope->canBypassPortfolioScope() && $customerIds === []) {
            return [];
        }

        $select = $connection->select()
            ->from($table)
            ->where('recovered = ?', 0)
            ->where('status != ?', 'recovered')
            ->order('abandoned_at DESC')
            ->limit($limit);

        if (!$this->portfolioScope->canBypassPortfolioScope()) {
            $select->where('customer_id IN (?)', $customerIds);
        }

        return $connection->fetchAll($select);
    }

    public function getPendingB2bCount(): int
    {
        if (!$this->portfolioScope->canViewAllPortfolios()) {
            return 0;
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter(
            'b2b_approval_status',
            ['in' => [ApprovalStatus::STATUS_PENDING, 'data_review']]
        );

        return (int) $collection->getSize();
    }

    private function countOpenTasks(): int
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return 0;
        }

        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);

        return (int) $collection->getSize();
    }

    private function countOverdueTasks(): int
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);
        $collection->addFieldToFilter('due_at', ['notnull' => true]);
        $collection->addFieldToFilter('due_at', ['lt' => $now]);

        return (int) $collection->getSize();
    }

    private function countContactsThisMonth(): int
    {
        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            return 0;
        }

        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
        $collection = $this->contactLogCollectionFactory->create();
        $collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
        $collection->addFieldToFilter('created_at', ['gteq' => $monthStart]);

        return (int) $collection->getSize();
    }

    private function countCustomersNoPurchase(): int
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            return 0;
        }

        $days = $this->taskConfig->getDaysNoPurchase();
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $withRecentOrders = $connection->fetchCol(
            $connection->select()
                ->from($orderTable, ['customer_id'])
                ->where('customer_id IN (?)', $customerIds)
                ->where('created_at >= ?', $since)
                ->group('customer_id')
        );

        return count($customerIds) - count(array_unique(array_map('intval', $withRecentOrders)));
    }

    private function countAbandonedCarts(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');

        if (!$connection->isTableExists($table)) {
            return 0;
        }

        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if (!$this->portfolioScope->canBypassPortfolioScope() && $customerIds === []) {
            return 0;
        }

        $select = $connection->select()
            ->from($table, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where('recovered = ?', 0)
            ->where('status != ?', 'recovered');

        if (!$this->portfolioScope->canBypassPortfolioScope()) {
            $select->where('customer_id IN (?)', $customerIds);
        }

        return (int) $connection->fetchOne($select);
    }

    private function countRecentOrders(int $days): int
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            return 0;
        }

        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['in' => $customerIds]);
        $collection->addFieldToFilter('created_at', ['gteq' => $since]);

        return (int) $collection->getSize();
    }

    /**
     * @return array<string, string>
     */
    private function getTaskTypeLabels(): array
    {
        return [
            TaskType::NO_PURCHASE => (string) __('Sem compra'),
            TaskType::PENDING_NO_CONTACT => (string) __('Pendente sem contato'),
            TaskType::QUOTE_NO_RESPONSE => (string) __('Cotação sem retorno'),
            TaskType::ABANDONED_CART => (string) __('Carrinho abandonado'),
            TaskType::NEW_CUSTOMER_NO_CONTACT => (string) __('Novo sem atendimento'),
            TaskType::MANUAL => (string) __('Manual'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getPortfolioClientsForAllAttendants(int $limit): array
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $mappingTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_attendant');
        $attendantTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');

        $select = $connection->select()
            ->from(['ca' => $mappingTable], ['customer_id', 'commercial_status', 'assigned_at'])
            ->join(
                ['a' => $attendantTable],
                'ca.attendant_id = a.attendant_id',
                ['attendant_name' => 'name']
            )
            ->where('ca.customer_id IN (?)', $customerIds)
            ->order('ca.assigned_at DESC')
            ->limit($limit);

        $rows = $connection->fetchAll($select);
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['customer_id'], $rows);
        $customerNames = $this->loadCustomerNames($ids);

        $result = [];
        foreach ($rows as $row) {
            $customerId = (int) $row['customer_id'];
            $result[] = [
                'customer_id' => $customerId,
                'commercial_status' => $row['commercial_status'],
                'assigned_at' => $row['assigned_at'],
                'attendant_name' => $row['attendant_name'],
                'firstname' => $customerNames[$customerId]['firstname'] ?? null,
                'lastname' => $customerNames[$customerId]['lastname'] ?? null,
                'email' => $customerNames[$customerId]['email'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array{firstname: ?string, lastname: ?string, email: ?string}>
     */
    private function loadCustomerNames(array $customerIds): array
    {
        $customerIds = array_values(array_filter(array_unique($customerIds)));
        if ($customerIds === []) {
            return [];
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $collection->addAttributeToSelect(['firstname', 'lastname', 'email']);

        $result = [];
        foreach ($collection as $customer) {
            $result[(int) $customer->getId()] = [
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'email' => $customer->getEmail(),
            ];
        }

        return $result;
    }
}
