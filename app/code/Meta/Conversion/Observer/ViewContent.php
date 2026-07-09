<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Sends a ViewContent event to Meta CAPI when a customer views a product page.
 *
 * Triggered by the `catalog_controller_product_view` event.
 */
class ViewContent implements ObserverInterface
{
    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly LoggerInterface $logger,
        private readonly B2BHelper $b2bHelper,
        private readonly B2BSignalBuilder $b2bSignalBuilder,
        private readonly UserDataBuilder $userDataBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var Product|null $product */
            $product = $observer->getEvent()->getData('product');
            if (!$product instanceof Product || !$product->getId()) {
                return;
            }

            $storeId = $product->getStoreId() !== null ? (int) $product->getStoreId() : null;
            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $sku = (string) $product->getSku();
            $productName = (string) $product->getName();
            $price = (float) ($product->getFinalPrice() ?: $product->getPrice() ?: 0.0);
            $currency = 'BRL';

            $categoryIds = $product->getCategoryIds();
            $contentCategory = '';
            if (is_array($categoryIds) && $categoryIds !== []) {
                $contentCategory = (string) end($categoryIds);
            }

            $userData = $this->userDataBuilder->build();
            $eventSourceUrl = $this->userDataBuilder->getEventSourceUrl();

            $customData = [
                'content_ids' => [$sku],
                'content_type' => 'product',
                'content_name' => $productName,
                'value' => $price,
                'currency' => $currency,
            ];

            if ($contentCategory !== '') {
                $customData['content_category'] = $contentCategory;
            }

            $brand = $this->getAttributeText($product, 'manufacturer');
            if ($brand !== '') {
                $customData['brand'] = $brand;
            }

            // B2B enrichment
            if ($this->b2bHelper->isB2BCustomer()) {
                $customData = array_merge($customData, $this->b2bSignalBuilder->build([
                    'lead_type' => 'b2b_product_view',
                    'register_channel' => 'website',
                ]));
                $customData['funnel_stage'] = 'consideration';
            }

            $capiEvent = [
                'event_name' => 'ViewContent',
                'event_time' => time(),
                'event_id' => sprintf('vc-%s-%d', $sku, time()),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => $customData,
            ];

            if ($eventSourceUrl !== null) {
                $capiEvent['event_source_url'] = $eventSourceUrl;
            }

            $this->capiDispatcher->sendEvents($pixelId, [$capiEvent], $storeId, 'ViewContent');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] ViewContent event failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getAttributeText(Product $product, string $attributeCode): string
    {
        $value = $product->getAttributeText($attributeCode);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '';
    }
}
