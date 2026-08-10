/**
 * Visual Audit — Core Regression Baselines
 *
 * Suíte dedicada para bugs visuais com snapshots focados nas áreas mais sensíveis:
 * - Header
 * - Menu
 * - Cards
 * - Produto
 * - Checkout
 * - Mobile
 * - Rodapé
 *
 * Preferencialmente execute via `pw-visual-audit.config.ts`, que já limita os projetos
 * aos breakpoints desktop/mobile mais úteis para baseline visual.
 */
import { expect, test, type Locator, type Page } from '@playwright/test';
import {
  COMMON,
  dismissCookie,
  navigateTo,
  waitForPage,
} from '../helpers/visual-audit.helpers';

const BASE = 'https://awamotos.com';
const URLS = {
  home: BASE,
  plp: `${BASE}/bagageiros.html`,
  pdp: `${BASE}/bagageiro-titan-125-modelo-00-04-fan-125-modelo-05-08-cromado-macico-3015.html`,
  checkout: `${BASE}/checkout/`,
} as const;

const DESKTOP_ONLY_REASON = 'Baseline visual desktop-only';
const MOBILE_ONLY_REASON = 'Baseline visual mobile-only';
const DEFAULT_MAX_DIFF = 0.05;
const CHECKOUT_MAX_DIFF = 0.08;
const CORE_DESKTOP_PROJECT = 'core-firefox-desktop-1280';
const CORE_MOBILE_PROJECT = 'core-firefox-mobile-375';
const HEADER_SELECTORS = ['#header', '.awa-site-header', 'header[role="banner"]', '[data-awa-header-content]'];
const MENU_SELECTORS = ['.vertical-menu', '.block-vertical-menu', '.awa-vertical-menu', '.nav-sections .navigation'];
const FOOTER_SELECTORS = ['footer.page-footer', '.footer.content'];
const PDP_SELECTORS = ['.product.media', '.product-info-main'];

type ClipRegion = {
  x: number;
  y: number;
  width: number;
  height: number;
};

async function stabilizePage(page: Page): Promise<void> {
  await waitForPage(page, 20_000);
  await dismissCookie(page).catch(() => {});
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-delay: 0s !important;
        animation-duration: 0s !important;
        transition-delay: 0s !important;
        transition-duration: 0s !important;
        caret-color: transparent !important;
        scroll-behavior: auto !important;
      }

      iframe[src*="tawk"],
      iframe[src*="chatwoot"],
      #tawk-bubble-container,
      .tawk-min-container,
      .chatwoot-widget,
      #chatwoot_live_chat_widget,
      .grecaptcha-badge,
      [id*="chatwoot"],
      [class*="chatwoot"] {
        opacity: 0 !important;
        visibility: hidden !important;
      }

      /* Cookie banner aparece via JS após o stabilize; ocultar de forma
         determinística para não poluir/overlaps nos baselines. */
      #awa-cookie-banner,
      .awa-cookie-banner {
        display: none !important;
      }
    `,
  }).catch(() => {});

  await page.evaluate(() => {
    document.querySelectorAll('input, textarea').forEach((element) => {
      (element as HTMLInputElement | HTMLTextAreaElement).blur();
    });
    window.scrollTo(0, 0);
  }).catch(() => {});

  await page.waitForTimeout(1_000);
}

async function openStable(page: Page, url: string): Promise<boolean> {
  const ok = await navigateTo(page, url);
  if (!ok) {
    return false;
  }

  try {
    await stabilizePage(page);
    return true;
  } catch {
    return false;
  }
}

/**
 * Espera as imagens visíveis na viewport (com margem) terminarem de carregar.
 * Evita layout shift de lazy-load entre o scroll e a captura do screenshot.
 */
async function waitForViewportImages(page: Page, timeoutMs = 4_000): Promise<void> {
  await page.evaluate(async (deadline) => {
    const pending = Array.from(document.images).filter((img) => {
      const rect = img.getBoundingClientRect();
      return !img.complete && rect.bottom > -300 && rect.top < window.innerHeight + 300;
    });

    if (pending.length === 0) {
      return;
    }

    await Promise.race([
      Promise.all(pending.map((img) => new Promise<void>((resolve) => {
        img.addEventListener('load', () => resolve(), { once: true });
        img.addEventListener('error', () => resolve(), { once: true });
      }))),
      new Promise<void>((resolve) => setTimeout(resolve, deadline)),
    ]);
  }, timeoutMs).catch(() => {});
}

/**
 * Abre o menu vertical de forma determinística (trigger → painel visível).
 * Sem isso o bounding box oscila entre trigger fechado (~206px) e painel aberto,
 * quebrando o baseline por diferença de dimensão do clip.
 */
async function openVerticalMenuPanel(page: Page): Promise<boolean> {
  const trigger = page.locator('[data-role="awa-vertical-menu-trigger"]').first();
  const panel = page.locator('[data-role="awa-vertical-menu-panel"]').first();

  if (!(await trigger.isVisible({ timeout: 3_000 }).catch(() => false))) {
    return false;
  }

  if (!(await panel.isVisible().catch(() => false))) {
    await trigger.click({ force: true }).catch(() => {});
    await panel.waitFor({ state: 'visible', timeout: 4_000 }).catch(() => {});
  }

  const ready = await safeWait(page, 600);
  return ready && (await panel.isVisible().catch(() => false));
}

function clampClip(box: ClipRegion, viewport: { width: number; height: number }): ClipRegion | null {
  const x = Math.max(0, Math.floor(box.x));
  const y = Math.max(0, Math.floor(box.y));
  const width = Math.min(viewport.width - x, Math.ceil(box.width));
  const height = Math.min(viewport.height - y, Math.ceil(box.height));

  if (width < 20 || height < 20) {
    return null;
  }

  return { x, y, width, height };
}

function isClosedTargetError(error: unknown): boolean {
  const message = error instanceof Error ? `${error.message}\n${error.stack ?? ''}` : String(error);

  return message.includes('Target page, context or browser has been closed')
    || message.includes('browser.newContext: Target page, context or browser has been closed')
    || message.includes('screencast.showOverlays: Target page, context or browser has been closed');
}

async function safeWait(page: Page, timeoutMs: number): Promise<boolean> {
  if (page.isClosed()) {
    return false;
  }

  await page.waitForTimeout(timeoutMs).catch(() => {});
  return !page.isClosed();
}

async function clipFromSelector(page: Page, selectors: string | string[], padding = 16): Promise<ClipRegion | null> {
  const selectorList = Array.isArray(selectors) ? selectors : [selectors];

  for (const selector of selectorList) {
    if (page.isClosed()) {
      return null;
    }

    const target = page.locator(selector).first();
    const visible = await target.isVisible({ timeout: 2_000 }).catch(() => false);
    if (!visible) {
      continue;
    }

    await target.scrollIntoViewIfNeeded().catch(() => {});
    const ready = await safeWait(page, 500);
    if (!ready) {
      return null;
    }

    const box = await target.boundingBox().catch(() => null);
    const viewport = page.viewportSize();
    if (!box || !viewport) {
      continue;
    }

    const clip = clampClip(
      {
        x: box.x - padding,
        y: box.y - padding,
        width: box.width + padding * 2,
        height: box.height + padding * 2,
      },
      viewport,
    );

    if (clip) {
      return clip;
    }
  }

  return null;
}

async function unionClipFromSelectors(page: Page, selectors: string[], padding = 20): Promise<ClipRegion | null> {
  const viewport = page.viewportSize();
  if (!viewport) {
    return null;
  }

  const boxes: ClipRegion[] = [];

  for (const selector of selectors) {
    const target = page.locator(selector).first();
    const visible = await target.isVisible({ timeout: 2_000 }).catch(() => false);
    if (!visible) {
      continue;
    }

    await target.scrollIntoViewIfNeeded().catch(() => {});
    const box = await target.boundingBox().catch(() => null);
    if (!box) {
      continue;
    }

    boxes.push({ x: box.x, y: box.y, width: box.width, height: box.height });
  }

  if (boxes.length === 0) {
    return null;
  }

  const left = Math.min(...boxes.map((box) => box.x));
  const top = Math.min(...boxes.map((box) => box.y));
  const right = Math.max(...boxes.map((box) => box.x + box.width));
  const bottom = Math.max(...boxes.map((box) => box.y + box.height));

  return clampClip(
    {
      x: left - padding,
      y: top - padding,
      width: right - left + padding * 2,
      height: bottom - top + padding * 2,
    },
    viewport,
  );
}

function topViewportClip(page: Page, preferredHeight: number): ClipRegion {
  const viewport = page.viewportSize() ?? { width: 1280, height: preferredHeight };

  return {
    x: 0,
    y: 0,
    width: viewport.width,
    height: Math.min(preferredHeight, viewport.height),
  };
}

async function addProductToCart(page: Page): Promise<boolean> {
  const ok = await openStable(page, URLS.pdp);
  if (!ok) {
    return false;
  }

  const swatch = page.locator('.swatch-attribute .swatch-option:not(.disabled)').first();
  if (await swatch.isVisible({ timeout: 1_500 }).catch(() => false)) {
    await swatch.click({ force: true }).catch(() => {});
    await page.waitForTimeout(300);
  }

  const addToCart = page.locator('#product-addtocart-button, button.action.tocart, button.tocart').first();
  const visible = await addToCart.isVisible({ timeout: 5_000 }).catch(() => false);
  if (!visible) {
    return false;
  }

  await addToCart.click({ force: true }).catch(() => {});
  await Promise.race([
    page.locator('.message-success, [data-ui-id="message-success"]').first().waitFor({ state: 'visible', timeout: 10_000 }).catch(() => {}),
    page.waitForLoadState('networkidle', { timeout: 12_000 }).catch(() => {}),
    page.waitForTimeout(4_000),
  ]).catch(() => {});

  return true;
}

async function openCheckoutReady(page: Page): Promise<boolean> {
  const directOpen = await openStable(page, URLS.checkout);
  if (directOpen && /\/checkout(\/|$)|expresscheckout/.test(page.url())) {
    await page.waitForTimeout(2_500);
    return true;
  }

  const added = await addProductToCart(page);
  if (!added) {
    return false;
  }

  const checkoutOpen = await openStable(page, URLS.checkout);
  if (!checkoutOpen) {
    return false;
  }

  await page.waitForTimeout(3_500);
  return /\/checkout(\/|$)|expresscheckout/.test(page.url());
}

async function expectRegionScreenshot(
  page: Page,
  name: string,
  clip: ClipRegion,
  maxDiffPixelRatio = DEFAULT_MAX_DIFF,
  mask: Locator[] = [],
): Promise<boolean> {
  if (page.isClosed()) {
    return false;
  }

  try {
    await expect(page).toHaveScreenshot(name, {
      clip,
      maxDiffPixelRatio,
      animations: 'disabled',
      mask,
    });
    return true;
  } catch (error) {
    if (isClosedTargetError(error)) {
      return false;
    }

    throw error;
  }
}

test.describe('Visual Audit — Core baselines', () => {
  test('Header — baseline desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== CORE_DESKTOP_PROJECT, DESKTOP_ONLY_REASON);

    const ok = await openStable(page, URLS.home);
    if (!ok) {
      test.skip();
      return;
    }

    const clip = await clipFromSelector(page, HEADER_SELECTORS);
    if (!clip) {
      test.skip();
      return;
    }

    const captured = await expectRegionScreenshot(page, 'core-header-desktop.png', clip, 0.04);
    if (!captured) {
      test.skip();
    }
  });

  test('Menu — baseline desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== CORE_DESKTOP_PROJECT, DESKTOP_ONLY_REASON);

    const ok = await openStable(page, URLS.home);
    if (!ok) {
      test.skip();
      return;
    }

    const menuOpen = await openVerticalMenuPanel(page);
    if (!menuOpen) {
      test.skip();
      return;
    }

    // Clip determinístico: container do menu com painel aberto (estado fixo).
    const clip = await clipFromSelector(page, ['[data-role="awa-vertical-menu"]', ...MENU_SELECTORS]);
    if (!clip) {
      test.skip();
      return;
    }

    const captured = await expectRegionScreenshot(page, 'core-menu-desktop.png', clip, 0.05);
    if (!captured) {
      test.skip();
    }
  });

  test('Cards — baseline desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== CORE_DESKTOP_PROJECT, DESKTOP_ONLY_REASON);

    const ok = await openStable(page, URLS.plp);
    if (!ok) {
      test.skip();
      return;
    }

    await page.evaluate(() => window.scrollTo(0, 520)).catch(() => {});
    const ready = await safeWait(page, 800);
    if (!ready) {
      test.skip();
      return;
    }

    // Lazy-load de imagens do grid desloca o conteúdo ~20px; espera e re-ancora.
    await waitForViewportImages(page);
    await page.evaluate(() => window.scrollTo(0, 520)).catch(() => {});
    if (!(await safeWait(page, 400))) {
      test.skip();
      return;
    }

    // Header sticky pode aparecer/esconder conforme o scroll; mascarar para
    // o baseline refletir apenas o grid de cards.
    const headerMask = HEADER_SELECTORS.map((selector) => page.locator(selector).first());
    const captured = await expectRegionScreenshot(
      page,
      'core-cards-desktop.png',
      topViewportClip(page, 720),
      0.06,
      headerMask,
    );
    if (!captured) {
      test.skip();
    }
  });

  test('Produto — baseline desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== CORE_DESKTOP_PROJECT, DESKTOP_ONLY_REASON);

    const ok = await openStable(page, URLS.pdp);
    if (!ok) {
      test.skip();
      return;
    }

    const clip = await unionClipFromSelectors(page, PDP_SELECTORS, 24);
    if (!clip) {
      test.skip();
      return;
    }

    // Blocos dinâmicos: urgência (estoque real), prova social (contador de
    // views do dia) e contagem de reviews; mascarar para baseline estável.
    const dynamicMask = [
      page.locator('.awa-pdp-urgency').first(),
      page.locator('.product-social-proof').first(),
      page.locator('.product-reviews-summary .reviews-actions').first(),
    ];
    const captured = await expectRegionScreenshot(page, 'core-product-desktop.png', clip, 0.05, dynamicMask);
    if (!captured) {
      test.skip();
    }
  });

  test('Checkout — baseline desktop', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== CORE_DESKTOP_PROJECT, DESKTOP_ONLY_REASON);

    const ok = await openCheckoutReady(page);
    if (!ok) {
      test.skip();
      return;
    }

    await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
    const ready = await safeWait(page, 1_000);
    if (!ready) {
      test.skip();
      return;
    }

    const captured = await expectRegionScreenshot(
      page,
      'core-checkout-desktop.png',
      topViewportClip(page, 720),
      CHECKOUT_MAX_DIFF,
    );
    if (!captured) {
      test.skip();
    }
  });

  test('Mobile — baseline homepage', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== CORE_MOBILE_PROJECT, MOBILE_ONLY_REASON);

    const ok = await openStable(page, URLS.home);
    if (!ok) {
      test.skip();
      return;
    }

    try {
      await expect(page).toHaveScreenshot('core-home-mobile.png', {
        maxDiffPixelRatio: 0.06,
        animations: 'disabled',
      });
    } catch (error) {
      if (isClosedTargetError(error)) {
        test.skip();
        return;
      }

      throw error;
    }
  });

});
