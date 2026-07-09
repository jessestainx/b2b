<?php

/**
 * Controller para responder cotação no admin
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Respond extends Action implements HttpGetActionInterface
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

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        Config $config
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->config = $config;
        parent::__construct($context);
    }

    /**
     * Execute action - show respond form
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

            // Verificar se pode responder
            $status = $quoteRequest->getStatus();
            if (!in_array($status, ['pending', 'processing'])) {
                $this->messageManager->addWarningMessage(
                    __('Esta cotação não pode ser respondida. Status atual: %1', $status)
                );
            }

            $resultPage = $this->resultPageFactory->create();
            $resultPage->setActiveMenu('GrupoAwamotos_B2B::quotes');
            $resultPage->getConfig()->getTitle()->prepend(
                __('Responder Cotação #%1', $quoteRequest->getRequestId())
            );

            return $resultPage;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
    }
}
