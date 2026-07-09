<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use GrupoAwamotos\B2B\Helper\Data as B2bHelper;
use Magento\Customer\Block\Account\SortLinkInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Non-interactive section label in the customer account sidebar.
 */
class NavSection extends Template implements SortLinkInterface
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly B2bConfig $b2bConfig,
        private readonly B2bHelper $b2bHelper,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder()
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function getSectionLabel(): string
    {
        return (string) $this->getData('label');
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

        $visibility = (string) ($this->getData('visibility') ?: 'b2b_approved');
        if ($visibility === 'b2b' && !$this->b2bHelper->isB2BCustomer()) {
            return false;
        }
        if ($visibility === 'b2b_approved' && !$this->b2bHelper->isApprovedB2BCustomer()) {
            return false;
        }

        $feature = (string) ($this->getData('feature') ?: '');
        if ($feature === '') {
            return true;
        }

        return match ($feature) {
            'credit' => $this->scopeConfig->isSetFlag(
                'grupoawamotos_b2b/credit/enabled',
                ScopeInterface::SCOPE_STORE
            ),
            'quote' => $this->b2bHelper->isQuoteEnabled(),
            default => true,
        };
    }
}
