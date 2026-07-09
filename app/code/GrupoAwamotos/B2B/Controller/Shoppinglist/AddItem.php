<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Shoppinglist;

use GrupoAwamotos\B2B\Model\ShoppingListService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;

class AddItem implements HttpPostActionInterface
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
        $listId    = (int) $this->request->getParam('list_id');
        $productId = (int) $this->request->getParam('product_id');
        $qty       = (float) ($this->request->getParam('qty', 1) ?: 1);
        if (!$listId || !$productId) {
            return $result->setData(['success' => false, 'message' => __('Parâmetros ausentes.')->render()]);
        }
        try {
            $this->shoppingListService->addItem($listId, $productId, $qty);
            return $result->setData(['success' => true, 'message' => __('Item adicionado.')->render()]);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['success' => false, 'message' => __('Erro ao adicionar item. Tente novamente.')->render()]);
        }
    }
}
