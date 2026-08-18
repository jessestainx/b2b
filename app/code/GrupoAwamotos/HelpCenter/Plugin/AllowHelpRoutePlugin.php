<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Plugin;

use GrupoAwamotos\B2B\CommercialPanel\Model\CockpitAccessGuard;
use Magento\Framework\App\RequestInterface;

/**
 * Libera a rota awa_commercial/commercialhelp/* no CockpitAccessGuard
 * sem alterar o módulo B2B.
 */
class AllowHelpRoutePlugin
{
    public function afterIsRequestAllowed(
        CockpitAccessGuard $subject,
        bool $result,
        RequestInterface $request
    ): bool {
        if ($result) {
            return true;
        }
        $routeName = strtolower((string) $request->getRouteName());
        $controller = strtolower((string) $request->getControllerName());
        if ($routeName === 'awa_commercial' && $controller === 'commercialhelp') {
            return true;
        }
        return false;
    }
}
