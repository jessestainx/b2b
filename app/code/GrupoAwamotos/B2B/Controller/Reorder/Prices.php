<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Reorder;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\ErpCodeResolver;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Prices implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Session $customerSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerPriceProvider $customerPriceProvider,
        private readonly ErpCodeResolver $erpCodeResolver,
        private readonly PriceHelper $priceHelper,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(403)->setData([
                'success' => false,
                'items' => [],
            ]);
        }

        $orderId = (int) $this->request->getParam('order_id');
        if ($orderId <= 0) {
            return $result->setData(['success' => false, 'items' => []]);
        }

        try {
            $order = $this->orderRepository->get($orderId);
            if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
                return $result->setHttpResponseCode(403)->setData(['success' => false, 'items' => []]);
            }

            $erpCode = $this->resolveErpCode((int) $this->customerSession->getCustomerId());
            $items = [];

            foreach ($order->getAllVisibleItems() as $item) {
                $original = (float) $item->getPrice();
                $current = $this->resolveCurrentPrice((string) $item->getSku(), $erpCode, $original);
                $changePct = $original > 0 ? round((($current - $original) / $original) * 100, 1) : 0.0;

                $items[(string) $item->getItemId()] = [
                    'price' => $current,
                    'formatted' => $this->priceHelper->currency($current, true, false),
                    'change_pct' => $changePct,
                    'increased' => $changePct > 5,
                ];
            }

            return $result->setData([
                'success' => true,
                'items' => $items,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B Reorder Prices] ' . $exception->getMessage());

            return $result->setData([
                'success' => false,
                'items' => [],
            ]);
        }
    }

    private function resolveErpCode(int $customerId): ?int
    {
        if (!$this->config->isEnabled()) {
            return null;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $approvalAttr = $customer->getCustomAttribute('b2b_approval_status');
            $approvalStatus = $approvalAttr ? (string) $approvalAttr->getValue() : '';

            if ($approvalStatus !== '' && $approvalStatus !== ApprovalStatus::STATUS_APPROVED) {
                return null;
            }

            return $this->erpCodeResolver->resolveForCustomerId($customerId, $customer);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveCurrentPrice(string $sku, ?int $erpCode, float $fallback): float
    {
        try {
            $product = $this->productRepository->get($sku);
            $catalogFinal = (float) $product->getFinalPrice();
            $customerPrice = $erpCode
                ? $this->customerPriceProvider->getCustomerPrice($erpCode, $sku)
                : null;

            if ($customerPrice !== null && $customerPrice > 0) {
                return (float) $customerPrice;
            }

            return $catalogFinal > 0 ? $catalogFinal : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
