<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class TipoOptions implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'churn', 'label' => __('Churn (Reativacao)')],
            ['value' => 'crosssell', 'label' => __('Cross-sell')],
        ];
    }
}
