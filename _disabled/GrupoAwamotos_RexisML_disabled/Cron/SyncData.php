<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Cron;

use GrupoAwamotos\RexisML\Model\DataPipeline\ErpDataCollector;
use GrupoAwamotos\RexisML\Model\DataPipeline\RfmCalculator;
use GrupoAwamotos\RexisML\Model\DataPipeline\ChurnDetector;
use GrupoAwamotos\RexisML\Model\DataPipeline\CrossSellGenerator;
use GrupoAwamotos\RexisML\Model\DataPipeline\CrossSellRecommender;
use Psr\Log\LoggerInterface;

class SyncData
{
    private ErpDataCollector $collector;
    private RfmCalculator $rfm;
    private ChurnDetector $churn;
    private CrossSellGenerator $crossSell;
    private CrossSellRecommender $crossSellRecommender;
    private LoggerInterface $logger;

    public function __construct(
        ErpDataCollector $collector,
        RfmCalculator $rfm,
        ChurnDetector $churn,
        CrossSellGenerator $crossSell,
        CrossSellRecommender $crossSellRecommender,
        LoggerInterface $logger
    ) {
        $this->collector = $collector;
        $this->rfm = $rfm;
        $this->churn = $churn;
        $this->crossSell = $crossSell;
        $this->crossSellRecommender = $crossSellRecommender;
        $this->logger = $logger;
    }

    public function execute(): void
    {

        try {
            $orders = $this->collector->fetchOrders(24);
            if (empty($orders)) {
                $this->logger->warning('[RexisML Cron] No orders found, skipping');
                return;
            }

            $customers = $this->collector->fetchCustomers();
            $products = $this->collector->fetchProducts();

            $rfmCount = $this->rfm->calculate($orders, $customers);
            $churnCount = $this->churn->detect($orders, $products);
            $crossCount = $this->crossSell->generate($orders);
            $xsRecCount = $this->crossSellRecommender->recommend($orders, $products);

            $this->logger->info(sprintf(
                '[RexisML Cron] Sync complete: RFM=%d, Churn=%d, Rules=%d, CrossSell=%d',
                $rfmCount,
                $churnCount,
                $crossCount,
                $xsRecCount
            ));
        } catch (\Exception $e) {
            $this->logger->error('[RexisML Cron] Sync failed: ' . $e->getMessage());
        }
    }
}
