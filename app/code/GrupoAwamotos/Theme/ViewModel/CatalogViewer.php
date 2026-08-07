<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class CatalogViewer implements ArgumentInterface
{
    private const XML_PATH_PDF = 'grupoawamotos_theme/catalogo/pdf_path';
    private const XML_PATH_COVER = 'grupoawamotos_theme/catalogo/cover_path';
    private const XML_PATH_PAGE_PREVIEW = 'grupoawamotos_theme/catalogo/page_preview_path';
    private const XML_PATH_MOBILE_COVER = 'grupoawamotos_theme/catalogo/mobile_cover_path';
    private const XML_PATH_HOME_B2B = 'grupoawamotos_theme/catalogo/home_b2b_banner_path';
    private const XML_PATH_HOME_B2B_MOBILE = 'grupoawamotos_theme/catalogo/home_b2b_banner_mobile_path';

    private const DEFAULT_PDF = 'awa/catalogo/catalogo-2026.pdf';
    private const DEFAULT_COVER = 'import/catalog/banners/catalogo-2026.jpg';
    private const DEFAULT_PAGE_PREVIEW = 'awa/catalogo/catalogo-2026-cover.jpg';
    private const DEFAULT_MOBILE_COVER = 'import/catalog/banners/banner-mobile-catalogo-2026.jpg';
    private const DEFAULT_HOME_B2B = 'import/catalog/banners/home-b2b-atacado-2026.jpg';
    private const DEFAULT_HOME_B2B_MOBILE = 'import/catalog/banners/home-b2b-atacado-2026-mobile.jpg';

    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getPdfUrl(): string
    {
        return $this->mediaUrl($this->configPath(self::XML_PATH_PDF, self::DEFAULT_PDF));
    }

    public function getCoverImageUrl(): string
    {
        return $this->mediaUrl($this->configPath(self::XML_PATH_COVER, self::DEFAULT_COVER));
    }

    /**
     * Prévia estática da 1ª página do PDF (evita iframe/PDF.js no PDF grande).
     */
    public function getPdfPagePreviewUrl(): string
    {
        return $this->mediaUrl($this->configPath(self::XML_PATH_PAGE_PREVIEW, self::DEFAULT_PAGE_PREVIEW));
    }

    public function getMobileCoverImageUrl(): string
    {
        return $this->mediaUrl($this->configPath(self::XML_PATH_MOBILE_COVER, self::DEFAULT_MOBILE_COVER));
    }

    public function getHomeB2bBannerUrl(): string
    {
        return $this->mediaUrl($this->configPath(self::XML_PATH_HOME_B2B, self::DEFAULT_HOME_B2B));
    }

    public function getHomeB2bBannerMobileUrl(): string
    {
        return $this->mediaUrl($this->configPath(self::XML_PATH_HOME_B2B_MOBILE, self::DEFAULT_HOME_B2B_MOBILE));
    }

    public function getB2bRegisterUrl(): string
    {
        return $this->urlBuilder->getUrl('b2b/register');
    }

    private function configPath(string $xmlPath, string $default): string
    {
        $value = trim((string) $this->scopeConfig->getValue(
            $xmlPath,
            ScopeInterface::SCOPE_STORE
        ));

        return $value !== '' ? ltrim($value, '/') : $default;
    }

    private function mediaUrl(string $relativePath): string
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA])
            . ltrim($relativePath, '/');
    }
}
