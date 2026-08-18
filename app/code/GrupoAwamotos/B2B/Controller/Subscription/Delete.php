<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Subscription;

use GrupoAwamotos\B2B\Model\ResourceModel\Subscription as SubscriptionResource;
use GrupoAwamotos\B2B\Model\SubscriptionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
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
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $subscriptionId = (int) $this->request->getParam('id');

        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Requisição inválida. Recarregue a página e tente novamente.'));
            return $redirect->setPath('b2b/subscription/index');
        }

        if ($subscriptionId <= 0) {
            $this->messageManager->addErrorMessage(__('Assinatura não especificada.'));
            return $redirect->setPath('b2b/subscription/index');
        }

        try {
            $subscription = $this->subscriptionFactory->create();
            $this->subscriptionResource->load($subscription, $subscriptionId);

            if (!$subscription->getId()) {
                $this->messageManager->addErrorMessage(__('Assinatura não encontrada.'));
                return $redirect->setPath('b2b/subscription/index');
            }

            $customerId = (int) $this->customerSession->getCustomerId();
            if ((int) $subscription->getData('customer_id') !== $customerId) {
                $this->messageManager->addErrorMessage(__('Você não tem permissão para remover esta assinatura.'));
                return $redirect->setPath('b2b/subscription/index');
            }

            $this->subscriptionResource->delete($subscription);
            $this->messageManager->addSuccessMessage(__('Assinatura removida com sucesso.'));
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[B2B Subscription Delete] Falha ao remover assinatura.',
                [
                    'subscription_id' => $subscriptionId,
                    'customer_id' => (int) $this->customerSession->getCustomerId(),
                    'exception' => $exception
                ]
            );
            $this->messageManager->addErrorMessage(__('Não foi possível remover a assinatura agora. Tente novamente.'));
        }

        return $redirect->setPath('b2b/subscription/index');
    }
}
