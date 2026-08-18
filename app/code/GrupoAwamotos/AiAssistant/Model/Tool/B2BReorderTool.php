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
        return 'Lista pedidos B2B reordenáveis. Criar o carrinho exige confirmação '
            . 'na interface; texto do cliente não autoriza a escrita.';
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
        if (empty($context['is_b2b'])) {
            return ['error' => 'Disponível apenas para clientes B2B aprovados.'];
        }

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
            if ($orderId === '' || mb_strlen($orderId) > 32) {
                return ['error' => 'Informe o número do pedido para repetir.'];
            }

            if (empty($context['write_confirmed'])) {
                return [
                    'deferred_write' => true,
                    'action'         => 'reorder',
                    'payload'        => ['order_id' => $orderId],
                    'summary'        => 'Repetir o pedido ' . $orderId . ' e criar um carrinho.',
                ];
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
