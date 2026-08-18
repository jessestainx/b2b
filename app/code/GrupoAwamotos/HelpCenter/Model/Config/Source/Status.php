<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('Ativo')],
            ['value' => 0, 'label' => __('Inativo')],
        ];
    }
}
