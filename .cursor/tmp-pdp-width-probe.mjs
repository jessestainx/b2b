#!/usr/bin/env node
/** PDP width probe — runtime evidence for container/column chain */
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = process.env.AWA_PDP_URL || 'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-width-probe', ...payload }) + '\n');
}

function probe(page) {
  return page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const sel = [
      '.page-wrapper',
      '.nav-breadcrumbs',
      '#maincontent.page-main',
      '.page-main.container',
      '.page-main > .columns',
      '.col-main',
      '.column.main',
      '.product-view',
      '.main-detail',
      '.main-detail > .row',
      '.product-info-main',
      '.product.media',
    ];
    const chain = sel.map((s) => {
      const el = document.querySelector(s);
      if (!el) return { sel: s, missing: true };
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return {
        sel: s,
        w: Math.round(r.width),
        x: Math.round(r.left),
        right: Math.round(r.right),
        maxW: cs.maxWidth,
        width: cs.width,
        padL: cs.paddingLeft,
        padR: cs.paddingRight,
        marginL: cs.marginLeft,
        marginR: cs.marginRight,
        display: cs.display,
        boxSizing: cs.boxSizing,
      };
    });
    const unusedL = chain.find(c => c.sel === '.page-main.container')?.x ?? chain.find(c => c.sel === '#maincontent.page-main')?.x ?? 0;
    const mainEl = document.querySelector('.page-main.container, #maincontent.page-main');
    const unusedR = mainEl ? vw - mainEl.getBoundingClientRect().right : null;
    const row = document.querySelector('.main-detail > .row');
    let colSplit = null;
    if (row) {
      const cols = row.querySelectorAll(':scope > .col-md-6');
      colSplit = Array.from(cols).map((c, i) => {
        const r = c.getBoundingClientRect();
        return { i, w: Math.round(r.width), pct: Math.round((r.width / row.getBoundingClientRect().width) * 100) };
      });
    }
    return { vw, unusedL: Math.round(unusedL), unusedR: unusedR != null ? Math.round(unusedR) : null, chain, colSplit };
  });
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

for (const vp of [{ w: 1440, h: 900, label: 'desktop-1440' }, { w: 1280, h: 800, label: 'desktop-1280' }, { w: 375, h: 812, label: 'mobile-375' }]) {
  await page.setViewportSize({ width: vp.w, height: vp.h });
  await page.goto(PDP, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('.product-view, .main-detail', { timeout: 30000 }).catch(() => {});
  await page.waitForTimeout(2000);
  const data = await probe(page);
  const main = data.chain.find(c => c.sel === '.page-main.container' || c.sel === '#maincontent.page-main');
  const colMain = data.chain.find(c => c.sel === '.col-main' || c.sel === '.column.main');
  const row = data.chain.find(c => c.sel === '.main-detail > .row');
  const issue = data.unusedL > 24 || data.unusedR > 24 || (main && main.w < data.vw * 0.85) || (colMain && colMain.w < (main?.w ?? data.vw) * 0.9);
  log({
    hypothesisId: 'PDP-WIDTH',
    location: 'tmp-pdp-width-probe.mjs',
    message: issue ? 'PDP_WIDTH_ISSUE' : 'PDP_WIDTH_OK',
    data: { viewport: vp.label, ...data, issue },
  });
  console.log(JSON.stringify({ viewport: vp.label, vw: data.vw, unusedL: data.unusedL, unusedR: data.unusedR, mainW: main?.w, colMainW: colMain?.w, rowW: row?.w, colSplit: data.colSplit, issue }, null, 2));
}

await browser.close();
