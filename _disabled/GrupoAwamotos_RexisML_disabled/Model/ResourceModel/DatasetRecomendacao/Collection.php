<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\ResourceModel\DatasetRecomendacao;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\RexisML\Model\DatasetRecomendacao::class,
            \GrupoAwamotos\RexisML\Model\ResourceModel\DatasetRecomendacao::class
        );
    }
}
