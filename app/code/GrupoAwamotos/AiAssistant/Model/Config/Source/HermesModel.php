<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class HermesModel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'NousResearch/Hermes-3-Llama-3.1-70B',
                'label' => 'Hermes 3 Llama 3.1 70B (recomendado — custo/qualidade)',
            ],
            [
                'value' => 'NousResearch/Hermes-3-Llama-3.1-405B-FP8',
                'label' => 'Hermes 3 Llama 3.1 405B FP8 (melhor qualidade, mais caro)',
            ],
            [
                'value' => 'NousResearch/Hermes-2-Pro-Llama-3-8B',
                'label' => 'Hermes 2 Pro Llama 3 8B (mais rápido, menor custo)',
            ],
        ];
    }
}
