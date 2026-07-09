<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Connection;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Pre-warms Redis cache for all B2B ERP price lists.
 *
 * Runs every 4 hours (20 min after SyncPrices) to ensure all MT_MATERIALLISTA
 * prices are cached in Redis before B2B customers request them during page
 * render. Prevents cold-cache ERP hits from GroupPricePlugin / CustomerPriceProvider.
 *
 * Coverage: 25 price lists × ~1000 SKUs = ~25,000 Redis writes per run.
 * Each list is fetched in a single query from ERP SQL Server.
 */
class WarmB2BPrices
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CustomerPriceProvider $priceProvider,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $start = microtime(true);
        $filial = $this->helper->getStockFilial();

        try {
            // Discover all distinct price lists from ERP (single query)
            $rows = $this->connection->query(
                "SELECT DISTINCT FATORPRECO FROM MT_MATERIALLISTA
                 WHERE FILIAL = ? AND VLRVDSUG > 0
                 ORDER BY FATORPRECO",
                [$filial]
            ) ?? [];

            if (empty($rows)) {
                $this->logger->warning('[WarmB2BPrices] No price lists found in ERP for filial ' . $filial);
                return;
            }

            $totalWarmed = 0;
            $lists = [];

            foreach ($rows as $row) {
                $listCode = (int) $row['FATORPRECO'];
                $count = $this->priceProvider->warmPriceList($listCode);
                $totalWarmed += $count;
                $lists[] = "#{$listCode}:{$count}";
            }

            $elapsed = round(microtime(true) - $start, 1);

            $this->logger->info(sprintf(
                '[WarmB2BPrices] Done: %d prices across %d lists in %ss. Lists: %s',
                $totalWarmed,
                count($rows),
                $elapsed,
                implode(', ', $lists)
            ));
        } catch (\Throwable $e) {
            $this->logger->error('[WarmB2BPrices] Fatal error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
