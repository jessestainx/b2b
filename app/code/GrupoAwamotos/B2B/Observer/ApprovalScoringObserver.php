<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Api\ApprovalScoreServiceInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class ApprovalScoringObserver implements ObserverInterface
{
    /**
     * Guard against re-entrancy: processRegistration → persistScore → customerRepository::save
     * re-dispatches customer_save_after_data_object.
     */
    private static bool $isProcessing = false;

    private Config $config;
    private ApprovalScoreServiceInterface $approvalScoreService;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        ApprovalScoreServiceInterface $approvalScoreService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->approvalScoreService = $approvalScoreService;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        if (self::$isProcessing) {
            return;
        }

        if (!$this->config->isEnabled() || !$this->config->requireApproval()) {
            return;
        }

        if (!$this->config->isApprovalScoringEnabled()) {
            return;
        }

        try {
            // customer_save_after_data_object exposes customer_data_object (not "customer")
            $customer = $observer->getEvent()->getCustomerDataObject();
            if (!$customer instanceof CustomerInterface || !$customer->getId()) {
                return;
            }

            // Only B2B registrations with CNPJ need approval scoring
            $cnpjAttr = $customer->getCustomAttribute('b2b_cnpj');
            $cnpj = $cnpjAttr ? trim((string) $cnpjAttr->getValue()) : '';
            if ($cnpj === '') {
                return;
            }

            self::$isProcessing = true;
            try {
                $this->approvalScoreService->processRegistration((int) $customer->getId());
            } finally {
                self::$isProcessing = false;
            }
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->logger->error('B2B ApprovalScoringObserver error: ' . $e->getMessage());
        }
    }
}
