<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Category extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_help_category', 'category_id');
    }
}
