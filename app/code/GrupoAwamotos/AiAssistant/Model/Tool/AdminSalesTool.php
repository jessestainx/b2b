<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\AdminDashboardInterface;

/**
 * Tool: resumo de vendas do dia e clientes novos (Admin only, somente leitura).
 */
class AdminSalesTool implements ToolInterface
{
    public function __construct(
        private readonly AdminDashboardInterface $adminDashboard
    ) {
    }

    public function getName(): string
    {
        return 'admin_sales_summary';
    }

    public function getDescription(): string
    {
        return 'Retorna o resumo de vendas de hoje (total, número de pedidos, ticket médio) e contagem de novos clientes. Somente leitura.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [],
            'required'   => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $sales    = $this->adminDashboard->salesToday();
        $customers = $this->adminDashboard->newCustomers();
        return ['sales_today' => $sales, 'new_customers' => $customers];
    }

    public function getAllowedChannels(): array
    {
        return [AssistantOrchestratorInterface::CHANNEL_ADMIN];
    }
}
