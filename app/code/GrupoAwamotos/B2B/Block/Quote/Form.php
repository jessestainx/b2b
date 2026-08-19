<?php

/**
 * Quote Form Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Quote;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Form extends Template
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var CustomerInterface|null|false
     */
    private $customerDataCache = false;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        Config $config,
        CustomerRepositoryInterface $customerRepository,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->customerRepository = $customerRepository;
        $this->priceCurrency = $priceCurrency;
        parent::__construct($context, $data);
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get current customer data via repository (reliable for EAV attributes)
     *
     * @return CustomerInterface|null
     */
    public function getCustomer()
    {
        if ($this->customerDataCache !== false) {
            return $this->customerDataCache;
        }

        if (!$this->isLoggedIn()) {
            $this->customerDataCache = null;
            return null;
        }

        try {
            $this->customerDataCache = $this->customerRepository->getById(
                $this->customerSession->getCustomerId()
            );
        } catch (\Exception $e) {
            $this->customerDataCache = null;
        }

        return $this->customerDataCache;
    }

    /**
     * Get customer email
     *
     * @return string
     */
    public function getCustomerEmail(): string
    {
        $customer = $this->getCustomer();
        return $customer ? $customer->getEmail() : '';
    }

    /**
     * Get customer name
     *
     * @return string
     */
    public function getCustomerName(): string
    {
        $customer = $this->getCustomer();
        return $customer ? $customer->getFirstname() . ' ' . $customer->getLastname() : '';
    }

    /**
     * Get customer company name
     *
     * @return string
     */
    public function getCompanyName(): string
    {
        $customer = $this->getCustomer();
        if (!$customer) {
            return '';
        }
        $attr = $customer->getCustomAttribute('b2b_razao_social');
        return $attr ? (string) $attr->getValue() : '';
    }

    /**
     * Get customer CNPJ
     *
     * @return string
     */
    public function getCnpj(): string
    {
        $customer = $this->getCustomer();
        if (!$customer) {
            return '';
        }
        $attr = $customer->getCustomAttribute('b2b_cnpj');
        return $attr ? (string) $attr->getValue() : '';
    }

    /**
     * Get customer phone
     *
     * @return string
     */
    public function getPhone(): string
    {
        $customer = $this->getCustomer();
        if (!$customer) {
            return '';
        }
        $attr = $customer->getCustomAttribute('b2b_phone');
        return $attr ? (string) $attr->getValue() : '';
    }

    /**
     * Get quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get cart items
     *
     * @return array
     */
    public function getCartItems(): array
    {
        $quote = $this->getQuote();
        return $quote->getAllVisibleItems();
    }

    /**
     * Check if cart has items
     *
     * @return bool
     */
    public function hasItems(): bool
    {
        return $this->getQuote()->hasItems();
    }

    /**
     * Get cart total
     *
     * @return float
     */
    public function getCartTotal(): float
    {
        return (float) $this->getQuote()->getGrandTotal();
    }

    /**
     * Get submit URL
     *
     * @return string
     */
    public function getSubmitUrl(): string
    {
        return $this->getUrl('b2b/quote/submit');
    }

    /**
     * Get cart URL
     *
     * @return string
     */
    public function getCartUrl(): string
    {
        return $this->getUrl('checkout/cart');
    }

    /**
     * Get expiry days
     *
     * @return int
     */
    public function getExpiryDays(): int
    {
        return $this->config->getQuoteExpiryDays();
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
    }
}
