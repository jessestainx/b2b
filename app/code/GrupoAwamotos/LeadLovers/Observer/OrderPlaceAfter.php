<?php

declare(strict_types=1);

namespace GrupoAwamotos\LeadLovers\Observer;

use GrupoAwamotos\LeadLovers\Model\LeadLoversClient;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class OrderPlaceAfter implements ObserverInterface
{
    public function __construct(
        private readonly LeadLoversClient $client,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Order|null $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order instanceof Order) {
            return;
        }

        if ((string) $order->getCustomerEmail() === '') {
            $this->logger->warning('[LeadLovers] Pedido sem e-mail, lead nao enviado.', [
                'order' => $order->getIncrementId(),
            ]);
            return;
        }

        $this->client->sendLeadFromOrder($order);
    }
}
