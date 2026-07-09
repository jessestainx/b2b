<?php

/**
 * Filtra o grid de pedidos no admin para atendentes verem apenas pedidos dos seus clientes.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Admin;

use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Magento\Framework\App\ResourceConnection;

class AttendantOrderGridPlugin
{
    public function __construct(
        private readonly CurrentAttendant $currentAttendant,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Restringe o grid de pedidos aos clientes do atendente logado.
     *
     * @param Collection $subject
     * @param mixed $printQuery
     * @param mixed $logQuery
     * @return array
     */
    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false): array
    {
        if (!$subject->isLoaded() && $this->currentAttendant->isAttendant()) {
            $customerIds = $this->getAssignedCustomerIds();
            $subject->addFieldToFilter('customer_id', ['in' => $customerIds ?: [0]]);
        }

        return [$printQuery, $logQuery];
    }

    /**
     * Retorna os IDs dos clientes atribuídos ao atendente logado.
     *
     * @return int[]
     */
    private function getAssignedCustomerIds(): array
    {
        $attendantId = $this->currentAttendant->getId();
        if (!$attendantId) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('grupoawamotos_b2b_customer_attendant');

        return array_map(
            'intval',
            $connection->fetchCol(
                $connection->select()
                    ->from($table, 'customer_id')
                    ->where('attendant_id = ?', $attendantId)
            )
        );
    }
}
