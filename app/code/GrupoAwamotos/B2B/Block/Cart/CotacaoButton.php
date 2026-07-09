<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Cart;

use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class CotacaoButton extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::cotacao/cart-button.phtml';

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly B2bCustomerResource $b2bCustomerResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isB2bApproved(): bool
    {
        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }
        $row = $this->b2bCustomerResource->getByCustomerId(
            (int) $this->customerSession->getCustomerId()
        );
        return !empty($row) && (int) ($row['status'] ?? 0) === 1;
    }

    public function getCreateUrl(): string
    {
        return $this->getUrl('b2b/cotacao/create');
    }
}
