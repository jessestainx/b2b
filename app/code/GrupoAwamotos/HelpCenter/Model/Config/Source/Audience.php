<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Model\Config\Source;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Audience implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => CategoryInterface::AUDIENCE_ALL,        'label' => __('Todos')],
            ['value' => CategoryInterface::AUDIENCE_CUSTOMER,   'label' => __('Clientes (loja)')],
            ['value' => CategoryInterface::AUDIENCE_B2B,        'label' => __('Clientes B2B')],
            ['value' => CategoryInterface::AUDIENCE_SELLER,     'label' => __('Vendedoras')],
            ['value' => CategoryInterface::AUDIENCE_SUPERVISOR, 'label' => __('Supervisoras')],
            ['value' => CategoryInterface::AUDIENCE_ADMIN,      'label' => __('Administradores')],
        ];
    }
}
