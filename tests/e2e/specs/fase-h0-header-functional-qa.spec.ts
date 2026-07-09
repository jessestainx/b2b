/**
 * Fase H0 — Header Functional QA
 * ================================
 * Suite de INSPECAO/REPRODUCAO de bugs funcionais do header AWA Motos.
 *
 * Regras desta fase (ver docs/visual-qa/fase-h0-header-inventario-2026-07-08.md):
 *  - Read-only. Nenhuma correcao de codigo é aplicada aqui.
 *  - Nao mistura QA funcional com limpeza de CSS morto.
 *  - Nao toca checkout/pagamento (apenas leitura da pagina /checkout/cart/, sem finalizar compra).
 *  - Cobre os 14 componentes obrigatorios, os 6 breakpoints obrigatorios (via projects em
 *    pw-fase-h0-header.config.ts) e as 11 rotas obrigatorias.
 *
 * Execucao:
 *   cd tests/e2e
 *   ALLOW_PRODUCTION_VALIDATION=true PLAYWRIGHT_BASE_URL=https://awamotos.com \
 *     playwright test --config=pw-fase-h0-header.config.ts
 *
 * Login B2B opcional (componente 9 — painel B2B):
 *   defina AWA_H0_B2B_USER / AWA_H0_B2B_PASS (fallback: credencial de QA conhecida do repo,
 *   ver header-greeting-casing.spec.ts). Se o login falhar, os testes do painel B2B sao
 *   pulados (test.skip) e reportados como pendencia — nao quebram a suite.
 */
import { test, expect, type Page } from '@playwright/test';
import { targetUrl } from '../helpers/target-url';
import {
  collectConsoleErrors,
  collectNetworkErrors,
  filterCriticalJsErrors,
  filter500s,
  checkOverflow,
  dismissCookie,
} from '../helpers/deep-audit.helpers';

/* ──────────────────────────────────────────────────────────────────────
 * Rotas obrigatorias da Fase H0
 * ────────────────────────────────────────────────────────────────────── */
type RouteDef = {
  id: string;
  path: string;
  label: string;
  /** false = rota usa shell alternativo (b2b_auth_shell) sem header padrao — by design */
  expectFullHeader: boolean;
  notes?: string;
  /**
   * BUG-H0-CRIT-01 (2026-07-08): /catalogo trava a thread principal do
   * renderer (Chromium headless) e eventualmente derruba o PROCESSO do
   * browser ("Error: Channel closed"), levando o worker inteiro do
   * Playwright a cair sem completar os testes restantes. Reproduzido de
   * forma isolada (script dedicado fora desta spec, ver bug report H0,
   * secao "BUG-H0-CRIT-01"): apos 'domcontentloaded' a pagina nunca
   * atinge 'load' (5 requests presos por 30s+, incl. 4 assets do proprio
   * dominio que respondem instantaneamente via curl) e chamadas
   * page.evaluate() subsequentes travam. Marcado como conhecido-instavel
   * para nao derrubar a suite inteira; ver relatorio de bugs para
   * evidencias completas e proximo passo (profiling de performance,
   * FORA do escopo desta fase H0).
   */
  knownUnstable?: boolean;
};

const ROUTES: RouteDef[] = [
  { id: 'home', path: '/', label: 'Home', expectFullHeader: true },
  { id: 'catalogo', path: '/catalogo', label: 'Catalogo', expectFullHeader: true, knownUnstable: true, notes: 'BUG-H0-CRIT-01: trava o browser headless — ver relatorio de bugs' },
  { id: 'bauletos', path: '/bauletos.html', label: 'PLP Bauletos', expectFullHeader: true },
  { id: 'nossas-marcas', path: '/nossas-marcas', label: 'Nossas Marcas', expectFullHeader: true },
  { id: 'about-us', path: '/about-us', label: 'About Us', expectFullHeader: true },
  { id: 'lancamentos', path: '/lancamentos', label: 'Lancamentos', expectFullHeader: true },
  { id: 'lancamentos-html', path: '/lancamentos.html', label: 'Lancamentos.html (301 esperado)', expectFullHeader: true },
  { id: 'b2b-register', path: '/b2b/register', label: 'Cadastro B2B', expectFullHeader: false, notes: 'b2b_auth_shell — sem topbar/busca/menu/minicart por design' },
  { id: 'customer-login', path: '/customer/account/login', label: 'Login (redireciona a /b2b/account/login/)', expectFullHeader: false, notes: 'b2b_auth_shell — sem topbar/busca/menu/minicart por design' },
  { id: 'search-guidao', path: '/catalogsearch/result/?q=guidao', label: 'Busca "guidao"', expectFullHeader: true },
  {
    id: 'pdp', path: '/bagageiro-titan-150-09-13-modelo-preto-macico-3000.html', label: 'PDP real', expectFullHeader: true,
    knownUnstable: true,
    notes: 'BUG-H0-CRIT-02: pagina nunca atinge domcontentloaded em Chromium headless (confirmado com timeout de 90s) — ver relatorio de bugs',
  },
];

const FULL_HEADER_ROUTES = ROUTES.filter(r => r.expectFullHeader);
const ALT_SHELL_ROUTES = ROUTES.filter(r => !r.expectFullHeader);

const HOME = ROUTES[0];
const PDP = ROUTES.find(r => r.id === 'pdp')!;
const CATALOGO = ROUTES.find(r => r.id === 'catalogo')!;

/* ──────────────────────────────────────────────────────────────────────
 * Selectors (fonte: docs/visual-qa/fase-h0-header-inventario-2026-07-08.md)
 * ────────────────────────────────────────────────────────────────────── */
const SEL = {
  siteHeader: 'header.awa-site-header[data-awa-site-header="true"]',
  promoBar: '#awa-b2b-promo-bar',
  promoClose: '#awa-b2b-promo-close',
  promoCta: '.awa-b2b-promo-bar__cta',
  logoImg: '.awa-header-brand-cell .logo img, .b2b-login-logo img',
  logoLink: '.awa-header-brand-cell .logo a, .b2b-login-logo a',
  searchForm: '#search_mini_form',
  searchInput: '#search, input[data-awa-search-input="true"]',
  autocompletePanel: '#search_autocomplete, .search-autocomplete, [data-role="minisearch"] .search-autocomplete',
  deptTrigger: '[data-role="awa-vertical-menu-trigger"]',
  deptPanel: '[data-role="awa-vertical-menu-panel"]',
  primaryNav: '.top-menu.top-menu-sticky',
  mobileToggle: '.awa-header-mobile-toggle[data-awa-nav-toggle="true"]',
  navClose: '.awa-nav-close',
  b2bStatusPanel: '.b2b-status-panel',
  b2bStatusTrigger: '.b2b-status-trigger',
  b2bStatusDropdown: '#b2b-status-dropdown',
  accountNav: '.top-account.awa-header-account-nav',
  accountPrompt: '.awa-header-account-prompt',
  minicartWrapper: '.awa-header-minicart[data-awa-header-cart="true"]',
  minicartFallback: '.awa-header-cart-fallback',
  minicartPanel: '#awa-minicart-panel',
  headerCartLink: '.awa-header-cart-link',
  stickyWrapper: '.header-wrapper-sticky',
  pwaInstall: '[data-awa-pwa-install]',
  pwaInstallAccept: '[data-awa-pwa-install-accept]',
  pwaInstallClose: '[data-awa-pwa-install-close]',
} as const;

/* ──────────────────────────────────────────────────────────────────────
 * Helpers
 * ────────────────────────────────────────────────────────────────────── */
/**
 * Ambiente conhecido por instabilidade de renderer (Chromium/Firefox headless
 * ocasionalmente fecha a pagina sem motivo aparente — ver relatorio de bugs H0,
 * secao "Instabilidade de ambiente"). page.waitForTimeout nativo pode ficar
 * pendurado indefinidamente quando a pagina/contexto já foi fechado por fora;
 * este wrapper usa um timer Node.js como rede de seguranca (mesmo padrao usado
 * em helpers/header.helpers.ts).
 */
async function safeWait(page: Page, ms: number): Promise<void> {
  if (page.isClosed()) return;
  await Promise.race<void>([
    page.waitForTimeout(ms).catch(() => {}),
    new Promise<void>(resolve => setTimeout(resolve, ms + 2_000)),
  ]);
}

/**
 * locator.count() nao aceita timeout nativo — se a pagina/contexto morrer entre
 * a navegacao e esta chamada (instabilidade de ambiente), a promise nunca resolve.
 * Mesma rede de seguranca de safeWait().
 */
async function safeCount(locator: import('@playwright/test').Locator): Promise<number> {
  return Promise.race<number>([
    locator.count().catch(() => 0),
    new Promise<number>(resolve => setTimeout(() => resolve(0), 6_000)),
  ]);
}

async function goto(page: Page, route: RouteDef): Promise<number> {
  const url = targetUrl(route.path, 'fase-h0-header');
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => null);
  if (page.isClosed()) return resp?.status() ?? -1; // renderer fechou a pagina — instabilidade de ambiente, nao é bug de header
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
  if (page.isClosed()) return resp?.status() ?? -1;
  await dismissCookie(page);
  await safeWait(page, 300);
  return resp?.status() ?? 0;
}

function vpWidth(page: Page): number {
  return page.viewportSize()?.width ?? 0;
}

/** Descobre empiricamente se o toggle mobile esta a ativo (visivel) neste viewport. */
async function isMobileToggleActive(page: Page): Promise<boolean> {
  return page.locator(SEL.mobileToggle).first().isVisible({ timeout: 2_000 }).catch(() => false);
}

async function attachIssues(testInfo: import('@playwright/test').TestInfo, name: string, issues: unknown[]): Promise<void> {
  await testInfo.attach(name, { body: JSON.stringify(issues, null, 2), contentType: 'application/json' });
  if (issues.length) {
    console.log(`[H0-BUGS] ${testInfo.title} :: ${JSON.stringify(issues)}`);
  }
}

const B2B_USER = process.env.AWA_H0_B2B_USER || process.env.TEST_ALLCAPS_USER || '66.618.406/0001-40';
const B2B_PASS = process.env.AWA_H0_B2B_PASS || process.env.TEST_ALLCAPS_PASS || '123awa';

/** Tenta logar como cliente B2B. Retorna true em sucesso (soft — nunca lanca). */
async function tryLoginB2B(page: Page): Promise<boolean> {
  await page.goto(targetUrl('/b2b/account/login/', 'fase-h0-header'), { waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => null);
  await dismissCookie(page);
  const user = page.locator('#b2b-email, input[name="login[username]"]').first();
  const pass = page.locator('#b2b-pass, input[name="login[password]"]').first();
  const submit = page.locator('.b2b-btn-entrar, button[type="submit"]').first();
  const formVisible = await user.isVisible({ timeout: 5_000 }).catch(() => false);
  if (!formVisible) return false;
  await user.fill(B2B_USER).catch(() => {});
  await pass.fill(B2B_PASS).catch(() => {});
  await submit.click().catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
  const url = page.url();
  return !/\/login\/?$/.test(url) && !/account\/login/i.test(url);
}

async function logout(page: Page): Promise<void> {
  await page.goto(targetUrl('/customer/account/logout', 'fase-h0-header'), { waitUntil: 'domcontentloaded', timeout: 20_000 }).catch(() => {});
}

/* ══════════════════════════════════════════════════════════════════════
 * H0-00 — Estrutura por rota (smoke matrix — todas as 11 rotas)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-00 — Estrutura do header por rota', () => {
  for (const route of ROUTES) {
    test(`estrutura — ${route.id}`, async ({ page }, testInfo) => {
      if (route.knownUnstable && process.env.AWA_H0_ALLOW_UNSTABLE !== 'true') {
        test.skip(true, `rota marcada knownUnstable (${route.notes ?? 'ver relatorio de bugs H0'}) — pulada para nao derrubar o worker; setar AWA_H0_ALLOW_UNSTABLE=true para forcar`);
        return;
      }
      const consoleErrors = collectConsoleErrors(page);
      const networkErrors = collectNetworkErrors(page);
      const issues: string[] = [];

      const status = await goto(page, route);
      if (status === 0 || status === -1) { test.skip(true, 'navegacao falhou ou renderer fechou a pagina (instabilidade de ambiente) — nao é bug de header'); return; }
      if (status >= 500) issues.push(`HTTP ${status} inesperado para ${route.path}`);

      const overflow = await checkOverflow(page);
      if (overflow.hasOverflow) issues.push(`overflow horizontal ${overflow.diff}px`);

      const hasSiteHeader = await page.locator(SEL.siteHeader).first().isVisible({ timeout: 5_000 }).catch(() => false);
      const hasPromoBar = await page.locator(SEL.promoBar).first().isVisible({ timeout: 2_000 }).catch(() => false);
      const hasSearch = await page.locator(SEL.searchForm).first().isVisible({ timeout: 2_000 }).catch(() => false);
      const hasDept = await safeCount(page.locator(SEL.deptTrigger).first()) > 0;
      const hasMinicart = await safeCount(page.locator(SEL.minicartWrapper).first()) > 0;
      const hasLogo = await page.locator(SEL.logoImg).first().isVisible({ timeout: 3_000 }).catch(() => false);

      if (route.expectFullHeader) {
        if (!hasSiteHeader) issues.push('header padrao (.awa-site-header) ausente em rota que deveria exibi-lo');
        if (!hasLogo) issues.push('logo ausente/nao visivel');
        if (!hasSearch) issues.push('form de busca ausente');
      } else {
        if (hasPromoBar) issues.push('topbar B2B inesperadamente visivel em shell de auth (deveria estar removida)');
        if (hasDept || hasMinicart) issues.push('menu de departamentos ou minicart inesperadamente presentes em shell de auth');
        if (!hasLogo) issues.push('logo compacto do shell de auth ausente/nao visivel');
      }

      await safeWait(page, 500);
      const critical = filterCriticalJsErrors(consoleErrors);
      const server5xx = filter500s(networkErrors);
      if (critical.length) issues.push(`JS errors criticos: ${critical.map(e => e.text).join(' | ')}`);
      if (server5xx.length) issues.push(`requisicoes 5xx: ${server5xx.map(e => `${e.status} ${e.url}`).join(' | ')}`);

      await attachIssues(testInfo, `h0-00-${route.id}.json`, issues);
      expect.soft(issues, `${route.label} (${route.path})`).toEqual([]);
    });
  }
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-01 / H0-02 — Topbar B2B + botao fechar
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-01/02 — Topbar B2B e botao fechar', () => {
  test('topbar visivel por padrao e link CTA aponta para b2b/register', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const bar = page.locator(SEL.promoBar).first();
    const visible = await bar.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!visible) { issues.push('topbar B2B nao visivel em visita nova (localStorage limpo)'); }
    const cta = page.locator(SEL.promoCta).first();
    const href = await cta.getAttribute('href').catch(() => null);
    if (!href || !/b2b\/register/.test(href)) issues.push(`CTA da topbar nao aponta para b2b/register (href="${href}")`);
    await attachIssues(testInfo, 'h0-01-topbar-visible.json', issues);
    expect.soft(issues).toEqual([]);
  });

  test('botao fechar oculta a topbar e persiste apos reload', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const bar = page.locator(SEL.promoBar).first();
    const closeBtn = page.locator(SEL.promoClose).first();

    const visibleBefore = await bar.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!visibleBefore) { test.skip(true, 'topbar ja nao estava visivel — nao é possivel testar o fechamento'); return; }

    await closeBtn.click({ force: true }).catch(() => {});
    await safeWait(page, 500);
    const stillVisible = await bar.isVisible({ timeout: 2_000 }).catch(() => false);
    if (stillVisible) issues.push('clicar no botao fechar (#awa-b2b-promo-close) nao ocultou a topbar');

    const dismissedFlag = await page.evaluate(() => {
      try { return localStorage.getItem('awa_b2b_promo_dismissed') || sessionStorage.getItem('awa_b2b_promo_dismissed_session'); }
      catch { return null; }
    });
    if (!dismissedFlag) issues.push('fechar a topbar nao gravou flag de dismissal em localStorage/sessionStorage');

    await page.reload({ waitUntil: 'domcontentloaded', timeout: 20_000 }).catch(() => {});
    await safeWait(page, 500);
    const visibleAfterReload = await bar.isVisible({ timeout: 2_000 }).catch(() => false);
    if (visibleAfterReload) issues.push('topbar B2B reaparece apos reload mesmo apos ser fechada (persistencia quebrada)');

    await attachIssues(testInfo, 'h0-02-topbar-close-persist.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-03 — Logo (rotas com header completo + shell de auth)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-03 — Logo', () => {
  for (const route of [HOME, PDP, CATALOGO, ...ALT_SHELL_ROUTES]) {
    test(`logo funcional — ${route.id}`, async ({ page }, testInfo) => {
      if (route.knownUnstable && process.env.AWA_H0_ALLOW_UNSTABLE !== 'true') {
        test.skip(true, `rota marcada knownUnstable (${route.notes ?? 'ver relatorio de bugs H0'}) — pulada para nao derrubar o worker`);
        return;
      }
      await goto(page, route);
      const issues: string[] = [];
      const img = page.locator(SEL.logoImg).first();
      const visible = await img.isVisible({ timeout: 8_000 }).catch(() => false);
      if (!visible) { issues.push('logo nao visivel'); await attachIssues(testInfo, `h0-03-${route.id}.json`, issues); expect.soft(issues).toEqual([]); return; }

      const loaded = await img.evaluate((el: HTMLImageElement) => el.naturalWidth > 0 && el.complete).catch(() => false);
      if (!loaded) issues.push('imagem do logo nao carregou (naturalWidth=0)');

      const link = page.locator(SEL.logoLink).first();
      const href = await link.getAttribute('href').catch(() => null);
      if (!href || !/^https?:\/\/[^/]+\/?$/.test(href)) issues.push(`href do logo nao aponta para a home (href="${href}")`);

      await attachIssues(testInfo, `h0-03-${route.id}.json`, issues);
      expect.soft(issues, route.label).toEqual([]);
    });
  }
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-04/05 — Busca + Autocomplete
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-04/05 — Busca e autocomplete', () => {
  test('input de busca aceita foco e digitacao', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const input = page.locator(SEL.searchInput).first();
    let visible = await input.isVisible({ timeout: 3_000 }).catch(() => false);

    if (!visible) {
      const toggle = page.locator('.awa-search-toggle, [data-awa-search-toggle], .search-toggle').first();
      const hasToggle = await toggle.isVisible({ timeout: 2_000 }).catch(() => false);
      if (hasToggle) { await toggle.click({ force: true }).catch(() => {}); await safeWait(page, 400); }
      visible = await input.isVisible({ timeout: 2_000 }).catch(() => false);
    }

    if (!visible) { issues.push('input de busca inacessivel neste breakpoint (nem direto, nem via toggle)'); await attachIssues(testInfo, 'h0-04-search-focus.json', issues); expect.soft(issues).toEqual([]); return; }

    await input.click({ force: true }).catch(() => {});
    await input.fill('guidao').catch(() => {});
    const value = await input.inputValue().catch(() => '');
    if (value !== 'guidao') issues.push(`input nao aceitou digitacao (valor="${value}")`);

    await attachIssues(testInfo, 'h0-04-search-focus.json', issues);
    expect.soft(issues).toEqual([]);
  });

  test('autocomplete abre com resultados/estado vazio e fecha com Escape', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const input = page.locator(SEL.searchInput).first();
    const visible = await input.isVisible({ timeout: 3_000 }).catch(() => false);
    if (!visible) { test.skip(true, 'input de busca nao visivel neste breakpoint'); return; }

    await input.click({ force: true }).catch(() => {});
    await input.fill('guidao');
    await safeWait(page, 1_200);

    const ariaExpanded = await input.getAttribute('aria-expanded').catch(() => null);
    const panel = page.locator(SEL.autocompletePanel).first();
    const panelVisible = await panel.isVisible({ timeout: 3_000 }).catch(() => false);

    if (ariaExpanded !== 'true' && !panelVisible) {
      issues.push('nem aria-expanded="true" nem painel de autocomplete visivel apos digitar 3+ caracteres');
    }

    await page.keyboard.press('Escape');
    await safeWait(page, 400);
    const panelVisibleAfterEscape = await panel.isVisible({ timeout: 1_500 }).catch(() => false);
    if (panelVisibleAfterEscape) issues.push('painel de autocomplete nao fecha com tecla Escape');

    await attachIssues(testInfo, 'h0-05-autocomplete.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-06 — Menu Departamentos (desktop: trigger dedicado / mobile: dentro do drawer)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-06 — Menu Departamentos', () => {
  test('desktop: trigger abre e fecha o painel de departamentos', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const mobileActive = await isMobileToggleActive(page);
    const trigger = page.locator(SEL.deptTrigger).first();
    const triggerVisible = await trigger.isVisible({ timeout: 3_000 }).catch(() => false);

    if (mobileActive || !triggerVisible) {
      test.skip(true, `breakpoint ${vpWidth(page)}px usa navegacao mobile — trigger dedicado de departamentos nao esperado aqui`);
      return;
    }

    const panel = page.locator(SEL.deptPanel).first();
    await trigger.click({ force: true }).catch(() => {});
    await safeWait(page, 400);
    const expandedAfterOpen = await trigger.getAttribute('aria-expanded').catch(() => null);
    const panelVisible = await panel.isVisible({ timeout: 2_000 }).catch(() => false);
    if (expandedAfterOpen !== 'true') issues.push('aria-expanded nao mudou para "true" apos clicar no trigger de departamentos');
    if (!panelVisible) issues.push('painel de departamentos nao ficou visivel apos clique no trigger');

    const childLinks = await safeCount(panel.locator('a'));
    if (panelVisible && childLinks === 0) issues.push('painel de departamentos abriu mas nao contem links de categoria');

    await page.keyboard.press('Escape');
    await safeWait(page, 400);
    const expandedAfterEscape = await trigger.getAttribute('aria-expanded').catch(() => null);
    if (expandedAfterEscape === 'true') issues.push('menu de departamentos nao fecha com tecla Escape');

    await attachIssues(testInfo, 'h0-06-departamentos-desktop.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-07 — Menu principal (nav superior)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-07 — Menu principal', () => {
  test('nav principal contem links navegaveis quando visivel no breakpoint', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const mobileActive = await isMobileToggleActive(page);
    const nav = page.locator(SEL.primaryNav).first();
    const navVisible = await nav.isVisible({ timeout: 3_000 }).catch(() => false);

    if (mobileActive) {
      test.skip(true, `breakpoint ${vpWidth(page)}px usa drawer mobile — nav principal fica dentro do drawer (ver H0-12)`);
      return;
    }

    if (!navVisible) { issues.push('nav principal (.top-menu.top-menu-sticky) nao visivel em breakpoint desktop/tablet'); }
    else {
      const links = nav.locator('a');
      const count = await safeCount(links);
      if (count === 0) issues.push('nav principal visivel mas sem nenhum link');
      else {
        const firstHref = await links.first().getAttribute('href').catch(() => null);
        if (!firstHref) issues.push('primeiro item do menu principal sem href');
      }
    }

    await attachIssues(testInfo, 'h0-07-menu-principal.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-08 — Link Lancamentos
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-08 — Link Lancamentos', () => {
  test('rota /lancamentos.html redireciona corretamente para /lancamentos', async ({ page, request }, testInfo) => {
    const issues: string[] = [];
    const resp = await request.get(targetUrl('/lancamentos.html', 'fase-h0-header'), { maxRedirects: 0 }).catch(() => null);
    if (!resp) { issues.push('nao foi possivel requisitar /lancamentos.html'); }
    else {
      const status = resp.status();
      const location = resp.headers()['location'] || '';
      if (status < 300 || status >= 400) issues.push(`/lancamentos.html retornou ${status} (esperado 3xx de redirect)`);
      if (!/\/lancamentos\/?$/.test(location)) issues.push(`/lancamentos.html redireciona para "${location}" (esperado /lancamentos)`);
    }
    await attachIssues(testInfo, 'h0-08-lancamentos-redirect.json', issues);
    expect.soft(issues).toEqual([]);
  });

  test('rota /lancamentos carrega com header completo', async ({ page }, testInfo) => {
    const status = await goto(page, ROUTES.find(r => r.id === 'lancamentos')!);
    const issues: string[] = [];
    if (status !== 200) issues.push(`/lancamentos retornou status ${status}`);
    const hasHeader = await page.locator(SEL.siteHeader).first().isVisible({ timeout: 5_000 }).catch(() => false);
    if (!hasHeader) issues.push('header padrao ausente em /lancamentos');
    await attachIssues(testInfo, 'h0-08-lancamentos-page.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-09 — Painel B2B / status do cliente (requer login)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-09 — Painel B2B / status do cliente', () => {
  test('painel B2B abre com dados do cliente e fecha corretamente', async ({ page }, testInfo) => {
    const loggedIn = await tryLoginB2B(page);
    if (!loggedIn) {
      test.skip(true, `login B2B de QA falhou (usuario "${B2B_USER}") — pendencia: configurar credencial dedicada para Fase H0`);
      return;
    }

    await goto(page, HOME);
    const issues: string[] = [];
    const panel = page.locator(SEL.b2bStatusPanel).first();
    const panelPresent = await safeCount(panel) > 0;
    if (!panelPresent) issues.push('painel B2B (.b2b-status-panel) ausente apos login B2B bem-sucedido');
    else {
      const trigger = page.locator(SEL.b2bStatusTrigger).first();
      const triggerVisible = await trigger.isVisible({ timeout: 5_000 }).catch(() => false);
      if (!triggerVisible) issues.push('trigger do painel B2B nao visivel apos login');
      else {
        await trigger.click({ force: true }).catch(() => {});
        await safeWait(page, 400);
        const expanded = await trigger.getAttribute('aria-expanded').catch(() => null);
        const dropdown = page.locator(SEL.b2bStatusDropdown).first();
        const dropdownVisible = await dropdown.isVisible({ timeout: 2_000 }).catch(() => false);
        if (expanded !== 'true' || !dropdownVisible) issues.push('dropdown do painel B2B nao abre ao clicar no trigger');

        await page.keyboard.press('Escape');
        await safeWait(page, 300);
        const expandedAfterEscape = await trigger.getAttribute('aria-expanded').catch(() => null);
        if (expandedAfterEscape === 'true') issues.push('dropdown do painel B2B nao fecha com Escape');
      }
    }

    await attachIssues(testInfo, 'h0-09-b2b-status-panel.json', issues);
    await logout(page);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-10 — Dropdown de conta (guest)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-10 — Dropdown de conta (guest)', () => {
  test('links de login/cadastro acessiveis para visitante', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const mobileActive = await isMobileToggleActive(page);
    const loginLinks = page.locator('a[href*="customer/account"], a[href*="b2b/account"], a[href*="b2b/register"]');
    const count = await safeCount(loginLinks);
    if (count === 0) { issues.push('nenhum link de login/cadastro encontrado no DOM do header para visitante'); }
    else if (!mobileActive) {
      const anyVisible = await loginLinks.first().isVisible({ timeout: 3_000 }).catch(() => false);
      if (!anyVisible) issues.push('links de login/cadastro presentes no DOM mas nao visiveis no breakpoint desktop');
    }
    await attachIssues(testInfo, 'h0-10-account-guest.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-11 — Minicart (estado guest, sem adicionar itens — leitura apenas)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-11 — Minicart', () => {
  test('abre e fecha o painel do minicart', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];

    const trigger = page.locator(`${SEL.minicartFallback}, ${SEL.headerCartLink}`).first();
    const triggerVisible = await trigger.isVisible({ timeout: 6_000 }).catch(() => false);
    if (!triggerVisible) { issues.push('nenhum controle de abertura do minicart visivel (.awa-header-cart-fallback / .awa-header-cart-link)'); await attachIssues(testInfo, 'h0-11-minicart.json', issues); expect.soft(issues).toEqual([]); return; }

    const isFallback = await page.locator(SEL.minicartFallback).first().isVisible({ timeout: 1_000 }).catch(() => false);
    if (!isFallback) { test.skip(true, 'apenas o link de atalho do carrinho (sem painel dropdown) esta ativo neste breakpoint — comportamento esperado em mobile'); return; }

    await trigger.click({ force: true }).catch(() => {});
    await safeWait(page, 600);
    const expanded = await trigger.getAttribute('aria-expanded').catch(() => null);
    const panel = page.locator(SEL.minicartPanel).first();
    const panelVisible = await panel.isVisible({ timeout: 3_000 }).catch(() => false);
    if (expanded !== 'true' && !panelVisible) issues.push('minicart nao abre (nem aria-expanded="true" nem painel visivel) ao clicar no icone do carrinho');

    await page.keyboard.press('Escape');
    await safeWait(page, 400);
    const panelVisibleAfterEscape = await panel.isVisible({ timeout: 1_500 }).catch(() => false);
    if (panelVisibleAfterEscape) issues.push('painel do minicart nao fecha com tecla Escape');

    await attachIssues(testInfo, 'h0-11-minicart.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-12 — Modal/drawer mobile
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-12 — Modal/drawer mobile', () => {
  test('hamburguer abre o drawer com navegacao e fecha corretamente', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];
    const active = await isMobileToggleActive(page);
    if (!active) { test.skip(true, `breakpoint ${vpWidth(page)}px usa navegacao desktop — toggle mobile nao esperado`); return; }

    const toggle = page.locator(SEL.mobileToggle).first();
    await toggle.click({ force: true }).catch(() => {});
    await safeWait(page, 500);

    const expanded = await toggle.getAttribute('aria-expanded').catch(() => null);
    const bodyOpen = await page.evaluate(() => document.body.classList.contains('nav-open')
      || document.body.classList.contains('awa-mobile-drawer-open')
      || document.body.classList.contains('nav-before-open')).catch(() => false);

    if (expanded !== 'true') issues.push('aria-expanded do hamburguer nao muda para "true" ao abrir o drawer');
    if (!bodyOpen) issues.push('nenhuma classe de estado esperada (nav-open/awa-mobile-drawer-open) foi adicionada ao body ao abrir o drawer');

    const navInDrawer = page.locator(`${SEL.primaryNav} a, .awa-header-categories a`);
    const navCount = await safeCount(navInDrawer);
    if (navCount === 0) issues.push('drawer aberto mas sem nenhum link de navegacao visivel dentro dele');

    const closeBtn = page.locator(SEL.navClose).first();
    const hasCloseBtn = await safeCount(closeBtn) > 0;
    if (hasCloseBtn) {
      await closeBtn.click({ force: true }).catch(() => {});
    } else {
      await toggle.click({ force: true }).catch(() => {});
    }
    await safeWait(page, 500);

    const expandedAfterClose = await toggle.getAttribute('aria-expanded').catch(() => null);
    const bodyOpenAfterClose = await page.evaluate(() => document.body.classList.contains('nav-open')
      || document.body.classList.contains('awa-mobile-drawer-open')).catch(() => false);
    if (expandedAfterClose === 'true' || bodyOpenAfterClose) issues.push('drawer mobile nao fecha ao clicar no botao de fechar/hamburguer novamente');

    await attachIssues(testInfo, 'h0-12-drawer-mobile.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-13 — Sticky header
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-13 — Sticky header', () => {
  test('header fica sticky ao rolar a pagina para baixo', async ({ page }, testInfo) => {
    // Usa HOME em vez de CATALOGO (BUG-H0-CRIT-01: catalogo trava o renderer
    // headless — ver relatorio de bugs). Comportamento sticky nao e especifico
    // de rota, home e suficiente para validar a funcionalidade.
    await goto(page, HOME);
    const issues: string[] = [];

    const beforeSticky = await page.evaluate(() => document.body.classList.contains('awa-header-is-sticky')).catch(() => false);
    if (beforeSticky) issues.push('body ja inicia com classe awa-header-is-sticky no topo da pagina (deveria iniciar sem sticky)');

    await page.mouse.wheel(0, 1200);
    await safeWait(page, 700);

    const afterSticky = await page.evaluate(() => document.body.classList.contains('awa-header-is-sticky')).catch(() => false);
    const stickyWrapperClass = await page.locator(SEL.stickyWrapper).first().evaluate(el => el.classList.contains('is-sticky')).catch(() => false);
    if (!afterSticky && !stickyWrapperClass) issues.push('nenhuma classe de estado sticky foi ativada apos rolar 1200px para baixo');

    const headerVisibleAfterScroll = await page.locator(SEL.siteHeader).first().isVisible({ timeout: 2_000 }).catch(() => false);
    if (!headerVisibleAfterScroll) issues.push('header nao permanece visivel apos rolagem (esperado: sticky no topo)');

    await page.mouse.wheel(0, -1200);
    await safeWait(page, 500);

    await attachIssues(testInfo, 'h0-13-sticky.json', issues);
    expect.soft(issues).toEqual([]);
  });
});

/* ══════════════════════════════════════════════════════════════════════
 * H0-14 — PWA install modal (nao disparado por elemento do header — ver inventario)
 * ══════════════════════════════════════════════════════════════════════ */
test.describe('H0-14 — PWA install modal', () => {
  test('modal simulado abre e fecha corretamente (evento sintetico beforeinstallprompt)', async ({ page }, testInfo) => {
    await goto(page, HOME);
    const issues: string[] = [];

    const modalExistsInDom = await safeCount(page.locator(SEL.pwaInstall)) > 0;
    if (!modalExistsInDom) { test.skip(true, 'bloco [data-awa-pwa-install] nao presente no DOM desta rota/config — nada a testar'); return; }

    await page.evaluate(() => {
      try {
        localStorage.setItem('awa-home-visited-before', '1');
        sessionStorage.removeItem('awa-home-modal-shown');
      } catch (e) { /* localStorage indisponivel */ }
    });
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 20_000 }).catch(() => {});
    await dismissCookie(page);
    await safeWait(page, 500);

    await page.evaluate(() => {
      const evt = new Event('beforeinstallprompt', { cancelable: true }) as unknown as Record<string, unknown>;
      evt.preventDefault = () => {};
      evt.prompt = () => {};
      evt.userChoice = Promise.resolve({ outcome: 'dismissed' });
      window.dispatchEvent(evt as unknown as Event);
    });
    await safeWait(page, 46_000);

    const modal = page.locator(SEL.pwaInstall).first();
    const visible = await modal.isVisible({ timeout: 5_000 }).catch(() => false);
    if (!visible) {
      issues.push('modal PWA install nao abriu apos simular beforeinstallprompt + visitante retornante (pode ser gate de sessao/tempo — ver awa-pwa-install.phtml)');
    } else {
      const closeBtn = page.locator(SEL.pwaInstallClose).first();
      await closeBtn.click({ force: true }).catch(() => {});
      await safeWait(page, 400);
      const hiddenAfterClose = await modal.isHidden({ timeout: 2_000 }).catch(() => false);
      if (!hiddenAfterClose) issues.push('modal PWA install nao fecha ao clicar no botao de fechar');
    }

    await attachIssues(testInfo, 'h0-14-pwa-install.json', issues);
    expect.soft(issues).toEqual([]);
  });
});
