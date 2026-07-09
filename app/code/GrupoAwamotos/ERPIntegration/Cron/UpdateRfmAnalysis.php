<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

/**
 * Cron Job - Update RFM Analysis
 *
 * Runs daily to recalculate RFM scores and segmentation for all customers
 */
class UpdateRfmAnalysis
{
    private RfmCalculator $rfmCalculator;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        RfmCalculator $rfmCalculator,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->rfmCalculator = $rfmCalculator;
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
            // Clear cache and force recalculation
            $this->rfmCalculator->clearCache();
            $customers = $this->rfmCalculator->calculateForAllCustomers(24, true);

            $this->logger->info('[ERP Cron] RFM analysis completed. Processed ' . count($customers) . ' customers.');

            // Log segment distribution
            $segmentStats = $this->rfmCalculator->getSegmentStats();
            $distribution = [];
            foreach ($segmentStats as $key => $segment) {
                if ($segment['count'] > 0) {
                    $distribution[$segment['label']] = $segment['count'];
                }
            }

            $this->logger->info('[ERP Cron] Segment distribution: ' . json_encode($distribution));

            // Log at-risk customers count
            $atRisk = $this->rfmCalculator->getAtRiskCustomers(100);
            $this->logger->info('[ERP Cron] At-risk customers identified: ' . count($atRisk));
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Error updating RFM analysis: ' . $e->getMessage());
        }
    }
}
