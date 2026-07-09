<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\RexisML\Model\DataPipeline\ErpDataCollector;
use GrupoAwamotos\RexisML\Model\DataPipeline\RfmCalculator;
use GrupoAwamotos\RexisML\Model\DataPipeline\ChurnDetector;
use GrupoAwamotos\RexisML\Model\DataPipeline\CrossSellGenerator;
use GrupoAwamotos\RexisML\Model\DataPipeline\CrossSellRecommender;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Sync extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'GrupoAwamotos_RexisML::dashboard';

    private JsonFactory $jsonFactory;
    private ErpDataCollector $collector;
    private RfmCalculator $rfm;
    private ChurnDetector $churn;
    private CrossSellGenerator $crossSell;
    private CrossSellRecommender $crossSellRecommender;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ErpDataCollector $collector,
        RfmCalculator $rfm,
        ChurnDetector $churn,
        CrossSellGenerator $crossSell,
        CrossSellRecommender $crossSellRecommender
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->collector = $collector;
        $this->rfm = $rfm;
        $this->churn = $churn;
        $this->crossSell = $crossSell;
        $this->crossSellRecommender = $crossSellRecommender;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $startTime = microtime(true);

        try {
            // Step 1: Collect ERP data
            $erpData = $this->collector->collect();
            $orders = $erpData['orders'] ?? [];
            $products = $erpData['products'] ?? [];

            // Step 2: RFM
            $rfmCount = $this->rfm->calculate($orders);

            // Step 3: Churn detection
            $churnCount = $this->churn->detect($orders, $products);

            // Step 4: Cross-sell rules (MBA)
            $rulesCount = $this->crossSell->generate($orders);

            // Step 5: Cross-sell recommendations
            $xsCount = $this->crossSellRecommender->recommend($orders, $products);

            $elapsed = round(microtime(true) - $startTime, 1);

            return $result->setData([
                'success' => true,
                'message' => "Sync concluido em {$elapsed}s",
                'stats' => [
                    'rfm' => $rfmCount,
                    'churn' => $churnCount,
                    'rules' => $rulesCount,
                    'crosssell' => $xsCount,
                    'elapsed' => $elapsed
                ]
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Erro no sync: ' . $e->getMessage()
            ]);
        }
    }
}
