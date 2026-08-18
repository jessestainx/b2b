<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\AdminDashboardInterface;

/**
 * Tool: detalha um pedido pelo número de incremento (Admin only, somente leitura).
 */
class AdminOrderDetailTool implements ToolInterface
{
    public function __construct(
        private readonly AdminDashboardInterface $adminDashboard
    ) {
    }

    public function getName(): string
    {
        return 'admin_order_detail';
    }

    public function getDescription(): string
    {
        return 'Retorna os detalhes de um pedido (cliente, itens, status, pagamento, envio) pelo número do pedido. Somente leitura.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'order_number' => [
                    'type'        => 'string',
                    'description' => 'Número do pedido (ex: 100012345).',
                ],
            ],
            'required' => ['order_number'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $incrementId = trim((string) ($arguments['order_number'] ?? ''));
        if ($incrementId === '') {
            return ['error' => 'Número do pedido não informado.'];
        }
        return $this->adminDashboard->orderDetail($incrementId);
    }

    public function getAllowedChannels(): array
    {
        return [AssistantOrchestratorInterface::CHANNEL_ADMIN];
    }
}
