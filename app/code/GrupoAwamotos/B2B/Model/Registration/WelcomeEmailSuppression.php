<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Registration;

use Magento\Framework\Registry;

/**
 * Controla a supressão do e-mail genérico de boas-vindas do Magento
 * durante o cadastro B2B (o cliente recebe apenas o template B2B de cadastro recebido).
 */
class WelcomeEmailSuppression
{
    public const REGISTRY_KEY = 'grupoawamotos_b2b_suppress_customer_welcome_email';

    public function __construct(
        private readonly Registry $registry
    ) {
    }

    public function isActive(): bool
    {
        return (bool) $this->registry->registry(self::REGISTRY_KEY);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function runWithoutWelcomeEmail(callable $callback): mixed
    {
        $this->enable();
        try {
            return $callback();
        } finally {
            $this->disable();
        }
    }

    private function enable(): void
    {
        $this->registry->register(self::REGISTRY_KEY, true);
    }

    private function disable(): void
    {
        if ($this->registry->registry(self::REGISTRY_KEY)) {
            $this->registry->unregister(self::REGISTRY_KEY);
        }
    }
}
