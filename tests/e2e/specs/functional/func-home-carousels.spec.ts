/**
 * AWA Motos — smoke carrosséis da home
 * Hero (SlideBanner), categorias (#awa-cat-carousel), vitrines Owl (Mais Vendidos, etc.)
 * flex-grid-flow + ui-ux-pro-max: touch 44px, hover estável (sem translateY/scale)
 */
import { test, expect, type Locator, type Page, type TestInfo } from '@playwright/test';
import { dismissCookie, navigateTo } from '../../helpers/visual-audit.helpers';

const HOME = '/';
const TOUCH_MIN = 44;
const TOUCH_TOLERANCE = 2;

async function activateAsyncCss(page: Page): Promise<void> {
  await Promise.race([
    page.evaluate(() => {
      document.querySelectorAll('link[rel="stylesheet"][media="print"]').forEach((link) => {
        (link as HTMLLinkElement).media = 'all';
      });
    }),
    page.waitForTimeout(12_000),
  ]).catch(() => {});
  await page.waitForTimeout(400);
}

async function gotoHomeReady(page: Page): Promise<void> {
  const ok = await navigateTo(page, `https://awamotos.com${HOME}`);
  if (!ok) {
    test.skip(true, 'Home não carregou');
  }
  await dismissCookie(page);
  await activateAsyncCss(page);
}

const isMobileProject = (testInfo: TestInfo): boolean =>
  testInfo.project.name.includes('mobile');

/** Owl + awa-carousel-nav.js — aguarda RequireJS/Owl e slide dimensionado (IntersectionObserver) */
async function waitForBestsellerOwlReady(rail: Locator): Promise<void> {
  await rail.scrollIntoViewIfNeeded({ timeout: 30_000 });
  await rail.evaluate((el) => {
    el.scrollIntoView({ block: 'center', inline: 'nearest' });
    window.dispatchEvent(new Event('resize'));
  });
  await rail.hover({ force: true });

  const page = rail.page();
  await page.waitForTimeout(1500);

  await page
    .waitForFunction(
      () => {
        const w = window as unknown as { jQuery?: { fn?: { owlCarousel?: unknown } } };
        return typeof w.jQuery !== 'undefined' && !!w.jQuery?.fn?.owlCarousel;
      },
      undefined,
      { timeout: 25_000 }
    )
    .catch(() => {});

  await rail.evaluate((root) => {
    const ul = root.querySelector('ul.owl');
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const jq = (window as any).jQuery;
    if (!ul || !jq || !jq.fn || !jq.fn.owlCarousel) {
      return;
    }
    const api = jq(ul).data('owlCarousel');
    if (api && typeof api.reload === 'function') {
      api.reload();
    }
    window.dispatchEvent(new Event('resize'));
  });

  await expect
    .poll(
      async () =>
        rail.evaluate((root) => {
          const navBtn =
            root.querySelector<HTMLElement>('.awa-owl-nav__btn--next') ||
            root.querySelector<HTMLElement>('.owl-controls .owl-buttons > .owl-next');
          if (navBtn) {
            const cs = getComputedStyle(navBtn);
            const r = navBtn.getBoundingClientRect();
            if (cs.display !== 'none' && r.width >= 42 && r.height >= 42) {
              return true;
            }
          }
          const items = Array.from(root.querySelectorAll<HTMLElement>('.owl-item'));
          return items.some((el) => el.getBoundingClientRect().width >= 80);
        }),
      {
        timeout: 55_000,
        intervals: [400, 800, 1500, 2500],
      }
    )
    .toBe(true);
}

/** Estado de navegação Owl — scrollPerPage salta vários itens; usar API + transform do stage */
async function readOwlNavState(section: Locator): Promise<{ currentItem: number; stageTransform: string }> {
  return section.evaluate((root) => {
    const ul = root.querySelector('ul.owl');
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const jq = (window as any).jQuery;
    const api = ul && jq ? jq(ul).data('owlCarousel') : null;
    const stage = root.querySelector('.owl-stage, .owl-wrapper');
    return {
      currentItem: typeof api?.currentItem === 'number' ? api.currentItem : -1,
      stageTransform: stage ? getComputedStyle(stage).transform : '',
    };
  });
}

test.describe('Home — carrosséis @carousel', () => {
  test.setTimeout(180_000);

  test.beforeEach(async ({ page }) => {
    await gotoHomeReady(page);
  });

  test('01 — carrossel de categorias visível com itens', async ({ page }) => {
    const section = page.locator('.top-home-content--category-carousel').first();
    await section.scrollIntoViewIfNeeded({ timeout: 30_000 });
    const track = section.locator('#awa-cat-carousel, .awa-category-carousel__track').first();
    await expect(track).toBeAttached({ timeout: 30_000 });
    await expect(track).toBeVisible({ timeout: 20_000 });
    const items = section.locator('.awa-category-carousel__item');
    expect(await items.count()).toBeGreaterThanOrEqual(3);
  });

  test('02 — setas categorias ≥44px (desktop)', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), 'Setas de categorias no desktop; mobile usa reel');
    await page.setViewportSize({ width: 1280, height: 800 });
    await activateAsyncCss(page);

    const section = page.locator('.top-home-content--category-carousel').first();
    await section.scrollIntoViewIfNeeded();

    const prev = section.locator('.awa-category-carousel__prev');
    const next = section.locator('.awa-category-carousel__next');

    await expect(prev).toHaveCount(1, { timeout: 15_000 });
    await expect(next).toHaveCount(1, { timeout: 15_000 });
    await expect(prev).toBeVisible({ timeout: 10_000 });

    const sizes = await page.evaluate(() => {
      const measure = (el: Element | null) => {
        if (!el) return null;
        const r = el.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height) };
      };
      return {
        prev: measure(document.querySelector('.awa-category-carousel__prev')),
        next: measure(document.querySelector('.awa-category-carousel__next')),
      };
    });

    for (const side of ['prev', 'next'] as const) {
      const s = sizes[side];
      expect(s, `${side} ausente`).not.toBeNull();
      if (s) {
        expect(s.w, `${side} width`).toBeGreaterThanOrEqual(TOUCH_MIN - TOUCH_TOLERANCE);
        expect(s.h, `${side} height`).toBeGreaterThanOrEqual(TOUCH_MIN - TOUCH_TOLERANCE);
      }
    }
  });

  test('03 — hover card categoria sem translateY (desktop)', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), 'Hover fine pointer — desktop');
    await page.setViewportSize({ width: 1280, height: 800 });
    await activateAsyncCss(page);

    const item = page.locator('.awa-category-carousel__item').first();
    await item.scrollIntoViewIfNeeded();
    await item.hover({ force: true });
    await page.waitForTimeout(250);

    const transform = await item.evaluate((el) => getComputedStyle(el).transform);
    expect(transform === 'none' || transform === 'matrix(1, 0, 0, 1, 0, 0)').toBe(true);
  });

  test('04 — vitrine Owl: setas ≥44px e navegação', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), '.awa-owl-nav oculto no mobile — swipe no reel');
    await page.setViewportSize({ width: 1280, height: 800 });
    await dismissCookie(page);
    await activateAsyncCss(page);

    const section = page
      .locator('.awa-carousel-section')
      .filter({ has: page.locator('.rokan-bestseller') })
      .first();
    const rail = section.locator('.rokan-bestseller').first();
    await expect(rail).toBeAttached({ timeout: 45_000 });
    await rail.scrollIntoViewIfNeeded({ timeout: 45_000 });
    await expect(rail).toBeVisible({ timeout: 20_000 });
    await waitForBestsellerOwlReady(rail);
    await dismissCookie(page);
    await rail.hover({ force: true });
    await page.waitForTimeout(300);

    const sizes = await rail.evaluate((root) => {
      const btn =
        root.querySelector<HTMLElement>('.awa-owl-nav__btn--next') ||
        root.querySelector<HTMLElement>('.owl-controls .owl-buttons > .owl-next');
      if (!btn) return null;
      const r = btn.getBoundingClientRect();
      return { w: Math.round(r.width), h: Math.round(r.height), source: btn.className };
    });
    expect(sizes, 'botão next do Owl').not.toBeNull();
    if (sizes) {
      expect(sizes.w, JSON.stringify(sizes)).toBeGreaterThanOrEqual(TOUCH_MIN - TOUCH_TOLERANCE);
      expect(sizes.h, JSON.stringify(sizes)).toBeGreaterThanOrEqual(TOUCH_MIN - TOUCH_TOLERANCE);
    }

    const before = await readOwlNavState(rail);

    await rail.evaluate((root) => {
      const btn = root.querySelector<HTMLElement>('.awa-owl-nav__btn--next');
      btn?.click();
    });

    await expect
      .poll(async () => {
        const after = await readOwlNavState(rail);
        return (
          after.currentItem > before.currentItem ||
          (after.stageTransform !== before.stageTransform &&
            after.stageTransform !== 'matrix(1, 0, 0, 1, 0, 0)')
        );
      }, { timeout: 8_000, intervals: [200, 400, 800] })
      .toBe(true);
  });

  test('05 — card produto no Owl: hover sem lift/zoom (desktop)', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), 'Hover fine pointer — desktop');
    await page.setViewportSize({ width: 1280, height: 800 });
    await activateAsyncCss(page);

    const card = page
      .locator('.rokan-bestseller .item-product, .content-item-product')
      .first();
    await card.scrollIntoViewIfNeeded();
    await card.hover({ force: true });
    await page.waitForTimeout(250);

    const metrics = await card.evaluate((el) => {
      const img = el.querySelector('.product-image-photo, .product-thumb img');
      return {
        cardTransform: getComputedStyle(el).transform,
        imgTransform: img ? getComputedStyle(img).transform : null,
        hasShadow: getComputedStyle(el).boxShadow !== 'none',
      };
    });

    const isIdentity = (t: string | null) =>
      !t || t === 'none' || t === 'matrix(1, 0, 0, 1, 0, 0)';

    expect(isIdentity(metrics.cardTransform)).toBe(true);
    expect(isIdentity(metrics.imgTransform)).toBe(true);
    expect(metrics.hasShadow).toBe(true);
  });

  test('06 — hero SlideBanner visível', async ({ page }) => {
    const hero = page.locator('.wrapper_slider').filter({ visible: true }).first();
    await expect(hero).toBeVisible({ timeout: 25_000 });
    await expect(hero.locator('.banner_item_bg img').first()).toBeVisible({ timeout: 30_000 });
  });

  test('07 — mobile: sem overflow horizontal na página', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo), 'Cenário mobile');
    await page.setViewportSize({ width: 375, height: 812 });
    await activateAsyncCss(page);

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4
    );
    expect(overflow, 'overflow horizontal na home mobile').toBe(false);
  });

  test('09 — card Owl: largura preenche o slide (desktop)', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), 'Layout Owl desktop');
    await page.setViewportSize({ width: 1280, height: 800 });
    await dismissCookie(page);
    await activateAsyncCss(page);

    const section = page
      .locator('.awa-carousel-section')
      .filter({ has: page.locator('.rokan-bestseller') })
      .first();
    await section.scrollIntoViewIfNeeded({ timeout: 30_000 });

    const rail = section.locator('.rokan-bestseller').first();
    await expect(rail.locator('.content-item-product').first()).toBeVisible({ timeout: 45_000 });
    await waitForBestsellerOwlReady(rail);

    const metrics = await rail.evaluate((root) => {
      const items = Array.from(root.querySelectorAll<HTMLElement>('.owl-item'));
      const item =
        items.find((el) => el.classList.contains('active') && el.getBoundingClientRect().width >= 80) ||
        items.find((el) => el.getBoundingClientRect().width >= 80) ||
        items[0];
      if (!item) {
        return null;
      }
      const card =
        item.querySelector<HTMLElement>('.content-item-product') ||
        item.querySelector<HTMLElement>('.awa-carousel-card-slot');
      if (!card) {
        return null;
      }
      const slideW = item.getBoundingClientRect().width;
      const cardW = card.getBoundingClientRect().width;
      return {
        slotW: Math.round(slideW),
        cardW: Math.round(cardW),
        ratio: slideW > 0 ? cardW / slideW : 0,
      };
    });

    expect(metrics, 'estrutura AWA card no Owl').not.toBeNull();
    if (!metrics) {
      return;
    }

    expect(metrics.ratio, JSON.stringify(metrics)).toBeGreaterThanOrEqual(0.45);

    if (metrics.slotW >= 100) {
      expect(metrics.cardW, JSON.stringify(metrics)).toBeGreaterThanOrEqual(80);
      expect(metrics.ratio, JSON.stringify(metrics)).toBeGreaterThanOrEqual(0.75);
    }
  });

  test('10 — Lançamentos: mesma estrutura de card que Mais Vendidos', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), 'Layout Owl desktop');
    await page.setViewportSize({ width: 1280, height: 800 });
    await activateAsyncCss(page);

    const section = page
      .locator('.awa-carousel-section[aria-label*="Lan"], .awa-carousel-section[aria-label*="lan"]')
      .filter({ has: page.locator('.rokan-newproduct') })
      .first();
    await section.scrollIntoViewIfNeeded({ timeout: 30_000 });

    const awaCards = section.locator('.rokan-newproduct .content-item-product');
    if ((await awaCards.count()) === 0) {
      test.skip(true, 'Lançamentos ainda sem template AWA (content-item-product) em produção.');
      return;
    }

    await expect(awaCards.first()).toBeVisible({ timeout: 15_000 });
    await expect(section.locator('.awa-carousel-card-slot').first()).toBeVisible({ timeout: 15_000 });
  });

  test('08 — mobile: carrossel categorias scrollável (reel)', async ({ page }, testInfo) => {
    test.skip(!isMobileProject(testInfo), 'Cenário mobile');
    await page.setViewportSize({ width: 390, height: 844 });
    await dismissCookie(page);
    await activateAsyncCss(page);

    const section = page.locator('.top-home-content--category-carousel').first();
    await section.scrollIntoViewIfNeeded({ timeout: 30_000 });
    const track = section.locator('#awa-cat-carousel, .awa-category-carousel__track').first();
    await expect(track).toBeAttached({ timeout: 30_000 });

    const scrollable = await track.evaluate((el) => el.scrollWidth > el.clientWidth + 8);
    expect(scrollable, 'track de categorias deve permitir scroll horizontal').toBe(true);
  });

  test('11 — Super Ofertas: nav ≥44px e cards flex (desktop)', async ({ page }, testInfo) => {
    test.skip(isMobileProject(testInfo), 'Setas super-ofertas no desktop');
    await page.setViewportSize({ width: 1280, height: 800 });
    await dismissCookie(page);
    await activateAsyncCss(page);

    const section = page.locator('.awa-carousel-section--super-offers').first();
    if ((await section.count()) === 0) {
      test.skip(true, 'Seção super-ofertas ausente na home atual.');
      return;
    }

    await section.scrollIntoViewIfNeeded({ timeout: 45_000 });
    await expect(section).toBeVisible({ timeout: 20_000 });

    const rail = section.locator('.awa-super-offers-carousel, .hot-deal').first();
    await expect(rail).toBeAttached({ timeout: 30_000 });
    await waitForBestsellerOwlReady(rail);

    const sizes = await rail.evaluate((root) => {
      const btn =
        root.querySelector<HTMLElement>('.awa-owl-nav__btn--next') ||
        root.querySelector<HTMLElement>('.owl-controls .owl-buttons > .owl-next');
      if (!btn) return null;
      const r = btn.getBoundingClientRect();
      return { w: Math.round(r.width), h: Math.round(r.height) };
    });
    expect(sizes, 'botão next super-ofertas').not.toBeNull();
    if (sizes) {
      expect(sizes.w).toBeGreaterThanOrEqual(TOUCH_MIN - TOUCH_TOLERANCE);
      expect(sizes.h).toBeGreaterThanOrEqual(TOUCH_MIN - TOUCH_TOLERANCE);
    }

    const card = section
      .locator('.content-item-product, .item-product, .hot-deal .product-item')
      .first();
    await expect(card).toBeVisible({ timeout: 15_000 });

    const flexCol = await card.evaluate((el) => getComputedStyle(el).flexDirection === 'column');
    expect(flexCol, 'card super-ofertas em coluna flex').toBe(true);
  });
});
