<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Account;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Dashboard implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $resultPageFactory,
        private readonly CustomerSession $customerSession,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    /**
     * @return Page|Redirect
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->guestLoginRedirect->create('b2b/account/dashboard');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Minha Conta B2B'));
        $resultPage->getConfig()->addBodyClass('b2b-account-dashboard');

        return $resultPage;
    }
}
