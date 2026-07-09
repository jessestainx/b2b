<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\B2B;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;

/**
 * Na home, separa preço público de gate de atacado para visitante.
 */
class HomePriceReplacementMessagePlugin
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly B2bConfig $b2bConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper
    ) {
    }

    public function afterGetPriceReplacementMessage(
        PriceVisibilityInterface $subject,
        string $result
    ): string {
        if ($this->request->getFullActionName() !== 'cms_index_index') {
            return $result;
        }

        if ($this->customerSession->isLoggedIn() || $subject->canViewPrices()) {
            return $result;
        }

        $loginUrl = $this->b2bConfig->isStrictB2B()
            ? $this->urlBuilder->getUrl('b2b/account/login')
            : $this->urlBuilder->getUrl('customer/account/login');

        $prefix = (string) __('Preço de atacado: ');
        $label = (string) __('entre ou cadastre-se');
        $ariaLabel = (string) __('Entrar para ver preços de atacado da sua loja');

        return '<span class="awa-wholesale-price-gate">'
            . '<span class="awa-wholesale-price-gate__prefix">' . $this->escaper->escapeHtml($prefix) . '</span>'
            . '<a href="' . $this->escaper->escapeUrl($loginUrl) . '" class="b2b-login-link awa-wholesale-price-gate__link"'
            . ' aria-label="' . $this->escaper->escapeHtmlAttr($ariaLabel) . '"'
            . ' aria-describedby="awa-home-pricing-notice">'
            . $this->escaper->escapeHtml($label) . '</a>'
            . '</span>';
    }
}
