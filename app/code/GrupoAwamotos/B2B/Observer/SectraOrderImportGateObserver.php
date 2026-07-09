<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\Sectra\OrderImportGate;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies Sectra import gate right after a B2B order is first persisted.
 *
 * Bound to sales_order_save_after (not sales_order_place_after) because
 * Magento\Sales\Model\Order::place() dispatches sales_order_place_after
 * BEFORE the order has been saved via OrderRepository — entity_id is not
 * yet assigned at that point, so it always no-oped. sales_order_save_after
 * always has entity_id; the sectra_import_status NULL check below ensures
 * this only runs once, on the order's first save (placement), and never
 * re-evaluates/overwrites the status on later saves (invoice, comments,
 * status changes, etc.) which are handled by OrderImportGate's cron
 * backfill/release methods instead.
 */
class SectraOrderImportGateObserver implements ObserverInterface
{
    public function __construct(
        private readonly OrderImportGate $orderImportGate,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface || !$order->getEntityId()) {
            return;
        }

        if ($order->getData('sectra_import_status') !== null) {
            return;
        }

        try {
            $this->orderImportGate->applyOnOrderPlace($order);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[B2B-Sectra] SectraOrderImportGateObserver pedido #%s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
