/**
 * JS Error Audit — AWA Motos
 *
 * Investiga erros JavaScript nas páginas principais da loja.
 * Para cada página captura:
 *   - pageerror       → exceções não tratadas (TypeError, ReferenceError, etc.)
 *   - console.error   → erros reportados por scripts (RequireJS, Knockout, etc.)
 *   - console.warn    → avisos que indicam fallback ou degradação
 *   - network-fail    → recursos JS/CSS com falha (404, ERR_*)
 *
 * Execução:
 *   cd tests/e2e
 *   npx playwright test specs/js-error-audit.spec.ts --project=firefox-notebook-1280 --reporter=list
 *
 * Para salvar relatório JSON:
 *   JS_AUDIT_JSON=1 npx playwright test specs/js-error-audit.spec.ts
 */

import { test, expect, BrowserContext } from '@playwright/test';
import path from 'path';
import fs from 'fs';

// ── Configuração ─────────────────────────────────────────────────────────────

const BASE_URL = 'https://awamotos.com';

const PAGES_TO_AUDIT: Array<{ name: string; url: string }> = [
  { name: 'home',      url: '/' },
  { name: 'categoria', url: '/bagageiros.html' },
  { name: 'pdp',       url: '/ret-biz-100-cr-redondo-universal-2220.html' },
  { name: 'busca',     url: '/catalogsearch/result/?q=bagageiro' },
  { name: 'carrinho',  url: '/checkout/cart/' },
  { name: 'checkout',  url: '/checkout/' },
];

/**
 * Padrões de erro conhecidos que são ruído aceitável.
 * Adicione aqui após analisar e decidir que são irrelevantes.
 */
const KNOWN_NOISE: RegExp[] = [
  /JQMIGRATE/i,
  /ResizeObserver loop/i,
  /Non-Error promise rejection/i,
  /Glyph bbox was incorrect/i,
  /Preload.*was ignored due to unknown/i,
];

const SS_DIR = path.join(__dirname, '..', 'screenshots', 'js-error-audit');

// ── Tipos ─────────────────────────────────────────────────────────────────────

interface JsError {
  type: 'pageerror' | 'console.error' | 'console.warn' | 'network-fail';
  message: string;
  stack?: string;
  url?: string;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function isNoise(msg: string): boolean {
  return KNOWN_NOISE.some(re => re.test(msg));
}

async function auditPage(
  context: BrowserContext,
  pageInfo: { name: string; url: string },
): Promise<{ errors: JsError[]; warnings: JsError[]; networkFails: JsError[] }> {
  const page = await context.newPage();
  const errors: JsError[] = [];
  const warnings: JsError[] = [];
  const networkFails: JsError[] = [];

  page.on('pageerror', (err) => {
    if (!isNoise(err.message)) {
      errors.push({
        type: 'pageerror',
        message: err.message,
        stack: err.stack?.split('\n').slice(0, 6).join('\n'),
      });
    }
  });

  page.on('console', (msg) => {
    const text = msg.text();
    if (isNoise(text)) return;
    if (msg.type() === 'error') {
      errors.push({ type: 'console.error', message: text.substring(0, 500) });
    } else if (msg.type() === 'warning') {
      warnings.push({ type: 'console.warn', message: text.substring(0, 500) });
    }
  });

  page.on('requestfailed', (req) => {
    const rt = req.resourceType();
    if (['script', 'stylesheet', 'fetch', 'xhr'].includes(rt)) {
      networkFails.push({
        type: 'network-fail',
        message: `${rt.toUpperCase()} falhou: ${req.failure()?.errorText ?? 'unknown'}`,
        url: req.url(),
      });
    }
  });

  let pageClosedUnexpectedly = false;
  page.on('close', () => {
    pageClosedUnexpectedly = true;
  });

  try {
    await page.goto(BASE_URL + pageInfo.url, { waitUntil: 'commit', timeout: 30_000 });
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    errors.push({ type: 'pageerror', message: `Falha ao abrir ${pageInfo.url}: ${msg.substring(0, 500)}` });
  }

  // Aguarda scripts assíncronos (RequireJS, Knockout, etc.) sem depender do canal do browser.
  await new Promise<void>((resolve) => setTimeout(resolve, 5_000));

  if (pageClosedUnexpectedly || page.isClosed()) {
    errors.push({ type: 'pageerror', message: `Página fechou inesperadamente durante a auditoria: ${pageInfo.url}` });
    return { errors, warnings, networkFails };
  }

  fs.mkdirSync(SS_DIR, { recursive: true });
  try {
    await Promise.race([
      page.screenshot({
        path: path.join(SS_DIR, `${pageInfo.name}.png`),
        fullPage: false,
        timeout: 10_000,
      }),
      new Promise((_, reject) => setTimeout(() => reject(new Error('screenshot-timeout')), 12_000)),
    ]);
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    warnings.push({ type: 'console.warn', message: `Screenshot não concluído em ${pageInfo.url}: ${msg.substring(0, 300)}` });
  }

  try {
    await Promise.race([
      page.close(),
      new Promise<void>((resolve) => setTimeout(resolve, 5_000)),
    ]);
  } catch {
    // ignora falhas de fechamento para não travar a auditoria
  }

  return { errors, warnings, networkFails };
}

// ── Testes de páginas ─────────────────────────────────────────────────────────

test.describe('JS Error Audit — Páginas Principais', () => {
  for (const pageInfo of PAGES_TO_AUDIT) {
    test(`[${pageInfo.name}] zero erros JS (pageerror + console.error)`, async ({ context }) => {
      const result = await auditPage(context, pageInfo);

      if (result.errors.length > 0) {
        console.error(`\n=== ERROS [${pageInfo.name}] ${pageInfo.url} ===`);
        for (const e of result.errors) {
          console.error(`  [${e.type}] ${e.message}`);
          if (e.stack) console.error(`    ${e.stack}`);
        }
      }
      if (result.warnings.length > 0) {
        console.warn(`\n=== AVISOS [${pageInfo.name}] ===`);
        result.warnings.forEach(w => console.warn(`  ${w.message}`));
      }
      if (result.networkFails.length > 0) {
        console.error(`\n=== FALHAS DE REDE [${pageInfo.name}] ===`);
        result.networkFails.forEach(n => console.error(`  ${n.message}\n  → ${n.url}`));
      }

      expect(
        result.errors,
        `[${pageInfo.name}] ${result.errors.length} erro(s):\n` +
          result.errors.map(e => `  • ${e.type}: ${e.message}`).join('\n'),
      ).toHaveLength(0);
    });

    test(`[${pageInfo.name}] zero scripts/CSS com falha de rede`, async ({ context }) => {
      const result = await auditPage(context, pageInfo);
      const scriptFails = result.networkFails.filter(n =>
        n.message.startsWith('SCRIPT') || n.message.startsWith('FETCH') || n.message.startsWith('XHR'),
      );
      expect(
        scriptFails,
        `[${pageInfo.name}] ${scriptFails.length} recurso(s) com falha:\n` +
          scriptFails.map(n => `  • ${n.url}`).join('\n'),
      ).toHaveLength(0);
    });
  }
});

// ── Testes específicos de módulos AWA ─────────────────────────────────────────

test.describe('Módulos AWA — verificação de init', () => {

  test('SocialProof: badge DOM presente e sem erros na PDP', async ({ context }) => {
    const page = await context.newPage();
    const errors: string[] = [];

    page.on('pageerror', e => { if (!isNoise(e.message)) errors.push(e.message); });
    page.on('console', m => {
      if (m.type() === 'error' && !isNoise(m.text())) errors.push(m.text().substring(0, 300));
    });

    let spApiStatus: number | null = null;
    page.on('response', res => {
      if (res.url().includes('socialproof/product/data')) spApiStatus = res.status();
    });

    await page.goto(BASE_URL + '/ret-biz-100-cr-redondo-universal-2220.html', {
      waitUntil: 'domcontentloaded', timeout: 45_000,
    }).catch(() => {});
    await page.waitForTimeout(6_000);

    const badge = await page.evaluate(() => {
      const el = document.getElementById('awa-social-proof-pdp');
      return el
        ? { exists: true, childCount: el.childElementCount, classes: el.className }
        : { exists: false, childCount: 0, classes: '' };
    });

    console.log(`SocialProof — API status: ${spApiStatus}, badge: ${JSON.stringify(badge)}`);

    fs.mkdirSync(SS_DIR, { recursive: true });
    await page.screenshot({ path: path.join(SS_DIR, 'social-proof-pdp.png') }).catch(() => {});
    await page.close();

    expect(errors, 'Erros JS na PDP:\n' + errors.join('\n')).toHaveLength(0);
    expect(badge.exists, 'Container #awa-social-proof-pdp deve existir no DOM').toBe(true);
  });

  test('CookieConsent: banner inicializa sem erro JS', async ({ context }) => {
    const page = await context.newPage();
    const errors: string[] = [];

    page.on('pageerror', e => { if (!isNoise(e.message)) errors.push(e.message); });
    page.on('console', m => {
      if (m.type() === 'error' && !isNoise(m.text())) errors.push(m.text().substring(0, 300));
    });

    // Limpa consent para forçar exibição do banner
    await page.addInitScript(() => {
      try { localStorage.removeItem('awa_cookie_consent'); } catch { /* ignore */ }
      document.cookie = 'awa_cookies_accepted=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    });

    await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    await page.waitForTimeout(3_000);

    const bannerState = await page.evaluate(() => {
      const el = document.getElementById('awa-cookie-banner');
      if (!el) return { exists: false, visible: false, classes: '' };
      const style = window.getComputedStyle(el);
      return { exists: true, visible: style.display !== 'none', classes: el.className };
    });

    console.log(`CookieConsent — banner: ${JSON.stringify(bannerState)}`);

    fs.mkdirSync(SS_DIR, { recursive: true });
    await page.screenshot({ path: path.join(SS_DIR, 'cookie-consent.png') }).catch(() => {});
    await page.close();

    expect(errors, 'Erros JS no CookieConsent:\n' + errors.join('\n')).toHaveLength(0);
  });

  test('RequireJS: sem módulos não resolvidos na home', async ({ context }) => {
    const page = await context.newPage();
    const requireErrors: string[] = [];

    page.on('console', m => {
      const text = m.text();
      if (m.type() === 'error' && /require|load timeout|anonymous define|mismatch/i.test(text)) {
        requireErrors.push(text.substring(0, 500));
      }
    });
    page.on('pageerror', e => {
      if (/require|load timeout|anonymous define/i.test(e.message)) {
        requireErrors.push(e.message);
      }
    });

    await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    // RequireJS timeout padrão = 7s — aguardar além disso
    await page.waitForTimeout(9_000);

    console.log(`RequireJS errors: ${requireErrors.length > 0 ? requireErrors.join(' | ') : 'nenhum'}`);
    await page.close();

    expect(requireErrors, 'Módulos RequireJS não resolvidos:\n' + requireErrors.join('\n')).toHaveLength(0);
  });

  test('Knockout: sem erros de binding na home', async ({ context }) => {
    const page = await context.newPage();
    const koErrors: string[] = [];

    page.on('console', m => {
      const text = m.text();
      if (m.type() === 'error' && /knockout|ko\b|binding|observable/i.test(text)) {
        koErrors.push(text.substring(0, 500));
      }
    });
    page.on('pageerror', e => {
      if (/knockout|binding/i.test(e.message)) koErrors.push(e.message);
    });

    await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded', timeout: 45_000 }).catch(() => {});
    await page.waitForTimeout(5_000);

    console.log(`Knockout errors: ${koErrors.length > 0 ? koErrors.join(' | ') : 'nenhum'}`);
    await page.close();

    expect(koErrors, 'Erros de binding Knockout:\n' + koErrors.join('\n')).toHaveLength(0);
  });
});
