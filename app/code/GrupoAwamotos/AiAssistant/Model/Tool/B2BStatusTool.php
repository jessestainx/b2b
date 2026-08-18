<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\B2BRegistrationInterface;

/**
 * Tool: consulta o status de cadastro B2B (aprovação CNPJ).
 *
 * Somente leitura — nunca aprova nem reprova o cadastro.
 */
class B2BStatusTool implements ToolInterface
{
    public function __construct(
        private readonly B2BRegistrationInterface $b2bRegistration
    ) {
    }

    public function getName(): string
    {
        return 'b2b_status';
    }

    public function getDescription(): string
    {
        return 'Consulta o status do cadastro B2B do cliente (pendente, aprovado, reprovado). '
            . 'Somente leitura — nunca aprova nem reprova. '
            . 'Pode receber o CNPJ ou telefone como identificador.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'identifier' => [
                    'type'        => 'string',
                    'description' => 'CNPJ (14 dígitos com ou sem formatação) ou telefone do cliente.',
                ],
            ],
            'required' => ['identifier'],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $identifier = trim((string) ($arguments['identifier'] ?? ''));

        if ($identifier === '') {
            $identifier = (string) ($context['customer_phone'] ?? '');
        }

        if ($identifier === '') {
            return ['error' => 'Informe o CNPJ ou telefone para consultar o status.'];
        }

        return $this->b2bRegistration->checkStatus($identifier);
    }

    public function getAllowedChannels(): array
    {
        return [
            AssistantOrchestratorInterface::CHANNEL_B2B,
            AssistantOrchestratorInterface::CHANNEL_ADMIN,
        ];
    }
}
