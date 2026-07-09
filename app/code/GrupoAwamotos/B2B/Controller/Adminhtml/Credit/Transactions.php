<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Credit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Transactions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::credit';

    public function __construct(Context $context, private readonly PageFactory $resultPageFactory)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('Customer ID obrigatório.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_B2B::credit');
        $page->getConfig()->getTitle()->prepend(__('Transações — Cliente #%1', $customerId));
        return $page;
    }
}
