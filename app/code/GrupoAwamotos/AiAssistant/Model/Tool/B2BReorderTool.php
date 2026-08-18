<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\B2BReorderInterface;

/**
 * Tool: lista pedidos anteriores reordenáveis para clientes B2B.
 *
 * A reposição efetiva (criar carrinho) requer confirmação explícita do usuário
 * antes de ser executada — o LLM deve sempre pedir confirmação ao cliente.
 */
class B2BReorderTool implements ToolInterface
{
    public function __construct(
        private readonly B2BReorderInterface $b2bReorder
    ) {
    }

    public function getName(): string
    {
        return 'b2b_reorder';
    }

    public function getDescription(): string
    {
        return 'Lista pedidos B2B anteriores disponíveis para reposição e, após confirmação explícita do cliente, inicia a reposição criando um carrinho com link de checkout. '
            . 'IMPORTANTE: sempre liste os pedidos primeiro e peça confirmação antes de criar o carrinho.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['list', 'reorder'],
                    'description' => '"list" para listar pedidos; "reorder" para criar carrinho após confirmação do cliente.',
                ],
                'order_id' => [
                    'type'        => 'string',
                    'description' => 'Número do pedido a repetir (somente para action=reorder).',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $phone = (string) ($context['customer_phone'] ?? '');
        if ($phone === '') {
            return ['error' => 'Telefone não disponível. Verifique seu cadastro B2B.'];
        }

        $action = (string) ($arguments['action'] ?? 'list');

        if ($action === 'list') {
            return $this->b2bReorder->getReorderableOrders($phone, 5);
        }

        if ($action === 'reorder') {
            $orderId = trim((string) ($arguments['order_id'] ?? ''));
            if ($orderId === '') {
                return ['error' => 'Informe o número do pedido para repetir.'];
            }
            return $this->b2bReorder->reorderByOrderId($phone, $orderId);
        }

        return ['error' => 'Ação inválida. Use "list" ou "reorder".'];
    }

    public function getAllowedChannels(): array
    {
        return [AssistantOrchestratorInterface::CHANNEL_B2B];
    }
}
