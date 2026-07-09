<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Cron;

use GrupoAwamotos\CatalogFix\Model\OfertasCategorySync;
use Psr\Log\LoggerInterface;

class SyncOfertasCategory
{
    public function __construct(
        private readonly OfertasCategorySync $ofertasCategorySync,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $result = $this->ofertasCategorySync->sync();
            $this->logger->info('[CatalogFix Cron] Ofertas category sync finished.', $result);
        } catch (\Throwable $e) {
            $this->logger->error('[CatalogFix Cron] Ofertas category sync failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
