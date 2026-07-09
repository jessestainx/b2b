<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Credit;

use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::credit';

    public function __construct(
        Context $context,
        private readonly CreditService $creditService,
        private readonly AdminSession $adminSession
    ) { parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }

        $customerId   = (int)   ($data['customer_id']   ?? 0);
        $limitValue   = (float) ($data['credit_limit']   ?? 0.0);
        $paymentTerms = (string)($data['payment_terms'] ?? '');

        if (!$customerId) {
            $this->messageManager->addErrorMessage(__('Customer ID obrigatório.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $adminUserId = (int) $this->adminSession->getUser()->getId();
            $this->creditService->setLimit($customerId, $limitValue, $adminUserId);
            if ($paymentTerms !== '') {
                $termsArray = array_filter(array_map('trim', explode(',', $paymentTerms)));
                if (!empty($termsArray)) {
                    $this->creditService->setPaymentTerms($customerId, $termsArray, $adminUserId);
                }
            }
            $this->messageManager->addSuccessMessage(__('Limite atualizado.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
