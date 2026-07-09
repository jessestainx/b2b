<?php

/**
 * Copyright © AWA Motos. All rights reserved.
 */

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Logger\Handler;

use Monolog\Logger;

/**
 * Raises the minimum log level of the debug handler to WARNING in production.
 * Prevents cron INFO/DEBUG messages from filling var/log/debug.log.
 * Monolog\Logger::WARNING = 300.
 */
class ProductionDebug extends \Magento\Framework\Logger\Handler\Debug
{
    /**
     * @var int Minimum Monolog level that this handler processes.
     */
    protected $loggerType = Logger::WARNING;
}
