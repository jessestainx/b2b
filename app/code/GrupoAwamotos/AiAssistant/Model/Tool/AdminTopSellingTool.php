<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\AdminDashboardInterface;

/**
 * Tool: lista os produtos mais vendidos num período (Admin only, somente leitura).
 */
class AdminTopSellingTool implements ToolInterface
{
    public function __construct(
        private readonly AdminDashboardInterface $adminDashboard
    ) {
    }

    public function getName(): string
    {
        return 'admin_top_selling';
    }

    public function getDescription(): string
    {
        return 'Lista os produtos mais vendidos nos últimos N dias. Somente leitura.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'days' => [
                    'type'        => 'integer',
                    'description' => 'Número de dias a considerar (padrão: 30).',
                    'default'     => 30,
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $days = (int) ($arguments['days'] ?? 30);
        return $this->adminDashboard->topSelling(max(1, min(365, $days)));
    }

    public function getAllowedChannels(): array
    {
        return [AssistantOrchestratorInterface::CHANNEL_ADMIN];
    }
}
