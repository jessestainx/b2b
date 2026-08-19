<?php

/**
 * View Quote Request Detail Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    private PageFactory $pageFactory;
    private CustomerSession $customerSession;
    private RedirectFactory $redirectFactory;
    private ManagerInterface $messageManager;
    private RequestInterface $request;
    private QuoteRequestRepositoryInterface $quoteRequestRepository;

    public function __construct(
        PageFactory $pageFactory,
        CustomerSession $customerSession,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        RequestInterface $request,
        QuoteRequestRepositoryInterface $quoteRequestRepository
    ) {
        $this->pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->request = $request;
        $this->quoteRequestRepository = $quoteRequestRepository;
    }

    public function execute()
    {

        $requestId = (int) $this->request->getParam('id');

        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addNoticeMessage(__('Faça login para ver suas cotações.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/account/login');
        }

        if (!$requestId) {
            $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/quote/history');
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/quote/history');
        }

        // Verify ownership
        $customerId = (int) $this->customerSession->getCustomerId();
        if ((int) $quoteRequest->getCustomerId() !== $customerId) {
            $this->messageManager->addErrorMessage(__('Você não tem permissão para visualizar esta cotação.'));
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('b2b/quote/history');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Cotação #%1', $requestId));

        return $page;
    }
}
