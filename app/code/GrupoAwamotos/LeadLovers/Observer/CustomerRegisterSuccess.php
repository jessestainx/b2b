<?php

declare(strict_types=1);

namespace GrupoAwamotos\LeadLovers\Observer;

use GrupoAwamotos\LeadLovers\Model\LeadLoversClient;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class CustomerRegisterSuccess implements ObserverInterface
{
    public function __construct(
        private readonly LeadLoversClient $client,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var CustomerInterface|null $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer instanceof CustomerInterface) {
            return;
        }

        $email = (string) $customer->getEmail();
        if ($email === '') {
            $this->logger->warning('[LeadLovers] Cadastro sem e-mail, lead nao enviado.', [
                'customer_id' => $customer->getId(),
            ]);
            return;
        }

        $this->client->sendLeadFromCustomer($customer);
    }
}
