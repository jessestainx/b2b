<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'abandoned_cart/general/enabled';
    private const XML_PATH_MIN_CART_VALUE = 'abandoned_cart/general/min_cart_value';
    private const XML_PATH_EXCLUDE_GUEST = 'abandoned_cart/general/exclude_guest';

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getMinCartValue(?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_MIN_CART_VALUE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function excludeGuest(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_GUEST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isEmailEnabled(int $emailNumber, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            "abandoned_cart/email_{$emailNumber}/enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEmailDelay(int $emailNumber, ?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            "abandoned_cart/email_{$emailNumber}/delay_hours",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEmailSubject(int $emailNumber, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            "abandoned_cart/email_{$emailNumber}/subject",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEmailTemplate(int $emailNumber, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            "abandoned_cart/email_{$emailNumber}/template",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isCouponEnabled(int $emailNumber, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            "abandoned_cart/email_{$emailNumber}/coupon_enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCouponDiscount(int $emailNumber, ?int $storeId = null): float
    {
        return (float) $this->scopeConfig->getValue(
            "abandoned_cart/email_{$emailNumber}/coupon_discount",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCouponType(int $emailNumber, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            "abandoned_cart/email_{$emailNumber}/coupon_type",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isWhatsappEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            'abandoned_cart/whatsapp/enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isWhatsappEnabledForWave(int $waveNumber, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            "abandoned_cart/whatsapp/wave_{$waveNumber}_enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
