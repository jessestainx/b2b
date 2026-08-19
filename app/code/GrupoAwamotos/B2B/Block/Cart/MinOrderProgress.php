<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Cart;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;

class MinOrderProgress extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ResolverInterface $localeResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled()
            && $this->config->isMinQtyEnabled()
            && $this->getMinOrderAmount() > 0;
    }

    public function shouldDisplay(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return $this->getCartSubtotal() > 0
            && $this->getRemainingAmount() > 0.009;
    }

    public function getMinOrderAmount(): float
    {
        return $this->config->getMinOrderAmount();
    }

    public function getCartSubtotal(): float
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            return max(0.0, (float) $quote->getSubtotal());
        } catch (\Exception) {
            return 0.0;
        }
    }

    public function getRemainingAmount(): float
    {
        return max(0.0, $this->getMinOrderAmount() - $this->getCartSubtotal());
    }

    public function getProgressPercent(): float
    {
        $min = $this->getMinOrderAmount();

        if ($min <= 0) {
            return 100.0;
        }

        return min(100.0, round(($this->getCartSubtotal() / $min) * 100, 1));
    }

    public function getProgressMessage(): string
    {
        $remaining = $this->formatCurrency($this->getRemainingAmount());
        $minimum = $this->formatCurrency($this->getMinOrderAmount());

        return (string) __(
            'Faltam %1 para atingir o pedido mínimo de %2.',
            $remaining,
            $minimum
        );
    }

    public function getConfigMessage(): string
    {
        return $this->config->getMinOrderMessage();
    }

    public function formatCurrency(float $amount): string
    {
        try {
            return $this->priceCurrency->format($amount, false);
        } catch (\Exception) {
            return 'R$ ' . number_format($amount, 2, ',', '.');
        }
    }

    /**
     * @return array<string, float|string|bool>
     */
    public function getClientConfig(): array
    {
        $locale = str_replace('_', '-', (string) $this->localeResolver->getLocale());

        return [
            'enabled' => $this->isEnabled(),
            'minAmount' => $this->getMinOrderAmount(),
            'subtotal' => $this->getCartSubtotal(),
            'remaining' => $this->getRemainingAmount(),
            'percent' => $this->getProgressPercent(),
            'message' => $this->getProgressMessage(),
            'configMessage' => $this->getConfigMessage(),
            'currencyCode' => (string) $this->_storeManager->getStore()->getCurrentCurrencyCode(),
            'locale' => $locale !== '' ? $locale : 'pt-BR',
        ];
    }
}
