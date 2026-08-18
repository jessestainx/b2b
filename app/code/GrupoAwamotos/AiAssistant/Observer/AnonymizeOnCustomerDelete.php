<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * DSR: anonymize conversation rows when a Magento customer is deleted.
 */
class AnonymizeOnCustomerDelete implements ObserverInterface
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getData('customer');
        $customerId = 0;
        if (is_object($customer) && method_exists($customer, 'getId')) {
            $customerId = (int) $customer->getId();
        }
        if ($customerId <= 0) {
            return;
        }

        try {
            $table = $this->resource->getTableName('grupoawamotos_ai_conversation_log');
            $updated = $this->resource->getConnection()->update(
                $table,
                [
                    'user_message' => '[titular excluído]',
                    'assistant_response' => null,
                    'error_detail' => null,
                    'customer_id' => null,
                    'ip_hash' => null,
                ],
                ['customer_id = ?' => $customerId]
            );
            $this->logger->info('[AiAssistant] DSR anonymize on customer delete', [
                'rows' => $updated,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('[AiAssistant] DSR anonymize failed: ' . $e->getMessage());
        }
    }
}
