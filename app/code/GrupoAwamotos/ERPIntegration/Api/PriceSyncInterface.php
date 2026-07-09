<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

interface PriceSyncInterface
{
    public function syncAll(?int $priceList = null): array;
    public function getPriceBySku(string $sku): ?array;
    public function getPricesForSkus(array $skus, ?int $priceList = null): array;
}
