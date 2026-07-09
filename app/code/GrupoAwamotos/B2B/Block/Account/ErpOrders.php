<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Model\Account\ErpCustomerContext;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class ErpOrders extends Template
{
    private ?array $orderDetail = null;

    public function __construct(
        Context $context,
        private readonly ErpCustomerContext $erpCustomerContext,
        private readonly PurchaseHistory $purchaseHistory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isAvailable(): bool
    {
        return $this->erpCustomerContext->isEnabled()
            && $this->erpCustomerContext->isLoggedIn()
            && $this->erpCustomerContext->getErpCustomerCode() !== null;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total_count: int}
     */
    public function getOrdersPage(int $page = 1, int $pageSize = 20): array
    {
        $customerCode = $this->erpCustomerContext->getErpCustomerCode();
        if (!$customerCode) {
            return ['items' => [], 'total_count' => 0];
        }

        return $this->purchaseHistory->getPaginatedOrders($customerCode, $page, $pageSize);
    }

    public function getCurrentPage(): int
    {
        return max(1, (int) $this->getRequest()->getParam('p'));
    }

    public function getPageSize(): int
    {
        return 20;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderDetail(): ?array
    {
        if ($this->orderDetail !== null) {
            return $this->orderDetail;
        }

        $orderId = (int) $this->getRequest()->getParam('id');
        $customerCode = $this->erpCustomerContext->getErpCustomerCode();
        if ($orderId <= 0 || !$customerCode) {
            $this->orderDetail = null;
            return null;
        }

        $this->orderDetail = $this->purchaseHistory->getOrderDetail($customerCode, $orderId);

        return $this->orderDetail;
    }

    public function getListUrl(): string
    {
        return $this->getUrl('b2b/erporders/index');
    }

    public function getViewUrl(int $erpOrderId): string
    {
        return $this->getUrl('b2b/erporders/view', ['id' => $erpOrderId]);
    }

    public function formatPrice(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    public function formatErpDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '-';
        }

        try {
            return (new \DateTime($date))->format('d/m/Y');
        } catch (\Exception) {
            return substr($date, 0, 10);
        }
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'F' => (string) __('Faturado'),
            'E' => (string) __('Entregue'),
            'V' => (string) __('Em Separação'),
            'S' => (string) __('Saiu p/ Entrega'),
            'W' => (string) __('Aguardando'),
            'A' => (string) __('Aberto'),
            'P' => (string) __('Pendente'),
            'L' => (string) __('Liberado'),
            'B' => (string) __('Bloqueado'),
            'C' => (string) __('Cancelado'),
            'X' => (string) __('Excluído'),
            default => $status,
        };
    }

    public function getStatusClass(string $status): string
    {
        return match ($status) {
            'F', 'E' => 'status-success',
            'V', 'S', 'L' => 'status-processing',
            'C', 'X' => 'status-failed',
            default => 'status-pending',
        };
    }
}
