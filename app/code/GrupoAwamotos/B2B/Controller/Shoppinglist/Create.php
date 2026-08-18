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

class Create implements HttpPostActionInterface
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
        $name = trim((string) $this->request->getParam('name', ''));
        if ($name === '') {
            $this->messageManager->addErrorMessage(__('Informe o nome da lista.'));
            return $redirect->setPath('b2b/shoppinglist/index');
        }
        try {
            $this->shoppingListService->createList($name, '', (int) $this->customerSession->getCustomerId());
            $this->messageManager->addSuccessMessage(__('Lista "%1" criada.', $name));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[B2B ShoppingList Create] Falha ao criar lista.',
                ['exception' => $exception]
            );
            $this->messageManager->addErrorMessage(__('Não foi possível criar a lista agora. Tente novamente.'));
        }
        return $redirect->setPath('b2b/shoppinglist/index');
    }
}
