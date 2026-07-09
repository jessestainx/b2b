<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Subscription extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('grupoawamotos_b2b_subscriptions', 'subscription_id');
    }
}
