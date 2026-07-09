<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Ajax;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\ErpCodeResolver;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Psr\Log\LoggerInterface;

class CustomerPrices implements HttpGetActionInterface
{
    private const MAX_PRODUCT_IDS = 48;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductRepositoryInterface $productRepository,
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
        $productIds = $this->getProductIds();

        if (empty($productIds)) {
            return $result->setData([
                'success' => false,
                'allowed' => false,
                'message' => 'No products requested.'
            ]);
        }

        try {
            $customerContext = $this->getCustomerContext();
            if (!$customerContext['allowed']) {
                return $result->setData([
                    'success' => true,
                    'allowed' => false,
                    'items' => []
                ]);
            }

            $items = [];
            foreach ($productIds as $productId) {
                try {
                    $product = $this->productRepository->getById($productId);
                    $items[$productId] = [
                        'html' => $this->buildPriceHtml($productId, $product, (int) $customerContext['erp_code'])
                    ];
                } catch (\Throwable $exception) {
                    $this->logger->warning('[B2B CustomerPrices] Failed to build product price.', [
                        'product_id' => $productId,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }

            return $result->setData([
                'success' => true,
                'allowed' => true,
                'items' => $items
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B CustomerPrices] Unexpected AJAX failure.', [
                'exception' => $exception->getMessage(),
            ]);

            return $result->setData([
                'success' => false,
                'allowed' => false,
                'message' => 'Unable to resolve customer prices.'
            ]);
        }
    }

    /**
     * @return int[]
     */
    private function getProductIds(): array
    {
        $raw = (string) $this->request->getParam('product_ids', '');
        if ($raw === '') {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', $raw)
        ))));

        return array_slice($ids, 0, self::MAX_PRODUCT_IDS);
    }

    /**
     * @return array{allowed: bool, erp_code: int|null}
     */
    private function getCustomerContext(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['allowed' => false, 'erp_code' => null];
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        if ($customerId <= 0) {
            return ['allowed' => false, 'erp_code' => null];
        }

        if (!$this->config->isEnabled()) {
            return ['allowed' => true, 'erp_code' => null];
        }

        $customer = $this->customerRepository->getById($customerId);
        $approvalAttr = $customer->getCustomAttribute('b2b_approval_status');
        $approvalStatus = $approvalAttr ? (string) $approvalAttr->getValue() : '';
        $erpCode = $this->erpCodeResolver->resolveForCustomerId($customerId, $customer);

        // Fail-closed: sem status explícito não deve liberar preços B2B.
        if ($approvalStatus === ApprovalStatus::STATUS_APPROVED) {
            if ($this->config->hidePriceForNoErp() && $erpCode === null) {
                return ['allowed' => false, 'erp_code' => null];
            }

            return ['allowed' => true, 'erp_code' => $erpCode];
        }

        if ($approvalStatus === ApprovalStatus::STATUS_PENDING) {
            return [
                'allowed' => $this->config->showPriceForPending(),
                'erp_code' => $erpCode
            ];
        }

        return ['allowed' => false, 'erp_code' => null];
    }

    private function buildPriceHtml(int $productId, object $product, ?int $erpCode = null): string
    {
        $catalogFinalPrice = (float) $product->getFinalPrice();
        $regularPrice = (float) $product->getPriceInfo()->getPrice('regular_price')->getValue();
        $customerPrice = $erpCode ? $this->customerPriceProvider->getCustomerPrice($erpCode, (string) $product->getSku()) : null;
        $effectivePrice = ($customerPrice !== null && $customerPrice > 0) ? $customerPrice : $catalogFinalPrice;
        $comparePrice = $regularPrice > ($effectivePrice + 0.0001) ? $regularPrice : null;

        if ($effectivePrice <= 0) {
            return '';
        }

        $formattedEffective = $this->escapeHtml($this->priceHelper->currency($effectivePrice, true, false));
        $formattedCompare = $comparePrice !== null
            ? $this->escapeHtml($this->priceHelper->currency($comparePrice, true, false))
            : null;

        if ($formattedCompare !== null) {
            return '<div class="price-box price-final_price" data-role="priceBox" data-product-id="' . $productId . '">'
                . '<span class="old-price">'
                . '<span class="price-container"><span class="price-wrapper"><span class="price">' . $formattedCompare . '</span></span></span>'
                . '</span>'
                . '<span class="special-price">'
                . '<span class="price-container"><span class="price-wrapper"><span class="price">' . $formattedEffective . '</span></span></span>'
                . '</span>'
                . '</div>';
        }

        return '<div class="price-box price-final_price" data-role="priceBox" data-product-id="' . $productId . '">'
            . '<span class="normal-price">'
            . '<span class="price-container"><span class="price-wrapper"><span class="price">' . $formattedEffective . '</span></span></span>'
            . '</span>'
            . '</div>';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
