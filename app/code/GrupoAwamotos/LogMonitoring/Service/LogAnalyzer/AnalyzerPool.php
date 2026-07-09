<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service\LogAnalyzer;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class AnalyzerPool
{
    private array $analyzers = [];
    private LoggerInterface $logger;

    public function __construct(
        array $analyzers,
        LoggerInterface $logger
    ) {
        $this->analyzers = $analyzers;
        $this->logger = $logger;
    }

    public function getAnalyzer(string $type): AnalyzerInterface
    {
        if (!isset($this->analyzers[$type])) {
            throw new LocalizedException(__('Analyzer type "%1" not found', $type));
        }

        return $this->analyzers[$type];
    }

    public function getAllAnalyzers(): array
    {
        return $this->analyzers;
    }

    public function analyzeAll(): array
    {
        $results = [];

        foreach ($this->analyzers as $type => $analyzer) {
            try {
                $results[$type] = $analyzer->analyze();
            } catch (\Throwable $e) {
                $this->logger->error("Error in analyzer {$type}: " . $e->getMessage());
                $results[$type] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
