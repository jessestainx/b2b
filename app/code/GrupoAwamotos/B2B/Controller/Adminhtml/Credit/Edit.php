<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Credit;

use GrupoAwamotos\B2B\Model\CreditLimitFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit as CreditLimitResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::credit';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CreditLimitFactory $creditLimitFactory,
        private readonly CreditLimitResource $creditLimitResource
    ) { parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('entity_id');
        if ($id) {
            $limit = $this->creditLimitFactory->create();
            $this->creditLimitResource->load($limit, $id);
            if (!$limit->getId()) {
                $this->messageManager->addErrorMessage(__('Registro não encontrado.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_B2B::credit');
        $page->getConfig()->getTitle()->prepend($id ? __('Editar Limite') : __('Novo Limite'));
        return $page;
    }
}
