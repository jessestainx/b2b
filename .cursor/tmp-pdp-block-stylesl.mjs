#!/usr/bin/env node
import { chromium } from 'playwright';
import fs from 'fs';

const LOG = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-199043.log';
const PDP = 'https://awamotos.com/lente-mini-pisca-titan-2000-lisa-vm.html';

function log(payload) {
  fs.appendFileSync(LOG, JSON.stringify({ sessionId: '199043', timestamp: Date.now(), runId: 'pdp-block-stylesl', ...payload }) + '\n');
}

async function measure(page) {
  return page.evaluate(() => {
    const cm = document.querySelector('.col-main');
    const cols = document.querySelector('.page-main > .columns');
    const cs = cols ? getComputedStyle(cols) : null;
    return {
      colMainW: cm ? Math.round(cm.getBoundingClientRect().width) : null,
      columnsW: cols ? Math.round(cols.getBoundingClientRect().width) : null,
      columnsDisplay: cs?.display,
      gridCols: cs?.gridTemplateColumns,
    };
  });
}

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
await ctx.route('**/*', (route) => {
  const u = route.request().url();
  if (/styles-l\.css|styles-m\.css/i.test(u)) return route.abort();
  return route.continue();
});
const page = await ctx.newPage();
await page.goto(PDP + '?nostylesl=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(1500);
const early = await measure(page);
await page.waitForTimeout(3000);
const late = await measure(page);

const issue = (early.colMainW && early.colMainW < 900) || (late.colMainW && late.colMainW < 900);
log({ hypothesisId: 'H-stylesl', location: 'tmp-pdp-block-stylesl.mjs', message: issue ? 'PDP_NARROW_WITHOUT_STYLESL' : 'PDP_OK_WITHOUT_STYLESL', data: { early, late, issue } });
console.log(JSON.stringify({ issue, early, late }, null, 2));
await browser.close();
