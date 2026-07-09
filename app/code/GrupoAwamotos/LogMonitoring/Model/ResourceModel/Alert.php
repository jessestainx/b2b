<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Alert extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('awa_log_alerts', 'entity_id');
    }
}
