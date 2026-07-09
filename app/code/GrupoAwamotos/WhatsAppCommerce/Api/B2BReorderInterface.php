<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp B2B Reorder API
 *
 * Allows B2B customers to quickly reorder previous purchases via WhatsApp.
 */
interface B2BReorderInterface
{
    /**
     * Get reorderable orders for a phone number
     *
     * Returns recent orders with items and totals for easy reordering.
     *
     * @param string $phone Customer phone number
     * @param int $limit Maximum number of orders to return
     * @return mixed[] List of orders with items available for reorder
     */
    public function getReorderableOrders(string $phone, int $limit = 5): array;

    /**
     * Reorder a specific previous order
     *
     * Creates a new cart populated with items from the specified order.
     * Returns a checkout link for the customer.
     *
     * @param string $phone Customer phone number
     * @param string $orderIncrementId Order increment ID (e.g. "000000042")
     * @return mixed[] Cart data with checkout link
     */
    public function reorderByOrderId(string $phone, string $orderIncrementId): array;

    /**
     * Reorder the last order for this customer
     *
     * @param string $phone Customer phone number
     * @return mixed[] Cart data with checkout link
     */
    public function reorderLast(string $phone): array;
}
