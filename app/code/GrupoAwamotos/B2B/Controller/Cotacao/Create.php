<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Cotacao;

use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\Cotacao;
use GrupoAwamotos\B2B\Model\CotacaoFactory;
use GrupoAwamotos\B2B\Model\CotacaoItemFactory;
use GrupoAwamotos\B2B\Model\Email\Sender;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Model\ResourceModel\Cotacao as CotacaoResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CotacaoItem as CotacaoItemResource;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class Create implements HttpPostActionInterface
{
    public function __construct(
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly CotacaoFactory $cotacaoFactory,
        private readonly CotacaoItemFactory $cotacaoItemFactory,
        private readonly CotacaoResource $cotacaoResource,
        private readonly CotacaoItemResource $cotacaoItemResource,
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly Sender $sender,
        private readonly ManagerInterface $messageManager,
        private readonly RequestInterface $request,
        private readonly GuestLoginRedirect $guestLoginRedirect,
        private readonly LoggerInterface $logger,
        private readonly FormKeyValidator $formKeyValidator
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Formulário inválido. Atualize a página e tente novamente.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $this->guestLoginRedirect->create('checkout/cart');
        }

        $customerId = (int) $this->customerSession->getCustomerId();

        $row = $this->b2bCustomerResource->getByCustomerId($customerId);
        if (empty($row)) {
            $this->messageManager->addErrorMessage(__('Cadastro B2B não encontrado.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $b2b = $this->b2bCustomerFactory->create();
        $this->b2bCustomerResource->load($b2b, $row['b2b_customer_id']);

        $quote = $this->checkoutSession->getQuote();
        $cartItems = $quote->getAllVisibleItems();

        if (empty($cartItems)) {
            $this->messageManager->addWarningMessage(__('Seu carrinho está vazio.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $notes = trim((string) $this->request->getPost('notes', ''));

        $cotacao = $this->cotacaoFactory->create();
        $cotacao->setData([
            'b2b_customer_id' => $b2b->getId(),
            'customer_id'     => $customerId,
            'status'          => Cotacao::STATUS_PENDING,
            'notes'           => $notes,
        ]);
        $this->cotacaoResource->save($cotacao);

        foreach ($cartItems as $item) {
            $cotItem = $this->cotacaoItemFactory->create();
            $cotItem->setData([
                'cotacao_id'    => $cotacao->getId(),
                'product_id'    => (int) $item->getProductId(),
                'sku'           => $item->getSku(),
                'name'          => $item->getName(),
                'qty'           => $item->getQty(),
                'price_catalog' => $item->getPrice() ?? 0.0,
            ]);
            $this->cotacaoItemResource->save($cotItem);
        }

        try {
            $this->sender->sendCotacaoReceived($cotacao, $b2b);
        } catch (\Throwable $e) {
            // Non-fatal: the quote was already saved, don't fail the request over
            // an email delivery problem — but do log it, otherwise a broken mail
            // transport silently stops notifying the sales team with no trace.
            $this->logger->error('[B2B] Falha ao enviar e-mail de cotação recebida', [
                'cotacao_id' => $cotacao->getId(),
                'exception' => $e->getMessage(),
            ]);
        }

        $this->messageManager->addSuccessMessage(
            __('Cotação #%1 enviada com sucesso! Em breve nossa equipe retornará com os preços.', $cotacao->getId())
        );

        return $this->redirectFactory->create()->setPath('b2b/cotacao/view', ['id' => $cotacao->getId()]);
    }
}
