<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account\Cotacao;

use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao\CollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Index extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::cotacao/index.phtml';

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $collectionFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCotacoes(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $row = $this->b2bCustomerResource->getByCustomerId($customerId);
        if (empty($row)) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('b2b_customer_id', $row['b2b_customer_id']);
        $collection->setOrder('cotacao_id', 'DESC');

        return $collection->getItems();
    }

    public function getViewUrl(int $cotacaoId): string
    {
        return $this->getUrl('b2b/cotacao/view', ['id' => $cotacaoId]);
    }

    public function getNewQuoteUrl(): string
    {
        return $this->getUrl('b2b/quote');
    }
}
