<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Helper;

use Magento\Framework\App\Request\Http;
use Magento\Framework\UrlInterface;

/**
 * Monta parâmetro referer para login B2B com retorno pós-auth seguro.
 */
class LoginRefererParams
{
    public function __construct(
        private readonly Http $request,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toLoginRedirectParams(): array
    {
        $returnUrl = $this->resolveReturnUrl();

        if ($returnUrl === '') {
            return [];
        }

        return ['referer' => base64_encode($returnUrl)];
    }

    private function resolveReturnUrl(): string
    {
        $baseUrl = rtrim($this->urlBuilder->getBaseUrl(), '/');

        if ($baseUrl === '') {
            return '';
        }

        $referer = (string) $this->request->getServer('HTTP_REFERER');

        if ($referer !== '' && $this->isInternalUrl($referer, $baseUrl)) {
            return $referer;
        }

        $requestUri = (string) $this->request->getRequestUri();

        if ($requestUri === '' || $requestUri === '/') {
            return '';
        }

        $currentUrl = $baseUrl . $requestUri;

        return $this->isInternalUrl($currentUrl, $baseUrl) ? $currentUrl : '';
    }

    private function isInternalUrl(string $url, string $baseUrl): bool
    {
        return str_starts_with($url, $baseUrl);
    }
}
