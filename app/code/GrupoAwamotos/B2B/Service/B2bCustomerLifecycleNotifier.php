<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Service;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use Magento\Framework\Event\ManagerInterface;

/**
 * Dispara eventos de ciclo de vida B2B consumidos por observers (WhatsApp, atendente, etc.).
 */
class B2bCustomerLifecycleNotifier
{
    public function __construct(
        private readonly ManagerInterface $eventManager
    ) {
    }

    public function notifyRegistered(B2bCustomer $b2bCustomer): void
    {
        $customerId = (int) $b2bCustomer->getCustomerId();
        if ($customerId <= 0) {
            return;
        }

        $this->eventManager->dispatch('grupoawamotos_b2b_registration_submitted', [
            'customer_id' => $customerId,
            'customer' => $customerId,
            'b2b_customer' => $b2bCustomer,
            'razao_social' => $b2bCustomer->getRazaoSocial(),
            'cnpj' => $b2bCustomer->getCnpj(),
            'phone' => $b2bCustomer->getPhone(),
            'registration_context' => [
                'lead_type' => 'b2b_cnpj',
                'person_type' => 'pj',
                'approval_status' => 'pending',
                'register_channel' => 'b2b_register_post',
            ],
        ]);
    }

    public function notifyApproved(B2bCustomer $b2bCustomer): void
    {
        $customerId = (int) $b2bCustomer->getCustomerId();
        if ($customerId <= 0) {
            return;
        }

        $this->eventManager->dispatch('grupoawamotos_b2b_customer_approved', [
            'customer_id' => $customerId,
            'customer' => $customerId,
            'b2b_customer' => $b2bCustomer,
        ]);
    }
}
