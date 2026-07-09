<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Shoppinglist;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly PageFactory $resultPageFactory,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly RequestInterface $request
    ) {
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->set(__('Lista de Compra'));
        return $page;
    }
}
