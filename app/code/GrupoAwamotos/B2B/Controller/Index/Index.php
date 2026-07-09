<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Index;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Ponto de entrada da área B2B: /b2b
 * - Cliente logado → /b2b/account/index (painel B2B)
 * - Visitante     → /b2b/account/login (login B2B)
 * - Módulo desabilitado → homepage
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config
    ) {
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('/');
        }

        if ($this->customerSession->isLoggedIn()) {
            return $redirect->setPath('b2b/account/index');
        }

        return $redirect->setPath('b2b/account/login');
    }
}
