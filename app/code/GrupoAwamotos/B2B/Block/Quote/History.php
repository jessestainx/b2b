<?php

/**
 * Quote History Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Quote;

use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class History extends Template
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;
    private PriceCurrencyInterface $priceCurrency;
    private TimezoneInterface $timezone;

    /**
     * @var \GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\Collection|null
     */
    private $collection = null;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CollectionFactory $collectionFactory,
        PriceCurrencyInterface $priceCurrency,
        TimezoneInterface $timezone,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->collectionFactory = $collectionFactory;
        $this->priceCurrency = $priceCurrency;
        $this->timezone = $timezone;
        parent::__construct($context, $data);
    }

    /**
     * Get quote requests collection
     *
     * @return \GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\Collection
     */
    public function getQuoteRequests()
    {
        if ($this->collection === null) {
            $this->collection = $this->collectionFactory->create();
            $this->collection->addCustomerFilter((int) $this->customerSession->getCustomerId());
            $this->collection->setOrder('created_at', 'DESC');
        }

        return $this->collection;
    }

    /**
     * Check if customer has quote requests
     *
     * @return bool
     */
    public function hasQuoteRequests(): bool
    {
        return $this->getQuoteRequests()->getSize() > 0;
    }

    /**
     * Get view URL for quote request
     *
     * @param int $requestId
     * @return string
     */
    public function getViewUrl(int $requestId): string
    {
        return $this->getUrl('b2b/quote/view', ['id' => $requestId]);
    }

    /**
     * Get new quote request URL
     *
     * @return string
     */
    public function getNewQuoteUrl(): string
    {
        return $this->getUrl('b2b/quote');
    }

    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
    }

    /**
     * Format quote date
     *
     * @param string $date
     * @return string
     */
    public function formatQuoteDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '-';
        }

        try {
            return $this->timezone->date(new \DateTime($date))->format('d/m/Y H:i');
        } catch (\Exception) {
            return substr($date, 0, 16);
        }
    }
}
