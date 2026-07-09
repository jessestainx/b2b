<?php

/**
 * GrupoAwamotos Social Proof
 *
 * Adiciona contadores de prova social:
 * - "X pessoas visualizaram este produto hoje"
 * - "Últimas X unidades em estoque"
 * - Badge "MAIS VENDIDO"
 */

declare(strict_types=1);

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'GrupoAwamotos_SocialProof',
    __DIR__
);
