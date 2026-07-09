<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Subscription;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\Subscription::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\Subscription::class
        );
    }

    /**
     * Filter by customer
     *
     * @param int $customerId
     * @return $this
     */
    public function filterByCustomer(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter active subscriptions
     *
     * @return $this
     */
    public function filterActive(): self
    {
        $this->addFieldToFilter('status', \GrupoAwamotos\B2B\Model\Subscription::STATUS_ACTIVE);
        return $this;
    }

    /**
     * Filter subscriptions due for run
     *
     * @return $this
     */
    public function filterDue(): self
    {
        $this->filterActive()
             ->addFieldToFilter('next_run_at', ['lteq' => date('Y-m-d H:i:s')]);
        return $this;
    }
}
