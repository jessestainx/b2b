<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Cotacao extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_cotacao', 'cotacao_id');
    }
}
