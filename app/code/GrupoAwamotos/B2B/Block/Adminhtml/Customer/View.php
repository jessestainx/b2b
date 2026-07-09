<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\Customer;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Registry;

class View extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::customer/view.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getB2bCustomer(): ?B2bCustomer
    {
        return $this->registry->registry('current_b2b_customer');
    }

    public function getApproveUrl(): string
    {
        return $this->getUrl('b2b/customer/approve', ['id' => $this->getB2bCustomer()?->getB2bCustomerId()]);
    }

    public function getRejectUrl(): string
    {
        return $this->getUrl('b2b/customer/reject', ['id' => $this->getB2bCustomer()?->getB2bCustomerId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/customer/index');
    }

    public function getMagentoCustomerUrl(): ?string
    {
        $customerId = $this->getB2bCustomer()?->getCustomerId();
        if (!$customerId) {
            return null;
        }
        return $this->getUrl('customer/index/edit', ['id' => $customerId]);
    }

    public function getStatusBadge(int $status): string
    {
        return match ($status) {
            B2bCustomer::STATUS_APPROVED => '<span class="grid-severity-notice"><span>Aprovado</span></span>',
            B2bCustomer::STATUS_REJECTED => '<span class="grid-severity-critical"><span>Rejeitado</span></span>',
            default                      => '<span class="grid-severity-minor"><span>Pendente</span></span>',
        };
    }
}
