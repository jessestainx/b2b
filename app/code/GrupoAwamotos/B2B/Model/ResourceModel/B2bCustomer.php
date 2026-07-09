<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class B2bCustomer extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_customer', 'b2b_customer_id');
    }

    public function getByCustomerId(int $customerId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('customer_id = ?', $customerId);

        return $connection->fetchRow($select) ?: [];
    }

    public function getByCnpj(string $cnpj): array
    {
        $cnpjClean = preg_replace('/[^0-9]/', '', $cnpj);
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('REPLACE(REPLACE(REPLACE(cnpj, ".", ""), "/", ""), "-", "") = ?', $cnpjClean);

        return $connection->fetchRow($select) ?: [];
    }
}
