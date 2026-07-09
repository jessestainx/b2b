<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

interface CampaignInterface
{
    /**
     * Send a broadcast campaign message to a segment
     *
     * @param string $segment Segment: all_optin, recent_90d, b2b
     * @param string $message Message text
     * @param int $batchSize Max messages per batch (default 50 for Baileys safety)
     * @return mixed[]
     */
    public function sendBroadcast(string $segment, string $message, int $batchSize = 50): array;

    /**
     * Get campaign statistics (opt-in counts by segment)
     *
     * @return mixed[]
     */
    public function getSegmentStats(): array;
}
