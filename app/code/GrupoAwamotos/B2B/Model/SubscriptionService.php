<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use GrupoAwamotos\B2B\Model\SubscriptionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\Subscription as SubscriptionResource;
use Psr\Log\LoggerInterface;

class SubscriptionService
{
    private $subscriptionFactory;
    private $subscriptionResource;
    private $orderRepository;
    private $cartManagement;
    private $cartRepository;
    private $quoteFactory;
    private $productRepository;
    private $customerRepository;
    private $logger;

    public function __construct(
        SubscriptionFactory $subscriptionFactory,
        SubscriptionResource $subscriptionResource,
        OrderRepositoryInterface $orderRepository,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $cartRepository,
        QuoteFactory $quoteFactory,
        ProductRepositoryInterface $productRepository,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->subscriptionFactory = $subscriptionFactory;
        $this->subscriptionResource = $subscriptionResource;
        $this->orderRepository = $orderRepository;
        $this->cartManagement = $cartManagement;
        $this->cartRepository = $cartRepository;
        $this->quoteFactory = $quoteFactory;
        $this->productRepository = $productRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
    }

    /**
     * Process a subscription run
     *
     * @param Subscription $subscription
     * @return int Order ID
     * @throws \Exception
     */
    public function processRun(Subscription $subscription): int
    {
        $customerId = (int) $subscription->getData('customer_id');
        $customer = $this->customerRepository->getById($customerId);

        // Create Quote
        $cartId = $this->cartManagement->createEmptyCart();
        $quote = $this->cartRepository->get($cartId);
        $quote->setStore($quote->getStore());
        $quote->assignCustomer($customer);

        // Add Items
        $items = $subscription->getItems();
        foreach ($items as $item) {
            try {
                $product = $this->productRepository->get($item['sku']);
                $quote->addProduct($product, (float) $item['qty']);
            } catch (\Exception $e) {
                $this->logger->error("Subscription #{$subscription->getId()} error: SKU {$item['sku']} not found.");
            }
        }

        if (!$quote->getItemsCount()) {
            throw new \LocalizedException(__('Assinatura sem itens válidos no momento.'));
        }

        // Address & Shipping
        // For B2B we assume default addresses or the one in subscription
        $quote->getBillingAddress()->importCustomerAddressData($customer->getDefaultBillingAddress());
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->importCustomerAddressData($customer->getDefaultShippingAddress());

        $shippingAddress->setCollectShippingRates(true)
                        ->collectShippingRates()
                        ->setShippingMethod('flatrate_flatrate'); // Default fallback

        $quote->setPaymentMethod('checkmo'); // Default fallback for automated orders
        $quote->getPayment()->importData(['method' => 'checkmo']);

        $quote->collectTotals();
        $this->cartRepository->save($quote);

        // Place Order
        $orderId = (int) $this->cartManagement->placeOrder($quote->getId());

        // Update Subscription
        $subscription->setData('last_run_at', date('Y-m-d H:i:s'));
        $subscription->setData('next_run_at', $this->calculateNextRun($subscription));
        $this->subscriptionResource->save($subscription);

        $this->logger->info("Subscription #{$subscription->getId()} processed. Order #$orderId created.");

        return $orderId;
    }

    /**
     * Calculate next run date based on frequency
     *
     * @param Subscription $subscription
     * @return string
     */
    public function calculateNextRun(Subscription $subscription): string
    {
        $frequency = $subscription->getData('frequency');
        $date = new \DateTime();

        switch ($frequency) {
            case Subscription::FREQUENCY_WEEKLY:
                $date->modify('+7 days');
                break;
            case Subscription::FREQUENCY_BIWEEKLY:
                $date->modify('+14 days');
                break;
            case Subscription::FREQUENCY_MONTHLY:
                $date->modify('+1 month');
                break;
            case Subscription::FREQUENCY_QUARTERLY:
                $date->modify('+3 months');
                break;
            default:
                $date->modify('+1 month');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
