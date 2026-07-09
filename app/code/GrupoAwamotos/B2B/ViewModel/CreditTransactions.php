<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel;

use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction\Collection;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for Credit Transactions in Admin
 */
class CreditTransactions implements ArgumentInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Get transactions collection for current customer
     *
     * @return Collection
     */
    public function getTransactions(): Collection
    {
        $customerId = (int) $this->request->getParam('customer_id');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->setOrder('transaction_id', 'DESC')
            ->setPageSize(100);

        return $collection;
    }

    /**
     * Get current customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int) $this->request->getParam('customer_id');
    }
}
