<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Cron Job - Update Sales Forecasts
 *
 * Runs daily to recalculate sales projections
 */
class UpdateForecasts
{
    private SalesProjection $salesProjection;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        SalesProjection $salesProjection,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->salesProjection = $salesProjection;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     */
    public function execute(): void
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            // Clear cache
            $this->salesProjection->clearCache();

            // Get current month projection
            $currentMonth = $this->salesProjection->getCurrentMonthProjection();

            if (!empty($currentMonth) && !isset($currentMonth['error'])) {
                $this->logger->info(sprintf(
                    '[ERP Cron] Current month projection: Actual: R$ %s | Projected: R$ %s | Progress: %s%%',
                    number_format($currentMonth['actual_sales'] ?? 0, 2, ',', '.'),
                    number_format($currentMonth['projected_total'] ?? 0, 2, ',', '.'),
                    number_format($currentMonth['progress_percentage'] ?? 0, 1)
                ));

                // Log alert if needed
                if (($currentMonth['alert_level'] ?? 'none') !== 'none' && ($currentMonth['alert_level'] ?? 'none') !== 'success') {
                    $this->logger->warning(sprintf(
                        '[ERP Cron] ALERT: Sales projection alert level: %s',
                        $currentMonth['alert_level']
                    ));
                }
            }

            // Get next month projection
            $nextMonth = $this->salesProjection->getNextMonthProjection();

            if (!empty($nextMonth)) {
                $this->logger->info(sprintf(
                    '[ERP Cron] Next month (%s) projection: R$ %s (Range: R$ %s - R$ %s)',
                    $nextMonth['month_name'] ?? '',
                    number_format($nextMonth['projection'] ?? 0, 2, ',', '.'),
                    number_format($nextMonth['range_min'] ?? 0, 2, ',', '.'),
                    number_format($nextMonth['range_max'] ?? 0, 2, ',', '.')
                ));
            }

            $this->logger->info('[ERP Cron] Forecast update completed.');
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Error updating forecasts: ' . $e->getMessage());
        }
    }
}
