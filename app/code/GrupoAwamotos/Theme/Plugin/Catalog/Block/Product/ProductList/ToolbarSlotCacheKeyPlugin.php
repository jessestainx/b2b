<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Catalog\Block\Product\ProductList;

use Magento\Catalog\Block\Product\ProductList\Toolbar;

/**
 * Include PLP toolbar slot in block_html cache key so top/bottom toolbars render unique IDs.
 */
class ToolbarSlotCacheKeyPlugin
{
    /**
     * @param Toolbar $subject
     * @param string[] $result
     * @return string[]
     */
    public function afterGetCacheKeyInfo(Toolbar $subject, array $result): array
    {
        $slot = (string) $subject->getData('awa_toolbar_slot');
        if ($slot !== '') {
            $result[] = 'awa_toolbar_slot_' . $slot;
        }

        return $result;
    }
}
