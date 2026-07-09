<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel for B2B gate on PDP (addtocart.phtml, b2b_secondary_ctas.phtml).
 *
 * Replaces the ObjectManager + $this->helper() anti-patterns in those templates.
 * Injected via catalog_product_view.xml as view_model argument.
 */
class PdpGate implements ArgumentInterface
{
    public function __construct(
        private readonly B2BHelper $b2bHelper,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function isActive(): bool
    {
        return $this->moduleManager->isEnabled('GrupoAwamotos_B2B');
    }

    public function canAddToCart(): bool
    {
        return $this->b2bHelper->canAddToCart();
    }

    public function getPriceGateState(): string
    {
        return $this->b2bHelper->getPriceGateState();
    }

    public function getPriceGateHeadline(): string
    {
        return $this->b2bHelper->getPriceGateHeadline();
    }

    public function getPriceGateDescription(): string
    {
        return $this->b2bHelper->getPriceGateDescription();
    }

    public function getPriceGatePrimaryUrl(): string
    {
        return $this->b2bHelper->getPriceGatePrimaryUrl();
    }

    public function getPriceGatePrimaryLabel(): string
    {
        return $this->b2bHelper->getPriceGatePrimaryLabel();
    }

    public function getPriceGateSecondaryUrl(): string
    {
        return $this->b2bHelper->getPriceGateSecondaryUrl();
    }

    public function getPriceGateSecondaryLabel(): string
    {
        return $this->b2bHelper->getPriceGateSecondaryLabel();
    }

    /**
     * Label do CTA principal na PDP para visitantes (abre modal de login).
     */
    public function getLoginToBuyLabel(): string
    {
        return (string) __('Entrar para Comprar');
    }
}
