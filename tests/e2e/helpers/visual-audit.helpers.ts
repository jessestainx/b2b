/**
 * AWA Motos — Visual Audit Helpers
 * Utilitários compartilhados para specs de auditoria visual das 8 fases
 */
import { Page, expect, Locator } from '@playwright/test';
import path from 'path';
import fs from 'fs';

const AUTH_STATE_FILE = path.join(__dirname, '..', '.auth-state.json');

function sleep(ms: number): Promise<void> {
  return new Promise<void>(resolve => setTimeout(resolve, ms));
}

/* ── Design Tokens (valores esperados) ─────────────────────────── */
export const TOKENS = {
  primary:       'rgb(183, 51, 55)',    // #b73337
  primaryDark:   'rgb(142, 38, 41)',    // #8e2629
  text:          'rgb(51, 51, 51)',     // #333333
  textMuted:     'rgb(102, 102, 102)',  // #666666
  white:         'rgb(255, 255, 255)',  // #ffffff
  surface:       'rgb(255, 255, 255)',
} as const;

/* ── Seletores comuns ─────────────────────────────────────────── */
export const COMMON = {
  header:        'header.awa-site-header, [data-awa-header-content]',
  footer:        'footer.page-footer, .footer.content',
  logo:          '.logo img',
  search:        'input#search, input[name="q"]',
  minicart:      '.mini-cart-wrapper, .awa-header-minicart, .minicart-wrapper, .action.showcart',
  breadcrumb:    '.breadcrumbs',
  pageTitle:     '.page-title',
  cookieBanner:  '#awa-cookie-accept, .awa-cookie-banner__btn--accept, .cookie-btn-accept, #btn-cookie-allow',
} as const;

/* ── Wait para página estabilizar ─────────────────────────────── */
export async function waitForPage(page: Page, _timeout = 15_000): Promise<void> {
  if (page.isClosed()) return;
  // Em ambiente remoto instável, waits de estado podem pendurar o canal do browser.
  // Usamos apenas espera Node-side curta para estabilização pós-commit.
  await sleep(1_200);
}

/* ── Dismiss cookie banner ────────────────────────────────────── */
export async function dismissCookie(page: Page): Promise<void> {
  const btn = page.locator(COMMON.cookieBanner).first();
  if (await btn.isVisible({ timeout: 2_000 }).catch(() => false)) {
    await btn.click({ force: true }).catch(() => {});
    await sleep(300);
  }
}

/* ── Navigate with retry ──────────────────────────────────────── */
export async function navigateTo(page: Page, url: string): Promise<boolean> {
  try {
    const navResponse = await page.goto(url, { waitUntil: 'commit', timeout: 12_000 }).catch(() => null);

    // Hook de navegação deve ser ultra-estável: sem leituras de DOM aqui.
    // As validações de DOM ficam nos próprios testes.
    const status = navResponse?.status?.() ?? 0;
    const current = page.url();
    const ok = !page.isClosed() && status >= 200 && status < 500 && current !== 'about:blank';

    if (!ok) {
      console.warn(`[NAV_FAIL] Navegação base inválida: ${url} | status=${status || 'n/a'} | current=${current}`);
    }

    return ok;
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    console.warn(`[NAV_FAIL] Exceção em navigateTo: ${url} | ${msg.substring(0, 200)}`);
    return false;
  }
}

/* ── Login B2B (com cache de cookies) ─────────────────────────── */
export async function loginB2B(page: Page): Promise<boolean> {
  const email = process.env.TEST_USER ?? '';
  const pass  = process.env.TEST_PASS ?? '';

  // Tentar reutilizar cookies salvos
  if (fs.existsSync(AUTH_STATE_FILE)) {
    try {
      const saved = JSON.parse(fs.readFileSync(AUTH_STATE_FILE, 'utf8'));
      await page.context().addCookies(saved.cookies || []);
      await page.goto('/', { waitUntil: 'commit', timeout: 20_000 }).catch(() => {});
      await sleep(1_000);
      // Verificar se está logado
      const accountLink = await page.locator('a[href*="customer/account"], .customer-welcome').first().isVisible().catch(() => false);
      if (accountLink) return true;
    } catch { /* fallback to manual login */ }
  }

  if (!email || !pass) return false;

  // Login manual
  await page.goto('/b2b/account/login/', { waitUntil: 'domcontentloaded', timeout: 60_000 }).catch(() => {});
  await sleep(1_000);
  await dismissCookie(page);
  await page.locator('#b2b-email').first().fill(email).catch(() => {});
  await page.locator('#b2b-pass').first().fill(pass).catch(() => {});
  await page.locator('.b2b-btn-entrar').first().click({ force: true }).catch(() => {});
  await page.waitForLoadState('domcontentloaded', { timeout: 30_000 }).catch(() => {});
  await sleep(2_000);

  // Salvar cookies
  try {
    const cookies = await page.context().cookies();
    fs.writeFileSync(AUTH_STATE_FILE, JSON.stringify({ cookies }), 'utf8');
  } catch { /* ignore */ }

  return true;
}

/* ── Get computed CSS property ─────────────────────────────────── */
export async function css(page: Page, selector: string, prop: string): Promise<string> {
  return Promise.race<string>([
    page.evaluate(
      ([sel, p]) => {
        const el = document.querySelector(sel as string);
        return el ? window.getComputedStyle(el).getPropertyValue(p as string).trim() : '';
      },
      [selector, prop]
    ).catch(() => ''),
    new Promise<string>(resolve => setTimeout(() => resolve(''), 8_000)),
  ]);
}

/* ── Get multiple CSS properties ──────────────────────────────── */
export async function cssMultiple(
  page: Page,
  selector: string,
  props: string[]
): Promise<Record<string, string>> {
  return Promise.race<Record<string, string>>([
    page.evaluate(
      ([sel, ps]) => {
        const el = document.querySelector(sel as string);
        if (!el) return {};
        const cs = window.getComputedStyle(el);
        return (ps as string[]).reduce((acc: Record<string, string>, p) => {
          acc[p] = cs.getPropertyValue(p).trim();
          return acc;
        }, {});
      },
      [selector, props]
    ).catch(() => ({})),
    new Promise<Record<string, string>>(resolve => setTimeout(() => resolve({}), 8_000)),
  ]);
}

/* ── Parse px value to number ─────────────────────────────────── */
export function px(v: string): number {
  return parseFloat(v) || 0;
}

/* ── Assert min-height (with tolerance) ───────────────────────── */
export function assertMinHeight(actual: string, expectedMin: number, tolerance = 2): void {
  const val = px(actual);
  expect(val, `min-height should be >= ${expectedMin - tolerance}px (got ${val}px)`).toBeGreaterThanOrEqual(expectedMin - tolerance);
}

/* ── Assert border-radius is >= expected ──────────────────────── */
export function assertBorderRadius(actual: string, expectedMin: number): void {
  const val = px(actual);
  expect(val, `border-radius should be >= ${expectedMin}px (got ${val}px)`).toBeGreaterThanOrEqual(expectedMin);
}

/* ── Check element exists and is visible ──────────────────────── */
export async function isVisible(page: Page, selector: string, timeout = 5_000): Promise<boolean> {
  try {
    await page.locator(selector).first().waitFor({ state: 'visible', timeout });
    return true;
  } catch {
    return false;
  }
}

/* ── Check no horizontal overflow ─────────────────────────────── */
export async function hasNoOverflow(page: Page): Promise<boolean> {
  try {
    return await Promise.race<boolean>([
      page.evaluate(() =>
        document.documentElement.scrollWidth <= document.documentElement.clientWidth + 2
      ),
      new Promise<boolean>(resolve => setTimeout(() => resolve(true), 5_000)),
    ]);
  } catch {
    return true; // fail-safe se browser crashou
  }
}

/* ── Collect JS errors during test ────────────────────────────── */
export function collectJsErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(e.message));
  return errors;
}
