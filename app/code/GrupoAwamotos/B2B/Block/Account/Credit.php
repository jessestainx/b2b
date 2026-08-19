<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Credit extends Template
{
    private Session $customerSession;
    private CreditService $creditService;
    private PriceCurrencyInterface $priceCurrency;
    private TimezoneInterface $timezone;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        CreditService $creditService,
        PriceCurrencyInterface $priceCurrency,
        array $data = [],
        ?TimezoneInterface $timezone = null
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->creditService = $creditService;
        $this->priceCurrency = $priceCurrency;
        // Optional after $data: compiled Interceptor omits TimezoneInterface.
        $this->timezone = $timezone ?? $context->getLocaleDate();
    }

    public function getCreditLimit()
    {
        return $this->creditService->getCreditLimit(
            (int) $this->customerSession->getCustomerId()
        );
    }

    public function getTransactions()
    {
        return $this->creditService->getTransactions(
            (int) $this->customerSession->getCustomerId(),
            50
        );
    }

    public function formatPrice(float $price): string
    {
        return $this->priceCurrency->format($price, false);
    }

    public function getUsagePercent(): float
    {
        $credit = $this->getCreditLimit();
        if ($credit->getCreditLimit() <= 0) {
            return 0;
        }
        return min(100, ($credit->getUsedCredit() / $credit->getCreditLimit()) * 100);
    }

    public function getTransactionTypeLabel(string $type): string
    {
        $types = \GrupoAwamotos\B2B\Model\CreditTransaction::getTypes();
        return isset($types[$type]) ? (string) $types[$type] : $type;
    }

    public function formatDateTime(?string $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '-';
        }

        try {
            return $this->timezone->date(new \DateTime($dateTime))->format('d/m/Y H:i');
        } catch (\Exception) {
            return substr($dateTime, 0, 16);
        }
    }
}
