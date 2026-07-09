<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

/**
 * Value object holding credit limit data for a customer.
 */
class CreditData
{
    public function __construct(private readonly float $creditLimit, private readonly float $availableCredit)
    {
    }

    public function getCreditLimit(): float
    {
        return $this->creditLimit;
    }

    public function getAvailableCredit(): float
    {
        return $this->availableCredit;
    }
}
