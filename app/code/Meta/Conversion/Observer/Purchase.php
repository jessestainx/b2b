<?php

declare(strict_types=1);

namespace Meta\Conversion\Observer;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Meta\BusinessExtension\Api\SystemConfigInterface;
use Meta\Conversion\Model\CapiEventDispatcher;
use Meta\Conversion\Helper\B2BSignalBuilder;
use Meta\Conversion\Helper\UserDataBuilder;
use Psr\Log\LoggerInterface;

/**
 * Observer to send Purchase events via Conversions API
 */
class Purchase implements ObserverInterface
{
    private const SESSION_KEY_LAST_ORDER_ID = 'meta_last_purchase_order_id';

    public function __construct(
        private readonly SystemConfigInterface $config,
        private readonly CapiEventDispatcher $capiDispatcher,
        private readonly \Magento\Checkout\Model\Session $checkoutSession,
        private readonly LoggerInterface $logger,
        private readonly B2BHelper $b2bHelper,
        private readonly B2BSignalBuilder $b2bSignalBuilder,
        private readonly ?UserDataBuilder $userDataBuilder = null
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var Order $order */
            $order = $observer->getEvent()->getData('order');
            if (!$order instanceof Order || !$order->getId()) {
                $orders = $observer->getEvent()->getData('orders');
                if (is_array($orders)) {
                    foreach ($orders as $candidateOrder) {
                        if ($candidateOrder instanceof Order && $candidateOrder->getId()) {
                            $order = $candidateOrder;
                            break;
                        }
                    }
                }
            }

            if (!$order instanceof Order || !$order->getId()) {
                $order = $this->checkoutSession->getLastRealOrder();
            }
            if (!$order || !$order->getId()) {
                return;
            }

            $orderReference = (string) ($order->getIncrementId() ?: $order->getId());
            $lastOrderReference = (string) ($this->checkoutSession->getData(self::SESSION_KEY_LAST_ORDER_ID) ?: '');
            if ($orderReference !== '' && hash_equals($lastOrderReference, $orderReference)) {
                return;
            }

            $storeId = $order->getStoreId() !== null ? (int) $order->getStoreId() : null;
            if (!$this->config->isActive($storeId)) {
                return;
            }

            $pixelId = $this->config->getPixelId($storeId);
            if ($pixelId === null) {
                return;
            }

            $contentIds = [];
            $contents = [];

            foreach ($order->getAllVisibleItems() as $item) {
                $contentIds[] = $item->getSku();
                $contents[] = [
                    'id' => $item->getSku(),
                    'quantity' => (int) $item->getQtyOrdered()
                ];
            }

            if ($contents === []) {
                return;
            }

            $externalId    = (string) ($order->getCustomerId() ?: ($order->getIncrementId() ?: $order->getId()));
            $purchaseAddr  = $order->getBillingAddress() ?: $order->getShippingAddress();
            $userData      = $this->userDataBuilder
                ? $this->userDataBuilder->build(
                    (string) ($order->getCustomerEmail() ?: ''),
                    (string) ($purchaseAddr?->getTelephone() ?: ''),
                    $externalId,
                    (string) ($order->getCustomerFirstname() ?: $purchaseAddr?->getFirstname() ?: ''),
                    (string) ($order->getCustomerLastname() ?: $purchaseAddr?->getLastname() ?: ''),
                    (string) ($purchaseAddr?->getCity() ?: ''),
                    (string) ($purchaseAddr?->getRegionCode() ?: $purchaseAddr?->getRegion() ?: ''),
                    (string) ($purchaseAddr?->getPostcode() ?: ''),
                    strtolower((string) ($purchaseAddr?->getCountryId() ?: 'br'))
                )
                : [];
            if ($this->userDataBuilder && !$this->userDataBuilder->hasMinimumSignals($userData)) {
                return;
            }
            $eventSourceUrl = $this->userDataBuilder?->getEventSourceUrl();

            $currency = (string) ($order->getOrderCurrencyCode() ?: $order->getBaseCurrencyCode() ?: 'BRL');
            $eventTime = time();
            $eventId = sprintf('purchase-%s', (string) ($order->getIncrementId() ?: $order->getId()));

            $event = [
                'event_name' => 'Purchase',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => [
                    'content_ids' => $contentIds,
                    'content_type' => 'product',
                    'contents' => $contents,
                    'num_items' => count($contents),
                    'value' => (float) $order->getGrandTotal(),
                    'currency' => $currency,
                    'order_id' => $order->getIncrementId()
                ]
            ];

            if ($this->isB2BOrder($order)) {
                $event['custom_data'] = array_merge(
                    $this->b2bSignalBuilder->build([
                        'lead_type' => 'b2b_purchase',
                        'person_type' => $this->resolveOrderPersonType($order),
                        'approval_status' => 'approved',
                        'customer_group_id' => (int) $order->getCustomerGroupId(),
                        'register_channel' => 'checkout'
                    ]),
                    $event['custom_data'],
                    [
                        'funnel_stage' => 'purchase',
                        'business_order' => true
                    ]
                );
            }
            if ($eventSourceUrl !== null) {
                $event['event_source_url'] = $eventSourceUrl;
            }

            $eventData = [$event];

            if ($orderReference !== '') {
                $this->checkoutSession->setData(self::SESSION_KEY_LAST_ORDER_ID, $orderReference);
            }

            $this->capiDispatcher->sendEvents($pixelId, $eventData, $storeId, 'Purchase');
        } catch (\Throwable $e) {
            $this->logger->error('[Meta CAPI] Purchase event failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function isB2BOrder(Order $order): bool
    {
        $customerGroupId = (int) $order->getCustomerGroupId();
        if ($customerGroupId > 0 && $this->b2bHelper->isB2BGroup($customerGroupId)) {
            return true;
        }

        $company = trim((string) ($order->getBillingAddress()?->getCompany() ?: ''));
        if ($company !== '') {
            return true;
        }

        $taxvat = preg_replace('/\D+/', '', (string) ($order->getCustomerTaxvat() ?: ''));

        return is_string($taxvat) && strlen($taxvat) === 14;
    }

    private function resolveOrderPersonType(Order $order): string
    {
        $taxvat = preg_replace('/\D+/', '', (string) ($order->getCustomerTaxvat() ?: ''));

        return is_string($taxvat) && strlen($taxvat) === 11 ? 'pf' : 'pj';
    }
}
