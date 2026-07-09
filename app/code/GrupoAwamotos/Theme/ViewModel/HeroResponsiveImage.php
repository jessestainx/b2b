<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * URLs srcset/sizes para o hero slider (LCP homepage).
 */
class HeroResponsiveImage implements ArgumentInterface
{
    /** @var int[] */
    private const WIDTHS = [480, 768, 1200, 1920];

    private StoreManagerInterface $storeManager;
    private Filesystem $filesystem;

    public function __construct(
        StoreManagerInterface $storeManager,
        Filesystem $filesystem
    ) {
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
    }

    /**
     * @return int[]
     */
    public function getWidths(): array
    {
        return self::WIDTHS;
    }

    public function getVariantRelativePath(string $slideImagePath, int $width): string
    {
        $normalized = ltrim($slideImagePath, '/');

        return $normalized . '-' . $width . 'w.webp';
    }

    public function getFullWebpRelativePath(string $slideImagePath): string
    {
        return ltrim($slideImagePath, '/') . '.webp';
    }

    public function getOriginalUrl(string $slideImagePath): string
    {
        return $this->getMediaUrl(ltrim($slideImagePath, '/'));
    }

    public function getMediaUrl(string $relativePath): string
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(
            \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
        );

        return rtrim($mediaUrl, '/') . '/' . ltrim($relativePath, '/');
    }

    /**
     * @return array<int, string> width => absolute URL (variant or fallback full webp)
     */
    public function resolveVariantUrls(string $slideImagePath): array
    {
        if ($slideImagePath === '') {
            return [];
        }

        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $fullWebp = $this->getFullWebpRelativePath($slideImagePath);
        $hasFullWebp = $mediaDir->isExist($fullWebp);
        $urls = [];

        foreach (self::WIDTHS as $width) {
            $variant = $this->getVariantRelativePath($slideImagePath, $width);
            if ($mediaDir->isExist($variant)) {
                $urls[$width] = $this->getMediaUrl($variant);
                continue;
            }

            if ($hasFullWebp) {
                $urls[$width] = $this->getMediaUrl($fullWebp);
            }
        }

        return $urls;
    }

    public function buildSrcset(string $slideImagePath): string
    {
        $parts = [];
        foreach ($this->resolveVariantUrls($slideImagePath) as $width => $url) {
            $parts[] = $url . ' ' . $width . 'w';
        }

        return implode(', ', $parts);
    }

    public function getPreloadUrl(string $slideImagePath, bool $mobile): string
    {
        $urls = $this->resolveVariantUrls($slideImagePath);
        if ($urls === []) {
            return $this->getOriginalUrl($slideImagePath);
        }

        return $mobile ? ($urls[768] ?? $urls[480] ?? reset($urls)) : ($urls[1920] ?? end($urls));
    }

    public function getSizesAttribute(bool $mobileSlider): string
    {
        return $mobileSlider ? '100vw' : '(min-width: 1200px) 1920px, 100vw';
    }

    /**
     * Monta <picture> responsivo para o primeiro slide (LCP).
     */
    public function buildLcpPictureHtml(
        string $slideImagePath,
        string $alt,
        bool $isLcpCandidate,
        bool $mobileSlider
    ): string {
        if ($slideImagePath === '') {
            return '';
        }

        $srcset = $this->buildSrcset($slideImagePath);
        $urls = $this->resolveVariantUrls($slideImagePath);
        $sizes = $this->getSizesAttribute($mobileSlider);
        $defaultSrc = $urls === []
            ? $this->getOriginalUrl($slideImagePath)
            : ($mobileSlider ? ($urls[768] ?? $urls[480] ?? reset($urls)) : ($urls[1920] ?? end($urls)));

        $loading = $isLcpCandidate ? 'eager' : 'lazy';
        // fetchpriority só no slider mobile visível — desktop usa <link rel=preload> (P9 dedup)
        $priority = ($isLcpCandidate && $mobileSlider) ? ' fetchpriority="high"' : '';
        $width = $mobileSlider ? 768 : 1920;
        $height = $mobileSlider ? 400 : 470;
        // async: preload no início do head traz o recurso cedo; sync bloqueia main thread (TBT/INP).
        $decoding = 'async';

        $altEsc = htmlspecialchars(strip_tags($alt), ENT_QUOTES, 'UTF-8');

        return '<picture>'
            . ($srcset !== '' ? '<source type="image/webp" srcset="' . htmlspecialchars($srcset, ENT_QUOTES, 'UTF-8') . '" sizes="' . $sizes . '">' : '')
            . '<img src="' . htmlspecialchars($defaultSrc, ENT_QUOTES, 'UTF-8') . '"'
            . ' alt="' . $altEsc . '"'
            . ' loading="' . $loading . '"'
            . ' decoding="' . $decoding . '"'
            . ' width="' . $width . '" height="' . $height . '"'
            . ' sizes="' . $sizes . '"'
            . $priority
            . ' />'
            . '</picture>';
    }
}
