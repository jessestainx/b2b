<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Model\ResourceModel\Subscription\CollectionFactory;
use GrupoAwamotos\B2B\Model\SubscriptionService;
use Psr\Log\LoggerInterface;

class ProcessSubscriptions
{
    private $collectionFactory;
    private $subscriptionService;
    private $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        SubscriptionService $subscriptionService,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->subscriptionService = $subscriptionService;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('B2B: Starting subscription processing cron...');

        $collection = $this->collectionFactory->create();
        $collection->filterDue();

        foreach ($collection as $subscription) {
            try {
                $this->subscriptionService->processRun($subscription);
            } catch (\Exception $e) {
                $this->logger->error("B2B Subscription Cron Error: #{$subscription->getId()} - " . $e->getMessage());
            }
        }

        $this->logger->info('B2B: Subscription processing cron finished.');
    }
}
