<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Catalog;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    public function execute(): mixed
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->guestLoginRedirect->create('b2b/catalog');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Meu Catálogo Exclusivo'));

        return $page;
    }
}
