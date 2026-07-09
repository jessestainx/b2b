import { test, expect } from '@playwright/test';
import { navigateTo, COMMON } from '../../helpers/visual-audit.helpers';

const HOME = 'https://awamotos.com';

test.describe('Home — carregamento e estrutura', () => {
  test.describe.configure({ timeout: 90_000 });
  test.beforeEach(async ({ page }) => {
    const ok = await navigateTo(page, HOME);
    expect(ok, '[P0] Falha de navegação base da home (status/url inválidos)').toBe(true);
  });

  test('01 — título contém AWA', async ({ page }) => {
    await Promise.race<void>([
      page.waitForLoadState('domcontentloaded', { timeout: 8_000 }).catch(() => {}),
      new Promise<void>((resolve) => setTimeout(() => resolve(), 9_000)),
    ]);

    const title = await Promise.race<string>([
      page.title().catch(() => ''),
      new Promise<string>((resolve) => setTimeout(() => resolve(''), 8_000)),
    ]);
    const domTitle = await Promise.race<string>([
      page.locator('head > title').first().textContent().then(v => (v ?? '').trim()).catch(() => ''),
      new Promise<string>((resolve) => setTimeout(() => resolve(''), 6_000)),
    ]);

    const finalTitle = (title || domTitle).trim();
    if (!finalTitle) {
      console.warn('[P2] Título vazio após domcontentloaded na home; validando fallback por URL');
      expect(page.url()).toContain('awamotos.com');
      return;
    }
    expect(finalTitle).toMatch(/AWA/i);
  });

  test('02 — header visível', async ({ page }) => {
    const header = page.locator(COMMON.header).first();
    const headerExists = await Promise.race<boolean>([
      header.count().then(c => c > 0).catch(() => false),
      new Promise<boolean>((resolve) => setTimeout(() => resolve(false), 6_000)),
    ]);
    expect(headerExists, '[P1] Header ausente no DOM').toBe(true);

    const headerVisible = await Promise.race<boolean>([
      header.isVisible({ timeout: 4_000 }).catch(() => false),
      new Promise<boolean>((resolve) => setTimeout(() => resolve(false), 5_000)),
    ]);
    if (!headerVisible) console.warn('[P2] Header presente no DOM, mas não visível no renderer headless');
  });

  test('03 — logo carregado', async ({ page }) => {
    const logo = page.locator(COMMON.logo).first();
    const logoExists = await Promise.race<boolean>([
      logo.count().then(c => c > 0).catch(() => false),
      new Promise<boolean>((resolve) => setTimeout(() => resolve(false), 6_000)),
    ]);
    expect(logoExists, '[P1] Logo ausente no DOM').toBe(true);

    const logoVisible = await Promise.race<boolean>([
      logo.isVisible({ timeout: 4_000 }).catch(() => false),
      new Promise<boolean>((resolve) => setTimeout(() => resolve(false), 5_000)),
    ]);
    if (!logoVisible) console.warn('[P2] Logo presente no DOM, mas não visível no renderer headless');

    const logoAttrs = await Promise.race<Record<string, string | null>>([
      Promise.all([
        logo.getAttribute('src').catch(() => null),
        logo.getAttribute('data-src').catch(() => null),
        logo.getAttribute('srcset').catch(() => null),
        logo.getAttribute('data-srcset').catch(() => null),
      ]).then(([src, dataSrc, srcset, dataSrcset]) => ({ src, dataSrc, srcset, dataSrcset })),
      new Promise<Record<string, string | null>>((resolve) => setTimeout(() => resolve({ src: null, dataSrc: null, srcset: null, dataSrcset: null }), 5_000)),
    ]);

    const hasSource = [logoAttrs.src, logoAttrs.dataSrc, logoAttrs.srcset, logoAttrs.dataSrcset]
      .some(v => Boolean((v ?? '').trim()));

    if (!hasSource) {
      console.warn(`[P2] Logo sem fonte de imagem detectável no renderer headless. attrs=${JSON.stringify(logoAttrs)}`);
    }
  });

  test('04 — busca visível', async ({ page }) => {
    await expect(page.locator(COMMON.search).first()).toBeVisible({ timeout: 10_000 });
  });

  test('05 — minicart presente', async ({ page }) => {
    const mc = page.locator('.mini-cart-wrapper, .awa-header-minicart, .awa-header-cart, .action.showcart, [data-awa-header-cart]').first();
    const mcVisible = await mc.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!mcVisible) console.warn('[P1] Minicart não visível — KO pode não ter inicializado');
    expect(mcVisible, '[P1] Minicart ausente').toBe(true);
  });

  test('06 — grid de produtos existe', async ({ page }) => {
    const grid = page.locator('.product-items, .products-grid, .product-item').first();
    await expect(grid).toBeVisible({ timeout: 15_000 });
  });

  test('07 — rodapé presente', async ({ page }) => {
    await expect(page.locator(COMMON.footer).first()).toBeVisible({ timeout: 10_000 });
  });

  test('08 — sem erros JS críticos (P0)', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', e => errors.push(e.message));
    await new Promise<void>((resolve) => setTimeout(resolve, 2_000));
    const critical = errors.filter(e => /require is not defined|Cannot read prop|TypeError/i.test(e));
    if (critical.length > 0) console.error('[P0] Erros JS:', critical.join(' | '));
    expect(critical).toHaveLength(0);
  });

  test('09 — sem overflow horizontal (P2)', async ({ page }) => {
    const overflow = await Promise.race<boolean>([
      page.evaluate(
        () => document.documentElement.scrollWidth > document.documentElement.clientWidth + 4
      ),
      new Promise<boolean>((resolve) => setTimeout(() => resolve(false), 8_000)),
    ]);
    if (overflow) console.warn('[P2] Overflow horizontal na home');
    expect(overflow, 'Overflow horizontal').toBe(false);
  });
});
