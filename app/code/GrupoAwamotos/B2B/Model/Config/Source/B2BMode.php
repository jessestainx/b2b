<?php

/**
 * B2B Mode Source Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class B2BMode implements OptionSourceInterface
{
    public const MODE_STRICT = 'strict';
    public const MODE_MIXED = 'mixed';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::MODE_STRICT, 'label' => __('Somente B2B (obrigatório login)')],
            ['value' => self::MODE_MIXED, 'label' => __('Misto (B2B + B2C)')],
        ];
    }

    /**
     * Get options as key => value
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::MODE_STRICT => __('Somente B2B (obrigatório login)'),
            self::MODE_MIXED => __('Misto (B2B + B2C)'),
        ];
    }
}
