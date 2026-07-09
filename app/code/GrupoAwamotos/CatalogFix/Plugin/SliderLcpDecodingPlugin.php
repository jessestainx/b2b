<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin;

use Rokanthemes\SlideBanner\Block\Slider;

/**
 * P9: Hero LCP usa decoding="async" via HeroResponsiveImage::buildLcpPictureHtml().
 * Plugin legado desativado — não remove mais decoding das imagens do slider.
 */
final class SliderLcpDecodingPlugin
{
    public function afterGetImageElement(
        Slider $subject,
        string $result,
        ?string $src,
        string $altText = '',
        bool $isFirst = false
    ): string {
        return $result;
    }

    public function afterGetImageElementMobile(
        Slider $subject,
        string $result,
        ?string $src,
        string $altText = '',
        bool $isFirst = false
    ): string {
        return $result;
    }
}
