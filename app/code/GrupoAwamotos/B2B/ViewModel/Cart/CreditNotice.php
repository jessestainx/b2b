<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel\Cart;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Contexto do aviso de crédito B2B no resumo do carrinho.
 */
class CreditNotice implements ArgumentInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly B2BHelper $b2bHelper,
        private readonly CreditService $creditService,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function shouldDisplay(): bool
    {
        if (!$this->b2bHelper->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return false;
        }

        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        return in_array($customerGroupId, $this->b2bHelper->getB2BGroupIds(), true);
    }

    public function hasCreditAvailable(): bool
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if (!$customerId) {
            return false;
        }

        $credit = $this->creditService->getCreditLimit($customerId);
        return $credit->getCreditLimit() > 0;
    }

    public function getAvailableCreditFormatted(): string
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        $credit = $this->creditService->getCreditLimit($customerId);
        $available = $credit->getCreditLimit() - $credit->getUsedCredit();

        return $this->priceCurrency->format(max(0.0, $available), false);
    }

    public function getDashboardUrl(): string
    {
        return $this->urlBuilder->getUrl('b2b/account/dashboard');
    }
}
