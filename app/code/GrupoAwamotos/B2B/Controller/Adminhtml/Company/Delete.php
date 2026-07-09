<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Company;

use GrupoAwamotos\B2B\Model\CompanyFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Company as CompanyResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::companies';

    public function __construct(
        Context $context,
        private readonly CompanyFactory $companyFactory,
        private readonly CompanyResource $companyResource
    ) { parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('company_id');
        if (!$id) {
            return $redirect->setPath('*/*/');
        }
        try {
            $company = $this->companyFactory->create();
            $this->companyResource->load($company, $id);
            if ($company->getId()) {
                $this->companyResource->delete($company);
            }
            $this->messageManager->addSuccessMessage(__('Empresa excluída.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro: %1', $e->getMessage()));
        }
        return $redirect->setPath('*/*/');
    }
}
