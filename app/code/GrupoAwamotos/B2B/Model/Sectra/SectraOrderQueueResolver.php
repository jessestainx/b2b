<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

/**
 * Resolve fila operacional Sectra (oc_order) e motivos de bloqueio por pedido.
 */
class SectraOrderQueueResolver
{
    public const BUCKET_READY = 'ready';
    public const BUCKET_BLOCKED = 'blocked';
    public const BUCKET_AWAITING = 'awaiting';
    public const BUCKET_IMPORTED = 'imported';
    public const BUCKET_CLOSED = 'closed';

    /** @var list<string> */
    private const ACTIVE_STATES = ['new', 'pending_payment', 'processing'];

    /**
     * @return array<string, string>
     */
    public static function bucketLabels(): array
    {
        return [
            self::BUCKET_READY => (string) __('Pronto — visível em oc_order'),
            self::BUCKET_BLOCKED => (string) __('Bloqueado'),
            self::BUCKET_AWAITING => (string) __('Aguardando validação ERP'),
            self::BUCKET_IMPORTED => (string) __('Já importado'),
            self::BUCKET_CLOSED => (string) __('Encerrado / cancelado'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{bucket: string, bucket_label: string, block_reason: string, next_action: string, in_oc_order: bool}
     */
    public function resolve(array $row): array
    {
        $isImported = (int) ($row['is_imported'] ?? 0) === 1
            || ($row['sectra_import_status'] ?? '') === SectraImportStatus::IMPORTED;
        $inOcOrder = (int) ($row['in_oc_order'] ?? 0) === 1;
        $state = (string) ($row['state'] ?? '');
        $importStatus = (string) ($row['sectra_import_status'] ?? '');

        if ($isImported) {
            return $this->result(
                self::BUCKET_IMPORTED,
                (string) __('Pedido já importado no Sectra'),
                (string) __('Nenhuma — acompanhar status no ERP'),
                false
            );
        }

        if (!in_array($state, self::ACTIVE_STATES, true)) {
            return $this->result(
                self::BUCKET_CLOSED,
                (string) __('Pedido não está ativo (state: %1)', $state),
                (string) __('Nenhuma'),
                false
            );
        }

        if ($importStatus === SectraImportStatus::ORDER_CANCELLED_BEFORE_ERP_IMPORT) {
            return $this->result(
                self::BUCKET_CLOSED,
                (string) __('Cancelado antes da importação ERP'),
                (string) __('Nenhuma'),
                false
            );
        }

        if ($importStatus === SectraImportStatus::ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED) {
            return $this->result(
                self::BUCKET_BLOCKED,
                (string) __('Cliente bloqueado — não validado no ERP'),
                (string) __('Importar Clientes Prospect no Sectra'),
                false
            );
        }

        if ($importStatus === SectraImportStatus::ORDER_BLOCKED_PRODUCT_NOT_REGISTERED) {
            return $this->result(
                self::BUCKET_BLOCKED,
                (string) __('Pedido bloqueado — contém produto sem cadastro OpenCardB2B no Sectra'),
                (string) __('Sincronizar cadastro de produto no Sectra e aguardar cron liberar'),
                false
            );
        }

        if ($importStatus === SectraImportStatus::AWAITING_CUSTOMER_VALIDATION) {
            return $this->result(
                self::BUCKET_AWAITING,
                (string) __('Status antigo: aguardando validação ERP do cliente'),
                (string) __('Aguardar cron liberar para Importar Pedidos'),
                false
            );
        }

        $cnpj = $this->normalizeDigits((string) ($row['b2b_cnpj'] ?? ''));
        if ($cnpj === '') {
            return $this->result(
                self::BUCKET_BLOCKED,
                (string) __('Cliente sem CNPJ cadastrado'),
                (string) __('Completar cadastro B2B no Magento'),
                false
            );
        }

        if ((int) ($row['erp_code_account_count'] ?? 0) > 1 && !$inOcOrder) {
            return $this->result(
                self::BUCKET_BLOCKED,
                (string) __(
                    'erp_code %1 duplicado em %2 contas Magento',
                    (string) ($row['erp_code'] ?? ''),
                    (string) ($row['erp_code_account_count'] ?? '')
                ),
                (string) __('Unificar cadastro — uma CHAVE Sectra por CNPJ'),
                false
            );
        }

        if ($inOcOrder) {
            $sectraChave = (int) ($row['sectra_chave'] ?? 0);
            $ocOrderCustomerId = (int) ($row['oc_order_customer_id'] ?? 0);
            if ($sectraChave > 0 && $ocOrderCustomerId > 0 && $sectraChave !== $ocOrderCustomerId) {
                return $this->result(
                    self::BUCKET_BLOCKED,
                    (string) __(
                        'oc_order.customer_id (%1) ≠ CHAVE Sectra (%2)',
                        (string) $ocOrderCustomerId,
                        (string) $sectraChave
                    ),
                    (string) __('Executar bridge / aguardar cron (5 min)'),
                    false
                );
            }

            $nextAction = (string) __('Sectra → Importar Pedidos (CHAVE %1)', (string) ($row['sectra_chave'] ?? ''));
            if ((int) ($row['erp_code_account_count'] ?? 0) > 1) {
                $nextAction .= ' — ' . (string) __('revisar erp_code duplicado no Magento');
            }

            return $this->result(
                self::BUCKET_READY,
                '',
                $nextAction,
                true
            );
        }

        return $this->result(
            self::BUCKET_BLOCKED,
            $this->resolveOcOrderGapReason($row),
            (string) __('Verificar bridge oc_customer / oc_order'),
            false
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveOcOrderGapReason(array $row): string
    {
        if (in_array((string) ($row['sectra_import_status'] ?? ''), SectraImportStatus::NON_IMPORTABLE, true)) {
            $label = SectraImportStatus::label((string) $row['sectra_import_status']);

            return (string) __('Status Magento impede exposição em oc_order: %1', $label);
        }

        if ((int) ($row['has_oc_customer'] ?? 0) !== 1) {
            return (string) __('Cliente ausente na tabela oc_customer (bridge)');
        }

        if ((int) ($row['oc_customer_group_id'] ?? 0) < 1) {
            return (string) __('oc_customer.customer_group_id vazio — lista de preço (FATORPRECO) não resolvida no ERP');
        }

        $ocCnpj = $this->normalizeDigits((string) ($row['oc_customer_cnpj'] ?? ''));
        if ($ocCnpj === '') {
            return (string) __('CNPJ vazio no custom_field do oc_customer');
        }

        return (string) __('Cliente validado, mas pedido ainda não aparece em oc_order — aguardar cron bridge (5 min)');
    }

    /**
     * @return array{ready: int, blocked: int, awaiting: int, imported: int, closed: int, total_pending: int}
     */
    public function summarizeRows(array $rows): array
    {
        $counts = [
            self::BUCKET_READY => 0,
            self::BUCKET_BLOCKED => 0,
            self::BUCKET_AWAITING => 0,
            self::BUCKET_IMPORTED => 0,
            self::BUCKET_CLOSED => 0,
        ];

        foreach ($rows as $row) {
            $resolved = $this->resolve($row);
            $counts[$resolved['bucket']]++;
        }

        return [
            'ready' => $counts[self::BUCKET_READY],
            'blocked' => $counts[self::BUCKET_BLOCKED],
            'awaiting' => $counts[self::BUCKET_AWAITING],
            'imported' => $counts[self::BUCKET_IMPORTED],
            'closed' => $counts[self::BUCKET_CLOSED],
            'total_pending' => $counts[self::BUCKET_READY]
                + $counts[self::BUCKET_BLOCKED]
                + $counts[self::BUCKET_AWAITING],
        ];
    }

    /**
     * @return array{bucket: string, bucket_label: string, block_reason: string, next_action: string, in_oc_order: bool}
     */
    private function result(string $bucket, string $blockReason, string $nextAction, bool $inOcOrder): array
    {
        $labels = self::bucketLabels();

        return [
            'bucket' => $bucket,
            'bucket_label' => $labels[$bucket] ?? $bucket,
            'block_reason' => $blockReason,
            'next_action' => $nextAction,
            'in_oc_order' => $inOcOrder,
        ];
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
