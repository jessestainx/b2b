<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::customers';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $id = (int) $this->getRequest()->getParam('id');

        $b2bCustomer = $this->b2bCustomerFactory->create();
        $this->b2bCustomerResource->load($b2bCustomer, $id);

        if (!$b2bCustomer->getB2bCustomerId()) {
            $this->messageManager->addErrorMessage(__('Cadastro B2B não encontrado.'));
            return $this->resultRedirectFactory->create()->setPath('b2b/customer/index');
        }

        $this->registry->register('current_b2b_customer', $b2bCustomer);

        $page = $this->pageFactory->create();
        $page->setActiveMenu('GrupoAwamotos_B2B::customers');
        $page->getConfig()->getTitle()->prepend(__(
            'B2B: %1 (%2)',
            $b2bCustomer->getRazaoSocial(),
            $b2bCustomer->getCnpj()
        ));

        return $page;
    }
}
