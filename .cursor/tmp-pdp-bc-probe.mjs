#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-bc-probe', ...payload }) + '\n');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(PDP, { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(2000);

const data = await page.evaluate(() => {
  const pm = document.querySelector('.page-main.container, #maincontent');
  const bc = document.querySelector('.breadcrumbs, .nav-breadcrumbs, [class*="breadcrumb"]');
  const all = Array.from(document.querySelectorAll('[class*="breadcrumb"], .nav-breadcrumbs, .breadcrumbs')).map(el => ({
    cls: el.className.toString().slice(0, 80),
    ...(() => { const r=el.getBoundingClientRect(); const cs=getComputedStyle(el); return { w:Math.round(r.width), x:Math.round(r.left), maxW:cs.maxWidth, display:cs.display }; })()
  }));
  return {
    pageMain: pm ? { w: Math.round(pm.getBoundingClientRect().width), x: Math.round(pm.getBoundingClientRect().left) } : null,
    breadcrumbs: all,
    bcInsideMain: bc && pm ? pm.contains(bc) : null,
  };
});

const bc = data.breadcrumbs[0];
const issue = bc && data.pageMain && (Math.abs(bc.x - data.pageMain.x) > 4 || bc.w > data.pageMain.w + 4);
log({ hypothesisId: 'H-bc', location: 'tmp-pdp-bc-probe.mjs', message: issue ? 'BREADCRUMB_WIDTH_MISMATCH' : 'BREADCRUMB_OK', data: { ...data, issue } });
console.log(JSON.stringify({ issue, ...data }, null, 2));
await browser.close();
