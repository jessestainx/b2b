<?php

/**
 * Admin Quote Request View Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::quotes';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var QuoteRequestRepositoryInterface
     */
    private $quoteRequestRepository;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        QuoteRequestRepositoryInterface $quoteRequestRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->quoteRequestRepository = $quoteRequestRepository;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $requestId = (int) $this->getRequest()->getParam('id');

        if (!$requestId) {
            $this->messageManager->addErrorMessage(__('ID da cotação não informado.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        try {
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);

            $resultPage = $this->resultPageFactory->create();
            $resultPage->setActiveMenu('GrupoAwamotos_B2B::quotes');
            $resultPage->getConfig()->getTitle()->prepend(
                __('Cotação #%1', $quoteRequest->getRequestId())
            );

            return $resultPage;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
    }
}
