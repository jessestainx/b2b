<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Cart API
 *
 * Manages guest carts tied to a phone number.
 */
interface CartInterface
{
    /**
     * Create or retrieve a cart for a phone number
     *
     * @param string $phone Phone number with country code
     * @return mixed[] Cart data (cart_id, masked_id)
     */
    public function createCart(string $phone): array;

    /**
     * Add item to the cart for a phone number
     *
     * @param string $phone Phone number
     * @param string $sku Product SKU
     * @param int $qty Quantity
     * @return mixed[] Updated cart summary
     */
    public function addItem(string $phone, string $sku, int $qty = 1): array;

    /**
     * View cart contents for a phone number
     *
     * @param string $phone Phone number
     * @return mixed[] Cart details (items, subtotal)
     */
    public function viewCart(string $phone): array;

    /**
     * Generate a checkout link for a phone number's cart
     *
     * @param string $phone Phone number
     * @return mixed[] Checkout URL
     */
    public function getCheckoutLink(string $phone): array;
}
