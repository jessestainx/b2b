<?php

/**
 * GrupoAwamotos LayoutFix
 * Fixes layout reference issues in admin
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_LayoutFix',
    __DIR__
);
