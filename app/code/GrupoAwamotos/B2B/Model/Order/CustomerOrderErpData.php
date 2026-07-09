<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Order;

use GrupoAwamotos\ERPIntegration\Api\OrderSyncInterface;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class CustomerOrderErpData
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SyncLogResource $syncLogResource,
        private readonly OrderSyncInterface $orderSync,
        private readonly TrackingUrlResolver $trackingUrlResolver,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getCustomerOrder(int $orderId, int $customerId): OrderInterface
    {
        $order = $this->orderRepository->get($orderId);

        if ((int) $order->getCustomerId() !== $customerId) {
            throw new NoSuchEntityException(__('Pedido não encontrado.'));
        }

        return $order;
    }

    public function getErpOrderId(int $magentoOrderId): ?int
    {
        $erpCode = $this->syncLogResource->getErpCodeByMagentoId('order', $magentoOrderId);

        return $erpCode !== null && $erpCode !== '' ? (int) $erpCode : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInvoiceSummary(int $magentoOrderId): ?array
    {
        $erpOrderId = $this->getErpOrderId($magentoOrderId);
        if ($erpOrderId === null) {
            return null;
        }

        $invoice = $this->orderSync->getOrderInvoiceData($erpOrderId);
        if ($invoice === null) {
            return null;
        }

        return [
            'numero' => (string) ($invoice['numero'] ?? ''),
            'serie' => (string) ($invoice['serie'] ?? ''),
            'chave' => (string) ($invoice['chave'] ?? ''),
            'data_emissao' => $this->formatDate($invoice['data_emissao'] ?? null),
            'url_danfe' => (string) ($invoice['url_danfe'] ?? ''),
            'emitente' => (string) ($invoice['emitente']['razao_social'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOperationsViewModel(int $magentoOrderId, OrderInterface $order): array
    {
        $erpOrderId = $this->getErpOrderId($magentoOrderId);
        $invoice = $erpOrderId !== null ? $this->orderSync->getOrderInvoiceData($erpOrderId) : null;
        $erpStatus = $erpOrderId !== null ? $this->orderSync->getErpOrderStatus($erpOrderId) : null;

        $trackingCode = trim((string) ($erpStatus['CODRASTREIO'] ?? ''));
        $carrierName = trim((string) ($erpStatus['TRANSPORTADORA_NOME'] ?? ''));
        $trackingLink = $this->trackingUrlResolver->resolve($trackingCode, $carrierName);

        return [
            'has_erp_mapping' => $erpOrderId !== null,
            'invoice' => $invoice !== null ? [
                'numero' => (string) ($invoice['numero'] ?? ''),
                'serie' => (string) ($invoice['serie'] ?? ''),
                'chave' => (string) ($invoice['chave'] ?? ''),
                'data_emissao' => $this->formatDate($invoice['data_emissao'] ?? null),
                'url_danfe' => (string) ($invoice['url_danfe'] ?? ''),
            ] : null,
            'tracking' => [
                'code' => $trackingCode,
                'carrier' => $trackingLink['carrier_label'],
                'url' => $trackingLink['url'],
                'estimated_delivery' => $this->formatDate($erpStatus['DTENTREGA'] ?? null),
                'shipped_at' => $this->formatDate($erpStatus['DTSAIDA'] ?? null),
                'invoiced_at' => $this->formatDate($erpStatus['DTFATURAMENTO'] ?? null),
            ],
            'timeline' => $this->buildTimeline($order, $erpStatus, $invoice),
        ];
    }

    /**
     * @param array<string, mixed>|null $erpStatus
     * @param array<string, mixed>|null $invoice
     * @return list<array{key: string, label: string, state: string, date: string}>
     */
    private function buildTimeline(OrderInterface $order, ?array $erpStatus, ?array $invoice): array
    {
        $invoicedAt = $this->formatDate($erpStatus['DTFATURAMENTO'] ?? ($invoice['data_emissao'] ?? null));
        $shippedAt = $this->formatDate($erpStatus['DTSAIDA'] ?? null);
        $deliveredAt = $this->formatDate($erpStatus['DTENTREGA'] ?? null);
        $hasTracking = trim((string) ($erpStatus['CODRASTREIO'] ?? '')) !== '';
        $hasInvoice = $invoice !== null || !empty($erpStatus['NFNUMERO']);

        $steps = [
            [
                'key' => 'confirmed',
                'label' => (string) __('Pedido confirmado'),
                'state' => 'done',
                'date' => $this->formatDate($order->getCreatedAt()),
            ],
            [
                'key' => 'invoiced',
                'label' => (string) __('NF-e emitida'),
                'state' => $hasInvoice ? 'done' : 'pending',
                'date' => $hasInvoice ? $invoicedAt : '',
            ],
            [
                'key' => 'shipped',
                'label' => (string) __('Enviado'),
                'state' => ($hasTracking || $shippedAt !== '') ? 'done' : 'pending',
                'date' => $shippedAt,
            ],
            [
                'key' => 'delivered',
                'label' => (string) __('Entregue'),
                'state' => $deliveredAt !== '' ? 'done' : 'pending',
                'date' => $deliveredAt,
            ],
        ];

        foreach ($steps as $index => $step) {
            if ($step['state'] === 'done' && $index < count($steps) - 1 && $steps[$index + 1]['state'] === 'pending') {
                $steps[$index + 1]['state'] = 'current';
                break;
            }
        }

        return $steps;
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            return $this->timezone->formatDate(
                (string) $value,
                \IntlDateFormatter::MEDIUM,
                false
            );
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * @param array<string, mixed> $invoice
     */
    public function buildInvoiceXml(array $invoice): string
    {
        $chave = htmlspecialchars((string) ($invoice['chave'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $numero = htmlspecialchars((string) ($invoice['numero'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $serie = htmlspecialchars((string) ($invoice['serie'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $dataEmissao = htmlspecialchars((string) ($invoice['data_emissao'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $emitente = htmlspecialchars((string) ($invoice['emitente']['razao_social'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $danfe = htmlspecialchars((string) ($invoice['url_danfe'] ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<resumoNFe xmlns="http://www.grupoawamotos.com.br/nfe/resumo" versao="1.0">
    <chaveAcesso>{$chave}</chaveAcesso>
    <numero>{$numero}</numero>
    <serie>{$serie}</serie>
    <dataEmissao>{$dataEmissao}</dataEmissao>
    <emitente>{$emitente}</emitente>
    <consultaDanfe>{$danfe}</consultaDanfe>
</resumoNFe>
XML;
    }
}
