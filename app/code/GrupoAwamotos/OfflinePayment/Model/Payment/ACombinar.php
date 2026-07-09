<?php

/**
 * Método de Pagamento "A Combinar"
 */

declare(strict_types=1);

namespace GrupoAwamotos\OfflinePayment\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\DataObject;

/**
 * "A Combinar" offline payment method.
 *
 * Extends AbstractMethod which is marked @deprecated in favour of the Payment
 * Gateway Adapter pattern. However, Magento 2.4.8-p3 core offline methods
 * (Checkmo, Banktransfer, Cashondelivery, Purchaseorder) still extend
 * AbstractMethod. Migrating this trivial no-op authorize to the full Adapter
 * pattern (~150 lines of XML virtual types) adds complexity with zero
 * functional gain. This will be revisited if/when Magento removes
 * AbstractMethod in a future major release.
 *
 * @SuppressWarnings(PHPMD.DeprecatedCode)
 */
class ACombinar extends AbstractMethod
{
    public const CODE = 'acombinar';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = false;

    /**
     * @var bool
     */
    protected $_canCapturePartial = false;

    /**
     * @var bool
     */
    protected $_canRefund = false;

    /**
     * @var bool
     */
    protected $_canVoid = false;

    /**
     * @var bool
     */
    protected $_canUseInternal = true;

    /**
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * @var bool
     */
    protected $_canUseForMultishipping = false;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = false;

    /**
     * Check whether payment method can be used
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?CartInterface $quote = null): bool
    {
        return parent::isAvailable($quote);
    }

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions(): string
    {
        return (string) $this->getConfigData('instructions');
    }

    /**
     * Authorize payment
     *
     * @param DataObject $payment
     * @param float $amount
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // Pagamento offline - não faz nada, apenas autoriza
        return $this;
    }
}
