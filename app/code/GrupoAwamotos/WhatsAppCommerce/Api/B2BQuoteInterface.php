<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp B2B Quote API
 *
 * Allows B2B customers to submit and manage quote requests via WhatsApp.
 * Integrates with existing B2B QuoteRequest system.
 */
interface B2BQuoteInterface
{
    /**
     * Submit a quote request from WhatsApp
     *
     * @param string $phone Customer phone number
     * @param mixed[] $items Array of items [{sku, qty}]
     * @param string|null $message Optional message from customer
     * @return mixed[] Quote request result (request_id, status, message)
     */
    public function submitQuote(string $phone, array $items, ?string $message = null): array;

    /**
     * Get quote requests for a phone number
     *
     * @param string $phone Customer phone number
     * @return mixed[] List of quote requests with status and details
     */
    public function getQuotes(string $phone): array;

    /**
     * Get quote request details by ID
     *
     * @param int $requestId Quote request ID
     * @param string $phone Phone for validation
     * @return mixed[] Full quote details including items and quoted prices
     */
    public function getQuoteDetail(int $requestId, string $phone): array;

    /**
     * Accept a quoted price (converts to order)
     *
     * @param int $requestId Quote request ID
     * @param string $phone Phone for validation
     * @return mixed[] Result with order_id or checkout link
     */
    public function acceptQuote(int $requestId, string $phone): array;
}
