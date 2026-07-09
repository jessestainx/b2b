<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

interface ReviewInterface
{
    /**
     * Save a product review submitted via WhatsApp
     *
     * @param string $phone Customer phone
     * @param string $sku Product SKU
     * @param int $rating Star rating (1-5)
     * @param string $text Review text
     * @param string|null $nickname Display name (optional, uses customer name)
     * @return mixed[]
     */
    public function saveReview(
        string $phone,
        string $sku,
        int $rating,
        string $text,
        ?string $nickname = null
    ): array;
}
