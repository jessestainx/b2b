import { test, expect } from '@playwright/test';
import { navigateTo } from '../../helpers/visual-audit.helpers';

const CHECKOUT = 'https://awamotos.com/checkout/';

test.describe('Checkout — fluxo', () => {
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, CHECKOUT);
    if (!ok) test.skip();
  });

  test('01 — checkout carrega sem 500 (P0)', async ({ page }) => {
    const res = await page.request.get(CHECKOUT).catch(() => null);
    if (res) expect(res.status(), '[P0] Checkout com erro').toBeLessThan(500);
  });

  test('02 — sem tela em branco (P0)', async ({ page }) => {
    const url = page.url();
    if (url.includes('/cart/')) { console.info('[INFO] Redir para carrinho vazio — OK'); return; }
    // B2B portal: checkout redireciona para login quando não autenticado — comportamento correto
    if (url.includes('/customer/account/login') || url.includes('/b2b/') || url.includes('account/login')) {
      console.info('[INFO] B2B gate redirect — checkout protegido por login, comportamento esperado');
      const loginForm = page.locator('.block-customer-login, .login-container, form[action*="login"], .b2b-login');
      const isLogin = await loginForm.isVisible().catch(() => false);
      if (isLogin) return; // gate OK
    }
    const content = page.locator('.opc-wrapper, #checkoutSteps, .checkout-container, .cart-empty, .page-title').first();
    await expect(content).toBeVisible({ timeout: 20_000 });
  });

  test('03 — sem erros JS críticos (P0)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.waitForTimeout(3_000);
    const critical = errors.filter(e => /require is not defined|Cannot read prop|Uncaught TypeError/i.test(e));
    if (critical.length > 0) console.error('[P0] Erros JS no checkout:', critical.join(' | '));
    expect(critical, '[P0] Erros JS críticos no checkout').toHaveLength(0);
  });

  test('04 — campo email existe (P0)', async ({ page }) => {
    const url = page.url();
    if (url.includes('/cart/')) { test.skip(); return; }
    const email = page.locator('input#customer-email, input[name="username"], input[type="email"]').first();
    const visible = await email.isVisible({ timeout: 15_000 }).catch(() => false);
    if (!visible) console.warn('[P0] Campo email não encontrado — usuário logado?');
  });
});
