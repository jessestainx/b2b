<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CotacaoItem extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_cotacao_item', 'item_id');
    }

    public function getItemsByCotacaoId(int $cotacaoId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('cotacao_id = ?', $cotacaoId)
            ->order('item_id ASC');

        return $connection->fetchAll($select);
    }
}
