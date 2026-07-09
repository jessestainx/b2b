<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Account;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * GET /b2b/account/orders
 * Histórico de pedidos do cliente B2B com status ERP.
 */
class Orders implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->guestLoginRedirect->create('b2b/account/orders');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Meus Pedidos B2B'));

        return $page;
    }
}
