<?php

/**
 * GrupoAwamotos MaintenanceMode Module
 *
 * Modo de manutenção customizado com controle via admin
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'GrupoAwamotos_MaintenanceMode',
    __DIR__
);
