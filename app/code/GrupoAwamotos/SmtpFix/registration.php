<?php

/**
 * Fix para compatibilidade do MagePal Gmail SMTP App com Symfony Mailer
 * Corrige o erro: "The reply-to header must be an instance of MailboxListHeader"
 */

declare(strict_types=1);

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'GrupoAwamotos_SmtpFix',
    __DIR__
);
