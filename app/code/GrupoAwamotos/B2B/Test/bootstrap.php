<?php

declare(strict_types=1);

/**
 * Garante que as classes GrupoAwamotos do worktree prevaleçam sobre o vendor/autoload
 * apontado para o DocumentRoot de produção.
 */
$moduleRoot = dirname(__DIR__);
$worktreeRoot = dirname($moduleRoot, 4);

$magentoBootstrap = $worktreeRoot . '/dev/tests/unit/framework/bootstrap.php';
if (!is_file($magentoBootstrap)) {
    throw new RuntimeException('Bootstrap Magento ausente: ' . $magentoBootstrap);
}

require $magentoBootstrap;

spl_autoload_register(
    static function (string $class) use ($worktreeRoot): bool {
        $prefix = 'GrupoAwamotos\\';
        if (!str_starts_with($class, $prefix)) {
            return false;
        }

        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $worktreeRoot . '/app/code/GrupoAwamotos/' . $relative . '.php';
        if (!is_file($file)) {
            return false;
        }

        require $file;
        return true;
    },
    true,
    true
);
