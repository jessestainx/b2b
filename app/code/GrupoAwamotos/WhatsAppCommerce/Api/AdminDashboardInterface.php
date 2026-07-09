<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

interface AdminDashboardInterface
{
    /**
     * Get today's sales summary
     *
     * @return mixed[]
     */
    public function salesToday(): array;

    /**
     * Get stock info for a product
     *
     * @param string $sku
     * @return mixed[]
     */
    public function stockCheck(string $sku): array;

    /**
     * Get new customers count (last 24h / 7d / 30d)
     *
     * @return mixed[]
     */
    public function newCustomers(): array;

    /**
     * Get order detail by increment ID
     *
     * @param string $incrementId
     * @return mixed[]
     */
    public function orderDetail(string $incrementId): array;

    /**
     * Get top selling products in the last N days
     *
     * @param int $days
     * @return mixed[]
     */
    public function topSelling(int $days = 30): array;
}
