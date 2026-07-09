<?php

/**
 * B2B Credit Payment Method
 *
 * Allows B2B customers with available credit to pay orders via faturamento (invoicing).
 * Debits the customer's credit limit upon order placement.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Payment;

use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\Method\AbstractMethod;

class CreditPayment extends AbstractMethod
{
    public const METHOD_CODE = 'b2b_credit';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Allowed payment term keys
     */
    private const VALID_TERMS = ['a_vista', '30', '60', '90'];

    /**
     * @var CreditService
     */
    private CreditService $creditService;

    /**
     * @var B2BHelper
     */
    private B2BHelper $b2bHelper;
    private CustomerRepositoryInterface $customerRepository;

    /**
     * Assign data from checkout to payment info.
     *
     * Stores the selected payment term (30/60/90/a_vista) as additional information.
     *
     * @param \Magento\Framework\DataObject $data
     * @return $this
     */
    public function assignData(\Magento\Framework\DataObject $data): self
    {
        parent::assignData($data);

        $additionalData = $data->getData('additional_data') ?? $data->getData('additional_information') ?? [];
        $term = $additionalData['payment_term'] ?? 'a_vista';

        // Validate term
        if (!in_array($term, self::VALID_TERMS, true)) {
            $term = 'a_vista';
        }

        $this->getInfoInstance()->setAdditionalInformation('payment_term', $term);

        return $this;
    }

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        PaymentHelper $paymentData,
        ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        CreditService $creditService,
        B2BHelper $b2bHelper,
        CustomerRepositoryInterface $customerRepository,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->creditService = $creditService;
        $this->b2bHelper = $b2bHelper;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Check if payment method is available
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        if (!$this->creditService->isEnabled() || !parent::isAvailable($quote) || $quote === null) {
            return false;
        }

        $customerId = (int)$quote->getCustomerId();
        if ($customerId === 0) {
            return false;
        }

        if (!$this->isApprovedCustomer($customerId)) {
            return false;
        }

        $customerGroupId = (int)$quote->getCustomerGroupId();
        if (!$this->b2bHelper->isB2BGroup($customerGroupId) || $customerGroupId === $this->b2bHelper->getPendingGroupId()) {
            return false;
        }

        $grandTotal = max(0.0, (float)$quote->getGrandTotal());
        if ($grandTotal <= 0.0) {
            return false;
        }

        return $this->creditService->hasSufficientCredit($customerId, $grandTotal);
    }

    /**
     * Capture payment — debits customer credit
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $customerId = (int)$order->getCustomerId();

        if ($customerId === 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Pagamento via crédito B2B disponível apenas para clientes logados.')
            );
        }

        $this->creditService->charge(
            $customerId,
            (float)$amount,
            (int)$order->getId(),
            sprintf('Pagamento do pedido #%s', $order->getIncrementId())
        );

        $payment->setTransactionId('b2b_credit_' . $order->getIncrementId());
        $payment->setIsTransactionClosed(true);

        return $this;
    }

    /**
     * Refund payment — returns credit to customer
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        $customerId = (int)$order->getCustomerId();

        if ($customerId > 0) {
            $this->creditService->refund(
                $customerId,
                (float)$amount,
                (int)$order->getId(),
                sprintf('Estorno do pedido #%s', $order->getIncrementId())
            );
        }

        return $this;
    }

    /**
     * Get payment method title from config
     *
     * @return string
     */
    public function getTitle(): string
    {
        $title = $this->_scopeConfig->getValue(
            'grupoawamotos_b2b/credit/payment_title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $title ? (string)$title : (string)__('Crédito B2B (Faturamento)');
    }

    private function isApprovedCustomer(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $statusAttribute = $customer->getCustomAttribute('b2b_approval_status');
            if ($statusAttribute === null) {
                return false;
            }

            return (string) $statusAttribute->getValue() === ApprovalStatus::STATUS_APPROVED;
        } catch (\Exception) {
            return false;
        }
    }
}
