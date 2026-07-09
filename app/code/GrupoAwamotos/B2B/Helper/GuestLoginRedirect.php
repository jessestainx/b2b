<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Helper;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;

/**
 * Redirect padronizado de visitante → login B2B com retorno pós-auth.
 */
class GuestLoginRedirect
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function create(string $returnRoutePath, array $returnParams = []): Redirect
    {
        $this->customerSession->setBeforeAuthUrl(
            $this->urlBuilder->getUrl($returnRoutePath, $returnParams)
        );

        return $this->redirectFactory->create()->setPath('b2b/account/login');
    }
}
