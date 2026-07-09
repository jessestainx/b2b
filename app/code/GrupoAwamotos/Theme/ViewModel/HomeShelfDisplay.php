<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Regras de exibição dos shelves de produto na home (visitante vs logado).
 */
class HomeShelfDisplay implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly PriceVisibilityInterface $priceVisibility
    ) {
    }

    public function isHomePage(): bool
    {
        return $this->request->getFullActionName() === 'cms_index_index';
    }

    /**
     * Visitante na home não vê add-to-cart no card (gate de preço + banner único).
     */
    public function shouldRenderAddToCart(bool $moduleConfigEnabled, bool $isHomePage): bool
    {
        if (!$moduleConfigEnabled) {
            return false;
        }

        if (!$isHomePage) {
            return true;
        }

        if ($this->priceVisibility->canViewPrices()) {
            return true;
        }

        return $this->customerSession->isLoggedIn();
    }

    public function shouldShowGuestPublicPrice(bool $isHomePage): bool
    {
        return false;
    }

    public function shouldShowProductPrices(bool $isHomePage): bool
    {
        if (!$isHomePage) {
            return true;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        if (!$this->priceVisibility->canViewPrices()) {
            return false;
        }

        return true;
    }

    public function resolveViewportId(string $shelfKey): string
    {
        $normalized = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($shelfKey))) ?: 'carousel';

        return 'awa-vp-' . $normalized;
    }
}
