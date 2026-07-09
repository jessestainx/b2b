<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CotacaoItem;

use GrupoAwamotos\B2B\Model\CotacaoItem;
use GrupoAwamotos\B2B\Model\ResourceModel\CotacaoItem as CotacaoItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CotacaoItem::class, CotacaoItemResource::class);
    }
}
