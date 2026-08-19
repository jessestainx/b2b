<?php

/**
 * Quote View Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Quote;

use GrupoAwamotos\B2B\Model\QuoteRequest;
use GrupoAwamotos\B2B\Model\QuoteRequestFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    private CustomerSession $customerSession;
    private QuoteRequestFactory $quoteRequestFactory;
    private QuoteRequestResource $quoteRequestResource;
    private RequestInterface $httpRequest;
    private PricingHelper $pricingHelper;
    private TimezoneInterface $timezone;
    private ?QuoteRequest $quoteRequest = null;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        QuoteRequestFactory $quoteRequestFactory,
        QuoteRequestResource $quoteRequestResource,
        RequestInterface $httpRequest,
        PricingHelper $pricingHelper,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        $this->customerSession = $customerSession;
        $this->quoteRequestFactory = $quoteRequestFactory;
        $this->quoteRequestResource = $quoteRequestResource;
        $this->httpRequest = $httpRequest;
        $this->pricingHelper = $pricingHelper;
        $this->timezone = $timezone ?? $context->getLocaleDate();
        parent::__construct($context, $data);
    }

    public function getQuoteRequest(): ?QuoteRequest
    {
        if ($this->quoteRequest === null) {
            $requestId = (int) $this->httpRequest->getParam('id');
            if ($requestId) {
                $this->quoteRequest = $this->quoteRequestFactory->create();
                $this->quoteRequestResource->load($this->quoteRequest, $requestId);
                if (!$this->quoteRequest->getRequestId()) {
                    $this->quoteRequest = null;
                }
            }
        }
        return $this->quoteRequest;
    }

    public function getItems(): array
    {
        $quote = $this->getQuoteRequest();
        return $quote ? $quote->getItems() : [];
    }

    public function formatPrice($price): string
    {
        return $this->pricingHelper->currency($price, true, false);
    }

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

    public function getStatusClass(string $status): string
    {
        $classes = [
            'pending' => 'status-pending',
            'processing' => 'status-pending',
            'quoted' => 'status-success',
            'accepted' => 'status-success',
            'rejected' => 'status-failed',
            'expired' => 'status-failed',
            'converted' => 'status-complete',
        ];
        return $classes[$status] ?? 'status-pending';
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/quote/history');
    }

    public function getNewQuoteUrl(): string
    {
        return $this->getUrl('b2b/quote');
    }

    public function getAcceptUrl(): string
    {
        $quote = $this->getQuoteRequest();
        return $quote ? $this->getUrl('b2b/quote/accept', ['id' => $quote->getRequestId()]) : '#';
    }

    public function getRejectUrl(): string
    {
        $quote = $this->getQuoteRequest();
        return $quote ? $this->getUrl('b2b/quote/reject', ['id' => $quote->getRequestId()]) : '#';
    }

    public function canAcceptOrReject(): bool
    {
        $quote = $this->getQuoteRequest();
        if (!$quote) {
            return false;
        }
        return $quote->getStatus() === \GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface::STATUS_QUOTED
            && !$quote->isExpired();
    }

    /**
     * Runtime probe for duplicate heading validation.
     */
}
