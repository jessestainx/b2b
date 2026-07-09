<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Reorder;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

class Index implements HttpPostActionInterface
{
    private RequestInterface $request;
    private RedirectFactory $redirectFactory;
    private FormKeyValidator $formKeyValidator;
    private ManagerInterface $messageManager;
    private Session $customerSession;
    private OrderRepositoryInterface $orderRepository;
    private Cart $cart;
    private ProductRepositoryInterface $productRepository;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        Session $customerSession,
        OrderRepositoryInterface $orderRepository,
        Cart $cart,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->orderRepository = $orderRepository;
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Formulário inválido. Tente novamente.'));
            return $redirect->setPath('sales/order/history');
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('b2b/account/login');
        }

        $orderId = (int) $this->request->getParam('order_id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Pedido não informado.'));
            return $redirect->setPath('sales/order/history');
        }

        try {
            $order = $this->orderRepository->get($orderId);

            if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
                $this->messageManager->addErrorMessage(__('Acesso negado.'));
                return $redirect->setPath('sales/order/history');
            }

            $this->processReorderItems($order);

            return $redirect->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->logger->error('B2B Reorder error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Erro ao reordenar. Tente novamente.'));
            return $redirect->setPath('sales/order/history');
        }
    }

    /**
     * Process order items for reorder, adding them to cart
     *
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    private function processReorderItems(\Magento\Sales\Model\Order $order): void
    {
        $added = 0;
        $errors = [];
        $selectedItems = array_map('intval', (array) $this->request->getParam('items', []));

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllVisibleItems() as $item) {
            if (!empty($selectedItems) && !in_array((int) $item->getItemId(), $selectedItems, true)) {
                continue;
            }
            $result = $this->addItemToCart($item);
            if ($result === true) {
                $added++;
            } else {
                $errors[] = $result;
            }
        }

        $this->cart->save();
        $this->reportReorderResults($added, $errors);
    }

    /**
     * Add a single order item to cart
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @return bool|string True on success, error description on failure
     */
    private function addItemToCart(\Magento\Sales\Model\Order\Item $item): bool|string
    {
        try {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $this->productRepository->get($item->getSku());
            if (!$product->isSalable()) {
                return $item->getName() . ' (indisponível)';
            }
            $this->cart->addProduct($product, ['qty' => $item->getQtyOrdered()]);
            return true;
        } catch (\Exception $e) {
            return $item->getName();
        }
    }

    /**
     * Add success/warning messages about reorder results
     *
     * @param int $added
     * @param array $errors
     * @return void
     */
    private function reportReorderResults(int $added, array $errors): void
    {
        if ($added > 0) {
            $this->messageManager->addSuccessMessage(
                __('%1 produto(s) adicionado(s) ao carrinho com preços atualizados.', $added)
            );
        }
        if (!empty($errors)) {
            $this->messageManager->addWarningMessage(
                __('Não foi possível adicionar: %1', implode(', ', $errors))
            );
        }
    }
}
