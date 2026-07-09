<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Cotacao;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\CotacaoFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao as CotacaoResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly CotacaoFactory $cotacaoFactory,
        private readonly CotacaoResource $cotacaoResource,
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly Registry $registry,
        private readonly RequestInterface $request,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            $id = (int) $this->request->getParam('id');
            return $this->guestLoginRedirect->create('b2b/cotacao/view', $id > 0 ? ['id' => $id] : []);
        }

        $id = (int) $this->request->getParam('id');
        $cotacao = $this->cotacaoFactory->create();
        $this->cotacaoResource->load($cotacao, $id);

        if (!$cotacao->getId()) {
            return $this->redirectFactory->create()->setPath('b2b/cotacao');
        }

        // Verify ownership
        $b2b = $this->b2bCustomerFactory->create();
        $this->b2bCustomerResource->load($b2b, $cotacao->getB2bCustomerId());
        if (!$b2b->getId() || (int) $b2b->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
            return $this->redirectFactory->create()->setPath('b2b/cotacao');
        }

        $this->registry->register('current_cotacao', $cotacao);

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Cotação #%1', $id));
        return $page;
    }
}
