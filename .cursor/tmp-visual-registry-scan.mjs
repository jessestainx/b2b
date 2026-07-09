#!/usr/bin/env node
/** Registry scan — _awa-bugfix-visual-2026-06.less key bugs */
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const BASE = 'https://awamotos.com';

function log(hypothesisId, message, data, ok) {
  fs.appendFileSync(LOG, JSON.stringify({
    sessionId: '199043', timestamp: Date.now(), runId: 'registry-scan',
    hypothesisId, location: 'tmp-visual-registry-scan.mjs', message, data: { ...data, ok },
  }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const results = [];

async function go(url, vp) {
  await page.setViewportSize(vp);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(2000);
}

// BUG-C1 desktop scroll
await go(BASE + '/', { width: 1280, height: 800 });
const c1 = await page.evaluate(() => {
  window.scrollTo(0, 400);
  const logo = document.querySelector('.awa-site-header .logo img, .awa-site-header .logo');
  if (!logo) return { ok: false, reason: 'no-logo' };
  const r = logo.getBoundingClientRect();
  const cs = getComputedStyle(logo);
  return { ok: r.height > 20 && cs.visibility !== 'hidden', logoH: Math.round(r.height), vis: cs.visibility };
});
results.push({ id: 'BUG-C1', ...c1 });
log('BUG-C1', c1.ok ? 'OK' : 'FAIL', c1, c1.ok);

// BUG-C2 PDP gallery
await go(BASE + '/lente-mini-pisca-titan-2000-lisa-vm.html', { width: 1280, height: 800 });
const c2 = await page.evaluate(() => {
  const ph = document.querySelector('.gallery-placeholder__image, .gallery-placeholder img');
  const fot = document.querySelector('.fotorama-item');
  const phCs = ph ? getComputedStyle(ph) : null;
  return {
    ok: !ph || !fot || phCs?.display === 'none' || phCs?.visibility === 'hidden' || phCs?.height === '0px',
    imgDisplay: phCs?.display, hasFotorama: !!fot,
  };
});
results.push({ id: 'BUG-C2', ...c2 });
log('BUG-C2', c2.ok ? 'OK' : 'FAIL', c2, c2.ok);

// BUG-01 404 button
await go(BASE + '/pagina-xyz-inexistente-404', { width: 375, height: 812 });
const b01 = await page.evaluate(() => {
  const btn = document.querySelector('.awa-404-page__links a:first-child, .cms-no-route .actions a.action');
  if (!btn) return { ok: false, reason: 'no-btn' };
  const r = btn.getBoundingClientRect();
  const cs = getComputedStyle(btn);
  const bg = cs.backgroundColor;
  const color = cs.color;
  return { ok: r.height >= 44 && r.width >= 44 && btn.offsetParent !== null, text: btn.textContent?.trim(), h: Math.round(r.height), bg, color };
});
results.push({ id: 'BUG-01', ...b01 });
log('BUG-01', b01.ok ? 'OK' : 'FAIL', b01, b01.ok);

// BUG-02 PLP footer gap
await go(BASE + '/pecas.html', { width: 1280, height: 800 });
const b02 = await page.evaluate(() => {
  const main = document.querySelector('.page-main, main.page-main');
  const footer = document.querySelector('.page-footer, footer.page-footer');
  if (!main || !footer) return { ok: false, reason: 'missing' };
  const gap = footer.getBoundingClientRect().top - main.getBoundingClientRect().bottom;
  return { ok: gap >= 40 && gap <= 150, gap: Math.round(gap) };
});
results.push({ id: 'BUG-02-plp', ...b02 });
log('BUG-02', b02.ok ? 'OK' : 'FAIL', { surface: 'plp', ...b02 }, b02.ok);

// BUG-03 promo tablet
await page.setViewportSize({ width: 900, height: 800 });
await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(1500);
const b03 = await page.evaluate(() => {
  const bar = document.querySelector('.awa-promo-bar, .promo-bar, [class*="promo-bar"]');
  if (!bar) return { ok: true, reason: 'no-promo-bar' };
  const cs = getComputedStyle(bar);
  return { ok: cs.flexWrap === 'wrap' || cs.flexWrap === 'wrap-reverse', flexWrap: cs.flexWrap, barH: Math.round(bar.getBoundingClientRect().height) };
});
results.push({ id: 'BUG-03', ...b03 });
log('BUG-03', b03.ok ? 'OK' : 'FAIL', b03, b03.ok);

// BUG-04 account prompt
await page.setViewportSize({ width: 1280, height: 800 });
await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(1500);
const b04 = await page.evaluate(() => {
  const el = document.querySelector('.awa-header-account-prompt');
  if (!el) return { ok: false, reason: 'missing' };
  return { ok: el.scrollHeight <= el.clientHeight, scrollH: el.scrollHeight, clientH: el.clientHeight, height: getComputedStyle(el).height };
});
results.push({ id: 'BUG-04', ...b04 });
log('BUG-04', b04.ok ? 'OK' : 'FAIL', b04, b04.ok);

// BUG-06 nav departamentos
const b06 = await page.evaluate(() => {
  const btn = document.querySelector('.our_category, .awa-nav-categories button, [class*="our_category"]');
  if (!btn) return { ok: true, reason: 'no-btn' };
  const cs = getComputedStyle(btn);
  return { ok: cs.textOverflow !== 'ellipsis' || btn.scrollWidth <= btn.clientWidth + 2, text: btn.textContent?.trim()?.slice(0, 30) };
});
results.push({ id: 'BUG-06', ...b06 });
log('BUG-06', b06.ok ? 'OK' : 'FAIL', b06, b06.ok);

// BUG-07 PLP top pagination
await page.goto(BASE + '/pecas.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(2000);
const b07 = await page.evaluate(() => {
  const toolbars = document.querySelectorAll('.toolbar.toolbar-products');
  const top = toolbars[0];
  if (!top) return { ok: true, reason: 'no-toolbar' };
  const pages = top.querySelector('.pages');
  return { ok: !pages, hasTopPages: !!pages };
});
results.push({ id: 'BUG-07', ...b07 });
log('BUG-07', b07.ok ? 'OK' : 'FAIL', b07, b07.ok);

// BUG-10 home footer gap
await page.setViewportSize({ width: 1280, height: 800 });
await page.goto(BASE + '/', { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(2500);
const b10 = await page.evaluate(() => {
  const shelves = document.querySelectorAll('.content-top-home .awa-shelf--carousel:has(.awa-carousel__track), .content-top-home .rokan-bestseller');
  const footer = document.querySelector('.page-footer, footer.page-footer');
  let lastBottom = 0;
  shelves.forEach((el) => { lastBottom = Math.max(lastBottom, el.getBoundingClientRect().bottom + window.scrollY); });
  if (!footer || !lastBottom) {
    const main = document.querySelector('.content-top-home, main.page-main');
    if (main && footer) {
      const gap = footer.getBoundingClientRect().top - main.getBoundingClientRect().bottom;
      return { ok: gap >= 32 && gap <= 120, gap: Math.round(gap), method: 'main' };
    }
    return { ok: false, reason: 'missing' };
  }
  const gap = footer.getBoundingClientRect().top + window.scrollY - lastBottom;
  return { ok: gap >= 32 && gap <= 120, gap: Math.round(gap), method: 'shelf' };
});
results.push({ id: 'BUG-10', ...b10 });
log('BUG-10', b10.ok ? 'OK' : 'FAIL', b10, b10.ok);

// BUG-34 PDP width
await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(BASE + '/lente-mini-pisca-titan-2000-lisa-vm.html', { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(2000);
const b34 = await page.evaluate(() => {
  const pm = document.querySelector('.page-main.container');
  const col = document.querySelector('.column.main, .col-main');
  if (!pm || !col) return { ok: false, reason: 'missing' };
  const fill = col.getBoundingClientRect().width >= pm.getBoundingClientRect().width - 40;
  const layout = document.body.className.match(/page-layout-\S+/)?.[0];
  return { ok: fill && layout === 'page-layout-1column', colW: Math.round(col.getBoundingClientRect().width), pmW: Math.round(pm.getBoundingClientRect().width), layout };
});
results.push({ id: 'BUG-34', ...b34 });
log('BUG-34', b34.ok ? 'OK' : 'FAIL', b34, b34.ok);

await browser.close();

const failed = results.filter(r => !r.ok);
log('SCAN_SUMMARY', failed.length ? 'HAS_FAILURES' : 'ALL_OK', { failed: failed.map(f => f.id), total: results.length }, failed.length === 0);
console.log(JSON.stringify({ failed: failed.map(f => ({ id: f.id, ...f })), passed: results.filter(r => r.ok).length, total: results.length }, null, 2));
process.exit(failed.length ? 1 : 0);
