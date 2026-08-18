<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\B2BQuoteInterface;

/**
 * Tool: consulta e submete cotações B2B.
 *
 * Reusa GrupoAwamotos\WhatsAppCommerce\Api\B2BQuoteInterface sem modificá-la.
 * Exige que o contexto contenha 'customer_phone' (obtido do endereço de faturamento).
 */
class B2BQuoteTool implements ToolInterface
{
    public function __construct(
        private readonly B2BQuoteInterface $b2bQuote
    ) {
    }

    public function getName(): string
    {
        return 'b2b_quote';
    }

    public function getDescription(): string
    {
        return 'Gerencia cotações B2B: consulta cotações abertas ou cria uma nova solicitação de cotação. '
            . 'Disponível apenas para clientes B2B logados. '
            . 'Nunca aceita cotações automaticamente — apenas lista e cria solicitações.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['list', 'submit'],
                    'description' => '"list" para listar cotações abertas; "submit" para criar uma nova.',
                ],
                'items' => [
                    'type'        => 'array',
                    'description' => 'Itens para cotação (somente para action=submit). Array de {sku, qty}.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'sku' => ['type' => 'string'],
                            'qty' => ['type' => 'integer'],
                        ],
                        'required' => ['sku', 'qty'],
                    ],
                ],
                'message' => [
                    'type'        => 'string',
                    'description' => 'Mensagem opcional do cliente junto à cotação.',
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
            return $this->b2bQuote->getQuotes($phone);
        }

        if ($action === 'submit') {
            $items = (array) ($arguments['items'] ?? []);
            if (empty($items)) {
                return ['error' => 'Informe ao menos um item para a cotação.'];
            }
            $message = isset($arguments['message']) ? (string) $arguments['message'] : null;
            return $this->b2bQuote->submitQuote($phone, $items, $message);
        }

        return ['error' => 'Ação inválida. Use "list" ou "submit".'];
    }

    public function getAllowedChannels(): array
    {
        return [AssistantOrchestratorInterface::CHANNEL_B2B];
    }
}
