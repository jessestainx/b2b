<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Adminhtml\Customer;

use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class SaveErp extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_B2B::approve';

    public function __construct(
        Context $context,
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $result = $this->jsonFactory->create();

        $body = json_decode($this->getRequest()->getContent(), true) ?? [];
        $id   = (int) ($body['id'] ?? 0);

        if (!$id) {
            return $result->setData(['success' => false, 'message' => 'ID inválido.']);
        }

        try {
            $b2b = $this->b2bCustomerFactory->create();
            $this->b2bCustomerResource->load($b2b, $id);

            if (!$b2b->getId()) {
                return $result->setData(['success' => false, 'message' => 'Cadastro não encontrado.']);
            }

            $b2b->setCodErp(trim((string) ($body['cod_erp'] ?? '')));
            $b2b->setListaPrecoErp(trim((string) ($body['lista_preco_erp'] ?? '')));
            $this->b2bCustomerResource->save($b2b);

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
