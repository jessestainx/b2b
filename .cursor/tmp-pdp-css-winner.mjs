#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-css-winner', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PDP + '?csswin=' + Date.now(), { waitUntil: 'networkidle', timeout: 120000 }).catch(() => {});
await page.waitForTimeout(3000);

const data = await page.evaluate(() => {
  const targets = [
    '.page-main.container',
    '.page-main > .columns',
    '.col-main',
    '.product-view',
    '.nav-breadcrumbs',
    '.page-title-wrapper',
  ];
  return targets.map((sel) => {
    const el = document.querySelector(sel);
    if (!el) return { sel, missing: true };
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    return {
      sel,
      w: Math.round(r.width),
      x: Math.round(r.left),
      maxW: cs.maxWidth,
      width: cs.width,
      padL: cs.paddingLeft,
      padR: cs.paddingRight,
      marginL: cs.marginLeft,
    };
  });
});

const pageMain = data.find(d => d.sel === '.page-main.container');
const columns = data.find(d => d.sel === '.page-main > .columns');
const bc = data.find(d => d.sel === '.nav-breadcrumbs');
const issues = [];
if (columns && pageMain && columns.w < pageMain.w - 48) issues.push('columns_narrower_than_page_main');
if (bc && pageMain && Math.abs(bc.x - pageMain.x) > 2) issues.push('breadcrumbs_misaligned');
if (pageMain && pageMain.w < 1200 && document.documentElement.clientWidth >= 1440) issues.push('page_main_too_narrow_at_1440');

log({ hypothesisId: 'H-css-win', location: 'tmp-pdp-css-winner.mjs', message: issues.length ? 'PDP_CSS_CONFLICT' : 'PDP_CSS_OK', data: { chain: data, vw: 1440, issues } });
console.log(JSON.stringify({ issues, chain: data }, null, 2));
await browser.close();
