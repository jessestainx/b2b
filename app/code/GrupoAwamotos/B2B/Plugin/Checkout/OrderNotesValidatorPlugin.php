<?php

/**
 * Validate and save order notes from checkout
 *
 * Intercepts payment information save to validate order notes constraints.
 * P2-4.4: Validates order notes before order placement
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

class OrderNotesValidatorPlugin
{
    public function __construct(
        private readonly B2BCheckoutValidationService $validationService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate order notes before saving payment information and placing order
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
        $this->validateAndSaveOrderNotes($cartId, $paymentMethod);
        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Validate order notes before saving payment information
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
        $this->validateAndSaveOrderNotes($cartId, $paymentMethod);
        return [$cartId, $paymentMethod, $billingAddress];
    }

    /**
     * Extract order notes from payment extension attributes and save to quote
     *
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @return void
     * @throws LocalizedException
     */
    private function validateAndSaveOrderNotes(int $cartId, PaymentInterface $paymentMethod): void
    {
        try {
            $quote = $this->cartRepository->getActive($cartId);

            // Extract order notes from extension attributes if available
            // (This will be set by frontend when user fills the field)
            $extensionAttributes = $quote->getExtensionAttributes();
            if ($extensionAttributes !== null && method_exists($extensionAttributes, 'getB2bOrderNotes')) {
                $orderNotes = $extensionAttributes->getB2bOrderNotes();
                if ($orderNotes !== null) {
                    $quote->setData('b2b_order_notes', $orderNotes);
                }
            }

            $this->validationService->validateCheckoutData($quote, $paymentMethod);

            $this->logger->info('[B2B] Order notes validated', [
                'quote_id' => $cartId,
            ]);
        } catch (LocalizedException $e) {
            $this->logger->warning('[B2B] Order notes validation failed: ' . $e->getMessage(), [
                'quote_id' => $cartId,
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[B2B] Erro ao validar notas do pedido: ' . $e->getMessage(), [
                'quote_id' => $cartId,
                'exception' => $e,
            ]);
            throw new LocalizedException(__('Erro ao validar notas do pedido.'));
        }
    }
}
