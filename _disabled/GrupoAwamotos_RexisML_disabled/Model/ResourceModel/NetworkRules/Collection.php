<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\ResourceModel\NetworkRules;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\RexisML\Model\NetworkRules::class,
            \GrupoAwamotos\RexisML\Model\ResourceModel\NetworkRules::class
        );
    }
}
