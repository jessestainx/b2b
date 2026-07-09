<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

/**
 * Structured event types for B2B → Sectra customer and order pipeline logging.
 */
final class ProspectEvent
{
    public const CUSTOMER_CREATED_MAGENTO = 'customer_created_magento';
    public const CUSTOMER_SENT_SECTRA = 'customer_sent_sectra';
    public const CUSTOMER_VALIDATOR_ACCEPTED = 'customer_validator_accepted';
    public const CUSTOMER_VALIDATION_PENDING = 'customer_validation_pending';
    public const ORDER_AWAITING_CUSTOMER = 'order_awaiting_customer';
    public const ORDER_RELEASED_FOR_IMPORT = 'order_released_for_import';
    public const ORDER_IMPORTED_SUCCESS = 'order_imported_success';
    public const ORDER_IMPORT_ERROR = 'order_import_error';
    public const ORDER_CANCELLED_BEFORE_ERP_IMPORT = 'order_cancelled_before_erp_import';
    public const ORDER_BLOCKED_CUSTOMER_NOT_VALIDATED = 'order_blocked_customer_not_validated';
    public const CHECKOUT_BLOCKED_CUSTOMER_NOT_VALIDATED = 'checkout_blocked_customer_not_validated';
    public const ORDER_NOT_CREATED_CUSTOMER_PENDING_ERP = 'order_not_created_customer_pending_erp';
    public const CUSTOMER_CONFIRMED_BY_ERP_POLL = 'customer_confirmed_by_erp_poll';
    public const CUSTOMER_VALIDATION_POLL_PENDING = 'customer_validation_poll_pending';
}
