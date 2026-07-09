import { test, expect } from '@playwright/test';

const BASE_URL = process.env.AWA_BASE_URL || 'https://awamotos.com';

const VIEWPORTS = [
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'tablet-768', width: 768, height: 1024 },
  { name: 'laptop-1366', width: 1366, height: 768 },
  { name: 'desktop-1920', width: 1920, height: 1080 },
];

const PAGES = [
  {
    path: '/',
    label: 'home',
    selector: '[role="banner"], header.page-header, .page-header',
  },
  {
    path: '/bauletos.html',
    label: 'plp',
    selector: '.page-main, #maincontent',
  },
  {
    path: '/b2b/account/login/',
    label: 'b2b-login',
    selector: 'form, .login-container, .form-login',
  },
  {
    path: '/checkout/cart',
    label: 'cart',
    selector: '.cart-container, .page-title, main',
  },
];

for (const vp of VIEWPORTS) {
  test.describe(`viewport ${vp.name}`, () => {
    test.use({ viewport: { width: vp.width, height: vp.height } });

    for (const pageDef of PAGES) {
      test(`${pageDef.label} loads without horizontal overflow`, async ({ page }) => {
        await page.goto(`${BASE_URL}${pageDef.path}`, {
          waitUntil: 'domcontentloaded',
          timeout: 60000,
        });
        const overflow = await page.evaluate(() => {
          return document.documentElement.scrollWidth > document.documentElement.clientWidth + 1;
        });
        expect(overflow, 'horizontal overflow detected').toBe(false);
      });

      test(`${pageDef.label} has primary layout region`, async ({ page }) => {
        await page.goto(`${BASE_URL}${pageDef.path}`, {
          waitUntil: 'domcontentloaded',
          timeout: 60000,
        });
        await expect(page.locator(pageDef.selector).first()).toBeVisible({ timeout: 30000 });
      });
    }
  });
}

test('design tokens exposed on home', async ({ page }) => {
  await page.goto(BASE_URL, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await expect(page.locator('[role="banner"], header.page-header').first()).toBeVisible({
    timeout: 60000,
  });

  await page.waitForFunction(
    () => {
      const root = getComputedStyle(document.documentElement);
      const body = getComputedStyle(document.body);
      const red = root.getPropertyValue('--awa-red').trim() || body.getPropertyValue('--awa-red').trim();
      const pad =
        root.getPropertyValue('--awa-page-pad').trim() || body.getPropertyValue('--awa-page-pad').trim();
      return red.length > 0 || pad.length > 0;
    },
    { timeout: 45000 }
  );

  const tokens = await page.evaluate(() => {
    const root = getComputedStyle(document.documentElement);
    const body = getComputedStyle(document.body);
    const pick = (prop: string) =>
      root.getPropertyValue(prop).trim() || body.getPropertyValue(prop).trim();
    return {
      gapXs: pick('--awa-gap-xs'),
      gapLg: pick('--awa-gap-lg'),
      red: pick('--awa-red'),
      pagePad: pick('--awa-page-pad'),
    };
  });

  const hasLayout =
    tokens.gapXs.includes('4') || tokens.gapLg.includes('16') || tokens.pagePad.length > 0;
  expect(hasLayout || tokens.red.length > 0).toBe(true);
});
