<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Source;

use GrupoAwamotos\B2B\Model\Cotacao;
use Magento\Framework\Data\OptionSourceInterface;

class CotacaoStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Cotacao::STATUS_PENDING,  'label' => __('Pendente')],
            ['value' => Cotacao::STATUS_QUOTED,    'label' => __('Cotado')],
            ['value' => Cotacao::STATUS_ACCEPTED,  'label' => __('Aceito')],
            ['value' => Cotacao::STATUS_REJECTED,  'label' => __('Recusado')],
            ['value' => Cotacao::STATUS_EXPIRED,   'label' => __('Expirado')],
        ];
    }
}
