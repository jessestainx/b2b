<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\PageCache;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\PageCache\NotCacheableInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\PageCache\Model\App\Response\HttpPlugin;

/**
 * Evita setNoCacheHeaders() do HttpPlugin core em primeira visita de convidados cacheáveis.
 *
 * O HttpPlugin compara getVaryString() com o cookie X-Magento-Vary. Quando o cookie ainda não
 * existe, o core chama setNoCacheHeaders() mesmo com layout público — PDP/PLP ficam UNCACHEABLE
 * no Varnish (~200–500 ms TTFB em todo hit). Mantemos no-store apenas quando o cookie existe e
 * diverge do contexto atual (troca de login / lista ERP).
 */
class HttpPluginFpcGuardPlugin
{
    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @param callable(HttpResponse): void $proceed
     */
    public function aroundBeforeSendResponse(
        HttpPlugin $subject,
        callable $proceed,
        HttpResponse $response
    ): void {
        if (
            $response instanceof NotCacheableInterface
            || $response->headersSent()
            || $response->getMetadata('NotCacheable')
        ) {
            $proceed($response);
            return;
        }

        $varyCookie = $this->request->get(HttpResponse::COOKIE_VARY_STRING);

        if ($this->isMissingVaryCookie($varyCookie) && $this->isAnonymousGuest()) {
            $response->sendVary();
            return;
        }

        $proceed($response);
    }

    /**
     * @param string|bool|null $varyCookie
     */
    private function isMissingVaryCookie($varyCookie): bool
    {
        return $varyCookie === null || $varyCookie === false || $varyCookie === '';
    }

    private function isAnonymousGuest(): bool
    {
        $sessionName = session_name();
        $cookie = $this->request->getCookie($sessionName);

        if ($cookie !== null && $cookie !== '') {
            return false;
        }

        return ($_COOKIE[$sessionName] ?? null) === null;
    }
}
