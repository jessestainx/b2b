<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Company;

use GrupoAwamotos\B2B\Model\CompanyFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Company as CompanyResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::companies';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CompanyFactory $companyFactory,
        private readonly CompanyResource $companyResource
    ) { parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('company_id');
        if ($id) {
            $company = $this->companyFactory->create();
            $this->companyResource->load($company, $id);
            if (!$company->getId()) {
                $this->messageManager->addErrorMessage(__('Empresa não encontrada.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_B2B::companies');
        $page->getConfig()->getTitle()->prepend($id ? __('Editar Empresa') : __('Nova Empresa'));
        return $page;
    }
}
