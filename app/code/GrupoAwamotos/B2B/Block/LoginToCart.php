<?php

/**
 * Block for B2B access restriction modals.
 * Shown to guests (login/register modal) AND to logged-in non-approved users (pending message).
 *
 * FPC note: uses Http\Context (not CustomerSession) for the login check so that guest page
 * renders do NOT start a PHP session → the homepage stays FPC-cacheable for guest visitors.
 * For logged-in users Magento's customer middleware already initialised the session before we
 * get here, so calling CustomerSession-dependent helpers (canAddToCart, etc.) is safe.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class LoginToCart extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var PriceVisibilityInterface
     */
    private $priceVisibility;

    /**
     * @var HttpContext
     */
    private $httpContext;

    public function __construct(
        Context $context,
        Config $config,
        Session $customerSession,
        PriceVisibilityInterface $priceVisibility,
        HttpContext $httpContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->priceVisibility = $priceVisibility;
        $this->httpContext = $httpContext;
    }

    /**
     * Check if modal should be rendered (guests OR non-approved logged-in users).
     * Uses Http\Context for the login check to keep guest pages FPC-cacheable.
     *
     * @return bool
     */
    public function shouldRender(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        if ($this->isGuest()) {
            return $this->config->hideAddToCartForGuests();
        }

        // Logged-in user: session is already active via Magento customer middleware
        return !$this->priceVisibility->canAddToCart();
    }

    /**
     * Only output HTML if conditions are met
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Check if user is a guest (not logged in).
     * Uses Http\Context so guests do not trigger session_start() during page render.
     *
     * @return bool
     */
    public function isGuest(): bool
    {
        return !(bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    /**
     * Check if user is logged in but pending approval.
     * Only meaningful for authenticated users (session already active).
     *
     * @return bool
     */
    public function isPendingApproval(): bool
    {
        if ($this->isGuest()) {
            return false;
        }
        return !$this->priceVisibility->isCustomerApproved();
    }

    /**
     * Get the pending message from config
     *
     * @return string
     */
    public function getPendingMessage(): string
    {
        $message = $this->config->getPendingMessage();
        if (empty($message)) {
            $message = 'Sua conta está aguardando aprovação. Você receberá um e-mail quando for aprovada.';
        }
        return $message;
    }

    /**
     * Get login URL
     *
     * @return string
     */
    public function getLoginUrl(): string
    {
        if ($this->config->isStrictB2B()) {
            return $this->getUrl('b2b/account/login');
        }

        return $this->getUrl('customer/account/login');
    }

    /**
     * Get register URL
     *
     * @return string
     */
    public function getRegisterUrl(): string
    {
        if ($this->config->isStrictB2B()) {
            return $this->getUrl('b2b/register');
        }

        return $this->getUrl('customer/account/create');
    }

    /**
     * Check if store is operating in strict B2B mode.
     */
    public function isStrictB2B(): bool
    {
        return $this->config->isStrictB2B();
    }

    /**
     * Get B2B register URL
     *
     * @return string
     */
    public function getB2bRegisterUrl(): string
    {
        return $this->getUrl('b2b/register');
    }

    /**
     * Get account dashboard URL
     *
     * @return string
     */
    public function getAccountUrl(): string
    {
        return $this->getUrl('b2b/account/dashboard');
    }
}
