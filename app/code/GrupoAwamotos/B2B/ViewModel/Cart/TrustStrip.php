<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel\Cart;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Contexto do trust strip B2B no carrinho (grupo aprovado + atalho ao painel).
 */
class TrustStrip implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly B2BHelper $b2bHelper,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function shouldDisplay(): bool
    {
        if (!$this->b2bHelper->isEnabled()) {
            return false;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        return $this->b2bHelper->isApprovedB2BCustomer();
    }

    public function getGroupName(): string
    {
        $groupId = (int) $this->customerSession->getCustomerGroupId();

        return $this->b2bHelper->getB2BGroupName($groupId);
    }

    public function getSectionAriaLabel(): string
    {
        return (string) __(
            'Conta B2B aprovada: %1',
            $this->getGroupName()
        );
    }

    public function getStatusLabel(): string
    {
        return (string) __('Conta B2B aprovada');
    }

    public function getLinkLabel(): string
    {
        return (string) __('Painel B2B');
    }

    public function getLinkAriaLabel(): string
    {
        return (string) __('Abrir painel B2B');
    }

    public function getDashboardUrl(): string
    {
        return $this->urlBuilder->getUrl('b2b/account');
    }
}
