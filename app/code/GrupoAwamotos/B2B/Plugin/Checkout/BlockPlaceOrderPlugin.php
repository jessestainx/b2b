<?php

/**
 * Block order placement for non-approved logged-in customers and enforce minimum order amount.
 * Intercepts the REST/GraphQL payment+placeOrder endpoint.
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\CheckoutAccessValidator;
use GrupoAwamotos\B2B\Model\CreditService;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Psr\Log\LoggerInterface;

class BlockPlaceOrderPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CreditService
     */
    private $creditService;

    /**
     * @var B2BHelper
     */
    private $b2bHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutAccessValidator
     */
    private $checkoutAccessValidator;

    public function __construct(
        Config $config,
        B2BHelper $b2bHelper,
        CartRepositoryInterface $cartRepository,
        CustomerSession $customerSession,
        CreditService $creditService,
        CheckoutAccessValidator $checkoutAccessValidator,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->b2bHelper = $b2bHelper;
        $this->cartRepository = $cartRepository;
        $this->customerSession = $customerSession;
        $this->creditService = $creditService;
        $this->checkoutAccessValidator = $checkoutAccessValidator;
        $this->logger = $logger;
    }

    /**
     * Before savePaymentInformationAndPlaceOrder - block if user is not approved or below minimum
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateCheckoutAccess((int) $cartId, $paymentMethod);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Before savePaymentInformation - block unauthorized API checkout progression as early as possible.
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws CouldNotSaveException
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateCheckoutAccess((int) $cartId, $paymentMethod);

        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Validate whether the current customer can continue through checkout APIs.
     *
     * @throws CouldNotSaveException
     */
    private function validateCheckoutAccess(int $cartId, PaymentInterface $paymentMethod): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);
        $customerId = (int) $quote->getCustomerId();
        $customerGroupId = (int) $quote->getCustomerGroupId();
        $isB2bGroup = $this->b2bHelper->isB2BGroup($customerGroupId);

        if ($customerId <= 0 && $this->customerSession->isLoggedIn()) {
            $customerId = (int) $this->customerSession->getCustomerId();
        }

        if ($isB2bGroup && $customerId <= 0) {
            throw new CouldNotSaveException(
                __('Faça login com uma conta B2B aprovada para finalizar este pedido.')
            );
        }

        if ($isB2bGroup) {
            $customerState = $this->checkoutAccessValidator->resolveCustomerState($customerId);
            if ($customerState !== CheckoutAccessValidator::STATE_APPROVED) {
                throw new CouldNotSaveException(
                    __('Sua conta precisa ser aprovada antes de realizar compras. Por favor, aguarde a aprovação.')
                );
            }
        }

        // Validate B2B credit sufficiency when paying with b2b_credit
        if ($customerId > 0 && $paymentMethod->getMethod() === 'b2b_credit') {
            try {
                $grandTotal = (float) $quote->getBaseGrandTotal();

                if (!$this->creditService->hasSufficientCredit($customerId, $grandTotal)) {
                    throw new CouldNotSaveException(
                        __('Crédito B2B insuficiente para este pedido. Verifique seu limite disponível ou escolha outra forma de pagamento.')
                    );
                }
            } catch (CouldNotSaveException $e) {
                throw $e;
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error('[B2B] Credit check failed: ' . $e->getMessage(), ['exception' => $e]);
                }
            }
        }

        // Enforce minimum order amount
        if ($this->config->isMinQtyEnabled()) {
            $minAmount = $this->config->getMinOrderAmount();
            if ($minAmount > 0) {
                try {
                    $subtotal = (float) $quote->getBaseSubtotal();

                    if ($subtotal < $minAmount) {
                        throw new CouldNotSaveException(
                            __($this->config->getMinOrderMessage())
                        );
                    }
                } catch (CouldNotSaveException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    // If we can't load the quote, allow the order to proceed
                    if ($this->logger) {
                        $this->logger->debug('[B2B] Exception: ' . $e->getMessage(), ['exception' => $e]);
                    }
                }
            }
        }
    }
}
