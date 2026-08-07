<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Cotacao;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\Cotacao;
use GrupoAwamotos\B2B\Model\CotacaoFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao as CotacaoResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;

class Respond implements HttpPostActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly CotacaoFactory $cotacaoFactory,
        private readonly CotacaoResource $cotacaoResource,
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly ManagerInterface $messageManager,
        private readonly RequestInterface $request,
        private readonly GuestLoginRedirect $guestLoginRedirect,
        private readonly FormKeyValidator $formKeyValidator
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Formulário inválido. Atualize a página e tente novamente.'));
            return $this->redirectFactory->create()->setPath('b2b/cotacao');
        }

        if (!$this->customerSession->isLoggedIn()) {
            $id = (int) $this->request->getPost('cotacao_id');
            return $this->guestLoginRedirect->create('b2b/cotacao/view', $id > 0 ? ['id' => $id] : []);
        }

        $id     = (int) $this->request->getPost('cotacao_id');
        $action = (string) $this->request->getPost('action');

        $cotacao = $this->cotacaoFactory->create();
        $this->cotacaoResource->load($cotacao, $id);

        if (!$cotacao->getId() || !$cotacao->isQuoted()) {
            $this->messageManager->addErrorMessage(__('Cotação inválida ou não disponível para resposta.'));
            return $this->redirectFactory->create()->setPath('b2b/cotacao');
        }

        // Verify ownership
        $b2b = $this->b2bCustomerFactory->create();
        $this->b2bCustomerResource->load($b2b, $cotacao->getB2bCustomerId());
        if (!$b2b->getId() || (int) $b2b->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
            return $this->redirectFactory->create()->setPath('b2b/cotacao');
        }

        if ($action === 'accept') {
            $cotacao->setData('status', Cotacao::STATUS_ACCEPTED);
            $this->cotacaoResource->save($cotacao);
            $this->messageManager->addSuccessMessage(
                __('Cotação aceita! Nossa equipe entrará em contato para finalizar o pedido.')
            );
        } elseif ($action === 'reject') {
            $cotacao->setData('status', Cotacao::STATUS_REJECTED);
            $this->cotacaoResource->save($cotacao);
            $this->messageManager->addNoticeMessage(__('Cotação recusada.'));
        }

        return $this->redirectFactory->create()->setPath('b2b/cotacao/view', ['id' => $id]);
    }
}
