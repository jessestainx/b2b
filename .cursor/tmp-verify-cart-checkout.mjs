/**
 * Verifica acesso HTTP + render do carrinho e fluxo checkout (Playwright headless).
 * Uso: node .cursor/tmp-verify-cart-checkout.mjs
 */
import { chromium } from 'playwright';
import fs from 'fs';

const BASE = 'https://awamotos.com';
const OUT = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/tmp-cart-checkout-verify.json';

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

const results = {};

async function probe(name, url, checks) {
  const entry = { url, ok: false, error: null, checks: {} };
  try {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    entry.status = resp?.status() ?? null;
    entry.finalUrl = page.url();
    await page.waitForTimeout(1200);
    entry.checks = await page.evaluate(checks);
    entry.ok = entry.status === 200 && Object.values(entry.checks).every(Boolean);
  } catch (e) {
    entry.error = String(e?.message || e);
  }
  results[name] = entry;
}

await probe(`${BASE}/checkout/cart/`, `${BASE}/checkout/cart/`, () => {
  const body = document.body;
  return {
    hasCartBodyClass: body.classList.contains('checkout-cart-index'),
    hasMain: !!document.querySelector('.page-main'),
    hasEmptyOrCart:
      !!document.querySelector('.awa-cart-empty') ||
      !!document.querySelector('.cart-container .form-cart'),
    requireJs: !!document.querySelector('script[src*="require.js"]'),
  };
});

await probe('expresscheckout', `${BASE}/expresscheckout.html`, () => {
  const onCart = window.location.pathname.includes('/checkout/cart');
  const notice = !!document.querySelector('.awa-cart-empty__notice');
  const empty = !!document.querySelector('.awa-cart-empty');
  return {
    redirectedToCart: onCart,
    showsEmptyCart: empty,
    expressNotice: notice,
  };
});

await probe('checkout_index', `${BASE}/checkout/`, () => {
  const onCart = window.location.pathname.includes('/checkout/cart');
  const onOpc =
    document.body.classList.contains('rokanthemes-onepagecheckout') ||
    document.body.classList.contains('checkout-index-index');
  return {
    landedOnCartOrOpc: onCart || onOpc,
    onCart,
    onOpc,
  };
});

await browser.close();
fs.writeFileSync(OUT, JSON.stringify(results, null, 2));
console.log(JSON.stringify(results, null, 2));
