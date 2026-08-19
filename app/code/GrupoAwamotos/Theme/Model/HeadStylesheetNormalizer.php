<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Model;

/**
 * Normalizações de <link rel="stylesheet"> compartilhadas (Fase 3).
 *
 * Estes três métodos existiam como CÓPIAS LITERAIS em
 * OptimizeHeadStylesPlugin (consolidador, sortOrder 998) e
 * OptimizeHtmlResponseObserver (safety net pós-FPC). Os corpos são
 * byte-idênticos ao que ambos executavam — só os literais de arquivo/query
 * foram trocados por lookups no {@see CascadeAssetManifest}.
 *
 * patchStaleHomeHeaderAssets NÃO entrou aqui: as versões do plugin e do
 * observer divergem (a do plugin também normaliza home-critical-stack e
 * header-refine-terminal) — cada uma permanece no seu consumidor, ambas
 * lendo os nomes do manifest.
 */
final class HeadStylesheetNormalizer
{
    public function __construct(
        private readonly CascadeAssetManifest $manifest,
    ) {
    }

    public function normalizeRefineStylesheetQuery(string $html): string
    {
        // Normaliza TODAS as versões antigas (.css?v=X e .min.css?v=X) para o canonical atual.
        return preg_replace(
            '/' . preg_quote($this->manifest->fragment('refine'), '/') . '\.(?:min\.)?css(?:\?v=[^"\'&\s>]+)?/',
            $this->manifest->file('refine') . $this->manifest->query('refine'),
            $html
        ) ?? $html;
    }

    public function injectGlobalRefineStylesheetIfMissing(string $html): string
    {
        // Fragmento sem extensão: detecta tanto .css quanto .min.css.
        if (str_contains($html, $this->manifest->fragment('refine'))) {
            return $html;
        }

        if (!preg_match('#/static/(version[A-Za-z0-9]+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#', $html, $versionMatch)) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . $this->manifest->file('refine')
            . $this->manifest->query('refine');

        $tag = '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
            . ' data-awa-impeccable-terminal="refine"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    public function dedupeStylesheetHrefs(string $html): string
    {
        $pattern = '/<link\s[^>]*rel=["\']stylesheet["\'][^>]*\/?>/i';
        $hrefCounts = [];
        $hasActiveTag = [];
        $seen = [];

        $isActiveStylesheet = static function (string $tag): bool {
            return !preg_match('/media=["\']print["\']/i', $tag)
                && !preg_match('/onload\s*=/i', $tag);
        };

        // Exclude <noscript> content from analysis — links inside noscript are fallbacks,
        // not active stylesheets. Counting them as active causes async print/onload links
        // to be incorrectly removed (the noscript link triggers hasActiveTag, removing the async one).
        $htmlForAnalysis = preg_replace('/<noscript>.*?<\/noscript>/is', '', $html) ?? $html;

        if (!preg_match_all($pattern, $htmlForAnalysis, $stylesheetMatches)) {
            return $html;
        }

        foreach ($stylesheetMatches[0] as $tag) {
            if (!preg_match('/href=(["\'])([^"\']+)\1/i', $tag, $hrefMatch)) {
                continue;
            }

            $href = $hrefMatch[2];
            $hrefCounts[$href] = ($hrefCounts[$href] ?? 0) + 1;
            if ($isActiveStylesheet($tag)) {
                $hasActiveTag[$href] = true;
            }
        }

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use (&$seen, $hrefCounts, $hasActiveTag, $isActiveStylesheet): string {
                $tag = $matches[0];
                if (!preg_match('/href=(["\'])([^"\']+)\1/i', $tag, $hrefMatch)) {
                    return $tag;
                }

                $href = $hrefMatch[2];
                if (($hrefCounts[$href] ?? 0) < 2) {
                    return $tag;
                }

                $active = $isActiveStylesheet($tag);
                if (($hasActiveTag[$href] ?? false) && !$active) {
                    return '';
                }

                if (isset($seen[$href])) {
                    return '';
                }

                $seen[$href] = true;

                return $tag;
            },
            $html
        ) ?? $html;
    }
}
