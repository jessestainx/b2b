<?php

/**
 * Accept Quote Request Controller
 *
 * Converts quoted items to Magento cart with custom prices and redirects to checkout.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Quote;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Accept implements HttpPostActionInterface
{
    private RequestInterface $request;
    private RedirectFactory $redirectFactory;
    private CustomerSession $customerSession;
    private QuoteRequestRepositoryInterface $quoteRequestRepository;
    private ProductRepositoryInterface $productRepository;
    private Cart $cart;
    private FormKeyValidator $formKeyValidator;
    private ManagerInterface $messageManager;
    private LoggerInterface $logger;
    private EventManagerInterface $eventManager;
    private StoreManagerInterface $storeManager;
    private ResourceConnection $resourceConnection;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        CustomerSession $customerSession,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        ProductRepositoryInterface $productRepository,
        Cart $cart,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        EventManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->customerSession = $customerSession;
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->productRepository = $productRepository;
        $this->cart = $cart;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $requestId = 0;
        $customerId = 0;
        $statusLockAcquired = false;

        try {
            // Validate form key
            if (!$this->formKeyValidator->validate($this->request)) {
                $this->messageManager->addErrorMessage(__('Formulário inválido. Tente novamente.'));
                return $redirect->setPath('b2b/quote/history');
            }

            // Verify customer is logged in
            if (!$this->customerSession->isLoggedIn()) {
                $this->messageManager->addErrorMessage(__('Faça login para aceitar a cotação.'));
                return $redirect->setPath('b2b/account/login');
            }

            $requestId = (int) $this->request->getParam('id');
            if (!$requestId) {
                $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
                return $redirect->setPath('b2b/quote/history');
            }

            // Load quote request
            $quoteRequest = $this->quoteRequestRepository->getById($requestId);
            $previousStatus = $quoteRequest->getStatus();

            // Verify ownership
            $customerId = (int) $this->customerSession->getCustomerId();
            if ((int) $quoteRequest->getCustomerId() !== $customerId) {
                $this->messageManager->addErrorMessage(__('Você não tem permissão para acessar esta cotação.'));
                return $redirect->setPath('b2b/quote/history');
            }

            // Verify status is quoted and not expired
            if ($quoteRequest->getStatus() !== QuoteRequestInterface::STATUS_QUOTED) {
                $this->messageManager->addErrorMessage(__('Esta cotação não pode ser aceita no momento.'));
                return $redirect->setPath('b2b/quote/view', ['id' => $requestId]);
            }

            $expiresAt = $quoteRequest->getExpiresAt();
            if (is_string($expiresAt) && $expiresAt !== '' && strtotime($expiresAt) < time()) {
                $this->messageManager->addErrorMessage(__('Esta cotação expirou. Solicite uma nova cotação.'));
                return $redirect->setPath('b2b/quote/view', ['id' => $requestId]);
            }

            $statusLockAcquired = $this->lockQuotedRequest($requestId, $customerId);
            if (!$statusLockAcquired) {
                $this->messageManager->addErrorMessage(
                    __('Esta cotação já está sendo processada ou já foi aceita em outra sessão.')
                );
                return $redirect->setPath('b2b/quote/view', ['id' => $requestId]);
            }

            // Clear current cart
            $this->cart->truncate();

            // Add quoted items to cart
            $items = $quoteRequest->getItems();
            $added = 0;
            $failed = [];

            foreach ($items as $item) {
                try {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $sku = $item['sku'] ?? '';
                    $qty = (float) ($item['qty'] ?? 1);

                    if ($productId) {
                        $product = $this->productRepository->getById($productId);
                    } elseif ($sku) {
                        $product = $this->productRepository->get($sku);
                    } else {
                        continue;
                    }

                    $this->cart->addProduct($product, new DataObject(['qty' => $qty]));
                    $added++;
                } catch (\Exception $e) {
                    $failed[] = $sku ?: "ID {$productId}";
                    $this->logger->warning(
                        sprintf('[B2B Quote Accept] Produto %s nao adicionado: %s', $sku, $e->getMessage())
                    );
                }
            }

            if ($added === 0) {
                $this->restoreQuotedStatus($requestId);
                $statusLockAcquired = false;
                $this->messageManager->addErrorMessage(
                    __('Nenhum produto da cotação pôde ser adicionado ao carrinho. Produtos podem estar indisponíveis.')
                );
                return $redirect->setPath('b2b/quote/view', ['id' => $requestId]);
            }

            // Save cart first to generate quote items
            $this->cart->save();

            // Apply quoted prices to cart items
            $magentoQuote = $this->cart->getQuote();
            foreach ($magentoQuote->getAllVisibleItems() as $quoteItem) {
                $sku = $quoteItem->getSku();
                foreach ($items as $item) {
                    if (($item['sku'] ?? '') === $sku && !empty($item['quoted_price'])) {
                        $quotedPrice = (float) $item['quoted_price'];
                        $quoteItem->setCustomPrice($quotedPrice);
                        $quoteItem->setOriginalCustomPrice($quotedPrice);
                        $quoteItem->getProduct()->setIsSuperMode(true);
                        break;
                    }
                }
            }

            // Recalculate totals with custom prices and save
            $magentoQuote->collectTotals()->save();

            // Update B2B quote request status
            $quoteRequest->setStatus(QuoteRequestInterface::STATUS_ACCEPTED);
            $quoteRequest->setQuoteId((int) $magentoQuote->getId());
            $this->quoteRequestRepository->save($quoteRequest);
            $statusLockAcquired = false;

            $this->eventManager->dispatch('grupoawamotos_b2b_quote_accepted', [
                'quote_request' => $quoteRequest,
                'previous_status' => $previousStatus,
                'lifecycle_event' => 'accepted',
                'store_id' => (int) $this->storeManager->getStore()->getId(),
                'quote_items' => $items,
                'customer_id' => $customerId,
            ]);

            // Success message
            $msg = __('Cotação #%1 aceita! Seu carrinho foi atualizado com os preços cotados.', $requestId);
            if (!empty($failed)) {
                $msg .= ' ' . __('Alguns produtos não puderam ser adicionados: %1', implode(', ', $failed));
            }
            $this->messageManager->addSuccessMessage($msg);

            $this->logger->info(sprintf(
                '[B2B Quote Accept] Cotação #%d aceita pelo cliente #%d. %d itens adicionados ao carrinho.',
                $requestId,
                $customerId,
                $added
            ));

            // Redirect to checkout
            return $redirect->setPath('checkout');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            if ($statusLockAcquired && $requestId > 0) {
                $this->restoreQuotedStatus($requestId);
            }
            $this->messageManager->addErrorMessage(__('Cotação não encontrada.'));
            return $redirect->setPath('b2b/quote/history');
        } catch (\Exception $e) {
            if ($statusLockAcquired && $requestId > 0) {
                $this->restoreQuotedStatus($requestId);
            }
            $this->logger->error('[B2B Quote Accept] Error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Erro ao processar aceitação da cotação. Tente novamente.')
            );
            return $redirect->setPath('b2b/quote/history');
        }
    }

    private function lockQuotedRequest(int $requestId, int $customerId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_quote_request');
        $updated = (int) $connection->update(
            $table,
            ['status' => QuoteRequestInterface::STATUS_PROCESSING],
            [
                'request_id = ?' => $requestId,
                'customer_id = ?' => $customerId,
                'status = ?' => QuoteRequestInterface::STATUS_QUOTED,
            ]
        );

        return $updated === 1;
    }

    private function restoreQuotedStatus(int $requestId): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_quote_request');
            $connection->update(
                $table,
                ['status' => QuoteRequestInterface::STATUS_QUOTED],
                [
                    'request_id = ?' => $requestId,
                    'status = ?' => QuoteRequestInterface::STATUS_PROCESSING,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->warning(
                sprintf('[B2B Quote Accept] Failed to restore quoted status for #%d: %s', $requestId, $e->getMessage())
            );
        }
    }
}
