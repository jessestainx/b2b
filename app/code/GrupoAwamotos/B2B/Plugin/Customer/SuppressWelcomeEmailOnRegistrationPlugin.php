<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Customer;

use GrupoAwamotos\B2B\Model\Registration\WelcomeEmailSuppression;
use Magento\Customer\Model\EmailNotification;

/**
 * Evita o e-mail padrão "Bem-vindo" do Magento ao criar conta no fluxo B2B.
 */
class SuppressWelcomeEmailOnRegistrationPlugin
{
    public function __construct(
        private readonly WelcomeEmailSuppression $welcomeEmailSuppression
    ) {
    }

    /**
     * @param mixed ...$args
     */
    public function aroundNewAccount(
        EmailNotification $subject,
        callable $proceed,
        ...$args
    ): void {
        if ($this->welcomeEmailSuppression->isActive()) {
            return;
        }

        $proceed(...$args);
    }
}
