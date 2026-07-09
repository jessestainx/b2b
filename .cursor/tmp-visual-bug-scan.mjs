#!/usr/bin/env node
/** Visual bug registry scan — _awa-bugfix-visual-2026-06.less */
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const BASE = process.env.AWA_BASE_URL || 'https://awamotos.com/';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'post-fix-scan', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

async function scan(name, url, vp, fn) {
  await page.setViewportSize(vp);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(1500);
  const data = await page.evaluate(fn);
  const ok = data.ok !== false;
  log({ hypothesisId: name, location: 'tmp-visual-bug-scan.mjs', message: ok ? `${name}_OK` : `${name}_FAIL`, data });
  return { name, ok, data };
}

const results = [];

// BUG-04 desktop account prompt
results.push(await scan('BUG-04', BASE, { width: 1280, height: 800 }, () => {
  const el = document.querySelector('.awa-header-account-prompt');
  if (!el) return { ok: false, reason: 'missing' };
  return { ok: el.scrollHeight <= el.clientHeight, scrollH: el.scrollHeight, clientH: el.clientHeight, height: getComputedStyle(el).height };
}));

// BUG-C1 logo visible after scroll
results.push(await scan('BUG-C1', BASE, { width: 1280, height: 800 }, () => {
  window.scrollTo(0, 400);
  const logo = document.querySelector('.awa-site-header .logo img, .awa-site-header .logo');
  if (!logo) return { ok: false, reason: 'missing' };
  const r = logo.getBoundingClientRect();
  const cs = getComputedStyle(logo);
  return { ok: r.height > 20 && cs.visibility !== 'hidden' && cs.opacity !== '0', logoH: r.height, vis: cs.visibility, op: cs.opacity };
}));

// BUG-01 404 button
results.push(await scan('BUG-01', BASE + 'pagina-inexistente-xyz', { width: 375, height: 812 }, () => {
  const btn = document.querySelector('.cms-no-route .actions a, .page-not-found a.action, a[href="/"]');
  if (!btn) return { ok: false, reason: 'missing' };
  const r = btn.getBoundingClientRect();
  return { ok: r.height >= 44 && r.width >= 44, text: btn.textContent?.trim(), h: r.height };
}));

// BUG-10 home footer gap
results.push(await scan('BUG-10', BASE, { width: 1280, height: 800 }, () => {
  const main = document.querySelector('main.page-main, .column.main');
  const footer = document.querySelector('.page-footer, footer.page-footer');
  if (!main || !footer) return { ok: false, reason: 'missing' };
  const gap = footer.getBoundingClientRect().top - main.getBoundingClientRect().bottom;
  return { ok: gap >= 40 && gap <= 120, gap: Math.round(gap) };
}));

await browser.close();
const failed = results.filter(r => !r.ok);
console.log(JSON.stringify({ total: results.length, failed: failed.length, results }, null, 2));
log({ hypothesisId: 'SCAN_SUMMARY', location: 'tmp-visual-bug-scan.mjs', message: failed.length ? 'SCAN_HAS_FAILURES' : 'SCAN_ALL_OK', data: { failed: failed.map(f => f.name) } });
process.exit(failed.length ? 1 : 0);
