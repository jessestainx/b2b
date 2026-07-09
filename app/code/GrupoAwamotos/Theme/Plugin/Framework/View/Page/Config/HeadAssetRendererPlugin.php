<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Framework\View\Page\Config;

use Magento\Framework\View\Page\Config\Renderer;

/**
 * Remove ou converte em async CSS render-blocking do head.
 *
 * 1. Google Fonts Rubik -> async (herdado do tema pai ayo_default).
 * 2. CSS de interacao (calendar, uppy, chosen, fancybox, quickview, loader, brand)
 *    -> removidos (conteudo consolidado em awa-super-global.css via CSS gate).
 * 3. styles-l.css duplicata -> removida a instancia extra (awa-styles-l-last.phtml
 *    carrega a versao definitiva por ultimo para garantir cascata correta).
 *
 * O mecanismo <remove> do layout XML nao remove assets declarados com <link src>
 * por modulos de terceiros (Rokanthemes). Este plugin opera diretamente no HTML
 * renderizado, garantindo a remocao independente do tipo de declaracao.
 */
class HeadAssetRendererPlugin
{
    private const RUBIK_URL_PATTERN = 'fonts.googleapis.com/css2?family=Rubik';

    /**
     * Sufixos de arquivo a remover completamente do head (conteudo em awa-super-global.css).
     * Apenas CSS declarados como bloqueantes (media="all") sao removidos.
     * Versoes com media="print" ou data-awa-gate sao mantidas (async/gated).
     *
     * @var string[]
     */
    private const BLOCKING_CSS_TO_REMOVE = [
        'mage/calendar.css',
        'jquery/uppy/dist/uppy-custom.css',
        'Rokanthemes_QuickView/css/rokan_quickview.css',
        'Rokanthemes_RokanBase/css/chosen.css',
        'Rokanthemes_RokanBase/css/jquery.fancybox.css',
        'Rokanthemes_Themeoption/css/loader.css',
        'Rokanthemes_Brand/css/styles.css',
        'css/styles-l.css',
    ];

    /**
     * Remove CSS render-blocking redundantes e converte Rubik para async.
     *
     * @param Renderer $subject
     * @param string   $result
     * @return string
     */
    public function afterRenderHeadAssets(Renderer $subject, string $result): string
    {
        $result = $this->removeBlockingCss($result);
        $result = $this->removeStylesLDuplicate($result);
        $result = $this->convertRubikToAsync($result);
        return $result;
    }

    /**
     * Remove CSS blocking cujo conteudo ja esta no awa-super-global.css (CSS gate).
     * So remove tags com media="all" (ou sem media) -- mantém async (media="print").
     *
     * @param string $html
     * @return string
     */
    private function removeBlockingCss(string $html): string
    {
        foreach (self::BLOCKING_CSS_TO_REMOVE as $cssPath) {
            $pattern = '/<link\s[^>]*href=["\'][^"\']*'
                . preg_quote($cssPath, '/')
                . '[^"\']*["\'][^>]*>/i';

            $html = preg_replace_callback($pattern, static function (array $m): string {
                $tag = $m[0];
                if (
                    strpos($tag, 'media="print"') !== false
                    || strpos($tag, "media='print'") !== false
                    || strpos($tag, 'data-awa-gate') !== false
                    || strpos($tag, 'onload') !== false
                ) {
                    return $tag;
                }
                return '';
            }, $html) ?? $html;
        }

        return $html;
    }

    /**
     * Remove a instancia duplicada de styles-l.css declarada pelo modulo/tema pai.
     * awa-styles-l-last.phtml adiciona a versao final corretamente posicionada.
     * Se houver 2+ instancias blocking de styles-l.css, mantem apenas a ultima.
     *
     * @param string $html
     * @return string
     */
    private function removeStylesLDuplicate(string $html): string
    {
        $pattern = '/<link\s[^>]*href=["\'][^"\']*\/css\/styles-l\.css[^"\']*["\'][^>]*>/i';
        preg_match_all($pattern, $html, $matches);

        $blockingMatches = array_values(array_filter($matches[0], static function (string $tag): bool {
            return strpos($tag, 'media="print"') === false
                && strpos($tag, "media='print'") === false
                && strpos($tag, 'onload') === false
                && strpos($tag, 'data-awa-gate') === false;
        }));

        if (count($blockingMatches) > 1) {
            $toRemove = array_slice($blockingMatches, 0, -1);
            foreach ($toRemove as $tag) {
                $html = str_replace($tag, '', $html);
            }
        }

        return $html;
    }

    /**
     * Converte o link do Google Fonts Rubik de bloqueante para assincrono.
     *
     * @param string $html
     * @return string
     */
    private function convertRubikToAsync(string $html): string
    {
        if (strpos($html, self::RUBIK_URL_PATTERN) === false) {
            return $html;
        }

        $html = preg_replace_callback(
            '/<link\s[^>]*' . preg_quote(self::RUBIK_URL_PATTERN, '/') . '[^>]*>/i',
            static function (array $matches): string {
                $original = $matches[0];

                if (
                    strpos($original, 'media="print"') !== false
                    || strpos($original, "media='print'") !== false
                ) {
                    return $original;
                }

                if (!preg_match('/href=["\']([^"\']+)["\']/', $original, $hrefMatch)) {
                    return $original;
                }

                $href = htmlspecialchars($hrefMatch[1], ENT_QUOTES, 'UTF-8');

                return '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin/>'
                    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>'
                    . '<link rel="stylesheet" type="text/css"'
                    . ' media="print" onload="this.media=\'all\'"'
                    . ' href="' . $href . '"/>'
                    . '<noscript><link rel="stylesheet" type="text/css"'
                    . ' href="' . $href . '"/></noscript>';
            },
            $html
        ) ?? $html;

        return $html;
    }
}
