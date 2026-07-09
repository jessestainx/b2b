<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Reorder;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Add implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly Session $customerSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Cart $cart,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->request) || !$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'message' => (string) __('Não autorizado.'),
            ]);
        }

        $orderId = (int) $this->request->getParam('order_id');
        if ($orderId <= 0) {
            return $result->setData([
                'success' => false,
                'message' => (string) __('Pedido não informado.'),
            ]);
        }

        try {
            $order = $this->orderRepository->get($orderId);
            if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
                return $result->setHttpResponseCode(403)->setData([
                    'success' => false,
                    'message' => (string) __('Acesso negado.'),
                ]);
            }

            $added = 0;
            $errors = [];
            $selectedItems = array_map('intval', (array) $this->request->getParam('items', []));

            foreach ($order->getAllVisibleItems() as $item) {
                if (!empty($selectedItems) && !in_array((int) $item->getItemId(), $selectedItems, true)) {
                    continue;
                }

                try {
                    $product = $this->productRepository->get($item->getSku());
                    if (!$product->isSalable()) {
                        $errors[] = $item->getName() . ' (indisponível)';
                        continue;
                    }

                    $this->cart->addProduct($product, ['qty' => $item->getQtyOrdered()]);
                    $added++;
                } catch (\Throwable $exception) {
                    $errors[] = (string) $item->getName();
                    $this->logger->warning('[B2B Reorder AJAX] Item skipped', [
                        'sku' => $item->getSku(),
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $this->cart->save();

            return $result->setData([
                'success' => $added > 0,
                'added' => $added,
                'errors' => $errors,
                'message' => $added > 0
                    ? (string) __('%1 produto(s) adicionado(s) ao carrinho com preços atualizados.', $added)
                    : (string) __('Nenhum produto pôde ser adicionado.'),
                'cart_url' => $this->urlBuilder->getUrl('checkout/cart'),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B Reorder AJAX] ' . $exception->getMessage());

            return $result->setData([
                'success' => false,
                'message' => (string) __('Erro ao reordenar. Tente novamente.'),
            ]);
        }
    }
}
