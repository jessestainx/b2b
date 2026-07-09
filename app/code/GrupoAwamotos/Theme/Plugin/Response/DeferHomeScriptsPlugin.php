<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Home PSI — adia require config + merged bundle até interação (TBT/Lighthouse).
 */
class DeferHomeScriptsPlugin
{
    private const HOME_ACTION = 'cms_index_index';
    private const HOME_BOOTSTRAP_VERSION = '20260703-carousel-contract-v9';

    /** Scripts que bloqueiam o parser na home — adiar com defer (stub permanece síncrono). */
    private const HOME_DEFER_SCRIPT_FRAGMENTS = [
        'awa-home-shelf-bootstrap.js',
        'awa-header-account-prompt.js',
        'awa-mirasvit-autocomplete-init.js',
        'awa-search-clear-init.js',
        'awa-a11y-nav-links.js',
    ];

    public function __construct(
        private readonly HttpRequest $request,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public function beforeSendResponse(ResponseHttp $subject): void
    {
        if ($this->request->getFullActionName() !== self::HOME_ACTION) {
            return;
        }

        $contentType = $subject->getHeader('Content-Type');
        if ($contentType && stripos($contentType->getFieldValue(), 'text/html') === false) {
            return;
        }

        $html = (string) $subject->getBody();
        if ($html === '') {
            return;
        }

        $html = $this->stripBodyLoaderMageInit($html);
        $html = $this->deferBlockingHomeScripts($html);
        $html = $this->bumpHomeBootstrapAssetVersions($html);

        if (!str_contains($html, 'awa-home-bootstrap-defer.js')) {
            // Merged bundle URL is .../_cache/merged/<hash>.js (not always .min.js)
            $pattern = '#<script(?![^>]*type=["\']text/plain["\'])[^>]*>\s*(var LOCALE\s*=.*?require\s*=\s*\{.*?\};)\s*</script>\s*'
                . '<script(?![^>]*defer)[^>]*src="([^"]*_cache/merged/[^"]+\.js)"[^>]*>\s*</script>#s';

            if (preg_match($pattern, $html, $matches)) {
                $inlineJs = $matches[1];
                $mergedSrc = $matches[2];

                try {
                    $deferSrc = $this->assetRepository->getUrl('js/awa-home-bootstrap-defer.js')
                        . '?v=' . self::HOME_BOOTSTRAP_VERSION;
                    $stubSrc = $this->assetRepository->getUrl('js/awa-require-stub.js')
                        . '?v=' . self::HOME_BOOTSTRAP_VERSION;
                } catch (\Throwable) {
                    $subject->setBody($html);
                    return;
                }

                $replacement = '<script src="'
                    . htmlspecialchars($stubSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '"></script><script type="text/plain" id="awa-home-bootstrap-inline">'
                    . $inlineJs
                    . '</script><script type="text/plain" id="awa-home-bootstrap-merged" data-src="'
                    . htmlspecialchars($mergedSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '"></script><script src="'
                    . htmlspecialchars($deferSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" defer></script>';

                $html = (string) preg_replace($pattern, $replacement, $html, 1);
            }
        }

        $subject->setBody($html);
    }

    /**
     * Home: body loader/loaderAjax via mage-init quebra com AMD adiado — init manual após bootstrap.
     */
    private function stripBodyLoaderMageInit(string $html): string
    {
        $html = (string) preg_replace(
            '/<body\s+data-container="body"\s+data-mage-init=\'[^\']*loaderAjax[^\']*\'/i',
            '<body data-container="body"',
            $html,
            1
        );

        if (!str_contains($html, 'awa-home-body-loader-defer')) {
            try {
                $loaderIcon = $this->assetRepository->getUrl('images/loader-2.gif');
            } catch (\Throwable) {
                return $html;
            }

            $loaderIconEsc = htmlspecialchars($loaderIcon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $snippet = '<script>(function(w,d){"use strict";var done=false;var cfg={"loader":{"icon":"'
                . $loaderIconEsc
                . '"},"loaderAjax":{}};function boot(){if(done||typeof w.require!=="function"){return;}done=true;w.require(["jquery","mage/loader"],function($){var $body=$("body");if(!$body.data("mageLoader")){$body.mage("loader",cfg.loader);}if(!$body.data("mageLoaderAjax")){$body.mage("loaderAjax",cfg.loaderAjax);}});}d.addEventListener("awa:customer-data-ready",boot,{once:true});if("requestIdleCallback"in w){w.requestIdleCallback(boot,{timeout:5000});}else{w.setTimeout(boot,3500);}})(window,document);</script>';

            $replaced = preg_replace('/<\/body>/i', $snippet . "\n</body>", $html, 1);

            return is_string($replaced) ? $replaced : $html;
        }

        return $html;
    }

    private function deferBlockingHomeScripts(string $html): string
    {
        foreach (self::HOME_DEFER_SCRIPT_FRAGMENTS as $fragment) {
            $quoted = preg_quote($fragment, '#');
            $html = (string) preg_replace(
                '#<script(\s[^>]*src="[^"]*' . $quoted . '[^"]*")(?![^>]*\bdefer\b)([^>]*)>\s*</script>#i',
                '<script$1 defer$2></script>',
                $html
            );
        }

        return $html;
    }

    private function bumpHomeBootstrapAssetVersions(string $html): string
    {
        $version = self::HOME_BOOTSTRAP_VERSION;
        $fragments = [
            'awa-home-bootstrap-defer.js',
            'awa-require-stub.js',
            'awa-home-shelf-bootstrap.js',
            'awa-scroll-carousel.min.js',
            'awa-scroll-carousel.js',
        ];

        foreach ($fragments as $fragment) {
            $quoted = preg_quote($fragment, '#');
            $html = (string) preg_replace(
                '#(' . $quoted . '\?v=)[^"\']+#',
                '${1}' . $version,
                $html
            );
        }

        return $html;
    }
}
