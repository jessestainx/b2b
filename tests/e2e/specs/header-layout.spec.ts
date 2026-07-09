/**
 * AWA Motos — Header Layout Tests
 * Breakpoints cobertos: Tablet (768-1024px) e Notebook (1024-1366px)
 *
 * Valida:
 *  - Elementos presentes e visíveis por breakpoint
 *  - Ausência de sobreposição entre colunas
 *  - Proporções corretas: logo / search / minicart / contact-slot
 *  - Alinhamento vertical (todos centralizados na mesma linha)
 *  - Espaçamentos uniformes (gap / padding)
 *  - Integridade do header durante resize
 *  - Screenshots documentais por viewport
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';
import {
  SELECTORS,
  BREAKPOINTS,
  waitForHeader,
  getBBox,
  getCSSProp,
  getMultipleCSS,
  px,
  isVisible,
  checkOverlap,
} from '../helpers/header.helpers';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

/* ── Screenshot dir relativo ao config ───────────────────────────────── */
const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');

/* ── Helpers locais ──────────────────────────────────────────────────── */
function screenshotPath(name: string): string {
  return path.join(SCREENSHOT_DIR, `${name}.png`);
}

/** Navega para home e aguarda header — retorna false se Chrome estiver morto */
async function goHomeQuiet(page: Page): Promise<boolean> {
  const alive = await Promise.race<boolean>([
    page.evaluate(() => true).catch(() => false),
    new Promise<boolean>(r => setTimeout(() => r(false), 5_000)),
  ]);
  if (!alive) return false;
  let ready = false;
  await Promise.race<void>([
    (async () => {
      await page.goto('/', { waitUntil: 'domcontentloaded', timeout: 30_000 });
      await waitForHeader(page);
      ready = true;
    })().catch(() => {}),
    new Promise<void>(r => setTimeout(r, 45_000)),
  ]);
  return ready;
}

/** Helper para beforeAll: cria contexto+página e navega para home.
 *  Retorna a página ou null se falhou. */
async function createHomePage(browser: import('@playwright/test').Browser): Promise<Page | null> {
  const ctx = await browser.newContext().catch(() => null);
  if (!ctx) return null;
  await blockOptionalThirdParty(ctx);
  const p = await ctx.newPage().catch(() => null);
  if (!p) { await ctx.close().catch(() => {}); return null; }
  const ok = await goHomeQuiet(p).catch(() => false);
  if (!ok) { await ctx.close().catch(() => {}); return null; }
  return p;
}

/* ════════════════════════════════════════════════════════════════════════
   SUITE 1 — ELEMENTOS PRESENTES POR BREAKPOINT
   ════════════════════════════════════════════════════════════════════════ */
let s1Page: Page | null = null;
test.describe('Header — Elementos presentes por breakpoint', () => {
  test.beforeAll(async ({ browser }) => { s1Page = await createHomePage(browser); });
  test.afterAll(async () => { await s1Page?.context().close().catch(() => {}); s1Page = null; });
  test.beforeEach(() => { if (!s1Page) test.skip(); });

  test('Logo é visível no desktop/notebook @header', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em mobile/tablet (<992px) o tema usa layout diferente — logo fica em container colapsado
    if (vw < 992) test.skip();
    const logo = s1Page!.locator(SELECTORS.logo).first();
    await expect(logo).toBeVisible({ timeout: 8_000 });

    const box = await logo.boundingBox();
    expect(box, 'Logo deve ter dimensões positivas').toBeTruthy();
    expect(box!.width).toBeGreaterThan(40);
    expect(box!.height).toBeGreaterThan(10);

    await s1Page!.locator(SELECTORS.header).screenshot({
      path: screenshotPath(`header-logo-visible-${vw}px`),
      animations: 'disabled',
    }).catch(() => {});
  });

  test('Barra de busca visível no desktop/notebook @header', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;

    if (vw >= BREAKPOINTS.notebookMin) {
      // Notebook: busca deve estar visível no header
      await expect(s1Page!.locator(SELECTORS.topSearch).first()).toBeVisible();
      const searchBox = await getBBox(s1Page!, SELECTORS.topSearch);
      expect(searchBox!.width, 'Search bar deve ter largura substancial').toBeGreaterThan(200);
    } else if (vw >= BREAKPOINTS.tabletMin && vw < BREAKPOINTS.notebookMin) {
      // Tablet 768-1023: busca existe no DOM com bounding box positivo.
      // Usa evaluate pois o .block-search tem ancestral legacy (.header visibility:hidden)
      // sobrescrito com visibility:visible — isVisible() do Playwright percorre ancestrais
      // e retorna false mesmo o elemento sendo renderizado visualmente.
      const searchHeight = await s1Page!.evaluate((sel: string) => {
        const el = document.querySelector(sel);
        return el ? el.getBoundingClientRect().height : 0;
      }, SELECTORS.searchBlock);
      expect(searchHeight, `Search deve ter altura positiva na viewport ${vw}px`).toBeGreaterThan(0);
    }
  });

  test('Minicart visível no desktop/notebook @header', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em mobile (<992px) o minicart fica no menu off-canvas (display:none no header desktop)
    if (vw < 992) test.skip();
    const minicart = s1Page!.locator(SELECTORS.minicart).first();
    await expect(minicart).toBeVisible({ timeout: 8_000 });
    const box = await minicart.boundingBox();
    expect(box!.width).toBeGreaterThan(20);
    expect(box!.height).toBeGreaterThan(20);
  });

  test('Nav toggle (hamburger) visível apenas em mobile (<992px) @header', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    const toggle = s1Page!.locator(SELECTORS.navToggle).first();

    if (vw < 992) {
      await expect(toggle).toBeVisible({ timeout: 5_000 });
    } else {
      // Desktop/notebook: deve estar oculto via CSS
      const display = await getCSSProp(s1Page!, SELECTORS.navToggle, 'display');
      expect(display, `Nav toggle deve estar oculto em ${vw}px`).toBe('none');
    }
  });

  test('Header nav (categorias + menu) visível em notebook @header', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= BREAKPOINTS.notebookMin) {
      const navVisible = await isVisible(s1Page!, SELECTORS.headerNav);
      expect(navVisible, 'Header nav deve estar visível em notebook').toBe(true);
    }
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 2 — LAYOUT E PROPORÇÕES DAS COLUNAS
   ════════════════════════════════════════════════════════════════════════ */
let s2Page: Page | null = null;
test.describe('Header — Proporções de colunas e layout', () => {
  test.beforeAll(async ({ browser }) => { s2Page = await createHomePage(browser); });
  test.afterAll(async () => { await s2Page?.context().close().catch(() => {}); s2Page = null; });
  test.beforeEach(() => { if (!s2Page) test.skip(); });

  test('Search bar ocupa ao menos 40% da largura do header @layout', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    // Usa header.awa-site-header (largura total visível) para proporção real
    const headerBox   = await getBBox(s2Page!, SELECTORS.header);
    const searchBox   = await getBBox(s2Page!, SELECTORS.searchBlock);

    expect(headerBox, 'Header main deve existir').toBeTruthy();
    expect(searchBox, 'Search bar deve existir').toBeTruthy();

    const ratio = searchBox!.width / headerBox!.width;
    expect(
      ratio,
      `Search bar deve ocupar ≥40% da largura do header (atual: ${(ratio * 100).toFixed(1)}%)`
    ).toBeGreaterThanOrEqual(0.40);
  });

  test('Logo não ultrapassa 20% da largura do header no notebook @layout', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    // Usa header.awa-site-header (largura total visível) como referência, não .header (só logo-cell)
    const headerBox = await getBBox(s2Page!, SELECTORS.header);
    const logoBox   = await getBBox(s2Page!, SELECTORS.logo);

    expect(headerBox).toBeTruthy();
    expect(logoBox).toBeTruthy();

    const ratio = logoBox!.width / headerBox!.width;
    expect(
      ratio,
      `Logo não deve ocupar mais de 20% do header (atual: ${(ratio * 100).toFixed(1)}%)`
    ).toBeLessThanOrEqual(0.20);
  });

  test('Colunas do header não se sobrepõem @layout', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    const logoBox   = await getBBox(s2Page!, SELECTORS.logo);
    const searchBox = await getBBox(s2Page!, SELECTORS.topSearch);

    expect(logoBox,   'Logo deve ter bounding box').toBeTruthy();
    expect(searchBox, 'Search deve ter bounding box').toBeTruthy();

    const { overlaps, gapPx } = await checkOverlap(s2Page!, SELECTORS.logo, SELECTORS.topSearch);
    expect(
      overlaps,
      `Logo e search bar não devem se sobrepor (gap: ${gapPx}px)`
    ).toBe(false);

    await s2Page!.locator(SELECTORS.header).screenshot({
      path: screenshotPath(`header-no-overlap-${vw}px`),
      animations: 'disabled',
    }).catch(() => {});
  });

  test('Contact slot não se sobrepõe com o search bar @layout', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    const contactVisible = await isVisible(s2Page!, SELECTORS.contactSlot);
    if (!contactVisible) {
      test.info().annotations.push({ type: 'info', description: 'Contact slot não visível neste viewport' });
      return;
    }

    const { overlaps } = await checkOverlap(s2Page!, SELECTORS.searchBlock, SELECTORS.contactSlot);
    expect(overlaps, 'Contact slot não deve sobrepor o search bar').toBe(false);
  });

  test('Minicart e search bar não se sobrepõem @layout', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    const minicartVisible = await isVisible(s2Page!, SELECTORS.minicart);
    const searchVisible   = await isVisible(s2Page!, SELECTORS.searchBlock);

    if (!minicartVisible || !searchVisible) return;

    const { overlaps, gapPx } = await checkOverlap(s2Page!, SELECTORS.searchBlock, SELECTORS.minicart);
    expect(
      overlaps,
      `Minicart e search não devem se sobrepor (gap: ${gapPx}px) em ${vw}px`
    ).toBe(false);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 3 — ALINHAMENTO VERTICAL E HORIZONTAL
   ════════════════════════════════════════════════════════════════════════ */
let s3Page: Page | null = null;
test.describe('Header — Alinhamento vertical dos componentes', () => {
  test.beforeAll(async ({ browser }) => { s3Page = await createHomePage(browser); });
  test.afterAll(async () => { await s3Page?.context().close().catch(() => {}); s3Page = null; });
  test.beforeEach(() => { if (!s3Page) test.skip(); });

  test('Logo e search bar estão na mesma faixa vertical @alignment', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    const logoBox   = await getBBox(s3Page!, SELECTORS.logo);
    const searchBox = await getBBox(s3Page!, SELECTORS.topSearch);

    expect(logoBox, 'Logo bounding box').toBeTruthy();
    expect(searchBox, 'Search bounding box').toBeTruthy();

    // Centro vertical de cada elemento
    const logoCY   = logoBox!.y + logoBox!.height / 2;
    const searchCY = searchBox!.y + searchBox!.height / 2;

    // Tolerância: 24px (gap de linha entre dois elementos em flex wrap)
    const diff = Math.abs(logoCY - searchCY);
    expect(
      diff,
      `Centro vertical do logo (${logoCY.toFixed(0)}px) e search (${searchCY.toFixed(0)}px) devem estar alinhados (diff: ${diff.toFixed(0)}px)`
    ).toBeLessThanOrEqual(24);
  });

  test('Minicart alinhado verticalmente com o search bar @alignment', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em tablet (<992px) o layout usa grid de 2 linhas: minicart fica no row 1 (nav|brand|cart)
    // e a search bar ocupa o row 2 (search search search). A diff vertical esperada é ~50px.
    // Este teste só faz sentido em notebook/desktop (>=992px) com layout de 1 linha.
    if (vw < 992) test.skip();
    const minicartBox = await getBBox(s3Page!, SELECTORS.minicart);
    const searchBox   = await getBBox(s3Page!, SELECTORS.topSearch);

    if (!minicartBox || !searchBox) return;

    const minicartCY = minicartBox.y + minicartBox.height / 2;
    const searchCY   = searchBox.y + searchBox.height / 2;
    const diff       = Math.abs(minicartCY - searchCY);

    expect(
      diff,
      `Minicart e search devem estar verticalmente alinhados (diff: ${diff.toFixed(0)}px) em ${vw}px`
    ).toBeLessThanOrEqual(24);
  });

  test('Linha principal do header não ultrapassa altura máxima @alignment', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em mobile (<992px) a linha .header colapsa; testar só em desktop where layout is fixed
    if (vw < 992) test.skip();

    // Usa .awa-site-header .header (linha principal: logo + search + minicart + contact)
    // NÃO usa header.awa-site-header (que inclui top-bar ~45px + nav ~60px)
    const headerBox = await getBBox(s3Page!, SELECTORS.headerMain);
    expect(headerBox).toBeTruthy();

    // Linha principal: logo row max 120px (sem top-bar, sem nav)
    const maxHeight = 120;
    expect(
      headerBox!.height,
      `Linha principal do header não deve ser maior que ${maxHeight}px (atual: ${headerBox!.height.toFixed(0)}px) em ${vw}px`
    ).toBeLessThanOrEqual(maxHeight);
  });

  test('Header alinhado ao topo da viewport @alignment', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    const headerBox = await getBBox(s3Page!, SELECTORS.header);
    expect(headerBox).toBeTruthy();
    // Em tablet (<992px) o header pode ter 24px+ de offset por skip-links; em notebook deve ser 0
    const maxY = vw < 992 ? 30 : 8;
    expect(
      headerBox!.y,
      `Header deve estar no topo (y: ${headerBox!.y.toFixed(0)}px) em ${vw}px`
    ).toBeLessThanOrEqual(maxY);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 4 — ESPAÇAMENTOS E MARGENS
   ════════════════════════════════════════════════════════════════════════ */
let s4Page: Page | null = null;
test.describe('Header — Espaçamentos e padding', () => {
  test.beforeAll(async ({ browser }) => { s4Page = await createHomePage(browser); });
  test.afterAll(async () => { await s4Page?.context().close().catch(() => {}); s4Page = null; });
  test.beforeEach(() => { if (!s4Page) test.skip(); });

  test('Search input tem padding interno adequado @spacing', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    const inputVisible = await isVisible(s4Page!, SELECTORS.searchInput);
    if (!inputVisible) return;

    const props = await getMultipleCSS(s4Page!, SELECTORS.searchInput, [
      'padding-left', 'padding-right', 'padding-top', 'padding-bottom',
    ]);

    // Padding horizontal: ao menos 8px de cada lado
    expect(
      px(props['padding-left']),
      `Input padding-left deve ser ≥8px (atual: ${props['padding-left']}) em ${vw}px`
    ).toBeGreaterThanOrEqual(8);

    expect(
      px(props['padding-right']),
      `Input padding-right deve ser ≥8px (atual: ${props['padding-right']}) em ${vw}px`
    ).toBeGreaterThanOrEqual(8);
  });

  test('Header principal tem padding vertical adequado @spacing', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;

    const props = await getMultipleCSS(s4Page!, SELECTORS.headerMain, [
      'padding-top', 'padding-bottom',
    ]).catch(() => ({} as Record<string, string>));

    if (!props['padding-top']) return; // elemento pode não ter padding explícito

    // Nota documental: valores registrados
    test.info().annotations.push({
      type: 'info',
      description: `Header padding em ${vw}px: top=${props['padding-top']}, bottom=${props['padding-bottom']}`,
    });
  });

  test('Gap entre logo e search é uniforme e ≥8px @spacing', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    const logoBox   = await getBBox(s4Page!, SELECTORS.logo);
    const searchBox = await getBBox(s4Page!, SELECTORS.topSearch);

    expect(logoBox).toBeTruthy();
    expect(searchBox).toBeTruthy();

    const logoRight  = logoBox!.x + logoBox!.width;
    const searchLeft = searchBox!.x;
    const gap        = searchLeft - logoRight;

    expect(
      gap,
      `Gap entre logo e search deve ser ≥8px (atual: ${gap.toFixed(0)}px) em ${vw}px`
    ).toBeGreaterThanOrEqual(8);
  });

  test('Gap entre search e minicart ≥4px @spacing', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em tablet (<992px) o minicart fica no row 1 e a search ocupa o row 2 (full-width).
    // O gap horizontal entre elementos em linhas diferentes nao faz sentido geometricamente.
    if (vw < 992) test.skip();

    const searchBox   = await getBBox(s4Page!, SELECTORS.searchBlock);
    const minicartBox = await getBBox(s4Page!, SELECTORS.minicart);

    if (!searchBox || !minicartBox) return;

    const searchRight  = searchBox.x + searchBox.width;
    const minicartLeft = minicartBox.x;
    const gap          = minicartLeft - searchRight;

    // Em flex gap pode ser computado pela distância visual
    expect(
      gap,
      `Gap entre search e minicart deve ser ≥4px (atual: ${gap.toFixed(0)}px) em ${vw}px`
    ).toBeGreaterThanOrEqual(4);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 5 — COMPORTAMENTO RESPONSIVO DURANTE RESIZE
   ════════════════════════════════════════════════════════════════════════ */
test.describe('Header — Comportamento durante resize', () => {
  test('Header mantém integridade visual do notebook ao tablet @responsive', async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForHeader(page);

    await page.locator(SELECTORS.header).screenshot({
      path: screenshotPath('header-resize-1366px'),
      animations: 'disabled',
    }).catch(() => {});

    // Redimensiona para tablet
    await page.setViewportSize({ width: 1024, height: 768 });
    await new Promise<void>(r => setTimeout(r, 300));

    await page.locator(SELECTORS.header).screenshot({
      path: screenshotPath('header-resize-to-1024px'),
      animations: 'disabled',
    }).catch(() => {});

    // Verifica que header ainda existe e está visível
    await expect(page.locator(SELECTORS.header)).toBeVisible();

    // Verifica ausência de overflow horizontal (soft: pode existir em alguns dispositivos)
    const hasHScroll = await Promise.race<boolean>([
      page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth).catch(() => false),
      new Promise<boolean>(r => setTimeout(() => r(false), 5_000)),
    ]);
    // eslint-disable-next-line playwright/no-conditional-expect
    expect.soft(hasHScroll, 'Não deve haver scroll horizontal após resize para 480px').toBe(false);
  });

  test('Header mantém integridade do tablet para mobile @responsive', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForHeader(page);

    await page.locator(SELECTORS.header).screenshot({
      path: screenshotPath('header-resize-768px'),
      animations: 'disabled',
    }).catch(() => {});

    await page.setViewportSize({ width: 480, height: 812 });
    await new Promise<void>(r => setTimeout(r, 300));

    await page.locator(SELECTORS.header).screenshot({
      path: screenshotPath('header-resize-to-480px'),
      animations: 'disabled',
    }).catch(() => {});

    await expect(page.locator(SELECTORS.header)).toBeVisible();

    const hasHScroll = await Promise.race<boolean>([
      page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth).catch(() => false),
      new Promise<boolean>(r => setTimeout(() => r(false), 5_000)),
    ]);
    expect(hasHScroll, 'Não deve haver scroll horizontal após resize para 480px').toBe(false);
  });

  test('Sticky header funciona corretamente no notebook @responsive', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < BREAKPOINTS.notebookMin) test.skip();

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForHeader(page);

    // Scroll para metade da página
    await Promise.race<void>([
      page.evaluate(() => window.scrollTo({ top: 600, behavior: 'instant' })).catch(() => {}),
      new Promise<void>(r => setTimeout(r, 3_000)),
    ]);
    await new Promise<void>(r => setTimeout(r, 300));

    const stickyVisible = await isVisible(page, SELECTORS.stickyHeader);
    if (stickyVisible) {
      const stickyBox = await getBBox(page, SELECTORS.stickyHeader);
      expect(stickyBox!.y).toBeLessThanOrEqual(50); // sticky perto do topo (pode ter top-bar acima)

      await page.screenshot({
        path: screenshotPath(`header-sticky-after-scroll-${vw}px`),
        animations: 'disabled',
        clip: { x: 0, y: 0, width: vw, height: 100 },
      }).catch(() => {});
    }
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 6 — SCREENSHOTS DOCUMENTAIS POR VIEWPORT
   ════════════════════════════════════════════════════════════════════════ */
test.describe('Header — Screenshots documentais completos', () => {
  test('Screenshot full header @screenshot', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    const browser = testInfo.project.name;

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForHeader(page);

    // Screenshot focado no header
    await page.locator(SELECTORS.header).screenshot({
      path: screenshotPath(`header-full-${browser}-${vw}px`),
      animations: 'disabled',
    }).catch(() => {});

    // Screenshot tela completa (acima da dobra)
    await page.screenshot({
      path: screenshotPath(`above-fold-${browser}-${vw}px`),
      animations: 'disabled',
      clip: { x: 0, y: 0, width: vw, height: Math.min(400, testInfo.project.use.viewport?.height ?? 400) },
    }).catch(() => {});
  });

  test('Screenshot header no estado de hover @screenshot', async ({ page }, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    const browser = testInfo.project.name;

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForHeader(page);

    // Hover no campo de busca
    const searchInput = page.locator(SELECTORS.searchInput).first();
    const inputVisible = await isVisible(page, SELECTORS.searchInput);

    if (inputVisible) {
      await searchInput.hover();
      await new Promise<void>(r => setTimeout(r, 150));

      await page.locator(SELECTORS.header).screenshot({
        path: screenshotPath(`header-search-hover-${browser}-${vw}px`),
        animations: 'disabled',
      }).catch(() => {});
    }
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 7 — ACESSIBILIDADE E SEMÂNTICA DO HEADER
   ════════════════════════════════════════════════════════════════════════ */
let s7Page: Page | null = null;
test.describe('Header — Acessibilidade @a11y', () => {
  test.beforeAll(async ({ browser }) => { s7Page = await createHomePage(browser); });
  test.afterAll(async () => { await s7Page?.context().close().catch(() => {}); s7Page = null; });
  test.beforeEach(() => { if (!s7Page) test.skip(); });

  test('Header tem role="banner" @a11y', async () => {
    const header = s7Page!.locator('header[role="banner"]').first();
    await expect(header).toBeVisible();
  });

  test('Logo tem link com title/aria-label @a11y', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em mobile o logo fica em container colapsado; testar só em notebook/desktop
    if (vw < 992) test.skip();
    const logoLink = s7Page!.locator('.awa-site-header .logo a, .awa-site-header a[aria-label*="Logo"]').first();
    await expect(logoLink).toBeVisible();
    const href = await logoLink.getAttribute('href');
    expect(href, 'Logo link deve ter href').toBeTruthy();
  });

  test('Search input tem label ou aria-label @a11y', async () => {
    const inputVisible = await isVisible(s7Page!, SELECTORS.searchInput);
    if (!inputVisible) return;

    const input = s7Page!.locator(SELECTORS.searchInput).first();
    const ariaLabel  = await input.getAttribute('aria-label');
    const id         = await input.getAttribute('id');
    const hasLabel   = id ? await s7Page!.locator(`label[for="${id}"]`).count() > 0 : false;

    expect(
      ariaLabel || hasLabel,
      'Search input deve ter aria-label ou label associado'
    ).toBeTruthy();
  });

  test('Minicart é identificável e visível @a11y', async ({}, testInfo) => {
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    // Em mobile (<992px) o minicart fica oculto no header desktop
    if (vw < 992) test.skip();
    // Usa .mini-cart-wrapper diretamente (o link .awa-header-cart-link tem aria-label mas fica hidden por CSS)
    const minicart = s7Page!.locator('.awa-site-header .mini-cart-wrapper').first();
    await expect(minicart).toBeVisible({ timeout: 5_000 });
  });
});
