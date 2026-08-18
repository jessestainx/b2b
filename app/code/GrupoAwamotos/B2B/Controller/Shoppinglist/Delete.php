<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Shoppinglist;

use GrupoAwamotos\B2B\Model\ShoppingListService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Psr\Log\LoggerInterface;

class Delete implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly MessageManagerInterface $messageManager,
        private readonly ShoppingListService $shoppingListService,
        private readonly LoggerInterface $logger
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
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[B2B ShoppingList Delete] Falha ao excluir lista.',
                ['list_id' => $listId, 'exception' => $exception]
            );
            $this->messageManager->addErrorMessage(__('Não foi possível excluir a lista agora. Tente novamente.'));
        }
        return $redirect->setPath('b2b/shoppinglist/index');
    }
}
