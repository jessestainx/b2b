<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Topic extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_help_topic', 'topic_id');
    }

    /**
     * Increment view counter atomically.
     */
    public function incrementViews(int $topicId): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['views' => new \Zend_Db_Expr('views + 1')],
            ['topic_id = ?' => $topicId]
        );
    }
}
