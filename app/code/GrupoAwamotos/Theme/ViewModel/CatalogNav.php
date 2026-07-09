<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class CatalogNav implements ArgumentInterface
{
    private const CONFIG_NAV_LINKS = 'grupoawamotos_theme/catalogo/nav_links';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function getCmsNavLinks(): array
    {
        $links = [];

        foreach ($this->parseNavConfig() as $entry) {
            $url = $this->resolveUrl($entry['path']);
            if ($url === '') {
                continue;
            }

            $links[] = [
                'label' => $entry['label'],
                'url' => $url,
                'active' => false,
            ];
        }

        return $links;
    }

    /**
     * @return list<array{label: string, url: string, active: bool, highlight?: bool}>
     */
    public function getCatalogModeLinks(): array
    {
        $isRevista = $this->isRevistaMode();

        return [
            [
                'label' => (string) __('Visualizador PDF'),
                'url' => $this->getStandardUrl(),
                'active' => !$isRevista,
            ],
            [
                'label' => (string) __('Catálogo em revista'),
                'url' => $this->getRevistaUrl(),
                'active' => $isRevista,
                'highlight' => true,
            ],
        ];
    }

    public function getStandardUrl(): string
    {
        return $this->urlBuilder->getUrl('catalogo');
    }

    public function getRevistaUrl(): string
    {
        return $this->urlBuilder->getUrl('catalogo/revista');
    }

    public function isRevistaMode(): bool
    {
        return $this->request->getModuleName() === 'catalogo'
            && $this->request->getControllerName() === 'revista';
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    private function parseNavConfig(): array
    {
        $configured = (string) $this->scopeConfig->getValue(
            self::CONFIG_NAV_LINKS,
            ScopeInterface::SCOPE_STORE
        );

        if (trim($configured) === '') {
            return $this->getDefaultEntries();
        }

        $entries = [];
        foreach (preg_split('/\r\n|\r|\n/', $configured) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) !== 2 || $parts[1] === '') {
                continue;
            }

            $entries[] = ['label' => $parts[0], 'path' => $parts[1]];
        }

        return $entries !== [] ? $entries : $this->getDefaultEntries();
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    private function getDefaultEntries(): array
    {
        return [
            ['label' => (string) __('Condições Atacado'), 'path' => 'atacado/condicoes'],
            ['label' => (string) __('Nossas Marcas'), 'path' => 'nossas-marcas'],
            ['label' => (string) __('Lançamentos'), 'path' => 'lancamentos'],
            ['label' => (string) __('Contato'), 'path' => 'contato'],
            ['label' => (string) __('Cadastro B2B'), 'path' => 'route:b2b/register'],
        ];
    }

    private function resolveUrl(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'route:')) {
            return $this->urlBuilder->getUrl(substr($path, 6));
        }

        return $this->urlBuilder->getUrl('', ['_direct' => trim($path, '/')]);
    }
}
