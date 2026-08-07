<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Plugin\Response;

use GrupoAwamotos\Theme\Model\Generated\CascadeAssetVersionConsts;

use GrupoAwamotos\Theme\Plugin\Response\OptimizeHeadStylesPlugin;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State as AppState;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Contratos HTML da cascata CSS (Fase 4).
 *
 * @covers \GrupoAwamotos\Theme\Plugin\Response\OptimizeHeadStylesPlugin
 */
class OptimizeHeadStylesPluginTest extends TestCase
{
    private OptimizeHeadStylesPlugin $plugin;
    private HttpRequest&MockObject $request;
    private AppState&MockObject $appState;

    protected function setUp(): void
    {
        $this->request = $this->createMock(HttpRequest::class);
        $this->appState = $this->createMock(AppState::class);
        $this->plugin = new OptimizeHeadStylesPlugin($this->request, $this->appState);
    }

    public function testStripStylesheetFragmentsRemovesLinkAndNoscript(): void
    {
        $html = <<<'HTML'
<html><head>
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-commerce-impeccable-refine.min.css"/>
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/styles-m.css"/>
<noscript><link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-commerce-impeccable-refine.min.css"/></noscript>
</head><body></body></html>
HTML;

        $out = $this->invoke('stripStylesheetFragments', $html, ['awa-commerce-impeccable-refine']);

        $this->assertStringNotContainsString('awa-commerce-impeccable-refine', $out);
        $this->assertStringContainsString('styles-m.css', $out);
        $this->assertSame(0, preg_match_all('/awa-commerce-impeccable-refine/', $out));
    }

    public function testStripStylesheetFragmentsLeavesUnrelatedAssets(): void
    {
        $html = '<link rel="stylesheet" href="/x/awa-footer-terminal-lock-v1.min.css"/>';
        $out = $this->invoke('stripStylesheetFragments', $html, ['awa-commerce-impeccable-refine']);
        $this->assertStringContainsString('awa-footer-terminal-lock-v1', $out);
    }

    public function testConsolidateM2VisualSsotKeepsExactlyOneAfterAlignGrid(): void
    {
        $html = <<<'HTML'
<html><head>
<link rel="stylesheet" href="/static/version1786042591/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-m2-visual-ssot.min.css?v=stale"/>
<link rel="stylesheet" href="/static/version1786042591/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-align-grid-terminal-2026-06-11.min.css?v=696a4f424479" data-awa-align-grid-body-terminal="1"/>
<link rel="stylesheet" href="/static/version1786042591/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-m2-visual-ssot.min.css?v=dup"/>
</head><body></body></html>
HTML;

        $out = $this->invoke('consolidateM2VisualSsotTerminal', $html, 'cms_index_index');

        $this->assertSame(
            1,
            preg_match_all('/<link\s[^>]*awa-m2-visual-ssot[^>]*\/?>/i', $out),
            'deve restar exatamente um link visual SSOT'
        );
        $this->assertMatchesRegularExpression(
            '/awa-align-grid-terminal[^>]*>\s*\n?<link[^>]*awa-m2-visual-ssot/i',
            $out
        );
        $this->assertStringContainsString('data-awa-m2-visual-ssot="1"', $out);
        $this->assertStringContainsString(CascadeAssetVersionConsts::M2_VISUAL_SSOT, $out);
    }

    public function testNormalizeAlignGridKeepsSingleCanonicalLink(): void
    {
        $canonicalQuery = \GrupoAwamotos\Theme\Model\HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;
        // O dedupe do plugin casa href === arquivo+query (não path /static/...).
        $html = <<<'HTML'
<link rel="stylesheet" href="awa-align-grid-terminal-2026-06-11.min.css?v=old-a"/>
<link rel="stylesheet" href="awa-align-grid-terminal-2026-06-11.min.css?v=old-b" data-awa-align-grid-body-terminal="1"/>
HTML;

        $out = $this->invoke('normalizeAlignGridStylesheetVersion', $html);

        $this->assertSame(
            1,
            preg_match_all(
                '/<link\s[^>]*awa-align-grid-terminal-2026-06-11\.min\.css'
                . preg_quote($canonicalQuery, '/')
                . '[^>]*\/?>/i',
                $out
            )
        );
        $this->assertStringContainsString('data-awa-align-grid-body-terminal', $out);
        $this->assertStringNotContainsString('?v=old-a', $out);
        $this->assertStringNotContainsString('?v=old-b', $out);
    }

    public function testNormalizeAlignGridRewritesStaleQueryOnStaticPaths(): void
    {
        $canonicalQuery = \GrupoAwamotos\Theme\Model\HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;
        $html = <<<'HTML'
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-align-grid-terminal-2026-06-11.min.css?v=old-a"/>
HTML;

        $out = $this->invoke('normalizeAlignGridStylesheetVersion', $html);
        $this->assertStringContainsString(
            'awa-align-grid-terminal-2026-06-11.min.css' . $canonicalQuery,
            $out
        );
        $this->assertStringNotContainsString('?v=old-a', $out);
    }

    public function testAuthStripFragmentsDoNotTargetAlignGrid(): void
    {
        $auth = $this->privateConstant('AUTH_STRIP_CSS_FRAGMENTS');
        $this->assertNotContains('awa-align-grid-terminal-2026-06-11', $auth);
        $this->assertContains('awa-commerce-impeccable-refine', $auth);
    }

    public function testHomeNeverGateExcludesAlignGridAndFooterTerminal(): void
    {
        $never = $this->privateConstant('HOME_NEVER_GATE_FRAGMENTS');
        $gate = $this->privateConstant('HOME_GATE_CSS_FRAGMENTS');

        $this->assertNotContains('awa-align-grid-terminal-2026-06-11', $never);
        $this->assertNotContains('awa-footer-terminal-lock-v1', $never);
        $this->assertContains('awa-align-grid-terminal-2026-06-11', $gate);
        $this->assertContains('awa-footer-terminal-lock-v1', $gate);
    }

    public function testHomeGateLayoutParityRemovesExistInCmsIndexIndex(): void
    {
        $layoutPath = BP . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/layout/cms_index_index.xml';
        $this->assertFileExists($layoutPath);
        $xml = (string) file_get_contents($layoutPath);
        $missing = \GrupoAwamotos\Theme\Model\HomeCssGateParity::missingLayoutRemoves($xml);
        $this->assertSame([], $missing, 'cms_index_index.xml deve espelhar LAYOUT_REMOVE_SRCS: ' . implode(', ', $missing));
    }

    public function testHomeGateActiveFragmentsAreCoveredByPluginConstant(): void
    {
        $gate = $this->privateConstant('HOME_GATE_CSS_FRAGMENTS');
        foreach (\GrupoAwamotos\Theme\Model\HomeCssGateParity::ACTIVE_GATE_FRAGMENTS as $fragment) {
            $this->assertContains(
                $fragment,
                $gate,
                'ACTIVE_GATE_FRAGMENTS deve permanecer em HOME_GATE_CSS_FRAGMENTS (safety-net): ' . $fragment
            );
        }
    }

    public function testGateHomeLargeStylesheetsMergesDiscoveredUrlsIntoSeededQueue(): void
    {
        $html = <<<'HTML'
<html><head>
<script id="awa-css-gate-queue" type="application/json">["/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-align-grid-terminal-2026-06-11.min.css?v=seed"]</script>
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-commerce-impeccable-refine.min.css?v=4a18e0d41a1f"/>
<link rel="stylesheet" href="https://awamotos.com/media/rokanthemes/theme_option/custom_default.css?v=1"/>
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/GrupoAwamotos_B2B/css/product/login-to-cart.min.css"/>
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/GrupoAwamotos_B2B/css/header/status-panel.min.css?v=x"/>
<link rel="stylesheet" href="/static/version1/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-b2b-status-panel.min.css?v=x"/>
</head><body></body></html>
HTML;

        $out = $this->invoke('gateHomeLargeStylesheets', $html);
        $this->assertSame(
            1,
            preg_match(
                '/<script id="awa-css-gate-queue"[^>]*>\[(.*?)\]<\/script>/s',
                $out,
                $queueMatch
            )
        );
        $decoded = json_decode('[' . $queueMatch[1] . ']', true);
        $this->assertIsArray($decoded);
        $missing = \GrupoAwamotos\Theme\Model\HomeCssGateParity::missingActiveGateFragments($decoded);
        $this->assertSame([], $missing, 'fila gate deve conter ACTIVE_GATE_FRAGMENTS: ' . implode(', ', $missing));
    }

    /**
     * @param mixed ...$args
     */
    private function invoke(string $method, ...$args): string
    {
        $ref = new ReflectionMethod(OptimizeHeadStylesPlugin::class, $method);
        $ref->setAccessible(true);
        $result = $ref->invoke($this->plugin, ...$args);
        $this->assertIsString($result);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function privateConstant(string $name): array
    {
        $ref = new ReflectionClass(OptimizeHeadStylesPlugin::class);
        $const = $ref->getReflectionConstant($name);
        $this->assertNotFalse($const);
        $value = $const->getValue();
        $this->assertIsArray($value);

        /** @var list<string> $value */
        return $value;
    }
}
