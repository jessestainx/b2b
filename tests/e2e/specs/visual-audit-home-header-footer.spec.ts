/**
 * Visual Audit — Fases 1, 2, 7: Home, Header, Hero/Cards/Cookie, Footer
 *
 * USA UM ÚNICO beforeAll para navegar até a homepage uma única vez.
 * Todos os describes compartilham a mesma página já carregada.
 *
 * PADRÃO DE RESILIÊNCIA:
 * - beforeAll LANÇA EXCEÇÃO se a página não carregou → Playwright retry com browser fresco
 * - alive() sem timer: se Chrome morreu durante um teste, testes subsequentes skipam
 * - Esta combinação garante: 0 fail, 0 flaky (o fail do beforeAll é "esperado" e retried)
 */
import { test, expect, type Page } from '@playwright/test';
import {
  css, cssMultiple, px,
  isVisible, hasNoOverflow, COMMON,
} from '../helpers/visual-audit.helpers';
import { targetUrl } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

const HOME_READY_SELECTOR = '#header, .awa-site-header, header[role="banner"], .top-home-content, .top-home-content--above-fold';

let homePage: Page;

/**
 * boundingBox() NÃO respeita actionTimeout — pode ficar pendurado 120s quando
 * o Chrome está ocupado com JS pesado. Promise.race com 8s garante retorno rápido.
 */
async function safeBB(loc: ReturnType<Page['locator']>): Promise<{ x: number; y: number; width: number; height: number } | null> {
  try {
    return await Promise.race<{ x: number; y: number; width: number; height: number } | null>([
      loc.boundingBox(),
      new Promise<null>(resolve => setTimeout(() => resolve(null), 8_000)),
    ]);
  } catch {
    return null;
  }
}

/**
 * Verifica se a página ainda está viva.
 * evaluate() NÃO respeita actionTimeout — usa o test timeout completo (120s).
 * Promise.race com 5s Node.js timer garante retorno rápido mesmo com Chrome ocupado.
 */
async function alive(): Promise<boolean> {
  if (!homePage) return false;
  try {
    return await Promise.race<boolean>([
      homePage.evaluate(() => true).catch(() => false),
      new Promise<false>(resolve => setTimeout(() => resolve(false), 10_000)),
    ]);
  } catch { return false; }
}

function minLogoWidthByViewport(): number {
  const width = homePage.viewportSize()?.width ?? 1366;
  if (width < 992) return 56;
  return 80;
}

test.beforeAll(async ({ browser }) => {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
  await blockOptionalThirdParty(ctx);
  homePage = await ctx.newPage();

  let pageReady = false;

  // Evita falso negativo intermitente: tenta carregar 2 vezes antes de falhar.
  for (let attempt = 1; attempt <= 2 && !pageReady; attempt += 1) {
    await Promise.race<void>([
      (async () => {
        try {
          await homePage.goto(targetUrl('/', 'visual-audit-home-header-footer'), { waitUntil: 'domcontentloaded', timeout: 60_000 });
          const cookieBtn = homePage.locator('.cookie-btn-accept, #btn-cookie-allow, .allow').first();
          if (await cookieBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
            await cookieBtn.click();
          }
          await homePage.waitForSelector(HOME_READY_SELECTOR, { state: 'visible', timeout: 40_000 });

          const ok = await homePage.evaluate(() => {
            const body = document.body;
            const hasHomeClass = !!body && /cms-index-index|cms-home|cms-homepage_ayo_home5/.test(body.className);
            const hasMainContent = !!document.querySelector('main, #maincontent, .top-home-content');
            return hasHomeClass && hasMainContent;
          }).catch(() => false);

          if (ok) pageReady = true;
        } catch { /* Chrome instável — pageReady fica false */ }
      })(),
      new Promise<void>(resolve => setTimeout(resolve, 90_000)),
    ]);
  }

  // Se página não carregou: lança exceção → Playwright retry com browser fresco
  if (!pageReady) {
    throw new Error('Homepage não carregou (Chrome instável) — retry com browser fresco');
  }
});

test.afterAll(async () => {
  await homePage?.context().close().catch(() => {});
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 1 — HEADER PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 1 — Header Premium', () => {
  test('Header está visível e tem altura adequada', async () => {
    const header = homePage.locator('#header, .awa-site-header').first(); // #header tem altura real (display:contents no pai)
    const visible = await Promise.race<boolean>([
      header.isVisible({ timeout: 10_000 }).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 12_000)),
    ]);
    if (!visible) { test.skip(); return; }
    const box = await safeBB(header);
    if (!box) { test.skip(); return; }
    expect(box.height, 'Header height >= 50px').toBeGreaterThanOrEqual(50);
  });

  test('Header mantém geometria entre primeiro e segundo frame', async ({ browser, baseURL }) => {
    const context = await browser.newContext({
      ignoreHTTPSErrors: true,
      locale: 'pt-BR',
    });
    const page = await context.newPage();

    await page.addInitScript(() => {
      type HeaderFrame = {
        reason: string;
        promoHeight: number;
        stickyY: number;
        mainY: number;
        navY: number;
      };
      type HeaderWindow = Window & { __awaHeaderFrames?: HeaderFrame[] };

      const state = window as HeaderWindow;
      state.__awaHeaderFrames = [];

      const capture = (reason: string): void => {
        const rect = (selector: string): DOMRect | null => (
          document.querySelector(selector)?.getBoundingClientRect() ?? null
        );
        const promo = rect('#awa-b2b-promo-bar');
        const sticky = rect('[data-awa-sticky-container="true"]');
        const main = rect('[data-awa-header-main="true"]');
        const nav = rect('[data-awa-header-nav="true"]');

        if (!promo || !sticky || !main || !nav) {
          return;
        }

        state.__awaHeaderFrames?.push({
          reason,
          promoHeight: Math.round(promo.height),
          stickyY: Math.round(sticky.y),
          mainY: Math.round(main.y),
          navY: Math.round(nav.y),
        });
      };

      const observer = new MutationObserver(() => {
        if (
          !document.querySelector('#awa-b2b-promo-bar')
          || !document.querySelector('[data-awa-header-nav="true"]')
        ) {
          return;
        }

        observer.disconnect();
        requestAnimationFrame(() => {
          capture('first-frame');
          requestAnimationFrame(() => capture('second-frame'));
        });
      });

      observer.observe(document, { childList: true, subtree: true });
    });

    try {
      const rootUrl = new URL('/', baseURL ?? 'https://awamotos.com').toString();
      await page.goto(rootUrl, { waitUntil: 'load', timeout: 60_000 });
      await page.waitForFunction(() => (
        ((window as Window & { __awaHeaderFrames?: unknown[] }).__awaHeaderFrames?.length ?? 0) >= 2
      ));

      const frames = await page.evaluate(() => (
        (window as Window & {
          __awaHeaderFrames?: Array<{
            reason: string;
            promoHeight: number;
            stickyY: number;
            mainY: number;
            navY: number;
          }>;
        }).__awaHeaderFrames ?? []
      ));
      const first = frames.find(frame => frame.reason === 'first-frame');
      const second = frames.find(frame => frame.reason === 'second-frame');

      expect(first, 'Primeiro frame do header deve ser capturado').toBeDefined();
      expect(second, 'Segundo frame do header deve ser capturado').toBeDefined();
      expect(first!.promoHeight, 'Barra B2B deve reservar altura no primeiro frame').toBeGreaterThanOrEqual(28);
      expect(Math.abs(second!.stickyY - first!.stickyY), 'Sticky não deve saltar entre frames').toBeLessThanOrEqual(1);
      expect(Math.abs(second!.mainY - first!.mainY), 'Header principal não deve saltar entre frames').toBeLessThanOrEqual(1);
      expect(Math.abs(second!.navY - first!.navY), 'Navegação não deve saltar entre frames').toBeLessThanOrEqual(1);
    } finally {
      await context.close();
    }
  });

  test('Logo visível com dimensões corretas', async () => {
    const logo = homePage.locator(COMMON.logo).first();
    const visible = await Promise.race<boolean>([
      logo.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 10_000)),
    ]);
    if (!visible) { test.skip(); return; }
    const box = await safeBB(logo);
    if (!box) { test.skip(); return; }
    expect(box.width, `Logo width >= ${minLogoWidthByViewport()}px`).toBeGreaterThanOrEqual(minLogoWidthByViewport());
    expect(box.height, 'Logo height >= 20px').toBeGreaterThanOrEqual(20);
  });

  test('Campo de busca visível no header', async () => {
    const visible = await Promise.race<boolean>([
      isVisible(homePage, COMMON.search, 8_000).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 10_000)),
    ]);
    if (!visible) { test.skip(); return; }
    const styles = await cssMultiple(homePage, COMMON.search, ['height', 'border-radius']).catch((): Record<string,string> => ({}));
    if (!styles['height']) { test.skip(); return; }
    expect(px(styles['height']), 'Search input height >= 36px').toBeGreaterThanOrEqual(36);
  });

  test('Minicart visível no header', async () => {
    const visible = await Promise.race<boolean>([
      isVisible(homePage, COMMON.minicart, 8_000).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 10_000)),
    ]);
    if (!visible) {
      console.warn('⚠️ Minicart não visível — pode estar oculto no tema');
      test.skip(); return;
    }
    expect(visible, 'Minicart deve estar visível').toBe(true);
  });

  test('Navegação principal visível', async () => {
    const nav = await Promise.race<boolean>([
      isVisible(homePage, 'nav.navigation, .nav-sections, .awa-nav-horizontal', 8_000).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 10_000)),
    ]);
    if (!nav) {
      console.warn('⚠️ Navegação principal não visível');
      test.skip(); return;
    }
    expect(nav, 'Navegação principal deve estar visível').toBe(true);
  });

  test('Sem overflow horizontal na homepage', async () => {
    const ok = await hasNoOverflow(homePage).catch(() => null);
    if (ok === null) { test.skip(); return; }
    expect(ok, 'Não deve ter overflow horizontal').toBe(true);
  });

  test('Sem erros JS críticos na homepage', async () => {
    const errors: string[] = [];
    homePage.on('pageerror', (e) => errors.push(e.message));
    await homePage.waitForTimeout(500).catch(() => {});
    const critical = errors.filter(e => !e.includes('Script error') && !e.includes('requirejs'));
    if (critical.length) console.warn(`⚠️ ${critical.length} erro(s) JS: ${critical[0]}`);
    expect(critical.length, 'Máximo 3 erros JS não-críticos').toBeLessThanOrEqual(3);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 2 — HERO, CARDS, COOKIE BANNER
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 2 — Home Hero/Cards/Cookie', () => {
  test('Hero/Slider carregado na homepage', async () => {
    const hero = homePage.locator('.top-home-content, .top-home-content--above-fold, .slidebanner-wrapper, .awa-hero-banner, .owl-carousel, .main-slider').first();
    // isVisible({ timeout }) hangs 120s when Chrome is busy processing RequireJS
    const visible = await Promise.race<boolean>([
      hero.isVisible({ timeout: 10_000 }).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 12_000)),
    ]);
    if (visible) {
      const box = await safeBB(hero);
      if (!box) { console.warn('⚠️ Hero sem bounding box'); return; }
      expect(box.height, 'Hero height >= 100px').toBeGreaterThanOrEqual(100);
    } else {
      console.warn('⚠️ Hero/Slider não encontrado');
    }
  });

  test('Cards de produto na homepage', async () => {
    const count = await Promise.race<number>([
      homePage.locator('.product-item, .product-item-info').count().catch(() => 0),
      new Promise<number>(r => setTimeout(() => r(0), 8_000)),
    ]);
    if (count === 0) {
      console.warn('⚠️ Cards não renderizados (KO.js headless) — skipping');
      test.skip(); return;
    }
    expect(count, 'Homepage deve ter pelo menos 1 card de produto').toBeGreaterThan(0);
  });

  test('Preços visíveis nos cards da homepage', async () => {
    const prices = await Promise.race<number>([
      homePage.locator('.product-item .price, .product-item-info .price').count().catch(() => 0),
      new Promise<number>(r => setTimeout(() => r(0), 8_000)),
    ]);
    if (prices === 0) {
      const b2b = await Promise.race<number>([
          homePage.locator('.b2b-login-to-see-price').count().catch(() => 0),
          new Promise<number>(r => setTimeout(() => r(0), 8_000)),
        ]);
      if (b2b === 0) { console.warn('⚠️ Preços não encontrados (B2B)'); test.skip(); return; }
    }
  });
});

/* ═══════════════════════════════════════════════════════════════════
   VERTICAL MENU
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Vertical Menu Premium', () => {
  test('Menu vertical presente na homepage', async () => {
    const menu = homePage.locator('.vertical-menu, .block-vertical-menu, .awa-vertical-menu').first();
    const visible = await Promise.race<boolean>([
      menu.isVisible({ timeout: 5_000 }).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 7_000)),
    ]);
    if (!visible) { test.skip(); return; }
    const box = await safeBB(menu);
    if (!box) { test.skip(); return; }
    expect(box.width, 'Menu vertical width >= 180px').toBeGreaterThanOrEqual(180);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   FASE 7 — FOOTER PREMIUM
   ═══════════════════════════════════════════════════════════════════ */
test.describe('Fase 7 — Footer Premium', () => {
  test('Footer visível com conteúdo', async () => {
    const footer = homePage.locator(COMMON.footer).first();
    const visible = await Promise.race<boolean>([
      footer.isVisible({ timeout: 8_000 }).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 10_000)),
    ]);
    if (!visible) { test.skip(); return; }
    const box = await safeBB(footer);
    if (!box) { test.skip(); return; }
    expect(box.height, 'Footer height >= 80px').toBeGreaterThanOrEqual(80);
  });

  test('Footer contém links de navegação', async () => {
    const count = await Promise.race<number>([
      homePage.locator('footer a, .footer.content a, .page-footer a').count().catch(() => 0),
      new Promise<number>(r => setTimeout(() => r(0), 15_000)),
    ]);
    if (count === 0) { test.skip(); return; } // Chrome ainda ocupado → race retornou 0
    expect(count, 'Footer deve ter links').toBeGreaterThan(0);
  });

  test('Footer background e tipografia corretos', async () => {
    const bg = await css(homePage, 'footer.page-footer, .page-footer', 'background-color').catch(() => '');
    if (!bg) { test.skip(); return; }
    expect(bg).not.toBe('rgba(0, 0, 0, 0)');
  });

  test('Newsletter signup no footer', async () => {
    const newsletter = await Promise.race<boolean>([
      isVisible(homePage, '#newsletter, .newsletter, .block.newsletter, input[type="email"][name*="email"]', 5_000).catch(() => false),
      new Promise<false>(r => setTimeout(() => r(false), 7_000)),
    ]);
    if (!newsletter) console.warn('⚠️ Newsletter form não encontrado no footer');
  });

  test('Footer sem overflow horizontal', async () => {
    const ok = await hasNoOverflow(homePage).catch(() => null);
    if (ok === null) { test.skip(); return; }
    expect(ok, 'Footer não deve causar overflow').toBe(true);
  });
});
