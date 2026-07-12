<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Home PSI — adia require config + merged bundle até interação (TBT/Lighthouse).
 */
class DeferHomeScriptsPlugin
{
    private const DEBUG_LOG_PATH = BP . '/.cursor/debug-ca59a1.log';

    private const HOME_ACTION = 'cms_index_index';
    private const HOME_BOOTSTRAP_VERSION = '20260710-pd6-search-intent-defer-v1';

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
        private readonly File $fileDriver,
    ) {
    }

    public function beforeSendResponse(ResponseHttp $subject): void
    {
        $fullAction = (string) $this->request->getFullActionName();
        if ($fullAction !== 'b2b_account_login') {
            $this->appendDebugLine([
                'sessionId' => 'ca59a1',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php',
                'message' => 'server-side-page-probe',
                'data' => [
                    'action' => $fullAction,
                    'method' => (string) $this->request->getMethod(),
                    'requestUri' => (string) $this->request->getRequestUri(),
                    'query' => $this->request->getParams(),
                    'statusCode' => (int) $subject->getHttpResponseCode(),
                    'userAgent' => (string) $this->request->getServer('HTTP_USER_AGENT'),
                    'referer' => (string) $this->request->getServer('HTTP_REFERER'),
                ],
            ]);
        }

        if ((string) $this->request->getParam('awa_dbg') === '1') {
            $this->appendDebugLine([
                'sessionId' => 'ca59a1',
                'timestamp' => (int) round(microtime(true) * 1000),
                'location' => 'GrupoAwamotos/Theme/Plugin/Response/DeferHomeScriptsPlugin.php',
                'message' => 'same-origin-debug-beacon',
                'data' => [
                    'action' => $fullAction,
                    'method' => (string) $this->request->getMethod(),
                    'requestUri' => (string) $this->request->getRequestUri(),
                    'query' => $this->request->getParams(),
                    'statusCode' => (int) $subject->getHttpResponseCode(),
                    'userAgent' => (string) $this->request->getServer('HTTP_USER_AGENT'),
                    'referer' => (string) $this->request->getServer('HTTP_REFERER'),
                ],
            ]);
        }

        if ($fullAction !== self::HOME_ACTION) {
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
        $html = $this->injectDebugBootProbe($html);

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

    private function injectDebugBootProbe(string $html): string
    {
        if (str_contains($html, 'awa-inline-debug-boot-probe')) {
            return $html;
        }

        $snippet = '<script id="awa-inline-debug-boot-probe">(function(){try{/* #region agent log */var sid="ca59a1";var run="pre-fix";var hyp="H40";var loc="defer-home-plugin:inline-probe";var msg="Inline probe fired on every home response";var data={href:location.href,readyState:document.readyState,stickyCount:document.querySelectorAll(".header-wrapper-sticky").length,menuRootCount:document.querySelectorAll("[data-role=\"awa-vertical-menu\"]").length,panelCount:document.querySelectorAll("[data-role=\"awa-vertical-menu-panel\"]").length,navBarCount:document.querySelectorAll(".awa-nav-bar,[data-awa-header-nav=\"true\"]").length};var encData=encodeURIComponent(JSON.stringify(data).slice(0,240));var qs="?awa_dbg=1&sid="+encodeURIComponent(sid)+"&run="+encodeURIComponent(run)+"&hyp="+encodeURIComponent(hyp)+"&loc="+encodeURIComponent(loc)+"&msg="+encodeURIComponent(msg)+"&ts="+Date.now()+"&transport=inline&data="+encData;var u="/b2b/account/login/"+qs;var i=new Image();i.src=u;if(navigator&&typeof navigator.sendBeacon==="function"){try{navigator.sendBeacon(u,JSON.stringify({sessionId:sid,runId:run,hypothesisId:hyp,location:loc,message:msg,timestamp:Date.now(),data:data}));}catch(e){}}/* #endregion */}catch(e){}})();</script>';
        $snippet .= <<<'HTML'
<script id="awa-inline-header-geometry-probe">(function(){try{
/* #region agent log */
var sentShift=false;
function rect(selector){var e=document.querySelector(selector),r=e&&e.getBoundingClientRect();return r?{x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height)}:null;}
function beacon(hyp,msg,data){var sid="ca59a1",run="header-initial-geometry",loc="defer-home-plugin:inline-header-geometry",qs="?awa_dbg=1&sid="+sid+"&run="+run+"&hyp="+hyp+"&loc="+encodeURIComponent(loc)+"&msg="+encodeURIComponent(msg)+"&ts="+Date.now()+"&transport=inline-geometry&data="+encodeURIComponent(JSON.stringify(data).slice(0,240)),url="/b2b/account/login/"+qs;(new Image()).src=url;if(navigator&&typeof navigator.sendBeacon==="function"){try{navigator.sendBeacon(url,JSON.stringify({sessionId:sid,runId:run,hypothesisId:hyp,location:loc,message:msg,data:data,timestamp:Date.now()}));}catch(e){}}}
function emit(reason){var shell={reason:reason,vw:innerWidth,cw:document.documentElement.clientWidth,sx:Math.round(scrollX),sticky:rect(".header-wrapper-sticky"),main:rect(".awa-main-header .header-main>.container")},content={reason:reason,inner:rect(".awa-main-header__inner.wp-header"),searchCol:rect(".awa-header-search-col"),search:rect("#search")},nav={reason:reason,nav:rect(".awa-nav-bar"),container:rect(".awa-nav-bar>.container"),inner:rect(".awa-nav-bar__inner")};fetch("http://localhost:7306/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3",{method:"POST",headers:{"Content-Type":"application/json","X-Debug-Session-Id":"ca59a1"},body:JSON.stringify({sessionId:"ca59a1",runId:"header-initial-geometry",hypothesisId:"H95-H98",location:"DeferHomeScriptsPlugin:inline-header-geometry",message:"Initial header geometry",data:{shell:shell,content:content,nav:nav},timestamp:Date.now()})}).catch(function(){});beacon("H95","Initial shell geometry",shell);beacon("H96","Initial main content geometry",content);beacon("H97","Initial navigation geometry",nav);}
emit("body-end");
requestAnimationFrame(function(){emit("first-frame");});
try{new PerformanceObserver(function(list){if(sentShift){return;}var entries=list.getEntries();if(entries.length){sentShift=true;requestAnimationFrame(function(){emit("first-layout-shift");});}}).observe({type:"layout-shift",buffered:true});}catch(e){}
/* #endregion */
}catch(e){}})();</script>
HTML;
        $snippet .= <<<'HTML'
<script id="awa-inline-delivery-timeline-probe">(function(){try{
/* #region agent log */
if(!document.body||!document.body.matches(".cms-index-index,.cms-home,.cms-homepage_ayo_home5")){return;}
var sid="ca59a1",run="delivery-timeline",loc="DeferHomeScriptsPlugin:inline-delivery-timeline",shiftSent=false;
function send(hyp,msg,data){var qs="?awa_dbg=1&sid="+sid+"&run="+run+"&hyp="+hyp+"&loc="+encodeURIComponent(loc)+"&msg="+encodeURIComponent(msg)+"&ts="+Date.now()+"&transport=delivery-timeline&data="+encodeURIComponent(JSON.stringify(data).slice(0,420)),url="/b2b/account/login/"+qs,payload=JSON.stringify({sessionId:sid,runId:run,hypothesisId:hyp,location:loc,message:msg,data:data,timestamp:Date.now()});(new Image()).src=url;if(navigator.sendBeacon){try{navigator.sendBeacon(url,payload);}catch(e){}}}
function box(selector){var e=document.querySelector(selector),r=e&&e.getBoundingClientRect();return r?{x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height)}:null;}
function sheets(){return Array.prototype.slice.call(document.querySelectorAll('link[rel="stylesheet"],link[data-awa-first-paint-critical]')).filter(function(l){return /styles-l|awa-head-preload-critical-home|awa-align-grid|awa-commerce-impeccable|awa-home-critical-stack/.test(l.href||"");}).map(function(l){return{f:(l.href||"").split("/").pop(),m:l.media||"",d:!!l.disabled,s:!!l.sheet};});}
var nav=performance.getEntriesByType("navigation")[0]||{};
send("H100","Navigation delivery identity",{type:nav.type||"",delivery:nav.deliveryType||"",transfer:nav.transferSize||0,encoded:nav.encodedBodySize||0,dpr:devicePixelRatio,vw:innerWidth,cw:document.documentElement.clientWidth,worker:navigator.serviceWorker&&navigator.serviceWorker.controller?navigator.serviceWorker.controller.scriptURL:null});
send("H101","Critical stylesheet state at body end",{ready:document.readyState,sheets:sheets()});
try{new PerformanceObserver(function(list){if(shiftSent){return;}var entries=list.getEntries().filter(function(e){return !e.hadRecentInput;});if(!entries.length){return;}shiftSent=true;send("H102","First layout shift attribution",{entries:entries.slice(0,3).map(function(e){return{v:e.value,t:Math.round(e.startTime),src:(e.sources||[]).slice(0,4).map(function(s){var n=s.node;return{n:n?(n.id?"#"+n.id:n.className?"."+String(n.className).trim().replace(/\s+/g,"."):n.tagName):null,p:s.previousRect,c:s.currentRect};})};})});}).observe({type:"layout-shift",buffered:true});}catch(e){}
addEventListener("load",function(){requestAnimationFrame(function(){var resources=performance.getEntriesByType("resource").filter(function(r){return /\.css(?:\?|$)/.test(r.name)&&/styles-l|awa-head-preload-critical-home|awa-align-grid|awa-commerce-impeccable|awa-home-critical-stack/.test(r.name);}).map(function(r){return{f:r.name.split("/").pop(),end:Math.round(r.responseEnd),dur:Math.round(r.duration),transfer:r.transferSize||0};});send("H103","Load-complete CSS and geometry",{ready:document.readyState,sheets:sheets(),resources:resources,header:box(".header-wrapper-sticky"),main:box(".awa-main-header__inner.wp-header"),nav:box(".awa-nav-bar"),inner:box(".awa-nav-bar__inner")});});},{once:true});
/* #endregion */
}catch(e){}})();</script>
HTML;
        $snippet .= <<<'HTML'
<script id="awa-inline-scroll-lifecycle-probe">(function(){try{
/* #region agent log */
if(!document.body||!document.body.matches(".cms-index-index,.cms-home,.cms-homepage_ayo_home5")){return;}
var sid="ca59a1",run="scroll-lifecycle-v1",loc="DeferHomeScriptsPlugin:inline-scroll-lifecycle",sent=0,lastY=Math.round(scrollY),nativeScrollTo=window.scrollTo,nativeScroll=window.scroll,nativeIntoView=Element.prototype.scrollIntoView,nativeFocus=HTMLElement.prototype.focus;
function targetName(node){if(!node){return null;}return node.id?"#"+node.id:node.className?"."+String(node.className).trim().replace(/\s+/g,"."):node.tagName;}
function state(reason,extra){return{reason:reason,ready:document.readyState,y:Math.round(scrollY),lastY:lastY,docH:document.documentElement.scrollHeight,bodyH:document.body.scrollHeight,viewportH:innerHeight,restoration:history.scrollRestoration,hash:location.hash||"",active:targetName(document.activeElement),bodyClass:document.body.className,sticky:document.body.classList.contains("awa-header-is-sticky"),extra:extra||null};}
function send(hyp,msg,data){if(sent>=9){return;}sent+=1;var payload={sessionId:sid,runId:run,hypothesisId:hyp,location:loc,message:msg,data:data,timestamp:Date.now()};fetch("http://localhost:7788/ingest/9a5bd517-cd53-4948-bac5-5aea194478a3",{method:"POST",headers:{"Content-Type":"application/json","X-Debug-Session-Id":"ca59a1"},body:JSON.stringify(payload)}).catch(function(){});var qs="?awa_dbg=1&sid="+sid+"&run="+run+"&hyp="+hyp+"&loc="+encodeURIComponent(loc)+"&msg="+encodeURIComponent(msg)+"&ts="+Date.now()+"&transport=scroll-lifecycle&data="+encodeURIComponent(JSON.stringify(data).slice(0,1000));(new Image()).src="/b2b/account/login/"+qs;}
send("H159-H160","Scroll state at body end",state("body-end"));
window.scrollTo=function(){send("H157","window.scrollTo invoked",state("scrollTo-before",{args:Array.prototype.slice.call(arguments,0,2)}));return nativeScrollTo.apply(window,arguments);};
window.scroll=function(){send("H157","window.scroll invoked",state("scroll-before",{args:Array.prototype.slice.call(arguments,0,2)}));return nativeScroll.apply(window,arguments);};
Element.prototype.scrollIntoView=function(){send("H158","scrollIntoView invoked",state("scrollIntoView-before",{target:targetName(this)}));return nativeIntoView.apply(this,arguments);};
HTMLElement.prototype.focus=function(){var before=Math.round(scrollY),self=this,args=arguments,result=nativeFocus.apply(this,args);requestAnimationFrame(function(){var after=Math.round(scrollY);if(after!==before){send("H158","Focus changed scroll position",state("focus-after",{target:targetName(self),before:before,after:after}));}});return result;};
addEventListener("scroll",function(){var now=Math.round(scrollY);if(Math.abs(now-lastY)>4){send("H159-H161","Observed viewport scroll change",state("scroll-event",{from:lastY,to:now}));lastY=now;}},{passive:true});
document.addEventListener("DOMContentLoaded",function(){send("H159-H161","DOMContentLoaded scroll state",state("dom-content-loaded"));},{once:true});
addEventListener("pageshow",function(e){send("H159","Page show restoration state",state("pageshow",{persisted:!!e.persisted}));},{once:true});
addEventListener("load",function(){requestAnimationFrame(function(){send("H159-H161","Load-complete scroll state",state("load"));});},{once:true});
/* #endregion */
}catch(e){}})();</script>
HTML;
        $replaced = preg_replace('/<\/body>/i', $snippet . "\n</body>", $html, 1);

        return is_string($replaced) ? $replaced : $html;
    }

    private function appendDebugLine(array $payload): void
    {
        try {
            $dirPath = (string) dirname(self::DEBUG_LOG_PATH);
            if (!$this->fileDriver->isExists($dirPath)) {
                $this->fileDriver->createDirectory($dirPath, 0775);
            }

            $line = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === '') {
                return;
            }

            $this->fileDriver->filePutContents(self::DEBUG_LOG_PATH, $line . PHP_EOL, FILE_APPEND);
        } catch (FileSystemException) {
            // Observabilidade best-effort: nunca interromper renderização por log de debug.
        }
    }
}
