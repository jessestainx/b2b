<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use GrupoAwamotos\B2B\Model\Sectra\OrderImportGate;
use GrupoAwamotos\B2B\Model\Sectra\ProspectEvent;
use GrupoAwamotos\B2B\Model\Sectra\SectraImportStatus;
use GrupoAwamotos\B2B\Model\Sectra\SectraSyncLogger;
use GrupoAwamotos\B2B\Model\Sectra\ValidatorChecker;
use GrupoAwamotos\ERPIntegration\Api\B2bOrderPullCustomerDataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Marks validated B2B orders as ready_for_import after successful checkout.
 */
class SectraValidatedOrderPlaceObserver implements ObserverInterface
{
    public function __construct(
        private readonly B2bConfig $b2bConfig,
        private readonly B2bOrderPullCustomerDataInterface $orderPullCustomerData,
        private readonly ValidatorChecker $validatorChecker,
        private readonly SectraSyncLogger $syncLogger,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->b2bConfig->isEnabled()) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface || !$order->getEntityId()) {
            return;
        }

        $customerId = (int) ($order->getCustomerId() ?? 0);
        if ($customerId <= 0 || !$this->orderPullCustomerData->isApprovedB2bCustomer($customerId)) {
            return;
        }

        if (!$this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $this->resourceConnection->getConnection()->update(
            'sales_order',
            ['sectra_import_status' => SectraImportStatus::READY_FOR_IMPORT],
            ['entity_id = ?' => $orderId]
        );

        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
        $this->syncLogger->log(
            ProspectEvent::ORDER_RELEASED_FOR_IMPORT,
            sprintf('Pedido #%s liberado para fila ERP (ready_for_import).', $order->getIncrementId()),
            $customerId,
            $orderId,
            null,
            $sectraChave,
            'success'
        );
    }
}
