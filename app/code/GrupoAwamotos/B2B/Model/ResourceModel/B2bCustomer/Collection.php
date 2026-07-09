<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'b2b_customer_id';

    protected function _construct(): void
    {
        $this->_init(B2bCustomer::class, B2bCustomerResource::class);
    }

    public function addPendingFilter(): self
    {
        return $this->addFieldToFilter('status', B2bCustomer::STATUS_PENDING);
    }

    public function addApprovedFilter(): self
    {
        return $this->addFieldToFilter('status', B2bCustomer::STATUS_APPROVED);
    }
}
