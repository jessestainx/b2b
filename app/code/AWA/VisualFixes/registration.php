<?php
/**
 * AWA_VisualFixes
 *
 * Carrega correções visuais (CSS + JS) para os 47 bugs identificados
 * na auditoria 2026-06-28.
 */
declare(strict_types=1);

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'AWA_VisualFixes',
    __DIR__
);
