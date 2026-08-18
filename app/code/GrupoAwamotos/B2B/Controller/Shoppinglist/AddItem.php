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
use Psr\Log\LoggerInterface;

class AddItem implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly JsonFactory $resultJsonFactory,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ShoppingListService $shoppingListService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Faça login.')]);
        }
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['success' => false, 'message' => __('Requisição inválida.')]);
        }
        $listId    = (int) $this->request->getParam('list_id');
        $productId = (int) $this->request->getParam('product_id');
        $qty       = (float) ($this->request->getParam('qty', 1) ?: 1);
        if (!$listId || !$productId) {
            return $result->setData(['success' => false, 'message' => __('Parâmetros ausentes.')]);
        }
        try {
            $this->shoppingListService->addItem($listId, $productId, $qty);
            return $result->setData(['success' => true, 'message' => __('Item adicionado.')]);
        } catch (LocalizedException $exception) {
            return $result->setData(['success' => false, 'message' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[B2B ShoppingList AddItem] Falha ao adicionar item.',
                [
                    'list_id' => $listId,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'exception' => $exception
                ]
            );

            return $result->setData([
                'success' => false,
                'message' => __('Erro ao adicionar item. Tente novamente.')
            ]);
        }
    }
}
