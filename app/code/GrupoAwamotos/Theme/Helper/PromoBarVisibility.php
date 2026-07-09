<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Controls where the global B2B promo bar should render (banner policy AWA).
 */
class PromoBarVisibility extends AbstractHelper
{
    /**
     * Routes where the header promo bar is redundant or hurts focus.
     *
     * @var list<string>
     */
    private const HIDDEN_FULL_ACTION_NAMES = [
        'catalogo_index_index',
        'catalogo_revista_index',
        'b2b_register_index',
        'cms_noroute_index',
        'curriculo_index_index',
        'curriculo_index_status',
    ];

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function shouldShowB2bPromoBar(?string $fullActionName = null): bool
    {
        $fullActionName = $fullActionName ?? (string) $this->_request->getFullActionName();

        return !in_array($fullActionName, self::HIDDEN_FULL_ACTION_NAMES, true);
    }
}
