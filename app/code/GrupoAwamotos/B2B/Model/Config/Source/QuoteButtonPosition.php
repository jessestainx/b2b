<?php

/**
 * Quote Button Position Source Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class QuoteButtonPosition implements OptionSourceInterface
{
    public const POSITION_NONE = 'none';
    public const POSITION_PRODUCT = 'product';
    public const POSITION_CART = 'cart';
    public const POSITION_BOTH = 'both';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::POSITION_NONE, 'label' => __('Não exibir')],
            ['value' => self::POSITION_PRODUCT, 'label' => __('Página do Produto')],
            ['value' => self::POSITION_CART, 'label' => __('Carrinho')],
            ['value' => self::POSITION_BOTH, 'label' => __('Produto e Carrinho')],
        ];
    }
}
