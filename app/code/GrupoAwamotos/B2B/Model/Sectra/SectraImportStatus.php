<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

/**
 * Sectra order import gate status stored on sales_order.sectra_import_status.
 */
final class SectraImportStatus
{
    public const NOT_APPLICABLE = 'not_applicable';
    public const AWAITING_CUSTOMER_VALIDATION = 'awaiting_customer_validation';
    public const ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED = 'order_blocked_customer_not_validated';
    public const ORDER_BLOCKED_PRODUCT_NOT_REGISTERED = 'order_blocked_product_not_registered';
    public const ORDER_CANCELLED_BEFORE_ERP_IMPORT = 'order_cancelled_before_erp_import';
    public const READY_FOR_IMPORT = 'ready_for_import';
    public const IMPORTED = 'imported';
    public const IMPORT_FAILED = 'import_failed';

    /** Statuses that must never enter oc_order or ERP pull. */
    public const NON_IMPORTABLE = [
        self::AWAITING_CUSTOMER_VALIDATION,
        self::ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED,
        self::ORDER_BLOCKED_PRODUCT_NOT_REGISTERED,
        self::ORDER_CANCELLED_BEFORE_ERP_IMPORT,
        self::IMPORT_FAILED,
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::NOT_APPLICABLE => 'N/A',
            self::AWAITING_CUSTOMER_VALIDATION => 'Aguardando validação ERP',
            self::ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED => 'Bloqueado — cliente não validado no ERP',
            self::ORDER_BLOCKED_PRODUCT_NOT_REGISTERED => 'Bloqueado — produto não cadastrado no B2B do Sectra',
            self::ORDER_CANCELLED_BEFORE_ERP_IMPORT => 'Cancelado antes da importação ERP',
            self::READY_FOR_IMPORT => 'Liberado para importação Sectra',
            self::IMPORTED => 'Importado no ERP',
            self::IMPORT_FAILED => 'Erro na importação ERP',
        ];
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }
}
