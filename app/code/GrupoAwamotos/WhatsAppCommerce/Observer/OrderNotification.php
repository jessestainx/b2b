<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Observer;

use GrupoAwamotos\WhatsAppCommerce\Api\AttendantInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use GrupoAwamotos\WhatsAppCommerce\Model\MessageSender;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class OrderNotification implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly MessageSender $messageSender,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AttendantInterface $attendantResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $event = $observer->getEvent();
        $eventName = $event->getName();

        $type = $this->mapEventToType($eventName);
        if ($type === null) {
            return;
        }

        $order = $this->extractOrder($event, $eventName);
        if (!$order) {
            return;
        }

        $this->notifyCustomer($order, $type);

        // Vendedora is notified only for the "placed" trigger, regardless of
        // whether the customer has WhatsApp opt-in/phone available.
        if ($type === 'placed') {
            $this->notifyAttendant($order);
        }
    }

    private function notifyCustomer(OrderInterface $order, string $type): void
    {
        if (!$this->isNotificationEnabled($type)) {
            return;
        }

        $phone = $this->getOrderPhone($order);
        if (empty($phone)) {
            return;
        }

        if (!$this->hasWhatsappConsent($order)) {
            $this->logger->debug('WhatsApp notification skipped - no opt-in', [
                'order_id' => $order->getIncrementId(),
            ]);
            return;
        }

        try {
            $data = [
                'order_id' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'total' => number_format((float) $order->getGrandTotal(), 2, ',', '.'),
                'items_count' => (string) $order->getTotalItemCount(),
                'customer_name' => $order->getCustomerFirstname() ?: 'Cliente',
            ];

            if ($type === 'shipped') {
                $data['tracking'] = $this->getTrackingInfo($order);
            }

            $this->messageSender->sendOrderNotification($phone, $order->getIncrementId(), $type, $data);
        } catch (\Exception $e) {
            $this->logger->error('WhatsApp order notification failed: ' . $e->getMessage(), [
                'order_id' => $order->getIncrementId(),
                'type' => $type,
            ]);
        }
    }

    /**
     * Notify the attendant ("vendedora") assigned to the customer that placed the order.
     */
    private function notifyAttendant(OrderInterface $order): void
    {
        if (!$this->config->isNotifyAttendantOnOrderPlacedEnabled()) {
            return;
        }

        $customerId = $order->getCustomerId();
        if (!$customerId) {
            return;
        }

        try {
            $attendant = $this->attendantResolver->getByCustomerId((int) $customerId);
            if (empty($attendant['found']) || empty($attendant['whatsapp'])) {
                return;
            }

            $message = sprintf(
                '🛒 Novo pedido #%s de %s - R$ %s',
                $order->getIncrementId(),
                $order->getCustomerFirstname() ?: 'Cliente',
                number_format((float) $order->getGrandTotal(), 2, ',', '.')
            );

            $this->messageSender->send((string) $attendant['whatsapp'], $message);
        } catch (\Exception $e) {
            $this->logger->error('WhatsApp attendant order notification failed: ' . $e->getMessage(), [
                'order_id' => $order->getIncrementId(),
                'customer_id' => $customerId,
            ]);
        }
    }

    private function extractOrder($event, string $eventName): ?OrderInterface
    {
        if ($eventName === 'sales_order_place_after') {
            return $event->getOrder();
        }

        if ($eventName === 'sales_order_invoice_pay') {
            $invoice = $event->getInvoice();
            return $invoice?->getOrder();
        }

        if ($eventName === 'sales_order_shipment_save_after') {
            $shipment = $event->getShipment();
            return $shipment?->getOrder();
        }

        if ($eventName === 'sales_order_creditmemo_save_after') {
            $creditmemo = $event->getCreditmemo();
            return $creditmemo?->getOrder();
        }

        return null;
    }

    private function mapEventToType(string $eventName): ?string
    {
        $map = [
            'sales_order_place_after' => 'placed',
            'sales_order_invoice_pay' => 'paid',
            'sales_order_shipment_save_after' => 'shipped',
            'sales_order_creditmemo_save_after' => 'refunded',
        ];

        return $map[$eventName] ?? null;
    }

    private function isNotificationEnabled(string $type): bool
    {
        return match ($type) {
            'placed' => $this->config->isNotifyOrderPlacedEnabled(),
            'paid' => $this->config->isNotifyOrderPaidEnabled(),
            'shipped' => $this->config->isNotifyOrderShippedEnabled(),
            'refunded' => $this->config->isNotifyOrderRefundedEnabled(),
            default => false,
        };
    }

    private function getOrderPhone(OrderInterface $order): string
    {
        $billingAddress = $order->getBillingAddress();
        return $billingAddress ? (string) $billingAddress->getTelephone() : '';
    }

    private function hasWhatsappConsent(OrderInterface $order): bool
    {
        $customerId = $order->getCustomerId();
        if (!$customerId) {
            return false;
        }

        try {
            $customer = $this->customerRepository->getById((int) $customerId);
            $optin = $customer->getCustomAttribute('whatsapp_optin');
            return $optin && (int) $optin->getValue() === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTrackingInfo(OrderInterface $order): string
    {
        $tracks = [];
        $shipmentsCollection = $order->getShipmentsCollection();

        if ($shipmentsCollection) {
            foreach ($shipmentsCollection as $shipment) {
                foreach ($shipment->getAllTracks() as $track) {
                    $tracks[] = $track->getCarrierCode() . ': ' . $track->getTrackNumber();
                }
            }
        }

        return implode(', ', $tracks) ?: 'Em processamento';
    }
}
