<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Helper\GuestLoginRedirect;
use GrupoAwamotos\B2B\Model\QuoteRequestFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class CloneQuote implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly QuoteRequestRepositoryInterface $quoteRequestRepository,
        private readonly QuoteRequestFactory $quoteRequestFactory,
        private readonly Config $config,
        private readonly DateTime $dateTime,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger,
        private readonly GuestLoginRedirect $guestLoginRedirect
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->formKeyValidator->validate($this->request) || !$this->customerSession->isLoggedIn()) {
            if (!$this->customerSession->isLoggedIn()) {
                return $this->guestLoginRedirect->create('b2b/quote/history');
            }

            return $redirect->setPath('b2b/quote/history');
        }

        $requestId = (int) $this->request->getParam('id');
        if ($requestId <= 0) {
            $this->messageManager->addErrorMessage(__('Cotação não informada.'));
            return $redirect->setPath('b2b/quote/history');
        }

        try {
            $source = $this->quoteRequestRepository->getById($requestId);
            $customerId = (int) $this->customerSession->getCustomerId();

            if ((int) $source->getCustomerId() !== $customerId) {
                throw new LocalizedException(__('Acesso negado.'));
            }

            $items = $source->getItems();
            if ($items === []) {
                throw new LocalizedException(__('A cotação não possui itens para clonar.'));
            }

            $clonedItems = [];
            foreach ($items as $item) {
                unset($item['quoted_price']);
                $clonedItems[] = $item;
            }

            $expiryDays = $this->config->getQuoteExpiryDays();
            $expiresAt = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

            $clone = $this->quoteRequestFactory->create();
            $clone->setCustomerId($customerId);
            $clone->setCustomerEmail($source->getCustomerEmail());
            $clone->setCustomerName($source->getCustomerName());
            $clone->setCompanyName($source->getCompanyName());
            $clone->setCnpj($source->getCnpj());
            $clone->setPhone($source->getPhone());
            $clone->setStatus(QuoteRequestInterface::STATUS_PENDING);
            $clone->setItems($clonedItems);
            $clone->setMessage(
                (string) __('Clonada da cotação #%1', $source->getRequestId())
            );
            $clone->setExpiresAt($expiresAt);

            $saved = $this->quoteRequestRepository->save($clone);

            $this->messageManager->addSuccessMessage(
                __('Nova cotação #%1 criada a partir da cotação #%2.', $saved->getRequestId(), $source->getRequestId())
            );

            return $redirect->setPath('b2b/quote/view', ['id' => $saved->getRequestId()]);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B Quote Clone] ' . $exception->getMessage());
            $this->messageManager->addErrorMessage(__('Erro ao clonar cotação. Tente novamente.'));
        }

        return $redirect->setPath('b2b/quote/history');
    }
}
