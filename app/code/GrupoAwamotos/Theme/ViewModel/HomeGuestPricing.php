<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Contexto de preços B2B na home para visitantes.
 */
class HomeGuestPricing implements ArgumentInterface
{
    public function __construct(
        private readonly PriceVisibilityInterface $priceVisibility,
        private readonly CustomerSession $customerSession,
        private readonly B2bConfig $b2bConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isHomeSectionEnabled(): bool
    {
        return true;
    }

    public function shouldShowNotice(): bool
    {
        return $this->isHomeSectionEnabled()
            && !$this->customerSession->isLoggedIn()
            && !$this->priceVisibility->canViewPrices();
    }

    public function getLoginUrl(): string
    {
        return $this->b2bConfig->isStrictB2B()
            ? $this->urlBuilder->getUrl('b2b/account/login')
            : $this->urlBuilder->getUrl('customer/account/login');
    }

    public function getRegisterUrl(): string
    {
        return $this->b2bConfig->isEnabled()
            ? $this->urlBuilder->getUrl('b2b/register')
            : $this->urlBuilder->getUrl('customer/account/create');
    }
}
