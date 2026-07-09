<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Catalog API
 *
 * Endpoints simplificados para consumo via Typebot.
 * Retorna dados formatados para exibição no WhatsApp (texto, imagens, botões).
 */
interface CatalogInterface
{
    /**
     * Search products by text query
     *
     * @param string $q Search query
     * @param int $page Page number (default: 1)
     * @return mixed[] Product list with simplified data
     */
    public function search(string $q, int $page = 1): array;

    /**
     * Get top-level categories
     *
     * @return mixed[] Category list
     */
    public function getCategories(): array;

    /**
     * Get products by category
     *
     * @param int $categoryId Category ID
     * @param int $page Page number (default: 1)
     * @return mixed[] Product list
     */
    public function getProductsByCategory(int $categoryId, int $page = 1): array;

    /**
     * Get product details
     *
     * @param string $sku Product SKU
     * @return mixed[] Product data with image, price and stock
     */
    public function getProduct(string $sku): array;
}
