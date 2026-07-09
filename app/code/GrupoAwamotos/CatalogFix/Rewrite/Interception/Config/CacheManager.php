<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Rewrite\Interception\Config;

use Magento\Framework\App\ObjectManager\ConfigLoader\Compiled as CompiledLoader;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\App\ObjectManager\ConfigWriterInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Fix PHP 8 TypeError em CacheManager::load() - race condition durante di:compile.
 *
 * Problema: isCompiled() verifica file_exists() e compiledLoader->load() faz
 * include() do mesmo arquivo. Se deletado entre as duas chamadas (race condition
 * durante setup:di:compile), include() retorna false. PHP 8 lanca TypeError pois
 * o return type e ?array.
 *
 * Solucao: Preference re-implementa load() com is_array() guard. Nao depende do
 * sistema de plugins (indisponivel durante bootstrap da interception config).
 *
 * @see vendor/magento/framework/Interception/Config/CacheManager.php
 */
class CacheManager extends \Magento\Framework\Interception\Config\CacheManager
{
    private FrontendInterface $cacheBackend;
    private SerializerInterface $serializerBackend;
    private CompiledLoader $compiledLoaderBackend;

    public function __construct(
        FrontendInterface $cache,
        SerializerInterface $serializer,
        ConfigWriterInterface $configWriter,
        CompiledLoader $compiledLoader
    ) {
        parent::__construct($cache, $serializer, $configWriter, $compiledLoader);
        $this->cacheBackend          = $cache;
        $this->serializerBackend     = $serializer;
        $this->compiledLoaderBackend = $compiledLoader;
    }

    /**
     * Carrega config de interception com guard contra race condition.
     */
    public function load(string $key): ?array
    {
        if (file_exists(CompiledLoader::getFilePath($key))) {
            // Guard: file_exists() pode ser true mas include() retornar false
            // se o arquivo for deletado entre as duas chamadas (race condition).
            $result = $this->compiledLoaderBackend->load($key);
            return is_array($result) ? $result : null;
        }

        $intercepted = $this->cacheBackend->load($key);
        return $intercepted ? $this->serializerBackend->unserialize($intercepted) : null;
    }
}
