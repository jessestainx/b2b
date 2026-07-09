<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Company;

use GrupoAwamotos\B2B\Model\CustomerApproval;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Approve extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::companies';

    public function __construct(
        Context $context,
        private readonly CustomerApproval $customerApproval,
        private readonly AdminSession $adminSession
    ) { parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $comment    = (string) $this->getRequest()->getParam('comment', '');
        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('Customer ID inválido.'));
            return $redirect->setPath('*/*/');
        }
        try {
            $adminUserId = (int) $this->adminSession->getUser()->getId();
            $this->customerApproval->approveCustomer($customerId, $adminUserId, $comment);
            $this->messageManager->addSuccessMessage(__('Cliente aprovado.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro: %1', $e->getMessage()));
        }
        return $redirect->setPath('*/*/index');
    }
}
