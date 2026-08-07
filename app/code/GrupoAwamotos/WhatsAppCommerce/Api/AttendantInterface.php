<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Attendant Routing API
 *
 * Returns the assigned attendant for a customer (by phone number or customer ID).
 * Used by automations to auto-assign conversations and to notify the attendant
 * ("vendedora") about her customers' registration and order events.
 */
interface AttendantInterface
{
    /**
     * Get the assigned attendant for a phone number
     *
     * @param string $phone Phone number (with or without country code)
     * @return mixed[] Attendant data
     */
    public function getByPhone(string $phone): array;

    /**
     * Get the assigned attendant for a customer ID
     *
     * @param int $customerId
     * @return mixed[] Attendant data
     */
    public function getByCustomerId(int $customerId): array;
}
