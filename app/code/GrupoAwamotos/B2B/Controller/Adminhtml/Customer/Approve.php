<?php

/**
 * Admin Approve Customer Controller
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Approve extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customer_approval';

    /**
     * @var CustomerApprovalInterface
     */
    private $customerApproval;

    /**
     * @var AdminSession
     */
    private $adminSession;

    public function __construct(
        Context $context,
        CustomerApprovalInterface $customerApproval,
        AdminSession $adminSession
    ) {
        $this->customerApproval = $customerApproval;
        $this->adminSession = $adminSession;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();

        $customerId = (int) $this->getRequest()->getParam('customer_id');

        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('ID do cliente não informado.'));
            return $redirect->setPath('*/*/pending');
        }

        try {
            $adminUserId = $this->adminSession->getUser()->getId();
            $comment = $this->getRequest()->getParam('comment');

            $this->customerApproval->approveCustomer($customerId, (int) $adminUserId, $comment);

            $this->messageManager->addSuccessMessage(
                __('Cliente #%1 aprovado com sucesso!', $customerId)
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Erro ao aprovar cliente: %1', $e->getMessage())
            );
        }

        return $redirect->setPath('*/*/pending');
    }
}
