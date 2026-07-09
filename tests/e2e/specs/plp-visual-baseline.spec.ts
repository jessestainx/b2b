/**
 * plp-visual-baseline.spec.ts — AWA Motos
 * ==========================================
 * Baseline de REPRODUCAO/EVIDENCIA para os bugs visuais da PLP "Bagageiros"
 * (docs/visual-qa/VISUAL_FIX_PLAN.md — secao "Pagina: Categoria / PLP — Bagageiros").
 *
 * IMPORTANTE — esta spec e propositalmente um DIAGNOSTICO, nao um portao de CI:
 *  - Nao faz expect() duro sobre condicoes que sabemos estarem quebradas hoje
 *    (ver VISUAL_FIX_PLAN.md, status REPRODUCED nos itens PLP-P0-*). Um assert
 *    rigido aqui falharia sempre ate a correcao ser aplicada, o que nao e o
 *    objetivo desta tarefa (documentar, nao corrigir).
 *  - Mesmo padrao de tests/e2e/specs/header-core-interactions-p0.spec.ts:
 *    coleta evidencia estruturada (bbox, CSS computado, visibilidade, contagem)
 *    e anexa via testInfo.attach() + screenshot, sem travar o pipeline.
 *  - Quando os itens PLP-P0-* avancarem para FIXED_SOURCE, promover as
 *    verificacoes relevantes para expect() reais aqui (ou em um novo spec
 *    plp-visual-regression.spec.ts, no mesmo padrao de home-visual-regression.spec.ts).
 *
 * Rotas cobertas:
 *  - /bagageiros.html (categoria alvo do relato visual, entity_id 67)
 *  - /bauletos.html (segunda PLP para comparacao/regressao de itens globais)
 *
 * Execucao:
 *   cd tests/e2e
 *   npx playwright test specs/plp-visual-baseline.spec.ts --project=desktop-1440
 *   npx playwright test specs/plp-visual-baseline.spec.ts --project=mobile-390
 *
 * Contra staging/producao:
 *   ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
 *     npx playwright test specs/plp-visual-baseline.spec.ts --project=desktop-1440
 */
import { test, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import { dismissCookie, collectConsoleErrors, collectNetworkErrors, checkOverflow } from '../helpers/deep-audit.helpers';
import { getMultipleCSS, getBBox, isVisible } from '../helpers/header.helpers';

const ROUTES: Array<{ id: string; path: string }> = [
  { id: 'bagageiros', path: '/bagageiros.html' },
  { id: 'bauletos', path: '/bauletos.html' },
];

const CSS_PROPS = ['display', 'visibility', 'opacity', 'width', 'height', 'position', 'z-index', 'background-color'];

/** Seletores mapeados 1:1 para os itens PLP-P0-N, HEADER-P0-N e FOOTER-P0-N do VISUAL_FIX_PLAN.md. */
const SELECTORS: Record<string, string> = {
  // PLP-P0-001/002/003 — hero de categoria e espaco vazio
  categoryHero: '.awa-category-hero',
  categoryHeroBgImage: '.awa-category-hero__bg-image',
  categoryHeroTitle: '.awa-category-hero__title',
  categoryHeroCount: '.awa-category-hero__count',
  toolbarTop: '.toolbar.toolbar-products',
  // PLP-P0-004/005/006 — sidebar (filtros + widget de pedidos recentes)
  sidebarMain: '.sidebar.sidebar-main',
  sidebarAdditional: '.sidebar-additional1, .sidebar-additional',
  layeredFilterBlock: '.block.filter, .layered-ajax-filter-block',
  reorderBlock: '.block.block-reorder, .block-reorder',
  reorderAddToCartButtons: '.block-reorder .action.tocart, .block-reorder .action.primary, .block-reorder button.tocart',
  // PLP-P0-007/008/009 — product card
  productItem: '.product-item',
  productItemPrice: '.product-item .price-box, .product-item .price',
  productItemCta: '.product-item .action.primary, .product-item .tocart, .product-item .action.tocart',
  // PLP-P1-009 — paginacao
  pagination: '.toolbar .pages, .pages',
  // HEADER-P0-001/002 — observado tambem na PLP
  minicartShowcart: '.minicart-wrapper .showcart, .action.showcart.header-mini-cart',
  deptTrigger: '[data-role="awa-vertical-menu-trigger"]',
  // FOOTER-P0-001 — colunas do footer (candidato a "blocos vermelhos")
  footer: 'footer.page-footer, .page-footer',
  footerColumns: '.page-footer .row.rowFlexMargin > [class*="col-"]',
  footerTrustBar: '.awa-footer-trust-bar',
};

async function safeGoto(page: Page, path: string): Promise<number> {
  const url = targetUrl(path, 'plp-visual-baseline');
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => null);
  if (page.isClosed()) return resp?.status() ?? -1;
  await page.waitForLoadState('networkidle', { timeout: 12_000 }).catch(() => {});
  if (page.isClosed()) return resp?.status() ?? -1;
  await dismissCookie(page);
  await new Promise((r) => setTimeout(r, 500));
  return resp?.status() ?? 0;
}

test.describe('PLP baseline — reproducao/evidencia (Bagageiros e comparacao)', () => {
  for (const route of ROUTES) {
    test(`diagnostico — ${route.id}`, async ({ page }, testInfo) => {
      const consoleErrors = collectConsoleErrors(page);
      const networkErrors = collectNetworkErrors(page);

      const status = await safeGoto(page, route.path);
      if (status === 0 || status === -1) {
        test.skip(true, 'navegacao falhou ou renderer fechou a pagina — instabilidade de ambiente, nao e bug de PLP');
        return;
      }

      const overflow = await checkOverflow(page);

      const componentEvidence: Record<string, unknown> = {};
      for (const [name, selector] of Object.entries(SELECTORS)) {
        const visible = await isVisible(page, selector);
        const cssVals = await getMultipleCSS(page, selector, CSS_PROPS);
        const bbox = await getBBox(page, selector);
        const count = await page.locator(selector).count().catch(() => 0);
        componentEvidence[name] = { selector, visible, count, css: cssVals, bbox };
      }

      // PLP-P0-005/006: presenca do widget nativo sale.reorder.sidebar + botoes duplicados.
      const reorderAddToCartCount = await page.locator(SELECTORS.reorderAddToCartButtons).count().catch(() => 0);

      // PLP-P0-002: gap vertical entre o hero da categoria e a toolbar (area vazia relatada).
      const heroToToolbarGapPx = await page.evaluate(() => {
        const hero = document.querySelector('.awa-category-hero');
        const toolbar = document.querySelector('.toolbar.toolbar-products');
        if (!hero || !toolbar) return null;
        const heroRect = hero.getBoundingClientRect();
        const toolbarRect = toolbar.getBoundingClientRect();
        return Math.round(toolbarRect.top - heroRect.bottom);
      }).catch(() => null);

      // FOOTER-P0-001: background-color computado de cada coluna do footer (candidato a "blocos vermelhos").
      const footerColumnBackgrounds = await page.evaluate((sel: string) => {
        return Array.from(document.querySelectorAll<HTMLElement>(sel)).map((el, i) => ({
          index: i,
          backgroundColor: getComputedStyle(el).backgroundColor,
          className: el.className,
        }));
      }, SELECTORS.footerColumns).catch(() => []);

      // PLP-P0-009: quantos product-items nao tem price-box visivel (ausencia de preco/contexto B2B).
      const productsWithoutPrice = await page.evaluate(() => {
        const items = Array.from(document.querySelectorAll('.product-item'));
        return items.filter((item) => {
          const price = item.querySelector('.price-box, .price');
          return !price || (price as HTMLElement).offsetParent === null;
        }).length;
      }).catch(() => null);

      const badAssets = networkErrors.filter(
        (e) => (e.status === 404 || e.status === 403) && /\.(css|js)(\?|$)/i.test(e.url),
      );

      const screenshotPath = testInfo.outputPath(`plp-baseline-${route.id}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true, timeout: 10_000 }).catch(() => {});

      const evidence = {
        route: route.path,
        httpStatus: status,
        hasHorizontalOverflow: overflow.hasOverflow,
        overflowDiffPx: overflow.diff,
        heroToToolbarGapPx,
        reorderAddToCartCount,
        productsWithoutPrice,
        footerColumnBackgrounds,
        components: componentEvidence,
        badAssets,
        consoleErrors,
        pageUrl: page.url(),
      };

      await testInfo.attach(`plp-baseline-${route.id}.json`, {
        body: JSON.stringify(evidence, null, 2),
        contentType: 'application/json',
      });

      console.log(`[PLP-BASELINE] ${route.id} :: ${JSON.stringify(evidence).slice(0, 4000)}`);
    });
  }
});
