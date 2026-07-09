<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\SlideBanner;

use Rokanthemes\SlideBanner\Block\Slider;

/**
 * Corrige alt das imagens do SlideBanner quando o texto vem de HTML rico (slide_text).
 *
 * strip_tags() cola o fim de um bloco ao início do seguinte (ex.: "Motos" + "Modelos"
 * → "MotosModelos"). Inserimos separadores antes de remover as tags.
 */
final class SliderSlideAltNormalizePlugin
{
    /**
     * @param mixed $src
     * @param mixed $altText
     * @param mixed $isFirst
     * @return array{0:mixed,1:string,2:bool}
     */
    public function beforeGetImageElement(Slider $subject, $src, $altText = '', $isFirst = false): array
    {
        return [$src, $this->plainAltFromSlideHtml((string) $altText), (bool) $isFirst];
    }

    /**
     * @param mixed $src
     * @param mixed $altText
     * @param mixed $isFirst
     * @return array{0:mixed,1:string,2:bool}
     */
    public function beforeGetImageElementMobile(Slider $subject, $src, $altText = '', $isFirst = false): array
    {
        return [$src, $this->plainAltFromSlideHtml((string) $altText), (bool) $isFirst];
    }

    /**
     * @param mixed $desktopSrc
     * @param mixed $mobileSrc
     * @param mixed $altText
     * @param mixed $isFirst
     * @return array{0:mixed,1:mixed,2:string,3:bool}
     */
    public function beforeGetPictureElement(
        Slider $subject,
        $desktopSrc,
        $mobileSrc,
        $altText = '',
        $isFirst = false
    ): array {
        return [$desktopSrc, $mobileSrc, $this->plainAltFromSlideHtml((string) $altText), (bool) $isFirst];
    }

    private function plainAltFromSlideHtml(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '' || !str_contains($trimmed, '<')) {
            return $trimmed;
        }

        $withBreaks = preg_replace(
            '/<\s*\/\s*(?:p|div|h[1-6]|li|ul|ol|section|article|blockquote|header|footer|table|tr|td|th)\b[^>]*>/i',
            ' ',
            $trimmed
        );
        if (!is_string($withBreaks)) {
            $withBreaks = $trimmed;
        }

        $withBreaks = preg_replace('/<\s*br\s*\/?\s*>/i', ' ', $withBreaks);
        if (!is_string($withBreaks)) {
            $withBreaks = $trimmed;
        }

        $plain = strip_tags($withBreaks);
        $collapsed = preg_replace('/\s+/u', ' ', trim($plain));

        return is_string($collapsed) ? $collapsed : trim($plain);
    }
}
