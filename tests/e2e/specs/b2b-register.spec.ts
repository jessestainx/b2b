import { test, expect } from '@playwright/test';
import { targetUrl, withCacheBuster } from '../helpers/target-url';
import { blockOptionalThirdParty } from '../helpers/third-party-block';

const REGISTER_URL = targetUrl(process.env.B2B_REGISTER_URL || '/b2b/register/', 'b2b-register');

test.describe('B2B register — auth shell e wizard', () => {
  test.describe.configure({ timeout: 60_000 });

  test.beforeEach(async ({ page }) => {
    await blockOptionalThirdParty(page.context());
    await page.goto(withCacheBuster(REGISTER_URL, 'register-smoke'), {
      waitUntil: 'domcontentloaded',
      timeout: 60_000,
    });
    await page.waitForSelector('#b2b-register-form.is-ready', { timeout: 30_000 });
  });

  test('renderiza shell, stepper e navegação por etapas', async ({ page }) => {
    await expect(page.locator('#b2b-register-shell')).toBeVisible();
    await expect(page.locator('.b2b-login-title.register-title')).toBeVisible();
    await expect(page.locator('.b2b-register-progress')).toBeVisible();
    await expect(page.locator('[data-register-step-nav]')).toBeVisible();
    await expect(page.locator('.b2b-register-step-next')).toBeVisible();
    await expect(page.locator('.b2b-register-step-prev')).toBeDisabled();
  });

  test('submit fica oculto até a etapa final', async ({ page }) => {
    const submitToolbar = page.locator('#b2b-register-form .actions-toolbar.b2b-login-actions');

    await expect(page.locator('#b2b-register-form')).not.toHaveClass(/is-register-final-step/);
    await expect(submitToolbar).toHaveAttribute('hidden', 'hidden');
    await expect(page.locator('#b2b-register-form .terms-section')).toHaveAttribute('hidden', 'hidden');
  });

  test('continuar sem preencher mantém na etapa 1', async ({ page }) => {
    await page.locator('.b2b-register-step-next').click();
    await expect(page.locator('.progress-step.is-active')).toHaveAttribute('data-step-number', '1');
    await expect(page.locator('#register-step-company')).toBeVisible();
  });

  test('CTA secundário de login espelha o fluxo do auth shell', async ({ page }) => {
    const loginCta = page.locator('.b2b-register-secondary-ctas .b2b-btn-claim');
    await expect(loginCta).toBeVisible();
    await expect(loginCta).toHaveAttribute('href', /b2b\/account\/login/);
  });

  test('mobile mantém stepper e navegação utilizáveis', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await expect(page.locator('.b2b-register-progress')).toBeVisible();
    await expect(page.locator('.b2b-register-step-next')).toBeVisible();
    await expect(page.locator('.b2b-login-whatsapp')).toBeVisible();
  });
});
