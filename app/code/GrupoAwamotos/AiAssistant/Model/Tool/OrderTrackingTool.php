<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Tool: rastreia pedidos do cliente logado ou por número de pedido.
 *
 * Para clientes logados, busca pelos últimos pedidos (customer_id do contexto).
 * Para visitantes, retorna orientação para verificar o e-mail de confirmação.
 */
class OrderTrackingTool implements ToolInterface
{
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
        return 'Consulta os pedidos do cliente logado, ou busca um pedido específico pelo número. '
            . 'Retorna status, itens e data estimada de entrega.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'order_number' => [
                    'type'        => 'string',
                    'description' => 'Número do pedido (ex: 100012345). Opcional para clientes logados.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        $customerId  = isset($context['customer_id']) ? (int) $context['customer_id'] : null;

        if ($orderNumber !== '') {
            return $this->getByIncrementId($orderNumber, $customerId);
        }

        if ($customerId !== null && $customerId > 0) {
            return $this->getCustomerOrders($customerId);
        }

        return [
            'message' => 'Por favor informe o número do pedido. Você encontra esse número no e-mail de confirmação que recebeu ao finalizar a compra.',
        ];
    }

    private function getByIncrementId(string $incrementId, ?int $customerId): array
    {
        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->create();

            $result = $this->orderRepository->getList($criteria);
            $orders = $result->getItems();

            if (empty($orders)) {
                return ['error' => 'Pedido ' . $incrementId . ' não encontrado.'];
            }

            $order = reset($orders);

            if ($customerId !== null && (int) $order->getCustomerId() !== $customerId) {
                return ['error' => 'Pedido não encontrado para este cliente.'];
            }

            return $this->formatOrder($order);
        } catch (\Exception $e) {
            return ['error' => 'Erro ao consultar pedido: ' . $e->getMessage()];
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

            return ['orders' => $formatted, 'total' => $result->getTotalCount()];
        } catch (\Exception $e) {
            return ['error' => 'Erro ao consultar pedidos: ' . $e->getMessage()];
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
