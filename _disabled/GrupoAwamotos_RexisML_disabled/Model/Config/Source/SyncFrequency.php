<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SyncFrequency implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'daily', 'label' => __('Diário')],
            ['value' => 'weekly', 'label' => __('Semanal')],
            ['value' => 'biweekly', 'label' => __('Quinzenal')],
            ['value' => 'monthly', 'label' => __('Mensal')],
        ];
    }
}
