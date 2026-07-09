<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\PageCache;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\PageCache\Model\App\FrontController\BuiltinPlugin;

/**
 * O FPC builtin não deve servir/gravar respostas do admin (evita HTML vazio com X-Magento-Cache-Debug: HIT).
 */
class DisableAdminBuiltinFpcPlugin
{
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    /**
     * @param callable(FrontControllerInterface, callable, RequestInterface): mixed $proceed
     * @param callable(RequestInterface): mixed $builtinProceed
     * @return mixed
     */
    public function aroundAroundDispatch(
        BuiltinPlugin $subject,
        callable $proceed,
        FrontControllerInterface $frontController,
        callable $builtinProceed,
        RequestInterface $request
    ) {
        $path = (string) $request->getPathInfo();
        $adminFront = (string) $this->deploymentConfig->get('backend/frontName', 'admin');

        if ($adminFront !== '' && str_starts_with($path, '/' . $adminFront)) {
            return $builtinProceed($request);
        }

        return $proceed($frontController, $builtinProceed, $request);
    }
}
