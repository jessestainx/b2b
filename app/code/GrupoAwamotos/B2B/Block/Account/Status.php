<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Status extends Template
{
    protected $_template = 'GrupoAwamotos_B2B::account/status.phtml';

    private ?array $b2bData = null;

    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly B2bCustomerResource $b2bCustomerResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    public function getB2bData(): array
    {
        if ($this->b2bData !== null) {
            return $this->b2bData;
        }

        $customerId = $this->getCustomerId();
        if (!$customerId) {
            $this->b2bData = [];
            return [];
        }

        $this->b2bData = $this->b2bCustomerResource->getByCustomerId($customerId) ?: [];
        return $this->b2bData;
    }

    public function isB2B(): bool
    {
        return !empty($this->getB2bData());
    }

    public function getStatus(): int
    {
        return (int) ($this->getB2bData()['status'] ?? -1);
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            B2bCustomer::STATUS_PENDING  => __('Pendente de aprovação')->__toString(),
            B2bCustomer::STATUS_APPROVED => __('Aprovado')->__toString(),
            B2bCustomer::STATUS_REJECTED => __('Reprovado')->__toString(),
            default                       => __('Não cadastrado')->__toString(),
        };
    }

    public function getStatusClass(): string
    {
        return match ($this->getStatus()) {
            B2bCustomer::STATUS_PENDING  => 'pending',
            B2bCustomer::STATUS_APPROVED => 'approved',
            B2bCustomer::STATUS_REJECTED => 'rejected',
            default                       => '',
        };
    }

    public function getRegisterUrl(): string
    {
        return $this->getUrl('b2b/register');
    }
}
