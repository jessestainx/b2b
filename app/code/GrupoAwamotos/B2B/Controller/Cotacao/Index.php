<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Cotacao;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->guestLoginRedirect->create('b2b/quote/history');
        }

        return $this->redirectFactory->create()->setPath('b2b/quote/history');
    }
}
