<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\ResourceModel\MetricasConversao;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\RexisML\Model\MetricasConversao;
use GrupoAwamotos\RexisML\Model\ResourceModel\MetricasConversao as MetricasConversaoResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(MetricasConversao::class, MetricasConversaoResource::class);
    }
}
