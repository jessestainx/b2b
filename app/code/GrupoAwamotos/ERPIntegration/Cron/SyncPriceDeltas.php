<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\PriceDeltaSync;
use Psr\Log\LoggerInterface;

class SyncPriceDeltas
{
    public function __construct(
        private readonly PriceDeltaSync $priceDeltaSync,
        private readonly Helper $helper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->helper->isPriceDeltaSyncEnabled()) {
            return;
        }

        try {
            $result = $this->priceDeltaSync->execute();
            $this->logger->info('[ERP Cron] SyncPriceDeltas finished.', $result);
        } catch (\Throwable $e) {
            $this->logger->error('[ERP Cron] SyncPriceDeltas failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
