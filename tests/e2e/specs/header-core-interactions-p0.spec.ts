/**
 * header-core-interactions-p0.spec.ts — AWA Motos
 * ==================================================
 * Reproducao rapida (Tarefa 1) para a correcao dos bugs P0 do header:
 *   A. Header desktop (logo, busca, conta, minicart, menu principal)
 *   B. Menu vertical / Departamentos
 *   C. Minicart
 *   D. Conta / Login / B2B status
 *
 * Regras desta spec:
 *  - NAO espera 'load' em rotas instaveis (/catalogo) — usa 'domcontentloaded'
 *    com fallback e checagem de page.isClosed() (mesmo padrao de
 *    fase-h0-header-functional-qa.spec.ts / fase-h0-route-stability-*.spec.ts).
 *  - Apenas leitura/diagnostico. Nenhuma correcao aplicada aqui.
 *
 * Execucao:
 *   cd tests/e2e
 *   ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
 *     npx playwright test specs/header-core-interactions-p0.spec.ts --project=desktop-1440
 */
import { test, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import { dismissCookie, collectConsoleErrors, collectNetworkErrors, checkOverflow } from '../helpers/deep-audit.helpers';
import { getMultipleCSS, getBBox, isVisible } from '../helpers/header.helpers';

const ROUTES: Array<{ id: string; path: string }> = [
  { id: 'home', path: '/' },
  { id: 'catalogo', path: '/catalogo' },
  { id: 'bauletos', path: '/bauletos.html' },
  { id: 'login', path: '/customer/account/login' },
  { id: 'b2b-register', path: '/b2b/register' },
];

/** Componentes/seletores criticos do header — Tarefa 1 (A-D). */
const CRITICAL_SELECTORS: Record<string, string> = {
  siteHeader: 'header.awa-site-header[data-awa-site-header="true"], .awa-site-header',
  logo: '.awa-header-brand-cell .logo img, .b2b-login-logo img',
  search: '#search_mini_form, input#search',
  primaryNav: '.top-menu.top-menu-sticky',
  navBar: '.header-control.awa-nav-bar',
  deptTrigger: '[data-role="awa-vertical-menu-trigger"]',
  minicartShell: '.awa-header-minicart[data-awa-header-cart="true"]',
  minicartFallback: '.awa-header-cart-fallback',
  minicartShowcart: '.minicart-wrapper .showcart, .action.showcart.header-mini-cart',
  accountPrompt: '.awa-header-account-prompt',
  accountNav: '.top-account.awa-header-account-nav',
  b2bStatusPanel: '.b2b-status-panel',
  b2bStatusTrigger: '.b2b-status-trigger',
};

const CSS_PROPS = ['display', 'visibility', 'opacity', 'width', 'height', 'position', 'z-index', 'overflow', 'pointer-events'];

async function safeGoto(page: Page, path: string): Promise<number> {
  const url = targetUrl(path, 'header-core-p0');
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => null);
  if (page.isClosed()) return resp?.status() ?? -1;
  await page.waitForLoadState('networkidle', { timeout: 12_000 }).catch(() => {});
  if (page.isClosed()) return resp?.status() ?? -1;
  await dismissCookie(page);
  await new Promise((r) => setTimeout(r, 400));
  return resp?.status() ?? 0;
}

test.describe('P0 — Reproducao rapida do header (Tarefa 1)', () => {
  for (const route of ROUTES) {
    test(`diagnostico — ${route.id}`, async ({ page }, testInfo) => {
      const consoleErrors = collectConsoleErrors(page);
      const networkErrors = collectNetworkErrors(page);

      const status = await safeGoto(page, route.path);
      if (status === 0 || status === -1) {
        test.skip(true, 'navegacao falhou ou renderer fechou a pagina — instabilidade de ambiente, nao é bug de header (ver fase-h0-route-stability)');
        return;
      }

      const overflow = await checkOverflow(page);

      const componentEvidence: Record<string, unknown> = {};
      for (const [name, selector] of Object.entries(CRITICAL_SELECTORS)) {
        const visible = await isVisible(page, selector);
        const cssVals = await getMultipleCSS(page, selector, CSS_PROPS);
        const bbox = await getBBox(page, selector);
        componentEvidence[name] = { selector, visible, css: cssVals, bbox };
      }

      // 404/403 de CSS/JS
      const badAssets = networkErrors.filter(
        (e) => (e.status === 404 || e.status === 403) && /\.(css|js)(\?|$)/i.test(e.url),
      );

      const screenshotPath = testInfo.outputPath(`p0-${route.id}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: false, timeout: 8_000 }).catch(() => {});

      const evidence = {
        route: route.path,
        httpStatus: status,
        hasHorizontalOverflow: overflow.hasOverflow,
        overflowDiffPx: overflow.diff,
        components: componentEvidence,
        badAssets,
        consoleErrors,
        pageUrl: page.url(),
      };

      await testInfo.attach(`p0-${route.id}.json`, {
        body: JSON.stringify(evidence, null, 2),
        contentType: 'application/json',
      });

      console.log(`[P0-DIAG] ${route.id} :: ${JSON.stringify(evidence).slice(0, 4000)}`);
    });
  }
});
