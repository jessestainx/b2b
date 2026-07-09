<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Customer\Form;

use Magento\Customer\Block\Form\Register;
use Magento\Framework\View\Element\ButtonLockManager;

/**
 * Garante que o formulário de cadastro tenha um ButtonLockManager válido
 * antes da renderização do template do tema.
 */
final class RegisterButtonLockManagerPlugin
{
    public function __construct(
        private readonly ButtonLockManager $buttonLockManager
    ) {
    }

    public function beforeToHtml(Register $subject): void
    {
        if ($subject->getButtonLockManager() instanceof ButtonLockManager) {
            return;
        }

        $subject->setButtonLockManager($this->buttonLockManager);
    }
}
