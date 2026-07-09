<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Shoppinglist;

use GrupoAwamotos\B2B\Model\ShoppingListService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class RemoveItem implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly JsonFactory $resultJsonFactory,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ShoppingListService $shoppingListService
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Faça login.')->render()]);
        }
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['success' => false, 'message' => __('Requisição inválida.')->render()]);
        }
        $itemId = (int) $this->request->getParam('item_id');
        if (!$itemId) {
            return $result->setData(['success' => false, 'message' => __('Item não especificado.')->render()]);
        }
        try {
            $this->shoppingListService->removeItem($itemId);
            return $result->setData(['success' => true, 'message' => __('Item removido.')->render()]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
