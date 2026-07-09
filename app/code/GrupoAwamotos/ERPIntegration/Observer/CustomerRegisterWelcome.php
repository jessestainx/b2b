<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Observer;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Send WhatsApp welcome message to new customers upon registration.
 */
class CustomerRegisterWelcome implements ObserverInterface
{
    public function __construct(
        private readonly ZApiClient $zapiClient,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->helper->isWhatsAppEnabled() || !$this->helper->isWhatsAppWelcomeEnabled()) {
            return;
        }

        /** @var CustomerInterface|null $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer instanceof CustomerInterface || !$customer->getId()) {
            return;
        }

        // Cadastro B2B pendente já recebe mensagem específica via módulo B2B
        $b2bCnpj = $customer->getCustomAttribute('b2b_cnpj');
        if ($b2bCnpj && (string) $b2bCnpj->getValue() !== '') {
            return;
        }

        $approvalStatus = $customer->getCustomAttribute('b2b_approval_status');
        if ($approvalStatus && (string) $approvalStatus->getValue() === 'pending') {
            return;
        }

        $phone = $this->extractPhone($customer);

        if (empty($phone)) {
            $this->logger->info('[Z-API Welcome] Customer registered without phone, skipping welcome message.', [
                'customer_id' => $customer->getId(),
            ]);
            return;
        }

        $firstName = $customer->getFirstname() ?: 'Cliente';

        try {
            $result = $this->zapiClient->sendWelcomeMessage($phone, $firstName);

            if ($result !== null) {
                $this->logger->info('[Z-API Welcome] Welcome message sent successfully.', [
                    'customer_id' => $customer->getId(),
                    'phone' => $this->maskPhone($phone),
                ]);
            } else {
                $this->logger->warning('[Z-API Welcome] Failed to send welcome message.', [
                    'customer_id' => $customer->getId(),
                    'phone' => $this->maskPhone($phone),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[Z-API Welcome] Exception sending welcome message: ' . $e->getMessage(), [
                'customer_id' => $customer->getId(),
                'phone' => $this->maskPhone($phone),
            ]);
        }
    }

    /**
     * Extract phone number from customer data or default billing address.
     */
    private function extractPhone(CustomerInterface $customer): string
    {
        $b2bPhoneAttr = $customer->getCustomAttribute('b2b_phone');
        if ($b2bPhoneAttr && !empty($b2bPhoneAttr->getValue())) {
            return (string) $b2bPhoneAttr->getValue();
        }

        // Try custom attribute "telephone" first
        $telephoneAttr = $customer->getCustomAttribute('telephone');
        if ($telephoneAttr && !empty($telephoneAttr->getValue())) {
            return (string) $telephoneAttr->getValue();
        }

        // Try default billing address
        try {
            $defaultBilling = $customer->getDefaultBilling();
            if ($defaultBilling) {
                foreach ($customer->getAddresses() as $address) {
                    if ((int) $address->getId() === (int) $defaultBilling) {
                        $phone = $address->getTelephone();
                        if (!empty($phone)) {
                            return $phone;
                        }
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('[Z-API Welcome] Error reading customer addresses: ' . $e->getMessage());
        }

        // Fallback: try any address with a phone
        try {
            foreach ($customer->getAddresses() as $address) {
                $phone = $address->getTelephone();
                if (!empty($phone)) {
                    return $phone;
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('[Z-API Welcome] Error reading customer addresses: ' . $e->getMessage());
        }

        return '';
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) <= 4) {
            return '****';
        }
        return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 8) . substr($digits, -4);
    }
}
