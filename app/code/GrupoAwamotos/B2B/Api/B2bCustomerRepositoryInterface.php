<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Api;

use GrupoAwamotos\B2B\Model\B2bCustomer;

interface B2bCustomerRepositoryInterface
{
    public function save(B2bCustomer $b2bCustomer): B2bCustomer;
    public function getById(int $id): B2bCustomer;
    public function getByCustomerId(int $customerId): ?B2bCustomer;
    public function delete(B2bCustomer $b2bCustomer): void;
}
