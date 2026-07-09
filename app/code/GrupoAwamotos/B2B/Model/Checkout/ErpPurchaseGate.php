<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Checkout;

use GrupoAwamotos\B2B\Model\Sectra\CheckoutBlockMessage;

/**
 * ERP purchase gate — desativado; mantido para compatibilidade com blocos/plugins legados.
 */
class ErpPurchaseGate
{
    public function isBlockedForCurrentCustomer(): bool
    {
        return false;
    }

    public function getBlockMessage(): string
    {
        return CheckoutBlockMessage::MESSAGE;
    }
}
