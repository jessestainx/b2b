<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Company;

use GrupoAwamotos\B2B\Model\CompanyFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Company as CompanyResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends Action implements HttpPostActionInterface
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
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = isset($data['company_id']) ? (int)$data['company_id'] : 0;
        try {
            $company = $this->companyFactory->create();
            if ($id) {
                $this->companyResource->load($company, $id);
                if (!$company->getId()) {
                    throw new \RuntimeException('Empresa não encontrada.');
                }
            }
            $company->setData(array_merge($company->getData(), $data));
            $this->companyResource->save($company);
            $this->messageManager->addSuccessMessage(__('Empresa salva.'));
            return $redirect->setPath('*/*/index');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Erro: %1', $e->getMessage()));
        }
        return $redirect->setPath('*/*/edit', ['company_id' => $id]);
    }
}
