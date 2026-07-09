<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\UrlInterface;

class BannerMedia extends AbstractHelper
{
    public function __construct(
        Context $context,
        private readonly UrlInterface $urlBuilder
    ) {
        parent::__construct($context);
    }

    public function getMediaUrl(string $relativePath): string
    {
        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA])
            . ltrim($relativePath, '/');
    }

    public function getHomeB2bDesktopUrl(): string
    {
        return $this->getMediaUrl('import/catalog/banners/home-b2b-atacado-2026.jpg');
    }

    public function getHomeB2bMobileUrl(): string
    {
        return $this->getMediaUrl('import/catalog/banners/home-b2b-atacado-2026-mobile.jpg');
    }
}
