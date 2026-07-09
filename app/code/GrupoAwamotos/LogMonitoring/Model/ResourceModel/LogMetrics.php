<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class LogMetrics extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('awa_log_metrics', 'entity_id');
    }
}
