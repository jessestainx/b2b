<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Observer;

use GrupoAwamotos\WhatsAppCommerce\Api\AttendantInterface;
use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use GrupoAwamotos\WhatsAppCommerce\Model\MessageSender;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Notifies the attendant ("vendedora") assigned to a customer, via WhatsApp,
 * whenever that customer registers a new account.
 *
 * The customer is NOT messaged here: the registration form only collects
 * name/e-mail/password (no phone), so there is no WhatsApp number available
 * for the customer at this stage. The customer's own WhatsApp confirmation
 * happens later, when they place their first order (see OrderNotification).
 */
class CustomerRegisterNotification implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly MessageSender $messageSender,
        private readonly AttendantInterface $attendantResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled() || !$this->config->isNotifyCustomerRegisteredEnabled()) {
            return;
        }

        /** @var CustomerInterface|null $customer */
        $customer = $observer->getEvent()->getData('customer');
        if (!$customer || !$customer->getId()) {
            return;
        }

        try {
            $attendant = $this->attendantResolver->getByCustomerId((int) $customer->getId());
            if (empty($attendant['found']) || empty($attendant['whatsapp'])) {
                return;
            }

            $name = trim((string) $customer->getFirstname() . ' ' . (string) $customer->getLastname());
            $message = sprintf(
                '🆕 Novo cliente cadastrado: %s (%s)',
                $name !== '' ? $name : 'Cliente',
                (string) $customer->getEmail()
            );

            $this->messageSender->send((string) $attendant['whatsapp'], $message);
        } catch (\Exception $e) {
            $this->logger->error('WhatsApp customer registration notification failed: ' . $e->getMessage(), [
                'customer_id' => $customer->getId(),
            ]);
        }
    }
}
