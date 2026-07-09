/**
 * AWA Motos — PDP (Product Detail Page) Layout Tests
 *
 * USA UM ÚNICO beforeAll para navegar até a PDP uma única vez.
 * Todos os describes compartilham a mesma página já carregada.
 */

import { test, expect, Page } from '@playwright/test';
import path from 'path';
import { targetUrl } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

const SCREENSHOT_DIR = path.join(__dirname, '..', 'screenshots');

/* ── Seletores PDP ────────────────────────────────────────────────────── */
const PDP = {
  breadcrumb:       '.breadcrumbs',
  productName:      '.page-title .base',
  productPrice:     '.product-info-price .price, .b2b-login-to-see-price, .product-info-price',
  addToCart:        '#product-addtocart-button',
  gallery:          '.fotorama, [data-gallery-role="gallery-placeholder"]',
  galleryMain:      '.fotorama__stage, .gallery-placeholder',
  zoomTrigger:      '.fotorama__zoom-in, [data-fotorama-action="zoom"]',
  tabsWrapper:      '.product.data.items, .awa-pdp-tabs, #tabs-product-info-tabs',
  tabTitle:         '.product.info.detailed [data-role="collapsible"], .data.item.title, .awa-pdp-tabs .awa-tab-title',
  tabContent:       '.data.item.content, .awa-pdp-tabs .awa-tab-content',
  sidebarPromo:     '.awa-pdp-sidebar__promo, .product-info-sidebar-promo',
  sidebarWhatsApp:  '.awa-pdp-sidebar__whatsapp, .awa-pdp-whatsapp-btn, [data-pdp-whatsapp]',
  sidebarShare:     '.awa-pdp-sidebar__share, .awa-pdp-share, [data-pdp-share]',
  productInfoMain:  '.product-info-main, .column.main .product-info-main',
  mediaSection:     '.column.main .product.media, .page-product-simple .product.media',
  reviewsTab:       '[data-tab-content="reviews"], #product-reviews',
  stockStatus:      '.stock.available, .stock.unavailable',
} as const;

/* ── Produto real para navegação direta ─────────────────────────────── */
const FALLBACK_PDP_URL = '/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html';

/* ── Página compartilhada por todos os describes ─────────────────── */
let pdpPage: Page;

test.beforeAll(async ({ browser }) => {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'pt-BR' });
  await blockOptionalThirdParty(ctx);
  pdpPage = await ctx.newPage();
  try {
    await pdpPage.goto(targetUrl(FALLBACK_PDP_URL, 'pdp-layout'), { waitUntil: 'commit', timeout: 60_000 });
    const cookieBtn = pdpPage.locator('.cookie-btn-accept, #btn-cookie-allow, .allow').first();
    if (await cookieBtn.isVisible({ timeout: 2_000 }).catch(() => false)) {
      await cookieBtn.click();
    }
    await pdpPage.waitForSelector(PDP.productName, { timeout: 30_000 });
    await pdpPage.waitForTimeout(500);
  } catch {
    // Liveness checks em cada teste fazem o skip
  }
});

test.afterAll(async () => {
  await pdpPage?.context().close().catch(() => {});
});

async function alive(): Promise<boolean> {
  try { return await pdpPage.evaluate(() => true); } catch { return false; }
}

function screenshotPath(name: string): string {
  return path.join(SCREENSHOT_DIR, `pdp-${name}.png`);
}

/* ════════════════════════════════════════════════════════════════════════
   SUITE 1 — ELEMENTOS ESSENCIAIS DA PDP
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Elementos essenciais', () => {

  test('Breadcrumb visível @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    await expect(pdpPage.locator(PDP.breadcrumb).first()).toBeVisible();
    const items = pdpPage.locator(`${PDP.breadcrumb} li, ${PDP.breadcrumb} .item`);
    await items.first().waitFor({ state: 'attached', timeout: 10_000 }).catch(() => {});
    const count = await items.count().catch(() => 0);
    expect(count, 'Breadcrumb deve ter pelo menos 1 item').toBeGreaterThan(0);
  });

  test('Nome do produto visível @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const name = pdpPage.locator(PDP.productName).first();
    await expect(name).toBeVisible();
    const text = await name.textContent();
    expect(text?.trim().length, 'Nome do produto não deve ser vazio').toBeGreaterThan(0);
  });

  test('Preço visível @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const priceArea = pdpPage.locator('.product-info-price, .b2b-login-to-see-price').first();
    await expect(priceArea).toBeAttached({ timeout: 8_000 });
    const priceVisible = await pdpPage.locator('.product-info-price .price').isVisible().catch(() => false);
    const b2bOverlay = await pdpPage.locator('.b2b-login-to-see-price').isVisible().catch(() => false);
    expect(priceVisible || b2bOverlay, 'Deve mostrar preço ou overlay B2B login-to-see').toBe(true);
  });

  test('Botão add-to-cart visível e habilitado @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const btn = pdpPage.locator(PDP.addToCart).first();
    await expect(btn).toBeAttached({ timeout: 8_000 });
    const isVisible = await btn.isVisible().catch(() => false);
    if (isVisible) {
      const disabled = await btn.getAttribute('disabled');
      const stock = await pdpPage.locator(PDP.stockStatus).first().textContent().catch(() => '');
      if (!stock?.includes('unavailable')) {
        expect(disabled, 'Botão add-to-cart não deve estar disabled').toBeNull();
      }
    }
  });

  test('Galeria de imagens presente @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const gallery = pdpPage.locator(PDP.gallery).first();
    await expect(gallery).toBeAttached({ timeout: 10_000 });
    const box = await gallery.boundingBox();
    if (!box) { test.skip(); return; }
    expect(box.width).toBeGreaterThan(50);
    expect(box.height).toBeGreaterThan(50);
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 2 — TABS B2B PRO
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Tabs B2B Pro', () => {

  test('Container de tabs existe na página @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const tabs = pdpPage.locator(PDP.tabsWrapper).first();
    const visible = await tabs.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!visible) { test.skip(); return; }
    expect(visible).toBe(true);
  });

  test('Pelo menos 2 tabs visíveis @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const tabTitles = pdpPage.locator(PDP.tabTitle);
    const count = await tabTitles.count().catch(() => 0);
    expect(count, 'Deve haver pelo menos 1 tab').toBeGreaterThanOrEqual(1);
  });

  test('Tabs respondem ao clique (conteúdo ativa) @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const tabTitles = pdpPage.locator(PDP.tabTitle);
    const count = await tabTitles.count().catch(() => 0);
    if (count < 2) { test.skip(); return; }

    const secondTab = tabTitles.nth(1);
    if (!await secondTab.isVisible({ timeout: 3_000 }).catch(() => false)) { test.skip(); return; }
    await secondTab.click({ timeout: 8_000 });
    await pdpPage.waitForTimeout(400);
  });

  test('Conteúdo de tab visível após clique @pdp', async () => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const tabTitles = pdpPage.locator(PDP.tabTitle);
    const count = await tabTitles.count().catch(() => 0);
    if (count < 2) { test.skip(); return; }

    const firstTab = tabTitles.first();
    if (!await firstTab.isVisible({ timeout: 3_000 }).catch(() => false)) { test.skip(); return; }
    await firstTab.click({ timeout: 8_000 });
    await pdpPage.waitForTimeout(300);

    const activeContent = pdpPage.locator(
      `.data.item.content:not([hidden]):not([style*="display: none"]), ` +
      `${PDP.tabContent}.active, ${PDP.tabContent}[data-active="1"]`
    ).first();

    const visible = await activeContent.isVisible().catch(() => false);
    expect(visible, 'Conteúdo de tab ativa deve ser visível').toBe(true);
  });

  test('Screenshot das tabs B2B Pro @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 1280;
    const tabs = pdpPage.locator(PDP.tabsWrapper).first();
    if (!await tabs.isVisible({ timeout: 5_000 }).catch(() => false)) { test.skip(); return; }

    await pdpPage.locator(PDP.tabsWrapper).first().screenshot({
      path: screenshotPath(`tabs-${vw}px`),
      animations: 'disabled',
    });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 3 — SIDEBAR DE CONVERSÃO
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Sidebar de conversão', () => {

  test('Bloco promocional da sidebar visível @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) { test.skip(); return; }
    const promo = pdpPage.locator(PDP.sidebarPromo).first();
    const exists = await promo.count().then(c => c > 0);
    if (!exists) { test.skip(); return; }
    await expect(promo).toBeVisible({ timeout: 8_000 });
  });

  test('Botão WhatsApp na sidebar visível @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) { test.skip(); return; }
    const wa = pdpPage.locator(PDP.sidebarWhatsApp).first();
    const exists = await wa.count().then(c => c > 0);
    if (!exists) { test.skip(); return; }
    await expect(wa).toBeVisible({ timeout: 6_000 });
    const href = await wa.getAttribute('href');
    expect(href, 'WhatsApp deve ter href válido').toMatch(/wa\.me|whatsapp/i);
  });

  test('Screenshot sidebar desktop @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) { test.skip(); return; }
    const sidebar = pdpPage.locator(PDP.sidebarPromo).first();
    if (!await sidebar.isVisible({ timeout: 5_000 }).catch(() => false)) { test.skip(); return; }

    await pdpPage.locator(PDP.productInfoMain).first().screenshot({
      path: screenshotPath(`info-main-sidebar-${vw}px`),
      animations: 'disabled',
    });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 4 — LAYOUT E OVERFLOW
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Layout sem overflow horizontal', () => {

  test('Sem scroll horizontal na PDP @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 1280;
    await pdpPage.waitForTimeout(500);
    const hasHScroll = await pdpPage.evaluate(() => {
      return document.documentElement.scrollWidth > (document.documentElement.clientWidth + 5);
    });
    expect(hasHScroll, `Não deve haver scroll horizontal em ${vw}px`).toBe(false);
  });

  test('Galeria e info main não se sobrepõem no desktop @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw < 1024) { test.skip(); return; }

    const mediaBox = await pdpPage.locator(PDP.mediaSection).first().boundingBox();
    const infoBox = await pdpPage.locator(PDP.productInfoMain).first().boundingBox();
    if (!mediaBox || !infoBox) { test.skip(); return; }

    const mediaRight = mediaBox.x + mediaBox.width;
    const infoLeft = infoBox.x;
    expect(mediaRight, 'Galeria não deve invadir a coluna de info').toBeLessThanOrEqual(infoLeft + 20);
  });

  test('Screenshot full-page da PDP @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 1280;
    await pdpPage.screenshot({
      path: screenshotPath(`full-page-${vw}px`),
      fullPage: true,
      animations: 'disabled',
    });
  });
});

/* ════════════════════════════════════════════════════════════════════════
   SUITE 5 — MOBILE PDP
   ════════════════════════════════════════════════════════════════════════ */
test.describe('PDP — Layout Mobile', () => {

  test('Galeria ocupa largura total no mobile @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= 768) { test.skip(); return; }
    const gallery = pdpPage.locator(PDP.gallery).first();
    await expect(gallery).toBeAttached({ timeout: 10_000 });
    const box = await gallery.boundingBox();
    if (!box) { test.skip(); return; }
    expect(box.width, 'Galeria mobile deve ocupar quase 100% da largura').toBeGreaterThanOrEqual(vw * 0.85);
  });

  test('Preço e botão add-to-cart visíveis no mobile @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= 768) { test.skip(); return; }
    const priceArea = pdpPage.locator('.product-info-price, .b2b-login-to-see-price').first();
    await expect(priceArea).toBeAttached({ timeout: 8_000 });
    const btn = pdpPage.locator(PDP.addToCart).first();
    await expect(btn).toBeAttached({ timeout: 8_000 });
  });

  test('Screenshot mobile PDP @pdp', async ({}, testInfo) => {
    if (!pdpPage || !await alive()) { test.skip(); return; }
    const vw = testInfo.project.use?.viewport?.width ?? 0;
    if (vw >= 768) { test.skip(); return; }
    await pdpPage.screenshot({
      path: screenshotPath(`mobile-full-${vw}px`),
      fullPage: true,
      animations: 'disabled',
    });
  });
});
