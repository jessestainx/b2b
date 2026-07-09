<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use GrupoAwamotos\B2B\Model\Payment\CreditPayment;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class OrderSuccessPresenter
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly TimezoneInterface $timezone,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @return array{
     *     incrementId: string,
     *     orderUrl: string,
     *     formattedDate: string,
     *     grandTotalFormatted: string,
     *     itemsCount: int,
     *     paymentLabel: string,
     *     paymentMethod: string,
     *     headline: string,
     *     subline: string,
     *     nextStep: string,
     *     isCreditPayment: bool
     * }
     */
    public function present(OrderInterface $order): array
    {
        $createdAt = $this->timezone->date(new \DateTime((string) $order->getCreatedAt()));
        $paymentMethod = (string) $order->getPayment()?->getMethod();
        $paymentLabel = (string) $order->getPayment()?->getAdditionalInformation('method_title');
        if ($paymentLabel === '') {
            $paymentLabel = $this->resolveDefaultPaymentLabel($paymentMethod);
        }

        $itemsCount = 0;
        if ($order instanceof Order) {
            foreach ($order->getAllVisibleItems() as $item) {
                $itemsCount += (int) round((float) $item->getQtyOrdered());
            }
        }

        $grandTotal = $this->priceCurrency->format(
            (float) $order->getGrandTotal(),
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            (int) $order->getStoreId(),
            (string) $order->getOrderCurrencyCode()
        );

        return [
            'incrementId' => (string) $order->getIncrementId(),
            'orderUrl' => $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $order->getId()]),
            'formattedDate' => $this->timezone->formatDate($createdAt, \IntlDateFormatter::SHORT, true),
            'grandTotalFormatted' => $grandTotal,
            'itemsCount' => $itemsCount,
            'paymentLabel' => $paymentLabel,
            'paymentMethod' => $paymentMethod,
            'headline' => (string) __('Pedido confirmado!'),
            'subline' => (string) __(
                'Seu pedido %1 foi registrado com sucesso.',
                (string) $order->getIncrementId()
            ),
            'nextStep' => $this->resolveNextStep($paymentMethod),
            'isCreditPayment' => $paymentMethod === CreditPayment::METHOD_CODE,
        ];
    }

    private function resolveDefaultPaymentLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'acombinar' => (string) __('A Combinar'),
            CreditPayment::METHOD_CODE => (string) __('Crédito B2B'),
            default => (string) __('Pagamento B2B'),
        };
    }

    private function resolveNextStep(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'acombinar' => (string) __(
                'Nossa equipe comercial entrará em contato para combinar pagamento e entrega.'
            ),
            CreditPayment::METHOD_CODE => (string) __(
                'O valor será debitado do seu limite de crédito B2B. Acompanhe o status na sua conta.'
            ),
            default => (string) __(
                'Você receberá atualizações sobre o andamento do pedido na sua conta.'
            ),
        };
    }
}
