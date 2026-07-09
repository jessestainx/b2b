<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Interception;

use Magento\Framework\Interception\Config\CacheManager;

/**
 * Fix PHP 8 TypeError em CacheManager::load() durante race condition de compile.
 *
 * Root cause: CacheManager::isCompiled() verifica file_exists(), mas
 * compiledLoader->load() faz include() do mesmo arquivo. Se o arquivo for
 * deletado entre as duas chamadas (race condition durante setup:di:compile),
 * include() retorna false. Como load() tem return type ?array, PHP 8 lanca
 * TypeError ANTES do return — o que impede um afterLoad de interceptar.
 *
 * Solucao: aroundLoad com try/catch TypeError converte race condition
 * em cache miss (null), permitindo fallback para o path nao-compilado.
 *
 * @see vendor/magento/framework/Interception/Config/CacheManager.php:load()
 * @see vendor/magento/framework/App/ObjectManager/ConfigLoader/Compiled.php:load()
 */
final class CacheManagerPlugin
{
    /**
     * Envolve load() capturando TypeError de race condition → retorna null (cache miss).
     *
     * @param CacheManager $subject
     * @param callable $proceed
     * @param string $key
     * @return array|null
     */
    public function aroundLoad(CacheManager $subject, callable $proceed, string $key): ?array
    {
        try {
            return $proceed($key);
        } catch (\TypeError $e) {
            // Race condition: arquivo compiled foi deletado entre isCompiled() e include().
            // Retornar null trata como cache miss; Magento reconstroi a config normalmente.
            return null;
        }
    }
}
