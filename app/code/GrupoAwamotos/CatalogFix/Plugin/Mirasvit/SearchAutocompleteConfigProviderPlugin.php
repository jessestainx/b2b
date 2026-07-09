<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Mirasvit;

use Mirasvit\SearchAutocomplete\Model\ConfigProvider;

/**
 * Evita session_start() em páginas cacheáveis (FPC) ao injetar autocomplete no header.
 *
 * Mirasvit\SearchAutocomplete\Block\Injection inclui customerGroupId no JSON de config;
 * ConfigProvider::getCustomerGroupId() chama CustomerSession e inicia PHPSESSID em todo GET guest.
 */
class SearchAutocompleteConfigProviderPlugin
{
    /**
     * @param callable(): int $proceed
     */
    public function aroundGetCustomerGroupId(ConfigProvider $subject, callable $proceed): int
    {
        if (($_COOKIE[session_name()] ?? null) === null) {
            return 0;
        }

        return (int) $proceed();
    }
}
