<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Source;

use GrupoAwamotos\B2B\Model\B2bCustomer;
use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => B2bCustomer::STATUS_PENDING,  'label' => __('Pendente')],
            ['value' => B2bCustomer::STATUS_APPROVED, 'label' => __('Aprovado')],
            ['value' => B2bCustomer::STATUS_REJECTED, 'label' => __('Rejeitado')],
        ];
    }
}
