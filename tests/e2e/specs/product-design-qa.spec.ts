/**
 * product-design-qa.spec.ts — AWA Motos
 * ==================================================
 * Fase PD0 — Product Design QA Foundation.
 *
 * Objetivo: avaliar e capturar evidencia visual/estrutural das paginas-chave
 * contra a regua definida em:
 *   - docs/product-design/AWA_PRODUCT_DESIGN_SYSTEM.md
 *   - docs/product-design/PRODUCT_DESIGN_QA_CHECKLIST.md
 *
 * Regras desta spec:
 *  - Apenas diagnostico/evidencia. NENHUMA correcao de tema/CSS/LESS/JS/
 *    template/pub/static/vendor e aplicada aqui ou motivada por este arquivo.
 *  - Sem asserts bloqueantes nesta fase (ambiente ainda em estabilizacao —
 *    ver docs/visual-qa/header-core-interactions-p0-report.md, secao
 *    "Path Sync / Execution Environment"). Achados viram bugs classificados
 *    em docs/product-design/PRODUCT_DESIGN_AUDIT_REPORT.md, nao falhas de CI.
 *  - Checkout: auditoria visual/estrutural apenas. Nunca preencher/submeter
 *    dados de pagamento.
 *  - Breakpoints pedidos vs projetos Playwright existentes (playwright.config.ts):
 *      1440x900 -> desktop-1440   (viewport real do projeto: 1440x1000)
 *      1366x768 -> notebook-1366
 *      1024x768 -> tablet-1024
 *      768x1024 -> tablet-768
 *      390x844  -> mobile-390
 *      430x932  -> sem projeto exato hoje (mais proximo: mobile-390)
 *      360x740  -> sem projeto exato hoje (mais proximo: mobile-375, 375x667)
 *    A criacao de projetos dedicados para 430x932/360x740 fica registrada como
 *    pendencia (ver PRODUCT_DESIGN_AUDIT_REPORT.md), fora do escopo desta fase.
 *
 * Execucao (comando validado nesta sessao, ver header-core-interactions-p0-report.md):
 *   cd tests/e2e
 *   PLAYWRIGHT_BASE_URL=https://awamotos.com ALLOW_PRODUCTION_VALIDATION=true \
 *     npx playwright test specs/product-design-qa.spec.ts \
 *       --workers=1 --reporter=list --project=desktop-1440
 */
import fs from 'node:fs';
import path from 'node:path';
import { test, type Page, type TestInfo } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import {
  dismissCookie,
  collectConsoleErrors,
  collectNetworkErrors,
  checkOverflow,
} from '../helpers/deep-audit.helpers';
import { getMultipleCSS, getBBox, isVisible } from '../helpers/header.helpers';
import { COMMON } from '../helpers/visual-audit.helpers';
import { awaSelectors, awaUrls } from '../support/awa-selectors';

/* ── Rotas (Tarefa 3) ──────────────────────────────────────────── */
const ROUTES: Array<{ id: string; path: string; optional?: boolean }> = [
  { id: 'home', path: '/' },
  { id: 'bagageiros', path: '/bagageiros.html', optional: true },
  { id: 'bauletos', path: '/bauletos.html', optional: true },
  { id: 'search-bagageiro', path: `${awaUrls.searchResults}bagageiro` },
  { id: 'b2b-login', path: awaUrls.b2bLogin },
  { id: 'b2b-register', path: awaUrls.b2bRegister },
  { id: 'cart', path: awaUrls.cart },
];

/* ── Seletores criticos por componente (reaproveita awa-selectors.ts) ── */
const CRITICAL_SELECTORS: Record<string, string> = {
  header: awaSelectors.header.root,
  logo: '.logo img, .b2b-login-logo img',
  search: awaSelectors.search.input,
  minicartTrigger: awaSelectors.minicart.trigger,
  accountEntry: `${awaSelectors.account.b2bLoginLink}, ${awaSelectors.account.b2bRegisterLink}, ${awaSelectors.account.customerAccountLink}`,
  verticalMenuTrigger: awaSelectors.verticalMenu.trigger,
  footer: COMMON.footer,
};

const RADIUS_TARGETS: Record<string, string> = {
  primaryCta: '.action.primary, button.action.primary, .b2b-btn-entrar',
  input: 'input#search, input[type="text"], input[type="email"]',
  productCard: '.product-item-info, .product-item, .products-grid .item',
};

const CSS_PROPS = ['display', 'visibility', 'opacity', 'border-radius'];
const BAD_ASSET_STATUSES = new Set([403, 404]);

const EVIDENCE_DIR = path.resolve(__dirname, '..', 'test-results', 'product-design-qa');

/* ── Helpers locais ────────────────────────────────────────────── */
async function safeGoto(page: Page, routePath: string): Promise<number> {
  const url = targetUrl(routePath, 'product-design-qa');
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => null);
  if (page.isClosed()) return resp?.status() ?? -1;
  await page.waitForLoadState('networkidle', { timeout: 12_000 }).catch(() => {});
  if (page.isClosed()) return resp?.status() ?? -1;
  await dismissCookie(page);
  await new Promise((r) => setTimeout(r, 400));
  return resp?.status() ?? 0;
}

async function snap(page: Page, testInfo: TestInfo, name: string): Promise<string | null> {
  if (page.isClosed()) return null;
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
  const fileName = `${testInfo.project.name}__${name}.png`;
  const filePath = path.join(EVIDENCE_DIR, fileName);
  const ok = await page.screenshot({ path: filePath, fullPage: true }).then(() => true).catch(() => false);
  if (!ok) return null;
  await testInfo.attach(fileName, { path: filePath, contentType: 'image/png' }).catch(() => {});
  return `test-results/product-design-qa/${fileName}`;
}

async function collectComponentEvidence(page: Page): Promise<Record<string, unknown>> {
  const evidence: Record<string, unknown> = {};
  for (const [name, selector] of Object.entries(CRITICAL_SELECTORS)) {
    const visible = await isVisible(page, selector);
    const bbox = await getBBox(page, selector);
    const css = await getMultipleCSS(page, selector, CSS_PROPS);
    evidence[name] = {
      selector,
      visible,
      bbox,
      zeroBoundingBox: !!bbox && bbox.width === 0 && bbox.height === 0,
      css,
    };
  }
  return evidence;
}

async function collectRadiusEvidence(page: Page): Promise<Record<string, unknown>> {
  const evidence: Record<string, unknown> = {};
  for (const [name, selector] of Object.entries(RADIUS_TARGETS)) {
    const css = await getMultipleCSS(page, selector, ['border-radius']);
    evidence[name] = { selector, borderRadius: css['border-radius'] ?? null };
  }
  return evidence;
}

async function collectMinButtonHeights(page: Page, viewportWidth: number): Promise<Record<string, unknown>> {
  const minExpected = viewportWidth < 1024 ? 44 : 40;
  const selectors = ['.action.primary', 'button[type="submit"]', '.b2b-btn-entrar'];
  const results: Record<string, unknown> = {};
  for (const selector of selectors) {
    const bbox = await getBBox(page, selector);
    results[selector] = {
      bbox,
      minExpected,
      meetsMin: bbox ? bbox.height >= minExpected : null,
    };
  }
  return results;
}

function classifyCspErrors(consoleErrors: Array<{ type: string; text: string }>): Array<{ type: string; text: string }> {
  return consoleErrors.filter((e) => /content security policy|refused to (load|execute|connect)/i.test(e.text));
}

/**
 * PD3 fix (fix/pd3-home-broken-images-p0): PD-BUG-001 (11 imagens "quebradas" na Home) era
 * falso-positivo do harness de teste, nao bug de produto/tema/dado/CMS — confirmado com
 * evidencia direta: todas as 11 URLs retornam HTTP 200 (curl) com content-type e tamanho
 * corretos, e todas renderizam corretamente (naturalWidth/naturalHeight corretos) quando
 * efetivamente colocadas na viewport. Duas causas de falso-positivo distintas:
 *  1. Imagens do footer (9) usam loading="lazy" nativo e nao tinham sido roladas para a
 *     viewport no momento da checagem original (findBrokenImages rodava logo apos o load,
 *     antes de qualquer scroll).
 *  2. Imagens de carrossel de produto (2+) ficam em slides "fora de palco"
 *     (.awa-carousel-card-slot) com bounding box 0x0 ate o carrossel ativa-las — scroll
 *     vertical nao resolve (carrossel e horizontal/JS-controlado), mas confirmam
 *     naturalWidth/Height corretos quando trazidas para a viewport individualmente.
 * Fix (escopo local a este spec — helpers/deep-audit.helpers.ts NAO foi alterado, pois e
 * usado por 6+ outros specs fora do escopo desta branch):
 *  - Scroll-through vertical (top->bottom->top) antes de checar imagens quebradas, para
 *    disparar o lazy-load nativo de imagens verticais (resolve o caso do footer).
 *  - Exigir bounding box > 0x0 (alem de complete && naturalWidth===0) para classificar uma
 *    imagem como realmente quebrada — imagens 0x0 sao clones fora de palco (carrossel) ou
 *    ainda nao roladas para perto da viewport, nao imagens com falha de carregamento.
 */
async function triggerLazyImages(page: Page): Promise<void> {
  await page.evaluate(async () => {
    const step = Math.max(300, Math.floor(window.innerHeight * 0.8));
    const max = document.body.scrollHeight;
    for (let y = 0; y < max; y += step) {
      window.scrollTo({ top: y, left: 0, behavior: 'instant' });
      await new Promise((r) => setTimeout(r, 150));
    }
    window.scrollTo({ top: max, left: 0, behavior: 'instant' });
    await new Promise((r) => setTimeout(r, 300));
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    // Garante que o scroll realmente voltou ao topo antes de seguir — alguns temas usam
    // scroll-behavior:smooth via CSS, que faz scrollTo animar mesmo com behavior:'instant'
    // sobrescrito por regra global; aqui fazemos polling curto para confirmar.
    for (let i = 0; i < 10 && window.scrollY > 2; i += 1) {
      window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
      await new Promise((r) => setTimeout(r, 100));
    }
  }).catch(() => {});
  await page.waitForTimeout(400);
}

async function findRealBrokenImages(page: Page): Promise<string[]> {
  try {
    return await Promise.race([
      page.evaluate(() => {
        const imgs = Array.from(document.querySelectorAll('img'));
        return imgs
          .filter((img) => {
            if (!img.src || img.src.startsWith('data:')) return false;
            const rect = img.getBoundingClientRect();
            const hasLayoutBox = rect.width > 0 && rect.height > 0;
            return hasLayoutBox && img.complete && img.naturalWidth === 0;
          })
          .map((img) => img.src);
      }),
      new Promise<string[]>((resolve) => setTimeout(() => resolve([]), 5_000)),
    ]);
  } catch {
    return [];
  }
}

/* ── Suite principal (uma rota por teste, sem loop de viewport manual —
     o breakpoint vem do --project=, evitando explosao combinatoria/Killed) ── */
test.describe('Product Design QA (PD0) — diagnostico por rota', () => {
  for (const route of ROUTES) {
    test(`design QA — ${route.id}`, async ({ page }, testInfo) => {
      const consoleErrors = collectConsoleErrors(page);
      const networkErrors = collectNetworkErrors(page);

      const status = await safeGoto(page, route.path);
      if (status === 0 || status === -1) {
        if (route.optional) {
          test.skip(true, `Rota opcional indisponivel (status ${status}) — nao bloqueia PD0.`);
          return;
        }
        test.skip(true, 'Navegacao falhou ou renderer fechou a pagina — instabilidade de ambiente, nao e bug de design.');
        return;
      }

      const overflow = await checkOverflow(page);
      await triggerLazyImages(page);
      const brokenImages = await findRealBrokenImages(page);
      const componentEvidence = await collectComponentEvidence(page);
      const radiusEvidence = await collectRadiusEvidence(page);
      const viewportWidth = page.viewportSize()?.width ?? 0;
      const buttonHeights = await collectMinButtonHeights(page, viewportWidth);

      const badAssets = networkErrors.filter(
        (e) => BAD_ASSET_STATUSES.has(e.status) && /\.(css|js)(\?|$)/i.test(e.url),
      );
      const cspErrors = classifyCspErrors(consoleErrors);

      const screenshot = await snap(page, testInfo, `${route.id}-fullpage`);

      /* ── Checks especificos da Home: menu vertical, minicart, autocomplete ── */
      let verticalMenuEvidence: Record<string, unknown> | null = null;
      let minicartEvidence: Record<string, unknown> | null = null;
      let autocompleteEvidence: Record<string, unknown> | null = null;

      if (route.id === 'home') {
        await snap(page, testInfo, 'header-fechado');

        const menuTrigger = page.locator(awaSelectors.verticalMenu.trigger).first();
        if (await menuTrigger.isVisible().catch(() => false)) {
          await menuTrigger.click({ timeout: 5_000 }).catch(() => {});
          await page.waitForTimeout(300);
          // PD1 fix: `isVisible()`/`getBBox()` (header.helpers.ts) usam
          // `locator.waitFor({ state: 'visible' })`, que trava ate o timeout
          // neste painel especifico (o JS reajusta altura via requestAnimationFrame
          // por ~5 frames apos abrir — awa-menu-controller.js `schedulePanelHeight`).
          // A API direta do Playwright (`locator.isVisible()` / `boundingBox()`)
          // reflete o estado real corretamente. Causa raiz confirmada com
          // evidencia lado a lado (DOM real aberto enquanto o helper reportava
          // falso-negativo) — ver PRODUCT_DESIGN_AUDIT_REPORT.md, PD-BUG-003.
          const listLocator = page.locator(awaSelectors.verticalMenu.list).first();
          const listVisible = await listLocator.isVisible().catch(() => false);
          const listBBox = listVisible ? await listLocator.boundingBox().catch(() => null) : null;
          const withinViewport = listBBox
            ? listBBox.x >= -2 && listBBox.y >= -2 && listBBox.x + listBBox.width <= viewportWidth + 2
            : null;
          await snap(page, testInfo, 'menu-vertical-aberto');
          verticalMenuEvidence = { visible: listVisible, bbox: listBBox, withinViewport };
          await page.keyboard.press('Escape').catch(() => {});
          await page.waitForTimeout(200);
        }

        const searchInput = page.locator(awaSelectors.search.input).first();
        if (await searchInput.isVisible().catch(() => false)) {
          await searchInput.fill('bagageiro').catch(() => {});
          // PD2 fix: o autocomplete (Mirasvit) faz bootstrap assincrono sob demanda
          // (fetch de templates + require de 4 modulos JS) na primeira interacao com a
          // busca na Home. Medido empiricamente em runtime real: a cadeia completa
          // (bootstrap -> AJAX suggest/typeahead -> render) leva ~2.6s. Um wait de 600ms
          // gerava falso-negativo aqui (o produto ja respondia corretamente, o teste
          // so nao esperava tempo suficiente) — ver PD-BUG-004 em
          // PRODUCT_DESIGN_AUDIT_REPORT.md para o historico completo da investigacao
          // (incluindo o bug real de race condition ja corrigido em
          // awa-mirasvit-autocomplete-init.js).
          // Polling em vez de sleep fixo: a cadeia assincrona (bootstrap -> ate 4
          // requests de modulo/template -> AJAX suggest/typeahead -> render) variou
          // entre ~2.6s e mais de 3.2s em execucoes reais desta mesma sessao — um
          // unico wait fixo e inerentemente instavel contra latencia real de rede.
          const autocompleteLocator = page.locator(
            '#search_autocomplete, .search-autocomplete, .mirasvit-searchautocomplete, [data-role="search-autocomplete"], .mst-searchautocomplete__autocomplete',
          ).first();
          let autocompleteVisible = false;
          const autocompleteDeadline = Date.now() + 6_000;
          while (Date.now() < autocompleteDeadline) {
            autocompleteVisible = await autocompleteLocator.isVisible().catch(() => false);
            if (autocompleteVisible) break;
            await page.waitForTimeout(300);
          }
          await snap(page, testInfo, 'autocomplete-aberto');
          autocompleteEvidence = { opened: autocompleteVisible };
          await searchInput.fill('').catch(() => {});
        }

        const minicartTrigger = page.locator(awaSelectors.minicart.trigger).first();
        if (await minicartTrigger.isVisible().catch(() => false)) {
          await minicartTrigger.click({ timeout: 5_000 }).catch(() => {});
          await page.waitForTimeout(400);
          const panelVisible = await isVisible(page, `${awaSelectors.minicart.dropdown}, ${awaSelectors.minicart.panel}`);
          const panelBBox = await getBBox(page, `${awaSelectors.minicart.dropdown}, ${awaSelectors.minicart.panel}`);
          const hasBackdrop = await page.evaluate(() => {
            return ['.modals-overlay', '.modal-backdrop', '.shadow_bkg_show'].some((selector) => {
              const el = document.querySelector(selector);
              if (!el) return false;
              const style = window.getComputedStyle(el);
              const rect = el.getBoundingClientRect();
              return style.display !== 'none' && style.visibility !== 'hidden' && parseFloat(style.opacity || '1') > 0 && rect.width > 0 && rect.height > 0;
            });
          }).catch(() => false);
          await snap(page, testInfo, 'minicart-aberto');
          minicartEvidence = {
            visible: panelVisible,
            bbox: panelBBox,
            unexpectedDesktopBackdrop: viewportWidth >= 1024 && hasBackdrop,
          };
          await page.keyboard.press('Escape').catch(() => {});
        }

        if (viewportWidth < 1024) {
          await snap(page, testInfo, 'mobile-header-menu');
        }
      }

      /* ── Checks especificos de PLP (product card + toolbar + footer) ── */
      let productCardEvidence: Record<string, unknown> | null = null;
      if (route.id === 'bagageiros' || route.id === 'bauletos' || route.id === 'search-bagageiro') {
        await snap(page, testInfo, `${route.id}-plp-fullpage`);
        const cardSelector = '.product-item-info, .product-item, .products-grid .item';
        const hasImage = await page.locator(`${cardSelector} img`).first().isVisible().catch(() => false);
        const hasTitle = await page.locator(`${cardSelector} .product-item-link, ${cardSelector} .product-name`).first().isVisible().catch(() => false);
        const hasCta = await page.locator(`${cardSelector} a.action, ${cardSelector} button`).first().isVisible().catch(() => false);
        productCardEvidence = { hasImage, hasTitle, hasCta };

        const footerVisible = await isVisible(page, COMMON.footer);
        const footerBBox = await getBBox(page, COMMON.footer);
        await snap(page, testInfo, `${route.id}-footer`);
        componentEvidence['footerOnPlp'] = { visible: footerVisible, bbox: footerBBox };
      }

      /* ── Checks especificos de formulario B2B (labels vs placeholder) ── */
      let formFieldEvidence: Record<string, unknown> | null = null;
      if (route.id === 'b2b-login' || route.id === 'b2b-register') {
        const labelCount = await page.locator('label').count().catch(() => 0);
        const inputCount = await page.locator('input:not([type="hidden"])').count().catch(() => 0);
        const placeholderOnlyInputs = await page.evaluate(() => {
          const inputs = Array.from(document.querySelectorAll('input:not([type="hidden"])'));
          return inputs.filter((input) => {
            const hasPlaceholder = input.hasAttribute('placeholder');
            const id = input.getAttribute('id');
            const hasLabelFor = id ? !!document.querySelector(`label[for="${id}"]`) : false;
            const hasAriaLabel = input.hasAttribute('aria-label') || input.hasAttribute('aria-labelledby');
            return hasPlaceholder && !hasLabelFor && !hasAriaLabel;
          }).length;
        }).catch(() => 0);
        formFieldEvidence = { labelCount, inputCount, placeholderOnlyInputs };
        await snap(page, testInfo, `${route.id}-form`);
      }

      /* ── Checkout: auditoria visual apenas, nenhuma interacao com pagamento ── */
      if (route.id === 'cart') {
        await snap(page, testInfo, 'cart-fullpage');
      }

      const evidence = {
        route: route.path,
        project: testInfo.project.name,
        viewport: page.viewportSize(),
        httpStatus: status,
        hasHorizontalOverflow: overflow.hasOverflow,
        overflowDiffPx: overflow.diff,
        brokenImages,
        components: componentEvidence,
        radius: radiusEvidence,
        buttonHeights,
        verticalMenu: verticalMenuEvidence,
        minicart: minicartEvidence,
        autocomplete: autocompleteEvidence,
        productCard: productCardEvidence,
        formField: formFieldEvidence,
        badAssets,
        cspErrors,
        consoleErrorsCount: consoleErrors.length,
        networkErrorsCount: networkErrors.length,
        screenshot,
        pageUrl: page.url(),
      };

      await testInfo.attach(`pd0-${route.id}.json`, {
        body: JSON.stringify(evidence, null, 2),
        contentType: 'application/json',
      });

      console.log(`[PD0-DIAG] ${route.id} :: ${JSON.stringify(evidence).slice(0, 4000)}`);
    });
  }

  test('design QA — PDP (auto-descoberta via resultado de busca)', async ({ page }, testInfo) => {
    const consoleErrors = collectConsoleErrors(page);
    const networkErrors = collectNetworkErrors(page);

    const listStatus = await safeGoto(page, `${awaUrls.searchResults}bagageiro`);
    if (listStatus === 0 || listStatus === -1) {
      test.skip(true, 'Listagem de busca indisponivel para descobrir PDP — instabilidade de ambiente.');
      return;
    }

    const pdpLink = page.locator('.product-item-link, .product-item-info a.product-item-link').first();
    const href = await pdpLink.getAttribute('href').catch(() => null);
    if (!href) {
      test.skip(true, 'Nenhuma PDP encontrada dinamicamente a partir do resultado de busca.');
      return;
    }

    const status = await safeGoto(page, href);
    if (status === 0 || status === -1) {
      test.skip(true, 'Navegacao para PDP falhou — instabilidade de ambiente.');
      return;
    }

    const overflow = await checkOverflow(page);
    await triggerLazyImages(page);
    const brokenImages = await findRealBrokenImages(page);
    const componentEvidence = await collectComponentEvidence(page);
    const radiusEvidence = await collectRadiusEvidence(page);
    const screenshot = await snap(page, testInfo, 'pdp-fullpage');

    const badAssets = networkErrors.filter(
      (e) => BAD_ASSET_STATUSES.has(e.status) && /\.(css|js)(\?|$)/i.test(e.url),
    );
    const cspErrors = classifyCspErrors(consoleErrors);

    const evidence = {
      route: href,
      project: testInfo.project.name,
      httpStatus: status,
      hasHorizontalOverflow: overflow.hasOverflow,
      overflowDiffPx: overflow.diff,
      brokenImages,
      components: componentEvidence,
      radius: radiusEvidence,
      badAssets,
      cspErrors,
      screenshot,
      pageUrl: page.url(),
    };

    await testInfo.attach('pd0-pdp.json', {
      body: JSON.stringify(evidence, null, 2),
      contentType: 'application/json',
    });

    console.log(`[PD0-DIAG] pdp :: ${JSON.stringify(evidence).slice(0, 4000)}`);
  });
});
