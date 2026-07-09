<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class Reorder extends Template
{
    private Session $customerSession;
    private CollectionFactory $orderCollectionFactory;
    private PriceCurrencyInterface $priceCurrency;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        CollectionFactory $orderCollectionFactory,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->priceCurrency = $priceCurrency;
    }

    public function getRecentOrders(int $limit = 10)
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $collection->addFieldToFilter('status', ['in' => ['complete', 'processing', 'closed']]);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);
        return $collection;
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
    }

    public function getReorderUrl(): string
    {
        return $this->getUrl('b2b/reorder/index');
    }

    public function getAddUrl(): string
    {
        return $this->getUrl('b2b/reorder/add');
    }

    public function getPricesUrl(): string
    {
        return $this->getUrl('b2b/reorder/prices');
    }

    public function getCatalogUrl(): string
    {
        return $this->getUrl('');
    }

    /**
     * @return array{pricesUrl: string, addUrl: string}
     */
    public function getReorderWidgetConfig(): array
    {
        return [
            'pricesUrl' => $this->getPricesUrl(),
            'addUrl' => $this->getAddUrl(),
        ];
    }
}
