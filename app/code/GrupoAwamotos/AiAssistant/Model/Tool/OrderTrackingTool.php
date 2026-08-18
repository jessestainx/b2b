<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Rastreia pedidos somente do cliente autenticado.
 * Visitantes nunca recebem dados de pedido (anti-enumeração / IDOR).
 */
class OrderTrackingTool implements ToolInterface
{
    private const NOT_FOUND = 'Pedido não encontrado.';
    private const LOGIN_REQUIRED = 'Para consultar pedidos, entre na sua conta. '
        . 'O assistente não informa dados de pedido a visitantes.';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder    $searchCriteriaBuilder
    ) {
    }

    public function getName(): string
    {
        return 'order_tracking';
    }

    public function getDescription(): string
    {
        return 'Consulta os pedidos do cliente autenticado. '
            . 'Nunca use para visitantes. Não invente status, itens ou valores.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'order_number' => [
                    'type'        => 'string',
                    'description' => 'Número do pedido. Opcional para clientes logados (lista os mais recentes).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $customerId = isset($context['customer_id']) ? (int) $context['customer_id'] : 0;
        if ($customerId <= 0) {
            return ['message' => self::LOGIN_REQUIRED];
        }

        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        if ($orderNumber !== '') {
            return $this->getByIncrementId($orderNumber, $customerId);
        }

        return $this->getCustomerOrders($customerId);
    }

    private function getByIncrementId(string $incrementId, int $customerId): array
    {
        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->addFilter('customer_id', $customerId)
                ->create();

            $result = $this->orderRepository->getList($criteria);
            $orders = $result->getItems();

            if (empty($orders)) {
                return ['error' => self::NOT_FOUND];
            }

            $order = reset($orders);

            return $this->formatOrder($order);
        } catch (\Exception $e) {
            return ['error' => self::NOT_FOUND];
        }
    }

    private function getCustomerOrders(int $customerId): array
    {
        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('customer_id', $customerId)
                ->create();

            $criteria->setPageSize(5);
            $result = $this->orderRepository->getList($criteria);
            $orders = $result->getItems();

            if (empty($orders)) {
                return ['message' => 'Nenhum pedido encontrado para sua conta.'];
            }

            $formatted = [];
            foreach (array_slice(array_reverse($orders), 0, 5) as $order) {
                $formatted[] = $this->formatOrder($order);
            }

            return ['orders' => $formatted, 'total' => count($formatted)];
        } catch (\Exception $e) {
            return ['error' => self::NOT_FOUND];
        }
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return array<string, mixed>
     */
    private function formatOrder($order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $items[] = [
                'name' => $item->getName(),
                'sku'  => $item->getSku(),
                'qty'  => (int) $item->getQtyOrdered(),
            ];
        }

        return [
            'order_number'    => $order->getIncrementId(),
            'status'          => $order->getStatus(),
            'status_label'    => $order->getStatusLabel(),
            'created_at'      => $order->getCreatedAt(),
            'grand_total'     => 'R$ ' . number_format((float) $order->getGrandTotal(), 2, ',', '.'),
            'items'           => $items,
            'shipping_method' => $order->getShippingDescription(),
        ];
    }

    public function getAllowedChannels(): array
    {
        return [
            AssistantOrchestratorInterface::CHANNEL_STOREFRONT,
            AssistantOrchestratorInterface::CHANNEL_B2B,
        ];
    }
}
