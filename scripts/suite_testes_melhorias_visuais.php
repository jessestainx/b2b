#!/usr/bin/env php
<?php
/**
 * Suite de Testes Completa - Melhorias Visuais
 * Grupo Awamotos - Magento 2.4.8-p3
 * 
 * Executa 25 testes automatizados para validar:
 * - URLs e redirects
 * - Assets estáticos (CSS/JS)
 * - Módulos customizados
 * - SEO e Schema.org
 * - Micro-interactions
 */

define('BP', dirname(__DIR__));
require BP . '/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

class VisualImprovementsTestSuite
{
    private $objectManager;
    private $results = [];
    private $baseUrl;
    
    public function __construct($objectManager)
    {
        $this->objectManager = $objectManager;
        $storeManager = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $this->baseUrl = rtrim($storeManager->getStore()->getBaseUrl(), '/');
    }
    
    public function run()
    {
        echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║  SUITE DE TESTES - MELHORIAS VISUAIS (v2.0)                  ║\n";
        echo "║  Grupo Awamotos - Magento 2.4.8-p3                           ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
        
        // Categoria 1: URLs & Redirects (5 testes)
        echo "━━━ 1. URLs & REDIRECTS (5 testes) ━━━\n";
        $this->testHomepage();
        $this->testProductPage();
        $this->testCategoryPage();
        $this->testSitemap();
        $this->testRobotsTxt();
        
        // Categoria 2: Assets Estáticos (5 testes)
        echo "\n━━━ 2. ASSETS ESTÁTICOS (5 testes) ━━━\n";
        $this->testMicroInteractionsJS();
        $this->testMicroInteractionsCSS();
        $this->testThemeCSS();
        $this->testRequireJS();
        $this->testStaticVersion();
        
        // Categoria 3: Módulos GrupoAwamotos (8 testes)
        echo "\n━━━ 3. MÓDULOS GRUPOAWAMOTOS (8 testes) ━━━\n";
        $this->testSchemaOrgModule();
        $this->testFitmentModule();
        $this->testVlibrasModule();
        $this->testBrazilCustomerModule();
        $this->testStoreSetupModule();
        $this->testSmtpFixModule();
        $this->testSocialProofModule();
        $this->testNewsletterModule();
        
        // Categoria 4: SEO & Schema.org (5 testes)
        echo "\n━━━ 4. SEO & SCHEMA.ORG (5 testes) ━━━\n";
        $this->testProductSchema();
        $this->testOrganizationSchema();
        $this->testBreadcrumbSchema();
        $this->testMetaTags();
        $this->testOpenGraph();
        
        // Categoria 5: Performance (2 testes)
        echo "\n━━━ 5. PERFORMANCE (2 testes) ━━━\n";
        $this->testPageLoadTime();
        $this->testAssetMinification();
        
        // Sumário
        $this->printSummary();
    }
    
    // ===== CATEGORIA 1: URLs & REDIRECTS =====
    
    private function testHomepage()
    {
        $url = $this->baseUrl . '/';
        $response = $this->curlGet($url);
        $this->assert(
            'Homepage acessível (HTTP 200)',
            $response['http_code'] === 200 && strpos($response['body'], '<html') !== false,
            "HTTP {$response['http_code']}"
        );
    }
    
    private function testProductPage()
    {
        // Busca primeiro produto do catálogo
        $productCollection = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
        $productCollection->setPageSize(1)->load();
        $product = $productCollection->getFirstItem();
        
        if ($product->getId()) {
            $url = $product->getProductUrl();
            $response = $this->curlGet($url);
            $this->assert(
                'Product page acessível',
                $response['http_code'] === 200,
                "HTTP {$response['http_code']} - {$product->getSku()}"
            );
        } else {
            $this->assert('Product page acessível', false, 'Nenhum produto no catálogo');
        }
    }
    
    private function testCategoryPage()
    {
        $categoryCollection = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
        $categoryCollection->addAttributeToFilter('level', ['gt' => 1])
                           ->setPageSize(1)
                           ->load();
        $category = $categoryCollection->getFirstItem();
        
        if ($category->getId()) {
            $url = $this->baseUrl . '/' . $category->getUrlPath();
            $response = $this->curlGet($url);
            $this->assert(
                'Category page acessível',
                $response['http_code'] === 200,
                "HTTP {$response['http_code']} - {$category->getName()}"
            );
        } else {
            $this->assert('Category page acessível', false, 'Nenhuma categoria encontrada');
        }
    }
    
    private function testSitemap()
    {
        $url = $this->baseUrl . '/sitemap.xml';
        $response = $this->curlGet($url);
        $hasUrls = preg_match_all('/<url>/', $response['body'], $matches);
        $this->assert(
            'Sitemap XML gerado',
            $response['http_code'] === 200 && $hasUrls > 0,
            "{$hasUrls} URLs encontradas"
        );
    }
    
    private function testRobotsTxt()
    {
        $url = $this->baseUrl . '/robots.txt';
        $response = $this->curlGet($url);
        $this->assert(
            'robots.txt configurado',
            $response['http_code'] === 200 && strpos($response['body'], 'User-agent') !== false,
            "HTTP {$response['http_code']}"
        );
    }
    
    // ===== CATEGORIA 2: ASSETS ESTÁTICOS =====
    
    private function testMicroInteractionsJS()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasJS = preg_match('/micro-interactions\.min\.js/', $html) || preg_match('/_cache\/merged\/.*\.js/', $html);
        $this->assert(
            'Micro-interactions JS carregado',
            $hasJS,
            $hasJS ? 'Presente no HTML (ou merged)' : 'Não encontrado'
        );
    }
    
    private function testMicroInteractionsCSS()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasCSS = preg_match('/micro-interactions\.min\.css/', $html) || preg_match('/_cache\/merged\/.*\.css/', $html);
        $this->assert(
            'Micro-interactions CSS carregado',
            $hasCSS,
            $hasCSS ? 'Presente no HTML (ou merged)' : 'Não encontrado'
        );
    }
    
    private function testThemeCSS()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasThemeCSS = preg_match('/styles-l\.css/', $html) || preg_match('/_cache\/merged\/.*\.css/', $html);
        $this->assert(
            'Theme CSS compilado',
            $hasThemeCSS,
            $hasThemeCSS ? 'styles-l.css presente (ou merged)' : 'CSS não encontrado'
        );
    }
    
    private function testRequireJS()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasRequireJS = preg_match('/requirejs\/require\.js/', $html) || preg_match('/_cache\/merged\/.*\.js/', $html);
        $this->assert(
            'RequireJS carregado',
            $hasRequireJS,
            $hasRequireJS ? 'require.js presente (ou merged)' : 'RequireJS não encontrado'
        );
    }
    
    private function testStaticVersion()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasVersion = preg_match('/static\/version(\d+)\//', $html);
        $this->assert(
            'Static versioning ativo',
            $hasVersion === 1,
            $hasVersion ? 'Versioning habilitado' : 'Sem versioning'
        );
    }
    
    // ===== CATEGORIA 3: MÓDULOS GRUPOAWAMOTOS =====
    
    private function testSchemaOrgModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_SchemaOrg');
        
        if ($isEnabled) {
            $html = $this->curlGet($this->baseUrl . '/')['body'];
            $hasSchema = preg_match('/<script type="application\/ld\+json">/', $html);
            $this->assert(
                'SchemaOrg module ativo',
                $hasSchema === 1,
                $hasSchema ? 'JSON-LD presente' : 'Schema não encontrado'
            );
        } else {
            $this->assert('SchemaOrg module ativo', false, 'Módulo desabilitado');
        }
    }
    
    private function testFitmentModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_Fitment');
        $this->assert(
            'Fitment module removido',
            !$isEnabled,
            $isEnabled ? 'Ainda habilitado' : 'Removido/desabilitado'
        );
    }
    
    private function testVlibrasModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_Vlibras');
        $this->assert(
            'VLibras module ativo',
            $isEnabled,
            $isEnabled ? 'Habilitado' : 'Desabilitado'
        );
    }
    
    private function testBrazilCustomerModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_BrazilCustomer');
        $this->assert(
            'BrazilCustomer module ativo',
            $isEnabled,
            $isEnabled ? 'Habilitado' : 'Desabilitado'
        );
    }
    
    private function testStoreSetupModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_StoreSetup');
        $this->assert(
            'StoreSetup module ativo',
            $isEnabled,
            $isEnabled ? 'Habilitado' : 'Desabilitado'
        );
    }
    
    private function testSmtpFixModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_SmtpFix');
        $this->assert(
            'SmtpFix module ativo',
            $isEnabled,
            $isEnabled ? 'Habilitado' : 'Desabilitado'
        );
    }
    
    private function testSocialProofModule()
    {
        $moduleManager = $this->objectManager->get(\Magento\Framework\Module\Manager::class);
        $isEnabled = $moduleManager->isEnabled('GrupoAwamotos_SocialProof');
        
        if ($isEnabled) {
            // Verifica se template existe
            $templatePath = BP . '/app/code/GrupoAwamotos/SocialProof/view/frontend/templates/product/social-proof.phtml';
            $templateExists = file_exists($templatePath);
            $this->assert(
                'SocialProof module funcional',
                $templateExists,
                $templateExists ? 'Template presente' : 'Template não encontrado'
            );
        } else {
            $this->assert('SocialProof module funcional', false, 'Módulo desabilitado');
        }
    }
    
    private function testNewsletterModule()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasNewsletter = preg_match('/newsletter-popup|newsletter-form|awa-newsletter|nl-form/', $html);
        $this->assert(
            'Newsletter popup ativo',
            $hasNewsletter === 1,
            $hasNewsletter ? 'Popup presente' : 'Popup não encontrado'
        );
    }
    
    // ===== CATEGORIA 4: SEO & SCHEMA.ORG =====
    
    private function testProductSchema()
    {
        $productCollection = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Product\Collection::class);
        $productCollection->setPageSize(1)->load();
        $product = $productCollection->getFirstItem();
        
        if ($product->getId()) {
            $url = $product->getProductUrl();
            $html = $this->curlGet($url)['body'];
            $hasProductSchema = preg_match('/"@type":\s*"Product"/', $html);
            $this->assert(
                'Product schema presente',
                $hasProductSchema === 1,
                $hasProductSchema ? 'JSON-LD Product encontrado' : 'Schema ausente'
            );
        } else {
            $this->assert('Product schema presente', false, 'Sem produtos para testar');
        }
    }
    
    private function testOrganizationSchema()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasOrgSchema = preg_match('/"@type":\s*"(Organization|LocalBusiness)"/', $html);
        $this->assert(
            'Organization schema presente',
            $hasOrgSchema === 1,
            $hasOrgSchema ? 'JSON-LD Organization encontrado' : 'Schema ausente'
        );
    }
    
    private function testBreadcrumbSchema()
    {
        $categoryCollection = $this->objectManager->create(\Magento\Catalog\Model\ResourceModel\Category\Collection::class);
        $categoryCollection->addAttributeToFilter('level', ['gt' => 1])
                           ->setPageSize(1)
                           ->load();
        $category = $categoryCollection->getFirstItem();
        
        if ($category->getId()) {
            $url = $category->getUrl();
            $html = $this->curlGet($url)['body'];
            $hasBreadcrumb = preg_match('/"@type":\s*"BreadcrumbList"/', $html);
            $this->assert(
                'Breadcrumb schema presente',
                $hasBreadcrumb === 1,
                $hasBreadcrumb ? 'JSON-LD Breadcrumb encontrado' : 'Schema ausente'
            );
        } else {
            $this->assert('Breadcrumb schema presente', false, 'Sem categorias para testar');
        }
    }
    
    private function testMetaTags()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasMeta = preg_match('/<meta name="description"/', $html) && 
                   preg_match('/<title>/', $html);
        $this->assert(
            'Meta tags configuradas',
            $hasMeta,
            $hasMeta ? 'Title + Description presentes' : 'Meta tags ausentes'
        );
    }
    
    private function testOpenGraph()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasOG = preg_match('/<meta property="og:/', $html);
        $this->assert(
            'Open Graph tags presentes',
            $hasOG === 1,
            $hasOG ? 'og: tags encontradas' : 'OG tags ausentes'
        );
    }
    
    // ===== CATEGORIA 5: PERFORMANCE =====
    
    private function testPageLoadTime()
    {
        $start = microtime(true);
        $this->curlGet($this->baseUrl . '/');
        $loadTime = microtime(true) - $start;
        
        $this->assert(
            'Page load time <3s',
            $loadTime < 3.0,
            sprintf('%.2fs', $loadTime)
        );
    }
    
    private function testAssetMinification()
    {
        $html = $this->curlGet($this->baseUrl . '/')['body'];
        $hasMinJS = preg_match('/\.min\.js/', $html) || preg_match('/_cache\/merged\/.*\.js/', $html);
        $hasMinCSS = preg_match('/\.min\.css/', $html) || preg_match('/_cache\/merged\/.*\.css/', $html);
        
        $this->assert(
            'Assets minificados',
            $hasMinJS && $hasMinCSS,
            ($hasMinJS && $hasMinCSS) ? 'JS + CSS minificados (ou merged)' : 'Minificação incompleta'
        );
    }
    
    // ===== HELPERS =====
    
    private function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (TestSuite/2.0)'
        ]);
        
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ['body' => $body, 'http_code' => $httpCode];
    }
    
    private function assert($testName, $condition, $details = '')
    {
        $status = $condition ? '✅ PASS' : '❌ FAIL';
        $this->results[] = ['name' => $testName, 'passed' => $condition];
        
        $detailsStr = $details ? " ({$details})" : '';
        printf("%-50s %s%s\n", $testName, $status, $detailsStr);
    }
    
    private function printSummary()
    {
        $total = count($this->results);
        $passed = array_filter($this->results, fn($r) => $r['passed']);
        $passedCount = count($passed);
        $failedCount = $total - $passedCount;
        $percentage = $total > 0 ? round(($passedCount / $total) * 100, 1) : 0;
        
        echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║  SUMÁRIO DOS TESTES                                          ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n\n";
        
        printf("Total de testes:     %d\n", $total);
        printf("✅ Passaram:          %d (%.1f%%)\n", $passedCount, $percentage);
        printf("❌ Falharam:          %d (%.1f%%)\n\n", $failedCount, 100 - $percentage);
        
        if ($failedCount > 0) {
            echo "━━━ TESTES FALHADOS ━━━\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "  ❌ {$result['name']}\n";
                }
            }
            echo "\n";
        }
        
        // Status colorido
        if ($percentage >= 90) {
            echo "🎉 Status: EXCELENTE (≥90%)\n";
        } elseif ($percentage >= 75) {
            echo "✅ Status: BOM (≥75%)\n";
        } elseif ($percentage >= 60) {
            echo "⚠️  Status: ACEITÁVEL (≥60%)\n";
        } else {
            echo "❌ Status: CRÍTICO (<60%)\n";
        }
        
        echo "\n";
    }
}

// Executa suite
$suite = new VisualImprovementsTestSuite($objectManager);
$suite->run();
