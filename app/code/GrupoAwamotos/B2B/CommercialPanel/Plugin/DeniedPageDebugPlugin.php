<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Plugin;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use Magento\Backend\Controller\Adminhtml\Denied\Index;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Redireciona perfis comerciais fora de admin/denied para o cockpit AWA Comercial.
 */
class DeniedPageDebugPlugin
{
    private const ACL_COMMERCIAL_DASHBOARD = 'GrupoAwamotos_B2B::commercial_dashboard';

    public function __construct(
        private readonly AdminSession $adminSession,
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly AuthorizationInterface $authorization,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    /**
     * @param callable(): mixed $proceed
     * @return mixed
     */
    public function aroundExecute(Index $subject, callable $proceed)
    {
        if (!$this->adminSession->isLoggedIn()) {
            return $proceed();
        }

        $hasCommercialDash = $this->authorization->isAllowed(self::ACL_COMMERCIAL_DASHBOARD);
        if ($this->portfolioScope->isCockpitOnlyUser() || $hasCommercialDash) {
            return $this->redirectFactory->create()
                ->setPath('awa_commercial/commercialdashboard/index');
        }

        return $proceed();
    }
}
