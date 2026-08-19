<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use GrupoAwamotos\B2B\Helper\Config as B2BConfig;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Header account prompt data without ObjectManager or $this->helper().
 *
 * Http\Context is the Magento FPC-safe logged-in flag. Customer Session is
 * only used for firstname on uncacheable account surfaces.
 */
class HeaderAccountPrompt implements ArgumentInterface
{
    public function __construct(
        private readonly B2BConfig $b2bConfig,
        private readonly HttpContext $httpContext,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function isB2bEnabled(): bool
    {
        return $this->b2bConfig->isEnabled();
    }

    public function isStrictB2B(): bool
    {
        return $this->isB2bEnabled() && $this->b2bConfig->isStrictB2B();
    }

    public function isLoggedIn(): bool
    {
        if ((bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH)) {
            return true;
        }

        return $this->customerSession->isLoggedIn();
    }

    public function getCustomerId(): int
    {
        return (int) $this->customerSession->getCustomerId();
    }

    public function getFirstname(): string
    {
        $customer = $this->customerSession->getCustomer();

        return $customer ? trim((string) $customer->getFirstname()) : '';
    }

    public function getFullname(): string
    {
        $customer = $this->customerSession->getCustomer();
        if (!$customer) {
            return '';
        }

        return trim(
            trim((string) $customer->getFirstname()) . ' ' . trim((string) $customer->getLastname())
        );
    }
}
