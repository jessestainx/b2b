<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tool: informa o conteúdo e total do carrinho do cliente logado.
 *
 * Adapta a funcionalidade do WhatsAppCommerce\Api\CartInterface para o contexto
 * web (sessão de checkout Magento, sem dependência de telefone).
 * Somente leitura — nunca adiciona ou remove itens diretamente.
 * O LLM deve instruir o cliente a usar o botão "Adicionar ao carrinho" no site.
 */
class CartInfoTool implements ToolInterface
{
    public function __construct(
        private readonly CheckoutSession       $checkoutSession,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getName(): string
    {
        return 'cart_info';
    }

    public function getDescription(): string
    {
        return 'Mostra os itens e total do carrinho atual do cliente. '
            . 'Use quando o cliente perguntar o que tem no carrinho ou qual o total. '
            . 'Não adiciona nem remove itens — oriente o cliente a usar o site para isso.';
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
        try {
            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId() || $quote->getItemsCount() === 0) {
                return [
                    'message'      => 'Seu carrinho está vazio.',
                    'items_count'  => 0,
                    'grand_total'  => 'R$ 0,00',
                    'checkout_url' => $this->storeManager->getStore()->getUrl('checkout/cart'),
                ];
            }

            $items = [];
            foreach ($quote->getAllVisibleItems() as $item) {
                $items[] = [
                    'name'  => $item->getName(),
                    'sku'   => $item->getSku(),
                    'qty'   => (int) $item->getQty(),
                    'price' => 'R$ ' . number_format((float) $item->getRowTotalInclTax(), 2, ',', '.'),
                ];
            }

            return [
                'items'        => $items,
                'items_count'  => (int) $quote->getItemsQty(),
                'grand_total'  => 'R$ ' . number_format((float) $quote->getGrandTotal(), 2, ',', '.'),
                'checkout_url' => $this->storeManager->getStore()->getUrl('checkout'),
                'cart_url'     => $this->storeManager->getStore()->getUrl('checkout/cart'),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Não foi possível acessar o carrinho: ' . $e->getMessage()];
        }
    }

    public function getAllowedChannels(): array
    {
        return [
            AssistantOrchestratorInterface::CHANNEL_STOREFRONT,
            AssistantOrchestratorInterface::CHANNEL_B2B,
        ];
    }
}
