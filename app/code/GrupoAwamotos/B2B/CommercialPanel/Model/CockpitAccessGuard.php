<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use Magento\Framework\App\RequestInterface;

/**
 * Verifica rotas permitidas para usuários restritos ao cockpit comercial.
 */
class CockpitAccessGuard
{
    private const MODULE_AWA_COMMERCIAL = 'awa_commercial';

    /** @var string[] */
    private const ALLOWED_FULL_PATHS = [
        'admin/auth/logout',
        'admin/auth/deniedcookie',
        'admin/denied/index',
        'admin/noroute/index',
        'mui/index/render',
        'mui/bookmark/save',
        'mui/bookmark/delete',
        'mui/export/gridToCsv',
        'mui/export/gridToXml',
    ];

    /** @var string[] frontName das rotas permitidas (não confundir com getModuleName()) */
    private const ALLOWED_ROUTE_NAMES = [
        'awa_commercial',
    ];

    public function isRequestAllowed(RequestInterface $request): bool
    {
        $routeName = strtolower((string) $request->getRouteName());
        $controllerName = (string) $request->getControllerName();
        $actionName = (string) $request->getActionName();
        $fullPath = strtolower($routeName . '/' . $controllerName . '/' . $actionName);

        if (in_array($fullPath, self::ALLOWED_FULL_PATHS, true)) {
            return true;
        }

        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        return false;
    }
}
