<?php

declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service\LogAnalyzer;

interface AnalyzerInterface
{
    public function analyze(): array;

    public function getSpecificMetrics(): array;

    public function checkHealth(): array;

    public function generateAlerts(): array;
}
