<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\CatalogInterface;

/**
 * Tool: retorna detalhes de um produto pelo SKU.
 */
class ProductDetailTool implements ToolInterface
{
    public function __construct(
        private readonly CatalogInterface $catalog
    ) {
    }

    public function getName(): string
    {
        return 'product_detail';
    }

    public function getDescription(): string
    {
        return 'Retorna os detalhes completos de um produto da AWA Motos: preço, estoque, descrição e URL. '
            . 'Use quando souber o SKU exato do produto.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sku' => [
                    'type'        => 'string',
                    'description' => 'SKU exato do produto.',
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
        return $this->catalog->getProduct($sku);
    }

    public function getAllowedChannels(): array
    {
        return [
            AssistantOrchestratorInterface::CHANNEL_STOREFRONT,
            AssistantOrchestratorInterface::CHANNEL_B2B,
            AssistantOrchestratorInterface::CHANNEL_ADMIN,
        ];
    }
}
