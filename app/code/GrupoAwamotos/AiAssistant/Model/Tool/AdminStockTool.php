<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\AdminDashboardInterface;

/**
 * Tool: consulta estoque de produto por SKU (Admin only, somente leitura).
 */
class AdminStockTool implements ToolInterface
{
    public function __construct(
        private readonly AdminDashboardInterface $adminDashboard
    ) {
    }

    public function getName(): string
    {
        return 'admin_stock_check';
    }

    public function getDescription(): string
    {
        return 'Verifica o estoque atual de um produto pelo SKU. Somente leitura.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sku' => [
                    'type'        => 'string',
                    'description' => 'SKU do produto a consultar.',
                ],
            ],
            'required' => ['sku'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $sku = trim((string) ($arguments['sku'] ?? ''));
        if ($sku === '') {
            return ['error' => 'SKU não informado.'];
        }
        return $this->adminDashboard->stockCheck($sku);
    }

    public function getAllowedChannels(): array
    {
        return [AssistantOrchestratorInterface::CHANNEL_ADMIN];
    }
}
