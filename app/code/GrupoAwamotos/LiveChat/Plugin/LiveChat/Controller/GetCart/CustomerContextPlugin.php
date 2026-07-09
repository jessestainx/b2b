<?php

declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Plugin\LiveChat\Controller\GetCart;

use GrupoAwamotos\LiveChat\Model\Chat\CustomerContextBuilder;
use LiveChat\LiveChat\Controller\GetCart\Index;

class CustomerContextPlugin
{
    private CustomerContextBuilder $customerContextBuilder;

    public function __construct(CustomerContextBuilder $customerContextBuilder)
    {
        $this->customerContextBuilder = $customerContextBuilder;
    }

    /**
     * Append customer context to LiveChat dynamic variables.
     *
     * @param array<int, array{name: string, value: scalar|null}> $result
     * @return array<int, array{name: string, value: scalar|null}>
     */
    public function afterGetCustomVariables(Index $subject, array $result): array
    {
        return array_merge($result, $this->customerContextBuilder->build());
    }
}
