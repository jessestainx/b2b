<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\HttpInterface;

/**
 * Forces HTTP 404 on the search-result page used to display "not found" results.
 *
 * Mirasvit Search (noroute_to_search=1) converts any 404 response into a 302
 * redirect to catalogsearch/result/?404=1, which then returns HTTP 200 — a
 * "soft 404" invisible to search engines.
 *
 * This plugin registers on HttpInterface::beforeSendResponse with sortOrder=999
 * (AFTER Mirasvit's NoRoutePlugin). When the request carries ?404=1 it:
 *   1. Forces HTTP 404 on the response (good for SEO)
 *   2. Clears any Location redirect header set by Mirasvit's plugin
 *
 * Result: the user sees the search-result page content (good UX from Mirasvit),
 * but search engines receive proper HTTP 404 (good SEO — page not indexed).
 */
class Soft404Plugin
{
    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    public function beforeSendResponse(HttpInterface $response): void
    {
        if ($this->request->getParam('404') !== '1') {
            return;
        }

        $response->setHttpResponseCode(404);

        // Clear Location redirect header that Mirasvit NoRoutePlugin may have set
        if ($response instanceof HttpResponse) {
            $headers = $response->getHeaders();
            $locationHeader = $headers->get('Location');
            if ($locationHeader !== false) {
                $headers->removeHeader($locationHeader);
            }
        }
    }
}
