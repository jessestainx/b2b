<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Model;

use GrupoAwamotos\WhatsAppCommerce\Api\B2BReorderInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class WhatsAppB2BReorder implements B2BReorderInterface
{
    public function __construct(
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartItemInterfaceFactory $cartItemFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getReorderableOrders(string $phone, int $limit = 5): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return ['orders' => [], 'message' => 'Nenhum cadastro encontrado para este telefone.'];
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', (int) $customer->getId());
        $collection->addFieldToFilter('state', ['in' => ['complete', 'processing', 'closed']]);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);

        $orders = [];
        foreach ($collection as $order) {
            $items = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = [
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty' => (int) $item->getQtyOrdered(),
                    'price' => (float) $item->getPrice(),
                ];
            }

            $orders[] = [
                'increment_id' => $order->getIncrementId(),
                'created_at' => $order->getCreatedAt(),
                'grand_total' => (float) $order->getGrandTotal(),
                'items_count' => count($items),
                'items' => $items,
                'status' => $order->getStatusLabel(),
            ];
        }

        return [
            'orders' => $orders,
            'total' => count($orders),
            'customer_name' => $customer->getFirstname(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function reorderByOrderId(string $phone, string $orderIncrementId): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return ['success' => false, 'message' => 'Cadastro nao encontrado.'];
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('increment_id', $orderIncrementId);
        $collection->addFieldToFilter('customer_id', (int) $customer->getId());
        $collection->setPageSize(1);

        $order = $collection->getFirstItem();
        if (!$order->getId()) {
            return ['success' => false, 'message' => "Pedido #{$orderIncrementId} nao encontrado."];
        }

        return $this->createCartFromOrder($customer, $order);
    }

    /**
     * @inheritDoc
     */
    public function reorderLast(string $phone): array
    {
        $customer = $this->findCustomerByPhone($phone);
        if ($customer === null) {
            return ['success' => false, 'message' => 'Cadastro nao encontrado.'];
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', (int) $customer->getId());
        $collection->addFieldToFilter('state', ['in' => ['complete', 'processing']]);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(1);

        $order = $collection->getFirstItem();
        if (!$order->getId()) {
            return ['success' => false, 'message' => 'Nenhum pedido anterior encontrado.'];
        }

        return $this->createCartFromOrder($customer, $order);
    }

    /**
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    private function createCartFromOrder(
        \Magento\Customer\Api\Data\CustomerInterface $customer,
        \Magento\Sales\Model\Order $order
    ): array {
        $customerId = (int) $customer->getId();

        try {
            $cartId = $this->cartManagement->createEmptyCartForCustomer($customerId);
            $cart = $this->cartRepository->get($cartId);

            $addedItems = [];
            $skippedItems = [];

            foreach ($order->getAllVisibleItems() as $orderItem) {
                try {
                    $product = $this->productRepository->get($orderItem->getSku());

                    if ((int) $product->getStatus() !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED) {
                        $skippedItems[] = $orderItem->getName() . ' (indisponivel)';
                        continue;
                    }

                    $cartItem = $this->cartItemFactory->create();
                    $cartItem->setSku($product->getSku());
                    $cartItem->setQty((float) $orderItem->getQtyOrdered());
                    $cartItem->setQuoteId($cartId);

                    $cart->addItem($cartItem);

                    $addedItems[] = [
                        'sku' => $product->getSku(),
                        'name' => $product->getName(),
                        'qty' => (int) $orderItem->getQtyOrdered(),
                        'price' => (float) $product->getFinalPrice(),
                    ];
                } catch (NoSuchEntityException $e) {
                    $skippedItems[] = $orderItem->getName() . ' (removido do catalogo)';
                }
            }

            if (empty($addedItems)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum produto deste pedido esta mais disponivel.',
                    'skipped_items' => $skippedItems,
                ];
            }

            $this->cartRepository->save($cart);

            $baseUrl = $this->config->getCheckoutBaseUrl();
            if (empty($baseUrl)) {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl() . 'checkout';
            }
            $checkoutLink = $baseUrl;

            $summary = "Carrinho criado com itens do pedido #{$order->getIncrementId()}:\n\n";
            $subtotal = 0.0;
            foreach ($addedItems as $ai) {
                $lineTotal = $ai['qty'] * $ai['price'];
                $subtotal += $lineTotal;
                $summary .= "- {$ai['qty']}x {$ai['name']} - R$ " . number_format($ai['price'], 2, ',', '.') . "\n";
            }
            $summary .= "\nSubtotal: R$ " . number_format($subtotal, 2, ',', '.');

            if (!empty($skippedItems)) {
                $summary .= "\n\nItens indisponiveis:\n";
                foreach ($skippedItems as $si) {
                    $summary .= "- {$si}\n";
                }
            }

            $summary .= "\n\nFinalizar: {$checkoutLink}";

            $this->logger->info('B2B Reorder via WhatsApp', [
                'customer_id' => $customerId,
                'original_order' => $order->getIncrementId(),
                'items_added' => count($addedItems),
                'items_skipped' => count($skippedItems),
            ]);

            return [
                'success' => true,
                'cart_id' => $cartId,
                'original_order' => $order->getIncrementId(),
                'items' => $addedItems,
                'skipped_items' => $skippedItems,
                'subtotal' => $subtotal,
                'checkout_link' => $checkoutLink,
                'message' => $summary,
            ];
        } catch (\Exception $e) {
            $this->logger->error('B2B Reorder error', [
                'order' => $order->getIncrementId(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Erro ao criar carrinho. Tente novamente.'];
        }
    }

    private function findCustomerByPhone(string $phone): ?\Magento\Customer\Api\Data\CustomerInterface
    {
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (strlen($cleanPhone) >= 13 && str_starts_with($cleanPhone, '55')) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $lastDigits = substr($cleanPhone, -8);
        if (strlen($lastDigits) < 8) {
            return null;
        }

        $connection = $this->resource->getConnection();
        $sql = $connection->select()
            ->from(['a' => $this->resource->getTableName('customer_address_entity')], ['parent_id'])
            ->where(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.telephone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE ?",
                '%' . $lastDigits
            )
            ->limit(1);
        $customerId = (int) $connection->fetchOne($sql);

        if (!$customerId) {
            $collection = $this->customerCollectionFactory->create();
            $collection->addAttributeToFilter('b2b_phone', ['like' => "%" . $lastDigits . "%"]);
            $collection->setPageSize(1);
            $customerId = (int) $collection->getFirstItem()->getId();
        }

        if (!$customerId) {
            return null;
        }

        try {
            return $this->customerRepository->getById($customerId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
