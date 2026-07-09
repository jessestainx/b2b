<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Cotacao;

use GrupoAwamotos\B2B\Model\Cotacao;
use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao as CotacaoResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Cotacao::class, CotacaoResource::class);
    }
}
