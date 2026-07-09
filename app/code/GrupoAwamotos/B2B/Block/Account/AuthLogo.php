<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Account;

use GrupoAwamotos\B2B\Model\AuthLogoResolver;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Theme\Block\Html\Header\Logo;

/**
 * Logo compacto reutilizável nas páginas de autenticação B2B.
 */
class AuthLogo extends Template
{
    private Logo $logo;
    private AuthLogoResolver $authLogoResolver;

    public function __construct(
        Context $context,
        Logo $logo,
        AuthLogoResolver $authLogoResolver,
        array $data = []
    ) {
        $this->logo = $logo;
        $this->authLogoResolver = $authLogoResolver;
        parent::__construct($context, $data);
    }

    public function getLogoSrc(): string
    {
        $resolved = trim($this->authLogoResolver->getLogoSrc());
        return $resolved !== '' ? $resolved : $this->logo->getLogoSrc();
    }

    public function getLogoAlt(): string
    {
        $resolved = trim($this->authLogoResolver->getLogoAlt());
        return $resolved !== '' ? $resolved : $this->logo->getLogoAlt();
    }

    public function getHomeUrl(): string
    {
        return $this->getUrl('');
    }
}
