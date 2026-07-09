<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Link;

use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use GrupoAwamotos\B2B\Helper\Data as B2bHelper;
use Magento\Customer\Block\Account\SortLink;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * B2B account sidebar link with enterprise-style conditional visibility.
 *
 * Layout arguments:
 *  - visibility: b2b | b2b_approved (default b2b_approved)
 *  - feature: credit | quick_order | quote | order_approval | '' (optional)
 */
class B2bAccountLink extends SortLink
{
    private const VISIBILITY_B2B = 'b2b';

    private const VISIBILITY_B2B_APPROVED = 'b2b_approved';

    private const FEATURE_CREDIT = 'credit';

    private const FEATURE_QUICK_ORDER = 'quick_order';

    private const FEATURE_QUOTE = 'quote';

    private const FEATURE_ORDER_APPROVAL = 'order_approval';

    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly CustomerSession $customerSession,
        private readonly B2bConfig $b2bConfig,
        private readonly B2bHelper $b2bHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }

        return parent::_toHtml();
    }

    private function shouldRender(): bool
    {
        if (!$this->b2bConfig->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return false;
        }

        $visibility = (string) ($this->getData('visibility') ?: self::VISIBILITY_B2B_APPROVED);
        if ($visibility === self::VISIBILITY_B2B && !$this->b2bHelper->isB2BCustomer()) {
            return false;
        }
        if ($visibility === self::VISIBILITY_B2B_APPROVED && !$this->b2bHelper->isApprovedB2BCustomer()) {
            return false;
        }

        return $this->isFeatureEnabled((string) ($this->getData('feature') ?: ''));
    }

    private function isFeatureEnabled(string $feature): bool
    {
        return match ($feature) {
            '' => true,
            self::FEATURE_CREDIT => $this->scopeConfig->isSetFlag(
                'grupoawamotos_b2b/credit/enabled',
                ScopeInterface::SCOPE_STORE
            ),
            self::FEATURE_QUICK_ORDER => $this->scopeConfig->isSetFlag(
                'grupoawamotos_b2b/features/quick_order_enabled',
                ScopeInterface::SCOPE_STORE
            ),
            self::FEATURE_QUOTE => $this->b2bHelper->isQuoteEnabled(),
            self::FEATURE_ORDER_APPROVAL => $this->b2bHelper->isOrderApprovalEnabled(),
            default => true,
        };
    }
}
