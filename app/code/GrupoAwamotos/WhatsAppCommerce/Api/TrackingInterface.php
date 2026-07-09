<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Tracking API
 *
 * Allows customers to check order status by phone number.
 */
interface TrackingInterface
{
    /**
     * Get recent orders for a phone number
     *
     * @param string $phone Phone number (with or without country code)
     * @return mixed[] List of orders with status, tracking info
     */
    public function getOrders(string $phone): array;
}
