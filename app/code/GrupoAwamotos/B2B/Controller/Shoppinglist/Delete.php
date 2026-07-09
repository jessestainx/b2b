<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Shoppinglist;

use GrupoAwamotos\B2B\Model\ShoppingListService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class Delete implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManagerInterface $messageManager,
        private readonly ShoppingListService $shoppingListService
    ) {
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Requisição inválida.'));
            return $redirect->setPath('b2b/shoppinglist/index');
        }
        $listId = (int) $this->request->getParam('id');
        if (!$listId) {
            $this->messageManager->addErrorMessage(__('Lista não especificada.'));
            return $redirect->setPath('b2b/shoppinglist/index');
        }
        try {
            $this->shoppingListService->deleteList($listId);
            $this->messageManager->addSuccessMessage(__('Lista excluída.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Erro: %1', $e->getMessage()));
        }
        return $redirect->setPath('b2b/shoppinglist/index');
    }
}
