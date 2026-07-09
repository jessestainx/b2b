<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Plugin;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Backend\Model\Url as BackendUrl;
use Magento\Framework\AuthorizationInterface;

/**
 * Redireciona perfis comerciais para o dashboard AWA Comercial após login.
 */
class CockpitStartupPagePlugin
{
    private const ACL_COMMERCIAL_DASHBOARD = 'GrupoAwamotos_B2B::commercial_dashboard';

    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly UserContextInterface $userContext,
        private readonly AdminSession $adminSession
    ) {
    }

    public function afterGetStartupPageUrl(BackendUrl $subject, string $result): string
    {
        $userId = (int) $this->userContext->getUserId();
        if ($userId <= 0) {
            $userId = (int) ($this->adminSession->getUser()?->getId() ?? 0);
        }

        if ($userId <= 0 || !$this->authorization->isAllowed(self::ACL_COMMERCIAL_DASHBOARD)) {
            return $result;
        }

        return $subject->getUrl('awa_commercial/commercialdashboard/index');
    }
}
