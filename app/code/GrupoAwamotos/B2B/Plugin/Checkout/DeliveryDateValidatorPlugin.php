<?php

/**
 * Validate and save delivery date from checkout
 *
 * Intercepts payment information save to validate delivery date format and constraints.
 * P2-4.3: Validates delivery date before order placement
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Model\Checkout\B2BCheckoutValidationService;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Psr\Log\LoggerInterface;

class DeliveryDateValidatorPlugin
{
    public function __construct(
        private readonly B2BCheckoutValidationService $validationService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate delivery date before saving payment information and placing order
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws LocalizedException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateAndSaveDeliveryDate($cartId, $paymentMethod);
        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Validate delivery date before saving payment information
     *
     * @param PaymentInformationManagementInterface $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return array
     * @throws LocalizedException
     */
    public function beforeSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): array {
        $this->validateAndSaveDeliveryDate($cartId, $paymentMethod);
        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Validate and save delivery date
     *
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @return void
     * @throws LocalizedException
     */
    private function validateAndSaveDeliveryDate(int $cartId, PaymentInterface $paymentMethod): void
    {
        try {
            $quote = $this->cartRepository->getActive($cartId);
            $this->validationService->validateCheckoutData($quote, $paymentMethod);

            $this->logger->info('[B2B] Delivery date validated', [
                'quote_id' => $cartId,
            ]);
        } catch (LocalizedException $e) {
            $this->logger->warning('[B2B] Delivery date validation failed: ' . $e->getMessage(), [
                'quote_id' => $cartId,
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao validar data de entrega: ' . $e->getMessage(), [
                'quote_id' => $cartId,
                'exception' => $e,
            ]);
            throw new LocalizedException(__('Erro ao validar data de entrega.'));
        }
    }
}
