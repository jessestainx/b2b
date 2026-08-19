<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Reorder extends Template
{
    private Session $customerSession;
    private CollectionFactory $orderCollectionFactory;
    private PriceCurrencyInterface $priceCurrency;
    private TimezoneInterface $timezone;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        CollectionFactory $orderCollectionFactory,
        PriceCurrencyInterface $priceCurrency,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->priceCurrency = $priceCurrency;
        $this->timezone = $timezone ?? $context->getLocaleDate();
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

    /**
     * @param string|\DateTimeInterface|null $date
     * @param int $format
     * @param bool $showTime
     * @param string|null $timezone
     */
    public function formatDate(
        $date = null,
        $format = \IntlDateFormatter::SHORT,
        $showTime = false,
        $timezone = null
    ): string {
        if (func_num_args() <= 1) {
            if ($date === null || $date === '') {
                return '-';
            }

            $raw = $date instanceof \DateTimeInterface ? $date->format('c') : (string) $date;
            try {
                return $this->timezone->date(new \DateTime($raw))->format('d/m/Y');
            } catch (\Exception) {
                return substr($raw, 0, 10);
            }
        }

        return (string) parent::formatDate($date, $format, $showTime, $timezone);
    }
}
