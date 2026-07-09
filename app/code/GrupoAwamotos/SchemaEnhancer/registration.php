<?php

/**
 * GrupoAwamotos_SchemaEnhancer
 *
 * Adiciona schemas JSON-LD extras que faltam no PDP:
 * - Organization (com hasMerchantReturnPolicy)
 * - LocalBusiness
 * - HowTo
 * - FAQPage (ja tem)
 * - BreadcrumbList (ja tem)
 */

declare(strict_types=1);

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'GrupoAwamotos_SchemaEnhancer',
    __DIR__
);
