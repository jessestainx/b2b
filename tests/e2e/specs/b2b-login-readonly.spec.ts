import { test, expect } from '@playwright/test';
import { targetUrl, withCacheBuster } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

const LOGIN_URL = targetUrl('/b2b/account/login/', 'b2b-login-readonly');

test.describe('B2B login — QA visual read-only', () => {
  test.describe.configure({ timeout: 60_000 });

  test.beforeEach(async ({ page }) => {
    await blockOptionalThirdParty(page.context());
    const response = await page.goto(withCacheBuster(LOGIN_URL, 'login-smoke'), {
      waitUntil: 'domcontentloaded',
      timeout: 60_000,
    });

    expect(response?.status(), 'Login B2B deve responder 2xx/3xx').toBeLessThan(400);
    await page.waitForSelector('#b2b-email, form, .b2b-login-form, .b2b-login-shell', { timeout: 30_000 });
  });

  test('renderiza estrutura principal sem submeter dados', async ({ page }) => {
    await expect(page.locator('#b2b-email, input[name="login[username]"]').first()).toBeVisible();
    await expect(page.locator('#b2b-pass, input[type="password"]').first()).toBeVisible();
    await expect(page.locator('button[type="submit"], .b2b-btn-entrar').first()).toBeVisible();
  });

  test('não tem overflow horizontal', async ({ page }) => {
    const overflowX = await page.evaluate(() => {
      return Math.max(0, document.body.scrollWidth - document.documentElement.clientWidth);
    });

    expect(overflowX, 'Login B2B não deve gerar overflow horizontal').toBeLessThanOrEqual(1);
  });

  test('CTA principal respeita alvo minimo de 44px', async ({ page }) => {
    const cta = page.locator('button[type="submit"], .b2b-btn-entrar').first();
    await expect(cta).toBeVisible();
    const box = await cta.boundingBox();

    expect(box?.height ?? 0, 'CTA de login deve ter altura minima de 44px').toBeGreaterThanOrEqual(44);
    expect(box?.width ?? 0, 'CTA de login deve ter largura minima de 44px').toBeGreaterThanOrEqual(44);
  });

  test('foco do campo de email fica visivel', async ({ page }) => {
    const email = page.locator('#b2b-email, input[name="login[username]"]').first();
    await email.focus();

    const focusVisible = await email.evaluate((el) => {
      const style = window.getComputedStyle(el);
      return (
        style.outlineStyle !== 'none' ||
        style.boxShadow !== 'none' ||
        style.borderColor !== window.getComputedStyle(document.body).backgroundColor
      );
    });

    expect(focusVisible, 'Campo de email deve apresentar estado de foco visivel').toBe(true);
  });

  test('link de cadastro B2B existe e aponta para o fluxo correto', async ({ page }) => {
    const registerLink = page.locator('a[href*="/b2b/register"], a[href*="b2b/register"]').first();
    await expect(registerLink).toBeVisible();
    await expect(registerLink).toHaveAttribute('href', /b2b\/register/);
  });
});
